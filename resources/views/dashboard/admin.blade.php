@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Admin Dashboard')

@section('content')
    @php
        $cards = [
            ['Total Users', $stats['total_users'], 'bi-people-fill', 'primary'],
            ['Total Drivers', $stats['total_drivers'], 'bi-person-badge-fill', 'info'],
            ['Total Passengers', $stats['total_passengers'], 'bi-person-walking', 'success'],
            ['Total Vehicles', $stats['total_vehicles'], 'bi-car-front-fill', 'secondary'],
            ['Total Rides', $stats['total_rides'], 'bi-signpost-split-fill', 'primary'],
            ['Active Rides', $stats['active_rides'], 'bi-lightning-charge-fill', 'warning'],
            ['Total Bookings', $stats['total_bookings'], 'bi-journal-check', 'success'],
            ['Pending Bookings', $stats['pending_bookings'], 'bi-hourglass-split', 'danger'],
        ];
    @endphp

    <div class="row g-3 mb-4">
        @foreach ($cards as [$label, $value, $icon, $color])
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

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Recent Users</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Name</th><th>Type</th><th>City</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($recentUsers as $u)
                            <tr>
                                <td>{{ $u->name }}<div class="text-muted small">{{ $u->email ?? $u->phone }}</div></td>
                                <td class="text-capitalize">{{ $u->user_type }}</td>
                                <td>{{ $u->city?->city_name ?? '-' }}</td>
                                <td><span class="badge text-bg-{{ $u->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($u->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No users yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Recent Bookings</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Passenger</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($recentBookings as $b)
                            <tr>
                                <td>{{ $b->id }}</td>
                                <td>{{ $b->passenger?->name }}</td>
                                <td>{{ \App\Support\Currency::format($b->total_amount) }}</td>
                                <td><span class="badge text-bg-{{ ['pending' => 'warning', 'confirmed' => 'primary', 'completed' => 'success', 'cancelled' => 'danger'][$b->booking_status] ?? 'secondary' }}">{{ ucfirst($b->booking_status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No bookings yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
