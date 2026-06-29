<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadtemplateyuriModel extends Model
{
    use HasFactory;

    protected $table = 'upload_yuri_template';

    protected $fillable = [
        'agent_id',
        'month',
        'year',
        'file_name',
        'file_path',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
