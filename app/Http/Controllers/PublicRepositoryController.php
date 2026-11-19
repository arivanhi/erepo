<?php

namespace App\Http\Controllers;

use App\Models\CapstoneProject;
use App\Models\PublicDownloadRequest;
use App\Models\ScriptProject;
use App\Services\AbstractExtractorService;
use App\Services\RepositoryNotificationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PublicRepositoryController extends Controller
{
    public function __construct(
        private readonly AbstractExtractorService $abstractExtractor,
        private readonly RepositoryNotificationService $notifier
    ) {}

    private function udinusEmailRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $email = strtolower(trim((string) $value));
            if (! str_ends_with($email, '@mhs.dinus.ac.id') && ! str_ends_with($email, '@dsn.dinus.ac.id')) {
                $fail('Hanya email resmi UDINUS (@mhs.dinus.ac.id atau @dsn.dinus.ac.id) yang diperbolehkan.');
            }
        };
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

    private function resolvePerPage(Request $request): int
    {
        $requested = (int) $request->input('per_page', 10);

        return in_array($requested, [10, 25, 50], true) ? $requested : 10;
    }

    private function getApprovedAcademicYears(): array
    {
        $capstoneYears = CapstoneProject::where('status', 'approved')
            ->select('academic_year', DB::raw('COUNT(*) as total'))
            ->groupBy('academic_year')
            ->pluck('total', 'academic_year')
            ->all();

        $scriptYears = ScriptProject::where('status', 'approved')
            ->select('academic_year', DB::raw('COUNT(*) as total'))
            ->groupBy('academic_year')
            ->pluck('total', 'academic_year')
            ->all();

        $allYears = array_unique(array_merge(array_keys($capstoneYears), array_keys($scriptYears)));
        rsort($allYears, SORT_NATURAL);

        $summary = [];
        foreach ($allYears as $yr) {
            if (! $yr) {
                continue;
            }
            $cCount = (int) ($capstoneYears[$yr] ?? 0);
            $sCount = (int) ($scriptYears[$yr] ?? 0);
            $summary[] = [
                'academic_year' => $yr,
                'capstone_count' => $cCount,
                'script_count' => $sCount,
                'total' => $cCount + $sCount,
            ];
        }

        return $summary;
    }

    public function dashboard(Request $request)
    {
        $selectedYear = $request->input('academic_year', 'all');
        $yearBreakdown = $this->getApprovedAcademicYears();

        $capstoneQuery = CapstoneProject::with(['user:id,name', 'dosen:id,name', 'members:id,name'])
            ->where('status', 'approved');

        $scriptQuery = ScriptProject::with(['user:id,name', 'capstoneProject.members:id,name', 'capstoneProject.dosen:id,name'])
            ->where('status', 'approved');

        if ($selectedYear && $selectedYear !== 'all') {
            $capstoneQuery->where('academic_year', $selectedYear);
            $scriptQuery->where('academic_year', $selectedYear);
        }

        $recentCapstones = (clone $capstoneQuery)
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get()
            ->map(fn (CapstoneProject $p) => [
                'id' => $p->id,
                'type' => 'PDF Capstone',
                'title' => $p->title,
                'group_id' => $p->group_id,
                'academic_year' => $p->academic_year,
                'member_names' => $p->members->isNotEmpty()
                    ? $p->members->pluck('name')->join(', ')
                    : ($p->user?->name ?? '-'),
                'dosen_name' => $p->dosen?->name ?? '-',
                'date_human' => $p->created_at?->diffForHumans() ?? '',
            ]);

        $recentScripts = (clone $scriptQuery)
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get()
            ->map(fn (ScriptProject $s) => [
                'id' => $s->id,
                'type' => 'Source Code',
                'title' => $s->title,
                'group_id' => $s->group_id,
                'academic_year' => $s->academic_year,
                'member_names' => ($s->capstoneProject && $s->capstoneProject->members->isNotEmpty())
                    ? $s->capstoneProject->members->pluck('name')->join(', ')
                    : ($s->user?->name ?? '-'),
                'dosen_name' => $s->capstoneProject?->dosen?->name ?? '-',
                'date_human' => $s->created_at?->diffForHumans() ?? '',
            ]);

        return Inertia::render('Public/Dashboard', [
            'stats' => [
                'totalCapstone' => CapstoneProject::where('status', 'approved')->count(),
                'totalScript' => ScriptProject::where('status', 'approved')->count(),
                'totalYears' => count($yearBreakdown),
            ],
            'yearBreakdown' => $yearBreakdown,
            'selectedYear' => $selectedYear,
            'recentCapstones' => $recentCapstones,
            'recentScripts' => $recentScripts,
        ]);
    }

    public function capstone(Request $request)
    {
        $perPage = $this->resolvePerPage($request);
        $search = trim((string) $request->input('search', ''));
        $academicYear = (string) $request->input('academic_year', 'all');

        $query = CapstoneProject::with(['user:id,name', 'dosen:id,name', 'members:id,name'])
            ->where('status', 'approved');

        if ($academicYear !== '' && $academicYear !== 'all') {
            $query->where('academic_year', $academicYear);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('group_id', 'like', "%{$search}%")
                  ->orWhereHas('members', fn ($mq) => $mq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('dosen', fn ($dq) => $dq->where('name', 'like', "%{$search}%"));
            });
        }

        $projects = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (CapstoneProject $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'academic_year' => $p->academic_year,
                'group_id' => $p->group_id,
                'member_names' => $p->members->isNotEmpty()
                    ? $p->members->pluck('name')->join(', ')
                    : ($p->user?->name ?? '-'),
                'dosen_name' => $p->dosen?->name ?? '-',
                'has_document' => ! empty($p->document_path),
            ]);

        return Inertia::render('Public/Capstone', [
            'projects' => $projects,
            'yearOptions' => array_column($this->getApprovedAcademicYears(), 'academic_year'),
            'filters' => [
                'search' => $search,
                'academic_year' => $academicYear,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function abstractMeta($id)
    {
        $project = CapstoneProject::where('status', 'approved')->findOrFail($id);
        $images = $this->abstractExtractor->getAbstractPageImages($project);

        $urls = [];
        foreach (array_keys($images) as $idx) {
            $urls[] = route('public.capstone.abstract.page', [
                'id' => $project->id,
                'page' => $idx + 1,
            ]);
        }

        return response()->json([
            'id' => $project->id,
            'title' => $project->title,
            'group_id' => $project->group_id,
            'page_count' => count($urls),
            'pages' => $urls,
        ]);
    }

    public function abstractPage($id, int $page)
    {
        $project = CapstoneProject::where('status', 'approved')->findOrFail($id);
        $images = $this->abstractExtractor->getAbstractPageImages($project);

        $index = $page - 1;
        abort_if(! isset($images[$index]) || ! is_file($images[$index]), 404, 'Halaman abstrak tidak ditemukan.');

        return response()->file($images[$index], [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=1800',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive, nosnippet',
        ]);
    }

    public function requestPdfToken(Request $request, $id)
    {
        $project = CapstoneProject::where('status', 'approved')->findOrFail($id);

        if (! $project->document_path || ! Storage::disk('public')->exists($project->document_path)) {
            return redirect()->back()->with('error', 'Berkas PDF belum tersedia pada repositori ini.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', $this->udinusEmailRule()],
        ]);

        $email = strtolower(trim($validated['email']));

        // Reuse existing valid 1-day token for same project + email if still active
        $existing = PublicDownloadRequest::where('type', 'capstone_pdf')
            ->where('capstone_project_id', $project->id)
            ->where('requester_email', $email)
            ->where('status', 'approved')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $existing) {
            $existing = PublicDownloadRequest::create([
                'type' => 'capstone_pdf',
                'capstone_project_id' => $project->id,
                'requester_email' => $email,
                'status' => 'approved',
                'download_code' => $this->generateDownloadCode(),
                'download_url_token' => Str::random(56),
                'expires_at' => now()->addDay(),
                'approved_at' => now(),
            ]);
        }

        $directUrl = route('public.download.direct', $existing->download_url_token);
        $this->notifier->sendPublicDownloadToken(
            $existing,
            'Laporan PDF Capstone',
            $project->title,
            $project->group_id,
            $directUrl
        );

        return redirect()->back()->with(
            'message',
            'Token download (berlaku 1 hari) telah dikirim ke ' . $email . '. Silakan cek kotak masuk email Anda!'
        );
    }

    public function script(Request $request)
    {
        $perPage = $this->resolvePerPage($request);
        $search = trim((string) $request->input('search', ''));
        $academicYear = (string) $request->input('academic_year', 'all');

        $query = ScriptProject::with(['user:id,name', 'capstoneProject.members:id,name', 'capstoneProject.dosen:id,name'])
            ->where('status', 'approved');

        if ($academicYear !== '' && $academicYear !== 'all') {
            $query->where('academic_year', $academicYear);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('group_id', 'like', "%{$search}%")
                  ->orWhereHas('capstoneProject.members', fn ($mq) => $mq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('capstoneProject.dosen', fn ($dq) => $dq->where('name', 'like', "%{$search}%"));
            });
        }

        $projects = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (ScriptProject $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'academic_year' => $s->academic_year,
                'group_id' => $s->group_id,
                'member_names' => ($s->capstoneProject && $s->capstoneProject->members->isNotEmpty())
                    ? $s->capstoneProject->members->pluck('name')->join(', ')
                    : ($s->user?->name ?? '-'),
                'dosen_name' => $s->capstoneProject?->dosen?->name ?? '-',
                'has_archive' => ! empty($s->archive_path),
            ]);

        return Inertia::render('Public/Script', [
            'projects' => $projects,
            'yearOptions' => array_column($this->getApprovedAcademicYears(), 'academic_year'),
            'filters' => [
                'search' => $search,
                'academic_year' => $academicYear,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function requestScriptAccess(Request $request, $id)
    {
        $script = ScriptProject::with(['user', 'capstoneProject.members', 'capstoneProject.dosen'])
            ->where('status', 'approved')
            ->findOrFail($id);

        if (! $script->archive_path || ! Storage::disk('public')->exists($script->archive_path)) {
            return redirect()->back()->with('error', 'Berkas arsip source code belum tersedia.');
        }

        $validated = $request->validate([
            'requester_name' => ['required', 'string', 'max:255'],
            'requester_email' => ['required', 'email', 'max:255', $this->udinusEmailRule()],
            'purpose' => ['required', 'string', 'max:1000'],
        ], [
            'requester_name.required' => 'Nama lengkap wajib diisi.',
            'purpose.required' => 'Keperluan / alasan pengunduhan source code wajib diisi.',
        ]);

        $cleanEmail = strtolower(trim($validated['requester_email']));

        // Prevent duplicate pending rows for the same Script + requester email
        $existingPending = PublicDownloadRequest::where('type', 'script_archive')
            ->where('script_project_id', $script->id)
            ->where('requester_email', $cleanEmail)
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return redirect()->back()->with(
                'message',
                'Permohonan Anda untuk source code ini sudah tercatat dan sedang menunggu verifikasi Dosen Pembimbing / Koordinator TA / Admin. Token download baru akan dikirim ke email Anda setelah disetujui.'
            );
        }

        $downloadReq = PublicDownloadRequest::create([
            'type' => 'script_archive',
            'script_project_id' => $script->id,
            'requester_name' => trim($validated['requester_name']),
            'requester_email' => $cleanEmail,
            'purpose' => trim($validated['purpose']),
            'status' => 'pending',
            'approval_token' => Str::random(56),
        ]);

        $verificationMenuUrl = route('download-requests.index');
        $this->notifier->sendScriptDownloadPermissionRequest($script, $downloadReq, $verificationMenuUrl);

        return redirect()->back()->with(
            'message',
            'Permohonan izin download telah dikirim! Notifikasi verifikasi telah dikirim ke Dosen Pembimbing, Koordinator TA, dan Admin. Token akan dikirim ke email Anda setelah diverifikasi.'
        );
    }

    public function verifyAndDownload(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:capstone_pdf,script_archive'],
            'project_id' => ['required', 'integer'],
            'email' => ['required', 'email'],
            'token' => ['required', 'string', 'max:32'],
        ]);

        $normInput = strtoupper(preg_replace('/[^A-Z0-9]/', '', $validated['token']) ?? '');
        $email = strtolower(trim($validated['email']));

        $query = PublicDownloadRequest::where('type', $validated['type'])
            ->where('requester_email', $email)
            ->where('status', 'approved');

        if ($validated['type'] === 'capstone_pdf') {
            $query->where('capstone_project_id', $validated['project_id']);
        } else {
            $query->where('script_project_id', $validated['project_id']);
        }

        $candidates = $query->latest('id')->get();
        $matched = $candidates->first(function (PublicDownloadRequest $item) use ($normInput) {
            if (! $item->isTokenValid()) {
                return false;
            }
            $storedNorm = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $item->download_code) ?? '');
            return $storedNorm !== '' && hash_equals($storedNorm, $normInput);
        });

        if (! $matched) {
            return redirect()->back()->with(
                'error',
                'Token download tidak valid, belum disetujui, atau sudah kedaluwarsa (> 1 hari).'
            );
        }

        return redirect()->route('public.download.direct', $matched->download_url_token);
    }

    public function downloadByUrlToken(string $urlToken)
    {
        $req = PublicDownloadRequest::with(['capstoneProject', 'scriptProject'])
            ->where('download_url_token', $urlToken)
            ->firstOrFail();

        abort_if(! $req->isTokenValid(), 403, 'Token download sudah kedaluwarsa (lebih dari 1 hari) atau tidak aktif.');

        if ($req->type === 'capstone_pdf') {
            $project = $req->capstoneProject;
            abort_if(! $project || $project->status !== 'approved' || ! $project->document_path, 404);
            abort_if(! Storage::disk('public')->exists($project->document_path), 404, 'File PDF tidak ditemukan.');

            $req->update(['downloaded_at' => now()]);

            return Storage::disk('public')->download($project->document_path, basename($project->document_path), [
                'X-Robots-Tag' => 'noindex, nofollow, noarchive, nosnippet',
            ]);
        }

        $script = $req->scriptProject;
        abort_if(! $script || $script->status !== 'approved' || ! $script->archive_path, 404);
        abort_if(! Storage::disk('public')->exists($script->archive_path), 404, 'File arsip tidak ditemukan.');

        $req->update(['downloaded_at' => now()]);

        return Storage::disk('public')->download($script->archive_path, basename($script->archive_path), [
            'X-Robots-Tag' => 'noindex, nofollow, noarchive, nosnippet',
        ]);
    }
}
