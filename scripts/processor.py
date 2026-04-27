import pandas as pd
import sys
import json
import re
from rapidfuzz import process, fuzz
from io import BytesIO

# =========================
# NORMALIZE
# =========================
def normalize(text):
    if not text or pd.isna(text): return ""
    text = str(text).lower()
    text = re.sub(r'[^a-z0-9 ]', ' ', text)
    remove_words = ["gr","gram","bogof","pouch","pcs","reg","free","promo"]
    for w in remove_words:
        text = text.replace(w, "")
    return re.sub(r'\s+', ' ', text).strip()

# =========================
# DETECT HEADER
# =========================
def detect_header(file_path, sheet):
    raw = pd.read_excel(file_path, sheet_name=sheet, header=None, engine='openpyxl')
    
    raw_header = raw.head(20)
    best_row = 0
    best_score = 0

    for i in range(len(raw_header)):
        row_content = raw_header.iloc[i].astype(str).str.lower()
        score = (
            row_content.str.contains("customer").sum() +
            row_content.str.contains("invoice").sum() +
            row_content.str.contains("kode").sum() +
            row_content.str.contains("nama").sum() +
            row_content.str.contains("alamat").sum() +
            row_content.str.contains("qty|jumlah").sum()
        )
        if score > best_score:
            best_score = score
            best_row = i

    header_values = raw.iloc[best_row].values
    df = raw.iloc[best_row + 1:].copy()
    
    clean_columns = [str(c).strip().replace('\n', ' ') if pd.notna(c) else f"Unnamed_{i}" for i, c in enumerate(header_values)]
    df.columns = clean_columns
    
    return df

# =========================
# COLUMN MATCH
# =========================
def normalize_col(x):
    return str(x).lower().replace(" ", "").replace("_", "").strip()

def find_column(target, columns):
    if not target:
        return None

    target_clean = normalize_col(target)

    for c in columns:
        if normalize_col(c) == target_clean:
            return c

    return None

def is_valid_data(df):
    non_empty_cols = df.notna().sum()
    return (non_empty_cols > 0).sum() >= 3

def autofit_columns(ws):
    for col in ws.columns:
        max_length = 0
        col_letter = col[0].column_letter
        
        for cell in col:
            try:
                if cell.value:
                    max_length = max(max_length, len(str(cell.value)))
            except:
                pass
        
        ws.column_dimensions[col_letter].width = (max_length + 2)

# =========================
# MAIN
# =========================
def run():
    try:
        input_data = json.loads(sys.stdin.read())
        file_path = input_data["file_path"]
        mapping_jim = input_data["mapping_jim"]
        mapping_inv = input_data["mapping_inv"]
        master_items = input_data["master_data"]

        master = pd.DataFrame(master_items)
        master["clean"] = master["item_name"].apply(normalize)
        master_list = master["clean"].tolist()

        xls = pd.ExcelFile(file_path, engine='openpyxl')
        df_jim_list = []
        df_invoice_list = []

        target_fields_inv = [
            "Nama Agen","Kode Customer","Nama Customer","Alamat Customer",
            "Nomor Telepon/HP Customer","Invoice Nomor Agen","Tanggal Invoice",
            "Tipe Customer","Sales","SKU Kode Agen","Nama SKU",
            "","Qty Terjual (PCS)","% Diskon 1 (Reguler)","% Diskon 2 (Cash)",
            "% Diskon 3 (DC Free)","% Diskon 4 (Promo 1)","% Diskon 5 (Promo 2)",
            "% Diskon 6 (Rp)","Quantity Bonus","Rafraksi","Total Invoice Value"
        ]

        for sheet in xls.sheet_names:
            try:
                df = detect_header(file_path, sheet)
                df = df.ffill()
                df = df[df.notna().sum(axis=1) > 2]
                df = df.reset_index(drop=True)

                if not is_valid_data(df): continue
            except: continue

            # =====================
            # PROSES JIM
            # =====================
            col_sku_agent = find_column(mapping_jim.get("Kode SKU Agent"), df.columns)
            col_stock_agent = find_column(mapping_jim.get("Stock Akhir Agent"), df.columns)
            col_nama_agent = find_column(mapping_jim.get("Nama Produk"), df.columns)

            if col_sku_agent and col_stock_agent:
                df_j = df.copy().rename(columns={
                    col_sku_agent: "kode_agent",
                    col_stock_agent: "stock_pcs"
                })

                df_j["stock_pcs"] = pd.to_numeric(df_j["stock_pcs"], errors="coerce").fillna(0)

                if col_nama_agent:
                    df_j["nama_produk"] = df[col_nama_agent]
                    df_j["clean"] = df_j["nama_produk"].apply(normalize)

                results_jim = []  # ✅ FIX BUG (dipindah keluar)

                for idx, row in df_j.iterrows():  # ✅ pakai idx untuk urutan
                    m = process.extractOne(row.get("clean", ""), master_list, scorer=fuzz.token_set_ratio)

                    if m and m[1] >= 70:
                        m_row = master.iloc[m[2]]
                        results_jim.append({
                            "ORDER": idx,
                            "Item Code": m_row["item_code"],
                            "Item Name": m_row["item_name"],
                            "Item Box": m_row.get("item_per_box", 1),
                            "Kode SKU Agent": row["kode_agent"],
                            "Nama Produk Agent": row.get("nama_produk"),
                            "Stock PCS": row["stock_pcs"],
                            "MATCH_STATUS": "MATCH"
                        })
                    else:
                        results_jim.append({
                            "ORDER": idx,
                            "Item Code": None,
                            "Item Name": None,
                            "Item Box": None,
                            "Kode SKU Agent": row["kode_agent"],
                            "Nama Produk Agent": row.get("nama_produk"),
                            "Stock PCS": row["stock_pcs"],
                            "MATCH_STATUS": "NOT MATCH"
                        })

                if results_jim:
                    df_jim_list.append(pd.DataFrame(results_jim))

            # =====================
            # PROSES INVOICE
            # =====================
            if (
                find_column(mapping_inv.get("Kode Customer"), df.columns)
                and find_column(mapping_inv.get("Nama Customer"), df.columns)
                and find_column(mapping_inv.get("Invoice Nomor Agen"), df.columns)
            ):
                df_inv_sheet = pd.DataFrame(index=df.index)

                for field in target_fields_inv:
                    excel_col = find_column(mapping_inv.get(field), df.columns)
                    if excel_col:
                        if field == "Tanggal Invoice":
                            df_inv_sheet[field] = pd.to_datetime(df[excel_col], errors='coerce').dt.strftime('%m/%d/%Y')
                        else:
                            df_inv_sheet[field] = df[excel_col]
                    else:
                        df_inv_sheet[field] = pd.NA
                
                if not df_inv_sheet.replace(pd.NA, "").dropna(how='all').empty:
                    df_invoice_list.append(df_inv_sheet)

        # =====================
        # FINALIZE
        # =====================
        df_inv_final = pd.concat(df_invoice_list, ignore_index=True) if df_invoice_list else pd.DataFrame()
        df_jim_final = pd.concat(df_jim_list, ignore_index=True) if df_jim_list else pd.DataFrame()

        # ✅ SORT sesuai urutan agent
        if not df_jim_final.empty:
            df_jim_final = df_jim_final.sort_values("ORDER")

        output = BytesIO()

        with pd.ExcelWriter(output, engine='openpyxl') as writer:

            # =====================
            # KODE JIM
            # =====================
            if not df_jim_final.empty:
                df_jim = (
                    df_jim_final
                    .sort_values("ORDER")
                    .drop_duplicates(subset=["Item Code"], keep="first")
                )[["Item Code", "Item Name", "Item Box", "Kode SKU Agent"]]

                df_jim.to_excel(writer, sheet_name="Kode JIM", index=False)
                autofit_columns(writer.book["Kode JIM"])

            # =====================
            # INVOICE
            # =====================
            if not df_inv_final.empty:
                df_inv_final.fillna("").to_excel(writer, sheet_name="Invoice Agent", index=False)
                autofit_columns(writer.book["Invoice Agent"])

            # =====================
            # STOCK
            # =====================
            if not df_jim_final.empty:
                df_s = df_jim_final.sort_values("ORDER").copy()

                df_s["Stock (Karton)"] = (
                    df_s["Stock PCS"] /
                    pd.to_numeric(df_s["Item Box"], errors='coerce').fillna(1)
                ).round(0)

                df_s.rename(columns={
                    "Item Code": "Kode SKU JIM",
                    "Item Name": "Item Name JIM"
                }).to_excel(writer, sheet_name="Stock Agen", index=False)

                autofit_columns(writer.book["Stock Agen"])

        output.seek(0)
        sys.stdout.buffer.write(output.read())

    except Exception as e:
        sys.stderr.write(str(e))
        sys.exit(1)

if __name__ == "__main__":
    run()