<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRecord;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ActivationController extends Controller
{
    public function sendId(Request $request): Response
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'string', 'max:255'],
        ]);

        $user = User::query()
            ->where('employee_id', $validated['employee_id'])
            ->first();

        if (! $user || ! $user->activation_token) {
            return back()->withErrors([
                'employee_id' => 'No pending activation record matched the provided employee ID.',
            ]);
        }

        $target = url('/activate-account/complete').'?token='.urlencode($user->activation_token).'&email='.urlencode($user->email);

        if ($request->header('X-Inertia')) {
            $request->session()->flash('status', 'Identity verified. You may now complete account activation.');

            return Inertia::location($target);
        }

        return redirect($target)->with('status', 'Identity verified. You may now complete account activation.');
    }

    public function complete(Request $request): Response
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'office' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
            'photo' => ['nullable', 'file', 'image', 'max:2048'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('activation_token', $validated['token'])
            ->first();

        if (! $user) {
            return redirect('/activate-account')
                ->withErrors([
                    'employee_id' => 'This activation session is invalid or has already been used. Please verify your employee ID again.',
                ]);
        }

        $employeeRecord = EmployeeRecord::query()
            ->where('employee_id', $user->employee_id)
            ->first();

        $user->forceFill([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'office' => $validated['office'],
            'password' => Hash::make($validated['password']),
            'activation_token' => null,
            'email_verified_at' => now(),
        ])->save();

        if ($user->getRoleNames()->isEmpty() && filled($user->employee_id)) {
            $user->assignRole('employee');
        }

        $employeeRecord?->forceFill([
            'office' => $validated['office'],
        ])->save();

        event(new Verified($user));

        $target = url('/activate-account/sign-in');

        if ($request->header('X-Inertia')) {
            $request->session()->flash('status', 'Account activated successfully. You may now sign in.');

            return Inertia::location($target);
        }

        return redirect($target)->with('status', 'Account activated successfully. You may now sign in.');
    }
}
