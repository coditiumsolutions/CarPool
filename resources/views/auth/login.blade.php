@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <form method="POST" action="{{ route('login.attempt') }}" data-busy novalidate>
        @csrf

        <div class="mb-3">
            <label for="login" class="form-label">Email or phone</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" id="login" name="login" value="{{ old('login') }}"
                       class="form-control @error('login') is-invalid @enderror" autocomplete="username" autofocus required>
                @error('login')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" id="password" name="password"
                       class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="text-end mb-3">
            <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">Sign in</button>
    </form>
@endsection
