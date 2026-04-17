<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mapping extends Model
{
    use HasFactory;

    protected $table = 'mappings';

    protected $fillable = [
        'agent_id',
        'nama_agent',
        'sheet',
        'mapping_json',
        'agent_report_id'
    ];

    protected $casts = [
        'mapping_json' => 'array'
    ];
}
