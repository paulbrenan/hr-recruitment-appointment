<?php

namespace App\Http\Controllers;

use App\Mail\ApplicationCodeAssigned;
use App\Models\Application;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RecordsController extends Controller
{
    /**
     * Applications still waiting on an Application Code -- i.e. Records
     * hasn't verified the applicant's submitted requirements yet.
     *
     * Supports:
     *   ?search=   filter by the applicant's (candidate's) full name
     *   ?position= filter by job posting title (dropdown of distinct titles)
     */
    public function index(Request $request)
    {
        $search = trim($request->query('search', ''));
        $position = trim($request->query('position', ''));

        $pending = Application::with(['candidate', 'jobPosting'])
            ->whereNull('transaction_number')
            ->when($search !== '', function ($query) use ($search) {
                // CONFIRMED REAL BUG: full_name is a PHP accessor on the
                // Candidate model (first_name/middle_name/last_name
                // concatenated at read time) -- it isn't a real database
                // column, so where('full_name', ...) blew up with
                // "Column not found" the moment anyone typed a letter.
                // Filter on the actual columns instead.
                $query->whereHas('candidate', function ($q) use ($search) {
                    $q->where(function ($nameQuery) use ($search) {
                        $nameQuery->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('middle_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%');
                    });
                });
            })
            ->when($position !== '', function ($query) use ($position) {
                $query->whereHas('jobPosting', function ($q) use ($position) {
                    $q->where('title', $position);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Applications that already have a code, for the "Assigned" table
        // where Records can correct a mistyped/wrong code. Kept simple:
        // same $search filter, separate pagination page name so paginating
        // one table doesn't reset the other.
        $assigned = Application::with(['candidate', 'jobPosting'])
            ->whereNotNull('transaction_number')
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('candidate', function ($q) use ($search) {
                    $q->where(function ($nameQuery) use ($search) {
                        $nameQuery->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('middle_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%');
                    });
                });
            })
            ->when($position !== '', function ($query) use ($position) {
                $query->whereHas('jobPosting', function ($q) use ($position) {
                    $q->where('title', $position);
                });
            })
            ->latest()
            ->paginate(20, ['*'], 'assigned_page')
            ->withQueryString();

        // Distinct job titles for the position filter dropdown, drawn only
        // from postings that currently have at least one pending application.
        $positions = JobPosting::whereHas('applications', function ($q) {
                $q->whereNull('transaction_number');
            })
            ->orderBy('title')
            ->pluck('title')
            ->unique()
            ->values();

        return view('records.index', compact('pending', 'assigned', 'search', 'position', 'positions'));
    }

    /**
     * Records has checked the applicant's requirements and is now
     * assigning the official SDO-YYYY-#### Application Code. Sends a
     * dedicated ApplicationCodeAssigned email containing the code
     * (see resources/views/mail/application-code.blade.php).
     */
    public function assignCode(Request $request, $id)
    {
        $application = Application::with(['candidate', 'jobPosting'])
            ->whereNull('transaction_number')
            ->findOrFail($id);

        // Optional manual override -- if Records typed a specific code,
        // validate and use that instead of auto-generating. Left blank
        // (the default), behavior is unchanged from before.
        $validated = $request->validate([
            'transaction_number' => [
                'nullable',
                'string',
                'max:50',
                'unique:applications,transaction_number',
            ],
        ]);

        $manualCode = trim((string) ($validated['transaction_number'] ?? ''));

        DB::transaction(function () use ($application, $manualCode) {
            $application->update([
                'transaction_number' => $manualCode !== ''
                    ? $manualCode
                    : Application::generateTransactionNumber(),
            ]);
        });

        try {
            Mail::to($application->candidate->email)
                ->send(new ApplicationCodeAssigned(
                    $application->candidate,
                    $application->transaction_number,
                    $application->jobPosting->title ?? '',
                    $application->jobPosting
                ));
        } catch (\Throwable $e) {
            Log::error('Application Code email failed for application ' . $application->id . ': ' . $e->getMessage());
        }

        return redirect()
            ->route('records.index')
            ->with('success', 'Application Code ' . $application->transaction_number . ' assigned to ' . ($application->candidate->full_name ?? 'applicant') . ' and emailed.');
    }

    /**
     * Records made a mistake on an already-assigned Application Code
     * (typo, wrong format, etc.) and needs to correct it manually.
     * This is a data fix only -- it does NOT resend the assignment email.
     */
    public function updateCode(Request $request, $id)
    {
        $application = Application::whereNotNull('transaction_number')
            ->findOrFail($id);

        $validated = $request->validate([
            'transaction_number' => [
                'required',
                'string',
                'max:50',
                'unique:applications,transaction_number,' . $application->id,
            ],
        ]);

        $application->update([
            'transaction_number' => $validated['transaction_number'],
        ]);

        return redirect()
            ->route('records.index')
            ->with('success', 'Application Code updated to ' . $application->transaction_number . '.');
    }
}