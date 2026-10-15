<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Member extends Authenticatable
{
    protected $attributes = ['active' => true, 'color' => '#e5ddf7'];

    protected $guarded = ['id'];

    protected $hidden = ['remember_token', 'avatar_path'];

    protected $casts = ['active' => 'boolean'];

    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? route('avatar', ['member' => $this->id]) : null;
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_members');
    }
}
