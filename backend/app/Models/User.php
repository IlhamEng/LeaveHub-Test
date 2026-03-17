<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Get the leave balances for the user.
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * Get the leave requests submitted by the user.
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get the leave requests responded by this user (admin).
     */
    public function respondedRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'responded_by');
    }

    /**
     * Get the leave requests deleted by this user.
     */
    public function deletedRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'deleted_by');
    }
}
