<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SmtpConfigurationService
{
    private const PLACEHOLDER_EMAILS = [
        '',
        'email_anda@gmail.com',
        'hello@example.com',
    ];

    private const PLACEHOLDER_PASSWORDS = [
        '',
        'password_app_gmail_anda',
    ];

    private static ?string $lastAppliedSignature = null;

    /**
     * Load resolved SMTP settings (DB overrides .env defaults).
     *
     * @return array{
     *     host: string,
     *     port: int,
     *     encryption: string,
     *     username: string,
     *     password: string,
     *     from_address: string,
     *     from_name: string
     * }
     */
    public function getResolvedSettings(): array
    {
        $db = $this->readDbSettings();

        $host = trim((string) ($db['smtp_host'] ?? env('MAIL_HOST', 'smtp.gmail.com')));
        $port = (int) ($db['smtp_port'] ?? env('MAIL_PORT', 587));
        $encryption = strtolower(trim((string) ($db['smtp_encryption'] ?? env('MAIL_ENCRYPTION', $port === 465 ? 'ssl' : 'tls'))));
        $username = trim((string) ($db['smtp_username'] ?? env('MAIL_USERNAME', '')));

        $password = '';
        if (! empty($db['smtp_password_encrypted'])) {
            try {
                $password = Crypt::decryptString($db['smtp_password_encrypted']);
            } catch (Throwable) {
                $password = '';
            }
        } else {
            $password = (string) env('MAIL_PASSWORD', '');
        }

        // Google App Passwords are often copied as "xxxx xxxx xxxx xxxx" — normalize whitespace
        if (str_contains(strtolower($host), 'gmail.com')) {
            $password = preg_replace('/\s+/', '', $password) ?? $password;
        } else {
            $password = trim($password);
        }

        $fromAddress = trim((string) ($db['smtp_from_address'] ?? env('MAIL_FROM_ADDRESS', $username)));
        if ($fromAddress === '' || in_array(strtolower($fromAddress), self::PLACEHOLDER_EMAILS, true)) {
            $fromAddress = $username;
        }

        $fromName = trim((string) ($db['smtp_from_name'] ?? env('MAIL_FROM_NAME', config('app.name', 'EREPO Teknik Elektro'))));
        if ($fromName === '') {
            $fromName = 'EREPO Teknik Elektro';
        }

        return [
            'host' => $host !== '' ? $host : 'smtp.gmail.com',
            'port' => $port > 0 ? $port : 587,
            'encryption' => in_array($encryption, ['tls', 'ssl'], true) ? $encryption : 'tls',
            'username' => $username,
            'password' => $password,
            'from_address' => $fromAddress,
            'from_name' => $fromName,
        ];
    }

    /**
     * Public-safe settings payload for Admin Settings UI (never exposes raw password).
     *
     * @return array<string, mixed>
     */
    public function getAdminViewPayload(): array
    {
        $resolved = $this->getResolvedSettings();
        $hasValidPassword = ! in_array($resolved['password'], self::PLACEHOLDER_PASSWORDS, true);
        $isConfigured = $this->isConfigured($resolved);

        $maskedPassword = null;
        if ($hasValidPassword) {
            $len = strlen($resolved['password']);
            $tail = $len >= 4 ? substr($resolved['password'], -4) : '••••';
            $maskedPassword = '•••• •••• •••• ' . $tail;
        }

        return [
            'host' => $resolved['host'],
            'port' => $resolved['port'],
            'encryption' => $resolved['encryption'],
            'username' => in_array(strtolower($resolved['username']), self::PLACEHOLDER_EMAILS, true) ? '' : $resolved['username'],
            'from_address' => in_array(strtolower($resolved['from_address']), self::PLACEHOLDER_EMAILS, true) ? '' : $resolved['from_address'],
            'from_name' => $resolved['from_name'],
            'has_password' => $hasValidPassword,
            'masked_password' => $maskedPassword,
            'is_configured' => $isConfigured,
        ];
    }

    /**
     * Save SMTP settings from Admin Settings UI into encrypted database storage.
     *
     * @param  array<string, mixed>  $input
     */
    public function saveSettings(array $input): void
    {
        $host = trim((string) ($input['host'] ?? 'smtp.gmail.com'));
        $port = (int) ($input['port'] ?? 587);
        $encryption = strtolower(trim((string) ($input['encryption'] ?? ($port === 465 ? 'ssl' : 'tls'))));
        $username = trim((string) ($input['username'] ?? ''));
        $fromAddress = trim((string) ($input['from_address'] ?? '')) ?: $username;
        $fromName = trim((string) ($input['from_name'] ?? 'EREPO Teknik Elektro'));

        $pairs = [
            'smtp_host' => $host,
            'smtp_port' => (string) $port,
            'smtp_encryption' => $encryption,
            'smtp_username' => $username,
            'smtp_from_address' => $fromAddress,
            'smtp_from_name' => $fromName,
        ];

        $rawPassword = (string) ($input['password'] ?? '');
        if (trim($rawPassword) !== '') {
            $cleanPassword = str_contains(strtolower($host), 'gmail.com')
                ? (preg_replace('/\s+/', '', $rawPassword) ?? trim($rawPassword))
                : trim($rawPassword);
            $pairs['smtp_password_encrypted'] = Crypt::encryptString($cleanPassword);
        }

        foreach ($pairs as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        self::$lastAppliedSignature = null;
        $this->applyRuntimeConfig();
    }

    /**
     * Determine whether real SMTP credentials have been configured.
     *
     * @param  array<string, mixed>|null  $resolved
     */
    public function isConfigured(?array $resolved = null): bool
    {
        $settings = $resolved ?? $this->getResolvedSettings();

        $username = strtolower(trim((string) ($settings['username'] ?? '')));
        $password = trim((string) ($settings['password'] ?? ''));
        $host = trim((string) ($settings['host'] ?? ''));

        if ($host === '' || in_array($username, self::PLACEHOLDER_EMAILS, true)) {
            return false;
        }

        if (in_array($password, self::PLACEHOLDER_PASSWORDS, true)) {
            return false;
        }

        return filter_var($username, FILTER_VALIDATE_EMAIL) !== false || $username !== '';
    }

    /**
     * Apply SMTP settings dynamically to current PHP/Octane/Queue worker runtime.
     */
    public function applyRuntimeConfig(bool $forceSmtp = false): void
    {
        if (app()->environment('testing') && ! $forceSmtp) {
            return;
        }

        $settings = $this->getResolvedSettings();
        if (! $forceSmtp && ! $this->isConfigured($settings)) {
            return;
        }

        $scheme = ($settings['encryption'] === 'ssl' || (int) $settings['port'] === 465)
            ? 'smtps'
            : 'smtp';

        $signature = sha1(json_encode([$settings, $scheme, $forceSmtp]) ?: '');

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.host' => $settings['host'],
            'mail.mailers.smtp.port' => (int) $settings['port'],
            'mail.mailers.smtp.username' => $settings['username'],
            'mail.mailers.smtp.password' => $settings['password'],
            'mail.mailers.smtp.timeout' => 25,
            'mail.from.address' => $settings['from_address'] ?: $settings['username'],
            'mail.from.name' => $settings['from_name'],
        ]);

        if (self::$lastAppliedSignature !== $signature) {
            Mail::purge('smtp');
            self::$lastAppliedSignature = $signature;
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function readDbSettings(): array
    {
        try {
            if (! Schema::hasTable('system_settings')) {
                return [];
            }

            return SystemSetting::query()
                ->whereIn('key', [
                    'smtp_host',
                    'smtp_port',
                    'smtp_encryption',
                    'smtp_username',
                    'smtp_password_encrypted',
                    'smtp_from_address',
                    'smtp_from_name',
                ])
                ->pluck('value', 'key')
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
