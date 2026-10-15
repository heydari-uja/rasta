<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStage extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['title'];

    public function getTitleAttribute(): string
    {
        return $this->attributes['name'];
    }

    public function setTitleAttribute(string $value): void
    {
        $this->attributes['name'] = $value;
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'product_stage_id');
    }
}
