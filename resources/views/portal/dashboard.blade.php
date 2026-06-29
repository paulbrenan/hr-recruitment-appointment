@extends('layouts.auth')

@section('title', 'Applicant dashboard')
@section('brand', 'Applicant Portal')

@section('content')
<h5 class="mb-2">Welcome, {{ auth()->guard('candidate')->user()->full_name }}</h5>
<p class="text-muted small mb-3">
    This is a placeholder landing page confirming the login/registration flow works end to end.
    Viewing open positions, applying, and tracking your application status will be built here next.
</p>
<form action="{{ route('portal.logout') }}" method="POST">
    @csrf
    <button type="submit" class="btn btn-outline-secondary btn-sm">Log out</button>
</form>
@endsection