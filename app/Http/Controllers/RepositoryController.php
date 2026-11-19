<?php

namespace App\Http\Controllers;

use App\Jobs\CheckCapstonePdfJob;
use App\Models\CapstoneProject;
use App\Models\CapstoneTeam;
use App\Models\ScriptProject;
use App\Models\User;
use App\Services\RepositoryNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class RepositoryController extends Controller
{
    public function __construct(
        private readonly RepositoryNotificationService $notifier
    ) {}

    private function hasFullRepositoryAccess(User $user): bool
    {
        return in_array($user->role, ['admin', 'koordinator_ta', 'kaprodi'], true);
    }

    private function resolveActiveTeamForStudent(Request $request, User $actor): ?CapstoneTeam
    {
        if ($actor->role !== 'mahasiswa') {
            return null;
        }

        $sessionTeamId = $request->session()->get('active_capstone_team_id');
        if ($sessionTeamId) {
            $team = CapstoneTeam::with('members')->find($sessionTeamId);
            if ($team) {
                return $team;
            }
        }

        return $actor->capstoneTeams()->with('members')->latest('capstone_teams.id')->first();
    }

    /**
     * @return array<int, int>
     */
    private function resolveMemberIds(Request $request, User $actor, ?CapstoneProject $existing = null, ?CapstoneTeam $team = null): array
    {
        if ($team && $team->members->isNotEmpty()) {
            return $team->members->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        }

        $submittedIds = collect($request->input('member_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $validStudentIds = $submittedIds->isNotEmpty()
            ? User::whereIn('id', $submittedIds)
                ->where('role', 'mahasiswa')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        if ($actor->role === 'mahasiswa' && ! in_array((int) $actor->id, $validStudentIds, true)) {
            array_unshift($validStudentIds, (int) $actor->id);
        }

        if (empty($validStudentIds)) {
            if ($existing && $existing->user_id) {
                $validStudentIds = [(int) $existing->user_id];
            } else {
                $validStudentIds = [(int) $actor->id];
            }
        }

        return array_values(array_unique($validStudentIds));
    }

    public function capstone()
    {
        /** @var User $user */
        $user = Auth::user();

        $projects = CapstoneProject::with(['user', 'dosen', 'members', 'reviewer'])
            ->where(function ($query) use ($user) {
                if (! $this->hasFullRepositoryAccess($user)) {
                    $query->where('status', 'approved')
                          ->orWhere('user_id', $user->id)
                          ->orWhere('dosen_id', $user->id)
                          ->orWhereHas('members', function ($q) use ($user) {
                              $q->where('users.id', $user->id);
                          });
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->onEachSide(1)
            ->withQueryString();

        $dosens = User::whereIn('role', ['dosen', 'kaprodi'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'identity_number']);

        $mahasiswas = User::where('role', 'mahasiswa')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'identity_number']);

        return Inertia::render('Repository/Capstone', [
            'projects' => $projects,
            'dosens' => $dosens,
            'mahasiswas' => $mahasiswas,
        ]);
    }

    public function storeCapstone(Request $request)
    {
        /** @var User $actor */
        $actor = Auth::user();
        $activeTeam = $this->resolveActiveTeamForStudent($request, $actor);

        if ($activeTeam) {
            $request->merge([
                'group_id' => $activeTeam->group_code,
                'academic_year' => $activeTeam->academic_year,
                'dosen_id' => $activeTeam->dosen_id ?? $request->input('dosen_id'),
            ]);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'academic_year' => 'required|string|max:50',
            'group_id' => 'required|string|max:100',
            'dosen_id' => 'required|exists:users,id',
            'document' => 'required|file|mimes:pdf|max:51200',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'integer|exists:users,id',
        ], [
            'document.required' => 'File PDF laporan wajib diunggah.',
            'document.mimes' => 'File laporan harus berformat PDF.',
            'document.max' => 'Ukuran file PDF maksimal 50 MB.',
        ]);

        $memberIds = $this->resolveMemberIds($request, $actor, null, $activeTeam);

        $project = new CapstoneProject($request->only('title', 'academic_year', 'group_id', 'dosen_id'));
        $project->capstone_team_id = $activeTeam?->id;
        $project->user_id = $actor->role === 'mahasiswa'
            ? $actor->id
            : ($memberIds[0] ?? $actor->id);
        $project->status = 'checking';
        $project->ai_check_status = 'checking';
        $project->ai_check_result = null;
        $project->ai_checked_at = null;

        if ($request->hasFile('document')) {
            $originalName = $request->file('document')->getClientOriginalName();
            $project->document_path = $request->file('document')->storeAs(
                'upload/capston/' . $project->group_id,
                $originalName,
                'public'
            );
        }

        $project->save();
        $project->members()->sync($memberIds);
        $project->load(['members', 'user', 'dosen']);

        $this->notifier->notifyStudentsUploadedChecking($project);

        CheckCapstonePdfJob::dispatch($project->id);

        $project->refresh();
        if ($project->status === 'rejected') {
            return redirect()->back()->with('error', 'Dokumen ditahan karena belum lengkap. Silakan periksa rincian kekurangan dan unggah ulang PDF.');
        }

        if ($project->status === 'pending') {
            return redirect()->back()->with('message', 'Dokumen lolos pemeriksaan kelengkapan AI dan menunggu persetujuan!');
        }

        return redirect()->back()->with('message', 'Dokumen berhasil diunggah! AI Qwen sedang memeriksa kelengkapan tanda tangan dan Daftar Isi...');
    }

    public function viewCapstone($id)
    {
        /** @var User $user */
        $user = Auth::user();
        $project = CapstoneProject::with('members')->findOrFail($id);

        $canView = $this->hasFullRepositoryAccess($user)
            || $project->status === 'approved'
            || (int) $project->dosen_id === (int) $user->id
            || $project->isMember((int) $user->id);

        abort_unless($canView, 403, 'Anda tidak memiliki akses untuk membuka dokumen ini.');
        abort_if(! $project->document_path || ! Storage::disk('public')->exists($project->document_path), 404, 'File PDF tidak ditemukan.');

        return response()->file(Storage::disk('public')->path($project->document_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($project->document_path) . '"',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive, nosnippet',
        ]);
    }

    public function script()
    {
        /** @var User $user */
        $user = Auth::user();

        $projects = ScriptProject::with(['user', 'capstoneProject.members', 'capstoneProject.dosen', 'reviewer'])
            ->where(function ($query) use ($user) {
                if (! $this->hasFullRepositoryAccess($user)) {
                    $query->where('status', 'approved')
                          ->orWhere('user_id', $user->id)
                          ->orWhereHas('capstoneProject', function ($q) use ($user) {
                              $q->where('dosen_id', $user->id)
                                ->orWhere('user_id', $user->id)
                                ->orWhereHas('members', function ($mq) use ($user) {
                                    $mq->where('users.id', $user->id);
                                });
                          });
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->onEachSide(1)
            ->withQueryString();

        if ($this->hasFullRepositoryAccess($user)) {
            $capstones = CapstoneProject::with('members')->orderBy('created_at', 'desc')->get();
        } else {
            $capstones = CapstoneProject::with('members')
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhere('dosen_id', $user->id)
                      ->orWhereHas('members', fn ($mq) => $mq->where('users.id', $user->id));
                })
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return Inertia::render('Repository/Script', [
            'projects' => $projects,
            'capstones' => $capstones,
        ]);
    }

    public function storeScript(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'capstone_project_id' => 'required|exists:capstone_projects,id',
            'archive' => 'nullable|file|max:51200',
        ]);

        $capstone = CapstoneProject::with(['dosen', 'members'])->findOrFail($request->capstone_project_id);

        $project = new ScriptProject($request->only('title', 'capstone_project_id'));
        $project->user_id = Auth::id();
        $project->status = 'pending';
        $project->academic_year = $capstone->academic_year;
        $project->group_id = $capstone->group_id;

        if ($request->hasFile('archive')) {
            $originalName = $request->file('archive')->getClientOriginalName();
            $project->archive_path = $request->file('archive')->storeAs(
                'upload/script/' . $project->group_id,
                $originalName,
                'public'
            );
        }

        $project->save();
        $project->load(['user', 'capstoneProject.members', 'capstoneProject.dosen']);

        $this->notifier->notifyStudentsPendingVerification($project, 'Script / Source Code');
        $this->notifier->notifyVerifiersNewSubmission($project, 'Script / Source Code');

        return redirect()->back()->with('message', 'Script uploaded successfully!');
    }

    public function approveCapstone($id)
    {
        /** @var User $user */
        $user = Auth::user();
        $project = CapstoneProject::with(['user', 'members'])->findOrFail($id);

        if (! $this->hasFullRepositoryAccess($user) && (int) $project->dosen_id !== (int) $user->id) {
            return redirect()->back()->with('error', 'Anda tidak memiliki akses untuk menyetujui dokumen ini.');
        }

        if ($project->status === 'checking') {
            return redirect()->back()->with('error', 'Dokumen masih sedang diperiksa oleh AI Qwen. Mohon tunggu hingga pemeriksaan selesai.');
        }

        if ($project->status === 'rejected') {
            return redirect()->back()->with('error', 'Dokumen sedang ditahan karena belum lengkap. Gunakan tombol "Terima dgn Catatan" (khusus Koordinator/Admin) atau tunggu mahasiswa upload ulang.');
        }

        $project->update([
            'status' => 'approved',
            'revision_note' => null,
            'reviewed_by' => $user->id,
            'reviewer_role' => $user->role,
            'reviewed_at' => now(),
        ]);

        $this->notifier->notifyStudentsApproved($project, 'Capstone Design', $user);

        return redirect()->back()->with('message', 'Capstone approved!');
    }

    /**
     * v1.2: Koordinator TA & Admin override to accept an AI-incomplete PDF with recorded note + email to Mahasiswa & Pembimbing.
     */
    public function acceptIncompleteCapstone(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! in_array($user->role, ['admin', 'koordinator_ta'], true)) {
            abort(403, 'Hanya Koordinator TA dan Admin yang dapat menerima dokumen belum lengkap dengan catatan.');
        }

        $project = CapstoneProject::with(['user', 'members', 'dosen'])->findOrFail($id);

        $validated = $request->validate([
            'acceptance_note' => 'required|string|max:2000',
        ], [
            'acceptance_note.required' => 'Catatan penerimaan wajib diisi untuk disimpan dan dikirim ke mahasiswa serta pembimbing.',
        ]);

        $note = trim($validated['acceptance_note']);

        $project->update([
            'status' => 'approved',
            'acceptance_note' => $note,
            'reviewed_by' => $user->id,
            'reviewer_role' => $user->role,
            'reviewed_at' => now(),
        ]);

        $this->notifier->notifyAcceptedWithNote($project, $user, $note);

        return redirect()->back()->with(
            'message',
            'Dokumen berhasil diterima dengan catatan! Catatan telah disimpan dan dikirim via email ke Mahasiswa & Dosen Pembimbing.'
        );
    }

    public function requestReuploadCapstone(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        $project = CapstoneProject::with(['user', 'members'])->findOrFail($id);

        if (! $this->hasFullRepositoryAccess($user) && (int) $project->dosen_id !== (int) $user->id) {
            return redirect()->back()->with('error', 'Anda tidak memiliki akses untuk meminta upload ulang dokumen ini.');
        }

        $validated = $request->validate([
            'revision_note' => 'required|string|max:2000',
        ], [
            'revision_note.required' => 'Catatan kekurangan / revisi wajib diisi agar mahasiswa mengetahui bagian yang harus diperbaiki.',
        ]);

        $project->update([
            'status' => 'rejected',
            'revision_note' => trim($validated['revision_note']),
            'acceptance_note' => null,
            'reviewed_by' => $user->id,
            'reviewer_role' => $user->role,
            'reviewed_at' => now(),
        ]);

        $this->notifier->notifyStudentsReviewerReuploadRequired(
            $project,
            'Capstone Design',
            $user,
            trim($validated['revision_note'])
        );

        return redirect()->back()->with('message', 'Permintaan upload ulang beserta catatan revisi telah dikirim ke email kelompok mahasiswa.');
    }

    public function recheckCapstone($id)
    {
        /** @var User $user */
        $user = Auth::user();
        $project = CapstoneProject::with('members')->findOrFail($id);

        if (
            ! $this->hasFullRepositoryAccess($user)
            && (int) $project->dosen_id !== (int) $user->id
            && ! $project->isMember((int) $user->id)
        ) {
            return redirect()->back()->with('error', 'Anda tidak memiliki akses untuk memeriksa ulang dokumen ini.');
        }

        if ($project->status === 'approved') {
            return redirect()->back()->with('error', 'Dokumen yang sudah disetujui tidak perlu diperiksa ulang.');
        }

        $project->update([
            'status' => 'checking',
            'ai_check_status' => 'checking',
        ]);

        CheckCapstonePdfJob::dispatch($project->id);

        return redirect()->back()->with('message', 'Pemeriksaan ulang kelengkapan PDF oleh AI Qwen sedang dijalankan...');
    }

    public function deleteCapstone($id)
    {
        /** @var User $user */
        $user = Auth::user();
        $project = CapstoneProject::findOrFail($id);

        if (! $this->hasFullRepositoryAccess($user) && (int) $project->dosen_id !== (int) $user->id) {
            return redirect()->back()->with('error', 'Unauthorized to delete this project.');
        }

        if ($project->document_path && Storage::disk('public')->exists($project->document_path)) {
            Storage::disk('public')->delete($project->document_path);
        }

        $project->delete();

        return redirect()->back()->with('message', 'Capstone deleted!');
    }

    public function updateCapstone(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        $project = CapstoneProject::with('members')->findOrFail($id);

        if (
            ! $this->hasFullRepositoryAccess($user)
            && (int) $project->dosen_id !== (int) $user->id
            && ! $project->isMember((int) $user->id)
        ) {
            return redirect()->back()->with('error', 'Unauthorized to edit this project.');
        }

        $activeTeam = $this->resolveActiveTeamForStudent($request, $user);
        if ($activeTeam) {
            $request->merge([
                'group_id' => $activeTeam->group_code,
                'academic_year' => $activeTeam->academic_year,
                'dosen_id' => $activeTeam->dosen_id ?? $project->dosen_id,
            ]);
        }

        $mustReuploadPdf = $project->status === 'rejected' && $user->role === 'mahasiswa';

        $request->validate([
            'title' => 'required|string|max:255',
            'academic_year' => 'required|string|max:50',
            'group_id' => 'required|string|max:100',
            'dosen_id' => 'required|exists:users,id',
            'document' => ($mustReuploadPdf ? 'required' : 'nullable') . '|file|mimes:pdf|max:51200',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'integer|exists:users,id',
        ], [
            'document.required' => 'Dokumen sedang ditahan. Anda wajib mengunggah ulang file PDF yang sudah diperbaiki.',
            'document.mimes' => 'File laporan harus berformat PDF.',
            'document.max' => 'Ukuran file PDF maksimal 50 MB.',
        ]);

        $project->fill($request->only('title', 'academic_year', 'group_id', 'dosen_id'));

        $uploadedNewPdf = false;
        if ($request->hasFile('document')) {
            if ($project->document_path && Storage::disk('public')->exists($project->document_path)) {
                Storage::disk('public')->delete($project->document_path);
            }
            $originalName = $request->file('document')->getClientOriginalName();
            $project->document_path = $request->file('document')->storeAs(
                'upload/capston/' . $project->group_id,
                $originalName,
                'public'
            );
            $project->status = 'checking';
            $project->ai_check_status = 'checking';
            $project->ai_check_result = null;
            $project->ai_checked_at = null;
            $project->revision_note = null;
            $project->acceptance_note = null;
            $project->reviewed_by = null;
            $project->reviewer_role = null;
            $project->reviewed_at = null;
            $uploadedNewPdf = true;
        }

        $project->save();

        if ($activeTeam || $request->has('member_ids')) {
            $memberIds = $this->resolveMemberIds($request, $user, $project, $activeTeam);
            $project->members()->sync($memberIds);
        }

        if ($uploadedNewPdf) {
            $project->load(['members', 'user', 'dosen']);
            $this->notifier->notifyStudentsUploadedChecking($project);

            CheckCapstonePdfJob::dispatch($project->id);
            $project->refresh();

            if ($project->status === 'rejected') {
                return redirect()->back()->with('error', 'Dokumen unggah ulang masih belum lengkap dan tetap ditahan. Silakan cek keterangan kekurangan.');
            }
            if ($project->status === 'pending') {
                return redirect()->back()->with('message', 'Dokumen unggah ulang lolos pemeriksaan AI dan masuk antrean persetujuan!');
            }

            return redirect()->back()->with('message', 'File PDF baru berhasil diunggah! AI Qwen sedang memeriksa ulang kelengkapan dokumen...');
        }

        return redirect()->back()->with('message', 'Capstone project updated successfully!');
    }

    public function approveScript($id)
    {
        /** @var User $user */
        $user = Auth::user();
        $project = ScriptProject::with(['user', 'capstoneProject.members'])->findOrFail($id);

        if (! $this->hasFullRepositoryAccess($user) && (int) ($project->capstoneProject?->dosen_id) !== (int) $user->id) {
            return redirect()->back()->with('error', 'Unauthorized to approve this script.');
        }

        if ($project->status === 'rejected') {
            return redirect()->back()->with('error', 'Source code sedang ditahan untuk revisi. Mahasiswa wajib mengunggah ulang file terlebih dahulu.');
        }

        $project->update([
            'status' => 'approved',
            'revision_note' => null,
            'reviewed_by' => $user->id,
            'reviewer_role' => $user->role,
            'reviewed_at' => now(),
        ]);

        $this->notifier->notifyStudentsApproved($project, 'Script / Source Code', $user);

        return redirect()->back()->with('message', 'Script approved!');
    }

    public function requestReuploadScript(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        $project = ScriptProject::with(['user', 'capstoneProject.members'])->findOrFail($id);

        if (! $this->hasFullRepositoryAccess($user) && (int) ($project->capstoneProject?->dosen_id) !== (int) $user->id) {
            return redirect()->back()->with('error', 'Anda tidak memiliki akses untuk meminta upload ulang source code ini.');
        }

        $validated = $request->validate([
            'revision_note' => 'required|string|max:2000',
        ], [
            'revision_note.required' => 'Catatan kekurangan / revisi wajib diisi agar mahasiswa mengetahui bagian yang harus diperbaiki.',
        ]);

        $project->update([
            'status' => 'rejected',
            'revision_note' => trim($validated['revision_note']),
            'reviewed_by' => $user->id,
            'reviewer_role' => $user->role,
            'reviewed_at' => now(),
        ]);

        $this->notifier->notifyStudentsReviewerReuploadRequired(
            $project,
            'Script / Source Code',
            $user,
            trim($validated['revision_note'])
        );

        return redirect()->back()->with('message', 'Permintaan upload ulang source code telah dikirim ke email kelompok mahasiswa.');
    }

    public function deleteScript($id)
    {
        /** @var User $user */
        $user = Auth::user();
        $project = ScriptProject::with('capstoneProject')->findOrFail($id);

        if (! $this->hasFullRepositoryAccess($user) && (int) ($project->capstoneProject?->dosen_id) !== (int) $user->id) {
            return redirect()->back()->with('error', 'Unauthorized to delete this project.');
        }

        if ($project->archive_path && Storage::disk('public')->exists($project->archive_path)) {
            Storage::disk('public')->delete($project->archive_path);
        }

        $project->delete();

        return redirect()->back()->with('message', 'Script deleted!');
    }

    public function updateScript(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        $project = ScriptProject::with('capstoneProject.members')->findOrFail($id);

        $isGroupMember = $project->capstoneProject && $project->capstoneProject->isMember((int) $user->id);

        if (
            ! $this->hasFullRepositoryAccess($user)
            && (int) ($project->capstoneProject?->dosen_id) !== (int) $user->id
            && (int) $project->user_id !== (int) $user->id
            && ! $isGroupMember
        ) {
            return redirect()->back()->with('error', 'Unauthorized to edit this project.');
        }

        $mustReuploadArchive = $project->status === 'rejected' && $user->role === 'mahasiswa';

        $request->validate([
            'title' => 'required|string|max:255',
            'capstone_project_id' => 'required|exists:capstone_projects,id',
            'archive' => ($mustReuploadArchive ? 'required' : 'nullable') . '|file|max:51200',
        ], [
            'archive.required' => 'Source code sedang diminta upload ulang. Silakan pilih file ZIP/RAR yang sudah diperbaiki.',
        ]);

        $project->fill($request->only('title', 'capstone_project_id'));

        $capstone = CapstoneProject::findOrFail($request->capstone_project_id);
        $project->academic_year = $capstone->academic_year;
        $project->group_id = $capstone->group_id;

        $uploadedNewArchive = false;
        if ($request->hasFile('archive')) {
            if ($project->archive_path && Storage::disk('public')->exists($project->archive_path)) {
                Storage::disk('public')->delete($project->archive_path);
            }
            $originalName = $request->file('archive')->getClientOriginalName();
            $project->archive_path = $request->file('archive')->storeAs(
                'upload/script/' . $project->group_id,
                $originalName,
                'public'
            );
            $project->status = 'pending';
            $project->revision_note = null;
            $project->reviewed_by = null;
            $project->reviewer_role = null;
            $project->reviewed_at = null;
            $uploadedNewArchive = true;
        }
        $project->save();

        if ($uploadedNewArchive) {
            $project->load(['user', 'capstoneProject.members', 'capstoneProject.dosen']);
            $this->notifier->notifyStudentsPendingVerification($project, 'Script / Source Code');
            $this->notifier->notifyVerifiersNewSubmission($project, 'Script / Source Code');

            return redirect()->back()->with('message', 'Source code berhasil diunggah ulang dan menunggu verifikasi!');
        }

        return redirect()->back()->with('message', 'Script updated successfully!');
    }

    public function downloadCapstone($id)
    {
        /** @var User $user */
        $user = Auth::user();
        $project = CapstoneProject::with('members')->findOrFail($id);

        $canDownload = $this->hasFullRepositoryAccess($user)
            || $project->status === 'approved'
            || (int) $project->dosen_id === (int) $user->id
            || $project->isMember((int) $user->id);

        abort_unless($canDownload, 403);

        if ($project->document_path && Storage::disk('public')->exists($project->document_path)) {
            return Storage::disk('public')->download($project->document_path, basename($project->document_path), [
                'X-Robots-Tag' => 'noindex, nofollow, noarchive, nosnippet',
            ]);
        }

        return redirect()->back()->with('error', 'File not found.');
    }

    public function downloadScript($id)
    {
        /** @var User $user */
        $user = Auth::user();
        $project = ScriptProject::with('capstoneProject.members')->findOrFail($id);

        $isGroupMember = $project->capstoneProject && $project->capstoneProject->isMember((int) $user->id);
        $canDownload = $this->hasFullRepositoryAccess($user)
            || $project->status === 'approved'
            || (int) ($project->capstoneProject?->dosen_id) === (int) $user->id
            || (int) $project->user_id === (int) $user->id
            || $isGroupMember;

        abort_unless($canDownload, 403);

        if ($project->archive_path && Storage::disk('public')->exists($project->archive_path)) {
            return Storage::disk('public')->download($project->archive_path, basename($project->archive_path), [
                'X-Robots-Tag' => 'noindex, nofollow, noarchive, nosnippet',
            ]);
        }

        return redirect()->back()->with('error', 'File not found.');
    }
}
