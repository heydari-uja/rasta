<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Task;
use Tests\WorkspaceCase;

class StageTest extends WorkspaceCase
{
    public function test_product_order_persists_and_rejects_partial_or_duplicate_lists(): void
    {
        $p = $this->project();
        $ids = $p->stages->pluck('id')->reverse()->values()->all();
        $this->putJson("/api/projects/$p->id/stages/product/order", ['ids' => $ids])->assertOk();
        $this->assertSame($ids, $p->stages()->pluck('id')->all());
        $this->putJson("/api/projects/$p->id/stages/product/order", ['ids' => [$ids[0], $ids[0]]])->assertUnprocessable();
        $this->putJson("/api/projects/$p->id/stages/product/order", ['ids' => [$ids[0]]])->assertUnprocessable();
    }

    public function test_personal_path_reorders_only_its_own_steps(): void
    {
        $p = $this->project();
        $ids = [];
        foreach (['یک', 'دو'] as $name) {
            $ids[] = $this->postJson("/api/projects/$p->id/stages", ['title' => $name, 'member_id' => $this->member->id, 'product_stage_id' => $p->stages()->first()->id])->assertCreated()->json('id');
        }
        $ids = array_reverse($ids);
        $this->putJson("/api/projects/$p->id/stages/member/order", ['ids' => $ids, 'member_id' => $this->member->id])->assertOk();
        $this->assertSame($ids, $p->memberStages()->pluck('id')->all());
    }

    public function test_my_task_columns_can_be_ordered_across_projects(): void
    {
        $first = $this->project();
        $second = $this->project();
        $a = $this->postJson("/api/projects/$first->id/stages", ['title' => 'ستون پروژه اول', 'member_id' => $this->member->id, 'product_stage_id' => $first->stages()->first()->id])->assertCreated()->json('id');
        $b = $this->postJson("/api/projects/$second->id/stages", ['title' => 'ستون پروژه دوم', 'member_id' => $this->member->id, 'product_stage_id' => $second->stages()->first()->id])->assertCreated()->json('id');

        $this->putJson('/api/member-stages/order', ['ids' => [$b, $a]])->assertOk();

        $this->assertDatabaseHas('member_stages', ['id' => $b, 'position' => 0]);
        $this->assertDatabaseHas('member_stages', ['id' => $a, 'position' => 1]);
        $this->putJson('/api/member-stages/order', ['ids' => [$a]])->assertUnprocessable();
    }

    public function test_active_member_can_create_own_project_column_and_is_attached_to_project(): void
    {
        $project = $this->project();
        $other = Member::create(['name' => 'عضو دوم', 'job_title' => 'طراح']);
        $this->actingAs($other);

        $stageId = $this->postJson("/api/projects/$project->id/stages", [
            'title' => 'ستون عضو دوم',
            'member_id' => $other->id,
            'product_stage_id' => $project->stages()->first()->id,
        ])->assertCreated()->json('id');

        $this->assertDatabaseHas('project_members', ['project_id' => $project->id, 'member_id' => $other->id]);
        $this->assertDatabaseHas('member_stages', ['id' => $stageId, 'project_id' => $project->id, 'member_id' => $other->id]);

        $this->postJson("/api/projects/$project->id/stages", [
            'title' => 'ستون جعلی',
            'member_id' => $this->member->id,
            'product_stage_id' => $project->stages()->first()->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('member_id');
    }

    public function test_nonempty_stage_cannot_be_deleted_and_empty_one_can(): void
    {
        $p = $this->project();
        $this->postJson('/api/tasks', $this->taskPayload($p))->assertCreated();
        $this->deleteJson("/api/projects/$p->id/stages/product/".$p->stages[0]->id)->assertUnprocessable();
        $this->deleteJson("/api/projects/$p->id/stages/product/".$p->stages[1]->id)->assertNoContent();
    }

    public function test_cross_project_order_and_invalid_dates_are_rejected(): void
    {
        $p = $this->project();
        $other = $this->project();
        $this->putJson("/api/projects/$p->id/stages/product/order", ['ids' => $other->stages->pluck('id')->all()])->assertUnprocessable();
        $this->postJson("/api/projects/$p->id/stages", ['title' => 'مرحله', 'start_date' => '2026-10-02', 'end_date' => '2026-10-01'])->assertUnprocessable();
    }

    public function test_deleting_columns_can_transfer_existing_tasks_to_a_destination(): void
    {
        $p = $this->project();
        $payload = $this->taskPayload($p);
        $task = $this->postJson('/api/tasks', $payload)->assertCreated()->json();
        $productTarget = $p->stages()->whereKeyNot($task['product_stage_id'])->first();

        $this->deleteJson('/api/projects/'.$p->id.'/stages/product/'.$task['product_stage_id'], ['transfer_stage_id' => $productTarget->id])->assertNoContent();
        $this->assertDatabaseHas('tasks', ['id' => $task['id'], 'product_stage_id' => $productTarget->id, 'member_stage_id' => $task['member_stage_id']]);

        $memberSource = $p->memberStages()->create(['member_id' => $this->member->id, 'product_stage_id' => $productTarget->id, 'name' => 'مبدأ شخصی', 'position' => 1]);
        $memberTarget = $p->memberStages()->create(['member_id' => $this->member->id, 'product_stage_id' => $productTarget->id, 'name' => 'مقصد شخصی', 'position' => 2]);
        Task::findOrFail($task['id'])->update(['member_stage_id' => $memberSource->id]);

        $this->deleteJson('/api/projects/'.$p->id.'/stages/member/'.$memberSource->id, ['transfer_stage_id' => $memberTarget->id])->assertNoContent();
        $this->assertDatabaseHas('tasks', ['id' => $task['id'], 'member_stage_id' => $memberTarget->id, 'product_stage_id' => $productTarget->id]);
    }

    public function test_completed_tasks_do_not_block_column_deletion_and_remain_in_database(): void
    {
        $p = $this->project();
        $task = $this->postJson('/api/tasks', $this->taskPayload($p))->assertCreated()->json();
        $this->patchJson('/api/tasks/'.$task['id'].'/complete', ['completed' => true])->assertOk();

        $memberStage = $p->memberStages()->create([
            'member_id' => $this->member->id,
            'product_stage_id' => $task['product_stage_id'],
            'name' => 'ستون تکمیل‌شده',
            'position' => 1,
        ]);
        Task::findOrFail($task['id'])->update(['member_stage_id' => $memberStage->id]);

        $this->deleteJson('/api/projects/'.$p->id.'/stages/member/'.$memberStage->id)->assertNoContent();
        $this->assertDatabaseHas('tasks', ['id' => $task['id'], 'member_stage_id' => null]);

        $this->deleteJson('/api/projects/'.$p->id.'/stages/product/'.$task['product_stage_id'])->assertNoContent();
        $this->assertDatabaseHas('tasks', ['id' => $task['id'], 'product_stage_id' => null]);
        $this->assertNotNull(Task::findOrFail($task['id'])->completed_at);
    }
}
