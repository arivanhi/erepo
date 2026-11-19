<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScriptProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'academic_year',
        'group_id',
        'status',
        'revision_note',
        'reviewed_by',
        'reviewer_role',
        'reviewed_at',
        'archive_path',
        'user_id',
        'capstone_project_id',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function capstoneProject()
    {
        return $this->belongsTo(CapstoneProject::class, 'capstone_project_id');
    }
}
