@extends('layouts.auth')

@section('title', 'Staff registration')
@section('brand', 'HR Recruitment — Staff Registration')

@section('content')
<h5 class="mb-3">Create staff account</h5>

@if ($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('register.attempt') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label class="form-label small fw-medium">Full name</label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required autofocus>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-medium">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
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
    Already have an account? <a href="{{ route('login') }}">Sign in</a>
</p>
@endsection