<?php

namespace App\Models;

use App\Enums\CompanyJoinRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class CompanyJoinRequest extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'company_id',
        'user_id',
        'status',
        'message',
        'requested_at',
        'resolved_at',
        'resolved_by_user_id',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status'       => CompanyJoinRequestStatus::class,
            'requested_at' => 'datetime',
            'resolved_at'  => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    protected static function booted(): void
    {
        // Garantie applicative : un seul pending par (user, company)
        static::creating(function (self $joinRequest) {
            if ($joinRequest->status === CompanyJoinRequestStatus::Pending || $joinRequest->status === null) {
                $exists = static::where('user_id', $joinRequest->user_id)
                    ->where('company_id', $joinRequest->company_id)
                    ->where('status', CompanyJoinRequestStatus::Pending->value)
                    ->exists();

                if ($exists) {
                    throw new \RuntimeException('Une demande est déjà en attente pour cet utilisateur et cette société.');
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', CompanyJoinRequestStatus::Pending->value);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereIn('status', [
            CompanyJoinRequestStatus::Approved->value,
            CompanyJoinRequestStatus::Rejected->value,
            CompanyJoinRequestStatus::Cancelled->value,
        ]);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
