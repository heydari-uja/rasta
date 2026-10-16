<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\ProductStage;
use Tests\WorkspaceCase;

class ProjectTest extends WorkspaceCase
{
    public function test_wizard_creates_one_goal_and_ordered_stages_atomically(): void
    {
        $p = $this->project();
        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseCount('goals', 1);
        $this->assertSame([0, 1], $p->stages->pluck('position')->all());
        $this->assertSame('#d29268', $p->color);
        $this->getJson('/api/workspace')->assertOk()->assertJsonPath('projects.0.tasks_count', 0);
    }

    public function test_invalid_wizard_creates_nothing(): void
    {
        $v = $this->payload();
        $v['goal']['target_date'] = '2025-01-01';
        $this->postJson('/api/projects', $v)->assertUnprocessable()->assertJsonValidationErrors('goal.target_date');
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('goals', 0);
    }

    public function test_failure_inside_transaction_rolls_back_project_and_goal(): void
    {
        ProductStage::creating(function () {
            throw new \RuntimeException('test rollback');
        });
        try {
            $this->postJson('/api/projects', $this->payload())->assertStatus(500);
            $this->assertDatabaseCount('projects', 0);
            $this->assertDatabaseCount('goals', 0);
        } finally {
            ProductStage::flushEventListeners();
        }
    }

    public function test_all_active_members_can_edit_goal_without_member_management_route(): void
    {
        $p = $this->project();
        $other = Member::create(['name' => 'همکار', 'job_title' => 'توسعه']);
        $this->actingAs($other)->putJson('/api/projects/'.$p->id.'/goal', $this->payload()['goal'])->assertOk();
        $this->postJson('/api/projects/'.$p->id.'/members', ['member_id' => $other->id])->assertNotFound();
    }

    public function test_project_can_be_edited_and_deleted(): void
    {
        $p = $this->project();
        $this->postJson('/api/tasks', $this->taskPayload($p))->assertCreated();

        $this->putJson('/api/projects/'.$p->id, [
            'name' => 'نام ویرایش‌شده',
            'description' => 'توضیح تازه',
            'color' => '#5877b9',
        ])->assertOk()->assertJsonPath('name', 'نام ویرایش‌شده');
        $this->assertDatabaseHas('projects', ['id' => $p->id, 'name' => 'نام ویرایش‌شده', 'color' => '#5877b9']);

        $this->deleteJson('/api/projects/'.$p->id)->assertNoContent();
        $this->assertDatabaseMissing('projects', ['id' => $p->id]);
        $this->assertDatabaseCount('tasks', 0);
    }
}
