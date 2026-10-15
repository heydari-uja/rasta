<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['read_at' => 'datetime'];

    public function author()
    {
        return $this->belongsTo(Member::class, 'author_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }
}
