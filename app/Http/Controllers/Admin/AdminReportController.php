<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Reports/Index', [
            'summary' => [
                'total_employee_records' => EmployeeRecord::query()->count(),
                'total_user_accounts' => User::query()->count(),
                'activated_accounts' => User::query()->whereNotNull('email_verified_at')->count(),
                'pending_activations' => User::query()->whereNull('email_verified_at')->count(),
                'records_with_accounts' => EmployeeRecord::query()->whereIn('employee_id', User::query()->select('employee_id')->whereNotNull('employee_id'))->count(),
            ],
            'roleBreakdown' => collect(['system-admin', 'secretariat', 'hrdc', 'supervisor', 'employee'])->map(fn ($role) => [
                'role' => $role,
                'count' => User::role($role)->count(),
            ])->values(),
            'officeBreakdown' => EmployeeRecord::query()
                ->selectRaw('office, count(*) as total')
                ->groupBy('office')
                ->orderBy('office')
                ->get()
                ->map(fn ($row) => ['office' => $row->office ?: 'Unassigned', 'count' => $row->total]),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = User::query()
            ->leftJoin('employee_records', 'users.employee_id', '=', 'employee_records.employee_id')
            ->select([
                'users.name',
                'users.email',
                'users.employee_id',
                'employee_records.office',
                'employee_records.position',
                'employee_records.employment_status',
                'users.email_verified_at',
                'users.activation_sent_at',
            ])
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Employee ID', 'Office', 'Position', 'Employment Status', 'Verified At', 'Activation Sent At']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->name,
                    $row->email,
                    $row->employee_id,
                    $row->office,
                    $row->position,
                    $row->employment_status,
                    $row->email_verified_at,
                    $row->activation_sent_at,
                ]);
            }

            fclose($handle);
        }, 'ld-report.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
