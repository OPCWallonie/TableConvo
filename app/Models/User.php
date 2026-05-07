<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'company_id',
        'level_id',
        'level_assigned_at',
        'interview_requested_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'level_assigned_at'        => 'datetime',
            'interview_requested_at'   => 'datetime',
            'password'                 => 'hashed',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getFilamentName(): string
    {
        return $this->full_name;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function hasLevel(): bool
    {
        return $this->level_id !== null;
    }

    public function activeCards(): HasMany
    {
        return $this->cards()->where('status', 'active')->where('expires_at', '>', now());
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('admin');
    }
}
