<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'regional_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function regional(): BelongsTo
    {
        return $this->belongsTo(Regional::class);
    }

    // Di dalam class User extends Authenticatable
    public function supervisors()
    {
        // Agent memiliki banyak supervisor
        return $this->belongsToMany(User::class, 'user_supervisor', 'user_id', 'supervisor_id');
    }

    public function agents()
    {
        // Supervisor memiliki banyak agent
        return $this->belongsToMany(User::class, 'user_supervisor', 'supervisor_id', 'user_id');
    }

    // public function monitoredAgents()
    // {
    //     return $this->belongsToMany(Agent::class, 'agent_user');
    // }

    // public function supervisors()
    // {
    //     // Menghubungkan Admin Agent (User) ke Supervisor (User) melalui tabel pivot
    //     return $this->belongsToMany(User::class, 'agent_user', 'agent_id', 'user_id');
    // }
}
