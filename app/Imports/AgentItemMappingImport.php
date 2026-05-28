<?php

namespace App\Imports;

use App\Models\AgentItemMapping;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class AgentItemMappingImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {

            // skip header
            if ($index == 0) {
                continue;
            }

            AgentItemMapping::updateOrCreate(
                [
                    'agent_sku' => $row[5], // kolom F
                ],
                [
                    'item_code' => $row[1] ?? '', // kolom B
                    'item_name' => $row[2] ?? '', // kolom C
                    'item_per_box' => $row[3] ?? 0, // kolom D
                    'item_group' => $row[4] ?? '', // kolom E
                ]
            );
        }
    }
}