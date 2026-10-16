<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\Task;

class TaskPolicy
{
    public function view(Member $member, Task $task): bool
    {
        return $member->active;
    }

    public function update(Member $member, Task $task): bool
    {
        return $member->active && (int) $task->created_by === (int) $member->id;
    }

    public function delete(Member $member, Task $task): bool
    {
        return $this->update($member, $task);
    }
}
