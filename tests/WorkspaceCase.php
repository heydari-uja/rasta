<?php

namespace Tests;

use App\Models\Member;
use App\Models\Project;
use App\Models\WorkspaceSecurity;
use App\Services\PinSecurity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

abstract class WorkspaceCase extends TestCase
{
    use RefreshDatabase;

    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $hash = Hash::make('2468');
        WorkspaceSecurity::singleton()->update([
            'member_pin_hash' => $hash,
            'admin_pin_configured_at' => now(),
        ]);
        $admin = (new Member)->forceFill([
            'name' => 'مدیر آزمون',
            'job_title' => 'مدیر',
            'role' => 'admin',
            'pin_hash' => Hash::make('8642'),
        ]);
        $admin->save();
        $this->withSession(['member_pin_fingerprint' => PinSecurity::fingerprint($hash)]);
        $this->member = Member::create(['name' => 'عضو آزمون', 'job_title' => 'طراح']);
        $this->actingAs($this->member);
    }

    protected function payload(): array
    {
        return ['name' => 'پروژه آزمون', 'description' => 'شرح', 'color' => 'project-marker-1', 'goal' => ['title' => 'نتیجه روشن', 'description' => 'شرح هدف', 'success_criterion' => 'معیار قابل سنجش', 'start_date' => '2026-10-01', 'target_date' => '2026-11-01'], 'stages' => [['title' => 'مرحله اول'], ['title' => 'مرحله دوم']]];
    }

    protected function project(): Project
    {
        $id = $this->postJson('/api/projects', $this->payload())->assertCreated()->json('id');

        return Project::findOrFail($id);
    }

    protected function taskPayload($p): array
    {
        $stage = $p->memberStages()->where('member_id', $this->member->id)->first()
            ?? $p->memberStages()->create(['member_id' => $this->member->id, 'product_stage_id' => $p->stages()->first()->id, 'name' => 'مرحله من', 'position' => 0]);

        return ['project_id' => $p->id, 'member_stage_id' => $stage->id, 'product_stage_id' => $p->stages()->orderBy('position')->first()->id, 'title' => 'تسک آزمون', 'start_date' => '2026-10-01', 'due_date' => '2026-10-31', 'labels' => [], 'subtasks' => []];
    }
}
