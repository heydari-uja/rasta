<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class Member extends Authenticatable
{
    protected $guarded = ['id'];
    protected $hidden = ['remember_token'];
    protected $casts = ['active' => 'boolean'];
    public function projects() { return $this->belongsToMany(Project::class, 'project_members'); }
}
