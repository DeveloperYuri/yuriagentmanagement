<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MappingProduk extends Model
{
    use HasFactory;

    protected $table = "item_aliases";

    protected $fillable = [
        "agent_name",
        "clean_name",
        "master_name",
        "item_code"
    ];
}
