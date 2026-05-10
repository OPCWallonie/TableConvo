<?php

namespace App\Models;

use App\Enums\CardStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Activity;

class Card extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'card_type_id',
        'order_id',
        'sessions_total',
        'sessions_remaining',
        'price_paid',
        'purchased_at',
        'expires_at',
        'status',
        'reminders_sent',
    ];

    protected $casts = [
        'status'          => CardStatus::class,
        'price_paid'      => 'decimal:2',
        'purchased_at'    => 'datetime',
        'expires_at'      => 'datetime',
        'reminders_sent'  => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cardType(): BelongsTo
    {
        return $this->belongsTo(CardType::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function isActive(): bool
    {
        return $this->status === CardStatus::Active && $this->expires_at->isFuture();
    }

    public function hasSessionsRemaining(): bool
    {
        return $this->sessions_remaining > 0;
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function scopeActive($query)
    {
        return $query->where('status', CardStatus::Active)->where('expires_at', '>', now());
    }
}
