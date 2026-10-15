<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\WorkspaceCase;

class CollaborationTest extends WorkspaceCase
{
    public function test_other_members_comment_creates_notification_but_own_comment_does_not(): void
    {
        $project = $this->project();
        $task = $this->postJson('/api/tasks', $this->taskPayload($project))->assertCreated()->json();
        $other = Member::create(['name' => 'نویسنده', 'job_title' => 'طراح']);

        $this->actingAs($other);
        $comment = $this->postJson('/api/tasks/'.$task['id'].'/comments', ['body' => 'نظر آزمون برای اعلان'])->assertCreated()->json();
        $this->assertDatabaseHas('notifications', [
            'member_id' => $this->member->id,
            'author_id' => $other->id,
            'task_id' => $task['id'],
            'comment_id' => $comment['id'],
            'read_at' => null,
        ]);

        $this->actingAs($this->member);
        $this->postJson('/api/tasks/'.$task['id'].'/comments', ['body' => 'نظر خودم'])->assertCreated();
        $this->assertDatabaseCount('notifications', 1);
        $this->getJson('/api/workspace')
            ->assertJsonPath('notifications.0.author.name', 'نویسنده')
            ->assertJsonPath('notifications.0.task.title', 'تسک آزمون');
        $this->postJson('/api/notifications/'.$this->getJson('/api/workspace')->json('notifications.0.id').'/read')->assertOk();
        $this->assertDatabaseMissing('notifications', ['member_id' => $this->member->id, 'read_at' => null]);
    }

    public function test_comment_author_only_can_edit_and_delete(): void
    {
        $project = $this->project();
        $taskId = $this->postJson('/api/tasks', $this->taskPayload($project))->assertCreated()->json('id');
        $other = Member::create(['name' => 'نویسنده نظر', 'job_title' => 'طراح']);
        $this->actingAs($other);
        $commentId = $this->postJson('/api/tasks/'.$taskId.'/comments', ['body' => 'نظر'])->assertCreated()->json('id');
        $this->putJson('/api/comments/'.$commentId, ['body' => 'نظر اصلاح شده'])->assertOk();
        $this->actingAs($this->member)->deleteJson('/api/comments/'.$commentId)->assertForbidden();
        $this->actingAs($other)->deleteJson('/api/comments/'.$commentId)->assertNoContent();
    }

    public function test_attachments_are_private_size_limited_and_can_belong_to_comment(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $taskId = $this->postJson('/api/tasks', $this->taskPayload($project))->assertCreated()->json('id');
        $commentId = $this->postJson('/api/tasks/'.$taskId.'/comments', ['body' => 'نظر فایل‌دار'])->assertCreated()->json('id');
        $attachmentId = $this->postJson('/api/tasks/'.$taskId.'/attachments', [
            'file' => UploadedFile::fake()->create('note.txt', 2, 'text/plain'),
            'comment_id' => $commentId,
        ])->assertCreated()->assertJsonPath('comment_id', $commentId)->assertJsonMissingPath('path')->json('id');

        $this->get('/attachments/'.$attachmentId)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->postJson('/api/tasks/'.$taskId.'/attachments', ['file' => UploadedFile::fake()->create('large.txt', 5121, 'text/plain')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $other = Member::create(['name' => 'همکار فایل', 'job_title' => 'طراح']);
        $this->actingAs($other)->deleteJson('/api/attachments/'.$attachmentId)->assertForbidden();
        $this->actingAs($this->member)->deleteJson('/api/attachments/'.$attachmentId)->assertNoContent();
        Storage::disk('local')->assertDirectoryEmpty('attachments');
    }
}
