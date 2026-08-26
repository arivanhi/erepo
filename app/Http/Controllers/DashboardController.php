<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\CapstoneProject;
use App\Models\ScriptProject;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $role = $user->role;

        // Fetch recent activities (combining capstones and scripts)
        $recentCapstones = CapstoneProject::with('user')->orderBy('created_at', 'desc')->take(3)->get()->map(function($item) {
            return [
                'id' => 'c_'.$item->id,
                'title' => $item->title,
                'type' => 'Capstone',
                'status' => $item->status,
                'time' => $item->created_at->diffForHumans(),
                'created_at' => $item->created_at
            ];
        });
        
        $recentScripts = ScriptProject::with('user')->orderBy('created_at', 'desc')->take(3)->get()->map(function($item) {
            return [
                'id' => 's_'.$item->id,
                'title' => $item->title,
                'type' => 'Script',
                'status' => $item->status,
                'time' => $item->created_at->diffForHumans(),
                'created_at' => $item->created_at
            ];
        });

        $recentActivities = $recentCapstones->concat($recentScripts)->sortByDesc('created_at')->take(5)->values();

        if ($role === 'mahasiswa') {
            $stats = [
                'uploaded' => CapstoneProject::where('user_id', $user->id)->count() + ScriptProject::where('user_id', $user->id)->count(),
                'pending' => CapstoneProject::where('user_id', $user->id)->where('status', 'pending')->count() + ScriptProject::where('user_id', $user->id)->where('status', 'pending')->count(),
                'approved' => CapstoneProject::where('user_id', $user->id)->where('status', 'approved')->count() + ScriptProject::where('user_id', $user->id)->where('status', 'approved')->count(),
            ];
            return Inertia::render('Student/Dashboard', [
                'stats' => $stats,
                'recentActivities' => $recentActivities
            ]);
        } elseif ($role === 'dosen' || $role === 'kaprodi') {
            $stats = [
                'uploaded' => CapstoneProject::count() + ScriptProject::count(),
                'pending' => CapstoneProject::where('status', 'pending')->count() + ScriptProject::where('status', 'pending')->count(),
                'approved' => CapstoneProject::where('status', 'approved')->count() + ScriptProject::where('status', 'approved')->count(),
            ];
            return Inertia::render(ucfirst($role).'/Dashboard', [
                'stats' => $stats,
                'recentActivities' => $recentActivities
            ]);
        }

        // Admin
        $stats = [
            'totalUsers' => User::count(),
            'totalCapstone' => CapstoneProject::count(),
            'totalScript' => ScriptProject::count(),
        ];
        
        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentActivities' => $recentActivities
        ]);
    }
}
