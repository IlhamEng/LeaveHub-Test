<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'default_quota',
    ];

    /**
     * Get the leave balances for this leave type.
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * Get the leave requests for this leave type.
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
