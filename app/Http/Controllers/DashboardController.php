<?php

namespace App\Http\Controllers;

use App\Models\CapstoneProject;
use App\Models\ScriptProject;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends Controller
{
    private function formatCapstoneAuthors(CapstoneProject $item): string
    {
        if ($item->relationLoaded('members') && $item->members->isNotEmpty()) {
            return $item->members->pluck('name')->join(', ');
        }

        return $item->user?->name ?? 'Mahasiswa';
    }

    private function formatScriptAuthors(ScriptProject $item): string
    {
        if (
            $item->relationLoaded('capstoneProject')
            && $item->capstoneProject
            && $item->capstoneProject->relationLoaded('members')
            && $item->capstoneProject->members->isNotEmpty()
        ) {
            return $item->capstoneProject->members->pluck('name')->join(', ');
        }

        return $item->user?->name ?? 'Mahasiswa';
    }

    public function index()
    {
        $user = auth()->user();
        $role = $user->role;

        if ($role === 'mahasiswa') {
            $capstoneBase = CapstoneProject::with(['user', 'members', 'reviewer'])
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhereHas('members', fn ($mq) => $mq->where('users.id', $user->id));
                });

            $scriptBase = ScriptProject::with(['user', 'capstoneProject.members', 'reviewer'])
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhereHas('capstoneProject', function ($cq) use ($user) {
                          $cq->where('user_id', $user->id)
                             ->orWhereHas('members', fn ($mq) => $mq->where('users.id', $user->id));
                      });
                });

            $stats = [
                'uploaded' => (clone $capstoneBase)->count() + (clone $scriptBase)->count(),
                'pending' => (clone $capstoneBase)->whereIn('status', ['pending', 'checking'])->count()
                    + (clone $scriptBase)->where('status', 'pending')->count(),
                'approved' => (clone $capstoneBase)->where('status', 'approved')->count()
                    + (clone $scriptBase)->where('status', 'approved')->count(),
                'rejected' => (clone $capstoneBase)->where('status', 'rejected')->count()
                    + (clone $scriptBase)->where('status', 'rejected')->count(),
            ];

            $studentRecentCapstones = (clone $capstoneBase)->orderBy('updated_at', 'desc')->take(4)->get()->map(function (CapstoneProject $item) {
                $authors = $this->formatCapstoneAuthors($item);
                return [
                    'id' => 'c_' . $item->id,
                    'title' => $item->title,
                    'type' => 'Capstone',
                    'status' => $item->status,
                    'ai_summary' => $item->ai_check_result['summary'] ?? null,
                    'missing_items' => $item->ai_check_result['missing_items'] ?? [],
                    'revision_note' => $item->revision_note,
                    'reviewer_role' => $item->reviewer_role,
                    'reviewer_name' => $item->reviewer?->name,
                    'author_names' => $authors,
                    'user' => ['name' => $authors],
                    'time' => $item->updated_at->diffForHumans(),
                    'created_at' => $item->updated_at,
                ];
            });

            $studentRecentScripts = (clone $scriptBase)->orderBy('updated_at', 'desc')->take(4)->get()->map(function (ScriptProject $item) {
                $authors = $this->formatScriptAuthors($item);
                return [
                    'id' => 's_' . $item->id,
                    'title' => $item->title,
                    'type' => 'Script',
                    'status' => $item->status,
                    'revision_note' => $item->revision_note,
                    'reviewer_role' => $item->reviewer_role,
                    'reviewer_name' => $item->reviewer?->name,
                    'author_names' => $authors,
                    'user' => ['name' => $authors],
                    'time' => $item->updated_at->diffForHumans(),
                    'created_at' => $item->updated_at,
                ];
            });

            $recentActivities = $studentRecentCapstones
                ->concat($studentRecentScripts)
                ->sortByDesc('created_at')
                ->take(5)
                ->values();

            return Inertia::render('Student/Dashboard', [
                'stats' => $stats,
                'recentActivities' => $recentActivities,
            ]);
        }

        // Fetch global recent activities for Admin, Koordinator TA, Dosen, Kaprodi
        $recentCapstones = CapstoneProject::with(['user', 'members'])
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get()
            ->map(function (CapstoneProject $item) {
                $authors = $this->formatCapstoneAuthors($item);
                return [
                    'id' => 'c_' . $item->id,
                    'title' => $item->title,
                    'type' => 'Capstone',
                    'status' => $item->status,
                    'author_names' => $authors,
                    'user' => ['name' => $authors],
                    'time' => $item->created_at->diffForHumans(),
                    'created_at' => $item->created_at,
                ];
            });

        $recentScripts = ScriptProject::with(['user', 'capstoneProject.members'])
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get()
            ->map(function (ScriptProject $item) {
                $authors = $this->formatScriptAuthors($item);
                return [
                    'id' => 's_' . $item->id,
                    'title' => $item->title,
                    'type' => 'Script',
                    'status' => $item->status,
                    'author_names' => $authors,
                    'user' => ['name' => $authors],
                    'time' => $item->created_at->diffForHumans(),
                    'created_at' => $item->created_at,
                ];
            });

        $recentActivities = $recentCapstones
            ->concat($recentScripts)
            ->sortByDesc('created_at')
            ->take(5)
            ->values();

        if ($role === 'dosen' || $role === 'kaprodi') {
            $stats = [
                'uploaded' => CapstoneProject::count() + ScriptProject::count(),
                'pending' => CapstoneProject::where('status', 'pending')->count() + ScriptProject::where('status', 'pending')->count(),
                'approved' => CapstoneProject::where('status', 'approved')->count() + ScriptProject::where('status', 'approved')->count(),
            ];

            return Inertia::render(ucfirst($role) . '/Dashboard', [
                'stats' => $stats,
                'recentActivities' => $recentActivities,
            ]);
        }

        // Admin & Koordinator TA
        $stats = [
            'totalUsers' => User::count(),
            'totalCapstone' => CapstoneProject::count(),
            'totalScript' => ScriptProject::count(),
            'pendingReview' => CapstoneProject::where('status', 'pending')->count() + ScriptProject::where('status', 'pending')->count(),
            'heldCapstone' => CapstoneProject::where('status', 'rejected')->count() + ScriptProject::where('status', 'rejected')->count(),
        ];

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentActivities' => $recentActivities,
        ]);
    }
}
