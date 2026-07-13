<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\JobPosting;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CloseExpiredJobPostings extends Command
{
    protected $signature   = 'job-postings:close-expired';
    protected $description = 'Automatically close job postings whose deadline has passed and reject all non-hired applicants.';

    public function handle(): int
    {
        $today = Carbon::today();

        // Find postings that have a closes_at in the past and are not yet closed
        $expired = JobPosting::whereNotNull('closes_at')
            ->where('closes_at', '<', $today)
            ->where('status', '!=', 'closed')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired job postings found.');
            return self::SUCCESS;
        }

        foreach ($expired as $posting) {
            // Reject all applicants that have not been hired
            $rejected = Application::where('job_posting_id', $posting->id)
                ->where('status', '!=', 'hired')
                ->update(['status' => 'rejected']);

            // Close the posting
            $posting->update(['status' => 'closed']);

            $this->line("  Closed: [{$posting->id}] {$posting->title} (deadline: {$posting->closes_at}) — {$rejected} applicant(s) rejected.");
        }

        $this->info("Done. {$expired->count()} posting(s) closed.");

        return self::SUCCESS;
    }
}