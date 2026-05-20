<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentExportReport extends Model
{
    protected $table = 'agent_export_report';

    protected $fillable = [
        'nama_agen',
        'kode_customer',
        'nama_customer',
        'alamat_customer',
        'nomor_telepon_customer',

        'invoice_nomor_agen',
        'tanggal_invoice',

        'tipe_customer',
        'sales',

        'sku_kode_agen',
        'nama_sku',

        'qty_terjual_pcs',

        'diskon_1_reguler',
        'diskon_2_cash',
        'diskon_3_dc_free',
        'diskon_4_promo_1',
        'diskon_5_promo_2',
        'diskon_6_rp',

        'quantity_bonus',
        'rafraksi',

        'total_invoice_value',
        'kode_sku_jim',
        'item_name_jim',
        'stock_karton',
        'match_item',
    ];

    protected $casts = [
        'tanggal_invoice' => 'date',

        'qty_terjual_pcs' => 'decimal:2',

        'diskon_1_reguler' => 'decimal:2',
        'diskon_2_cash' => 'decimal:2',
        'diskon_3_dc_free' => 'decimal:2',
        'diskon_4_promo_1' => 'decimal:2',
        'diskon_5_promo_2' => 'decimal:2',
        'diskon_6_rp' => 'decimal:2',

        'quantity_bonus' => 'decimal:2',
        'rafraksi' => 'decimal:2',

        'total_invoice_value' => 'decimal:2',
    ];
}
