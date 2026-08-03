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
 * Multiple job postings, each with a random 2-11 applicants (so some
 * postings end up small — 2-3 applicants — good for quick spot-checks,
 * while others are big enough to see the qualification-checking list
 * scroll/paginate). EVERY applicant across EVERY posting sits at
 * "pending qualification checking" -- status 'submitted' or 'screening',
 * qualification_result left null -- nothing pre-decided, so you're
 * testing the actual checking workflow from a clean state rather than
 * data that's already been through it.
 *
 * All postings are 'open' (never 'closed'), so every one you click into
 * stays fully walkable/editable.
 *
 * Also creates one HR staff login account, so this works standalone
 * after a `migrate:fresh` too.
 *
 * TEST DATA ONLY — everything created here is clearly fake (emails end
 * in @example.test, names are Faker-generated) so it's obvious what to
 * delete afterward.
 *
 * Heads up: JobPosting/Application observers registered in
 * AppServiceProvider write to activity_logs — running this will also
 * generate a matching batch of activity log rows. Expected, not a bug.
 *
 * Run manually (never run automatically for you):
 *   php artisan db:seed --class=Database\\Seeders\\PendingQualificationTestSeeder
 *
 * Safe to run alongside your other test seeders -- it only adds its own
 * postings/applicants, doesn't touch existing data.
 */
class PendingQualificationTestSeeder extends Seeder
{
    // How many job postings to create.
    private const JOB_POSTING_COUNT = 15;

    // Applicant count per posting is randomized within this range, so
    // you naturally get a mix of small (2-3) and large (up to 11)
    // postings without having to special-case anything.
    private const APPLICANTS_MIN = 2;
    private const APPLICANTS_MAX = 11;

    // Both statuses read as "not yet qualification-checked" in this app.
    private const PENDING_STATUSES = ['submitted', 'screening'];

    private const JOB_TITLES = [
        'Teacher I', 'Teacher II', 'Master Teacher I', 'Administrative Assistant II',
        'Administrative Officer II', 'Nurse II', 'Guidance Counselor I',
        'Security Guard II', 'Project Development Officer I', 'School Principal I',
        'Special Education Teacher I', 'Registrar I', 'Head Teacher I',
    ];
    private const EMPLOYMENT_TYPES = ['Permanent', 'Contract of Service', 'Job Order'];

    // Edit before running if you want different login credentials.
    private const ADMIN_EMAIL    = 'hrstaff@example.test';
    private const ADMIN_PASSWORD = 'password';
    private const ADMIN_NAME     = 'HR Staff (Test)';

    public function run(): void
    {
        $admin = $this->makeAdminUser();

        $applicantCounts = [];

        DB::transaction(function () use (&$applicantCounts) {
            for ($i = 0; $i < self::JOB_POSTING_COUNT; $i++) {
                $posting = $this->makeJobPosting();
                $count = fake()->numberBetween(self::APPLICANTS_MIN, self::APPLICANTS_MAX);
                $this->makePendingApplicants($posting, $count);
                $applicantCounts[] = $count;
            }
        });

        $this->command->info('Pending-qualification test data seeded:');
        $this->command->info('  ' . self::JOB_POSTING_COUNT . ' job postings (all open), ' . array_sum($applicantCounts) . ' applicants total');
        $this->command->info('  per-posting applicant counts: ' . implode(', ', $applicantCounts));
        $this->command->info('  every applicant is pending qualification checking (status: submitted/screening, qualification_result: null)');
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
        $postedAt = fake()->dateTimeBetween('-1 month', '-1 day');

        return JobPosting::create([
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
            'place_of_assignment'        => null,
            'employment_type'            => fake()->randomElement(self::EMPLOYMENT_TYPES),
            'salary_grade'               => 'SG-' . fake()->numberBetween(1, 24),
            'vacancies'                  => fake()->numberBetween(1, 5),
            'posted_at'                  => $postedAt,
            'closes_at'                  => fake()->dateTimeBetween('+1 week', '+2 months'),
            'status'                     => 'open',
        ]);
    }

    private function makePendingApplicants(JobPosting $posting, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $candidate = Candidate::create([
                'first_name'       => fake()->firstName(),
                'middle_name'      => fake()->optional(0.7)->firstName(),
                'last_name'        => fake()->lastName(),
                'email'            => 'testdata+pq-' . time() . '-' . $posting->id . '-' . $i . '@example.test',
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
                    "Bachelor's Degree in Business Administration",
                    "Master's Degree in Education Management",
                ]),
                'training_hours'   => fake()->numberBetween(0, 40),
                'years_experience' => fake()->numberBetween(0, 5),
                'eligibility'      => fake()->randomElement(['Career Service Professional', 'RA 1080', 'LET/PBET', null]),
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
