<?php

namespace App\Models;

use App\Enums\GlobalWaitlistEntryStatus;
use App\Enums\GlobalWaitlistSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class GlobalWaitlistEntry extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'user_id',
        'level_id',
        'requested_at',
        'source',
        'source_registration_id',
        'admin_reason',
        'created_by',
        'status',
        'reassigned_to_registration_id',
        'dismissed_reason',
        'dismissed_at',
        'dismissed_by',
    ];

    protected $casts = [
        'status'       => GlobalWaitlistEntryStatus::class,
        'source'       => GlobalWaitlistSource::class,
        'requested_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'reassigned_to_registration_id',
                'dismissed_reason',
                'dismissed_at',
                'dismissed_by',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // ─── Relations ───────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function sourceRegistration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'source_registration_id');
    }

    public function reassignedToRegistration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'reassigned_to_registration_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dismissedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', GlobalWaitlistEntryStatus::Pending);
    }

    public function scopeForLevel(Builder $query, Level $level): Builder
    {
        return $query->where('level_id', $level->id);
    }

    public function scopeOldestFirst(Builder $query): Builder
    {
        return $query->orderBy('requested_at', 'asc');
    }

    // ─── Accesseurs ──────────────────────────────────────────────

    public function getWaitingDaysAttribute(): int
    {
        return (int) $this->requested_at->diffInDays(now());
    }
}
