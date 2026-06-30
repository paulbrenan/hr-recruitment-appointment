<?php

namespace App\Http\Controllers;

use App\Models\Pipeline;
use App\Models\TalentPool;
use App\Models\JobPosting;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function index()
    {
        $pipelines = Pipeline::with(['talentPool', 'jobPosting'])
            ->orderBy('stage')
            ->get()
            ->groupBy('stage');

        $stages = Pipeline::stages();

        return view('pipelines.index', compact('pipelines', 'stages'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'talent_pool_id' => 'required|exists:talent_pools,id',
            'job_posting_id' => 'required|exists:job_postings,id',
        ]);

        $exists = Pipeline::where('talent_pool_id', $request->talent_pool_id)
            ->where('job_posting_id', $request->job_posting_id)
            ->exists();

        if ($exists) {
            return back()->with('info', 'This candidate is already in a pipeline for that job posting.');
        }

        Pipeline::create([
            'talent_pool_id' => $request->talent_pool_id,
            'job_posting_id' => $request->job_posting_id,
            'stage'          => 'contacted',
        ]);

        return back()->with('success', 'Candidate added to pipeline.');
    }

    public function update(Request $request, $id)
    {
        $pipeline = Pipeline::findOrFail($id);

        $request->validate([
            'stage' => 'required|in:contacted,interested,interviewing,placed,dropped',
            'notes' => 'nullable|string',
        ]);

        $pipeline->update($request->only('stage', 'notes'));

        return back()->with('success', 'Pipeline updated.');
    }

    public function destroy($id)
    {
        Pipeline::findOrFail($id)->delete();
        return back()->with('success', 'Removed from pipeline.');
    }
}