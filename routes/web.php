<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\JobPostingImportController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\InterviewScheduleController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\JobOfferController;
use App\Http\Controllers\TalentPoolController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\RankingController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Job postings
Route::get('/job-postings', [JobPostingController::class, 'index'])->name('job-postings.index');
Route::get('/job-postings/create', [JobPostingController::class, 'create'])->name('job-postings.create');
Route::get('/job-postings/{id}/edit', [JobPostingController::class, 'edit'])->name('job-postings.edit');
// Job postings -- PDF import (Stage 2: extraction diagnostic only)
Route::get('/job-postings/import', [JobPostingImportController::class, 'create'])->name('job-postings.import.create');
Route::post('/job-postings/import/extract', [JobPostingImportController::class, 'extract'])->name('job-postings.import.extract');
Route::get('/job-postings/{id}', [JobPostingController::class, 'show'])->name('job-postings.show');
Route::post('/job-postings', [JobPostingController::class, 'store'])->name('job-postings.store');
Route::put('/job-postings/{id}', [JobPostingController::class, 'update'])->name('job-postings.update');
Route::delete('/job-postings/{id}', [JobPostingController::class, 'destroy'])->name('job-postings.destroy');

//Rankings
Route::get('/rankings', [RankingController::class, 'index'])->name('rankings.index');
Route::post('/rankings/send/{application}', [RankingController::class, 'sendOne'])->name('rankings.send-one');
Route::post('/rankings/send-all', [RankingController::class, 'sendAll'])->name('rankings.send-all');

// Applications
Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
Route::get('/applications/{id}', [ApplicationController::class, 'show'])->name('applications.show');
Route::put('/applications/{id}/status', [ApplicationController::class, 'updateStatus'])->name('applications.updateStatus');
Route::delete('/applications/{id}', [ApplicationController::class, 'destroy'])->name('applications.destroy');

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

// Appointment & onboarding
Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
Route::put('/appointments/{id}', [AppointmentController::class, 'update'])->name('appointments.update');
Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');