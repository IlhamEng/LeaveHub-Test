<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class LeaveRequest extends Model
{
    use SoftDeletes;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELED = 'canceled';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'status',
        'responded_by',
        'admin_notes',
        'responded_at',
        'deleted_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * Check if the request has a final status.
     */
    public function isFinal(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELED,
        ]);
    }

    /**
     * Check if the request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Scope: only pending requests.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: only active (non-deleted) requests.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Get the user that submitted this request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the leave type for this request.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Get the admin who responded to this request.
     */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * Get the user who deleted this request.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
