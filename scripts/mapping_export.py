import pandas as pd
import sys
import json
import re
import os
import uuid
from rapidfuzz import process, fuzz

# =========================
# NORMALIZE TEXT
# =========================
def normalize(text):
    if not text or pd.isna(text):
        return ""
    text = str(text).lower()
    text = re.sub(r'[^a-z0-9 ]', ' ', text)
    text = re.sub(r'\s+', ' ', text)
    return text.strip()

# =========================
# EXTRACT NUMBER
# =========================
def extract_number(val):
    nums = re.findall(r'\d+', str(val))
    return int(nums[0]) if nums else 0

# =========================
# ROUND KARTON
# =========================
def round_karton(val):
    return int(val + 0.5)

# =========================
# DETECT NAMA
# =========================
def detect_nama(cols):
    keywords = ["nama", "item", "barang", "description"]
    for col in cols:
        for k in keywords:
            if k in col.lower():
                return col
    return None

# =========================
# MAIN
# =========================
def run():
    try:
        input_data = json.loads(sys.stdin.read())

        file_path = input_data["file_path"]
        sheet_name = input_data.get("source_sheet", 0)
        mapping = input_data.get("mapping", {})
        master_items = input_data["master_data"]
        base_dir = input_data["base_path"]
        print("MAPPING DARI VUE:", mapping)

        # =========================
        # MASTER
        # =========================
        master = pd.DataFrame(master_items)

        if master.empty:
            print(json.dumps({"error": "Master kosong"}))
            return

        master = master.rename(columns={
            "item_code": "kd_barang",
            "item_name": "nama_barang",
            "item_per_box": "item_per_box",
            "item_group": "item_group"
        })

        master["clean"] = master["nama_barang"].apply(normalize)

        # =========================
        # AGENT
        # =========================
        agent = pd.read_excel(file_path, sheet_name=sheet_name)

        # =========================
        # MAPPING DARI VUE (INI KUNCI)
        # =========================
        kode_col = mapping.get("Kode SKU Agent")
        stock_col = mapping.get("Stock Akhir Agent")

        if not kode_col or not stock_col:
            # print(json.dumps({"error": "Mapping belum lengkap"}))
            print(json.dumps({"error": "Kolom KODE wajib dipilih"}))
            return

        # rename sesuai colab
        agent = agent.rename(columns={
            kode_col: "kode_agent",
            stock_col: "stock_pcs"
        })

        # =========================
        # DETECT NAMA
        # =========================
        nama_col = detect_nama(agent.columns)
        
        if not nama_col:
        # fallback pakai kode sebagai nama
            agent["nama_agent"] = agent["kode_agent"].astype(str)
        else:
            agent = agent.rename(columns={nama_col: "nama_agent"})

        # if not nama_col:
        #     print(json.dumps({"error": "Kolom nama tidak ditemukan"}))
        #     return

        agent = agent.rename(columns={nama_col: "nama_agent"})

        # =========================
        # CLEAN
        # =========================
        agent["clean"] = agent["nama_agent"].apply(normalize)
        agent["stock_pcs"] = agent["stock_pcs"].apply(extract_number)

        master_list = master["clean"].tolist()

        # =========================
        # MATCHING (SAMA PERSIS COLAB)
        # =========================
        results = []

        for _, row in agent.iterrows():
            m = process.extractOne(
                row["clean"],
                master_list,
                scorer=fuzz.token_sort_ratio
            )

            pcs = row["stock_pcs"]

            if m:
                _, score, idx = m
                m_row = master.iloc[idx]

                try:
                    per_box = float(m_row["item_per_box"])
                    karton = pcs / per_box if per_box > 0 else 0
                except:
                    per_box = 0
                    karton = 0

                results.append({
                    "Kode Agent": row["kode_agent"],
                    "Item Code": m_row["kd_barang"],
                    "Item Name": m_row["nama_barang"],
                    "Item / Box": per_box,
                    "Stock PCS": pcs,
                    "Stock Karton": round_karton(karton),
                    "Item Group": m_row["item_group"],
                    "Score": score,
                    "Status": "MATCH" if score >= 80 else "REVIEW"
                })

            else:
                results.append({
                    "Kode Agent": row["kode_agent"],
                    "Item Code": None,
                    "Item Name": None,
                    "Item / Box": None,
                    "Stock PCS": pcs,
                    "Stock Karton": 0,
                    "Item Group": None,
                    "Score": 0,
                    "Status": "NOT FOUND"
                })

        df = pd.DataFrame(results)

        # =========================
        # SPLIT SHEET
        # =========================
        df_no_stock = df[[
            "Kode Agent","Item Code","Item Name","Item / Box","Item Group","Status"
        ]]

        df_with_stock = df[[
            "Kode Agent","Item Code","Item Name","Item / Box",
            "Stock PCS","Stock Karton","Item Group","Status"
        ]]

        # =========================
        # EXPORT
        # =========================
        output_dir = os.path.join(base_dir, "storage", "app", "public")
        os.makedirs(output_dir, exist_ok=True)

        output_file = os.path.join(output_dir, f"hasil_mapping_{uuid.uuid4()}.xlsx")

        with pd.ExcelWriter(output_file, engine='openpyxl') as writer:
            df_no_stock.to_excel(writer, sheet_name='Tanpa Stock', index=False)
            df_with_stock.to_excel(writer, sheet_name='Dengan Stock', index=False)

        print(json.dumps({
            "success": True,
            "file": output_file
        }))

    except Exception as e:
        print(json.dumps({"error": str(e)}))


if __name__ == "__main__":
    run()