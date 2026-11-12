<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    /** Admins get system-wide statistics; drivers/passengers get their own. */
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return view('dashboard.admin', [
                'stats' => $this->dashboard->adminStats(),
                'recentUsers' => $this->dashboard->recentUsers(),
                'recentBookings' => $this->dashboard->recentBookings(),
            ]);
        }

        return view('dashboard.user', ['stats' => $this->dashboard->userStats($user)]);
    }
}
