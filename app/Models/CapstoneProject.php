<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CapstoneProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'academic_year',
        'group_id',
        'capstone_team_id',
        'status',
        'ai_check_status',
        'ai_check_result',
        'ai_checked_at',
        'revision_note',
        'acceptance_note',
        'reviewed_by',
        'reviewer_role',
        'reviewed_at',
        'document_path',
        'user_id',
        'dosen_id',
    ];

    protected function casts(): array
    {
        return [
            'ai_check_result' => 'array',
            'ai_checked_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dosen()
    {
        return $this->belongsTo(User::class, 'dosen_id');
    }

    public function team()
    {
        return $this->belongsTo(CapstoneTeam::class, 'capstone_team_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'capstone_project_user')->withTimestamps();
    }

    public function isMember(int $userId): bool
    {
        if ((int) $this->user_id === $userId) {
            return true;
        }

        if ($this->relationLoaded('members')) {
            return $this->members->contains('id', $userId);
        }

        return $this->members()->where('users.id', $userId)->exists();
    }
}
