import sys
import pandas as pd
import json

def get_headers():
    try:
        file_path = sys.argv[1]
        sheet_input = sys.argv[2]

        # =========================
        # AMBIL SEMUA NAMA SHEET
        # =========================
        xls = pd.ExcelFile(file_path)

        # Normalisasi: strip + lower
        sheet_map = {
            s.strip().lower(): s for s in xls.sheet_names
        }

        key = sheet_input.strip().lower()

        # =========================
        # VALIDASI SHEET
        # =========================
        if key not in sheet_map:
            print(json.dumps({
                "error": f"Sheet '{sheet_input}' tidak ditemukan",
                "available_sheets": xls.sheet_names
            }))
            sys.exit(1)

        real_sheet = sheet_map[key]

        # =========================
        # SCAN HEADER
        # =========================
        raw = pd.read_excel(
            file_path,
            sheet_name=real_sheet,
            header=None,
            nrows=20,
            engine='openpyxl'
        )

        best_row = 0
        max_cols = 0

        for i, row in raw.iterrows():
            non_empty = row.dropna().count()
            if non_empty > max_cols:
                max_cols = non_empty
                best_row = i

        # =========================
        # BACA ULANG DENGAN HEADER
        # =========================
        df = pd.read_excel(
            file_path,
            sheet_name=real_sheet,
            header=best_row,
            engine='openpyxl'
        )

        headers = [
            str(c).strip()
            for c in df.columns
            if c is not None and not str(c).startswith('Unnamed:')
        ]

        print(json.dumps({
            "headers": headers,
            "used_sheet": real_sheet
        }))

    except Exception as e:
        print(json.dumps({
            "error": str(e),
            "headers": []
        }))
        sys.exit(1)

if __name__ == "__main__":
    get_headers()