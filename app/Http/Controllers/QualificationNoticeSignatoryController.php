<?php

namespace App\Http\Controllers;

use App\Models\QualificationNoticeSignatory;
use Illuminate\Http\Request;

class QualificationNoticeSignatoryController extends Controller
{
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
        ];
    }

    public function store(Request $request)
    {
        QualificationNoticeSignatory::create($request->validate($this->rules()));
        return back()->with('success', 'Qualification notice signatory added.');
    }

    public function update(Request $request, QualificationNoticeSignatory $qualificationNoticeSignatory)
    {
        $qualificationNoticeSignatory->update($request->validate($this->rules()));
        return back()->with('success', 'Qualification notice signatory updated.');
    }

    public function destroy(QualificationNoticeSignatory $qualificationNoticeSignatory)
    {
        $qualificationNoticeSignatory->delete();
        return back()->with('success', 'Qualification notice signatory deleted.');
    }
}