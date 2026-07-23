<?php

namespace App\Http\Controllers;

use App\Models\IERSignatory;
use App\Models\QualificationNoticeSignatory;

class SignatoriesPageController extends Controller
{
    public function index()
    {
        $ierSignatories = IERSignatory::orderBy('name')->get();
        $qualificationNoticeSignatories = QualificationNoticeSignatory::orderBy('name')->get();

        return view('signatories.index', compact('ierSignatories', 'qualificationNoticeSignatories'));
    }
}