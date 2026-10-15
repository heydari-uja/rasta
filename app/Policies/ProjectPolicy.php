<?php
namespace App\Policies;
use App\Models\Member;
use App\Models\Project;
class ProjectPolicy {
 public function view(Member $member, Project $project): bool { return $member->active; }
 public function update(Member $member, Project $project): bool { return $member->active; }
}
