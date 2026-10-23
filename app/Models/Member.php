<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Member extends Authenticatable
{
    protected $attributes = ['active' => true, 'color' => 'project-marker-4', 'role' => 'member'];

    protected $fillable = ['name', 'job_title', 'color', 'avatar_path'];

    protected $hidden = ['remember_token', 'avatar_path', 'pin_hash'];

    protected $casts = ['active' => 'boolean'];

    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? route('avatar', ['member' => $this->id, 'v' => substr(hash('sha256', $this->avatar_path), 0, 16)]) : null;
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_members');
    }
}
