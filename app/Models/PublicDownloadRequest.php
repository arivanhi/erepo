<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicDownloadRequest extends Model
{
    protected $fillable = [
        'type',
        'capstone_project_id',
        'script_project_id',
        'requester_name',
        'requester_email',
        'purpose',
        'status',
        'approval_token',
        'download_code',
        'download_url_token',
        'expires_at',
        'approved_by_email',
        'approved_at',
        'downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    public function capstoneProject()
    {
        return $this->belongsTo(CapstoneProject::class);
    }

    public function scriptProject()
    {
        return $this->belongsTo(ScriptProject::class);
    }

    public function isTokenValid(): bool
    {
        return $this->status === 'approved'
            && ! empty($this->download_code)
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
