<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Small, fast seeder for testing the Qualification Checking step
 * specifically. Creates exactly ONE job posting with 1-2 applicants,
 * all sitting at "pending qualification checking" — i.e. status
 * 'submitted' or 'screening', with qualification_result left null (no
 * qualified/not_qualified decision made yet). Nothing further along the
 * pipeline, so you can walk through checking qualifications from a
 * clean, minimal starting point instead of digging through a big
 * dataset.
 *
 * Also creates one HR staff login account (same as OpenJobsTestSeeder),
 * so this works standalone after a `migrate:fresh` too.
 *
 * TEST DATA ONLY — everything created here is clearly fake (emails end
 * in @example.test, names are Faker-generated) so it's obvious what to
 * delete afterward.
 *
 * Heads up: JobPosting/Application observers registered in
 * AppServiceProvider write to activity_logs — running this will also
 * generate a small batch of activity log rows. Expected, not a bug.
 *
 * Run manually (never run automatically for you):
 *   php artisan db:seed --class=Database\\Seeders\\QualificationCheckingTestSeeder
 *
 * Safe to run alongside OpenJobsTestSeeder / PaginationTestSeeder --
 * it only adds its own posting/applicants, doesn't touch existing data.
 */
class QualificationCheckingTestSeeder extends Seeder
{
    // 1 or 2 applicants — tune here if you want a couple more.
    private const APPLICANT_COUNT = 10;

    private const JOB_TITLE = 'Administrative Assistant II';

    // Both statuses read as "not yet qualification-checked" in this app.
    private const PENDING_STATUSES = ['submitted', 'screening'];

    // Edit before running if you want different login credentials.
    private const ADMIN_EMAIL    = 'admin@example.com';
    private const ADMIN_PASSWORD = 'admin1234';
    private const ADMIN_NAME     = 'HR Staff';

    public function run(): void
    {
        $admin = $this->makeAdminUser();

        DB::transaction(function () {
            $posting = $this->makeJobPosting();
            $this->makePendingApplicants($posting);
        });

        $this->command->info('Qualification-checking test data seeded:');
        $this->command->info('  1 job posting: "' . self::JOB_TITLE . '"');
        $this->command->info('  ' . self::APPLICANT_COUNT . ' applicant(s), all pending qualification checking (status: submitted/screening, qualification_result: null)');
        $this->command->info('');
        $this->command->info('HR staff login:');
        $this->command->info('  email:    ' . self::ADMIN_EMAIL);
        $this->command->info('  password: ' . self::ADMIN_PASSWORD);
        $this->command->info('  (' . ($admin->wasRecentlyCreated ? 'created new account' : 'already existed — password reset to the above') . ')');
    }

    private function makeAdminUser(): User
    {
        $user = User::where('email', self::ADMIN_EMAIL)->first();

        if ($user) {
            $user->password = Hash::make(self::ADMIN_PASSWORD);
            $user->save();
            return $user;
        }

        return User::create([
            'name'     => self::ADMIN_NAME,
            'email'    => self::ADMIN_EMAIL,
            'password' => Hash::make(self::ADMIN_PASSWORD),
        ]);
    }

    private function makeJobPosting(): JobPosting
    {
        $postedAt = fake()->dateTimeBetween('-1 week', '-1 day');

        return JobPosting::create([
            'title'                      => self::JOB_TITLE,
            'description'                => fake()->paragraph(),
            'duties_responsibilities'    => fake()->paragraphs(3, true),
            'qualification_education'    => "Bachelor's degree relevant to the job",
            'qualification_training'     => fake()->numberBetween(0, 40) . ' hours of relevant training',
            'qualification_experience'   => fake()->numberBetween(0, 3) . ' year(s) of relevant experience',
            'qualification_eligibility'  => 'Career Service Professional (Second Level) or its equivalent',
            'mandatory_requirements'     => null, // falls back to RequirementsExtractor defaults
            'additional_requirements'    => null,
            'memo_pdf_path'              => null,
            'place_of_assignment'        => null,
            'employment_type'            => 'Permanent',
            'salary_grade'               => 'SG-11',
            'vacancies'                  => 1,
            'posted_at'                  => $postedAt,
            'closes_at'                  => fake()->dateTimeBetween('+1 week', '+1 month'),
            'status'                     => 'open',
        ]);
    }

    private function makePendingApplicants(JobPosting $posting): void
    {
        for ($i = 0; $i < self::APPLICANT_COUNT; $i++) {
            $candidate = Candidate::create([
                'first_name'       => fake()->firstName(),
                'middle_name'      => fake()->optional(0.7)->firstName(),
                'last_name'        => fake()->lastName(),
                'email'            => 'testdata+qc-' . time() . '-' . $i . '@example.test',
                'password'         => 'password', // 'hashed' cast on the model hashes this automatically
                'phone'            => '+639' . fake()->numerify('#########'),
                'address'          => fake()->address(),
                'age'              => fake()->numberBetween(21, 58),
                'sex'              => fake()->randomElement(['Male', 'Female']),
                'civil_status'     => fake()->randomElement(['Single', 'Married', 'Widowed']),
                'religion'         => fake()->randomElement(['Roman Catholic', 'Christian', 'Iglesia ni Cristo', null]),
                'disability'       => null,
                'ethnic_group'     => null,
                'education'        => "Bachelor's Degree in Business Administration",
                'training_hours'   => fake()->numberBetween(0, 40),
                'years_experience' => fake()->numberBetween(0, 5),
                'eligibility'      => fake()->randomElement(['Career Service Professional', 'RA 1080', null]),
            ]);

            Application::create([
                'transaction_number'        => null, // assigned later by Records, not at creation
                'candidate_id'               => $candidate->id,
                'job_posting_id'             => $posting->id,
                'job_posting_location_id'    => null,
                'status'                     => fake()->randomElement(self::PENDING_STATUSES),
                'applied_at'                 => fake()->dateTimeBetween($posting->posted_at, 'now'),
                'notes'                      => null,
                'qualification_check'        => null,
                'qualification_result'       => null, // not checked yet — the whole point of this seeder
                'qualification_checked_at'   => null,
                'qualification_notified_at'  => null,
                'schedule_notice_sent_at'    => null,
            ]);
        }
    }
}
