<?php

namespace Tests\Feature;

use App\Models\Member;
use Tests\WorkspaceCase;

class TaskTest extends WorkspaceCase
{
    public function test_task_crud_subtasks_labels_and_progress(): void
    {
        $project = $this->project();
        $label = $this->postJson('/api/labels', ['name' => 'طراحی', 'color' => '#9274be', 'project_id' => $project->id])->assertCreated()->json('id');
        $payload = $this->taskPayload($project);
        $payload['labels'] = [$label];
        $payload['subtasks'] = [['title' => 'الف', 'completed' => true], ['title' => 'ب', 'completed' => false]];
        $response = $this->postJson('/api/tasks', $payload)->assertCreated();
        $taskId = $response->json('id');
        $this->assertCount(2, $response->json('subtasks'));

        $payload['subtasks'] = array_reverse(array_map(fn ($subtask) => [
            'id' => $subtask['id'],
            'title' => $subtask['title'],
            'completed' => (bool) $subtask['completed_at'],
        ], $response->json('subtasks')));
        $payload['title'] = 'ویرایش';
        $this->putJson('/api/tasks/'.$taskId, $payload)->assertOk()->assertJsonPath('subtasks.0.title', 'ب');
        $this->patchJson('/api/tasks/'.$taskId.'/complete', ['completed' => true])->assertOk();
        $this->getJson('/api/workspace')->assertJsonPath('projects.0.completed_count', 1);
        $this->patchJson('/api/tasks/'.$taskId.'/complete', ['completed' => false])->assertOk();
        $this->deleteJson('/api/tasks/'.$taskId)->assertNoContent();
        $this->assertDatabaseCount('subtasks', 0);
    }

    public function test_assignee_is_creator_and_cannot_be_changed(): void
    {
        $project = $this->project();
        $task = $this->postJson('/api/tasks', $this->taskPayload($project))
            ->assertCreated()
            ->assertJsonPath('owner_id', $this->member->id)
            ->json();
        $this->assertDatabaseHas('tasks', ['id' => $task['id'], 'created_by' => $this->member->id, 'owner_id' => $this->member->id]);

        $other = Member::create(['name' => 'سارا', 'job_title' => 'طراح']);
        $project->members()->attach($other->id);
        $payload = $this->taskPayload($project);
        $payload['owner_id'] = $other->id;
        $this->putJson('/api/tasks/'.$task['id'], $payload)->assertUnprocessable()->assertJsonValidationErrors('owner_id');
        $this->assertDatabaseHas('tasks', ['id' => $task['id'], 'owner_id' => $this->member->id]);
    }

    public function test_cross_project_stage_and_label_are_rejected(): void
    {
        $project = $this->project();
        $otherProject = $this->project();
        $payload = $this->taskPayload($project);
        $foreignStage = $otherProject->memberStages()->create([
            'member_id' => $this->member->id,
            'product_stage_id' => $otherProject->stages()->first()->id,
            'name' => 'مرحله بیرونی',
            'position' => 0,
        ]);
        $payload['member_stage_id'] = $foreignStage->id;
        $this->postJson('/api/tasks', $payload)->assertUnprocessable()->assertJsonValidationErrors('member_stage_id');

        $label = $this->postJson('/api/labels', ['name' => 'محدود', 'color' => '#9274be', 'project_id' => $otherProject->id])->json('id');
        $payload = $this->taskPayload($project);
        $payload['labels'] = [$label];
        $this->postJson('/api/tasks', $payload)->assertUnprocessable()->assertJsonValidationErrors('labels');
    }

    public function test_date_range_single_day_and_no_date_are_supported(): void
    {
        $project = $this->project();
        $payload = $this->taskPayload($project);
        $payload['start_date'] = '2026-10-23';
        $payload['due_date'] = '2026-10-30';
        $task = $this->postJson('/api/tasks', $payload)->assertCreated()->json();
        $this->assertDatabaseHas('tasks', ['id' => $task['id'], 'start_date' => '2026-10-23', 'due_date' => '2026-10-30']);

        $payload['start_date'] = '2026-11-01';
        $payload['due_date'] = '2026-11-01';
        $this->putJson('/api/tasks/'.$task['id'], $payload)->assertOk();
        $this->assertDatabaseHas('tasks', ['id' => $task['id'], 'start_date' => '2026-11-01', 'due_date' => '2026-11-01']);

        $payload['start_date'] = null;
        $payload['due_date'] = null;
        $this->putJson('/api/tasks/'.$task['id'], $payload)->assertOk();
        $this->assertDatabaseHas('tasks', ['id' => $task['id'], 'start_date' => null, 'due_date' => null]);
    }

    public function test_invalid_date_order_and_other_tasks_subtask_ids_are_rejected(): void
    {
        $project = $this->project();
        $payload = $this->taskPayload($project);
        $payload['start_date'] = '2026-10-10';
        $payload['due_date'] = '2026-10-01';
        $this->postJson('/api/tasks', $payload)->assertUnprocessable();

        $payload = $this->taskPayload($project);
        $payload['subtasks'] = [['title' => 'جزء', 'completed' => false]];
        $first = $this->postJson('/api/tasks', $payload)->assertCreated()->json();
        $payload['subtasks'][0]['id'] = $first['subtasks'][0]['id'];
        $this->postJson('/api/tasks', $payload)->assertNotFound();
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_personal_labels_are_private_and_can_be_edited_deleted(): void
    {
        $id = $this->postJson('/api/labels', ['name' => 'شخصی', 'color' => '#9274be'])->json('id');
        $this->putJson('/api/labels/'.$id, ['name' => 'ویرایش', 'color' => '#527fa9'])->assertOk();
        $other = Member::create(['name' => 'دیگری', 'job_title' => 'طراح']);
        $this->actingAs($other)->getJson('/api/workspace')->assertJsonCount(0, 'labels');
        $this->deleteJson('/api/labels/'.$id)->assertNotFound();
        $this->actingAs($this->member)->deleteJson('/api/labels/'.$id)->assertNoContent();
    }

    public function test_another_member_can_edit_task_but_creator_and_personal_stage_stay_fixed(): void
    {
        $project = $this->project();
        $payload = $this->taskPayload($project);
        $task = $this->postJson('/api/tasks', $payload)->assertCreated()->json();
        $other = Member::create(['name' => 'سارا آزمون', 'job_title' => 'طراح']);
        $project->members()->attach($other->id);

        $payload['title'] = 'ویرایش توسط سارا';
        $this->actingAs($other)->putJson('/api/tasks/'.$task['id'], $payload)
            ->assertOk()
            ->assertJsonPath('title', 'ویرایش توسط سارا')
            ->assertJsonPath('owner_id', $this->member->id)
            ->assertJsonPath('member_stage_id', $payload['member_stage_id']);
    }

    public function test_new_task_without_personal_stage_returns_clear_validation_error(): void
    {
        $project = $this->project();
        $payload = ['project_id' => $project->id, 'title' => 'بدون مرحله', 'start_date' => '2026-10-01', 'due_date' => '2026-10-31', 'labels' => [], 'subtasks' => []];
        $this->postJson('/api/tasks', $payload)->assertUnprocessable()->assertJsonValidationErrors('member_stage_id');
    }

    public function test_personal_stage_can_be_created_then_selected_for_new_task(): void
    {
        $project = $this->project();
        $stage = $this->postJson('/api/projects/'.$project->id.'/stages', [
            'title' => 'مرحله شخصی تازه',
            'member_id' => $this->member->id,
            'product_stage_id' => $project->stages()->first()->id,
        ])->assertCreated()->json();
        $payload = ['project_id' => $project->id, 'member_stage_id' => $stage['id'], 'title' => 'تسک تازه', 'start_date' => '2026-10-01', 'due_date' => '2026-10-31', 'labels' => [], 'subtasks' => []];
        $this->postJson('/api/tasks', $payload)->assertCreated()->assertJsonPath('member_stage_id', $stage['id'])->assertJsonPath('product_stage_id', $stage['product_stage_id']);
    }

    public function test_personal_label_is_allowed(): void
    {
        $project = $this->project();
        $personal = $this->postJson('/api/labels', ['name' => 'شخصی', 'color' => '#9274be'])->assertCreated()->json('id');
        $payload = $this->taskPayload($project);
        $payload['labels'] = [$personal];
        $this->postJson('/api/tasks', $payload)->assertCreated();
    }

    public function test_product_board_move_updates_product_stage_without_reverting(): void
    {
        $project = $this->project();
        $task = $this->postJson('/api/tasks', $this->taskPayload($project))->assertCreated()->json();
        $target = $project->stages()->whereKeyNot($task['product_stage_id'])->first();
        $this->patchJson('/api/tasks/'.$task['id'].'/product-stage', ['product_stage_id' => $target->id])
            ->assertOk()->assertJsonPath('product_stage_id', $target->id)->assertJsonPath('member_stage_id', null);
    }
}
