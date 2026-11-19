<?php

namespace App\Http\Controllers;

use App\Models\CapstoneTeam;
use App\Models\User;
use App\Services\RepositoryNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CapstoneTeamController extends Controller
{
    public function __construct(
        private readonly RepositoryNotificationService $notifier
    ) {}

    private function ensureCoordinatorOrAdmin(): void
    {
        $role = auth()->user()?->role;
        abort_unless(
            in_array($role, ['admin', 'koordinator_ta'], true),
            403,
            'Hanya Koordinator TA dan Admin yang dapat mengelola Tim Capstone.'
        );
    }

    /**
     * Resolve existing + inline-created student accounts for a Capstone Team.
     *
     * @return array<int, int>
     */
    private function resolveTeamStudentIds(Request $request): array
    {
        $existingIds = collect($request->input('member_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $validIds = $existingIds->isNotEmpty()
            ? User::whereIn('id', $existingIds)
                ->where('role', 'mahasiswa')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $newMembers = $request->input('new_members', []);
        if (is_array($newMembers)) {
            foreach ($newMembers as $nm) {
                if (! is_array($nm)) {
                    continue;
                }
                $email = strtolower(trim((string) ($nm['email'] ?? '')));
                $name = trim((string) ($nm['name'] ?? ''));
                $nim = trim((string) ($nm['identity_number'] ?? ''));

                if ($email === '' || $name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    continue;
                }

                $student = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'identity_number' => $nim !== '' ? $nim : null,
                        'role' => 'mahasiswa',
                        'password' => Hash::make(Str::random(24)),
                    ]
                );

                if ($student->role === 'mahasiswa') {
                    $validIds[] = (int) $student->id;
                }
            }
        }

        return array_values(array_unique($validIds));
    }

    public function index(Request $request)
    {
        $this->ensureCoordinatorOrAdmin();

        $search = trim((string) $request->input('search', ''));
        $query = CapstoneTeam::with(['dosen:id,name,email', 'members:id,name,email,identity_number'])
            ->withCount('capstoneProjects');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('group_code', 'like', "%{$search}%")
                  ->orWhere('team_name', 'like', "%{$search}%")
                  ->orWhere('academic_year', 'like', "%{$search}%")
                  ->orWhereHas('members', fn ($mq) => $mq->where('name', 'like', "%{$search}%")->orWhere('identity_number', 'like', "%{$search}%"));
            });
        }

        $teams = $query
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (CapstoneTeam $team) => [
                'id' => $team->id,
                'group_code' => $team->group_code,
                'team_name' => $team->team_name,
                'academic_year' => $team->academic_year,
                'dosen_id' => $team->dosen_id,
                'dosen' => $team->dosen,
                'members' => $team->members,
                'capstone_projects_count' => $team->capstone_projects_count,
                'has_active_token' => $team->hasActiveToken(),
                'token_expires_formatted' => $team->login_token_expires_at
                    ? $team->login_token_expires_at->timezone('Asia/Jakarta')->format('d M Y H:i')
                    : null,
            ]);

        $dosens = User::whereIn('role', ['dosen', 'kaprodi'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'identity_number']);

        $mahasiswas = User::where('role', 'mahasiswa')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'identity_number']);

        return Inertia::render('Admin/CapstoneTeams', [
            'teams' => $teams,
            'dosens' => $dosens,
            'mahasiswas' => $mahasiswas,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureCoordinatorOrAdmin();

        $normalizedCode = strtoupper(trim((string) $request->input('group_code', '')));
        $request->merge(['group_code' => $normalizedCode]);

        $validated = $request->validate([
            'group_code' => ['required', 'string', 'max:80', 'unique:capstone_teams,group_code'],
            'team_name' => ['nullable', 'string', 'max:255'],
            'academic_year' => ['required', 'string', 'max:50'],
            'dosen_id' => ['required', 'exists:users,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'new_members' => ['nullable', 'array'],
            'new_members.*.name' => ['required_with:new_members', 'string', 'max:255'],
            'new_members.*.email' => ['required_with:new_members', 'email', 'max:255'],
            'new_members.*.identity_number' => ['nullable', 'string', 'max:100'],
            'send_token_now' => ['nullable', 'boolean'],
        ], [
            'group_code.unique' => 'Kode Capstone tersebut sudah terdaftar.',
        ]);

        $studentIds = $this->resolveTeamStudentIds($request);
        if (empty($studentIds)) {
            return redirect()->back()->withErrors([
                'member_ids' => 'Pilih minimal 1 mahasiswa anggota kelompok (atau tambahkan mahasiswa baru).',
            ])->withInput();
        }

        $team = CapstoneTeam::create([
            'group_code' => $validated['group_code'],
            'team_name' => $validated['team_name'] ?? null,
            'academic_year' => $validated['academic_year'],
            'dosen_id' => $validated['dosen_id'],
            'lead_user_id' => $studentIds[0],
            'created_by' => auth()->id(),
        ]);

        $team->members()->sync($studentIds);

        if ($request->boolean('send_token_now', true)) {
            $plainToken = $team->ensureActiveToken();
            $this->notifier->sendCapstoneTeamLoginToken($team, $plainToken);

            return redirect()->back()->with(
                'message',
                "Tim Capstone {$team->group_code} berhasil dibuat & Token Login (aktif 1 bulan) telah dikirim ke email anggota!"
            );
        }

        return redirect()->back()->with('message', "Tim Capstone {$team->group_code} berhasil dibuat!");
    }

    public function update(Request $request, $id)
    {
        $this->ensureCoordinatorOrAdmin();

        $team = CapstoneTeam::with('capstoneProjects')->findOrFail($id);
        $normalizedCode = strtoupper(trim((string) $request->input('group_code', '')));
        $request->merge(['group_code' => $normalizedCode]);

        $validated = $request->validate([
            'group_code' => ['required', 'string', 'max:80', Rule::unique('capstone_teams', 'group_code')->ignore($team->id)],
            'team_name' => ['nullable', 'string', 'max:255'],
            'academic_year' => ['required', 'string', 'max:50'],
            'dosen_id' => ['required', 'exists:users,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'new_members' => ['nullable', 'array'],
            'new_members.*.name' => ['required_with:new_members', 'string', 'max:255'],
            'new_members.*.email' => ['required_with:new_members', 'email', 'max:255'],
            'new_members.*.identity_number' => ['nullable', 'string', 'max:100'],
        ]);

        $studentIds = $this->resolveTeamStudentIds($request);
        if (empty($studentIds)) {
            return redirect()->back()->withErrors([
                'member_ids' => 'Tim Capstone wajib memiliki minimal 1 mahasiswa anggota.',
            ]);
        }

        $team->update([
            'group_code' => $validated['group_code'],
            'team_name' => $validated['team_name'] ?? null,
            'academic_year' => $validated['academic_year'],
            'dosen_id' => $validated['dosen_id'],
            'lead_user_id' => $studentIds[0],
        ]);

        $team->members()->sync($studentIds);

        // Sync metadata & members to linked CapstoneProject rows
        foreach ($team->capstoneProjects as $proj) {
            $proj->update([
                'group_id' => $team->group_code,
                'academic_year' => $team->academic_year,
                'dosen_id' => $team->dosen_id,
            ]);
            $proj->members()->sync($studentIds);
        }

        return redirect()->back()->with('message', "Tim Capstone {$team->group_code} berhasil diperbarui!");
    }

    public function sendToken(Request $request, $id)
    {
        $this->ensureCoordinatorOrAdmin();

        $team = CapstoneTeam::with('members')->findOrFail($id);
        $plainToken = $team->ensureActiveToken($request->boolean('regenerate', false));
        $sentCount = $this->notifier->sendCapstoneTeamLoginToken($team, $plainToken);

        return redirect()->back()->with(
            'message',
            "Token Login 1 bulan untuk tim {$team->group_code} berhasil dikirim ke {$sentCount} email anggota!"
        );
    }

    public function destroy($id)
    {
        $this->ensureCoordinatorOrAdmin();

        $team = CapstoneTeam::findOrFail($id);
        $code = $team->group_code;
        $team->delete();

        return redirect()->back()->with('message', "Tim Capstone {$code} berhasil dihapus.");
    }
}
