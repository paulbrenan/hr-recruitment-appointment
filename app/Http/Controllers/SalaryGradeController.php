<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSalaryGradeImportJob;
use App\Models\BudgetCircular;
use App\Models\SalaryGrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SalaryGradeController extends Controller
{
    /**
     * Current SG table + import history. This is the "another dashboard"
     * page -- add its sidebar link next to Appointment & onboarding.
     */
    public function index()
    {
        $currentCircular = BudgetCircular::current()->first();
        $currentTable = SalaryGrade::currentTableArray();

        $circulars = BudgetCircular::orderByDesc('created_at')->paginate(15);

        return view('salary-grades.index', compact('currentCircular', 'currentTable', 'circulars'));
    }

    public function create()
    {
        return view('salary-grades.upload');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sg_file' => 'required|file|mimes:pdf,xlsx,xls,csv|max:20480',
            'circular_no' => 'nullable|string|max:50',
            'subject' => 'nullable|string|max:250',
            'effective_date' => 'nullable|date',
        ]);

        $file = $request->file('sg_file');
        $extension = strtolower($file->getClientOriginalExtension());
        $sourceType = $extension === 'pdf' ? 'pdf' : 'xlsx';

        $storedPath = $file->store('budget-circulars', 'local');
        $fullPath = Storage::disk('local')->path($storedPath);

        $circular = BudgetCircular::create([
            'circular_no' => $validated['circular_no'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'effective_date' => $validated['effective_date'] ?? null,
            'source_type' => $sourceType,
            'source_file_path' => $fullPath,
            'original_filename' => $file->getClientOriginalName(),
            'status' => 'processing',
            'imported_by' => auth()->id(),
        ]);

        ProcessSalaryGradeImportJob::dispatch($circular->id);

        return redirect()
            ->route('salary-grades.review', $circular->id)
            ->with('success', 'File uploaded -- parsing the salary table now. Refresh this page in a few seconds if it still says "processing".');
    }

    /**
     * Side-by-side review of the parsed table before it goes live anywhere.
     * Staff can hand-correct any OCR misreads here.
     */
    public function review(BudgetCircular $budgetCircular)
    {
        $budgetCircular->load('salaryGrades');
        return view('salary-grades.review', ['circular' => $budgetCircular]);
    }

    /**
     * Save manual corrections to the parsed (not-yet-current) table.
     * Expects amounts[grade][step] = value from the review form.
     */
    public function update(Request $request, BudgetCircular $budgetCircular)
    {
        $validated = $request->validate([
            'circular_no' => 'nullable|string|max:50',
            'subject' => 'nullable|string|max:250',
            'effective_date' => 'nullable|date',
            'amounts' => 'required|array',
        ]);

        foreach ($validated['amounts'] as $grade => $steps) {
            foreach ($steps as $step => $amount) {
                if ($amount === '' || $amount === null) {
                    continue;
                }

                SalaryGrade::updateOrCreate(
                    [
                        'budget_circular_id' => $budgetCircular->id,
                        'grade' => (int) $grade,
                        'step' => (int) $step,
                    ],
                    ['amount' => (float) str_replace(',', '', $amount)]
                );
            }
        }

        $budgetCircular->update([
            'circular_no' => $validated['circular_no'] ?? $budgetCircular->circular_no,
            'subject' => $validated['subject'] ?? $budgetCircular->subject,
            'effective_date' => $validated['effective_date'] ?? $budgetCircular->effective_date,
        ]);

        return back()->with('success', 'Corrections saved.');
    }

    /**
     * Makes this circular's table the one everything else reads
     * (SalaryGrade::currentTableArray()). Unsets any previous current one.
     */
    public function confirm(BudgetCircular $budgetCircular)
    {
        if ($budgetCircular->status !== 'ready') {
            return back()->with('error', 'Only a fully-parsed import can be confirmed as current.');
        }

        BudgetCircular::where('is_current', true)->update(['is_current' => false]);

        $budgetCircular->update([
            'is_current' => true,
            'status' => 'applied',
        ]);

        return redirect()
            ->route('salary-grades.index')
            ->with('success', 'This salary schedule is now the active one used across the system.');
    }

    public function destroy(BudgetCircular $budgetCircular)
    {
        if ($budgetCircular->is_current) {
            return back()->with('error', 'Cannot delete the currently active salary schedule.');
        }

        if ($budgetCircular->source_file_path && file_exists($budgetCircular->source_file_path)) {
            @unlink($budgetCircular->source_file_path);
        }

        $budgetCircular->delete();

        return back()->with('success', 'Import deleted.');
    }
}
