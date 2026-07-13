<?php

namespace App\Services;

use App\Models\Application;
use App\Models\JobPosting;
use Illuminate\Support\Collection;

class RankingService
{
    /**
     * Compute weighted rankings for all assessed applicants of a posting.
     *
     * Score formula:
     *   weighted_score = SUM( (criterion.weight_percentage / 100) * assessment.score )
     *
     * Returns a collection sorted by weighted_score DESC, with rank assigned.
     *
     * Moved here (unchanged) from RankingController so it can also be used
     * by InterviewScheduleController when combining a ranking result with
     * a schedule invitation into a single email.
     */
    public function computeRankings(JobPosting $posting): Collection
    {
        $applications = Application::with([
            'candidate',
            'assessments.criterion',
        ])
            ->where('job_posting_id', $posting->id)
            ->whereHas('assessments')
            ->get();

        $rows = $applications->map(function (Application $app) {
            $weightedScore = 0;

            foreach ($app->assessments as $assessment) {
                $weight = $assessment->criterion?->weight_percentage ?? 0;
                $score  = $assessment->score ?? 0;
                $weightedScore += ($weight / 100) * $score;
            }

            return [
                'application_id' => $app->id,
                'candidate'      => $app->candidate,
                'weighted_score' => round($weightedScore, 2),
                'status'         => $app->status,
                'notification_sent' => $app->status === 'ranking_sent',
            ];
        });

        return $rows->sortByDesc('weighted_score')
            ->values()
            ->map(function ($row, $index) use ($rows) {
                $row['rank']   = $index + 1;
                $row['passed'] = $row['weighted_score'] >= 75;
                $row['total']  = $rows->count();
                return $row;
            });
    }
}
