<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentItemMapping extends Model
{
    protected $fillable = [
        'agent_sku',
        'item_code',
        'item_name',
        'item_per_box',
        'item_group',
    ];
}