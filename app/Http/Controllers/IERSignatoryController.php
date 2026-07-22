<?php

namespace App\Http\Controllers;

use App\Models\IERSignatory;
use Illuminate\Http\Request;

class IERSignatoryController extends Controller
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
        IERSignatory::create($request->validate($this->rules()));
        return back()->with('success', 'IER signatory added.');
    }

    public function update(Request $request, IERSignatory $ierSignatory)
    {
        $ierSignatory->update($request->validate($this->rules()));
        return back()->with('success', 'IER signatory updated.');
    }

    public function destroy(IERSignatory $ierSignatory)
    {
        $ierSignatory->delete();
        return back()->with('success', 'IER signatory deleted.');
    }
}