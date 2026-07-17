<?php

namespace Database\Seeders;

use App\Models\EmployeeRecord;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['system-admin', 'secretariat', 'hrdc', 'supervisor', 'employee'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $accounts = [
            ['role' => 'system-admin', 'name' => 'System Admin', 'email' => 'admin@smartld.test'],
            ['role' => 'secretariat', 'name' => 'Secretariat User', 'email' => 'secretariat@smartld.test'],
            ['role' => 'hrdc', 'name' => 'HRDC User', 'email' => 'hrdc@smartld.test'],
            ['role' => 'supervisor', 'name' => 'Supervisor User', 'email' => 'supervisor@smartld.test'],
            ['role' => 'employee', 'name' => 'Employee User', 'email' => 'employee@smartld.test'],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'employee_id' => match ($account['role']) {
                        'system-admin' => 'ADM-001',
                        'secretariat' => 'SEC-001',
                        'hrdc' => 'HRD-001',
                        'supervisor' => 'SUP-001',
                        default => 'EMP-001',
                    },
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$account['role']]);
        }

        $employees = [
            ['employee_id' => 'ADM-001', 'first_name' => 'System', 'last_name' => 'Admin', 'email' => 'admin@smartld.test', 'office' => 'ICT Office', 'position' => 'System Administrator'],
            ['employee_id' => 'SEC-001', 'first_name' => 'Secretariat', 'last_name' => 'User', 'email' => 'secretariat@smartld.test', 'office' => 'HR Office', 'position' => 'L&D Secretariat'],
            ['employee_id' => 'HRD-001', 'first_name' => 'HRDC', 'last_name' => 'User', 'email' => 'hrdc@smartld.test', 'office' => 'HR Office', 'position' => 'HRDC Member'],
            ['employee_id' => 'SUP-001', 'first_name' => 'Supervisor', 'last_name' => 'User', 'email' => 'supervisor@smartld.test', 'office' => 'Operations', 'position' => 'Division Chief'],
            ['employee_id' => 'EMP-001', 'first_name' => 'Employee', 'last_name' => 'User', 'email' => 'employee@smartld.test', 'office' => 'Operations', 'position' => 'Administrative Aide'],
        ];

        foreach ($employees as $employee) {
            EmployeeRecord::query()->updateOrCreate(
                ['employee_id' => $employee['employee_id']],
                [
                    'first_name' => $employee['first_name'],
                    'last_name' => $employee['last_name'],
                    'email' => $employee['email'],
                    'office' => $employee['office'],
                    'position' => $employee['position'],
                    'employment_status' => 'Active',
                    'source' => 'Seeder',
                    'last_imported_at' => now(),
                ],
            );
        }
    }
}
