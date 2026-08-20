<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEmployeeController;
use App\Http\Controllers\Admin\AdminLearningDevelopmentController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Employee\EmployeeDashboardController;
use App\Http\Controllers\Employee\EmployeeLearningController;
use App\Http\Controllers\Employee\EmployeeProfileController;
use App\Http\Controllers\HRDC\HrdcPortalController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\Secretariat\SecretariatPortalController;
use App\Http\Controllers\Supervisor\SupervisorLnaController;
use App\Http\Controllers\Supervisor\SupervisorPortalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::redirect('/', '/login');

// Public intake landing page — employees redirected here from smart-pms when training-locked.
// No auth middleware: employee may not have an L&D account yet.
Route::get('/intake', [IntakeController::class, 'show'])->name('intake');
Route::get('/activate-account', function (Request $request) {
    if ($request->user()) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    return Inertia::render('Auth/Login', [
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
        'status' => $request->session()->get('status'),
        'mode' => 'activate-verify',
    ]);
});
Route::get('/activate-account/complete', function (Request $request) {
    if ($request->user()) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    return Inertia::render('Auth/Login', [
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
        'status' => $request->session()->get('status'),
        'mode' => 'activate-complete',
        'token' => $request->query('token', ''),
        'email' => $request->query('email', ''),
    ]);
});
Route::get('/activate-account/sign-in', function (Request $request) {
    if ($request->user()) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    return Inertia::render('Auth/Login', [
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
        'status' => $request->session()->get('status'),
        'mode' => 'login',
    ]);
});
Route::get('/logout', function (Request $request) {
    $user = $request->user();

    if (! $user) {
        return redirect('/login');
    }

    $role = $user->getRoleNames()->first();

    $target = match ($role) {
        'system-admin' => '/admin',
        'secretariat' => '/secretariat',
        'hrdc' => '/hrdc',
        'supervisor' => '/supervisor',
        default => '/employee',
    };

    return redirect($target);
});

Route::post('/send/id', [ActivationController::class, 'sendId'])->name('activation.send');
Route::post('/activate/complete', [ActivationController::class, 'complete'])->name('activation.complete');

Route::middleware('auth')->prefix('api/notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::post('/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/read-all', [NotificationController::class, 'markAllRead']);
});

Route::middleware(['auth', 'role:system-admin'])->prefix('admin')->group(function () {
    Route::get('/', AdminDashboardController::class);
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::post('/users/manual-employee', [AdminUserController::class, 'storeManualEmployee']);
    Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole']);
    Route::post('/users/{user}/resend-activation', [AdminUserController::class, 'resendActivation']);
    Route::delete('/users/{account}', [AdminUserController::class, 'destroy']);
    Route::get('/employees', [AdminEmployeeController::class, 'index']);
    Route::post('/employees/import', [AdminEmployeeController::class, 'import']);
    Route::patch('/employees/{employeeRecord}', [AdminEmployeeController::class, 'update']);
    Route::redirect('/offices', '/admin/employees');
    Route::post('/offices/import', [AdminEmployeeController::class, 'import']);
    Route::get('/settings', AdminLearningDevelopmentController::class);
    Route::get('/settings/export', [AdminReportController::class, 'export']);
    Route::get('/profile', [AdminProfileController::class, 'show']);
    Route::patch('/profile', [AdminProfileController::class, 'update']);
});

Route::middleware(['auth', 'role:secretariat'])->prefix('secretariat')->group(function () {
    Route::get('/', [SecretariatPortalController::class, 'dashboard']);
    Route::get('/applications', [SecretariatPortalController::class, 'applications']);
    Route::patch('/applications/{trainingApplication}', [SecretariatPortalController::class, 'processApplication']);
    Route::get('/ld-plans', [SecretariatPortalController::class, 'ldPlans']);
    Route::post('/ld-plans', [SecretariatPortalController::class, 'storeLdPlan']);
    Route::get('/training-monitor', [SecretariatPortalController::class, 'trainingMonitor']);
    Route::get('/lap-submissions', [SecretariatPortalController::class, 'lapSubmissions']);
    Route::patch('/lap-submissions/{learningActionPlan}', [SecretariatPortalController::class, 'processLap']);
    Route::get('/reports', [SecretariatPortalController::class, 'reports']);
    Route::get('/reports/export', [SecretariatPortalController::class, 'exportReport']);
    Route::get('/profile', [SecretariatPortalController::class, 'profile']);
    Route::patch('/profile', [SecretariatPortalController::class, 'updateProfile']);
    Route::redirect('/training-programs', '/secretariat/ld-plans');
    Route::redirect('/schedules', '/secretariat/training-monitor');
    Route::redirect('/nominations', '/secretariat/applications');
    Route::redirect('/attendance', '/secretariat/lap-submissions');
});

Route::middleware(['auth', 'role:hrdc'])->prefix('hrdc')->group(function () {
    Route::get('/', [HrdcPortalController::class, 'dashboard']);
    Route::get('/ld-plans', [HrdcPortalController::class, 'plans']);
    Route::get('/ld-plans/{learningDevelopmentPlan}', [HrdcPortalController::class, 'planShow']);
    Route::patch('/ld-plans/{learningDevelopmentPlan}/receive', [HrdcPortalController::class, 'receivePlan']);
    Route::get('/program-approvals', [HrdcPortalController::class, 'programApprovals']);
    Route::patch('/program-approvals/{proposedTrainingProgram}', [HrdcPortalController::class, 'reviewProgram']);
    Route::get('/reports', [HrdcPortalController::class, 'reports']);
    Route::get('/profile', [HrdcPortalController::class, 'profile']);
    Route::patch('/profile', [HrdcPortalController::class, 'updateProfile']);
    Route::redirect('/training-plans', '/hrdc/ld-plans');
    Route::redirect('/nominations', '/hrdc/program-approvals');
    Route::redirect('/evaluations', '/hrdc/program-approvals');
});

Route::middleware(['auth', 'role:supervisor'])->prefix('supervisor')->group(function () {
    Route::get('/', [SupervisorPortalController::class, 'dashboard']);
    Route::get('/team', [SupervisorPortalController::class, 'teamIndex']);
    Route::get('/lna-reviews', [SupervisorLnaController::class, 'index']);
    Route::patch('/lna-reviews/{learningNeedsAnalysis}', [SupervisorLnaController::class, 'update']);
    Route::get('/nominations', [SupervisorPortalController::class, 'nominations']);
    Route::get('/nominations/{id}', [SupervisorPortalController::class, 'nominationShow']);
    Route::get('/trainings', [SupervisorPortalController::class, 'trainings']);
    Route::get('/idp', [SupervisorPortalController::class, 'idpIndex']);
    Route::get('/idp/{learningActionPlan}', [SupervisorPortalController::class, 'idpShow']);
    Route::get('/profile', [SupervisorPortalController::class, 'profile']);
    Route::patch('/profile', [SupervisorPortalController::class, 'updateProfile']);
});

Route::middleware(['auth', 'employee.access', 'trainee.only'])->prefix('employee')->group(function () {
    Route::get('/', EmployeeDashboardController::class);
    Route::get('/recommendations', [EmployeeLearningController::class, 'trainings']);
    Route::get('/training-applications', [EmployeeLearningController::class, 'applications']);
    Route::post('/training-applications', [EmployeeLearningController::class, 'storeTraining']);
    Route::redirect('/my-trainings', '/employee/training-applications');
    Route::get('/training-applications/{trainingApplication}', [EmployeeLearningController::class, 'showApplication']);
    Route::redirect('/my-idp', '/employee/learning-needs-analysis');
    Route::get('/learning-needs-analysis', [EmployeeLearningController::class, 'learningNeedsAnalysis']);
    Route::post('/learning-needs-analysis', [EmployeeLearningController::class, 'storeLna']);
    Route::get('/learning-action-plan', [EmployeeLearningController::class, 'learningActionPlan']);
    Route::post('/learning-action-plan', [EmployeeLearningController::class, 'storeLap']);
    Route::get('/history', [EmployeeLearningController::class, 'history']);
    Route::get('/profile', [EmployeeProfileController::class, 'show']);
    Route::patch('/profile', [EmployeeProfileController::class, 'update']);
});

require __DIR__.'/settings.php';
