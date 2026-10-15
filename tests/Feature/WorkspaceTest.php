<?php
namespace Tests\Feature;
use Tests\WorkspaceCase;
class WorkspaceTest extends WorkspaceCase {
 public function test_all_views_render_and_empty_workspace_has_no_fixtures(): void {
  foreach(['/dashboard','/projects','/tasks','/labels','/settings'] as $route)$this->get($route)->assertOk()->assertSee('dir="rtl"',false)->assertSee('lang="fa"',false);
  $this->getJson('/api/workspace')->assertJsonCount(0,'projects')->assertJsonCount(0,'tasks')->assertJsonCount(0,'labels')->assertJsonCount(0,'notices');
 }
 public function test_settings_have_name_and_job_title_without_personal_credentials(): void {
  $this->putJson('/api/settings',['name'=>'نام تازه','job_title'=>'پژوهشگر'])->assertOk()->assertJsonPath('job_title','پژوهشگر')->assertJsonMissingPath('email')->assertJsonMissingPath('password');
 }
 public function test_workspace_exposes_relations_for_my_tasks_and_search(): void {
  $p=$this->project();$this->postJson('/api/tasks',$this->taskPayload($p))->assertCreated();
  $this->getJson('/api/workspace')->assertOk()->assertJsonPath('tasks.0.assigned_to',$this->member->id)->assertJsonPath('tasks.0.assignee.name',$this->member->name)->assertJsonCount(2,'projects.0.stages');
 }
}
