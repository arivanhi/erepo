<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'identity_number'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function capstoneProjects()
    {
        return $this->belongsToMany(CapstoneProject::class, 'capstone_project_user')->withTimestamps();
    }

    public function capstoneTeams()
    {
        return $this->belongsToMany(CapstoneTeam::class, 'capstone_team_user')->withTimestamps();
    }

    public function isRepositoryAdmin(): bool
    {
        return in_array($this->role, ['admin', 'koordinator_ta'], true);
    }
}
