<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminActivationCredentialsMail;
use App\Models\EmployeeRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    protected function manageableRoles(): array
    {
        return ['secretariat', 'hrdc', 'supervisor', 'employee'];
    }

    protected function activationUrl(User $user): string
    {
        return url('/activate-account?employee_id='.urlencode((string) $user->employee_id));
    }

    protected function createUserAccountFromEmployee(EmployeeRecord $employee, string $role, bool $sendActivation = true): User
    {
        $temporaryPassword = Str::password(10, true, true, false, false);
        $activationToken = Str::random(48);

        $user = User::query()->updateOrCreate(
            ['employee_id' => $employee->employee_id],
            [
                'name' => trim($employee->first_name.' '.$employee->last_name),
                'email' => $employee->email,
                'office' => $employee->office,
                'password' => Hash::make($temporaryPassword),
                'activation_sent_at' => null,
                'activation_token' => $activationToken,
                'email_verified_at' => null,
            ],
        );

        $user->syncRoles([$role]);

        $activationUrl = $this->activationUrl($user);

        if ($sendActivation) {
            $this->sendActivationMail($user, $activationUrl, $activationToken);
        }

        return $user;
    }

    protected function sendActivationMail(User $user, string $activationUrl, string $activationToken): void
    {
        try {
            Mail::mailer('smtp')
                ->to($user->email)
                ->send(new AdminActivationCredentialsMail($user, $activationUrl, $activationToken));

            $user->forceFill([
                'activation_sent_at' => now(),
            ])->save();

            Log::info('Activation email sent successfully.', [
                'employee_id' => $user->employee_id,
                'email' => $user->email,
                'mailer' => 'smtp',
            ]);
        } catch (\Throwable $exception) {
            Log::error('Activation email sending failed.', [
                'employee_id' => $user->employee_id,
                'email' => $user->email,
                'mailer' => 'smtp',
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'The activation email could not be sent right now. Please verify the employee email address and try again.',
            ]);
        }
    }

    public function index(): Response
    {
        $users = User::query()
            ->with('roles', 'employeeRecord')
            ->latest()
            ->get()
            ->map(function (User $user) {
                $roles = $user->getRoleNames()->values()->all();
                $primaryRole = $roles[0] ?? null;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'employee_id' => $user->employee_id,
                    'roles' => $roles,
                    'email_verified_at' => $user->email_verified_at?->toDateTimeString(),
                    'activation_sent_at' => $user->activation_sent_at?->toDateTimeString(),
                    'created_at' => $user->created_at?->toDateTimeString(),
                    'employee_source' => $user->employeeRecord?->source,
                    'source' => 'user',
                    'is_manageable' => in_array($primaryRole, $this->manageableRoles(), true),
                ];
            });

        $employeeOnly = EmployeeRecord::query()
            ->whereDoesntHave('user')
            ->orderBy('last_name')
            ->get()
            ->map(fn (EmployeeRecord $employee) => [
                'id' => 'employee-'.$employee->id,
                'name' => trim($employee->first_name.' '.$employee->last_name),
                'email' => $employee->email,
                'employee_id' => $employee->employee_id,
                'office' => $employee->office,
                'roles' => [],
                'email_verified_at' => null,
                'activation_sent_at' => null,
                'created_at' => $employee->created_at?->toDateTimeString(),
                'source' => 'employee',
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users->concat($employeeOnly)->values(),
            'employees' => $employeeOnly,
            'mailer' => config('mail.default'),
            'assignableRoles' => $this->manageableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'string', 'exists:employee_records,employee_id'],
            'role' => ['required', 'string', 'in:'.implode(',', $this->manageableRoles())],
        ]);

        $employee = EmployeeRecord::query()->where('employee_id', $validated['employee_id'])->firstOrFail();
        $user = $this->createUserAccountFromEmployee($employee, $validated['role']);

        return back()->with('success', "Activation email sent to {$user->email}. Employee ID: {$user->employee_id}.");
    }

    public function storeManualEmployee(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'in:'.implode(',', $this->manageableRoles())],
            'email' => ['required', 'email', 'max:255', 'unique:employee_records,email'],
            'office' => ['required', 'string', 'max:255'],
        ]);

        $parts = preg_split('/\s+/', trim($validated['name'])) ?: [];
        $firstName = array_shift($parts) ?? $validated['name'];
        $lastName = count($parts) > 0 ? array_pop($parts) : 'Employee';
        $middleName = count($parts) > 0 ? implode(' ', $parts) : null;

        $rolePrefix = match ($validated['role']) {
            'secretariat' => 'SEC',
            'hrdc' => 'HRD',
            'supervisor' => 'SUP',
            default => 'EMP',
        };

        $employeeId = $rolePrefix.'-'.str_pad((string) (EmployeeRecord::query()->count() + 1), 3, '0', STR_PAD_LEFT);

        while (EmployeeRecord::query()->where('employee_id', $employeeId)->exists()) {
            $employeeId = $rolePrefix.'-'.str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT);
        }

        $employee = EmployeeRecord::query()->create([
            'employee_id' => $employeeId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'middle_name' => $middleName,
            'email' => $validated['email'],
            'office' => $validated['office'],
            'position' => str_replace('-', ' ', ucfirst($validated['role'])),
            'employment_status' => 'Active',
            'source' => 'Manual Admin Entry',
            'last_imported_at' => now(),
        ]);

        $user = $this->createUserAccountFromEmployee($employee, $validated['role'], false);

        return back()->with('success', "Employee and user account added successfully. Use Send to send the activation details to {$user->email}. Employee ID: {$user->employee_id}.");
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        if ($user->hasRole('system-admin')) {
            throw ValidationException::withMessages([
                'role' => 'System administrator accounts are not managed from this role assignment flow.',
            ]);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', $this->manageableRoles())],
        ]);

        $user->syncRoles([$validated['role']]);

        return back()->with('success', "{$user->name} is now assigned as {$validated['role']}.");
    }

    public function resendActivation(Request $request, User $user): RedirectResponse
    {
        $hadActivationHistory = filled($user->activation_sent_at);
        $temporaryPassword = Str::password(10, true, true, false, false);
        $activationToken = Str::random(48);

        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
            'office' => $user->office,
            'activation_sent_at' => null,
            'activation_token' => $activationToken,
            'email_verified_at' => null,
        ])->save();

        $activationUrl = $this->activationUrl($user);

        $this->sendActivationMail($user, $activationUrl, $activationToken);

        $message = $hadActivationHistory
            ? "Activation email resent to {$user->email}. Employee ID: {$user->employee_id}."
            : "Activation email sent to {$user->email}. Employee ID: {$user->employee_id}.";

        return back()->with('success', $message);
    }

    public function destroy(string $account): RedirectResponse
    {
        if (str_starts_with($account, 'employee-')) {
            $employeeId = (int) str_replace('employee-', '', $account);
            $employee = EmployeeRecord::query()->findOrFail($employeeId);

            if ($employee->user()->exists()) {
                throw ValidationException::withMessages([
                    'user' => 'This employee already has an account. Delete the user account record instead.',
                ]);
            }

            $employeeName = trim($employee->first_name.' '.$employee->last_name);
            $employee->delete();

            return back()->with('success', "{$employeeName} was deleted from user management.");
        }

        $user = User::query()->findOrFail($account);

        if ($user->hasRole('system-admin')) {
            throw ValidationException::withMessages([
                'user' => 'System administrator accounts cannot be deleted from this page.',
            ]);
        }

        $employeeRecord = EmployeeRecord::query()
            ->where('employee_id', $user->employee_id)
            ->where('source', 'Manual Admin Entry')
            ->first();

        $userName = $user->name;

        $user->syncRoles([]);
        $user->delete();

        if ($employeeRecord && ! User::query()->where('employee_id', $employeeRecord->employee_id)->exists()) {
            $employeeRecord->delete();
        }

        return back()->with('success', "{$userName} was deleted from user management.");
    }
}
