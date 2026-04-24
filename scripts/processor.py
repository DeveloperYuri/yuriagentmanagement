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

    remove_words = ["ml","gr","gram","bogof","pouch","pcs","reg","free","promo"]
    for w in remove_words:
        text = text.replace(w, "")

    return re.sub(r'\s+', ' ', text).strip()

# =========================
# DETECT HEADER
# =========================
def detect_header(file_path, sheet):
    # Baca 20 baris pertama untuk mencari header terbaik
    raw = pd.read_excel(file_path, sheet_name=sheet, header=None, nrows=20, engine='openpyxl')

    best_row = 0
    max_cols = 0

    for i, row in raw.iterrows():
        # Hitung kolom yang tidak kosong
        non_empty = row.dropna().count()
        if non_empty > max_cols:
            max_cols = non_empty
            best_row = i

    return pd.read_excel(file_path, sheet_name=sheet, header=best_row, engine='openpyxl')

# =========================
# MATCH COLUMN (FLEXIBLE)
# =========================
def match_column(target, columns):
    if not target: return False
    target = str(target).lower().strip()
    for c in columns:
        c = str(c).lower().strip()
        if target == c: # Prioritas kecocokan persis
            return True
        if target in c or c in target: # Kecocokan sebagian
            return True
    return False

# =========================
# MAIN
# =========================
def run():
    try:
        # Baca input JSON dari Laravel
        input_data = json.loads(sys.stdin.read())

        file_path = input_data["file_path"]
        mapping_jim = input_data["mapping_jim"]
        mapping_inv = input_data["mapping_inv"]
        master_items = input_data["master_data"]

        # Master Data Processing
        master = pd.DataFrame(master_items)
        if master.empty:
            raise Exception("Master data kosong di database")

        master["clean"] = master["item_name"].apply(normalize)
        master_list = master["clean"].tolist()

        # Load Excel
        xls = pd.ExcelFile(file_path, engine='openpyxl')
        sheet_names = xls.sheet_names

        df_jim_list = []
        df_invoice_list = []

        for sheet in sheet_names:
            try:
                df = detect_header(file_path, sheet)
                df = df.ffill() # Forward fill untuk merged cells
            except Exception:
                continue

            cols_lower = [str(c).lower().strip() for c in df.columns]

            # ==========================================
            # DETECT TYPE (LOGIKA DILONGGARKAN)
            # ==========================================
            # Cek apakah ada minimal satu kolom mapping yang cocok di sheet ini
            is_jim_valid = any(match_column(val, cols_lower) for key, val in mapping_jim.items() if val)
            is_invoice_valid = any(match_column(val, cols_lower) for key, val in mapping_inv.items() if val)

            # ==========================================
            # PROSES JIM (MAPPING KODE)
            # ==========================================
            if is_jim_valid:
                try:
                    # Ambil kolom wajib untuk JIM
                    col_sku_agent = mapping_jim.get("Kode SKU Agent")
                    col_stock_agent = mapping_jim.get("Stock Akhir Agent")
                    col_nama_agent = mapping_jim.get("Nama Produk")

                    if col_sku_agent in df.columns and col_stock_agent in df.columns:
                        df_j = df.copy()
                        df_j = df_j.rename(columns={
                            col_sku_agent: "kode_agent",
                            col_stock_agent: "stock_pcs"
                        })

                        df_j["stock_pcs"] = pd.to_numeric(df_j["stock_pcs"], errors="coerce").fillna(0)
                        
                        if col_nama_agent in df.columns:
                            df_j["nama_produk"] = df[col_nama_agent]
                            df_j["clean"] = df_j["nama_produk"].apply(normalize)

                            results_jim = []
                            for _, row in df_j.iterrows():
                                # Fuzzy matching ke master data
                                m = process.extractOne(row["clean"], master_list, scorer=fuzz.token_set_ratio)
                                if m:
                                    _, score, idx = m
                                    if score >= 70: # Threshold akurasi
                                        m_row = master.iloc[idx]
                                        results_jim.append({
                                            "Item Code": m_row["item_code"],
                                            "Item Name": m_row["item_name"],
                                            "Item Box": m_row.get("item_per_box", 1),
                                            "Kode SKU Agent": row["kode_agent"],
                                            "Stock PCS": row["stock_pcs"]
                                        })
                            
                            if results_jim:
                                df_jim_list.append(pd.DataFrame(results_jim))
                except Exception as e:
                    sys.stderr.write(f"Error JIM sheet {sheet}: {str(e)}\n")

            # ==========================================
            # PROSES INVOICE (MAPPING INVOICE)
            # ==========================================
            # Gunakan IF (bukan ELIF) agar jika sheet yang sama punya data Inv, tetap diproses
            if is_invoice_valid:
                try:
                    target_fields_inv = [
                        "Nama Agen","Kode Customer","Nama Customer","Alamat Customer",
                        "Nomor Telepon/HP Customer","Invoice Nomor Agen","Tanggal Invoice",
                        "Tipe Customer","Sales","SKU Kode Agen","Nama SKU",
                        "Qty Terjual (PCS)","% Diskon 1 (Reguler)","% Diskon 2 (Cash)",
                        "% Diskon 3 (DC Free)","% Diskon 4 (Promo 1)","% Diskon 5 (Promo 2)",
                        "% Diskon 6 (Rp)","Quantity Bonus","Rafraksi","Total Invoice Value"
                    ]

                    df_inv_sheet = pd.DataFrame(index=df.index)

                    for field in target_fields_inv:
                        excel_col_source = mapping_inv.get(field)
                        
                        if excel_col_source and excel_col_source in df.columns:
                            # Format khusus tanggal
                            if field == "Tanggal Invoice":
                                df_inv_sheet[field] = pd.to_datetime(df[excel_col_source], errors='coerce').dt.strftime('%m/%d/%Y')
                            else:
                                df_inv_sheet[field] = df[excel_col_source]
                        else:
                            df_inv_sheet[field] = ""

                    # Hanya tambahkan jika ada baris yang mengandung data (bukan kolom kosong semua)
                    if not df_inv_sheet.replace("", pd.NA).dropna(how='all').empty:
                        df_invoice_list.append(df_inv_sheet)

                except Exception as e:
                    sys.stderr.write(f"Error Invoice sheet {sheet}: {str(e)}\n")

        # =========================
        # CONSOLIDATE & EXPORT
        # =========================
        df_jim_final = pd.concat(df_jim_list, ignore_index=True) if df_jim_list else pd.DataFrame()
        df_invoice_final = pd.concat(df_invoice_list, ignore_index=True) if df_invoice_list else pd.DataFrame()

        output = BytesIO()
        with pd.ExcelWriter(output, engine='openpyxl') as writer:
            
            # Sheet 1: Mapping Kode Produk (JIM)
            if not df_jim_final.empty:
                df_k = df_jim_final[["Item Code", "Item Name", "Item Box", "Kode SKU Agent"]].drop_duplicates().copy()
                df_k.insert(0, "No", range(1, len(df_k) + 1))
                df_k.to_excel(writer, sheet_name="Kode Produk JIM", index=False)
            
            # Sheet 2: Stock Agent
            if not df_jim_final.empty:
                df_s = df_jim_final.copy()
                df_s["Item Box"] = pd.to_numeric(df_s["Item Box"], errors="coerce").fillna(1)
                df_s["Stock (Karton)"] = (df_s["Stock PCS"] / df_s["Item Box"]).round(0).astype(int)
                
                df_s = df_s.rename(columns={"Item Code": "Kode SKU JIM", "Item Name": "Item Name JIM"})
                df_s.insert(0, "No", range(1, len(df_s) + 1))
                
                cols_s = ["No", "Kode SKU Agent", "Kode SKU JIM", "Item Name JIM", "Stock (Karton)"]
                df_s[cols_s].to_excel(writer, sheet_name="Stock Agent", index=False)

            # Sheet 3: Invoice Agen
            if not df_invoice_final.empty:
                # Hapus baris yang semua kolomnya kosong
                df_invoice_final = df_invoice_final.replace("", pd.NA).dropna(how='all').fillna("")
                df_invoice_final.to_excel(writer, sheet_name="Invoice Agen", index=False)

            # Auto-adjust column width
            for sheet_name in writer.sheets:
                worksheet = writer.sheets[sheet_name]
                for col in worksheet.columns:
                    max_length = 0
                    column = col[0].column_letter
                    for cell in col:
                        try:
                            if cell.value and len(str(cell.value)) > max_length:
                                max_length = len(str(cell.value))
                        except: pass
                    worksheet.column_dimensions[column].width = max_length + 2

        # Kirim hasil binary ke Laravel stdout
        sys.stdout.buffer.write(output.getvalue())

    except Exception as e:
        sys.stderr.write(f"FATAL ERROR: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    run()