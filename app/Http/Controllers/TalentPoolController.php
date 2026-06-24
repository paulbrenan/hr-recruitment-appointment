<?php

namespace App\Http\Controllers;

class TalentPoolController extends Controller
{
    public function index()
    {
        $pool = collect([
            (object)['candidate_name' => 'Pedro Garcia', 'candidate_email' => 'pedro.garcia@email.com', 'tags' => 'Records, Data Entry', 'notes' => 'Strong filing system experience, good fit for future records openings.', 'added_at' => '2026-05-01'],
            (object)['candidate_name' => 'Carmen Lopez', 'candidate_email' => 'carmen.lopez@email.com', 'tags' => 'IT, Networking', 'notes' => 'Solid technical interview, no current opening matched.', 'added_at' => '2026-04-18'],
            (object)['candidate_name' => 'Ramon Torres', 'candidate_email' => 'ramon.torres@email.com', 'tags' => 'Finance, Budgeting', 'notes' => 'Reserve candidate for accounting section.', 'added_at' => '2026-03-30'],
        ]);

        return view('talent-pool.index', compact('pool'));
    }
}