@extends('layouts.auth')

@section('title', 'Sign in')

@section('content')
<div class="d-flex align-items-center gap-2 mb-1">
    <i class="bi bi-box-arrow-in-right" style="color: var(--blue); font-size: 1.1rem;"></i>
    <h5 class="mb-0 fw-bold" style="color: var(--text);">Sign in</h5>
</div>
<p class="small mb-3" style="color: var(--muted);">Access the HR Recruitment System dashboard.</p>

@if ($errors->any())
    <div class="alert alert-danger small py-2">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('status'))
    <div class="alert alert-success small py-2">{{ session('status') }}</div>
@endif

<form action="{{ route('login.attempt') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label class="form-label small fw-medium">Email</label>
        <div class="input-group">
            <span class="input-group-text bg-white" style="border-color: #c5d0e6;">
                <i class="bi bi-envelope" style="color: var(--muted); font-size: .85rem;"></i>
            </span>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-medium">Password</label>
        <div class="input-group">
            <span class="input-group-text bg-white" style="border-color: #c5d0e6;">
                <i class="bi bi-lock" style="color: var(--muted); font-size: .85rem;"></i>
            </span>
            <input type="password" name="password" class="form-control" required>
        </div>
    </div>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="remember" id="remember">
        <label class="form-check-label small" for="remember">Remember me</label>
    </div>
    <button type="submit" class="btn btn-hr-primary w-100">
        <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
    </button>
</form>
@endsection