import sys
import pandas as pd
import json

def get_headers():
    try:
        file_path = sys.argv[1]
        sheet_input = sys.argv[2]

        # =========================
        # AMBIL SEMUA SHEET
        # =========================
        xls = pd.ExcelFile(file_path)

        sheet_map = {
            s.strip().lower(): s for s in xls.sheet_names
        }

        key = sheet_input.strip().lower()

        if key not in sheet_map:
            print(json.dumps({
                "error": f"Sheet '{sheet_input}' tidak ditemukan",
                "available_sheets": xls.sheet_names
            }))
            sys.exit(1)

        real_sheet = sheet_map[key]

        # =========================
        # BACA RAW UNTUK DETECT HEADER
        # =========================
        raw = pd.read_excel(
            file_path,
            sheet_name=real_sheet,
            header=None,
            nrows=20,
            engine='openpyxl'
        )

        # =========================
        # DETECT HEADER (KEYWORD BASED)
        # =========================
        best_row = 0
        best_score = 0

        for i in range(min(20, len(raw))):
            row = raw.iloc[i].astype(str).str.lower()

            score = (
                row.str.contains("customer").sum() +
                row.str.contains("invoice").sum() +
                row.str.contains("kode").sum() +
                row.str.contains("nama").sum() +
                row.str.contains("barang").sum() +
                row.str.contains("qty").sum() +
                row.str.contains("jumlah").sum()
            )

            if score > best_score:
                best_score = score
                best_row = i

        # fallback kalau ga ketemu
        if best_score < 2:
            best_row = 0

        # =========================
        # DETECT MULTI HEADER (SMART)
        # =========================
        is_multi_header = False

        if best_row + 1 < len(raw):
            row1 = raw.iloc[best_row].astype(str).str.lower()
            row2 = raw.iloc[best_row + 1].astype(str).str.lower()

            # deteksi group header (bulan / total)
            months = ["jan","feb","mar","apr","mei","jun","jul","aug","sep","okt","nov","des"]

            is_group_header = any(
                any(m in cell for m in months)
                for cell in row1
            ) or row1.str.contains("total").sum() >= 1

            # deteksi header asli
            is_real_header = (
                row2.str.contains("qty|jumlah|harga|total|pcs|barang|customer|invoice").sum() >= 2
            )

            if is_group_header and is_real_header:
                is_multi_header = True

        # =========================
        # BACA DATA
        # =========================
        if is_multi_header:
            df = pd.read_excel(
                file_path,
                sheet_name=real_sheet,
                header=[best_row, best_row + 1],
                engine='openpyxl'
            )

            # flatten header
            # df.columns = [
            #     " ".join([str(i) for i in col if str(i) != 'nan']).strip()
            #     for col in df.columns
            # ]
            
            new_cols = []

            for col in df.columns:
                if isinstance(col, tuple):
                    level1, level2 = col

                    # ambil level ke-2 (header asli)
                    if str(level2) != 'nan' and not str(level2).lower().startswith('unnamed'):
                        new_cols.append(str(level2).strip())
                    else:
                        new_cols.append(str(level1).strip())
                else:
                    new_cols.append(str(col).strip())

            df.columns = new_cols

        else:
            df = pd.read_excel(
                file_path,
                sheet_name=real_sheet,
                header=best_row,
                engine='openpyxl'
            )

        # =========================
        # FIX MERGED CELL
        # =========================
        df = df.ffill()

        # =========================
        # CLEAN HEADER
        # =========================
        # headers = [
        #     str(c).strip()
        #     for c in df.columns
        #     if c and not str(c).lower().startswith("unnamed")
        # ]
        
        def clean_column(col):
            col = str(col)

            # hapus bagian Unnamed
            col = col.split("Unnamed")[0]

            return col.strip()

        df.columns = [clean_column(c) for c in df.columns]
        
        headers = df.columns.tolist()

        # =========================
        # OUTPUT
        # =========================
        print(json.dumps({
            "headers": headers,
            "used_sheet": real_sheet,
            "multi_header": bool(is_multi_header)
        }))

    except Exception as e:
        print(json.dumps({
            "error": str(e),
            "headers": []
        }))
        sys.exit(1)

if __name__ == "__main__":
    get_headers()