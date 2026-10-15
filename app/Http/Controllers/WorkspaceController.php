<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Member;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Services\ProjectAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkspaceController extends Controller
{
    private function visibleLabels(Request $r)
    {
        $ids = Project::pluck('id');

        return Label::where(fn ($q) => $q->whereIn('project_id', $ids)->orWhere(fn ($q) => $q->whereNull('project_id')->where('member_id', $r->user()->id)));
    }

    private function activity(Task $task, Member $member, string $type, ?string $old = null, ?string $new = null): void
    {
        TaskActivity::create(['task_id' => $task->id, 'member_id' => $member->id, 'type' => $type, 'old_value' => $old, 'new_value' => $new]);
    }

    public function data(Request $r)
    {
        $projects = Project::with(['goal', 'stages', 'memberStages.productStage', 'members'])->withCount(['tasks', 'tasks as completed_count' => fn ($q) => $q->whereNotNull('completed_at')])->get();
        $tasks = Task::whereIn('project_id', $projects->pluck('id'))->with(['subtasks', 'labels', 'assignee', 'memberStage'])->latest()->get();

        return ['user' => $r->user(), 'projects' => $projects, 'tasks' => $tasks, 'labels' => $this->visibleLabels($r)->get(), 'notifications' => Notification::where('member_id', $r->user()->id)->with(['author', 'task'])->latest()->limit(30)->get()];
    }

    public function project(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:150', 'color' => 'required|regex:/^#[0-9a-fA-F]{6}$/', 'description' => 'nullable|string|max:2000', 'goal' => 'required|array:title,description,success_criterion,start_date,target_date', 'goal.title' => 'required|string|max:200', 'goal.description' => 'nullable|string|max:3000', 'goal.success_criterion' => 'required|string|max:3000', 'goal.start_date' => 'required|date_format:Y-m-d', 'goal.target_date' => 'required|date_format:Y-m-d|after_or_equal:goal.start_date', 'stages' => 'required|array|min:1|max:30', 'stages.*' => 'array:title,description,start_date,end_date', 'stages.*.title' => 'required|string|max:120', 'stages.*.description' => 'nullable|string|max:2000', 'stages.*.start_date' => 'nullable|date_format:Y-m-d', 'stages.*.end_date' => 'nullable|date_format:Y-m-d|after_or_equal:stages.*.start_date']);
        $p = DB::transaction(function () use ($v, $r) {
            $p = Project::create(['name' => $v['name'], 'description' => $v['description'] ?? null, 'created_by' => $r->user()->id, 'color' => $v['color']]);
            $p->members()->attach($r->user()->id);
            $p->goal()->create($v['goal']);
            foreach ($v['stages'] as $i => $s) {
                $p->stages()->create([...$s, 'position' => $i]);
            }

            return $p;
        });

        return response()->json($p, 201);
    }

    public function goal(Request $r, Project $project)
    {
        ProjectAccess::check($project, 'owner');
        $v = $r->validate(['title' => 'required|string|max:200', 'description' => 'nullable|string|max:3000', 'success_criterion' => 'required|string|max:3000', 'start_date' => 'required|date_format:Y-m-d', 'target_date' => 'required|date_format:Y-m-d|after_or_equal:start_date']);

        return $project->goal()->updateOrCreate(['project_id' => $project->id], $v);
    }

    public function stage(Request $r, Project $project)
    {
        ProjectAccess::check($project, 'write');
        abort_unless($project->goal, 422, 'ابتدا هدف پروژه را مشخص کنید.');
        $v = $r->validate(['title' => 'required|string|max:120', 'description' => 'nullable|string|max:2000', 'start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date', 'member_id' => ['nullable', Rule::exists('project_members', 'member_id')->where('project_id', $project->id)], 'product_stage_id' => ['nullable', 'integer', Rule::exists('product_stages', 'id')->where('project_id', $project->id)]]);
        if (isset($v['member_id']) && empty($v['product_stage_id'])) {
            throw ValidationException::withMessages(['product_stage_id' => 'مرحله محصول را انتخاب کنید.']);
        }
        $rel = isset($v['member_id']) ? $project->memberStages()->where('member_id', $v['member_id']) : $project->stages();
        $v['position'] = ($rel->max('position') ?? -1) + 1;

        return response()->json($rel->create($v), 201);
    }

    public function updateStage(Request $r, Project $project, string $kind, int $id)
    {
        ProjectAccess::check($project, 'write');
        $stage = ($kind === 'member' ? $project->memberStages() : $project->stages())->findOrFail($id);
        if ($r->isMethod('delete')) {
            if ($kind === 'member') {
                $tasks = Task::where('member_stage_id', $stage->id);
                if ($tasks->exists()) {
                    $v = $r->validate(['transfer_stage_id' => ['required', 'integer', Rule::exists('member_stages', 'id')->where(fn ($q) => $q->where('project_id', $project->id)->where('member_id', $stage->member_id))]]);
                    abort_if((int) $v['transfer_stage_id'] === $stage->id, 422, 'ستون مقصد باید متفاوت باشد.');
                    $target = $project->memberStages()->findOrFail($v['transfer_stage_id']);
                    $tasks->update(['member_stage_id' => $target->id, 'product_stage_id' => $target->product_stage_id]);
                }
            } else {
                $tasks = Task::where('product_stage_id', $stage->id);
                if ($tasks->exists()) {
                    $v = $r->validate(['transfer_stage_id' => ['required', 'integer', Rule::exists('product_stages', 'id')->where('project_id', $project->id)]]);
                    abort_if((int) $v['transfer_stage_id'] === $stage->id, 422, 'ستون مقصد باید متفاوت باشد.');
                    $tasks->update(['product_stage_id' => $v['transfer_stage_id'], 'member_stage_id' => null]);
                }
            }
            $stage->delete();

            return response()->noContent();
        }
        $rules = ['title' => 'required|string|max:120', 'description' => 'nullable|string|max:2000', 'start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date'];
        if ($kind === 'member') {
            $rules['product_stage_id'] = ['required', 'integer', Rule::exists('product_stages', 'id')->where('project_id', $project->id)];
        }
        $stage->update($r->validate($rules));

        return $stage;
    }

    public function order(Request $r, Project $project, string $kind)
    {
        ProjectAccess::check($project, 'write');
        $v = $r->validate(['ids' => 'required|array|min:1', 'ids.*' => 'required|integer|distinct', 'member_id' => 'nullable|integer']);
        $rel = $kind === 'member' ? $project->memberStages()->where('member_id', $v['member_id'] ?? 0) : $project->stages();
        $actual = $rel->pluck('id')->sort()->values()->all();
        $ids = $v['ids'];
        sort($ids);
        abort_unless($actual === $ids, 422, 'فهرست مراحل معتبر نیست.');
        DB::transaction(function () use ($v, $rel) {
            foreach ($v['ids'] as $i => $id) {
                (clone $rel)->where('id', $id)->update(['position' => $i]);
            }
        });

        return ['ok' => true];
    }

    public function taskDetail(Task $task)
    {
        ProjectAccess::check($task->project);

        return $task->load(['subtasks', 'labels', 'comments.user', 'attachments.user', 'activities.member', 'memberStage']);
    }

    public function saveTask(Request $r, ?Task $task = null)
    {
        $project = $task?->project ?? Project::findOrFail($r->input('project_id'));
        ProjectAccess::check($project, 'write');
        if ($r->has('owner_id') || $r->has('members')) {
            throw ValidationException::withMessages(['owner_id' => 'مسئول تسک قابل تغییر نیست.']);
        }
        $ownerId = $task?->created_by ?? $r->user()->id;
        $v = $r->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:10000',
            'member_stage_id' => 'nullable|integer',
            'start_date' => 'nullable|date_format:Y-m-d',
            'due_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'completed' => 'boolean',
            'labels' => 'present|array|max:30',
            'labels.*' => 'integer|distinct',
            'subtasks' => 'present|array|max:100',
            'subtasks.*.id' => 'nullable|integer|distinct',
            'subtasks.*.title' => 'required|string|max:200',
            'subtasks.*.completed' => 'required|boolean',
        ]);

        if (! empty($v['member_stage_id'])) {
            $memberStage = $project->memberStages()->where('member_id', $ownerId)->find($v['member_stage_id']);
            if (! $memberStage) {
                throw ValidationException::withMessages(['member_stage_id' => 'مرحله انتخاب‌شده متعلق به سازنده این تسک نیست.']);
            }
            if (! $memberStage->product_stage_id) {
                throw ValidationException::withMessages(['member_stage_id' => 'این مرحله شخصی به مرحله محصول متصل نیست.']);
            }
            $v['product_stage_id'] = $memberStage->product_stage_id;
        } elseif ($task) {
            $v['product_stage_id'] = $task->product_stage_id;
        } else {
            throw ValidationException::withMessages(['member_stage_id' => 'ابتدا یک «مرحله من» انتخاب یا همین‌جا ایجاد کنید.']);
        }

        $validLabels = Label::where(fn ($q) => $q->where('project_id', $project->id)->orWhere(fn ($q) => $q->whereNull('project_id')->where('member_id', $r->user()->id)))->pluck('id');
        if (collect($v['labels'])->diff($validLabels)->isNotEmpty()) {
            throw ValidationException::withMessages(['labels' => 'برچسب انتخاب‌شده برای این تسک مجاز نیست.']);
        }

        $new = ! $task;
        $before = $task ? $task->only(['title', 'start_date', 'due_date', 'product_stage_id', 'completed_at']) : [];
        $oldStage = $task?->stage?->title;

        $task = DB::transaction(function () use ($v, $project, $task, $r, $ownerId) {
            $fields = collect($v)->except(['labels', 'subtasks', 'completed'])->all();
            $fields['owner_id'] = $ownerId;
            $fields['completed_at'] = ($v['completed'] ?? false) ? ($task?->completed_at ?? now()) : null;
            if ($task) {
                $task->update($fields);
            } else {
                $task = $project->tasks()->create([...$fields, 'created_by' => $r->user()->id]);
            }
            $task->labels()->sync($v['labels']);
            $keep = [];
            foreach ($v['subtasks'] as $i => $subtask) {
                $fields = ['title' => $subtask['title'], 'position' => $i, 'completed_at' => $subtask['completed'] ? now() : null];
                if (! empty($subtask['id'])) {
                    $sub = $task->subtasks()->findOrFail($subtask['id']);
                    $sub->update($fields);
                } else {
                    $sub = $task->subtasks()->create($fields);
                }
                $keep[] = $sub->id;
            }
            $task->subtasks()->whereNotIn('id', $keep)->delete();

            return $task->fresh(['stage', 'assignee']);
        });

        if ($new) {
            $this->activity($task, $r->user(), 'created', null, $task->title);
        } else {
            if ($before['title'] !== $task->title) {
                $this->activity($task, $r->user(), 'title', $before['title'], $task->title);
            }
            foreach (['start_date', 'due_date'] as $field) {
                if ((string) $before[$field] !== (string) $task->$field) {
                    $this->activity($task, $r->user(), $field, (string) $before[$field], (string) $task->$field);
                }
            }
            if ((int) $before['product_stage_id'] !== (int) $task->product_stage_id) {
                $this->activity($task, $r->user(), 'stage', $oldStage, $task->stage?->title);
            }
            $wasCompleted = ! empty($before['completed_at']);
            $isCompleted = ! empty($task->completed_at);
            if ($wasCompleted !== $isCompleted) {
                $this->activity($task, $r->user(), $isCompleted ? 'completed' : 'reopened');
            }
        }

        return response()->json($task->load(['subtasks', 'labels', 'activities.member', 'memberStage']), $new ? 201 : 200);
    }

    public function moveTaskStage(Request $r, Task $task)
    {
        ProjectAccess::check($task->project, 'write');
        $v = $r->validate([
            'product_stage_id' => ['required', 'integer', Rule::exists('product_stages', 'id')->where('project_id', $task->project_id)],
        ]);
        $oldStage = $task->stage?->title;
        $task->update(['product_stage_id' => $v['product_stage_id']]);
        if ($task->member_stage_id && $task->memberStage?->product_stage_id !== (int) $v['product_stage_id']) {
            $task->update(['member_stage_id' => null]);
        }
        $task->load('stage');
        $this->activity($task, $r->user(), 'stage', $oldStage, $task->stage?->title);

        return $task->fresh(['memberStage', 'stage']);
    }

    public function complete(Request $r, Task $task)
    {
        ProjectAccess::check($task->project, 'write');
        $v = $r->validate(['completed' => 'required|boolean']);
        $wasCompleted = (bool) $task->completed_at;
        $task->update(['completed_at' => $v['completed'] ? now() : null]);
        if ($wasCompleted !== (bool) $task->completed_at) {
            $this->activity($task, $r->user(), $task->completed_at ? 'completed' : 'reopened');
        }

        return $task;
    }

    public function removeTask(Task $task)
    {
        ProjectAccess::check($task->project, 'write');
        foreach ($task->attachments as $a) {
            Storage::disk('local')->delete($a->path);
        }
        $task->delete();

        return response()->noContent();
    }

    public function label(Request $r, ?Label $label = null)
    {
        if ($label) {
            if ($label->project_id) {
                ProjectAccess::check($label->project, 'write');
            } else {
                abort_unless($label->member_id === $r->user()->id, 404);
            }
        }
        if ($r->isMethod('delete')) {
            $label->delete();

            return response()->noContent();
        }
        $v = $r->validate(['name' => 'required|string|max:60', 'color' => 'required|regex:/^#[0-9a-fA-F]{6}$/', 'description' => 'nullable|string|max:500', 'project_id' => 'nullable|integer']);
        if (! $label && isset($v['project_id'])) {
            ProjectAccess::check(Project::findOrFail($v['project_id']), 'write');
        }
        if ($label) {
            unset($v['project_id']);
            $label->update($v);
        } else {
            $label = Label::create([...$v, 'member_id' => $r->user()->id]);
        }

        return $label;
    }

    public function comment(Request $r, Task $task)
    {
        ProjectAccess::check($task->project, 'write');
        $v = $r->validate(['body' => 'required|string|max:5000']);
        $c = $task->comments()->create([...$v, 'member_id' => $r->user()->id]);
        if ($task->owner_id && $task->owner_id !== $r->user()->id) {
            Notification::create([
                'member_id' => $task->owner_id,
                'author_id' => $r->user()->id,
                'task_id' => $task->id,
                'comment_id' => $c->id,
                'excerpt' => mb_substr($c->body, 0, 240),
            ]);
        }

        return response()->json($c->load('user'), 201);
    }

    public function editComment(Request $r, Comment $comment)
    {
        ProjectAccess::check($comment->task->project, 'write');
        Gate::authorize($r->isMethod('delete') ? 'delete' : 'update', $comment);
        if ($r->isMethod('delete')) {
            $comment->delete();

            return response()->noContent();
        }
        $comment->update($r->validate(['body' => 'required|string|max:5000']));

        return $comment;
    }

    public function upload(Request $r, Task $task)
    {
        ProjectAccess::check($task->project, 'write');
        $v = $r->validate([
            'file' => 'required|file|max:5120|mimes:pdf,png,jpg,jpeg,webp,txt|extensions:pdf,png,jpg,jpeg,webp,txt',
            'comment_id' => 'nullable|integer',
        ]);
        if (! empty($v['comment_id'])) {
            abort_unless($task->comments()->whereKey($v['comment_id'])->exists(), 422, 'نظر انتخاب‌شده معتبر نیست.');
        }
        $f = $r->file('file');
        $path = $f->store('attachments', 'local');

        return response()->json($task->attachments()->create([
            'comment_id' => $v['comment_id'] ?? null,
            'member_id' => $r->user()->id,
            'original_name' => mb_substr(basename($f->getClientOriginalName()), 0, 200),
            'file_name' => basename($path),
            'mime' => $f->getMimeType(),
            'size' => $f->getSize(),
            'path' => $path,
        ])->load('user'), 201);
    }

    public function download(Attachment $attachment)
    {
        ProjectAccess::check($attachment->task->project);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function removeAttachment(Request $r, Attachment $attachment)
    {
        ProjectAccess::check($attachment->task->project, 'write');
        Gate::authorize('delete', $attachment);
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return response()->noContent();
    }

    public function notifications(Request $r, ?Notification $notification = null)
    {
        if ($notification) {
            abort_unless($notification->member_id === $r->user()->id, 404);
            $notification->update(['read_at' => $notification->read_at ?? now()]);
        } else {
            Notification::where('member_id', $r->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        }

        return ['ok' => true];
    }

    public function settings(Request $r)
    {
        $v = $r->validate(['name' => ['required', 'string', 'max:100', Rule::unique('members')->ignore($r->user()->id)], 'job_title' => 'required|string|max:100']);
        $r->user()->update($v);

        return $r->user()->fresh();
    }

    public function uploadAvatar(Request $r)
    {
        $r->validate(['avatar' => 'required|file|max:2048|mimes:jpg,jpeg,png,webp|extensions:jpg,jpeg,png,webp']);
        abort_unless(extension_loaded('gd'), 500, 'پردازش تصویر روی سرور فعال نیست.');
        $bytes = file_get_contents($r->file('avatar')->getRealPath());
        $source = @imagecreatefromstring($bytes);
        abort_unless($source, 422, 'تصویر قابل پردازش نیست.');
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $srcX = (int) floor(($width - $side) / 2);
        $srcY = (int) floor(($height - $side) / 2);
        $target = imagecreatetruecolor(512, 512);
        imagealphablending($target, true);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, $srcX, $srcY, 512, 512, $side, $side);
        ob_start();
        imagewebp($target, null, 85);
        $output = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        $member = $r->user();
        if ($member->avatar_path) {
            Storage::disk('local')->delete($member->avatar_path);
        }
        $path = 'avatars/'.$member->id.'-'.bin2hex(random_bytes(8)).'.webp';
        Storage::disk('local')->put($path, $output);
        $member->update(['avatar_path' => $path]);

        return $member->fresh();
    }

    public function removeAvatar(Request $r)
    {
        $member = $r->user();
        if ($member->avatar_path) {
            Storage::disk('local')->delete($member->avatar_path);
            $member->update(['avatar_path' => null]);
        }

        return $member->fresh();
    }

    public function avatar(Member $member)
    {
        abort_unless($member->avatar_path && Storage::disk('local')->exists($member->avatar_path), 404);

        return response()->file(Storage::disk('local')->path($member->avatar_path), ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }
}
