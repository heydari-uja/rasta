<?php

namespace Tests\Feature;

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
        $this->assertDatabaseHas('tasks', ['id' => $task['id'], 'product_stage_id' => $productTarget->id, 'member_stage_id' => null]);

        $memberSource = $p->memberStages()->create(['member_id' => $this->member->id, 'product_stage_id' => $productTarget->id, 'name' => 'مبدأ شخصی', 'position' => 1]);
        $memberTarget = $p->memberStages()->create(['member_id' => $this->member->id, 'product_stage_id' => $productTarget->id, 'name' => 'مقصد شخصی', 'position' => 2]);
        Task::findOrFail($task['id'])->update(['member_stage_id' => $memberSource->id]);

        $this->deleteJson('/api/projects/'.$p->id.'/stages/member/'.$memberSource->id, ['transfer_stage_id' => $memberTarget->id])->assertNoContent();
        $this->assertDatabaseHas('tasks', ['id' => $task['id'], 'member_stage_id' => $memberTarget->id, 'product_stage_id' => $productTarget->id]);
    }
}
