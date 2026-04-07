<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentReport extends Model
{
    use HasFactory;

    protected $table = 'agent_reports';

    protected $fillable = [
        'agent_id',
        'month',
        'year',
        'file_path',
        'file_name',
        'notes',
        'user_id'
    ];

    // Relasi balik ke Agent
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
