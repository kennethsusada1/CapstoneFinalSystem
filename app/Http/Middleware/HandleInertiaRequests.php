<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        $manifestPath = public_path('build/manifest.json');
        $trainingPagePath = resource_path('js/pages/Employee/TrainingApplications/Index.jsx');
        $sources = array_filter([
            file_exists($manifestPath) ? (string) filemtime($manifestPath) : null,
            file_exists($trainingPagePath) ? (string) filemtime($trainingPagePath) : null,
        ]);

        if ($sources !== []) {
            return sha1(implode('|', $sources));
        }

        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatar' => $request->user()->avatar ?? null,
                    'profile_photo_url' => $request->user()->profile_photo_url ?? null,
                    'roles' => $request->user()->getRoleNames()->values()->all(),
                ] : null,
            ],
            'flash' => [
                'just_logged_in' => $request->session()->pull('just_logged_in', false),
                'success' => $request->session()->get('success'),
            ],
        ]);
    }
}
