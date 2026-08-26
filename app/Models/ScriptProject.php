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
        'archive_path',
        'user_id',
        'capstone_project_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function capstoneProject()
    {
        return $this->belongsTo(CapstoneProject::class, 'capstone_project_id');
    }
}
