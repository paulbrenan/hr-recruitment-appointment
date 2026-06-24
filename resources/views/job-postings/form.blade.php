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
                    <input type="text" class="form-control" name="title" value="{{ old('title', $posting->title ?? '') }}" placeholder="e.g. Administrative Officer II">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Vacancies</label>
                    <input type="number" class="form-control" name="vacancies" value="{{ old('vacancies', $posting->vacancies ?? 1) }}" min="1">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-medium">Place of assignment</label>
                    <input type="text" class="form-control" name="place_of_assignment" value="{{ old('place_of_assignment', $posting->place_of_assignment ?? '') }}">
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
@endsection