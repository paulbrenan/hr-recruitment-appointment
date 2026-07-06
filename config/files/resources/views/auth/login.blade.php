@extends('layouts.auth')

@section('title', 'Staff login')
@section('brand', 'HR Recruitment — Staff Login')

@section('content')
<h5 class="mb-3">Staff sign in</h5>

@if ($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('status'))
    <div class="alert alert-success small">{{ session('status') }}</div>
@endif

<form action="{{ route('login.attempt') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label class="form-label small fw-medium">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-medium">Password</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="remember" id="remember">
        <label class="form-check-label small" for="remember">Remember me</label>
    </div>
    <button type="submit" class="btn btn-hr-primary w-100">Sign in</button>
</form>

<p class="text-center small text-muted mt-3 mb-0">
    Don't have an account? <a href="{{ route('register') }}">Register</a>
</p>
@endsection