<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LeaveType;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaveTypes = [
            ['name' => 'Annual Leave', 'default_quota' => 12],
            ['name' => 'Sick Leave', 'default_quota' => 6],
        ];

        foreach ($leaveTypes as $type) {
            LeaveType::firstOrCreate(
                ['name' => $type['name']],
                ['default_quota' => $type['default_quota']]
            );
        }
    }
}
