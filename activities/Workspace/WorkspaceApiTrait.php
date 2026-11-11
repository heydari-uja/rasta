<?php

namespace Activities\Workspace;

use Lib\JsonPresenter;
use Lib\ValidationException;
use Lib\Validator;

trait WorkspaceApiTrait
{
    public function projectRecord(array $request, $projectId)
    {
        $this->gateApi();
        $project = $this->findProject((int) $projectId);
        if (!$project) {
            abortHttp(404);
        }
        if (methodField() === 'DELETE') {
            $paths = $this->db->select(
                'SELECT a.path FROM attachments a JOIN tasks t ON t.id = a.task_id WHERE t.project_id = ?',
                [$project['id']]
            )->fetchAll();
            $this->db->beginTransaction();
            $this->db->query('DELETE FROM tasks WHERE project_id = ?', [$project['id']]);
            $this->db->delete('projects', $project['id']);
            $this->db->commit();
            foreach ($paths as $p) {
                @unlink($this->storagePath($p['path']));
            }
            http_response_code(204);
            exit;
        }

        try {
            $v = (new Validator($request, [
                'name' => 'required|string|max:150',
                'color' => 'required|in:project-marker-1,project-marker-2,project-marker-3,project-marker-4,project-marker-5,project-marker-6,project-marker-7,project-marker-8',
                'description' => 'nullable|string|max:2000',
            ]))->validate();
        } catch (ValidationException $e) {
            jsonError('اطلاعات معتبر نیست.', $e->errors);
        }
        $this->db->beginTransaction();
        $this->db->update('projects', $project['id'], ['name', 'color', 'description'], [$v['name'], $v['color'], $v['description'] ?? null]);
        if (!empty($request['goal']) && is_array($request['goal'])) {
            $g = $request['goal'];
            $existing = $this->db->select('SELECT id FROM goals WHERE project_id = ?', [$project['id']])->fetch();
            if ($existing) {
                $this->db->update('goals', $existing['id'], ['title', 'description', 'success_criterion', 'start_date', 'target_date'], [
                    $g['title'] ?? '', $g['description'] ?? null, $g['success_criterion'] ?? '', $g['start_date'] ?? null, $g['target_date'] ?? null,
                ]);
            } else {
                $this->db->insert('goals', ['project_id', 'title', 'description', 'success_criterion', 'start_date', 'target_date'], [
                    $project['id'], $g['title'] ?? '', $g['description'] ?? null, $g['success_criterion'] ?? '', $g['start_date'] ?? null, $g['target_date'] ?? null,
                ]);
            }
        }
        $this->db->commit();
        $fresh = $this->db->select('SELECT * FROM projects WHERE id = ?', [$project['id']])->fetch();
        $goal = $this->db->select('SELECT * FROM goals WHERE project_id = ?', [$project['id']])->fetch();
        jsonResponse(JsonPresenter::project($fresh, ['goal' => $goal]));
    }

    public function goal(array $request, $projectId)
    {
        $this->gateApi();
        $project = $this->findProject((int) $projectId);
        if (!$project) {
            abortHttp(404);
        }
        try {
            $v = (new Validator($request, [
                'title' => 'required|string|max:200',
                'success_criterion' => 'required|string|max:3000',
                'start_date' => 'required|date_format:Y-m-d',
                'target_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            ]))->validate();
        } catch (ValidationException $e) {
            jsonError('اطلاعات هدف معتبر نیست.', $e->errors);
        }
        $existing = $this->db->select('SELECT id FROM goals WHERE project_id = ?', [$project['id']])->fetch();
        $fields = ['title', 'description', 'success_criterion', 'start_date', 'target_date'];
        $values = [$v['title'], $v['description'] ?? null, $v['success_criterion'], $v['start_date'], $v['target_date']];
        if ($existing) {
            $this->db->update('goals', $existing['id'], $fields, $values);
            $goal = $this->db->select('SELECT * FROM goals WHERE id = ?', [$existing['id']])->fetch();
        } else {
            $this->db->insert('goals', array_merge(['project_id'], $fields), array_merge([$project['id']], $values));
            $goal = $this->db->select('SELECT * FROM goals WHERE project_id = ?', [$project['id']])->fetch();
        }
        jsonResponse($goal);
    }

    public function stage(array $request, $projectId)
    {
        $this->gateApi();
        $project = $this->findProject((int) $projectId);
        if (!$project) {
            abortHttp(404);
        }
        $goal = $this->db->select('SELECT id FROM goals WHERE project_id = ?', [$project['id']])->fetch();
        if (!$goal) {
            jsonError('ابتدا هدف پروژه را مشخص کنید.', [], 422);
        }
        try {
            $v = (new Validator($request, [
                'title' => 'required|string|max:120',
            ]))->validate();
        } catch (ValidationException $e) {
            jsonError('اطلاعات مرحله معتبر نیست.', $e->errors);
        }
        $uid = (int) $this->user()['id'];
        if (!empty($request['member_id'])) {
            if ((int) $request['member_id'] !== $uid) {
                jsonError('ستون شخصی فقط برای حساب واردشده قابل ایجاد است.', ['member_id' => ['invalid']]);
            }
            if (empty($request['product_stage_id'])) {
                jsonError('مرحله محصول را انتخاب کنید.', ['product_stage_id' => ['required']]);
            }
            $this->db->query('INSERT IGNORE INTO project_members (project_id, member_id) VALUES (?, ?)', [$project['id'], $uid]);
            $pos = (int) $this->db->select('SELECT COALESCE(MAX(position), -1) AS p FROM member_stages WHERE member_id = ?', [$uid])->fetch()['p'] + 1;
            $this->db->insert('member_stages', ['project_id', 'member_id', 'product_stage_id', 'name', 'description', 'start_date', 'end_date', 'position'], [
                $project['id'], $uid, (int) $request['product_stage_id'], $v['title'], $request['description'] ?? null,
                $request['start_date'] ?? null, $request['end_date'] ?? null, $pos,
            ]);
            $row = $this->db->select('SELECT * FROM member_stages WHERE id = ?', [$this->db->lastInsertId()])->fetch();
            jsonResponse(JsonPresenter::stage($row), 201);
            return;
        }
        $pos = (int) $this->db->select('SELECT COALESCE(MAX(position), -1) AS p FROM product_stages WHERE project_id = ?', [$project['id']])->fetch()['p'] + 1;
        $this->db->insert('product_stages', ['project_id', 'name', 'description', 'start_date', 'end_date', 'position'], [
            $project['id'], $v['title'], $request['description'] ?? null, $request['start_date'] ?? null, $request['end_date'] ?? null, $pos,
        ]);
        $row = $this->db->select('SELECT * FROM product_stages WHERE id = ?', [$this->db->lastInsertId()])->fetch();
        jsonResponse(JsonPresenter::stage($row), 201);
    }

    public function updateStage(array $request, $projectId, $kind, $id)
    {
        $this->gateApi();
        $project = $this->findProject((int) $projectId);
        if (!$project) {
            abortHttp(404);
        }
        $table = $kind === 'member' ? 'member_stages' : 'product_stages';
        $stage = $this->db->select("SELECT * FROM {$table} WHERE id = ? AND project_id = ?", [(int) $id, $project['id']])->fetch();
        if (!$stage) {
            abortHttp(404);
        }
        if (methodField() === 'DELETE') {
            $this->deleteStageWithTransfer($request, $stage, $kind, $project);
            http_response_code(204);
            exit;
        }
        try {
            $rules = ['title' => 'required|string|max:120'];
            $v = (new Validator($request, $rules))->validate();
        } catch (ValidationException $e) {
            jsonError('اطلاعات مرحله معتبر نیست.', $e->errors);
        }
        $fields = ['name', 'description', 'start_date', 'end_date'];
        $values = [$v['title'], $request['description'] ?? null, $request['start_date'] ?? null, $request['end_date'] ?? null];
        if ($kind === 'member' && isset($request['product_stage_id'])) {
            $fields[] = 'product_stage_id';
            $values[] = (int) $request['product_stage_id'];
        }
        $this->db->update($table, $stage['id'], $fields, $values);
        $fresh = $this->db->select("SELECT * FROM {$table} WHERE id = ?", [$stage['id']])->fetch();
        jsonResponse(JsonPresenter::stage($fresh));
    }

    private function deleteStageWithTransfer(array $request, array $stage, string $kind, array $project): void
    {
        $col = $kind === 'member' ? 'member_stage_id' : 'product_stage_id';
        $active = $this->db->select("SELECT id FROM tasks WHERE {$col} = ? AND completed_at IS NULL", [$stage['id']])->fetchAll();
        if ($active) {
            $transfer = (int) ($request['transfer_stage_id'] ?? 0);
            if ($transfer === (int) $stage['id']) {
                jsonError('ستون مقصد باید متفاوت باشد.', [], 422);
            }
            $this->db->query("UPDATE tasks SET {$col} = ? WHERE {$col} = ? AND completed_at IS NULL", [$transfer, $stage['id']]);
        }
        $this->db->query("UPDATE tasks SET {$col} = NULL WHERE {$col} = ? AND completed_at IS NOT NULL", [$stage['id']]);
        $table = $kind === 'member' ? 'member_stages' : 'product_stages';
        $this->db->delete($table, $stage['id']);
    }

    public function personalStage(array $request, $stageId = null)
    {
        $this->gateApi();
        $uid = (int) $this->user()['id'];
        $stage = $stageId ? $this->db->select('SELECT * FROM member_stages WHERE id = ?', [(int) $stageId])->fetch() : null;
        if ($stage && ($stage['project_id'] !== null || (int) $stage['member_id'] !== $uid)) {
            abortHttp(404);
        }
        if (methodField() === 'DELETE' && $stage) {
            $active = $this->db->select('SELECT id FROM tasks WHERE member_stage_id = ? AND project_id IS NULL AND completed_at IS NULL', [$stage['id']])->fetchAll();
            if ($active) {
                $transfer = (int) ($request['transfer_stage_id'] ?? 0);
                $this->db->query('UPDATE tasks SET member_stage_id = ?, product_stage_id = NULL, project_id = NULL WHERE member_stage_id = ? AND project_id IS NULL AND completed_at IS NULL', [$transfer, $stage['id']]);
            }
            $this->db->query('UPDATE tasks SET member_stage_id = NULL WHERE member_stage_id = ? AND project_id IS NULL AND completed_at IS NOT NULL', [$stage['id']]);
            $this->db->delete('member_stages', $stage['id']);
            http_response_code(204);
            exit;
        }
        try {
            $v = (new Validator($request, ['title' => 'required|string|max:120']))->validate();
        } catch (ValidationException $e) {
            jsonError('اطلاعات معتبر نیست.', $e->errors);
        }
        if ($stage) {
            $this->db->update('member_stages', $stage['id'], ['name', 'description', 'start_date', 'end_date'], [
                $v['title'], $request['description'] ?? null, $request['start_date'] ?? null, $request['end_date'] ?? null,
            ]);
            $fresh = $this->db->select('SELECT * FROM member_stages WHERE id = ?', [$stage['id']])->fetch();
            jsonResponse(JsonPresenter::stage($fresh));
            return;
        }
        $pos = (int) $this->db->select('SELECT COALESCE(MAX(position), -1) AS p FROM member_stages WHERE member_id = ?', [$uid])->fetch()['p'] + 1;
        $this->db->insert('member_stages', ['member_id', 'project_id', 'product_stage_id', 'name', 'description', 'start_date', 'end_date', 'position'], [
            $uid, null, null, $v['title'], $request['description'] ?? null, $request['start_date'] ?? null, $request['end_date'] ?? null, $pos,
        ]);
        $fresh = $this->db->select('SELECT * FROM member_stages WHERE id = ?', [$this->db->lastInsertId()])->fetch();
        jsonResponse(JsonPresenter::stage($fresh), 201);
    }

    public function memberStageOrder(array $request)
    {
        $this->gateApi();
        $uid = (int) $this->user()['id'];
        $ids = $request['ids'] ?? [];
        $actual = $this->db->select('SELECT id FROM member_stages WHERE member_id = ? ORDER BY id', [$uid])->fetchAll();
        $actualIds = array_map(fn ($r) => (int) $r['id'], $actual);
        sort($actualIds);
        $sorted = array_map('intval', $ids);
        sort($sorted);
        if ($actualIds !== $sorted) {
            jsonError('فهرست ستون‌ها معتبر نیست.', [], 422);
        }
        $this->db->beginTransaction();
        foreach ($ids as $i => $id) {
            $this->db->query('UPDATE member_stages SET position = ? WHERE member_id = ? AND id = ?', [$i, $uid, (int) $id]);
        }
        $this->db->commit();
        jsonResponse(['ok' => true]);
    }

    public function order(array $request, $projectId, $kind)
    {
        $this->gateApi();
        $project = $this->findProject((int) $projectId);
        if (!$project) {
            abortHttp(404);
        }
        $ids = array_map('intval', $request['ids'] ?? []);
        $table = $kind === 'member' ? 'member_stages' : 'product_stages';
        $memberId = (int) ($request['member_id'] ?? 0);
        $sql = "SELECT id FROM {$table} WHERE project_id = ?" . ($kind === 'member' ? ' AND member_id = ?' : '') . ' ORDER BY id';
        $bind = $kind === 'member' ? [$project['id'], $memberId] : [$project['id']];
        $actual = array_map(fn ($r) => (int) $r['id'], $this->db->select($sql, $bind)->fetchAll());
        sort($actual);
        $sorted = $ids;
        sort($sorted);
        if ($actual !== $sorted) {
            jsonError('فهرست مراحل معتبر نیست.', [], 422);
        }
        $this->db->beginTransaction();
        foreach ($ids as $i => $id) {
            $this->db->query("UPDATE {$table} SET position = ? WHERE id = ?", [$i, $id]);
        }
        $this->db->commit();
        jsonResponse(['ok' => true]);
    }

    public function saveTask(array $request, $taskId = null)
    {
        $this->gateApi();
        $task = $taskId ? $this->findTask((int) $taskId) : null;
        if ($task) {
            $this->checkTaskAccess($task, true);
            if (!$this->canUpdateTask($task)) {
                abortHttp(403);
            }
        }
        if (isset($request['owner_id']) || isset($request['members'])) {
            jsonError('مسئول تسک قابل تغییر نیست.', ['owner_id' => ['locked']]);
        }

        $ownerId = $task ? (int) $task['created_by'] : (int) $this->user()['id'];
        try {
            $v = (new Validator($request, [
                'title' => 'required|string|max:200',
                'labels' => 'present|array|max:30',
                'subtasks' => 'present|array|max:100',
            ]))->validate();
        } catch (ValidationException $e) {
            jsonError('اطلاعات تسک معتبر نیست.', $e->errors);
        }

        $project = null;
        $projectId = $request['project_id'] ?? null;
        if ($projectId !== null && $projectId !== '') {
            $project = $this->findProject((int) $projectId);
            if (!$project) {
                abortHttp(404);
            }
        }

        if ($project) {
            $productStageId = $request['product_stage_id'] ?? null;
            if (!$productStageId) {
                jsonError('مرحله این تسک را از مراحل پروژه انتخاب کنید.', ['product_stage_id' => ['required']]);
            }
            $stageOk = $this->db->select(
                'SELECT id FROM product_stages WHERE id = ? AND project_id = ?',
                [(int) $productStageId, $project['id']]
            )->fetch();
            if (!$stageOk) {
                jsonError('مرحله این تسک را از مراحل پروژه انتخاب کنید.', ['product_stage_id' => ['invalid']]);
            }
            $memberStageId = $request['member_stage_id'] ?? null;
            if ($memberStageId) {
                $msOk = $this->db->select(
                    'SELECT id FROM member_stages WHERE id = ? AND project_id = ? AND member_id = ?',
                    [(int) $memberStageId, $project['id'], $ownerId]
                )->fetch();
                if (!$msOk) {
                    jsonError('ستون انتخاب‌شده متعلق به سازنده این تسک نیست.', ['member_stage_id' => ['invalid']]);
                }
            } else {
                $memberStageId = null;
            }
        } else {
            $memberStageId = $request['member_stage_id'] ?? null;
            $msOk = $memberStageId
                ? $this->db->select(
                    'SELECT id FROM member_stages WHERE id = ? AND project_id IS NULL AND member_id = ?',
                    [(int) $memberStageId, $ownerId]
                )->fetch()
                : null;
            if (!$msOk) {
                jsonError('برای تسک شخصی یک مرحله بدون پروژه انتخاب کنید.', ['member_stage_id' => ['required']]);
            }
            $productStageId = null;
        }

        $fields = [
            'title' => $v['title'],
            'description' => $request['description'] ?? null,
            'project_id' => $project ? $project['id'] : null,
            'member_stage_id' => $memberStageId ? (int) $memberStageId : null,
            'product_stage_id' => $productStageId ? (int) $productStageId : null,
            'start_date' => $request['start_date'] ?? null,
            'due_date' => $request['due_date'] ?? null,
            'owner_id' => $ownerId,
            'completed_at' => !empty($request['completed']) ? ($task['completed_at'] ?? date('Y-m-d H:i:s')) : null,
        ];

        $this->db->beginTransaction();
        if ($task) {
            $this->db->update('tasks', $task['id'], array_keys($fields), array_values($fields));
            $tid = (int) $task['id'];
        } else {
            $pos = $fields['member_stage_id']
                ? ((int) $this->db->select('SELECT COALESCE(MAX(position), -1) AS p FROM tasks WHERE member_stage_id = ?', [$fields['member_stage_id']])->fetch()['p'] + 1)
                : 0;
            $this->db->insert('tasks', array_merge(array_keys($fields), ['created_by', 'position']), array_merge(array_values($fields), [(int) $this->user()['id'], $pos]));
            $tid = (int) $this->db->lastInsertId();
        }

        $this->db->query('DELETE FROM task_labels WHERE task_id = ?', [$tid]);
        foreach ($request['labels'] ?? [] as $lid) {
            $this->db->query('INSERT INTO task_labels (task_id, label_id) VALUES (?, ?)', [$tid, (int) $lid]);
        }

        $keep = [];
        foreach ($request['subtasks'] ?? [] as $i => $sub) {
            if (!empty($sub['id'])) {
                $this->db->update('subtasks', (int) $sub['id'], ['title', 'position', 'completed_at'], [
                    $sub['title'], $i, !empty($sub['completed']) ? date('Y-m-d H:i:s') : null,
                ]);
                $keep[] = (int) $sub['id'];
            } else {
                $this->db->insert('subtasks', ['task_id', 'title', 'position', 'completed_at'], [
                    $tid, $sub['title'], $i, !empty($sub['completed']) ? date('Y-m-d H:i:s') : null,
                ]);
                $keep[] = (int) $this->db->lastInsertId();
            }
        }
        if ($keep) {
            $in = implode(',', $keep);
            $this->db->query("DELETE FROM subtasks WHERE task_id = ? AND id NOT IN ({$in})", [$tid]);
        } else {
            $this->db->query('DELETE FROM subtasks WHERE task_id = ?', [$tid]);
        }
        $this->db->commit();

        $fresh = $this->findTask($tid);
        jsonResponse($this->mapTaskDetail($fresh), $task ? 200 : 201);
    }

    public function reorderTasks(array $request)
    {
        $this->gateApi();
        $uid = (int) $this->user()['id'];
        $task = $this->db->select('SELECT * FROM tasks WHERE id = ? AND owner_id = ?', [(int) $request['task_id'], $uid])->fetch();
        if (!$task || !$this->canUpdateTask($task)) {
            abortHttp(403);
        }
        $targetId = (int) $request['target_stage_id'];
        $target = $this->db->select('SELECT * FROM member_stages WHERE id = ? AND member_id = ?', [$targetId, $uid])->fetch();
        if (!$target) {
            abortHttp(404);
        }
        $this->db->beginTransaction();
        $this->db->update('tasks', $task['id'], ['member_stage_id', 'project_id', 'product_stage_id'], [
            $target['id'], $target['project_id'], $target['product_stage_id'],
        ]);
        foreach ($request['target_ids'] ?? [] as $i => $id) {
            $this->db->query('UPDATE tasks SET member_stage_id = ?, position = ? WHERE owner_id = ? AND id = ?', [$target['id'], $i, $uid, (int) $id]);
        }
        $sourceStage = $task['member_stage_id'];
        if ((int) $sourceStage !== (int) $target['id']) {
            foreach ($request['source_ids'] ?? [] as $i => $id) {
                $this->db->query('UPDATE tasks SET position = ? WHERE owner_id = ? AND member_stage_id = ? AND id = ?', [$i, $uid, $sourceStage, (int) $id]);
            }
        }
        $this->db->commit();
        jsonResponse($this->mapTaskSummary($this->findTask($task['id'])));
    }

    public function moveTaskStage(array $request, $taskId)
    {
        $this->gateApi();
        $task = $this->findTask((int) $taskId);
        if (!$task || !$this->canUpdateTask($task) || empty($task['project_id'])) {
            jsonError('تسک شخصی مرحله محصول ندارد.', [], 422);
        }
        $this->db->update('tasks', $task['id'], ['product_stage_id'], [(int) $request['product_stage_id']]);
        jsonResponse($this->mapTaskSummary($this->findTask($task['id'])));
    }

    public function label(array $request, $labelId = null)
    {
        $this->gateApi();
        $uid = (int) $this->user()['id'];
        $label = $labelId ? $this->db->select('SELECT * FROM labels WHERE id = ?', [(int) $labelId])->fetch() : null;
        if ($label && $label['project_id'] === null && (int) $label['member_id'] !== $uid) {
            abortHttp(404);
        }
        if (methodField() === 'DELETE' && $label) {
            $this->db->delete('labels', $label['id']);
            http_response_code(204);
            exit;
        }
        try {
            $v = (new Validator($request, [
                'name' => 'required|string|max:60',
                'color' => 'required|in:project-marker-1,project-marker-2,project-marker-3,project-marker-4,project-marker-5,project-marker-6,project-marker-7,project-marker-8',
            ]))->validate();
        } catch (ValidationException $e) {
            jsonError('اطلاعات برچسب معتبر نیست.', $e->errors);
        }
        if ($label) {
            $this->db->update('labels', $label['id'], ['name', 'color', 'description'], [$v['name'], $v['color'], $request['description'] ?? null]);
            jsonResponse(JsonPresenter::label($this->db->select('SELECT * FROM labels WHERE id = ?', [$label['id']])->fetch()));
            return;
        }
        $this->db->insert('labels', ['name', 'color', 'description', 'member_id', 'project_id'], [
            $v['name'], $v['color'], $request['description'] ?? null, $uid, $request['project_id'] ?? null,
        ]);
        jsonResponse(JsonPresenter::label($this->db->select('SELECT * FROM labels WHERE id = ?', [$this->db->lastInsertId()])->fetch()), 201);
    }

    public function comment(array $request, $taskId)
    {
        $this->gateApi();
        $task = $this->findTask((int) $taskId);
        if (!$task) {
            abortHttp(404);
        }
        $this->checkTaskAccess($task, true);
        try {
            $v = (new Validator($request, ['body' => 'required|string|max:5000']))->validate();
        } catch (ValidationException $e) {
            jsonError('نظر معتبر نیست.', $e->errors);
        }
        $uid = (int) $this->user()['id'];
        $this->db->insert('comments', ['task_id', 'member_id', 'body'], [(int) $taskId, $uid, $v['body']]);
        $cid = (int) $this->db->lastInsertId();
        if (!empty($_FILES['files'])) {
            $this->storeCommentFiles($_FILES['files'], $task, $cid, $uid);
        }
        if ($task['owner_id'] && (int) $task['owner_id'] !== $uid) {
            $this->db->insert('notifications', ['member_id', 'author_id', 'task_id', 'comment_id', 'excerpt'], [
                (int) $task['owner_id'], $uid, (int) $task['id'], $cid, mb_substr($v['body'], 0, 240),
            ]);
        }
        $c = $this->db->select('SELECT * FROM comments WHERE id = ?', [$cid])->fetch();
        $user = $this->db->select('SELECT * FROM members WHERE id = ?', [$uid])->fetch();
        jsonResponse(JsonPresenter::comment($c, $user, []), 201);
    }

    public function editComment(array $request, $commentId)
    {
        $this->gateApi();
        $comment = $this->db->select('SELECT * FROM comments WHERE id = ?', [(int) $commentId])->fetch();
        if (!$comment) {
            abortHttp(404);
        }
        $task = $this->findTask((int) $comment['task_id']);
        $this->checkTaskAccess($task, true);
        if ((int) $comment['member_id'] !== (int) $this->user()['id']) {
            abortHttp(403);
        }
        if (methodField() === 'DELETE') {
            $paths = $this->db->select('SELECT path FROM attachments WHERE comment_id = ?', [$comment['id']])->fetchAll();
            $this->db->delete('comments', $comment['id']);
            foreach ($paths as $p) {
                @unlink($this->storagePath($p['path']));
            }
            http_response_code(204);
            exit;
        }
        $v = (new Validator($request, ['body' => 'required|string|max:5000']))->validate();
        $this->db->update('comments', $comment['id'], ['body'], [$v['body']]);
        $user = $this->db->select('SELECT * FROM members WHERE id = ?', [$comment['member_id']])->fetch();
        $atts = $this->db->select('SELECT * FROM attachments WHERE comment_id = ?', [$comment['id']])->fetchAll();
        jsonResponse(JsonPresenter::comment($comment, $user, $atts));
    }

    public function upload(array $request, $taskId)
    {
        $this->gateApi();
        $task = $this->findTask((int) $taskId);
        if (!$task || empty($_FILES['file'])) {
            abortHttp(422);
        }
        $this->checkTaskAccess($task, true);
        $uid = (int) $this->user()['id'];
        $saved = $this->saveUpload($_FILES['file'], 'attachments');
        $this->db->insert('attachments', ['task_id', 'member_id', 'comment_id', 'original_name', 'file_name', 'mime', 'size', 'path'], [
            (int) $taskId, $uid, $request['comment_id'] ?? null, $saved['original_name'], $saved['file_name'], $saved['mime'], $saved['size'], $saved['path'],
        ]);
        $row = $this->db->select('SELECT * FROM attachments WHERE id = ?', [$this->db->lastInsertId()])->fetch();
        $user = $this->db->select('SELECT * FROM members WHERE id = ?', [$uid])->fetch();
        jsonResponse(JsonPresenter::attachment($row, $user), 201);
    }

    public function removeAttachment(array $request, $attachmentId)
    {
        $this->gateApi();
        $a = $this->db->select('SELECT * FROM attachments WHERE id = ?', [(int) $attachmentId])->fetch();
        if (!$a || (int) $a['member_id'] !== (int) $this->user()['id']) {
            abortHttp(403);
        }
        @unlink($this->storagePath($a['path']));
        $this->db->delete('attachments', $a['id']);
        http_response_code(204);
        exit;
    }

    public function removeTask(array $request, $taskId)
    {
        $this->gateApi();
        $task = $this->findTask((int) $taskId);
        if (!$task || !$this->canUpdateTask($task)) {
            abortHttp(403);
        }
        $paths = $this->db->select('SELECT path FROM attachments WHERE task_id = ?', [$task['id']])->fetchAll();
        $this->db->delete('tasks', $task['id']);
        foreach ($paths as $p) {
            @unlink($this->storagePath($p['path']));
        }
        http_response_code(204);
        exit;
    }

    public function uploadAvatar(array $request)
    {
        $this->gateApi();
        if (empty($_FILES['avatar'])) {
            jsonError('فایل الزامی است.', [], 422);
        }
        if (!extension_loaded('gd')) {
            abortJson(500, 'پردازش تصویر روی سرور فعال نیست.');
        }
        $uid = (int) $this->user()['id'];
        $member = $this->db->select('SELECT * FROM members WHERE id = ?', [$uid])->fetch();
        $bytes = file_get_contents($_FILES['avatar']['tmp_name']);
        $source = @imagecreatefromstring($bytes);
        if (!$source) {
            jsonError('تصویر قابل پردازش نیست.', [], 422);
        }
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
        $relative = 'avatars/' . $uid . '-' . bin2hex(random_bytes(8)) . '.webp';
        $dest = $this->storagePath($relative);
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0775, true);
        }
        if (!$output || file_put_contents($dest, $output) === false) {
            jsonError('ذخیره تصویر انجام نشد. دوباره تلاش کنید.', [], 500);
        }
        $this->db->update('members', $uid, ['avatar_path'], [$relative]);
        if (!empty($member['avatar_path'])) {
            @unlink($this->storagePath($member['avatar_path']));
        }
        $fresh = $this->db->select('SELECT * FROM members WHERE id = ?', [$uid])->fetch();
        jsonResponse(JsonPresenter::member($fresh));
    }

    public function removeAvatar(array $request = [])
    {
        $this->gateApi();
        $uid = (int) $this->user()['id'];
        $member = $this->db->select('SELECT * FROM members WHERE id = ?', [$uid])->fetch();
        if (!empty($member['avatar_path'])) {
            @unlink($this->storagePath($member['avatar_path']));
            $this->db->update('members', $uid, ['avatar_path'], [null]);
        }
        jsonResponse(JsonPresenter::member($this->db->select('SELECT * FROM members WHERE id = ?', [$uid])->fetch()));
    }

    private function gateApi(): void
    {
        \Lib\AuthGate::requireSharedAccess();
        \Lib\AuthGate::requireUser();
        \Lib\AuthGate::requireSecuritySetupComplete();
        if (in_array(methodField(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            verify_csrf(requestPayload());
        }
    }

    private function storeCommentFiles(array $files, array $task, int $commentId, int $uid): void
    {
        $count = is_array($files['name']) ? count($files['name']) : 1;
        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name' => is_array($files['name']) ? $files['name'][$i] : $files['name'],
                'type' => is_array($files['type']) ? $files['type'][$i] : $files['type'],
                'tmp_name' => is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'],
                'error' => is_array($files['error']) ? $files['error'][$i] : $files['error'],
                'size' => is_array($files['size']) ? $files['size'][$i] : $files['size'],
            ];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                continue;
            }
            $saved = $this->saveUpload($file, 'attachments');
            $this->db->insert('attachments', ['task_id', 'member_id', 'comment_id', 'original_name', 'file_name', 'mime', 'size', 'path'], [
                (int) $task['id'], $uid, $commentId, $saved['original_name'], $saved['file_name'], $saved['mime'], $saved['size'], $saved['path'],
            ]);
        }
    }
}
