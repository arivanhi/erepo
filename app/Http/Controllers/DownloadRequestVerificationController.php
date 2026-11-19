<?php

namespace App\Http\Controllers;

use App\Models\PublicDownloadRequest;
use App\Models\User;
use App\Services\RepositoryNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DownloadRequestVerificationController extends Controller
{
    public function __construct(
        private readonly RepositoryNotificationService $notifier
    ) {}

    private function ensureVerifierRole(User $user): void
    {
        abort_unless(
            in_array($user->role, ['admin', 'koordinator_ta', 'dosen', 'kaprodi'], true),
            403,
            'Hanya Dosen Pembimbing, Koordinator TA, dan Admin yang dapat mengakses menu Verifikasi Permintaan Download.'
        );
    }

    private function canVerifyRequest(User $user, PublicDownloadRequest $req): bool
    {
        if (in_array($user->role, ['admin', 'koordinator_ta', 'kaprodi'], true)) {
            return true;
        }

        if ($user->role === 'dosen') {
            $dosenId = $req->scriptProject?->capstoneProject?->dosen_id;
            return $dosenId !== null && (int) $dosenId === (int) $user->id;
        }

        return false;
    }

    private function generateDownloadCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $raw = '';
        for ($i = 0; $i < 8; $i++) {
            $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
    }

    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $this->ensureVerifierRole($user);

        $statusFilter = (string) $request->input('status', 'all');
        $search = trim((string) $request->input('search', ''));
        $perPageReq = (int) $request->input('per_page', 10);
        $perPage = in_array($perPageReq, [10, 25, 50], true) ? $perPageReq : 10;

        $query = PublicDownloadRequest::with([
            'scriptProject.user:id,name',
            'scriptProject.capstoneProject.members:id,name',
            'scriptProject.capstoneProject.dosen:id,name',
        ])->where('type', 'script_archive');

        // Dosen Pembimbing only sees requests for repositories they supervise
        if ($user->role === 'dosen') {
            $query->whereHas('scriptProject.capstoneProject', function ($q) use ($user) {
                $q->where('dosen_id', $user->id);
            });
        }

        if (in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('requester_name', 'like', "%{$search}%")
                  ->orWhere('requester_email', 'like', "%{$search}%")
                  ->orWhere('purpose', 'like', "%{$search}%")
                  ->orWhereHas('scriptProject', function ($sq) use ($search) {
                      $sq->where('title', 'like', "%{$search}%")
                         ->orWhere('group_id', 'like', "%{$search}%");
                  });
            });
        }

        $requests = $query
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END ASC")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->onEachSide(1)
            ->withQueryString()
            ->through(function (PublicDownloadRequest $req) {
                $script = $req->scriptProject;
                $members = $script?->capstoneProject?->members;
                $memberNames = ($members && $members->isNotEmpty())
                    ? $members->pluck('name')->join(', ')
                    : ($script?->user?->name ?? '-');

                return [
                    'id' => $req->id,
                    'status' => $req->status,
                    'requester_name' => $req->requester_name,
                    'requester_email' => $req->requester_email,
                    'purpose' => $req->purpose,
                    'script_title' => $script?->title ?? '(Script telah dihapus)',
                    'group_id' => $script?->group_id ?? '-',
                    'academic_year' => $script?->academic_year ?? '-',
                    'member_names' => $memberNames,
                    'dosen_name' => $script?->capstoneProject?->dosen?->name ?? '-',
                    'verified_by' => $req->approved_by_email,
                    'created_at_formatted' => $req->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB',
                    'approved_at_formatted' => $req->approved_at
                        ? ($req->approved_at->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB')
                        : null,
                    'expires_at_formatted' => $req->expires_at
                        ? ($req->expires_at->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB')
                        : null,
                    'is_token_active' => $req->isTokenValid(),
                ];
            });

        return Inertia::render('Admin/DownloadRequests', [
            'requests' => $requests,
            'filters' => [
                'status' => $statusFilter,
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function decide(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        $this->ensureVerifierRole($user);

        $req = PublicDownloadRequest::with('scriptProject.capstoneProject')->findOrFail($id);
        abort_unless($this->canVerifyRequest($user, $req), 403, 'Anda tidak berwenang memverifikasi permintaan download untuk repositori ini.');

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
        ]);

        $verifierLabel = $user->name . ' (' . RepositoryNotificationService::formatRoleName($user->role) . ')';

        if ($validated['decision'] === 'reject') {
            $req->update([
                'status' => 'rejected',
                'approved_by_email' => $verifierLabel,
                'approved_at' => now(),
            ]);

            return redirect()->back()->with('message', 'Permintaan download source code telah ditolak.');
        }

        if (! $req->isTokenValid()) {
            $req->update([
                'status' => 'approved',
                'download_code' => $this->generateDownloadCode(),
                'download_url_token' => Str::random(56),
                'expires_at' => now()->addDay(),
                'approved_by_email' => $verifierLabel,
                'approved_at' => now(),
            ]);
        } else {
            $req->update([
                'approved_by_email' => $verifierLabel,
                'approved_at' => now(),
            ]);
        }

        if ($req->scriptProject) {
            $directUrl = route('public.download.direct', $req->download_url_token);
            $this->notifier->sendPublicDownloadToken(
                $req,
                'Source Code (Script)',
                $req->scriptProject->title,
                $req->scriptProject->group_id,
                $directUrl
            );
        }

        return redirect()->back()->with(
            'message',
            'Permintaan berhasil diverifikasi! Token download (aktif 1 hari) telah dikirim ke ' . $req->requester_email . '.'
        );
    }
}
