<?php

namespace App\Imports;

use App\Models\Item;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class ItemsImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        foreach ($rows->skip(1) as $row) {

            if (empty($row[0])) {
                continue;
            }

            $itemCode = trim((string) $row[0]);

            $item = Item::where('item_code', $itemCode)->first();

            if ($item) {

                $item->length_cm = (float) $row[4];
                $item->width_cm = (float) $row[5];
                $item->height_cm = (float) $row[6];
                $item->weight = (float) $row[7];

                $item->save();
            } else {

                Item::create([
                    'item_code' => $itemCode,
                    'item_name' => $row[1],
                    'item_per_box' => $row[2],
                    'item_group' => $row[3],

                    'length_cm' => (float) $row[4],
                    'width_cm' => (float) $row[5],
                    'height_cm' => (float) $row[6],
                    'weight' => (float) $row[7],
                ]);
            }
        }
        // foreach ($rows->skip(1) as $row) {
        //     Item::create([
        //         'item_code' => $row[0],
        //         'item_name' => $row[1],
        //         'item_per_box' => $row[2],
        //         'item_group' => $row[3], 
        //     ]);
        // }
    }
}
