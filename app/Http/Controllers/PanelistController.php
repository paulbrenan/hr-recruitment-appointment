<?php

namespace App\Http\Controllers;

use App\Models\Panelist;
use Illuminate\Http\Request;

class PanelistController extends Controller
{
    /**
     * Rename a panelist (called via AJAX or form — currently via form redirect back).
     */
    public function update(Request $request, $id)
    {
        $panelist = Panelist::findOrFail($id);
        $request->validate(['name' => 'required|string|max:255']);
        $panelist->update(['name' => $request->input('name')]);

        // Respond with JSON when called via AJAX (inline edit),
        // or redirect when called via a regular form submit.
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['ok' => true, 'name' => $panelist->name]);
        }

        return redirect()->back()->with('success', 'Panelist name updated.');
    }

    /**
     * Delete a panelist from the global pool.
     * The cascade on job_posting_panelist will remove pivot rows automatically.
     */
    public function destroy($id)
    {
        $panelist = Panelist::findOrFail($id);
        $panelist->delete();

        return redirect()->back()->with('success', 'Panelist removed.');
    }
}