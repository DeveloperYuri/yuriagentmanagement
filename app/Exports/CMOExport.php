<?php

namespace App\Exports;

use App\Models\AgentExportStock;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CMOExport implements FromCollection, WithHeadings
{
    protected $agentId;

    public function __construct($agentId)
    {
        $this->agentId = $agentId;
    }

    public function collection()
    {
        return AgentExportStock::where(
            'agent_id',
            $this->agentId
        )
            ->get()
            ->map(function ($item) {

                return [
                    'kode_sku_jim'   => $item->kode_sku_jim,
                    'item_name_jim'  => $item->item_name_jim,
                    'item_group'    => $item->item?->item_group,
                    'agent_id'        => $item->agent?->name,
                    'berat'        => '-',
                    'volume'        => '-',
                    'item_per_box'  => $item->item?->item_per_box,
                    'total_stock_karton'        => '-',
                    'buffer'        => 45,
                    'avg_qty_outsell_karton'        => '-',
                    'total_sales_qty_outsell_karton'        => '-',
                    'nka1'        => '-',
                    'nka2'        => '-',
                    'nka3'        => '-',
                    'min_stock'        => '-',
                    'selisih_karton_order_agen'        => '-',
                    'order_tambahan'        => '-',
                    'total_cmo'        => '-',
                    'total_berat'        => '-',
                    'total_volume'        => '-',
                    'periode'        => '-',
                    'periode'        => '-',
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Item Code',
            'Item Name',
            'Item Group Name',
            'Customer Name',
            'BERAT (kg)',
            'VOLUME (m3)',
            'Item / Box',
            'Total Stock Karton',
            'Buffer (Hari)',
            'Avg Qty Outsell Karton',
            'Total Sales Qty Insell Carton',
            'Total Sales Qty Outsell Carton',
            'NKA 1',
            'NKA 2',
            'NKA 3',
            'MIN STOK',
            'MIN STOCK (NKA)',
            'Selisih Karton Order Agen (Bulan Berikutnya)',
            'ORDER TAMBAHAN / PRODUCT PROMOSI',
            'TOTAL CMO',
            'TOTAL BERAT (kg)',
            'TOTAL VOLUME (m3)',
        ];
    }
}
