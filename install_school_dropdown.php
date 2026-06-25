<?php
// install_school_dropdown.php
// Run once from your Laravel project root: php install_school_dropdown.php
// Replaces the free-text Place of assignment input on the Job Posting form with
// a searchable type-to-filter dropdown, populated from a starting list of 121
// real DepEd Cavite schools (compiled from actual Call for Applications memos).
// Unlike the title dropdown, this one allows typing a school NOT yet on the list
// (soft suggestion, not a hard restriction) since the list is known to be incomplete.
// Backs up files it overwrites to .bak before writing. Safe to delete after running.

$files = [];

$files['config/schools.php'] = <<<'PHPCODE'
<?php

// config/schools.php
// Master list of schools for the Place of Assignment searchable dropdown.
// This is a STARTING list compiled from actual DepEd Cavite Division Memoranda
// (Call for Applications attachments) and is known to be incomplete -- it does
// not yet cover every school in the division. Add new schools as you encounter
// them by appending a new line to the array below.

return [
    'schools' => [
        'Agus-os ES',
        'Alfonso Integrated High School',
        'Amadeo ES',
        'Amadeo Integrated School',
        'Amaya ES',
        'Amaya Sch. of Home Industries',
        'Amuyong Elementary School',
        'Antonio B. del Rosario Sr. Mem. ES',
        'Anuling Lejos Integrated High School',
        'Area J ES',
        'Bagbag National High School',
        'Bailen ES',
        'Banaba Cerca Integrated School',
        'Bancod ES',
        'Bendita Integrated High School',
        'Binakayan National High School',
        'Bucal I ES',
        'Bucal National High School - Sta. Mercedes Annex',
        'Bucal National Integrated School',
        'Buho ES',
        'Bulalo ES',
        'Bulalo ES - Annex',
        'Bulihan Integrated National High School',
        'Bulihan Sites & Services Project ES',
        'Cabangaan ES',
        'Cabulusan ES',
        'Caluangan ES',
        'Caluangan National High School',
        'Calumpang Lejos ES',
        'Carasuchi ES',
        'Carmen ES',
        'Cavite Science Integrated School',
        'Constancio E. Aure Sr. National High School',
        'Dao ES',
        'Emilia Ambalada Poblete Integrated High School',
        'Emiliano T. Tirona Memorial National High School',
        'Feliciano Cabuco ES',
        'Francisco Osorio National High School',
        'Francisco P. Tolentino Integrated High School',
        'Galicia ES',
        'Gen. Emilio Aguinaldo-Bailen Integrated School',
        'Gen. Mariano Alvarez Tech. High School',
        'Guitasin PS',
        'Guyung-Guyong ES',
        'Halang Banaybanay NHS',
        'Harasan ES',
        'Hugo Perez ES',
        'Indang Integrated National High School',
        'Kaingen-Poblacion ES',
        'Kanggahan ES',
        'Kaong ES',
        'Kaong National High School',
        'Kaymisas ES',
        'Kaypaaba ES',
        'Kaytambog ES',
        'Kaytitinga Integrated School',
        'Layong Mabilog ES',
        'Lucsuhin Integrated School',
        'Luis Aguado National High School',
        'Lumampong Balagbag ES',
        'Lumampong INHS',
        'Lumampong Integrated National High School',
        'Lumil Integrated National High School',
        'Lumipa ES',
        'Mahabang Kahoy Lejos ES',
        'Malabag National High School',
        'Malainen Bago Integrated School',
        'Maragondon National High School',
        'Marahan ES',
        'Marcelo D. Samaniego ES (Bucal IV ES)',
        'Mataas Na Burol ES (Burol ES)',
        'Matagbak ES',
        'Medina ES',
        'Mendez-Nuñez National High School',
        'Munting Ilog Integrated National High School',
        'Naic Coastal Integrated National High School',
        'Naic ES',
        'Naic Integrated National High School',
        'Naic Senior High School Stand-Alone',
        'Narvaez ES',
        'Noveleta National High School',
        'Noveleta Senior High School',
        'Pacheco ES',
        'Paligawan ES',
        'Palocpoc ES',
        'Pangil NHS',
        'Pantihan II ES',
        'Petronilo L. Torres MES',
        'Pulo ni Sara ES',
        'Pulo ni Sara Integrated School',
        'Pulong Bunga ES',
        'Pulong Saging ES',
        'Punta ES',
        'Puting Kahoy ES',
        'Ricardo Lejos Cortez ES',
        'Rosa G. Acuña Memorial ES',
        'Rosario National High School',
        'San Miguel ES',
        'Santiago L. Angue, Sr. ES (formerly Mabato ES)',
        'Sicat ES',
        'Silang Central School',
        'Sta. Mercedes ES',
        'Tagaytay City National High School',
        'Tagaytay City Science National High School - Integrated Senior High School',
        'Talipusngo ES',
        'Talon Integrated School',
        'Tambo Balagbag ES',
        'Tanza NTS Annex',
        'Tanza National Comprehensive High School',
        'Tanza National Trade School',
        'Tatiao ES',
        'Taywanak ES',
        'Taywanak National High School',
        'Ternate Integrated National High School',
        'Ternate West National High School',
        'Timalan ES',
        'Timalan Hillsview Integrated School',
        'Trece Martires City National High School',
        'Tua ES',
        'Urdaneta ES',
        'Victoriano Luciano ES',
    ],
];
PHPCODE;

$files['resources/views/job-postings/form.blade.php'] = <<<'BLADE'
@extends('layouts.app')

@section('title', $posting->exists ?? false ? 'Edit posting' : 'New posting')
@section('page-title', ($posting->exists ?? false) ? 'Edit job posting' : 'New job posting')

@section('content')
<div class="card">
    <div class="card-body p-4">
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please fix the following:</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form action="{{ ($posting->exists ?? false) ? route('job-postings.update', $posting->id) : route('job-postings.store') }}" method="POST">
            @if ($posting->exists ?? false)
                @method('PUT')
            @endif
            @csrf
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label small fw-medium">Job title</label>
                    <div class="position-relative" id="titleSearchWrapper">
                        <input
                            type="text"
                            class="form-control"
                            id="titleSearchInput"
                            autocomplete="off"
                            placeholder="Type to search position titles..."
                            value="{{ old('title', $posting->title ?? '') }}"
                        >
                        <input type="hidden" name="title" id="titleHiddenInput" value="{{ old('title', $posting->title ?? '') }}">
                        <div
                            id="titleSearchResults"
                            class="list-group position-absolute w-100 shadow-sm"
                            style="z-index: 1050; max-height: 260px; overflow-y: auto; display: none; top: 100%;"
                        ></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Vacancies</label>
                    <input type="number" class="form-control" name="vacancies" value="{{ old('vacancies', $posting->vacancies ?? 1) }}" min="1">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-medium">Place of assignment</label>
                    <div class="position-relative" id="schoolSearchWrapper">
                        <input
                            type="text"
                            class="form-control"
                            id="schoolSearchInput"
                            name="place_of_assignment"
                            autocomplete="off"
                            placeholder="Type to search schools, or enter a new one..."
                            value="{{ old('place_of_assignment', $posting->place_of_assignment ?? '') }}"
                        >
                        <div
                            id="schoolSearchResults"
                            class="list-group position-absolute w-100 shadow-sm"
                            style="z-index: 1050; max-height: 220px; overflow-y: auto; display: none; top: 100%;"
                        ></div>
                        <div class="form-text" style="font-size: 0.72rem;">Pick from the list or type a school not yet listed.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-medium">Employment type</label>
                    <select class="form-select" name="employment_type">
                        @foreach (['Regular', 'Provisional', 'Casual', 'Job Order', 'On-the-Job Trainee'] as $type)
                            <option value="{{ $type }}" {{ ($posting->employment_type ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-medium">Job description</label>
                    <textarea class="form-control" name="description" rows="3">{{ $posting->description ?? '' }}</textarea>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-medium">Duties and responsibilities</label>
                    <textarea class="form-control" name="duties_responsibilities" rows="3">{{ $posting->duties_responsibilities ?? '' }}</textarea>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-medium">Qualification standards</label>
                    <textarea class="form-control" name="qualification_standards" rows="3">{{ $posting->qualification_standards ?? '' }}</textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-medium">Posted date</label>
                    <input type="date" class="form-control" name="posted_at" value="{{ old('posted_at', optional($posting->posted_at ?? null)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Closes</label>
                    <input type="date" class="form-control" name="closes_at" value="{{ old('closes_at', optional($posting->closes_at ?? null)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Status</label>
                    <select class="form-select" name="status">
                        @foreach (['draft', 'open', 'filled', 'closed'] as $status)
                            <option value="{{ $status }}" {{ ($posting->status ?? 'draft') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn" style="background-color: var(--hr-primary); color: #fff;">Save posting</button>
                <a href="{{ route('job-postings.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const titles = @json($jobTitles ?? []);
        const searchInput = document.getElementById('titleSearchInput');
        const hiddenInput = document.getElementById('titleHiddenInput');
        const resultsBox = document.getElementById('titleSearchResults');
        const wrapper = document.getElementById('titleSearchWrapper');

        function renderResults(filter) {
            const query = filter.trim().toLowerCase();
            const matches = query === ''
                ? titles
                : titles.filter(t => t.toLowerCase().includes(query));

            resultsBox.innerHTML = '';

            if (matches.length === 0) {
                resultsBox.style.display = 'none';
                return;
            }

            matches.slice(0, 50).forEach(function (title) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action small';
                item.textContent = title;
                item.addEventListener('click', function () {
                    searchInput.value = title;
                    hiddenInput.value = title;
                    resultsBox.style.display = 'none';
                    searchInput.classList.remove('is-invalid');
                });
                resultsBox.appendChild(item);
            });

            resultsBox.style.display = 'block';
        }

        searchInput.addEventListener('input', function () {
            hiddenInput.value = '';
            renderResults(searchInput.value);
        });

        searchInput.addEventListener('focus', function () {
            renderResults(searchInput.value);
        });

        document.addEventListener('click', function (event) {
            if (!wrapper.contains(event.target)) {
                resultsBox.style.display = 'none';
            }
        });

        // Block submission if the typed text doesn't match an exact, valid title.
        searchInput.closest('form').addEventListener('submit', function (event) {
            if (!titles.includes(searchInput.value.trim())) {
                event.preventDefault();
                searchInput.classList.add('is-invalid');
                searchInput.focus();
                renderResults(searchInput.value);

                let feedback = wrapper.querySelector('.invalid-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback d-block';
                    feedback.textContent = 'Please select a valid position title from the list.';
                    wrapper.appendChild(feedback);
                }
            } else {
                hiddenInput.value = searchInput.value.trim();
            }
        });
    })();

    (function () {
        const schools = @json(config('schools.schools', []));
        const schoolInput = document.getElementById('schoolSearchInput');
        const schoolResultsBox = document.getElementById('schoolSearchResults');
        const schoolWrapper = document.getElementById('schoolSearchWrapper');

        function renderSchoolResults(filter) {
            const query = filter.trim().toLowerCase();
            const matches = query === ''
                ? schools
                : schools.filter(s => s.toLowerCase().includes(query));

            schoolResultsBox.innerHTML = '';

            if (matches.length === 0) {
                schoolResultsBox.style.display = 'none';
                return;
            }

            matches.slice(0, 50).forEach(function (school) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action small';
                item.textContent = school;
                item.addEventListener('click', function () {
                    schoolInput.value = school;
                    schoolResultsBox.style.display = 'none';
                });
                schoolResultsBox.appendChild(item);
            });

            schoolResultsBox.style.display = 'block';
        }

        schoolInput.addEventListener('input', function () {
            renderSchoolResults(schoolInput.value);
        });

        schoolInput.addEventListener('focus', function () {
            renderSchoolResults(schoolInput.value);
        });

        document.addEventListener('click', function (event) {
            if (!schoolWrapper.contains(event.target)) {
                schoolResultsBox.style.display = 'none';
            }
        });
        // Note: no submit-blocking here -- typing a school not in the list is allowed.
    })();
</script>
@endpush
@endsection
BLADE;

$base = __DIR__;
$created = 0;

foreach ($files as $relativePath => $content) {
    $fullPath = $base . '/' . $relativePath;
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (file_exists($fullPath)) {
        copy($fullPath, $fullPath . '.bak');
        echo "BACKED UP: $relativePath -> " . basename($fullPath) . ".bak\n";
    }
    file_put_contents($fullPath, $content);
    echo "WROTE: $relativePath\n";
    $created++;
}

echo "\nDone. $created file(s) written.\n";
echo "Run 'php artisan config:clear' to make sure the new config file is picked up.\n";
echo "Then visit /job-postings/create and try typing a school name in Place of assignment.\n";
echo "Note: this list has 121 schools and is a STARTING point, not a complete master list.\n";
echo "Add more schools later by editing the 'schools' array in config/schools.php directly.\n";
