<?php

namespace Database\Seeders;

use App\Models\JobPosting;
use App\Models\AssessmentCriterion;
use App\Models\CandidateAssessment;
use App\Models\Application;
use Illuminate\Database\Seeder;

class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $criteriaTemplate = [
            ['name' => 'Technical skills', 'weight_percentage' => 40, 'description' => 'Job-specific knowledge and competence'],
            ['name' => 'Communication', 'weight_percentage' => 30, 'description' => 'Clarity, listening, and interpersonal skills'],
            ['name' => 'Problem solving', 'weight_percentage' => 30, 'description' => 'Analytical thinking and adaptability'],
        ];

        $postings = JobPosting::all();

        if ($postings->isEmpty()) {
            $this->command->warn('No job postings found. Run JobPostingSeeder first.');
            return;
        }

        foreach ($postings as $posting) {
            $existingCriteria = AssessmentCriterion::where('job_posting_id', $posting->id)->get();

            if ($existingCriteria->isNotEmpty()) {
                $criteria = $existingCriteria;
            } else {
                $criteria = collect($criteriaTemplate)->map(function ($c) use ($posting) {
                    return AssessmentCriterion::create([
                        'job_posting_id' => $posting->id,
                        'name' => $c['name'],
                        'weight_percentage' => $c['weight_percentage'],
                        'description' => $c['description'],
                    ]);
                });
            }

            $applications = Application::where('job_posting_id', $posting->id)->get();

            foreach ($applications as $i => $application) {
                // Only seed scores for roughly half the applications per posting,
                // so the ranking page shows a realistic mix of scored and unscored candidates.
                if ($i % 2 !== 0) {
                    continue;
                }

                foreach ($criteria as $criterion) {
                    $maxScore = (float) $criterion->weight_percentage;
                    $score = round($maxScore * (0.7 + (mt_rand(0, 25) / 100)), 2);
                    $score = min($score, $maxScore);

                    CandidateAssessment::updateOrCreate(
                        [
                            'application_id' => $application->id,
                            'assessment_criteria_id' => $criterion->id,
                        ],
                        [
                            'score' => $score,
                            'evaluator_remarks' => 'Seeded sample evaluation.',
                            'evaluated_by' => 'HR Panel',
                        ]
                    );
                }
            }
        }

        $this->command->info('Assessment criteria and sample scores seeded.');
    }
}