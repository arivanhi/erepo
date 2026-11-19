<?php

namespace App\Http\Controllers;

use App\Mail\SmtpTestMail;
use App\Models\User;
use App\Services\SmtpConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SmtpConfigurationService $smtpConfig
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Settings', [
            'smtpSettings' => $user->role === 'admin'
                ? $this->smtpConfig->getAdminViewPayload()
                : null,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ]);

        $user->fill($validated);
        $user->save();

        return redirect()->back()->with('message', 'Informasi profil berhasil diperbarui!');
    }

    public function updateSmtp(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->role === 'admin',
            403,
            'Hanya Admin yang dapat mengubah konfigurasi SMTP.'
        );

        $current = $this->smtpConfig->getAdminViewPayload();
        $passwordRule = $current['has_password']
            ? ['nullable', 'string', 'max:255']
            : ['required', 'string', 'max:255'];

        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', 'in:tls,ssl'],
            'username' => ['required', 'email', 'max:255'],
            'password' => $passwordRule,
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ], [
            'username.required' => 'Alamat email akun Google wajib diisi.',
            'username.email' => 'Format alamat email Google tidak valid.',
            'password.required' => 'Google App Password (16 karakter) wajib diisi.',
        ]);

        $this->smtpConfig->saveSettings($validated);

        return redirect()->back()->with('message', 'Pengaturan Google SMTP berhasil disimpan dan diaktifkan!');
    }

    public function testSmtp(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'admin', 403, 'Hanya Admin yang dapat menguji koneksi SMTP.');

        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ], [
            'test_email.required' => 'Masukkan alamat email tujuan tes.',
            'test_email.email' => 'Format alamat email tujuan tes tidak valid.',
        ]);

        if (! $this->smtpConfig->isConfigured()) {
            return redirect()->back()->with(
                'error',
                'Pengaturan Google SMTP belum disimpan. Silakan isi Email Google & App Password lalu klik Simpan terlebih dahulu.'
            );
        }

        try {
            $this->smtpConfig->applyRuntimeConfig(forceSmtp: true);
            $resolved = $this->smtpConfig->getResolvedSettings();

            $mailerName = app()->environment('testing') ? config('mail.default') : 'smtp';

            Mail::mailer($mailerName)
                ->to($validated['test_email'])
                ->send(new SmtpTestMail(
                    triggeredByName: $user->name,
                    smtpHost: $resolved['host'] . ':' . $resolved['port'],
                    smtpUsername: $resolved['username']
                ));

            return redirect()->back()->with(
                'message',
                'Email tes berhasil dikirim ke ' . $validated['test_email'] . '! Silakan cek kotak masuk Anda.'
            );
        } catch (Throwable $e) {
            return redirect()->back()->with(
                'error',
                'Gagal mengirim email tes melalui SMTP: ' . $e->getMessage()
            );
        }
    }
}
