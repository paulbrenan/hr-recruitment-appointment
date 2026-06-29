@extends('layouts.auth')

@section('title', 'Applicant Dashboard')
@section('brand', 'Applicant Portal')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h5 class="mb-0">Welcome, {{ auth()->guard('candidate')->user()->full_name }}</h5>
        <small class="text-muted">{{ auth()->guard('candidate')->user()->email }}</small>
    </div>
    <form action="{{ route('portal.logout') }}" method="POST" class="mb-0">
        @csrf
        <button type="submit" class="btn btn-outline-secondary btn-sm">Log out</button>
    </form>
</div>

<hr>

<div class="d-grid gap-2">
    <a href="{{ route('portal.jobs.index') }}" class="btn btn-hr-primary">
        <i class="bi bi-briefcase me-1"></i> Browse Open Positions
    </a>
    <a href="{{ route('portal.my-applications') }}" class="btn btn-outline-secondary">
        <i class="bi bi-file-earmark-text me-1"></i> My Applications
    </a>
</div>
@endsection