<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AccountController extends Controller
{
    private function ensureAdmin(): void
    {
        abort_unless(
            auth()->user()?->role === 'admin',
            403,
            'Akses ditolak. Hanya Admin yang dapat mengelola akun pengguna.'
        );
    }

    public function index(Request $request)
    {
        $this->ensureAdmin();

        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('identity_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $allowedSorts = ['name', 'role', 'identity_number', 'created_at'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $users = $query->paginate(8)->onEachSide(1)->withQueryString();

        return Inertia::render('Admin/AssignAccount', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'sort_by', 'sort_dir']),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:admin,mahasiswa,dosen,kaprodi,koordinator_ta',
            'nim_npp' => 'required|string|max:255',
        ]);

        User::firstOrCreate(
            ['email' => $request->email],
            [
                'name' => $request->nama_lengkap,
                'role' => $request->role,
                'identity_number' => $request->nim_npp,
                'password' => Hash::make('knowledgebank123'),
            ]
        );

        return redirect()->back()->with('message', 'Account created successfully!');
    }

    public function import(Request $request)
    {
        $this->ensureAdmin();

        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx,xls',
        ]);

        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\UsersImport, $request->file('file'));

        return redirect()->back()->with('message', 'Accounts imported successfully!');
    }

    public function downloadTemplate()
    {
        $this->ensureAdmin();

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\UsersTemplateExport, 'template_akun.xlsx');
    }

    public function update(Request $request, $id)
    {
        $this->ensureAdmin();

        $user = User::findOrFail($id);

        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => 'required|in:admin,mahasiswa,dosen,kaprodi,koordinator_ta',
            'nim_npp' => 'required|string|max:255',
        ]);

        $user->update([
            'name' => $request->nama_lengkap,
            'email' => $request->email,
            'role' => $request->role,
            'identity_number' => $request->nim_npp,
        ]);

        return redirect()->back()->with('message', 'Account updated successfully!');
    }

    public function destroy($id)
    {
        $this->ensureAdmin();

        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->back()->with('message', 'Account deleted successfully!');
    }
}
