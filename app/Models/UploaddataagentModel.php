<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploaddataagentModel extends Model
{
    use HasFactory;

    protected $table = 'upload_agent_data';

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
