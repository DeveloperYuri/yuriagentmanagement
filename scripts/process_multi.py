import pandas as pd
import sys
import json
import re
from rapidfuzz import process, fuzz
from io import BytesIO

def normalize(text):
    if not text or pd.isna(text): return ""
    text = str(text).lower()
    text = re.sub(r'[^a-z0-9 ]', ' ', text)

    remove_words = ["ml","gr","gram","bogof","pouch","pcs","reg","free","promo"]
    for w in remove_words:
        text = text.replace(w, "")

    return re.sub(r'\s+', ' ', text).strip()

def run():
    try:
        input_data = json.loads(sys.stdin.read())

        file_path = input_data["file_path"]
        sheet_name = input_data["mappings"]["Kode Produk JIM"]["source_sheet"]
        master_items = input_data["master_data"]

        # =========================
        # MASTER
        # =========================
        master = pd.DataFrame(master_items)
        if master.empty:
            raise Exception("Master kosong")

        master["clean"] = master["item_name"].apply(normalize)
        master_list = master["clean"].tolist()

        # =========================
        # AGENT (HEADER DETECTION)
        # =========================
        raw_agent = pd.read_excel(file_path, sheet_name=sheet_name, header=None)

        header_row = 0
        for i in range(min(10, len(raw_agent))):
            row = raw_agent.iloc[i].astype(str).str.lower()
            if row.str.contains("nama").any() or row.str.contains("item").any():
                header_row = i
                break

        agent = pd.read_excel(file_path, sheet_name=sheet_name, header=header_row)

        # =========================
        # DETECT KOLOM NAMA
        # =========================
        name_cols = [
            c for c in agent.columns
            if any(k in str(c).lower() for k in ["nama", "item", "produk", "desc"])
        ]

        if not name_cols:
            raise Exception(f"Kolom nama tidak ditemukan. Kolom tersedia: {list(agent.columns)}")

        name_col = name_cols[0]

        # =========================
        # DETECT KOLOM KODE AGENT
        # =========================
        code_cols = [
            c for c in agent.columns
            if any(k in str(c).lower() for k in ["kd_barang", "kode", "sku"])
        ]

        code_col = code_cols[0] if code_cols else None

        # CLEAN
        agent["clean"] = agent[name_col].apply(normalize)

        # =========================
        # MATCHING
        # =========================
        results = []

        for _, row in agent.iterrows():
            m = process.extractOne(row["clean"], master_list, scorer=fuzz.token_sort_ratio)

            if m:
                _, score, idx = m
                master_row = master.iloc[idx]

                results.append({
                    "Nama Excel": row[name_col],
                    "Nama Master": master_row["item_name"],
                    "Kode Agent": row[code_col] if code_col and code_col in row else None,
                    "Item Code": master_row["item_code"],
                    "Score": round(score, 2),
                    "Status": "MATCH" if score >= 80 else "REVIEW"
                })
            else:
                results.append({
                    "Nama Excel": row[name_col],
                    "Nama Master": None,
                    "Kode Agent": row[code_col] if code_col and code_col in row else None,
                    "Item Code": None,
                    "Score": 0,
                    "Status": "NOT FOUND"
                })

        df = pd.DataFrame(results)

        if df.empty:
            raise Exception("Data hasil kosong")

        # =========================
        # URUTAN KOLOM (FIX)
        # =========================
        df = df[[
            "Nama Excel",
            "Nama Master",
            "Kode Agent",
            "Item Code",
            "Score",
            "Status"
        ]]

        # =========================
        # EXPORT EXCEL
        # =========================
        output = BytesIO()
        df.to_excel(output, index=False)
        output.seek(0)

        sys.stdout.buffer.write(output.read())

    except Exception as e:
        sys.stderr.write(str(e))
        sys.exit(1)

if __name__ == "__main__":
    run()