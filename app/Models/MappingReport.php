<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MappingReport extends Model
{
    use HasFactory;

    protected $table = 'mappings_report';

    protected $fillable = [
        'agent_id',
        'month',
        'year',
        'file_path',
        'file_name',
        'notes',
        'user_id',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
