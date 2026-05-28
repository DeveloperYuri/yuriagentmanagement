import pandas as pd
import sys
import json
import re
from rapidfuzz import process, fuzz
from io import BytesIO
import os
import base64
import numpy as np

# pd.set_option('future.no_silent_downcasting', True)

def get_engine(file_path):
    ext = os.path.splitext(file_path)[1].lower()

    if ext == '.xls':
        return 'xlrd'
    elif ext == '.xlsb':
        return 'pyxlsb'
    else:
        return 'openpyxl'

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

def extract_ml(text):
    match = re.search(r'(\d+)\s*ml', str(text).lower())
    return match.group(1) if match else None

# =========================
# DETECT HEADER
# =========================
def detect_header(file_path, sheet, engine):
    raw = pd.read_excel(file_path, sheet_name=sheet, header=None, engine=engine)
    
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
            row_content.str.contains("qty|jumlah").sum() +
            row_content.str.contains("kode|co|copc").sum() +
            row_content.str.contains(r"\bna\b", case=False, na=False).sum()
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
        nama_agent = input_data.get("nama_agent", "")
        engine = get_engine(file_path)
        
        alias_items = input_data.get("alias_data", [])
        alias_df = pd.DataFrame(alias_items)

        if not alias_df.empty:
            alias_df["clean_name"] = alias_df["clean_name"].apply(normalize)

        master = pd.DataFrame(master_items)
        master["clean"] = master["item_name"].apply(normalize)
        master_list = master["clean"].tolist()

        xls = pd.ExcelFile(file_path, engine=engine)
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
                df = detect_header(file_path, sheet, engine)
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
                
                df_j["kode_agent"] = df_j["kode_agent"].astype(str)
                df_j["kode_agent"] = df_j["kode_agent"].str.replace(r"\.0$", "", regex=True)

                df_j["stock_pcs"] = pd.to_numeric(df_j["stock_pcs"], errors="coerce").fillna(0)

                if col_nama_agent:
                    df_j["nama_produk"] = df[col_nama_agent]
                    df_j["clean"] = df_j["nama_produk"].apply(normalize)

                results_jim = []  # ✅ FIX BUG (dipindah keluar)
                
                for idx, row in df_j.iterrows():

                    clean_name = normalize(row.get("nama_produk"))

                    # =========================
                    # 1. CEK ALIAS DULU
                    # =========================
                    alias_match = None

                    if not alias_df.empty:
                        match = alias_df[alias_df["clean_name"] == clean_name]
                        if not match.empty:
                            alias_match = match.iloc[0]

                    # =========================
                    # 2. KALAU ADA → LANGSUNG PAKAI
                    # =========================
                    if alias_match is not None:
                        # m_row = master[master["item_name"] == alias_match["master_name"]].iloc[0]
                        
                        m_filtered = master[master["item_name"] == alias_match["master_name"]]

                        if m_filtered.empty:
                            continue  # skip kalau tidak ketemu

                        m_row = m_filtered.iloc[0]

                        results_jim.append({
                            "ORDER": idx,
                            "Item Code": m_row["item_code"],
                            "Item Name": m_row["item_name"],
                            "Item Box": m_row.get("item_per_box", 1),
                            "Kode SKU Agent": row["kode_agent"],
                            "Nama Produk Agent": row.get("nama_produk"),
                            "Stock PCS": row["stock_pcs"],
                            "MATCH_STATUS": "ALIAS_MATCH"
                        })

                    # =========================
                    # 3. KALAU TIDAK ADA → FUZZY
                    # =========================
                    else:
                        m = process.extractOne(clean_name, master_list, scorer=fuzz.token_set_ratio)

                        if m and m[1] >= 70:
                            agent_num = extract_ml(row.get("nama_produk"))
                            master_num = extract_ml(master.iloc[m[2]]["item_name"])

                            if agent_num and master_num and agent_num != master_num:
                                m = None

                        if m:
                            # m_row = master.iloc[m[2]]
                            if m and 0 <= m[2] < len(master):
                                m_row = master.iloc[m[2]]
                            else:
                                m = None
    
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
                
                # for idx, row in df_j.iterrows():
                #     m = process.extractOne(row.get("clean", ""), master_list, scorer=fuzz.token_set_ratio)

                #     if m and m[1] >= 70:
                #         # 🔥 TAMBAHKAN DI SINI
                #         agent_num = extract_ml(row.get("nama_produk"))
                #         master_num = extract_ml(master.iloc[m[2]]["item_name"])

                #         # VALIDASI ANGKA (ML)
                #         if agent_num and master_num and agent_num != master_num:
                #             m = None

                #     # =========================
                #     # BARU MASUK KE APPEND
                #     # =========================
                #     if m:
                #         m_row = master.iloc[m[2]]
                #         results_jim.append({
                #             "ORDER": idx,
                #             "Item Code": m_row["item_code"],
                #             "Item Name": m_row["item_name"],
                #             "Item Box": m_row.get("item_per_box", 1),
                #             "Kode SKU Agent": row["kode_agent"],
                #             "Nama Produk Agent": row.get("nama_produk"),
                #             "Stock PCS": row["stock_pcs"],
                #             "MATCH_STATUS": "MATCH"
                #         })
                #     else:
                #         results_jim.append({
                #             "ORDER": idx,
                #             "Item Code": None,
                #             "Item Name": None,
                #             "Item Box": None,
                #             "Kode SKU Agent": row["kode_agent"],
                #             "Nama Produk Agent": row.get("nama_produk"),
                #             "Stock PCS": row["stock_pcs"],
                #             "MATCH_STATUS": "NOT MATCH"
                #         })

                # for idx, row in df_j.iterrows():  # ✅ pakai idx untuk urutan
                #     m = process.extractOne(row.get("clean", ""), master_list, scorer=fuzz.token_set_ratio)

                #     if m and m[1] >= 70:
                #         m_row = master.iloc[m[2]]
                #         results_jim.append({
                #             "ORDER": idx,
                #             "Item Code": m_row["item_code"],
                #             "Item Name": m_row["item_name"],
                #             "Item Box": m_row.get("item_per_box", 1),
                #             "Kode SKU Agent": row["kode_agent"],
                #             "Nama Produk Agent": row.get("nama_produk"),
                #             "Stock PCS": row["stock_pcs"],
                #             "MATCH_STATUS": "MATCH"
                #         })
                #     else:
                #         results_jim.append({
                #             "ORDER": idx,
                #             "Item Code": None,
                #             "Item Name": None,
                #             "Item Box": None,
                #             "Kode SKU Agent": row["kode_agent"],
                #             "Nama Produk Agent": row.get("nama_produk"),
                #             "Stock PCS": row["stock_pcs"],
                #             "MATCH_STATUS": "NOT MATCH"
                #         })

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
                df_inv_sheet.insert(0, "Nama Agen", nama_agent)

                for field in target_fields_inv:
                    if field == "Nama Agen":
                        continue
                    excel_col = find_column(mapping_inv.get(field), df.columns)
                    if excel_col:
                        # if field == "Tanggal Invoice":
                        #     df_inv_sheet[field] = pd.to_datetime(df[excel_col], errors='coerce').dt.strftime('%m/%d/%Y')
                        if field == "Tanggal Invoice":
                            df_inv_sheet[field] = pd.to_datetime(df[excel_col], errors='coerce')
                        elif field == "SKU Kode Agen":
                            df_inv_sheet[field] = df[excel_col].astype(str)
                            df_inv_sheet[field] = df_inv_sheet[field].str.replace(r"\.0$", "", regex=True)
                        else:
                            df_inv_sheet[field] = df[excel_col]
                    else:
                        df_inv_sheet[field] = pd.NA
                        
                        #BARUURRURURURURUR

                if "Nama SKU" in df_inv_sheet.columns:
                    # 1. Normalisasi nama (Vectorized)
                    df_inv_sheet["clean_name"] = df_inv_sheet["Nama SKU"].fillna("").apply(normalize)

                    if not alias_df.empty:
                        # 2. Amankan kolom agar tidak Error 500 lagi
                        # Kita pastikan kolom yang dipanggil benar-benar ada
                        master_col = 'master_name' if 'master_name' in alias_df.columns else alias_df.columns[0]
                        alias_col = 'alias_name' if 'alias_name' in alias_df.columns else None
                        
                        # 3. Tandai Duplikat di Master secara Manual
                        # Kita hitung berapa kali tiap clean_name muncul di master data
                        counts = alias_df['clean_name'].value_counts().to_dict()
                        
                        # 4. BUANG DUPLIKAT di tabel alias sebelum Join
                        # Ini kunci agar tidak error 'Reindexing'. Kita ambil satu saja buat mapping.
                        alias_lookup = alias_df.drop_duplicates(subset=['clean_name'], keep='first').copy()

                        # 5. Gunakan LEFT JOIN (Merge) - Cara paling aman
                        df_inv_sheet = df_inv_sheet.merge(
                            alias_lookup[['clean_name', master_col] + ([alias_col] if alias_col else [])], 
                            on='clean_name', 
                            how='left'
                        )

                        # 6. Tentukan Status & Tandai jika sebenarnya ada duplikat
                        def check_status(row):
                            c_name = row['clean_name']
                            if pd.isnull(row[master_col]):
                                return "NOT MATCH"
                            if counts.get(c_name, 0) > 1:
                                return "DUPLICATE_IN_MASTER"
                            return "ALIAS_MATCH"

                        df_inv_sheet["MATCH STATUS"] = df_inv_sheet.apply(check_status, axis=1)
                        
                        # 7. Rename Kolom Output
                        rename_map = {master_col: "MATCH ITEM"}
                        if alias_col:
                            rename_map[alias_col] = "MATCH ALIAS"
                        else:
                            df_inv_sheet["MATCH ALIAS"] = ""
                            
                        df_inv_sheet.rename(columns=rename_map, inplace=True)
                        
                    else:
                        df_inv_sheet["MATCH ITEM"] = ""
                        df_inv_sheet["MATCH ALIAS"] = ""
                        df_inv_sheet["MATCH STATUS"] = "NOT MATCH"

                    # 8. Final Cleanup (Isi yang kosong)
                    df_inv_sheet["MATCH ITEM"] = df_inv_sheet["MATCH ITEM"].fillna("")
                    df_inv_sheet["MATCH ALIAS"] = df_inv_sheet["MATCH ALIAS"].fillna("")
                    
                if "clean_name" in df_inv_sheet.columns:
                    df_inv_sheet.drop(columns=["clean_name"], inplace=True)
                        
                        # if "Nama SKU" in df_inv_sheet.columns:

                        #     df_inv_sheet["clean_name"] = (
                        #         df_inv_sheet["Nama SKU"]
                        #         .fillna("")
                        #         .apply(normalize)
                        #     )

                        #     match_status = []
                        #     matched_item = []
                        #     matched_alias = []
                            
                        #     match_cache = {}

                        #     for _, row in df_inv_sheet.iterrows():

                        #         clean_name = row["clean_name"]
                                
                        #         if clean_name in match_cache:

                        #             cache = match_cache[clean_name]

                        #             matched_item.append(cache["item"])
                        #             matched_alias.append(cache["alias"])
                        #             match_status.append(cache["status"])

                        #             continue

                        #         alias_match = None

                        #         # =====================
                        #         # CEK ALIAS
                        #         # =====================
                        #         if not alias_df.empty:
                        #             match = alias_df[
                        #                 alias_df["clean_name"] == clean_name
                        #             ]

                        #             if not match.empty:
                        #                 alias_match = match.iloc[0]

                        #         # =====================
                        #         # JIKA ADA ALIAS
                        #         # =====================
                        #         if alias_match is not None:

                        #             matched_item.append(alias_match["master_name"])
                        #             matched_alias.append(alias_match.get("alias_name", ""))
                        #             match_status.append("ALIAS_MATCH")
                                    
                        #             match_cache[clean_name] = {
                        #                 "item": alias_match["master_name"],
                        #                 "alias": alias_match.get("alias_name", ""),
                        #                 "status": "ALIAS_MATCH"
                        #             }

                        #         else:
                                    
                        #             matched_item.append("")
                        #             matched_alias.append("")
                        #             match_status.append("NOT MATCH")

                        #             match_cache[clean_name] = {
                        #                 "item": "",
                        #                 "alias": "",
                        #                 "status": "NOT MATCH"
                        #             }

                        #     df_inv_sheet["MATCH ITEM"] = matched_item
                        #     df_inv_sheet["MATCH ALIAS"] = matched_alias
                        #     df_inv_sheet["MATCH STATUS"] = match_status

                        #     df_inv_sheet.drop(columns=["clean_name"], inplace=True)
    
                        # if not df_inv_sheet.replace(pd.NA, "").dropna(how='all').empty:
                        #     df_invoice_list.append(df_inv_sheet)
                
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
                    .groupby(["Item Code", "Item Name"], as_index=False, sort=False)
                    .agg({
                        "Stock PCS": "sum",
                        "Item Box": "first",
                        "Kode SKU Agent": "first"
                    })
                )

                # tetap hitung (kalau nanti butuh)
                df_jim["Stock Karton"] = df_jim["Stock PCS"] / df_jim["Item Box"]

                # 🔥 FILTER KOLOM
                df_jim = df_jim[[
                    "Item Code",
                    "Item Name",
                    "Kode SKU Agent"
                ]]

                df_jim.to_excel(writer, sheet_name="Kode JIM", index=False)
                autofit_columns(writer.book["Kode JIM"])

            #     df_jim = (
            #         df_jim_final
            #         .sort_values("ORDER")
            #         .drop_duplicates(subset=["Item Code"], keep="first")
            #     )[["Item Code", "Item Name", "Item Box", "Kode SKU Agent"]]

            
            

            # =====================
            # INVOICE
            # =====================
            # if not df_inv_final.empty:
            #     df_inv_final.fillna("").to_excel(writer, sheet_name="Invoice Agent", index=False)
            #     autofit_columns(writer.book["Invoice Agent"])
            
            if not df_inv_final.empty:
                df_inv_final.fillna("").to_excel(writer, sheet_name="Invoice Agent", index=False)

                ws = writer.book["Invoice Agent"]

                # cari kolom "Tanggal Invoice"
                col_idx = None
                for i, col in enumerate(df_inv_final.columns):
                    if col == "Tanggal Invoice":
                        col_idx = i + 1  # openpyxl mulai dari 1
                        break

                # set format jadi MM/DD/YYYY
                if col_idx:
                    for row in ws.iter_rows(min_row=2, min_col=col_idx, max_col=col_idx):
                        for cell in row:
                            cell.number_format = 'MM/DD/YYYY'

                # aktifkan filter dropdown
                ws.auto_filter.ref = ws.dimensions

                autofit_columns(ws)

            # =====================
            # STOCK
            # =====================
            if not df_jim_final.empty:
                df_s = (
                    df_jim_final
                    .groupby(["Item Code", "Item Name"], as_index=False, sort=False)
                    .agg({
                        "Stock PCS": "sum",
                        "Item Box": "first",
                        "Kode SKU Agent": "first",
                        "ORDER": "min"
                    })
                )

                # df_s["Stock (Karton)"] = df_s["Stock PCS"] / df_s["Item Box"]
                df_s["Stock (Karton)"] = (
                    (df_s["Stock PCS"] / df_s["Item Box"]) + 0.5
                ).astype(int)
                    # df_s["Stock PCS"] / df_s["Item Box"]
                # ).round(0).astype(int)
                df_s = df_s.sort_values("ORDER")

                df_s = df_s.rename(columns={
                    "Item Code": "Kode SKU JIM",
                    "Item Name": "Item Name JIM"
                })

                # 🔥 pilih kolom yang mau ditampilkan saja
                df_s = df_s[[
                    "Kode SKU Agent",
                    "Kode SKU JIM",
                    "Item Name JIM",
                    "Stock (Karton)"
                ]]

                df_s.to_excel(writer, sheet_name="Stock Agen", index=False)

                autofit_columns(writer.book["Stock Agen"])
            # if not df_jim_final.empty:
            #     df_s = df_jim_final.sort_values("ORDER").copy()

            #     df_s["Stock (Karton)"] = (
            #         df_s["Stock PCS"] /
            #         pd.to_numeric(df_s["Item Box"], errors='coerce').fillna(1)
            #     ).round(0)

            #     df_s.rename(columns={
            #         "Item Code": "Kode SKU JIM",
            #         "Item Name": "Item Name JIM"
            #     }).to_excel(writer, sheet_name="Stock Agen", index=False)

            #     autofit_columns(writer.book["Stock Agen"])

        # lama bener
        # output.seek(0)
        # sys.stdout.buffer.write(output.read())
        
        
        # baru ubah 
        output.seek(0)

        excel_base64 = base64.b64encode(output.read()).decode('utf-8')

        invoice_data = []
        stock_agent_data = []

        # =====================
        # INVOICE JSON
        # =====================
        if not df_inv_final.empty:
            invoice_data = (
                df_inv_final
                .fillna("")
                .to_dict(orient="records")
            )

        # =====================
        # STOCK JSON
        # =====================
        if not df_jim_final.empty:

            df_stock_json = (
                df_jim_final
                .groupby(["Item Code", "Item Name"], as_index=False, sort=False)
                .agg({
                    "Stock PCS": "sum",
                    "Item Box": "first",
                    "Kode SKU Agent": "first",
                    "ORDER": "min"
                })
            )

            df_stock_json["Stock (Karton)"] = (
                (df_stock_json["Stock PCS"] / df_stock_json["Item Box"]) + 0.5
            ).astype(int)
                # df_stock_json["Stock PCS"] / df_stock_json["Item Box"]
            # ).round(0).astype(int)

            df_stock_json = df_stock_json.sort_values("ORDER")

            df_stock_json = df_stock_json.rename(columns={
                "Item Code": "Kode SKU JIM",
                "Item Name": "Item Name JIM"
            })

            stock_agent_data = (
                df_stock_json
                .fillna("")
                .to_dict(orient="records")
            )

        # =====================
        # FINAL RESULT
        # =====================
        result = {
            "invoice_data": invoice_data,
            "stock_agent_data": stock_agent_data,
            "excel_base64": excel_base64
        }

        print(json.dumps(result, default=str))

    except Exception as e:
        sys.stderr.write(str(e))
        sys.exit(1)

if __name__ == "__main__":
    run()