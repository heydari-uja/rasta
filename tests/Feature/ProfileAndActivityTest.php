<?php

namespace Tests\Feature;

use App\Models\TaskActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\WorkspaceCase;

class ProfileAndActivityTest extends WorkspaceCase
{
    public function test_member_can_upload_private_square_avatar_and_remove_it(): void
    {
        Storage::fake('local');
        $response = $this->post('/api/settings/avatar', ['avatar' => UploadedFile::fake()->image('avatar.png', 900, 600)], ['Accept' => 'application/json'])->assertOk();
        $this->member->refresh();
        $this->assertNotNull($this->member->avatar_path);
        $this->assertStringStartsWith('avatars/', $this->member->avatar_path);
        Storage::disk('local')->assertExists($this->member->avatar_path);
        $this->assertNotNull($response->json('avatar_url'));
        $size = getimagesize(Storage::disk('local')->path($this->member->avatar_path));
        $this->assertSame(512, $size[0]);
        $this->assertSame(512, $size[1]);
        $this->get($this->member->avatar_url)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $path = $this->member->avatar_path;
        $this->deleteJson('/api/settings/avatar')->assertOk()->assertJsonPath('avatar_url', null);
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($this->member->fresh()->avatar_path);
    }

    public function test_avatar_validation_rejects_large_or_unsupported_files(): void
    {
        Storage::fake('local');
        $this->post('/api/settings/avatar', ['avatar' => UploadedFile::fake()->create('large.jpg', 2049, 'image/jpeg')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->post('/api/settings/avatar', ['avatar' => UploadedFile::fake()->create('avatar.gif', 100, 'image/gif')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('avatar');
    }

    public function test_task_history_records_only_title_and_date_changes(): void
    {
        $project = $this->project();
        $payload = $this->taskPayload($project);
        $created = $this->postJson('/api/tasks', $payload)->assertCreated();
        $taskId = $created->json('id');
        $this->assertSame(0, TaskActivity::where('task_id', $taskId)->count());

        $secondStage = $project->memberStages()->create([
            'member_id' => $this->member->id,
            'product_stage_id' => $project->stages()->orderBy('position')->skip(1)->value('id'),
            'name' => 'مرحله دوم من',
            'position' => 1,
        ]);
        $payload['title'] = 'عنوان تازه';
        $payload['start_date'] = '2026-10-02';
        $payload['due_date'] = '2026-11-01';
        $payload['member_stage_id'] = $secondStage->id;
        $this->putJson('/api/tasks/'.$taskId, $payload)->assertOk();

        foreach (['title', 'start_date', 'due_date'] as $type) {
            $this->assertDatabaseHas('task_activities', ['task_id' => $taskId, 'type' => $type, 'member_id' => $this->member->id]);
        }
        foreach (['created', 'stage', 'completed', 'reopened', 'assignee'] as $type) {
            $this->assertDatabaseMissing('task_activities', ['task_id' => $taskId, 'type' => $type]);
        }
        $this->assertSame(3, TaskActivity::where('task_id', $taskId)->count());

        $this->putJson('/api/tasks/'.$taskId, $payload)->assertOk();
        $this->assertSame(3, TaskActivity::where('task_id', $taskId)->count());

        $this->patchJson('/api/tasks/'.$taskId.'/complete', ['completed' => true])->assertOk();
        $this->patchJson('/api/tasks/'.$taskId.'/complete', ['completed' => false])->assertOk();
        $this->assertSame(3, TaskActivity::where('task_id', $taskId)->count());
        $detail = $this->getJson('/api/tasks/'.$taskId)->assertOk();
        $this->assertCount(3, $detail->json('activities'));
    }
}
