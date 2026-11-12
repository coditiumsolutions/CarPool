<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
@php($user = auth()->user())

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand"><i class="bi bi-car-front-fill"></i> Ride</div>
    <nav class="sidebar-nav nav flex-column">
        <div class="sidebar-heading">Main</div>
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        @if ($user->isAdmin())
            <div class="sidebar-heading">Management</div>
            <a class="nav-link disabled" href="#"><i class="bi bi-people"></i> Users</a>
            <a class="nav-link disabled" href="#"><i class="bi bi-car-front"></i> Vehicles</a>
            <a class="nav-link disabled" href="#"><i class="bi bi-signpost-split"></i> Rides</a>
            <a class="nav-link disabled" href="#"><i class="bi bi-megaphone"></i> Ride Requests</a>
            <a class="nav-link disabled" href="#"><i class="bi bi-journal-check"></i> Bookings</a>
            <a class="nav-link disabled" href="#"><i class="bi bi-cash-coin"></i> Payments</a>
            <div class="sidebar-heading">Insights</div>
            <a class="nav-link disabled" href="#"><i class="bi bi-bar-chart-line"></i> Reports</a>
        @else
            <div class="sidebar-heading">My Account</div>
            @if ($user->isDriver())
                <a class="nav-link disabled" href="#"><i class="bi bi-car-front"></i> My Vehicles</a>
                <a class="nav-link disabled" href="#"><i class="bi bi-signpost-split"></i> My Rides</a>
            @endif
            @if ($user->isPassenger())
                <a class="nav-link disabled" href="#"><i class="bi bi-megaphone"></i> My Requests</a>
                <a class="nav-link disabled" href="#"><i class="bi bi-journal-check"></i> My Bookings</a>
            @endif
        @endif
    </nav>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="main">
    <header class="topbar d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light d-lg-none" id="sidebarToggle" type="button" aria-label="Toggle navigation">
                <i class="bi bi-list fs-5"></i>
            </button>
            <h1 class="h5 mb-0">@yield('heading', 'Dashboard')</h1>
        </div>

        <div class="dropdown">
            <a class="d-flex align-items-center gap-2 text-decoration-none text-dark dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                <span class="d-none d-md-block text-start lh-sm">
                    <span class="d-block fw-semibold small">{{ $user->name }}</span>
                    <span class="d-block text-muted text-capitalize" style="font-size:.75rem">{{ $user->user_type }}</span>
                </span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    <main class="content">
        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show" data-autodismiss role="alert">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="footer">&copy; {{ date('Y') }} {{ config('app.name') }} &middot; All amounts in {{ config('currency.code') }}</footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
