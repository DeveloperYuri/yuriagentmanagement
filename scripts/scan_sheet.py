import pandas as pd
import sys
import json
import os

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No path provided"}))
        sys.exit(1)

    file_path = sys.argv[1]

    if not os.path.exists(file_path):
        print(json.dumps({"error": f"File not found: {file_path}"}))
        sys.exit(1)

    try:
        # Load excel tanpa baca data (hanya metadata sheet)
        xl = pd.ExcelFile(file_path)
        print(json.dumps(xl.sheet_names))
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    main()