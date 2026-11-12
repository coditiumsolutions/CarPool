@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <p class="text-muted small">Enter the email address on your account and we will send you a link to reset your password.</p>

    <form method="POST" action="{{ route('password.email') }}" data-busy novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" autofocus required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">Send reset link</button>
        <div class="text-center mt-3"><a href="{{ route('login') }}" class="small"><i class="bi bi-arrow-left"></i> Back to sign in</a></div>
    </form>
@endsection
