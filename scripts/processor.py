import pandas as pd
import sys
import json
import re
from rapidfuzz import process, fuzz
from io import BytesIO
import os
import base64
import numpy as np

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

    if not text or pd.isna(text):
        return ""

    text = str(text).lower()

    text = re.sub(
        r'[^a-z0-9 ]',
        ' ',
        text
    )

    remove_words = [
        "gr",
        "gram",
        "bogof",
        "pouch",
        "pcs",
        "reg",
        "free",
        "promo"
    ]

    for w in remove_words:
        text = text.replace(w, "")

    return re.sub(
        r'\s+',
        ' ',
        text
    ).strip()

# =========================
# DETECT HEADER
# =========================
def detect_header(
    file_path,
    sheet,
    engine
):

    raw = pd.read_excel(
        file_path,
        sheet_name=sheet,
        header=None,
        engine=engine
    )

    raw_header = raw.head(20)

    best_row = 0
    best_score = 0

    for i in range(len(raw_header)):

        row_content = (
            raw_header
            .iloc[i]
            .astype(str)
            .str.lower()
        )

        score = (
            row_content
            .str.contains("customer")
            .sum()

            +

            row_content
            .str.contains("invoice")
            .sum()

            +

            row_content
            .str.contains("kode")
            .sum()

            +

            row_content
            .str.contains("nama")
            .sum()

            +

            row_content
            .str.contains("alamat")
            .sum()

            +

            row_content
            .str.contains("qty|jumlah")
            .sum()

            +

            row_content
            .str.contains(
                "kode|co|copc"
            )
            .sum()
        )

        if score > best_score:
            best_score = score
            best_row = i

    header_values = raw.iloc[
        best_row
    ].values

    df = raw.iloc[
        best_row + 1:
    ].copy()

    clean_columns = [

        str(c)
        .strip()
        .replace('\n', ' ')

        if pd.notna(c)

        else f"Unnamed_{i}"

        for i, c in enumerate(
            header_values
        )
    ]

    df.columns = clean_columns

    return df

# =========================
# COLUMN MATCH
# =========================
def normalize_col(x):

    return (
        str(x)
        .lower()
        .replace(" ", "")
        .replace("_", "")
        .strip()
    )

def find_column(
    target,
    columns
):

    if not target:
        return None

    target_clean = normalize_col(
        target
    )

    for c in columns:

        if normalize_col(c) == target_clean:
            return c

    return None

def is_valid_data(df):

    non_empty_cols = df.notna().sum()

    return (
        non_empty_cols > 0
    ).sum() >= 3

def autofit_columns(ws):

    for col in ws.columns:

        max_length = 0

        col_letter = col[0].column_letter

        for cell in col:

            try:

                if cell.value:

                    max_length = max(
                        max_length,
                        len(str(cell.value))
                    )

            except:
                pass

        ws.column_dimensions[
            col_letter
        ].width = max_length + 2

# =========================
# MAIN
# =========================
def run():

    try:

        input_data = json.loads(
            sys.stdin.read()
        )

        file_path = input_data[
            "file_path"
        ]

        mapping_jim = input_data[
            "mapping_jim"
        ]

        mapping_inv = input_data[
            "mapping_inv"
        ]

        master_items = input_data[
            "master_data"
        ]
        
        nama_agent = input_data.get(
            "nama_agent",
            ""
        )

        mapping_df = pd.DataFrame(
            input_data.get(
                "mapping_data",
                []
            )
        )

        # =====================
        # MASTER
        # =====================
        master = pd.DataFrame(
            master_items
        )

        # =====================
        # MAPPING LOOKUP
        # =====================
        mapping_lookup = {}

        if not mapping_df.empty:

            mapping_df["agent_sku"] = (
                mapping_df["agent_sku"]
                .astype(str)
                .str.replace(
                    ".0",
                    "",
                    regex=False
                )
                .str.strip()
            )

            mapping_lookup = dict(
                zip(
                    mapping_df[
                        "agent_sku"
                    ],

                    mapping_df[
                        "item_code"
                    ].astype(str)
                )
            )

        # =====================
        # OPEN FILE
        # =====================
        engine = get_engine(file_path)

        xls = pd.ExcelFile(
            file_path,
            engine=engine
        )

        df_jim_list = []
        df_invoice_list = []

        # =====================
        # LOOP SHEET
        # =====================
        for sheet in xls.sheet_names:

            # print("========== SHEET ==========")
            # print(sheet)
            
            # sys.stderr.write(...)

            try:

                df = detect_header(
                    file_path,
                    sheet,
                    engine
                )

                # print("========== COLUMNS ==========")
                # print(df.columns.tolist())

                df = df.ffill()

                df = df[
                    df.notna().sum(axis=1) > 2
                ]

                df = df.reset_index(
                    drop=True
                )

                if not is_valid_data(df):
                    continue

            except Exception as e:

                print(e)

                continue

            # =====================
            # PROSES JIM
            # =====================
            col_sku_agent = find_column(
                mapping_jim.get(
                    "Kode SKU Agent"
                ),
                df.columns
            )

            col_stock_agent = find_column(
                mapping_jim.get(
                    "Stock Akhir Agent"
                ),
                df.columns
            )

            col_nama_agent = find_column(
                mapping_jim.get(
                    "Nama Produk"
                ),
                df.columns
            )

            if (
                col_sku_agent
                and col_stock_agent
            ):

                df_j = df.copy().rename(
                    columns={
                        col_sku_agent:
                        "kode_agent",

                        col_stock_agent:
                        "stock_pcs"
                    }
                )

                df_j["kode_agent"] = (
                    df_j["kode_agent"]
                    .astype(str)
                    .str.replace(
                        r"\.0$",
                        "",
                        regex=True
                    )
                    .str.strip()
                )

                df_j["stock_pcs"] = (
                    pd.to_numeric(
                        df_j["stock_pcs"],
                        errors="coerce"
                    )
                    .fillna(0)
                )

                if col_nama_agent:

                    df_j["nama_produk"] = (
                        df[col_nama_agent]
                    )

                results_jim = []

                for idx, row in df_j.iterrows():

                    kode_agent = str(
                        row.get(
                            "kode_agent",
                            ""
                        )
                    ).replace(
                        ".0",
                        ""
                    ).strip()

                    mapped_item_code = (
                        mapping_lookup.get(
                            kode_agent
                        )
                    )
                    
                    # if kode_agent == "Y1701":
                    #     print("================================")
                    #     print("SKU:", repr(kode_agent))
                    #     print("MAPPED:", repr(mapped_item_code))
                    #     print("IN LOOKUP:", kode_agent in mapping_lookup)

                    if mapped_item_code:

                        m_filtered = master[
                            master[
                                "item_code"
                            ].astype(str)

                            ==

                            str(
                                mapped_item_code
                            )
                        ]

                        if not m_filtered.empty:

                            m_row = (
                                m_filtered
                                .iloc[0]
                            )

                            results_jim.append({

                                "No": idx + 1,

                                "Item Code":
                                m_row["item_code"],

                                "Item Name":
                                m_row["item_name"],

                                "Item Box":
                                m_row.get(
                                    "item_per_box",
                                    1
                                ),

                                "Kode SKU Agent":
                                row["kode_agent"],

                                "Nama Produk Agent":
                                row.get(
                                    "nama_produk"
                                ),

                                "Stock PCS":
                                row["stock_pcs"],

                                "MATCH_STATUS":
                                "CODE_MATCH"
                            })

                            continue

                    results_jim.append({

                        "ORDER": idx,

                        "Item Code": None,

                        "Item Name": None,

                        "Item Box": None,

                        "Kode SKU Agent":
                        row["kode_agent"],

                        "Nama Produk Agent":
                        row.get(
                            "nama_produk"
                        ),

                        "Stock PCS":
                        row["stock_pcs"],

                        "MATCH_STATUS":
                        "NOT_MATCH"
                    })

                if results_jim:

                    df_jim_list.append(
                        pd.DataFrame(
                            results_jim
                        )
                    )

            # =====================
            # PROSES INVOICE
            # =====================
            results_invoice = []

            col_invoice = find_column(
                mapping_inv.get(
                    "Invoice Nomor Agen"
                ),
                df.columns
            )

            col_customer = find_column(
                mapping_inv.get(
                    "Nama Customer"
                ),
                df.columns
            )

            col_sku = find_column(
                mapping_inv.get(
                    "SKU Kode Agen"
                ),
                df.columns
            )

            col_qty = find_column(
                mapping_inv.get(
                    "Qty Terjual (PCS)"
                ),
                df.columns
            )

            # print("COL INVOICE:", col_invoice)
            # print("COL CUSTOMER:", col_customer)
            # print("COL SKU:", col_sku)
            # print("COL QTY:", col_qty)

            if (
                col_invoice is not None
                and col_sku is not None
            ):

                for idx, row in df.iterrows():
                    
                    kode_agent_invoice = str(
                        row.get(col_sku, "")
                    ).replace(
                        ".0",
                        ""
                    ).strip()

                    mapped_item_code_invoice = (
                        mapping_lookup.get(
                            kode_agent_invoice
                        )
                    )

                    mapped_item_name_invoice = None

                    if mapped_item_code_invoice:

                        m_filtered_invoice = master[
                            master[
                                "item_code"
                            ].astype(str)

                            ==

                            str(
                                mapped_item_code_invoice
                            )
                        ]

                        if not m_filtered_invoice.empty:
                            
                            mapped_item_name_invoice = (
                                m_filtered_invoice
                                .iloc[0]
                                ["item_name"]
                            )

                    results_invoice.append({

                        # "Nama Agen":
                        # row.get(
                        #     find_column(
                        #         mapping_inv.get(
                        #             "Nama Agen"
                        #         ),
                        #         df.columns
                        #     )
                        # ),
                        "Nama Agen":
                        nama_agent,

                        "Kode Customer":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "Kode Customer"
                                ),
                                df.columns
                            )
                        ),

                        "Nama Customer":
                        row.get(col_customer),

                        "Alamat Customer":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "Alamat Customer"
                                ),
                                df.columns
                            )
                        ),

                        "Nomor Telepon/HP Customer":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "Nomor Telepon/HP Customer"
                                ),
                                df.columns
                            )
                        ),

                        "Invoice Nomor Agen":
                        row.get(col_invoice),

                        "Tanggal Invoice":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "Tanggal Invoice"
                                ),
                                df.columns
                            )
                        ),

                        "Tipe Customer":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "Tipe Customer"
                                ),
                                df.columns
                            )
                        ),

                        "Sales":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "Sales"
                                ),
                                df.columns
                            )
                        ),

                        "SKU Kode Agen":
                        kode_agent_invoice,

                        "Item Code":
                        mapped_item_code_invoice,

                        "Item Name":
                        mapped_item_name_invoice,

                        "MATCH_STATUS":
                        "CODE_MATCH"
                        if mapped_item_code_invoice
                        else "NOT_MATCH",

                        "Nama SKU":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "Nama SKU"
                                ),
                                df.columns
                            )
                        ),

                        "Qty Terjual (PCS)":
                        row.get(col_qty, 0),

                        "% Diskon 1 (Reguler)":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "% Diskon 1 (Reguler)"
                                ),
                                df.columns
                            )
                        ),

                        "% Diskon 2 (Cash)":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "% Diskon 2 (Cash)"
                                ),
                                df.columns
                            )
                        ),

                        "% Diskon 3 (DC Free)":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "% Diskon 3 (DC Free)"
                                ),
                                df.columns
                            )
                        ),

                        "% Diskon 4 (Promo 1)":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "% Diskon 4 (Promo 1)"
                                ),
                                df.columns
                            )
                        ),

                        "% Diskon 5 (Promo 2)":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "% Diskon 5 (Promo 2)"
                                ),
                                df.columns
                            )
                        ),

                        "% Diskon 6 (Rp)":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "% Diskon 6 (Rp)"
                                ),
                                df.columns
                            )
                        ),

                        "Quantity Bonus":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "Quantity Bonus"
                                ),
                                df.columns
                            )
                        ),

                        "Rafraksi":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "Rafraksi"
                                ),
                                df.columns
                            )
                        ),

                        "Total Invoice Value":
                        row.get(
                            find_column(
                                mapping_inv.get(
                                    "Total Invoice Value"
                                ),
                                df.columns
                            )
                        ),
                    })

                    # results_invoice.append({

                    #     "Nama Agen":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "Nama Agen"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "Kode Customer":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "Kode Customer"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "Nama Customer":
                    #     row.get(col_customer),

                    #     "Alamat Customer":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "Alamat Customer"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "Nomor Telepon/HP Customer":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "Nomor Telepon/HP Customer"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "Invoice Nomor Agen":
                    #     row.get(col_invoice),

                    #     "Tanggal Invoice":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "Tanggal Invoice"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "Tipe Customer":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "Tipe Customer"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "Sales":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "Sales"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "SKU Kode Agen":
                    #     row.get(col_sku),

                    #     "Nama SKU":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "Nama SKU"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "Qty Terjual (PCS)":
                    #     row.get(col_qty, 0),

                    #     "% Diskon 1 (Reguler)":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "% Diskon 1 (Reguler)"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "% Diskon 2 (Cash)":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "% Diskon 2 (Cash)"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "% Diskon 3 (DC Free)":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "% Diskon 3 (DC Free)"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "% Diskon 4 (Promo 1)":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "% Diskon 4 (Promo 1)"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "% Diskon 5 (Promo 2)":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "% Diskon 5 (Promo 2)"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "% Diskon 6 (Rp)":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "% Diskon 6 (Rp)"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "Quantity Bonus":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "Quantity Bonus"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "Rafraksi":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "Rafraksi"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),

                    #     "Total Invoice Value":
                    #     row.get(
                    #         find_column(
                    #             mapping_inv.get(
                    #                 "Total Invoice Value"
                    #             ),
                    #             df.columns
                    #         )
                    #     ),
                    # })

            if results_invoice:

                df_invoice_list.append(
                    pd.DataFrame(
                        results_invoice
                    )
                )

        # =====================
        # STOCK DATA
        # =====================
        stock_agent_data = []

        for df_result in df_jim_list:

            df_result = (
                df_result
                .replace([np.nan], None)
                .fillna("")
            )

            stock_agent_data.extend(
                df_result.to_dict(
                    orient="records"
                )
            )

        # =====================
        # INVOICE DATA
        # =====================
        invoice_data = []

        for df_inv in df_invoice_list:

            df_inv = (
                df_inv
                .replace([np.nan], None)
                .fillna("")
            )

            invoice_data.extend(
                df_inv.to_dict(
                    orient="records"
                )
            )

        # =====================
        # DATAFRAME FINAL
        # =====================
        final_stock_df = pd.DataFrame(
            stock_agent_data
        )

        final_invoice_df = pd.DataFrame(
            invoice_data
        )

        # =====================
        # EXCEL
        # =====================
        output = BytesIO()

        with pd.ExcelWriter(
            output,
            engine="openpyxl"
        ) as writer:
            
           # FILTER
            final_stock_df = final_stock_df[
                final_stock_df["MATCH_STATUS"] != "NOT_MATCH"
            ]

            final_invoice_df = final_invoice_df[
                final_invoice_df["MATCH_STATUS"] != "NOT_MATCH"
            ]
            
            # RESET NOMOR
            final_stock_df["No"] = range(
                1,
                len(final_stock_df) + 1
            )

            final_invoice_df["No"] = range(
                1,
                len(final_invoice_df) + 1
            )
            
            # =====================
            # KODE JIM
            # =====================
            # final_stock_df.to_excel(
            #     writer,
            #     sheet_name="kode produk JIM",
            #     index=False
            # )

            # ws_stock = writer.sheets[
            #     "kode produk JIM"
            # ]
            
            final_stock_df[[
                "No",
                "Item Code",
                "Item Name",
                "Item Box",
                "Kode SKU Agent",
                # "Stock PCS"
            ]].to_excel(
                writer,
                sheet_name="kode produk JIM",
                index=False
            )
            
            ws_stock = writer.sheets[
                "kode produk JIM"
            ]

            autofit_columns(ws_stock)

            # =====================
            # INVOICE
            # =====================
            # final_invoice_df.to_excel(
            #     writer,
            #     sheet_name="Invoice Agen",
            #     index=False
            # )
            
            # final_invoice_df["Tanggal Invoice"] = pd.to_datetime(
            #     final_invoice_df["Tanggal Invoice"],
            #     errors="coerce"
            # ).dt.strftime("%m/%d/%Y")
            
            # print("========== RAW TANGGAL ==========")

            # print(
            #     final_invoice_df["Tanggal Invoice"]
            #     .head(20)
            # )

            # print(
            #     final_invoice_df["Tanggal Invoice"]
            #     .dtype
            # )


            final_invoice_df["Tanggal Invoice"] = (
                final_invoice_df["Tanggal Invoice"]
                .astype(str)
                .str.replace(".0", "", regex=False)
                .str.strip()
            )
            
            final_invoice_df["Tanggal Invoice"] = pd.to_datetime(
                final_invoice_df["Tanggal Invoice"],
                errors="coerce"
            )

            final_invoice_df["Tanggal Invoice"] = (
                final_invoice_df["Tanggal Invoice"]
                .dt.strftime("%m/%d/%Y")
                .fillna("")
            )
            
            # print("========== CLEAN TANGGAL ==========")

            # print(
            #     final_invoice_df["Tanggal Invoice"]
            #     .head(20)
            # )
            
            
            # final_invoice_df["Tanggal Invoice"] = pd.to_datetime(
            #     final_invoice_df["Tanggal Invoice"],
            #     format="%Y%m%d",
            #     errors="coerce"
            # )

            # final_invoice_df["Tanggal Invoice"] = (
            #     final_invoice_df["Tanggal Invoice"]
            #     .dt.strftime("%m/%d/%Y")
            # )
            
            # print("========== PARSE DATE ==========")

            # print(
            #     final_invoice_df["Tanggal Invoice"]
            #     .head(20)
            # )
            
            final_invoice_df[[
                "Nama Agen",
                "Kode Customer",
                "Nama Customer",
                "Alamat Customer",
                "Nomor Telepon/HP Customer",
                "Invoice Nomor Agen",
                "Tanggal Invoice",
                "Tipe Customer",
                "Sales",
                "SKU Kode Agen",
                "Item Code",
                "Item Name",
                "Qty Terjual (PCS)",
                "% Diskon 1 (Reguler)",
                "% Diskon 2 (Cash)",
                "% Diskon 3 (DC Free)",
                "% Diskon 4 (Promo 1)",
                "% Diskon 5 (Promo 2)",
                "% Diskon 6 (Rp)",
                "Quantity Bonus",
                "Rafraksi",
                "Total Invoice Value"
            ]].to_excel(
                writer,
                sheet_name="Invoice Agen",
                index=False
            )

            ws_invoice = writer.sheets[
                "Invoice Agen"
            ]
            
            ws_invoice.auto_filter.ref = (
                ws_invoice.dimensions
            )

            autofit_columns(ws_invoice)
            
            # =====================
            # STOCK AGENT
            # =====================
            
            # final_stock_df["Stock Karton"] = np.rint(
            #     final_stock_df["Stock PCS"]
            #     / final_stock_df["Item Box"]
            # ).astype(int)
            
            # final_stock_df["Stock Karton"] = np.floor(
            #     (
            #         final_stock_df["Stock PCS"]
            #         /
            #         final_stock_df["Item Box"]
            #     ) + 0.5
            # ).astype(int)
            
            # final_stock_df["Stock Karton"] = (
            #     final_stock_df["Stock PCS"]
            #     /
            #     final_stock_df["Item Box"]
            # ).round().astype(int)
            
            # kode_jim_df = final_stock_df[[
            #     "Kode SKU Agent",
            #     "Item Code",
            #     "Item Name",
            #     "Stock PCS",
            #     "Stock Karton"
            #     # "MATCH_STATUS"
            # ]]
            
            # # gabungkan kode yang sama
            # kode_jim_df = (
            #     kode_jim_df
            #     .groupby(
            #         [
            #             "Kode SKU Agent",
            #             "Item Code",
            #             "Item Name"
            #         ],
            #         as_index=False
            #     )
            #     .agg({
            #         "Stock PCS": "sum",
            #         "Stock Karton": "sum"
            #     })
            # )
            
            kode_jim_df = final_stock_df[[
                "Kode SKU Agent",
                "Item Code",
                "Item Name",
                "Item Box",
                "Stock PCS"
            ]]

            # group stock pcs
            kode_jim_df = (
                kode_jim_df
                .groupby(
                    [
                        "Kode SKU Agent",
                        "Item Code",
                        "Item Name",
                        "Item Box"
                    ],
                    as_index=False,
                    sort=False
                )
                .agg({
                    "Stock PCS": "sum"
                })
            )

            # numeric
            kode_jim_df["Stock PCS"] = pd.to_numeric(
                kode_jim_df["Stock PCS"],
                errors="coerce"
            ).fillna(0)

            kode_jim_df["Item Box"] = pd.to_numeric(
                kode_jim_df["Item Box"],
                errors="coerce"
            ).fillna(1)

            # hitung karton ulang
            kode_jim_df["Stock Karton"] = np.rint(
                kode_jim_df["Stock PCS"]
                / kode_jim_df["Item Box"]
            ).astype(int)

            kode_jim_df.to_excel(
                writer,
                sheet_name="Stock Agent",
                index=False
            )

            ws_jim = writer.sheets[
                "Stock Agent"
            ]

            autofit_columns(ws_jim)
            
            
        # =====================
        # BASE64
        # =====================
        excel_base64 = base64.b64encode(
            output.getvalue()
        ).decode("utf-8")

        # =====================
        # RESULT
        # =====================
        
        # =====================
# UPDATE INVOICE DATA
# =====================
        invoice_data = (
            final_invoice_df
            .replace([np.nan], None)
            .to_dict(orient="records")
        )

        result = {

            "invoice_data":
            invoice_data,

            "stock_agent_data":
            stock_agent_data,

            "excel_base64":
            excel_base64
        }

        print(
            json.dumps(
                result,
                default=str
            )
        )

    except Exception as e:

        sys.stderr.write(
            str(e)
        )

        sys.exit(1)

if __name__ == "__main__":
    run()