<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\LeaveType;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@leavehub.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]
        );

        // Auto-assign leave balances for admin user
        $currentYear = now()->year;
        $leaveTypes = LeaveType::all();

        foreach ($leaveTypes as $leaveType) {
            $admin->leaveBalances()->firstOrCreate(
                [
                    'leave_type_id' => $leaveType->id,
                    'year' => $currentYear,
                ],
                [
                    'total_quota' => $leaveType->default_quota,
                    'used' => 0,
                ]
            );
        }
    }
}
