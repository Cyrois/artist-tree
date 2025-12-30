<?php

namespace App\Http\Controllers;

use App\Http\Resources\LineupResource;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $lineups = $request->user()
            ->lineups()
            ->with(['artists' => function ($query) {
                // Eager load artists for preview, maybe limit if possible per lineup?
                // For now, load them all, it's MVP. 
                // In production with many artists, we'd use a subquery or specific relation for preview.
                $query->orderByPivot('tier'); // customized order?
            }])
            ->latest('updated_at')
            ->take(6)
            ->get();

        return Inertia::render('Dashboard', [
            'lineups' => LineupResource::collection($lineups),
        ]);
    }
}
