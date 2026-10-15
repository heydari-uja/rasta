<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['mime_type'];

    public function getMimeTypeAttribute(): string
    {
        return $this->attributes['mime'];
    }

    public function setMimeTypeAttribute(string $value): void
    {
        $this->attributes['mime'] = $value;
    }

    protected $hidden = ['path', 'file_name'];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
