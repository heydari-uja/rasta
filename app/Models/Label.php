<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['created_by'];

    public function getCreatedByAttribute(): int
    {
        return $this->attributes['member_id'];
    }

    public function setCreatedByAttribute(int $value): void
    {
        $this->attributes['member_id'] = $value;
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
