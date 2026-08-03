<?php

namespace App\Http\Controllers;

use App\Models\CarHrmpsbSignatory;
use Illuminate\Http\Request;

class CarHrmpsbSignatoryController extends Controller
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
        CarHrmpsbSignatory::create($request->validate($this->rules()));
        return back()->with('success', 'CAR HRMPSB signatory added.');
    }

    public function update(Request $request, CarHrmpsbSignatory $carHrmpsbSignatory)
    {
        $carHrmpsbSignatory->update($request->validate($this->rules()));
        return back()->with('success', 'CAR HRMPSB signatory updated.');
    }

    public function destroy(CarHrmpsbSignatory $carHrmpsbSignatory)
    {
        $carHrmpsbSignatory->delete();
        return back()->with('success', 'CAR HRMPSB signatory deleted.');
    }
}
