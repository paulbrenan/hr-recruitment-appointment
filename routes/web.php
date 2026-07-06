<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidateAuthController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\JobPostingImportController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\InterviewScheduleController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\JobOfferController;
use App\Http\Controllers\TalentPoolController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\RankingController;

// Admin (HR staff) authentication
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/portal/register', [CandidateAuthController::class, 'showRegister'])->name('portal.register');
Route::post('/portal/register', [CandidateAuthController::class, 'register'])->name('portal.register.attempt');
Route::get('/portal/login', [CandidateAuthController::class, 'showLogin'])->name('portal.login');
Route::post('/portal/login', [CandidateAuthController::class, 'login'])->name('portal.login.attempt');
Route::post('/portal/logout', [CandidateAuthController::class, 'logout'])->name('portal.logout');

Route::middleware('auth:candidate')->prefix('portal')->name('portal.')->group(function () {
    Route::get('/dashboard', [CandidateAuthController::class, 'dashboard'])->name('dashboard');
    Route::get('/jobs', [PortalController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/{id}', [PortalController::class, 'showJob'])->name('jobs.show');
    Route::post('/jobs/{id}/apply', [PortalController::class, 'apply'])->name('apply');
    Route::get('/my-applications', [PortalController::class, 'myApplications'])->name('my-applications');
});

// Public landing page
Route::get('/', function () {
    return view('welcome');
});

// AJAX tracker — returns JSON, no auth required
Route::get('/api/track', function (\Illuminate\Http\Request $request) {
    $txn = strtoupper(trim($request->query('txn', '')));

    if (!$txn) {
        return response()->json(['found' => false]);
    }

    $app = \App\Models\Application::with(['candidate', 'jobPosting'])
        ->where('transaction_number', $txn)
        ->first();

    if (!$app) {
        return response()->json(['found' => false]);
    }

    return response()->json([
        'found'      => true,
        'status'     => $app->status ?? 'submitted',
        'name'       => $app->candidate?->full_name ?? '—',
        'position'   => $app->jobPosting?->title ?? '—',
        'applied_at' => $app->applied_at
            ? \Carbon\Carbon::parse($app->applied_at)->format('M d, Y')
            : '—',
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Job postings
Route::get('/job-postings', [JobPostingController::class, 'index'])->name('job-postings.index');
Route::get('/job-postings/create', [JobPostingController::class, 'create'])->name('job-postings.create');
Route::get('/job-postings/{id}/edit', [JobPostingController::class, 'edit'])->name('job-postings.edit');
Route::get('/job-postings/import', [JobPostingImportController::class, 'create'])->name('job-postings.import.create');
Route::post('/job-postings/import/extract', [JobPostingImportController::class, 'extract'])->name('job-postings.import.extract');
Route::get('/job-postings/import/{batch}/processing', [JobPostingImportController::class, 'processing'])->name('job-postings.import.processing');
Route::get('/job-postings/import/{batch}/status', [JobPostingImportController::class, 'status'])->name('job-postings.import.status');
Route::get('/job-postings/import/{batch}/review', [JobPostingImportController::class, 'review'])->name('job-postings.import.review');
Route::post('/job-postings/import/{batch}/confirm', [JobPostingImportController::class, 'confirm'])->name('job-postings.import.confirm');
Route::get('/job-postings/{id}', [JobPostingController::class, 'show'])->name('job-postings.show');
Route::post('/job-postings', [JobPostingController::class, 'store'])->name('job-postings.store');
Route::put('/job-postings/{id}', [JobPostingController::class, 'update'])->name('job-postings.update');
Route::delete('/job-postings/{id}', [JobPostingController::class, 'destroy'])->name('job-postings.destroy');

// Rankings
Route::get('/rankings', [RankingController::class, 'index'])->name('rankings.index');
Route::post('/rankings/send/{application}', [RankingController::class, 'sendOne'])->name('rankings.send-one');
Route::post('/rankings/send-all', [RankingController::class, 'sendAll'])->name('rankings.send-all');

// Applications
Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
Route::get('/applications/{id}', [ApplicationController::class, 'show'])->name('applications.show');
Route::put('/applications/{id}/status', [ApplicationController::class, 'updateStatus'])->name('applications.updateStatus');
Route::post('/applications/{id}/qualification-check', [ApplicationController::class, 'saveQualificationCheck'])->name('applications.qualification-check');
Route::post('/applications/{id}/qualification-notice', [ApplicationController::class, 'sendQualificationNotice'])->name('applications.qualification-notice');

// Scheduling
Route::get('/interviews', [InterviewScheduleController::class, 'index'])->name('interviews.index');
Route::post('/interviews', [InterviewScheduleController::class, 'store'])->name('interviews.store');
Route::put('/interviews/{id}', [InterviewScheduleController::class, 'update'])->name('interviews.update');
Route::delete('/interviews/{id}', [InterviewScheduleController::class, 'destroy'])->name('interviews.destroy');

// Assessment & ranking
Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
Route::post('/assessments/send/{application}', [AssessmentController::class, 'sendOne'])->name('assessments.send-one');
Route::post('/assessments/criteria', [AssessmentController::class, 'storeCriterion'])->name('assessments.criteria.store');
Route::delete('/assessments/criteria/{id}', [AssessmentController::class, 'destroyCriterion'])->name('assessments.criteria.destroy');
Route::post('/assessments/scores', [AssessmentController::class, 'saveScores'])->name('assessments.scores.save');
Route::post('/assessments/send-all', [AssessmentController::class, 'sendAll'])->name('assessments.send-all');

// Offer management
Route::get('/offers', [JobOfferController::class, 'index'])->name('offers.index');
Route::post('/offers', [JobOfferController::class, 'store'])->name('offers.store');
Route::put('/offers/{id}/send', [JobOfferController::class, 'send'])->name('offers.send');
Route::put('/offers/{id}/respond', [JobOfferController::class, 'respond'])->name('offers.respond');
Route::delete('/offers/{id}', [JobOfferController::class, 'destroy'])->name('offers.destroy');

// Talent pool
Route::get('/talent-pool', [TalentPoolController::class, 'index'])->name('talent-pool.index');
Route::post('/talent-pool', [TalentPoolController::class, 'store'])->name('talent-pool.store');
Route::get('/talent-pool/{id}', [TalentPoolController::class, 'show'])->name('talent-pool.show');
Route::get('/talent-pool/{id}/edit', [TalentPoolController::class, 'edit'])->name('talent-pool.edit');
Route::put('/talent-pool/{id}', [TalentPoolController::class, 'update'])->name('talent-pool.update');
Route::delete('/talent-pool/{id}', [TalentPoolController::class, 'destroy'])->name('talent-pool.destroy');
Route::post('/applications/{id}/add-to-talent-pool', [TalentPoolController::class, 'storeFromApplication'])->name('talent-pool.store-from-application');

// Pipelines
Route::get('/pipelines', [PipelineController::class, 'index'])->name('pipelines.index');
Route::post('/pipelines', [PipelineController::class, 'store'])->name('pipelines.store');
Route::put('/pipelines/{id}', [PipelineController::class, 'update'])->name('pipelines.update');
Route::delete('/pipelines/{id}', [PipelineController::class, 'destroy'])->name('pipelines.destroy');

// Appointment & onboarding
Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
Route::put('/appointments/{id}', [AppointmentController::class, 'update'])->name('appointments.update');
Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');