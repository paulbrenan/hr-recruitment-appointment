@extends('layouts.auth')

@section('title', 'Applicant registration')
@section('brand', 'Applicant Portal — Registration')

@section('content')
<h5 class="mb-3">Create your account</h5>

@if ($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('portal.register.attempt') }}" method="POST">
    @csrf
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label small fw-medium">First name</label>
            <input type="text" name="first_name" class="form-control" value="{{ old('first_name') }}" required autofocus>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-medium">Middle name</label>
            <input type="text" name="middle_name" class="form-control" value="{{ old('middle_name') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-medium">Last name</label>
            <input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}" required>
        </div>
    </div>
    <div class="mb-3 mt-2">
        <label class="form-label small fw-medium">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-medium">Phone</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="e.g. +639171234567">
    </div>
    <div class="mb-3">
        <label class="form-label small fw-medium">Password</label>
        <input type="password" name="password" class="form-control" required>
        <div class="form-text" style="font-size: 0.72rem;">At least 8 characters.</div>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-medium">Confirm password</label>
        <input type="password" name="password_confirmation" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-hr-primary w-100">Create account</button>
</form>

<p class="text-center small text-muted mt-3 mb-0">
    Already have an account? <a href="{{ route('portal.login') }}">Sign in</a>
</p>
@endsection