<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminEmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $employees = EmployeeRecord::query()
            ->with('user:id,employee_id')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('employee_id', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('office', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get()
            ->map(fn (EmployeeRecord $employee) => [
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'middle_name' => $employee->middle_name,
                'name' => trim(implode(' ', array_filter([$employee->first_name, $employee->middle_name, $employee->last_name]))),
                'email' => $employee->email,
                'office' => $employee->office,
                'position' => $employee->position,
                'employment_status' => $employee->employment_status,
                'source' => $employee->source,
                'last_imported_at' => $employee->last_imported_at?->toDateTimeString(),
                'has_account' => $employee->user !== null,
            ]);

        return Inertia::render('Admin/Employees/Index', [
            'employees' => $employees,
            'filters' => ['search' => $search],
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'csv' => ['required', 'string'],
        ]);

        $rows = preg_split("/\r\n|\n|\r/", trim($validated['csv'])) ?: [];
        $header = null;
        $imported = 0;

        foreach ($rows as $row) {
            if (trim($row) === '') {
                continue;
            }

            $columns = str_getcsv($row);

            if ($header === null) {
                $header = array_map(fn ($value) => strtolower(trim((string) $value)), $columns);
                continue;
            }

            $data = array_combine($header, $columns);

            if (! is_array($data) || empty($data['employee_id'])) {
                continue;
            }

            EmployeeRecord::query()->updateOrCreate(
                ['employee_id' => trim((string) $data['employee_id'])],
                [
                    'first_name' => trim((string) ($data['first_name'] ?? '')),
                    'last_name' => trim((string) ($data['last_name'] ?? '')),
                    'middle_name' => trim((string) ($data['middle_name'] ?? '')),
                    'email' => trim((string) ($data['email'] ?? '')),
                    'office' => trim((string) ($data['office'] ?? '')),
                    'position' => trim((string) ($data['position'] ?? '')),
                    'employment_status' => trim((string) ($data['employment_status'] ?? 'Active')),
                    'source' => 'HRMS Import',
                    'last_imported_at' => now(),
                ],
            );

            $imported++;
        }

        return back()->with('success', "{$imported} employee record(s) imported.");
    }

    public function update(Request $request, EmployeeRecord $employeeRecord): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:employee_records,email,'.$employeeRecord->id],
            'office' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'employment_status' => ['required', 'string', 'max:255'],
        ]);

        $employeeRecord->update($validated);

        return back()->with('success', "Employee record {$employeeRecord->employee_id} updated.");
    }
}
