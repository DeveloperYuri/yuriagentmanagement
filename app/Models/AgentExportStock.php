<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentExportStock extends Model
{
    use HasFactory;

    protected $table = 'agent_export_stocks';

    protected $fillable = [
        'kode_sku_agent',
        'kode_sku_jim',
        'item_name_jim',
        'stock_karton',
        'bulan',
        'tahun',
        'periode'
    ];
}
