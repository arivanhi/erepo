<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CapstoneTeam;
use App\Services\RepositoryNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CapstoneAuthController extends Controller
{
    public function __construct(
        private readonly RepositoryNotificationService $notifier
    ) {}

    public function requestToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'group_code' => ['required', 'string', 'max:80'],
        ], [
            'group_code.required' => 'Masukkan Kode Capstone kelompok Anda terlebih dahulu.',
        ]);

        $code = strtoupper(trim($validated['group_code']));
        $throttleKey = 'capstone-token-req:' . $code . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'group_code' => "Terlalu banyak permintaan pengiriman token. Silakan tunggu {$seconds} detik.",
            ]);
        }

        $team = CapstoneTeam::with('members')->where('group_code', $code)->first();
        if (! $team) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'group_code' => 'Kode Capstone tidak ditemukan. Pastikan kode sesuai yang didaftarkan oleh Koordinator TA.',
            ]);
        }

        if ($team->members->isEmpty()) {
            throw ValidationException::withMessages([
                'group_code' => 'Tim Capstone ini belum memiliki email anggota terdaftar. Hubungi Koordinator TA.',
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        $plainToken = $team->ensureActiveToken(false);
        $this->notifier->sendCapstoneTeamLoginToken($team, $plainToken);

        return redirect()->back()->with(
            'status',
            "Kode Token Login (aktif 1 bulan) telah dikirim ke email seluruh anggota kelompok {$team->group_code}."
        );
    }

    public function loginWithToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'group_code' => ['required', 'string', 'max:80'],
            'token' => ['required', 'string', 'max:32'],
        ], [
            'group_code.required' => 'Kode Capstone wajib diisi.',
            'token.required' => 'Kode Token Login wajib diisi.',
        ]);

        $code = strtoupper(trim($validated['group_code']));
        $throttleKey = 'capstone-login:' . $code . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 6)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'token' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
            ]);
        }

        $team = CapstoneTeam::with(['members', 'leadUser'])->where('group_code', $code)->first();
        if (! $team || ! $team->verifyToken($validated['token'])) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'token' => 'Kode Capstone atau Token tidak valid / sudah kedaluwarsa (> 1 bulan). Silakan klik "Kirim Token ke Email Kelompok".',
            ]);
        }

        $loginUser = $team->leadUser ?? $team->members->first();
        if (! $loginUser) {
            throw ValidationException::withMessages([
                'group_code' => 'Akun anggota untuk tim ini belum tersedia. Hubungi Koordinator TA.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($loginUser);
        $request->session()->regenerate();
        $request->session()->put('active_capstone_team_id', $team->id);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
