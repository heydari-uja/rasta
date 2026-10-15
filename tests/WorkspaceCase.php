<?php
namespace Tests;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
abstract class WorkspaceCase extends TestCase {
 use RefreshDatabase;
 protected Member $member;
 protected function setUp(): void { parent::setUp(); $hash=Hash::make(bin2hex(random_bytes(24))); config(['uja.password_hash'=>$hash]); $this->withSession(['shared_access'=>hash('sha256',$hash)]); $this->member=Member::create(['name'=>'عضو آزمون','job_title'=>'طراح']);$this->actingAs($this->member); }
 protected function payload(): array { return ['name'=>'پروژه آزمون','description'=>'شرح','color'=>'#d29268','goal'=>['title'=>'نتیجه روشن','description'=>'شرح هدف','success_criteria'=>'معیار قابل سنجش','start_date'=>'2026-10-01','end_date'=>'2026-11-01'],'stages'=>[['title'=>'مرحله اول'],['title'=>'مرحله دوم']]]; }
 protected function project(): \App\Models\Project { $id=$this->postJson('/api/projects',$this->payload())->assertCreated()->json('id');return \App\Models\Project::findOrFail($id); }
 protected function taskPayload($p): array { return ['project_id'=>$p->id,'stage_id'=>$p->stages->first()->id,'assigned_to'=>$this->member->id,'title'=>'تسک آزمون','start_date'=>'2026-10-01','due_date'=>'2026-10-31','members'=>[],'labels'=>[],'subtasks'=>[]]; }
}
