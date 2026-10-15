<?php
namespace Tests\Feature;
use Tests\WorkspaceCase;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
class CollaborationTest extends WorkspaceCase {
 public function test_comments_author_only_and_notices_can_be_read(): void {
  $p=$this->project();$v=$this->taskPayload($p);$id=$this->postJson('/api/tasks',$v)->json('id');
  $other=Member::create(['name'=>'نویسنده','job_title'=>'طراح']);$this->actingAs($other);
  $cid=$this->postJson("/api/tasks/$id/comments",['body'=>'نظر آزمون'])->assertCreated()->json('id');
  $this->putJson("/api/comments/$cid",['body'=>'نظر اصلاح شده'])->assertOk();
  $this->actingAs($this->member)->deleteJson("/api/comments/$cid")->assertForbidden();
  $this->putJson("/api/comments/$cid",['body'=>'غیرمجاز'])->assertForbidden();
  $this->getJson('/api/workspace')->assertJsonPath('notices.0.type','comment');
  $this->postJson('/api/notices/read')->assertOk();$this->assertDatabaseMissing('notices',['member_id'=>$this->member->id,'read_at'=>null]);
  $this->actingAs($other)->deleteJson("/api/comments/$cid")->assertNoContent();
 }
 public function test_attachments_private_size_limited_and_author_only(): void {
  Storage::fake('local');$p=$this->project();$id=$this->postJson('/api/tasks',$this->taskPayload($p))->json('id');
  $aid=$this->postJson("/api/tasks/$id/attachments",['file'=>UploadedFile::fake()->create('note.txt',2,'text/plain')])->assertCreated()->assertJsonMissingPath('path')->json('id');
  $this->get("/attachments/$aid")->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
  $this->postJson("/api/tasks/$id/attachments",['file'=>UploadedFile::fake()->create('large.txt',5121,'text/plain')])->assertUnprocessable()->assertJsonValidationErrors('file');
  $other=Member::create(['name'=>'همکار فایل','job_title'=>'طراح']);$this->actingAs($other)->deleteJson("/api/attachments/$aid")->assertForbidden();
  $this->actingAs($this->member)->deleteJson("/api/attachments/$aid")->assertNoContent();Storage::disk('local')->assertDirectoryEmpty('attachments');
  $this->post('/logout');$this->get("/attachments/$aid")->assertRedirect('/login');
 }
 public function test_assignment_and_edit_notices_go_to_owner(): void {
  $p=$this->project();$other=Member::create(['name'=>'مسئول دیگر','job_title'=>'طراح']);$p->members()->attach($other);
  $v=$this->taskPayload($p);$v['assigned_to']=$other->id;$id=$this->postJson('/api/tasks',$v)->assertCreated()->json('id');
  $this->assertDatabaseHas('notices',['member_id'=>$other->id,'type'=>'assigned']);
  $v['title']='اصلاح';$this->putJson("/api/tasks/$id",$v)->assertOk();$this->assertDatabaseHas('notices',['member_id'=>$other->id,'type'=>'edited']);
 }
}
