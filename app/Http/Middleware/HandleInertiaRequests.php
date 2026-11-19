<?php

namespace App\Http\Middleware;

use App\Models\CapstoneTeam;
use App\Models\PublicDownloadRequest;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $activeTeam = null;
        $pendingDownloadRequestsCount = 0;

        if ($user && $user->role === 'mahasiswa') {
            try {
                $sessionTeamId = $request->session()->get('active_capstone_team_id');
                if ($sessionTeamId) {
                    $activeTeam = CapstoneTeam::with(['dosen:id,name,email', 'members:id,name,email,identity_number'])
                        ->find($sessionTeamId);
                }

                if (! $activeTeam) {
                    $activeTeam = $user->capstoneTeams()
                        ->with(['dosen:id,name,email', 'members:id,name,email,identity_number'])
                        ->latest('capstone_teams.id')
                        ->first();
                }
            } catch (\Throwable) {
                $activeTeam = null;
            }
        }

        if ($user && in_array($user->role, ['admin', 'koordinator_ta', 'dosen', 'kaprodi'], true)) {
            try {
                $q = PublicDownloadRequest::where('type', 'script_archive')->where('status', 'pending');
                if ($user->role === 'dosen') {
                    $q->whereHas('scriptProject.capstoneProject', fn ($cq) => $cq->where('dosen_id', $user->id));
                }
                $pendingDownloadRequestsCount = $q->count();
            } catch (\Throwable) {
                $pendingDownloadRequestsCount = 0;
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'capstoneTeam' => $activeTeam,
                'pendingDownloadRequestsCount' => $pendingDownloadRequestsCount,
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
