<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Models\ActivityLog;
use App\Models\JobPosting;
use App\Models\Application;
use App\Models\JobOffer;
use App\Models\InterviewSchedule;
use App\Models\CandidateAssessment;
use App\Models\TalentPool;
use App\Models\Appointment;
use App\Models\Pipeline;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel's default paginator view uses Tailwind classes, which
        // this Bootstrap 5 project doesn't load — without this, ->links()
        // renders unstyled Previous/Next anchors that stack as two
        // oversized full-width blocks instead of small inline buttons.
        Paginator::useBootstrapFive();

        // Activity Log Book: record create/update/delete on core HR models.
        $loggedModels = [
            JobPosting::class => 'title',
            Application::class => 'transaction_number',
            JobOffer::class => null,
            InterviewSchedule::class => null,
            CandidateAssessment::class => null,
            TalentPool::class => null,
            Appointment::class => null,
            Pipeline::class => null,
        ];

        foreach ($loggedModels as $modelClass => $labelField) {
            $modelClass::created(function ($model) use ($labelField) {
                ActivityLog::recordFor($model, 'created', $labelField);
            });
            $modelClass::updated(function ($model) use ($labelField) {
                ActivityLog::recordFor($model, 'updated', $labelField);
            });
            $modelClass::deleted(function ($model) use ($labelField) {
                ActivityLog::recordFor($model, 'deleted', $labelField);
            });
        }
    }
}
