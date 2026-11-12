@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'My Dashboard')

@section('content')
    @php
        $labels = [
            'my_vehicles' => ['My Vehicles', 'bi-car-front-fill', 'secondary'],
            'my_rides' => ['My Rides', 'bi-signpost-split-fill', 'primary'],
            'my_active_rides' => ['Active Rides', 'bi-lightning-charge-fill', 'warning'],
            'my_bookings' => ['My Bookings', 'bi-journal-check', 'success'],
            'my_pending_bookings' => ['Pending Bookings', 'bi-hourglass-split', 'danger'],
            'my_ride_requests' => ['My Ride Requests', 'bi-megaphone-fill', 'info'],
        ];
    @endphp

    <div class="alert alert-light border">
        Welcome back, <strong>{{ auth()->user()->name }}</strong>. Ride and booking features are coming in the next phase.
    </div>

    <div class="row g-3">
        @foreach ($stats as $key => $value)
            @php([$label, $icon, $color] = $labels[$key])
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-{{ $color }} bg-opacity-10 text-{{ $color }}"><i class="bi {{ $icon }}"></i></div>
                        <div>
                            <div class="stat-value">{{ number_format($value) }}</div>
                            <div class="stat-label">{{ $label }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
