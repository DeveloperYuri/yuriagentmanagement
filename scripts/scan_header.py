import sys
import pandas as pd
import json
import os

# Default keywords jika user tidak menginputkan apa-apa
DEFAULT_KEYWORDS = {
    "code": ["kode", "code", "sku", "copc", "co", "item"],
    "name": ["nama", "name", "produk", "barang", "product", "nak"],
    "qty": ["qty", "jumlah", "quantity", "pcs", "ss"],
    "price": ["harga", "price"],
    "supplier": ["supplier", "customer", "pelanggan"],
    # "date": ["tanggal", "date", "tgl"]
}
# Gabungkan semua, tapi taruh keyword panjang di depan
# DEFAULT_KEYWORDS = {
#     "code": ["kode", "sku", "item", "copc", "co"], 
#     "name": ["nama", "produk", "barang", "product", "na"],
#     "qty": ["jumlah", "quantity", "pcs", "ss", "qty"],
#     "date": ["tanggal", "date", "tgl"]
# }

def keyword_score(row, target_keywords):
    """Menghitung skor berdasarkan kemunculan keyword pada baris."""
    score = 0
    # Gabungkan semua keyword menjadi satu list flat agar pencarian lebih cepat
    all_keywords = [k.lower() for sublist in target_keywords.values() for k in sublist]
    
    for word in all_keywords:
        # Menghitung berapa kali keyword muncul di baris tersebut
        score += row.str.contains(word, na=False).sum()
    return score

def get_headers():
    try:
        # Validasi input argumen
        if len(sys.argv) < 3:
            raise ValueError("Penggunaan: python script.py <file_path> <sheet_name> <optional_json_keywords>")

        file_path = sys.argv[1]
        sheet_input = sys.argv[2]
        
        # 1. AMBIL CUSTOM KEYWORDS (Jika ada)
        # Input diharapkan berupa JSON string: '{"key1": ["word1", "word2"]}'
        custom_keywords = DEFAULT_KEYWORDS
        if len(sys.argv) > 3:
            try:
                provided_keywords = json.loads(sys.argv[3])
                if isinstance(provided_keywords, dict):
                    custom_keywords = provided_keywords
            except json.JSONDecodeError:
                # Jika JSON tidak valid, tetap gunakan default atau beri peringatan di stderr
                print("Warning: JSON keyword tidak valid, menggunakan default.", file=sys.stderr)

        ext = os.path.splitext(file_path)[1].lower()
        engine = 'pyxlsb' if ext == '.xlsb' else 'xlrd' if ext == '.xls' else 'openpyxl'

        xls = pd.ExcelFile(file_path, engine=engine)
        sheet_map = {s.strip().lower(): s for s in xls.sheet_names}
        key = sheet_input.strip().lower()

        if key not in sheet_map:
            print(json.dumps({"error": f"Sheet '{sheet_input}' tidak ditemukan", "available_sheets": xls.sheet_names}))
            sys.exit(1)

        real_sheet = sheet_map[key]
        raw = pd.read_excel(file_path, sheet_name=real_sheet, header=None, nrows=20, engine=engine)

        # 2. DETEKSI HEADER DENGAN KEYWORD DINAMIS
        best_row = 0
        best_score = 0
        
        for i in range(min(20, len(raw))):
            row = raw.iloc[i].astype(str).str.lower()
            non_empty = row[row.str.strip() != ""]
            
            if len(non_empty) < 3: continue
            if row.str.len().mean() > 30: continue # Skip baris kalimat panjang

            # Hitung skor keyword
            k_score = keyword_score(row, custom_keywords)
            
            # Hitung jumlah sel yang bukan angka (asumsi header adalah teks)
            text_count = (~non_empty.str.match(r'^\d+(\.\d+)?$')).sum()

            # Bobot: Keyword sangat penting (x3), keberadaan teks juga penting
            score = text_count + (k_score * 3)

            # Lookahead: Jika baris di bawahnya banyak angka, probabilitas ini header meningkat
            if i + 1 < len(raw):
                next_row = raw.iloc[i + 1].astype(str)
                numeric_next = next_row.str.match(r'^\d+(\.\d+)?$').sum()
                if numeric_next >= 2:
                    score += 5

            if score > best_score:
                best_score = score
                best_row = i

        # Fallback
        if best_score < 2:
            best_row = 0

        # 3. BACA DATA (Single Header Logic untuk simpelnya)
        df = pd.read_excel(file_path, sheet_name=real_sheet, header=best_row, engine=engine)
        
        # Clean headers
        headers = [str(c).split("Unnamed")[0].strip() for c in df.columns]

        print(json.dumps({
            "headers": [h for h in headers if h and not h.lower().startswith("unnamed")],
            "used_sheet": real_sheet,
            "detected_row": best_row,
            "using_custom_keywords": len(sys.argv) > 3
        }))

    except Exception as e:
        print(json.dumps({"error": str(e), "headers": []}))
        sys.exit(1)

if __name__ == "__main__":
    get_headers()

# import sys
# import pandas as pd
# import json
# import os

# def score_header_row(row):
#     values = row.astype(str).str.lower()

#     non_empty = values[values.str.strip() != ""]
#     if len(non_empty) == 0:
#         return 0

#     score = 0

#     # 🔥 struktur utama (tanpa keyword bergantung)
#     text_cells = non_empty[~non_empty.str.match(r'^\d+(\.\d+)?$')]
#     score += len(text_cells) * 2

#     # 🔥 optional booster (boleh hapus kalau mau pure fleksibel)
#     score += values.str.contains("kode|sku|qty|nama|customer|invoice").sum()

#     return score

# def get_headers():
#     try:
#         file_path = sys.argv[1]
#         sheet_input = sys.argv[2]
        
#         ext = os.path.splitext(file_path)[1].lower()

#         if ext == '.xlsb':
#             engine = 'pyxlsb'
#         elif ext == '.xls':
#             engine = 'xlrd'
#         else:
#             engine = 'openpyxl'

#         # =========================
#         # AMBIL SEMUA SHEET
#         # =========================
#         # xls = pd.ExcelFile(file_path)
#         xls = pd.ExcelFile(file_path, engine=engine)

#         sheet_map = {
#             s.strip().lower(): s for s in xls.sheet_names
#         }

#         key = sheet_input.strip().lower()

#         if key not in sheet_map:
#             print(json.dumps({
#                 "error": f"Sheet '{sheet_input}' tidak ditemukan",
#                 "available_sheets": xls.sheet_names
#             }))
#             sys.exit(1)

#         real_sheet = sheet_map[key]

#         # =========================
#         # BACA RAW UNTUK DETECT HEADER
#         # =========================
#         raw = pd.read_excel(
#             file_path,
#             sheet_name=real_sheet,
#             header=None,
#             nrows=20,
#             engine=engine
#             # engine='openpyxl'
#         )

#         # =========================
#         # DETECT HEADER (KEYWORD BASED)
#         # =========================
#         best_row = 0
#         best_score = 0

#         for i in range(min(20, len(raw))):
#             row = raw.iloc[i].astype(str).str.lower()

#             # score = (
#             #     row.str.contains("customer").sum() +
#             #     row.str.contains("invoice").sum() +
#             #     row.str.contains("kode").sum() +
#             #     row.str.contains("nama").sum() +
#             #     row.str.contains("barang").sum() +
#             #     row.str.contains("qty").sum() +
#             #     row.str.contains("jumlah").sum()
#             # )
            
#             score = score_header_row(raw.iloc[i])
            
#             # print(f"ROW: {i} SCORE: {score}", file=sys.stderr)
#             with open("/tmp/debug.log", "a") as f:
#                 f.write(f"ROW: {i} SCORE: {score}\n")

#             if score > best_score:
#                 best_score = score
#                 best_row = i

#         # fallback kalau ga ketemu
#         if best_score < 2:
#             best_row = 0

#         # =========================
#         # DETECT MULTI HEADER (SMART)
#         # =========================
#         is_multi_header = False

#         if best_row + 1 < len(raw):
#             row1 = raw.iloc[best_row].astype(str).str.lower()
#             row2 = raw.iloc[best_row + 1].astype(str).str.lower()

#             # deteksi group header (bulan / total)
#             months = ["jan","feb","mar","apr","mei","jun","jul","aug","sep","okt","nov","des"]

#             is_group_header = any(
#                 any(m in cell for m in months)
#                 for cell in row1
#             ) or row1.str.contains("total").sum() >= 1

#             # deteksi header asli
#             is_real_header = (
#                 row2.str.contains("qty|jumlah|harga|total|pcs|barang|customer|invoice").sum() >= 2
#             )

#             if is_group_header and is_real_header:
#                 is_multi_header = True

#         # =========================
#         # BACA DATA
#         # =========================
#         if is_multi_header:
#             df = pd.read_excel(
#                 file_path,
#                 sheet_name=real_sheet,
#                 header=[best_row, best_row + 1],
#                 engine=engine
#                 # engine='openpyxl'
#             )

#             # flatten header
#             # df.columns = [
#             #     " ".join([str(i) for i in col if str(i) != 'nan']).strip()
#             #     for col in df.columns
#             # ]
            
#             new_cols = []

#             for col in df.columns:
#                 if isinstance(col, tuple):
#                     level1, level2 = col

#                     # ambil level ke-2 (header asli)
#                     if str(level2) != 'nan' and not str(level2).lower().startswith('unnamed'):
#                         new_cols.append(str(level2).strip())
#                     else:
#                         new_cols.append(str(level1).strip())
#                 else:
#                     new_cols.append(str(col).strip())

#             df.columns = new_cols

#         else:
#             df = pd.read_excel(
#                 file_path,
#                 sheet_name=real_sheet,
#                 header=best_row,
#                 engine=engine
#                 # engine='openpyxl'
#             )

#         # =========================
#         # FIX MERGED CELL
#         # =========================
#         df = df.ffill()

#         # =========================
#         # CLEAN HEADER
#         # =========================
#         # headers = [
#         #     str(c).strip()
#         #     for c in df.columns
#         #     if c and not str(c).lower().startswith("unnamed")
#         # ]
        
#         def clean_column(col):
#             col = str(col)

#             # hapus bagian Unnamed
#             col = col.split("Unnamed")[0]

#             return col.strip()

#         df.columns = [clean_column(c) for c in df.columns]
        
#         headers = df.columns.tolist()

#         # =========================
#         # OUTPUT
#         # =========================
#         print(json.dumps({
#             "headers": headers,
#             "used_sheet": real_sheet,
#             "multi_header": bool(is_multi_header)
#         }))

#     except Exception as e:
#         print(json.dumps({
#             "error": str(e),
#             "headers": []
#         }))
#         sys.exit(1)

# if __name__ == "__main__":
#     get_headers()