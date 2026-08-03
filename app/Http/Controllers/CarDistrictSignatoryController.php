<?php

namespace App\Http\Controllers;

use App\Models\CarDistrictSignatory;
use Illuminate\Http\Request;

class CarDistrictSignatoryController extends Controller
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
        CarDistrictSignatory::create($request->validate($this->rules()));
        return back()->with('success', 'CAR District Sub-Committee signatory added.');
    }

    public function update(Request $request, CarDistrictSignatory $carDistrictSignatory)
    {
        $carDistrictSignatory->update($request->validate($this->rules()));
        return back()->with('success', 'CAR District Sub-Committee signatory updated.');
    }

    public function destroy(CarDistrictSignatory $carDistrictSignatory)
    {
        $carDistrictSignatory->delete();
        return back()->with('success', 'CAR District Sub-Committee signatory deleted.');
    }
}
