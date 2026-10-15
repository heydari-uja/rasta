<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Member;

class AttachmentPolicy
{
    public function update(Member $member, Attachment $record): bool
    {
        return $member->active && $record->member_id === $member->id;
    }

    public function delete(Member $member, Attachment $record): bool
    {
        return $this->update($member, $record);
    }
}
