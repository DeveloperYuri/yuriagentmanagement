<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $table = 'items';

    protected $fillable = [
        'item_code',
        'item_name',
        'item_per_box',
        'item_group',
        'uom',
        'weight',
        'description'
    ];
}
