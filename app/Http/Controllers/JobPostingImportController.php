<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Smalot\PdfParser\Parser as PdfParser;

class JobPostingImportController extends Controller
{
    /**
     * Stage 2 of PDF import: show the upload form.
     */
    public function create()
    {
        return view('job-postings.import.upload');
    }

    /**
     * Stage 2 of PDF import: accept the uploaded PDF, extract raw text via
     * smalot/pdfparser, and display it for inspection. No parsing into
     * structured postings yet -- that's Stage 3.
     */
    public function extract(Request $request)
    {
        $request->validate([
            'pdf_file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $uploadedFile = $request->file('pdf_file');
        $originalName = $uploadedFile->getClientOriginalName();

        $parser = new PdfParser();

        try {
            $pdf = $parser->parseFile($uploadedFile->getPathname());
            $rawText = $pdf->getText();
        } catch (\Exception $e) {
            return back()->withErrors([
                'pdf_file' => 'Could not read this PDF: ' . $e->getMessage(),
            ]);
        }

        $pages = $pdf->getPages();
        $pageCount = count($pages);

        $pageTexts = [];
        foreach ($pages as $index => $page) {
            $pageTexts[] = [
                'number' => $index + 1,
                'text' => $page->getText(),
            ];
        }

        return view('job-postings.import.extracted', [
            'originalName' => $originalName,
            'rawText' => $rawText,
            'pageCount' => $pageCount,
            'pageTexts' => $pageTexts,
        ]);
    }
}