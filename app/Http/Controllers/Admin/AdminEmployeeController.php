<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeePerformanceRecord;
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
                'supervisor_employee_id' => $employee->supervisor_employee_id,
                'position' => $employee->position,
                'education_level' => $employee->education_level,
                'role_family' => $employee->role_family,
                'seniority_level' => $employee->seniority_level,
                'hire_date' => $employee->hire_date?->toDateString(),
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

            if (count($columns) !== count($header)) {
                continue;
            }

            $data = array_combine($header, $columns);

            if (empty($data['employee_id'])) {
                continue;
            }

            $employeeId = trim((string) $data['employee_id']);
            $employeeFields = [
                'first_name' => trim((string) ($data['first_name'] ?? '')),
                'last_name' => trim((string) ($data['last_name'] ?? '')),
                'middle_name' => trim((string) ($data['middle_name'] ?? '')),
                'email' => trim((string) ($data['email'] ?? '')),
                'office' => trim((string) ($data['office'] ?? '')),
                'position' => trim((string) ($data['position'] ?? '')),
                'employment_status' => trim((string) ($data['employment_status'] ?? 'Active')),
                'source' => 'HRMS Import',
                'last_imported_at' => now(),
            ];

            foreach ([
                'supervisor_employee_id' => fn (mixed $value) => $this->nullableValue($value),
                'education_level' => fn (mixed $value) => $this->nullableValue($value),
                'role_family' => fn (mixed $value) => $this->nullableValue($value),
                'seniority_level' => fn (mixed $value) => $this->integerValue($value, 1, 5),
                'hire_date' => fn (mixed $value) => $this->dateValue($value),
            ] as $field => $normalize) {
                if (array_key_exists($field, $data)) {
                    $employeeFields[$field] = $normalize($data[$field]);
                }
            }

            EmployeeRecord::query()->updateOrCreate(['employee_id' => $employeeId], $employeeFields);

            $reviewYear = $this->integerValue($data['review_year'] ?? $data['performance_year'] ?? null, 2000, (int) now()->year + 1);
            $performanceRating = $data['ipcr_rating'] ?? $data['performance_rating'] ?? null;

            if ($reviewYear !== null) {
                $this->storePerformanceRating($employeeId, $reviewYear, $performanceRating);
            }

            foreach ($data as $column => $rating) {
                if (preg_match('/^ipcr_(20\d{2})$/', (string) $column, $match) === 1) {
                    $historyYear = $this->integerValue($match[1], 2000, (int) now()->year + 1);
                    if ($historyYear !== null) {
                        $this->storePerformanceRating($employeeId, $historyYear, $rating);
                    }
                }
            }

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
            'supervisor_employee_id' => ['nullable', 'string', 'max:255', 'exists:employee_records,employee_id', 'not_in:'.$employeeRecord->employee_id],
            'position' => ['nullable', 'string', 'max:255'],
            'education_level' => ['nullable', 'string', 'max:255'],
            'role_family' => ['nullable', 'string', 'max:255'],
            'seniority_level' => ['nullable', 'integer', 'between:1,5'],
            'hire_date' => ['nullable', 'date'],
            'employment_status' => ['required', 'string', 'max:255'],
        ]);

        $employeeRecord->update($validated);

        return back()->with('success', "Employee record {$employeeRecord->employee_id} updated.");
    }

    private function nullableValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function integerValue(mixed $value, int $minimum, int $maximum): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $integer = (int) $value;

        return $integer >= $minimum && $integer <= $maximum ? $integer : null;
    }

    private function dateValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function storePerformanceRating(string $employeeId, int $reviewYear, mixed $rating): void
    {
        if (! is_numeric($rating) || (float) $rating < 1 || (float) $rating > 5) {
            return;
        }

        EmployeePerformanceRecord::query()->updateOrCreate(
            ['employee_id' => $employeeId, 'review_year' => $reviewYear],
            ['ipcr_rating' => (float) $rating, 'source' => 'HRMS Import'],
        );
    }
}
