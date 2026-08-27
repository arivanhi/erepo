<?php

namespace App\Http\Controllers;

use App\Models\CapstoneProject;
use App\Models\ScriptProject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RepositoryController extends Controller
{
    public function capstone()
    {
        $user = Auth::user();
        $projects = CapstoneProject::with('user', 'dosen')
            ->where(function($query) use ($user) {
                if ($user->role !== 'admin' && $user->role !== 'kaprodi') {
                    $query->where('status', 'approved')
                          ->orWhere('user_id', $user->id)
                          ->orWhere('dosen_id', $user->id);
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();
        $dosens = User::where('role', 'dosen')->get();
        return Inertia::render('Repository/Capstone', [
            'projects' => $projects,
            'dosens' => $dosens
        ]);
    }

    public function storeCapstone(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'academic_year' => 'required|string',
            'group_id' => 'required|string',
            'dosen_id' => 'required|exists:users,id',
            // 'document' => 'nullable|file|mimes:pdf|max:51200'
        ]);

        $project = new CapstoneProject($request->only('title', 'academic_year', 'group_id', 'dosen_id'));
        $project->user_id = Auth::id();
        $project->status = 'pending';

        if ($request->hasFile('document')) {
            $originalName = $request->file('document')->getClientOriginalName();
            $project->document_path = $request->file('document')->storeAs('upload/capston/' . $project->group_id, $originalName, 'public');
        }

        $project->save();

        return redirect()->back()->with('message', 'Capstone project uploaded successfully!');
    }

    public function script()
    {
        $user = Auth::user();
        $projects = ScriptProject::with('user', 'capstoneProject')
            ->where(function($query) use ($user) {
                if ($user->role !== 'admin' && $user->role !== 'kaprodi') {
                    $query->where('status', 'approved')
                          ->orWhere('user_id', $user->id)
                          ->orWhereHas('capstoneProject', function($q) use ($user) {
                              $q->where('dosen_id', $user->id);
                          });
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();
        $capstones = CapstoneProject::where('user_id', Auth::id())->get();
        return Inertia::render('Repository/Script', [
            'projects' => $projects,
            'capstones' => $capstones
        ]);
    }

    public function storeScript(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'capstone_project_id' => 'required|exists:capstone_projects,id',
            // 'archive' => 'nullable|file|mimes:zip,rar|max:51200'
        ]);

        $project = new ScriptProject($request->only('title', 'capstone_project_id'));
        $project->user_id = Auth::id();
        $project->status = 'pending';
        
        // Use the same academic year and group ID as the related capstone
        $capstone = CapstoneProject::find($request->capstone_project_id);
        $project->academic_year = $capstone->academic_year;
        $project->group_id = $capstone->group_id;

        if ($request->hasFile('archive')) {
            $originalName = $request->file('archive')->getClientOriginalName();
            $project->archive_path = $request->file('archive')->storeAs('upload/script/' . $project->group_id, $originalName, 'public');
        }

        $project->save();

        return redirect()->back()->with('message', 'Script uploaded successfully!');
    }

    public function approveCapstone($id)
    {
        $project = CapstoneProject::findOrFail($id);
        $project->status = 'approved';
        $project->save();
        return redirect()->back()->with('message', 'Capstone approved!');
    }

    public function deleteCapstone($id)
    {
        $project = CapstoneProject::findOrFail($id);
        $project->delete();
        return redirect()->back()->with('message', 'Capstone deleted!');
    }

    public function approveScript($id)
    {
        $project = ScriptProject::findOrFail($id);
        $project->status = 'approved';
        $project->save();
        return redirect()->back()->with('message', 'Script approved!');
    }

    public function deleteScript($id)
    {
        $project = ScriptProject::findOrFail($id);
        $project->delete();
        return redirect()->back()->with('message', 'Script deleted!');
    }

    public function downloadCapstone($id)
    {
        $project = CapstoneProject::findOrFail($id);
        if ($project->document_path && Storage::disk('public')->exists($project->document_path)) {
            return Storage::disk('public')->download($project->document_path);
        }
        return redirect()->back()->with('error', 'File not found.');
    }

    public function downloadScript($id)
    {
        $project = ScriptProject::findOrFail($id);
        if ($project->archive_path && Storage::disk('public')->exists($project->archive_path)) {
            return Storage::disk('public')->download($project->archive_path);
        }
        return redirect()->back()->with('error', 'File not found.');
    }
}
