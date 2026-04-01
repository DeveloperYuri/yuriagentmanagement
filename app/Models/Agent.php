<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;

    /**
     * Nama tabel di database (opsional kalau namanya sudah jamak/plural 'agents')
     */
    protected $table = 'agents';

    /**
     * Mass Assignment Protection: 
     * Daftar kolom yang BOLEH diisi secara manual melalui form.
     */
    protected $fillable = [
        'code',
        'name',
        'phone',
        'address',
        'is_active',
    ];

    /**
     * Casting: Mengubah tipe data otomatis saat diambil dari database.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
