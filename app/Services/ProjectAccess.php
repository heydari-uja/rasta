<?php
namespace App\Services;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
class ProjectAccess {
 public static function check(Project $project, string $action='view'): string {
  Gate::authorize($action==='view'?'view':'update',$project); return 'member';
 }
}
