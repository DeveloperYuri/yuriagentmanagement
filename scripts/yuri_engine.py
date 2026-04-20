import pandas as pd
import numpy as np
import sys
import json
import os
import decimal

def scan_file(file_path):
    try:
        xls = pd.ExcelFile(file_path)
        all_sheets = []

        for sheet_name in xls.sheet_names:
            raw = pd.read_excel(xls, sheet_name=sheet_name, header=None, nrows=60, dtype=str)

            keywords = ['KODE', 'NAMA', 'QTY', 'HARGA', 'TOTAL', 'INVOICE', 'SKU', 'CUSTOMER', 'KUANTITAS', 'DISKON']
            h_idx, max_score = 0, 0

            for i, row in raw.iterrows():
                row_str = " ".join([str(v).upper() for v in row.values if pd.notnull(v)])
                score = sum(1 for k in keywords if k in row_str)
                if score > max_score:
                    max_score, h_idx = score, i

            row_top = raw.iloc[h_idx].copy().ffill()
            row_bottom = raw.iloc[h_idx+1].copy()

            final_headers = []
            for idx in range(len(row_top)):
                v_top = str(row_top.iloc[idx]).strip() if pd.notnull(row_top.iloc[idx]) else ""
                v_bot = str(row_bottom.iloc[idx]).strip() if pd.notnull(row_bottom.iloc[idx]) else ""

                name = f"{v_top} {v_bot}" if v_top and v_bot and v_top != v_bot else (v_top or v_bot or f"KOLOM_{idx}")
                final_headers.append(name.replace('\n', ' ').strip().replace('nan', ''))

            clean_headers = [h for h in final_headers if h and "KOLOM_" not in h]

            all_sheets.append({
                "sheet": sheet_name,
                "headers": clean_headers,
                "header_row_index": int(h_idx)
            })

        print(json.dumps({
            "status": "success",
            "sheets": all_sheets
        }))

    except Exception as e:
        print(json.dumps({"status": "error", "message": str(e)}))


def export_file(input_path, mapping_json, sheet_name, output_path):
    try:
        mapping = json.loads(mapping_json)

        xls = pd.ExcelFile(input_path)

        if sheet_name not in xls.sheet_names:
            raise Exception(f"Sheet '{sheet_name}' tidak ditemukan. Available: {xls.sheet_names}")

        df_raw = pd.read_excel(xls, sheet_name=sheet_name, header=None, dtype=str)

        # DETECT HEADER
        keywords = ['KODE', 'NAMA', 'QTY', 'HARGA', 'TOTAL', 'INVOICE', 'SKU', 'CUSTOMER', 'UNIT', 'JUMLAH']
        h_idx, max_score = 0, 0

        for i, row in df_raw.iterrows():
            row_str = " ".join([str(v).upper() for v in row.values if pd.notnull(v)])
            score = sum(1 for k in keywords if k in row_str)
            if score > max_score:
                max_score = score
                h_idx = i

        row_top = df_raw.iloc[h_idx].copy().ffill()
        row_bottom = df_raw.iloc[h_idx+1].copy()

        final_headers = []
        for idx in range(len(row_top)):
            v_top = str(row_top.iloc[idx]).strip() if pd.notnull(row_top.iloc[idx]) else ""
            v_bot = str(row_bottom.iloc[idx]).strip() if pd.notnull(row_bottom.iloc[idx]) else ""

            if v_top and v_bot and v_top != v_bot and v_bot.lower() != 'nan':
                name = f"{v_top} {v_bot}"
            else:
                name = v_top or v_bot or f"KOLOM_{idx}"

            final_headers.append(name.replace('\n', ' ').strip().replace('nan', ''))

        # DATA START
        data_start_idx = h_idx + 1
        test_row = " ".join(df_raw.iloc[h_idx+1].astype(str))

        if max_score > 0 and any(char.isdigit() for char in test_row):
            data_start_idx = h_idx + 1
        else:
            data_start_idx = h_idx + 2

        df = df_raw.iloc[data_start_idx:].copy()
        df.columns = final_headers[:len(df.columns)]

        # MAPPING
        f_map = {v: k for k, v in mapping.items() if v != '-- Kosongkan --'}
        res = df.rename(columns=f_map)

        # =========================
        # 🔥 PARSE NUMBER FIX
        # =========================
        def parse_number(val):
            try:
                if pd.isna(val):
                    return np.nan

                s = str(val).strip()

                if '.' in s and ',' in s:
                    if s.rfind(',') > s.rfind('.'):
                        s = s.replace('.', '').replace(',', '.')
                    else:
                        s = s.replace(',', '')
                elif ',' in s:
                    if s.count(',') > 1:
                        s = s.replace(',', '')
                    else:
                        s = s.replace(',', '.')
                elif '.' in s:
                    pass

                return float(s)

            except:
                return np.nan

        numeric_cols = ['Qty Terjual Karton', 'Qty Terjual (PCS)', 'Total Invoice Value']
        
        
        
        def round_half_up(n):
            if pd.isna(n):
                return 0
            return int(decimal.Decimal(str(n)).quantize(
        decimal.Decimal('1'),
        rounding=decimal.ROUND_HALF_UP
        ))
            
        for col in numeric_cols:
            if col in res.columns:
                res[col] = res[col].apply(parse_number)      # string → float
                res[col] = res[col].apply(round_half_up) 
    
        # for col in numeric_cols:
        #     if col in res.columns:
        #         res[col] = res[col].apply(parse_number)
        #         res[col] = res[col].round(0)  # 🔥 BULATIN

        # FORMAT TANGGAL
        if 'Tanggal Invoice' in res.columns:
            res['Tanggal Invoice'] = pd.to_datetime(
                res['Tanggal Invoice'], errors='coerce'
            ).dt.strftime('%m/%d/%Y')

        # NAMA AGEN
        if 'Nama Agen' in mapping:
            res['Nama Agen'] = mapping['Nama Agen']

        # FFILL
        identitas = [
            'Nama Agen','Kode Customer','Nama Customer','Alamat Customer',
            'Nomor Telepon/HP Customer','Invoice Nomor Agen','Tanggal Invoice',
            'Sales','Tipe Customer'
        ]

        for c in identitas:
            if c in res.columns:
                res[c] = res[c].replace(['', 'nan', 'NaN', 'None'], np.nan)
                res[c] = res[c].ffill()

        # CLEAN DATA
        check_col = None
        for target, excel_col in mapping.items():
            if target in ['Nama SKU', 'SKU Kode Agen'] and excel_col != '-- Kosongkan --':
                check_col = target
                break

        if check_col and check_col in res.columns:
            res = res[res[check_col].notnull() & (res[check_col].astype(str).str.strip() != "")]

        # TARGET
        target_fields = [
            'Nama Agen','Kode Customer','Nama Customer','Alamat Customer',
            'Nomor Telepon/HP Customer','Invoice Nomor Agen','Tanggal Invoice',
            'Tipe Customer','Sales','SKU Kode Agen','Nama SKU',
            'Qty Terjual (Karton)', 'Qty Terjual (PCS)',
            '% Diskon 1 (Reguler)','% Diskon 2 (Cash)','% Diskon 3 (DC Free)',
            '% Diskon 4 (Promo 1)','% Diskon 5 (Promo 2)','% Diskon 6 (Rp)',
            'Quantity Bonus','Rafraksi',
            'Total Invoice Value'
        ]

        for f in target_fields:
            if f not in res.columns:
                res[f] = 0 if any(x in f.lower() for x in ['qty','total','diskon','bonus','rafraksi']) else ""

        # =========================
        # WRITE EXCEL
        # =========================
        writer = pd.ExcelWriter(output_path, engine='xlsxwriter')
        res[target_fields].to_excel(writer, index=False, sheet_name='Sales_Report')

        workbook = writer.book
        worksheet = writer.sheets['Sales_Report']

        header_fmt = workbook.add_format({
            'bold': True,
            'align': 'center',
            'bg_color': '#D9EAD3',
            'border': 1
        })

        text_fmt = workbook.add_format({'border': 1})

        number_fmt = workbook.add_format({
            'num_format': '#,##0',
            'border': 1
        })

        # for col_num, value in enumerate(target_fields):
        #     worksheet.write(0, col_num, value, header_fmt)

        #     if value in numeric_cols:
        #         worksheet.set_column(col_num, col_num, 22, number_fmt)
        #     else:
        #         worksheet.set_column(col_num, col_num, 22, text_fmt)
        for col_num, value in enumerate(target_fields):
            worksheet.write(0, col_num, value, header_fmt)

        # hitung panjang max data di kolom
            if value in res.columns:
                max_len = max(
                res[value].astype(str).map(len).max(),
                len(value)
                ) + 2
            else:
                max_len = len(value) + 2

    # apply format + width
            if value in numeric_cols:
                worksheet.set_column(col_num, col_num, max_len, number_fmt)
            else:
                worksheet.set_column(col_num, col_num, max_len, text_fmt)

        writer.close()

        print(json.dumps({
            "status": "success",
            "file": output_path
        }))

    except Exception as e:
        import traceback
        print(json.dumps({
            "status": "error",
            "message": str(e) + "\n" + traceback.format_exc()
        }))


if __name__ == "__main__":
    mode = sys.argv[1]

    if mode == "scan":
        scan_file(sys.argv[2])

    elif mode == "export":
        export_file(sys.argv[2], sys.argv[3], sys.argv[4], sys.argv[5])