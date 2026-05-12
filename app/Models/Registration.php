<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Registration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'conversation_table_id',
        'card_id',
        'status',
        'waitlist_position',
        'registered_at',
        'cancelled_at',
        'cancelled_by',
        'reminded_at',
    ];

    protected $casts = [
        'status'        => RegistrationStatus::class,
        'registered_at' => 'datetime',
        'cancelled_at'  => 'datetime',
        'reminded_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversationTable(): BelongsTo
    {
        return $this->belongsTo(ConversationTable::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', RegistrationStatus::Registered);
    }

    public function scopeOnWaitlist($query)
    {
        return $query->where('status', RegistrationStatus::Waitlist);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereHas('conversationTable', fn ($q) => $q->where('scheduled_at', '>', now()))
            ->whereIn('status', [RegistrationStatus::Registered->value, RegistrationStatus::Waitlist->value]);
    }

    public function getPositionAttribute(): int
    {
        return (int) $this->waitlist_position;
    }
}
