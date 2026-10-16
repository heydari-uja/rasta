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

    public function test_another_member_can_view_and_comment_but_cannot_mutate_task(): void
    {
        $project = $this->project();
        $payload = $this->taskPayload($project);
        $task = $this->postJson('/api/tasks', $payload)->assertCreated()->json();
        $other = Member::create(['name' => 'سارا آزمون', 'job_title' => 'طراح']);
        $project->members()->attach($other->id);

        $payload['title'] = 'ویرایش توسط سارا';
        $this->actingAs($other);
        $this->getJson('/api/tasks/'.$task['id'])->assertOk()->assertJsonPath('title', 'تسک آزمون');
        $this->postJson('/api/tasks/'.$task['id'].'/comments', ['body' => 'فقط نظر'])->assertCreated();
        $this->putJson('/api/tasks/'.$task['id'], $payload)->assertForbidden();
        $this->patchJson('/api/tasks/'.$task['id'].'/complete', ['completed' => true])->assertForbidden();
        $this->patchJson('/api/tasks/'.$task['id'].'/product-stage', ['product_stage_id' => $project->stages()->first()->id])->assertForbidden();
        $this->deleteJson('/api/tasks/'.$task['id'])->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task['id'],
            'title' => 'تسک آزمون',
            'created_by' => $this->member->id,
        ]);
    }

    public function test_project_task_requires_product_stage_and_stage_is_independent_from_column(): void
    {
        $project = $this->project();
        $memberStage = $project->memberStages()->where('member_id', $this->member->id)->first()
            ?? $project->memberStages()->create([
                'member_id' => $this->member->id,
                'product_stage_id' => $project->stages()->orderBy('position')->first()->id,
                'name' => 'ستون من',
                'position' => 0,
            ]);
        $productStages = $project->stages()->orderBy('position')->get();
        $selectedProductStage = $productStages->last();

        $payload = [
            'project_id' => $project->id,
            'member_stage_id' => $memberStage->id,
            'title' => 'تسک تازه',
            'start_date' => '2026-10-01',
            'due_date' => '2026-10-31',
            'labels' => [],
            'subtasks' => [],
        ];
        $this->postJson('/api/tasks', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_stage_id');

        $payload['product_stage_id'] = $selectedProductStage->id;
        $this->postJson('/api/tasks', $payload)
            ->assertCreated()
            ->assertJsonPath('member_stage_id', $memberStage->id)
            ->assertJsonPath('product_stage_id', $selectedProductStage->id);
    }

    public function test_personal_stage_can_be_created_then_task_can_choose_another_product_stage(): void
    {
        $project = $this->project();
        $columnStage = $project->stages()->orderBy('position')->first();
        $selectedProductStage = $project->stages()->orderBy('position')->get()->last();
        $stage = $this->postJson('/api/projects/'.$project->id.'/stages', [
            'title' => 'مرحله شخصی تازه',
            'member_id' => $this->member->id,
            'product_stage_id' => $columnStage->id,
        ])->assertCreated()->json();
        $payload = [
            'project_id' => $project->id,
            'member_stage_id' => $stage['id'],
            'product_stage_id' => $selectedProductStage->id,
            'title' => 'تسک تازه',
            'start_date' => '2026-10-01',
            'due_date' => '2026-10-31',
            'labels' => [],
            'subtasks' => [],
        ];
        $this->postJson('/api/tasks', $payload)
            ->assertCreated()
            ->assertJsonPath('member_stage_id', $stage['id'])
            ->assertJsonPath('product_stage_id', $selectedProductStage->id);
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
            ->assertOk()->assertJsonPath('product_stage_id', $target->id)->assertJsonPath('member_stage_id', $task['member_stage_id']);
    }

    public function test_task_can_be_private_without_project_and_is_visible_only_to_owner(): void
    {
        $stage = $this->postJson('/api/member-stages', [
            'title' => 'مرحله شخصی',
            'description' => 'فقط برای کارهای شخصی',
        ])->assertCreated()->json();

        $this->assertNull($stage['project_id']);
        $this->assertNull($stage['product_stage_id']);

        $task = $this->postJson('/api/tasks', [
            'project_id' => null,
            'member_stage_id' => $stage['id'],
            'title' => 'کار خصوصی',
            'start_date' => '2026-10-16',
            'due_date' => '2026-10-16',
            'labels' => [],
            'subtasks' => [],
        ])->assertCreated()
            ->assertJsonPath('project_id', null)
            ->assertJsonPath('product_stage_id', null)
            ->json();

        $this->getJson('/api/workspace')
            ->assertJsonPath('personalStages.0.id', $stage['id'])
            ->assertJsonPath('tasks.0.id', $task['id']);

        $other = Member::create(['name' => 'عضو دیگر', 'job_title' => 'طراح']);
        $this->actingAs($other)->getJson('/api/tasks/'.$task['id'])->assertNotFound();
        $this->actingAs($other)->getJson('/api/workspace')->assertJsonCount(0, 'personalStages')->assertJsonCount(0, 'tasks');
    }

    public function test_my_tasks_drag_order_persists_and_can_move_between_project_and_private_columns(): void
    {
        $project = $this->project();
        $payload = $this->taskPayload($project);
        $first = $this->postJson('/api/tasks', $payload)->assertCreated()->json();
        $payload['title'] = 'تسک دوم';
        $second = $this->postJson('/api/tasks', $payload)->assertCreated()->json();
        $sourceStage = $first['member_stage_id'];

        $this->putJson('/api/tasks/reorder', [
            'task_id' => $second['id'],
            'target_stage_id' => $sourceStage,
            'target_ids' => [$second['id'], $first['id']],
            'source_ids' => [$second['id'], $first['id']],
        ])->assertOk();
        $this->assertDatabaseHas('tasks', ['id' => $second['id'], 'member_stage_id' => $sourceStage, 'position' => 0]);
        $this->assertDatabaseHas('tasks', ['id' => $first['id'], 'member_stage_id' => $sourceStage, 'position' => 1]);

        $projectStage = $this->postJson('/api/projects/'.$project->id.'/stages', [
            'title' => 'ستون دوم',
            'member_id' => $this->member->id,
            'product_stage_id' => $project->stages()->first()->id,
        ])->assertCreated()->json();

        $this->putJson('/api/tasks/reorder', [
            'task_id' => $second['id'],
            'target_stage_id' => $projectStage['id'],
            'target_ids' => [$second['id']],
            'source_ids' => [$first['id']],
        ])->assertOk()->assertJsonPath('member_stage_id', $projectStage['id']);
        $this->assertDatabaseHas('tasks', ['id' => $second['id'], 'project_id' => $project->id, 'member_stage_id' => $projectStage['id'], 'position' => 0]);
        $this->assertDatabaseHas('tasks', ['id' => $first['id'], 'member_stage_id' => $sourceStage, 'position' => 0]);

        $privateStage = $this->postJson('/api/member-stages', ['title' => 'خصوصی'])->assertCreated()->json();
        $this->putJson('/api/tasks/reorder', [
            'task_id' => $second['id'],
            'target_stage_id' => $privateStage['id'],
            'target_ids' => [$second['id']],
            'source_ids' => [],
        ])->assertOk()
            ->assertJsonPath('project_id', null)
            ->assertJsonPath('product_stage_id', null)
            ->assertJsonPath('member_stage_id', $privateStage['id']);
        $this->assertDatabaseHas('tasks', ['id' => $second['id'], 'project_id' => null, 'product_stage_id' => null, 'member_stage_id' => $privateStage['id'], 'position' => 0]);
    }
}
