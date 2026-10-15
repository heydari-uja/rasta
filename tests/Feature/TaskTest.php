<?php

namespace Tests\Feature;

use App\Models\Member;
use Tests\WorkspaceCase;

class TaskTest extends WorkspaceCase
{
    public function test_task_crud_subtasks_labels_members_and_progress(): void
    {
        $p = $this->project();
        $other = Member::create(['name' => 'همکار آزمون', 'job_title' => 'طراح']);
        $this->postJson("/api/projects/$p->id/members", ['member_id' => $other->id])->assertOk();
        $label = $this->postJson('/api/labels', ['name' => 'طراحی', 'color' => '#9274be', 'project_id' => $p->id])->assertCreated()->json('id');
        $v = $this->taskPayload($p);
        $v['labels'] = [$label];
        $v['members'] = [$other->id];
        $v['subtasks'] = [['title' => 'الف', 'completed' => true], ['title' => 'ب', 'completed' => false]];
        $r = $this->postJson('/api/tasks', $v)->assertCreated();
        $id = $r->json('id');
        $this->assertCount(2, $r->json('subtasks'));
        $v['subtasks'] = array_reverse(array_map(fn ($s) => ['id' => $s['id'], 'title' => $s['title'], 'completed' => (bool) $s['completed_at']], $r->json('subtasks')));
        $v['title'] = 'ویرایش';
        $this->putJson("/api/tasks/$id", $v)->assertOk()->assertJsonPath('subtasks.0.title', 'ب');
        $this->patchJson("/api/tasks/$id/complete", ['completed' => true])->assertOk();
        $this->getJson('/api/workspace')->assertJsonPath('projects.0.completed_count', 1);
        $this->patchJson("/api/tasks/$id/complete", ['completed' => false])->assertOk();
        $this->getJson('/api/workspace')->assertJsonPath('projects.0.completed_count', 0);
        $this->deleteJson("/api/tasks/$id")->assertNoContent();
        $this->assertDatabaseCount('subtasks', 0);
        $this->assertDatabaseCount('task_members', 0);
    }

    public function test_cross_project_stage_member_and_label_are_rejected(): void
    {
        $p = $this->project();
        $other = $this->project();
        $v = $this->taskPayload($p);
        $foreignStage = $other->memberStages()->create(['member_id' => $this->member->id, 'product_stage_id' => $other->stages()->first()->id, 'name' => 'مرحله بیرونی', 'position' => 0]);
        $v['member_stage_id'] = $foreignStage->id;
        $this->postJson('/api/tasks', $v)->assertUnprocessable()->assertJsonValidationErrors('member_stage_id');
        $v = $this->taskPayload($p);
        $v['owner_id'] = Member::create(['name' => 'بیرون', 'job_title' => 'طراح'])->id;
        $this->postJson('/api/tasks', $v)->assertUnprocessable()->assertJsonValidationErrors('owner_id');
        $label = $this->postJson('/api/labels', ['name' => 'محدود', 'color' => '#9274be', 'project_id' => $other->id])->json('id');
        $v = $this->taskPayload($p);
        $v['labels'] = [$label];
        $this->postJson('/api/tasks', $v)->assertUnprocessable()->assertJsonValidationErrors('labels');
    }

    public function test_dates_and_other_tasks_subtask_ids_are_validated(): void
    {
        $p = $this->project();
        $v = $this->taskPayload($p);
        $v['due_date'] = '2025-01-01';
        $this->postJson('/api/tasks', $v)->assertUnprocessable();
        $v = $this->taskPayload($p);
        $v['subtasks'] = [['title' => 'جزء', 'completed' => false]];
        $first = $this->postJson('/api/tasks', $v)->assertCreated()->json();
        $v['subtasks'][0]['id'] = $first['subtasks'][0]['id'];
        $this->postJson('/api/tasks', $v)->assertNotFound();
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_personal_labels_are_private_and_can_be_edited_deleted(): void
    {
        $id = $this->postJson('/api/labels', ['name' => 'شخصی', 'color' => '#9274be'])->json('id');
        $this->putJson("/api/labels/$id", ['name' => 'ویرایش', 'color' => '#527fa9'])->assertOk();
        $other = Member::create(['name' => 'دیگری', 'job_title' => 'طراح']);
        $this->actingAs($other)->getJson('/api/workspace')->assertJsonCount(0, 'labels');
        $this->deleteJson("/api/labels/$id")->assertNotFound();
        $this->actingAs($this->member)->deleteJson("/api/labels/$id")->assertNoContent();
    }

    public function test_another_member_can_edit_task_using_assignees_personal_stage(): void
    {
        $p = $this->project();
        $payload = $this->taskPayload($p);
        $task = $this->postJson('/api/tasks', $payload)->assertCreated()->json();
        $other = Member::create(['name' => 'سارا آزمون', 'job_title' => 'طراح']);
        $p->members()->attach($other->id);

        $payload['title'] = 'ویرایش توسط سارا';
        $this->actingAs($other)->putJson('/api/tasks/'.$task['id'], $payload)
            ->assertOk()
            ->assertJsonPath('title', 'ویرایش توسط سارا')
            ->assertJsonPath('member_stage_id', $payload['member_stage_id']);
    }

    public function test_new_task_without_personal_stage_returns_clear_validation_error(): void
    {
        $p = $this->project();
        $payload = ['project_id' => $p->id, 'owner_id' => $this->member->id, 'title' => 'بدون مرحله', 'start_date' => '2026-10-01', 'due_date' => '2026-10-31', 'members' => [], 'labels' => [], 'subtasks' => []];

        $this->postJson('/api/tasks', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member_stage_id');
    }

    public function test_personal_stage_can_be_created_then_selected_for_new_task(): void
    {
        $p = $this->project();
        $stage = $this->postJson('/api/projects/'.$p->id.'/stages', [
            'title' => 'مرحله شخصی تازه',
            'member_id' => $this->member->id,
            'product_stage_id' => $p->stages()->first()->id,
        ])->assertCreated()->json();
        $payload = ['project_id' => $p->id, 'member_stage_id' => $stage['id'], 'owner_id' => $this->member->id, 'title' => 'تسک تازه', 'start_date' => '2026-10-01', 'due_date' => '2026-10-31', 'members' => [], 'labels' => [], 'subtasks' => []];

        $this->postJson('/api/tasks', $payload)
            ->assertCreated()
            ->assertJsonPath('member_stage_id', $stage['id'])
            ->assertJsonPath('product_stage_id', $stage['product_stage_id']);
    }

    public function test_personal_label_is_allowed_but_other_project_label_is_not(): void
    {
        $p = $this->project();
        $personal = $this->postJson('/api/labels', ['name' => 'شخصی', 'color' => '#9274be'])->assertCreated()->json('id');
        $payload = $this->taskPayload($p);
        $payload['labels'] = [$personal];
        $this->postJson('/api/tasks', $payload)->assertCreated();
    }

    public function test_product_board_move_updates_product_stage_without_reverting(): void
    {
        $p = $this->project();
        $payload = $this->taskPayload($p);
        $task = $this->postJson('/api/tasks', $payload)->assertCreated()->json();
        $target = $p->stages()->whereKeyNot($task['product_stage_id'])->first();

        $this->patchJson('/api/tasks/'.$task['id'].'/product-stage', ['product_stage_id' => $target->id])
            ->assertOk()
            ->assertJsonPath('product_stage_id', $target->id)
            ->assertJsonPath('member_stage_id', null);
    }
}
