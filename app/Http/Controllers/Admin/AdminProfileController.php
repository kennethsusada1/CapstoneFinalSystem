<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = User::query()
            ->with('employeeRecord')
            ->whereKey($request->user()->getAuthIdentifier())
            ->firstOrFail();

        return Inertia::render('Admin/Profile/Index', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'address' => $user->address,
                'employee_id' => $user->employee_id,
                'office' => $user->office ?: $user->employeeRecord?->office,
                'position' => $user->employeeRecord?->position,
                'verified_on' => $user->email_verified_at?->toDateString(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update($validated);

        return back()->with('success', 'System administrator profile updated successfully.');
    }
}
