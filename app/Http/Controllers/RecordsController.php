<?php

namespace App\Http\Controllers;

use App\Mail\ApplicationCodeAssigned;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RecordsController extends Controller
{
    /**
     * Applications still waiting on an Application Code -- i.e. Records
     * hasn't verified the applicant's submitted requirements yet.
     */
    public function index(Request $request)
    {
        $pending = Application::with(['candidate', 'jobPosting'])
            ->whereNull('transaction_number')
            ->latest()
            ->get();

        return view('records.index', compact('pending'));
    }

    /**
     * Records has checked the applicant's requirements and is now
     * assigning the official SDO-YYYY-#### Application Code. Sends a
     * dedicated ApplicationCodeAssigned email containing the code
     * (see resources/views/mail/application-code.blade.php).
     */
    public function assignCode($id)
    {
        $application = Application::with(['candidate', 'jobPosting'])
            ->whereNull('transaction_number')
            ->findOrFail($id);

        DB::transaction(function () use ($application) {
            $application->update([
                'transaction_number' => Application::generateTransactionNumber(),
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
}