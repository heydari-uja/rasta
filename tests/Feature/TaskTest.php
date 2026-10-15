<?php
namespace Tests\Feature;
use Tests\WorkspaceCase;
use App\Models\Member;
class TaskTest extends WorkspaceCase {
 public function test_task_crud_subtasks_labels_members_and_progress(): void {
  $p=$this->project();$other=Member::create(['name'=>'همکار آزمون','job_title'=>'طراح']);
  $this->postJson("/api/projects/$p->id/members",['member_id'=>$other->id])->assertOk();
  $label=$this->postJson('/api/labels',['name'=>'طراحی','color'=>'#9274be','project_id'=>$p->id])->assertCreated()->json('id');
  $v=$this->taskPayload($p);$v['labels']=[$label];$v['members']=[$other->id];$v['subtasks']=[['title'=>'الف','completed'=>true],['title'=>'ب','completed'=>false]];
  $r=$this->postJson('/api/tasks',$v)->assertCreated();$id=$r->json('id');$this->assertCount(2,$r->json('subtasks'));
  $v['subtasks']=array_reverse(array_map(fn($s)=>['id'=>$s['id'],'title'=>$s['title'],'completed'=>(bool)$s['completed_at']],$r->json('subtasks')));$v['title']='ویرایش';
  $this->putJson("/api/tasks/$id",$v)->assertOk()->assertJsonPath('subtasks.0.title','ب');
  $this->patchJson("/api/tasks/$id/complete",['completed'=>true])->assertOk();
  $this->getJson('/api/workspace')->assertJsonPath('projects.0.completed_count',1);
  $this->patchJson("/api/tasks/$id/complete",['completed'=>false])->assertOk();
  $this->getJson('/api/workspace')->assertJsonPath('projects.0.completed_count',0);
  $this->deleteJson("/api/tasks/$id")->assertNoContent();$this->assertDatabaseCount('subtasks',0);$this->assertDatabaseCount('task_members',0);
 }
 public function test_cross_project_stage_member_and_label_are_rejected(): void {
  $p=$this->project();$other=$this->project();$v=$this->taskPayload($p);$v['stage_id']=$other->stages[0]->id;
  $this->postJson('/api/tasks',$v)->assertUnprocessable()->assertJsonValidationErrors('stage_id');
  $v=$this->taskPayload($p);$v['assigned_to']=Member::create(['name'=>'بیرون','job_title'=>'طراح'])->id;
  $this->postJson('/api/tasks',$v)->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
  $label=$this->postJson('/api/labels',['name'=>'محدود','color'=>'#9274be','project_id'=>$other->id])->json('id');$v=$this->taskPayload($p);$v['labels']=[$label];
  $this->postJson('/api/tasks',$v)->assertUnprocessable()->assertJsonValidationErrors('labels');
 }
 public function test_dates_and_other_tasks_subtask_ids_are_validated(): void {
  $p=$this->project();$v=$this->taskPayload($p);$v['due_date']='2025-01-01';$this->postJson('/api/tasks',$v)->assertUnprocessable();
  $v=$this->taskPayload($p);$v['subtasks']=[['title'=>'جزء','completed'=>false]];$first=$this->postJson('/api/tasks',$v)->assertCreated()->json();
  $v['subtasks'][0]['id']=$first['subtasks'][0]['id'];$this->postJson('/api/tasks',$v)->assertNotFound();$this->assertDatabaseCount('tasks',1);
 }
 public function test_personal_labels_are_private_and_can_be_edited_deleted(): void {
  $id=$this->postJson('/api/labels',['name'=>'شخصی','color'=>'#9274be'])->json('id');
  $this->putJson("/api/labels/$id",['name'=>'ویرایش','color'=>'#527fa9'])->assertOk();
  $other=Member::create(['name'=>'دیگری','job_title'=>'طراح']);$this->actingAs($other)->getJson('/api/workspace')->assertJsonCount(0,'labels');$this->deleteJson("/api/labels/$id")->assertNotFound();
  $this->actingAs($this->member)->deleteJson("/api/labels/$id")->assertNoContent();
 }
}
