import pandas as pd
import sys
import json
import re
from rapidfuzz import process, fuzz

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
def detect_header(df):
    for i in range(min(10, len(df))):
        row = df.iloc[i].astype(str).str.lower()
        if row.str.contains("nama").any() or row.str.contains("item").any():
            return i
    return 0

# =========================
# MAIN
# =========================
def run():
    try:
        raw = sys.stdin.read()
        input_data = json.loads(raw)

        file_path = input_data["file_path"]
        sheet_name = input_data["source_sheet"]
        master_items = input_data["master_data"]

        # =========================
        # MASTER DATA
        # =========================
        master = pd.DataFrame(master_items)

        if master.empty:
            print(json.dumps({"error": "Master kosong"}))
            return

        master["clean"] = master["item_name"].apply(normalize)

        # =========================
        # AGENT DATA
        # =========================
        raw_agent = pd.read_excel(file_path, sheet_name=sheet_name, header=None)
        header_row = detect_header(raw_agent)

        agent = pd.read_excel(file_path, sheet_name=sheet_name, header=header_row)

        # Detect kolom
        name_cols = [c for c in agent.columns if "nama" in str(c).lower() or "item" in str(c).lower()]
        code_cols = [c for c in agent.columns if "kode" in str(c).lower() or "sku" in str(c).lower()]

        if not name_cols:
            print(json.dumps({"error": "Kolom nama tidak ditemukan"}))
            return

        name_col = name_cols[0]
        code_col = code_cols[0] if code_cols else None

        agent["clean"] = agent[name_col].apply(normalize)

        master_list = master["clean"].tolist()

        # =========================
        # MATCHING
        # =========================
        results = []

        for _, row in agent.iterrows():
            clean_name = row["clean"]

            m = process.extractOne(clean_name, master_list, scorer=fuzz.token_sort_ratio)

            if m:
                match_name, score, idx = m
                master_row = master.iloc[idx]

                results.append({
                    "Agent Nama": row[name_col],
                    "Kode Agent": row[code_col] if code_col else None,
                    "Master Nama": master_row["item_name"],
                    "Item Code": master_row["item_code"],
                    "Score": round(score, 2),
                    "Status": "MATCH" if score >= 80 else "REVIEW"
                })
            else:
                results.append({
                    "Agent Nama": row[name_col],
                    "Kode Agent": row[code_col] if code_col else None,
                    "Master Nama": None,
                    "Item Code": None,
                    "Score": 0,
                    "Status": "NOT FOUND"
                })

        print(json.dumps({"results": results}))

    except Exception as e:
        print(json.dumps({"error": str(e)}))

if __name__ == "__main__":
    run()