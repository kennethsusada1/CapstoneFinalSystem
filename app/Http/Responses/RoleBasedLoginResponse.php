<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse;

class RoleBasedLoginResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $role = $user?->getRoleNames()->first();

        $request->session()->put('just_logged_in', true);

        $target = match ($role) {
            'system-admin' => '/admin',
            'secretariat' => '/secretariat',
            'hrdc' => '/hrdc',
            'supervisor' => '/supervisor',
            default => '/employee',
        };

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false, 'redirect' => $target])
            : redirect($target);
    }
}
