<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customeritemminstocks extends Model
{
    use HasFactory;

    protected $table = 'customer_item_min_stocks';

    protected $fillable = [
        'user_id',
        'item_id',
        'minimum_stock'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
