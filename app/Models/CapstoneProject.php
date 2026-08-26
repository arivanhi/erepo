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
        'status',
        'document_path',
        'user_id',
        'dosen_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dosen()
    {
        return $this->belongsTo(User::class, 'dosen_id');
    }
}
