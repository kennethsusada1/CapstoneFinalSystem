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

        $demoMode = filter_var(env('LNA_DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN);

        if (! app()->environment(['local', 'testing']) && ! $demoMode) {
            return;
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
            ['employee_id' => 'ADM-001', 'first_name' => 'System', 'last_name' => 'Admin', 'email' => 'admin@smartld.test', 'office' => 'ICT Office', 'position' => 'System Administrator', 'role_family' => 'ict', 'seniority_level' => 2, 'hire_date' => '2018-06-01'],
            ['employee_id' => 'SEC-001', 'first_name' => 'Secretariat', 'last_name' => 'User', 'email' => 'secretariat@smartld.test', 'office' => 'HR Office', 'position' => 'L&D Secretariat', 'role_family' => 'hr', 'seniority_level' => 2, 'hire_date' => '2019-03-15'],
            ['employee_id' => 'HRD-001', 'first_name' => 'HRDC', 'last_name' => 'User', 'email' => 'hrdc@smartld.test', 'office' => 'HR Office', 'position' => 'HRDC Member', 'role_family' => 'hr', 'seniority_level' => 2, 'hire_date' => '2017-09-01'],
            ['employee_id' => 'SUP-001', 'first_name' => 'Supervisor', 'last_name' => 'User', 'email' => 'supervisor@smartld.test', 'office' => 'Operations', 'position' => 'Division Chief', 'role_family' => 'administration', 'seniority_level' => 3, 'hire_date' => '2012-01-01'],
            ['employee_id' => 'EMP-001', 'first_name' => 'Employee', 'last_name' => 'User', 'email' => 'employee@smartld.test', 'office' => 'Operations', 'position' => 'Administrative Aide', 'role_family' => 'administration', 'seniority_level' => 1, 'hire_date' => '2021-05-10', 'supervisor_employee_id' => 'SUP-001'],
        ];

        foreach ($employees as $employee) {
            EmployeeRecord::query()->updateOrCreate(
                ['employee_id' => $employee['employee_id']],
                [
                    'first_name' => $employee['first_name'],
                    'last_name' => $employee['last_name'],
                    'email' => $employee['email'],
                    'office' => $employee['office'],
                    'supervisor_employee_id' => $employee['supervisor_employee_id'] ?? null,
                    'position' => $employee['position'],
                    'education_level' => "Bachelor's Degree",
                    'role_family' => $employee['role_family'],
                    'seniority_level' => $employee['seniority_level'],
                    'hire_date' => $employee['hire_date'],
                    'employment_status' => 'Permanent',
                    'source' => 'Seeder',
                    'last_imported_at' => now(),
                ],
            );
        }
    }
}
