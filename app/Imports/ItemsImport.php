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
            Item::create([
                'item_code' => $row[0],
                'item_name' => $row[1],
                'item_per_box' => $row[2],
                'item_group' => $row[3], 
            ]);
        }
    }
}
