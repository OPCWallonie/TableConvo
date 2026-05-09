<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversationTable extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'level_id',
        'topic',
        'description',
        'scheduled_at',
        'duration_minutes',
        'max_participants',
        'location',
        'animator_id',
        'status',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'status' => SessionStatus::class,
        'scheduled_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function animator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'animator_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function confirmedRegistrations(): HasMany
    {
        return $this->registrations()->where('status', RegistrationStatus::Registered->value);
    }

    public function waitlist(): HasMany
    {
        return $this->registrations()->where('status', RegistrationStatus::Waitlist->value)->orderBy('waitlist_position');
    }

    public function isFull(): bool
    {
        return $this->confirmedRegistrations()->count() >= $this->max_participants;
    }

    public function availableSpots(): int
    {
        return max(0, $this->max_participants - $this->confirmedRegistrations()->count());
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', SessionStatus::Scheduled);
    }

    public function scopeUpcoming($query)
    {
        return $query->scheduled()->where('scheduled_at', '>', now());
    }
}
