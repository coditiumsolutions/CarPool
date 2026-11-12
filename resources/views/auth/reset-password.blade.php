@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
    <form method="POST" action="{{ route('password.update') }}" data-busy novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}"
                   class="form-control @error('email') is-invalid @enderror" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">New password</label>
            <input type="password" id="password" name="password"
                   class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">At least 8 characters, with letters and numbers.</div>
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">Reset password</button>
    </form>
@endsection
