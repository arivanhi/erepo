<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

class CapstoneTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_code',
        'team_name',
        'academic_year',
        'dosen_id',
        'lead_user_id',
        'login_token_encrypted',
        'login_token_expires_at',
        'login_token_sent_at',
        'created_by',
    ];

    protected $hidden = [
        'login_token_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'login_token_expires_at' => 'datetime',
            'login_token_sent_at' => 'datetime',
        ];
    }

    public function dosen()
    {
        return $this->belongsTo(User::class, 'dosen_id');
    }

    public function leadUser()
    {
        return $this->belongsTo(User::class, 'lead_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'capstone_team_user')->withTimestamps();
    }

    public function capstoneProjects()
    {
        return $this->hasMany(CapstoneProject::class, 'capstone_team_id');
    }

    public function hasActiveToken(): bool
    {
        return ! empty($this->login_token_encrypted)
            && $this->login_token_expires_at !== null
            && $this->login_token_expires_at->isFuture()
            && $this->getPlainToken() !== null;
    }

    public function getPlainToken(): ?string
    {
        if (empty($this->login_token_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->login_token_encrypted);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Return existing active 30-day token or issue a new 30-day token.
     */
    public function ensureActiveToken(bool $forceRegenerate = false): string
    {
        if (! $forceRegenerate && $this->hasActiveToken()) {
            $existing = $this->getPlainToken();
            if ($existing) {
                $this->update(['login_token_sent_at' => now()]);
                return $existing;
            }
        }

        // Readable 8-char uppercase alphanumeric code (excluding ambiguous 0/O/1/I)
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $raw = '';
        for ($i = 0; $i < 8; $i++) {
            $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $formatted = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);

        $this->update([
            'login_token_encrypted' => Crypt::encryptString($formatted),
            'login_token_expires_at' => now()->addDays(30),
            'login_token_sent_at' => now(),
        ]);

        return $formatted;
    }

    public function verifyToken(string $candidate): bool
    {
        if (! $this->hasActiveToken()) {
            return false;
        }

        $plain = $this->getPlainToken();
        if (! $plain) {
            return false;
        }

        $normPlain = strtoupper(preg_replace('/[^A-Z0-9]/', '', $plain) ?? '');
        $normCandidate = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($candidate)) ?? '');

        return $normPlain !== '' && hash_equals($normPlain, $normCandidate);
    }
}
