<?php
namespace App\Policies;
use App\Models\Member;
use App\Models\Comment;
class CommentPolicy {
 public function update(Member $member, Comment $record): bool { return $member->active && $record->member_id === $member->id; }
 public function delete(Member $member, Comment $record): bool { return $this->update($member,$record); }
}
