<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\AssessmentCriterion;
use App\Models\Candidate;
use App\Models\CandidateAssessment;
use App\Models\JobPosting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Generates enough job postings, candidates, applications, and ranked
 * assessments to actually see pagination kick in on:
 *   - Job postings list
 *   - Applications / Records list
 *   - The ranking-of-candidates panel inside a job posting's pipeline
 *
 * This is TEST DATA ONLY — everything it creates is clearly fake
 * (emails end in @example.test, names are Faker-generated) so it's
 * obvious what to delete afterward. See the teardown note at the
 * bottom of this file.
 *
 * Heads up: JobPosting/Application/CandidateAssessment all have
 * created/updated/deleted observers registered in AppServiceProvider
 * that write to activity_logs — running this will also generate a
 * matching batch of activity log rows. Expected, not a bug.
 *
 * Run manually (never run automatically for you):
 *   php artisan db:seed --class=Database\\Seeders\\PaginationTestSeeder
 *
 * Adjust the counts below if you want more/less per page.
 */
class PaginationTestSeeder extends Seeder
{
    // Tune these to however much you need to see multiple pages.
    private const CANDIDATE_COUNT      = 80;
    private const JOB_POSTING_COUNT    = 45;
    private const APPLICATIONS_MIN     = 3;   // per posting
    private const APPLICATIONS_MAX     = 12;  // per posting
    private const RANKING_POSTINGS     = 3;   // how many postings get a full ranking-ready pipeline
    private const RANKABLE_PER_POSTING = 25;  // qualified applications per ranking posting

    private const JOB_POSTING_STATUSES = ['open', 'open', 'open', 'interview_scheduled', 'ranking', 'closed'];
    private const APPLICATION_STATUSES = [
        'submitted', 'screening', 'shortlisted', 'interview',
        'assessed', 'ranked', 'ranking_sent', 'offer', 'offer_sent',
        'offer_accepted', 'offer_declined', 'hired', 'rejected',
    ];
    private const EMPLOYMENT_TYPES = ['Permanent', 'Contract of Service', 'Job Order'];
    private const JOB_TITLES = [
        'Teacher I', 'Teacher II', 'Master Teacher I', 'Administrative Assistant II',
        'Administrative Officer II', 'Nurse II', 'Guidance Counselor I',
        'Security Guard II', 'Project Development Officer I', 'School Principal I',
        'Special Education Teacher I', 'Registrar I', 'Head Teacher I',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $candidates = $this->makeCandidates();
            $postings   = $this->makeJobPostings();

            foreach ($postings as $i => $posting) {
                $this->makeApplications($posting, $candidates);
            }

            // Give a handful of postings a full ranking-ready pipeline:
            // assessment criteria + enough qualified, assessed
            // applications to actually paginate the ranking table.
            $rankingPostings = collect($postings)
                ->filter(fn ($p) => $p->status === 'ranking')
                ->take(self::RANKING_POSTINGS);

            foreach ($rankingPostings as $posting) {
                $this->makeRankingData($posting, $candidates);
            }
        });

        $this->command->info('Pagination test data seeded:');
        $this->command->info('  ' . self::CANDIDATE_COUNT . ' candidates');
        $this->command->info('  ' . self::JOB_POSTING_COUNT . ' job postings');
        $this->command->info('  ' . self::RANKING_POSTINGS . ' posting(s) with a full ranking table ready to view');
    }

    private function makeCandidates(): \Illuminate\Support\Collection
    {
        $candidates = collect();

        for ($i = 0; $i < self::CANDIDATE_COUNT; $i++) {
            $candidates->push(Candidate::create([
                'first_name'       => fake()->firstName(),
                'middle_name'      => fake()->optional(0.7)->firstName(),
                'last_name'        => fake()->lastName(),
                'email'            => 'testdata+' . time() . '-' . $i . '@example.test',
                'password'         => 'password', // 'hashed' cast on the model hashes this automatically
                'phone'            => '+639' . fake()->numerify('#########'),
                'address'          => fake()->address(),
                'age'              => fake()->numberBetween(21, 58),
                'sex'              => fake()->randomElement(['Male', 'Female']),
                'civil_status'     => fake()->randomElement(['Single', 'Married', 'Widowed']),
                'religion'         => fake()->randomElement(['Roman Catholic', 'Christian', 'Iglesia ni Cristo', null]),
                'disability'       => null,
                'ethnic_group'     => null,
                'education'        => fake()->randomElement([
                    "Bachelor's Degree in Education",
                    "Bachelor's Degree in Elementary Education",
                    "Bachelor's Degree in Secondary Education, Major in Mathematics",
                    "Master's Degree in Education Management",
                ]),
                'training_hours'   => fake()->numberBetween(0, 120),
                'years_experience' => fake()->numberBetween(0, 15),
                'eligibility'      => fake()->randomElement([
                    'LET/PBET', 'Career Service Professional', 'RA 1080', null,
                ]),
            ]));
        }

        return $candidates;
    }

    private function makeJobPostings(): array
    {
        $postings = [];

        for ($i = 0; $i < self::JOB_POSTING_COUNT; $i++) {
            $postedAt = fake()->dateTimeBetween('-6 months', '-1 week');

            $postings[] = JobPosting::create([
                'title'                      => fake()->randomElement(self::JOB_TITLES),
                'description'                => fake()->paragraph(),
                'duties_responsibilities'    => fake()->paragraphs(3, true),
                'qualification_education'    => "Bachelor's degree relevant to the job",
                'qualification_training'     => fake()->numberBetween(0, 40) . ' hours of relevant training',
                'qualification_experience'   => fake()->numberBetween(0, 3) . ' year(s) of relevant experience',
                'qualification_eligibility'  => 'Career Service Professional (Second Level) or its equivalent',
                'mandatory_requirements'     => null, // falls back to RequirementsExtractor defaults
                'additional_requirements'    => null,
                'memo_pdf_path'              => null,
                'place_of_assignment'        => null, // intentionally null — see prior migration
                'employment_type'            => fake()->randomElement(self::EMPLOYMENT_TYPES),
                'salary_grade'               => 'SG-' . fake()->numberBetween(1, 24),
                'vacancies'                  => fake()->numberBetween(1, 5),
                'posted_at'                  => $postedAt,
                'closes_at'                  => (clone $postedAt)->modify('+' . fake()->numberBetween(14, 45) . ' days'),
                'status'                     => fake()->randomElement(self::JOB_POSTING_STATUSES),
            ]);
        }

        return $postings;
    }

    private function makeApplications(JobPosting $posting, \Illuminate\Support\Collection $candidates): void
    {
        $count = fake()->numberBetween(self::APPLICATIONS_MIN, self::APPLICATIONS_MAX);

        for ($i = 0; $i < $count; $i++) {
            Application::create([
                'transaction_number'        => null, // assigned later by Records, not at creation
                'candidate_id'               => $candidates->random()->id,
                'job_posting_id'             => $posting->id,
                'job_posting_location_id'    => null,
                'status'                     => fake()->randomElement(self::APPLICATION_STATUSES),
                'applied_at'                 => fake()->dateTimeBetween($posting->posted_at, 'now'),
                'notes'                      => null,
                'qualification_check'        => null,
                'qualification_result'       => fake()->randomElement(['qualified', 'not_qualified', null, null]),
                'qualification_checked_at'   => null,
                'qualification_notified_at'  => null,
                'schedule_notice_sent_at'    => null,
            ]);
        }
    }

    /**
     * For a handful of 'ranking'-status postings: build assessment
     * criteria (weights summing to 100) plus enough qualified,
     * fully-scored applications to paginate the ranking table itself.
     */
    private function makeRankingData(JobPosting $posting, \Illuminate\Support\Collection $candidates): void
    {
        $criteriaDefs = [
            ['name' => 'Education',  'weight_percentage' => 30],
            ['name' => 'Experience', 'weight_percentage' => 40],
            ['name' => 'Interview',  'weight_percentage' => 30],
        ];

        $criteria = collect($criteriaDefs)->map(fn ($def) => AssessmentCriterion::create([
            'job_posting_id'     => $posting->id,
            'name'               => $def['name'],
            'weight_percentage'  => $def['weight_percentage'],
            'description'        => null,
        ]));

        for ($i = 0; $i < self::RANKABLE_PER_POSTING; $i++) {
            $application = Application::create([
                'transaction_number'        => null,
                'candidate_id'               => $candidates->random()->id,
                'job_posting_id'             => $posting->id,
                'job_posting_location_id'    => null,
                'status'                     => 'ranked',
                'applied_at'                 => fake()->dateTimeBetween($posting->posted_at, 'now'),
                'notes'                      => null,
                'qualification_check'        => null,
                'qualification_result'       => 'qualified', // required to appear in the ranking panel at all
                'qualification_checked_at'   => now(),
                'qualification_notified_at'  => null,
                'schedule_notice_sent_at'    => null,
            ]);

            foreach ($criteria as $criterion) {
                // Score as a random fraction of that criterion's own
                // weight, so totals spread realistically around the
                // pass/fail line (>= 75) instead of clustering at the
                // maximum.
                CandidateAssessment::create([
                    'application_id'            => $application->id,
                    'assessment_criteria_id'     => $criterion->id,
                    'score'                      => round(fake()->randomFloat(2, 0.5, 1.0) * $criterion->weight_percentage, 2),
                ]);
            }
        }
    }
}
