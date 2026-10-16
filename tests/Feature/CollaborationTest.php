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

    public function test_comment_create_and_edit_persist_attachments_on_the_comment(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $taskId = $this->postJson('/api/tasks', $this->taskPayload($project))->assertCreated()->json('id');

        $comment = $this->post('/api/tasks/'.$taskId.'/comments', [
            'body' => 'نظر فایل‌دار',
            'files' => [UploadedFile::fake()->create('first.txt', 2, 'text/plain')],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('attachments.0.original_name', 'first.txt')
            ->json();

        $this->getJson('/api/tasks/'.$taskId)
            ->assertOk()
            ->assertJsonPath('comments.0.attachments.0.original_name', 'first.txt');

        $this->post('/api/comments/'.$comment['id'], [
            'body' => 'نظر ویرایش‌شده',
            'files' => [UploadedFile::fake()->create('second.txt', 2, 'text/plain')],
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('body', 'نظر ویرایش‌شده')
            ->assertJsonCount(2, 'attachments')
            ->assertJsonPath('attachments.1.original_name', 'second.txt');

        $this->assertDatabaseCount('attachments', 2);
        $this->deleteJson('/api/comments/'.$comment['id'])->assertNoContent();
        $this->assertDatabaseCount('attachments', 0);
        Storage::disk('local')->assertDirectoryEmpty('attachments');
    }

    public function test_attachments_are_private_and_size_limited(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $taskId = $this->postJson('/api/tasks', $this->taskPayload($project))->assertCreated()->json('id');
        $attachmentId = $this->postJson('/api/tasks/'.$taskId.'/attachments', [
            'file' => UploadedFile::fake()->create('note.txt', 2, 'text/plain'),
        ])->assertCreated()->assertJsonMissingPath('path')->json('id');

        $this->get('/attachments/'.$attachmentId)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->postJson('/api/tasks/'.$taskId.'/attachments', ['file' => UploadedFile::fake()->create('large.txt', 5121, 'text/plain')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $other = Member::create(['name' => 'همکار فایل', 'job_title' => 'طراح']);
        $this->actingAs($other)->postJson('/api/tasks/'.$taskId.'/attachments', [
            'file' => UploadedFile::fake()->create('blocked.txt', 2, 'text/plain'),
        ])->assertForbidden();
        $this->actingAs($other)->deleteJson('/api/attachments/'.$attachmentId)->assertForbidden();
        $this->actingAs($this->member)->deleteJson('/api/attachments/'.$attachmentId)->assertNoContent();
        Storage::disk('local')->assertDirectoryEmpty('attachments');
    }
}
