<?php

namespace Activities\Workspace;

require_once __DIR__ . '/WorkspaceApiTrait.php';

use Database\Database;
use Lib\AuthGate;
use Lib\JsonPresenter;
use Lib\PinSecurity;
use Lib\ValidationException;
use Lib\Validator;
use Lib\WorkspaceSecurityState;

class Workspace
{
    use WorkspaceApiTrait;

    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    private function user(): array
    {
        return currentMember();
    }

    public function shell(...$ignored)
    {
        AuthGate::requireSharedAccess();
        AuthGate::requireUser();
        AuthGate::requireSecuritySetupComplete();
        renderTemplate('app/workspace.php', [
            'isAdmin' => ($this->user()['role'] ?? '') === 'admin',
        ]);
    }

    public function data()
    {
        AuthGate::requireSharedAccess();
        AuthGate::requireUser();
        AuthGate::requireSecuritySetupComplete();
        $user = $this->user();
        $uid = (int) $user['id'];

        $projects = $this->db->select('SELECT * FROM projects ORDER BY id')->fetchAll();
        $projectPayload = [];
        foreach ($projects as $p) {
            $pid = (int) $p['id'];
            $goal = $this->db->select('SELECT * FROM goals WHERE project_id = ?', [$pid])->fetch();
            $stages = array_map([JsonPresenter::class, 'stage'], $this->db->select('SELECT * FROM product_stages WHERE project_id = ? ORDER BY position, id', [$pid])->fetchAll());
            $memberStages = array_map([JsonPresenter::class, 'stage'], $this->db->select('SELECT * FROM member_stages WHERE project_id = ? ORDER BY position, id', [$pid])->fetchAll());
            $memberIds = $this->db->select('SELECT member_id FROM project_members WHERE project_id = ?', [$pid])->fetchAll();
            $members = [];
            foreach ($memberIds as $row) {
                $m = $this->db->select('SELECT * FROM members WHERE id = ?', [$row['member_id']])->fetch();
                if ($m) {
                    $members[] = JsonPresenter::member($m);
                }
            }
            $counts = $this->db->select('SELECT COUNT(*) AS total, SUM(completed_at IS NOT NULL) AS done FROM tasks WHERE project_id = ?', [$pid])->fetch();
            $projectPayload[] = JsonPresenter::project($p, [
                'goal' => $goal,
                'stages' => $stages,
                'member_stages' => $memberStages,
                'members' => $members,
                'tasks_count' => (int) ($counts['total'] ?? 0),
                'completed_count' => (int) ($counts['done'] ?? 0),
            ]);
        }

        $projectIds = array_map(fn ($p) => $p['id'], $projects);
        $inProjects = $projectIds ? implode(',', array_map('intval', $projectIds)) : '0';
        $tasks = $this->db->select("SELECT * FROM tasks WHERE project_id IN ({$inProjects}) OR (project_id IS NULL AND owner_id = ?) ORDER BY id DESC", [$uid])->fetchAll();
        $taskPayload = array_map(fn ($t) => $this->mapTaskSummary($t), $tasks);

        $personalStages = array_map(
            [JsonPresenter::class, 'stage'],
            $this->db->select('SELECT * FROM member_stages WHERE project_id IS NULL AND member_id = ? ORDER BY position, id', [$uid])->fetchAll()
        );

        $labels = array_map([JsonPresenter::class, 'label'], $this->visibleLabels($uid, $projectIds));
        $notifications = $this->db->select(
            'SELECT n.*, a.name AS author_name, a.avatar_path AS author_avatar_path, a.job_title AS author_job_title, a.role AS author_role, a.active AS author_active, a.color AS author_color, t.title AS task_title
             FROM notifications n
             JOIN members a ON a.id = n.author_id
             JOIN tasks t ON t.id = n.task_id
             WHERE n.member_id = ? ORDER BY n.id DESC LIMIT 30',
            [$uid]
        )->fetchAll();
        $notificationPayload = [];
        foreach ($notifications as $n) {
            $author = [
                'id' => $n['author_id'], 'name' => $n['author_name'], 'job_title' => $n['author_job_title'],
                'role' => $n['author_role'], 'active' => $n['author_active'], 'color' => $n['author_color'],
                'avatar_path' => $n['author_avatar_path'],
            ];
            $taskTitle = $n['task_title'] ?? '';
            unset($n['author_name'], $n['author_avatar_path'], $n['author_job_title'], $n['author_role'], $n['author_active'], $n['author_color'], $n['task_title']);
            $n['author'] = JsonPresenter::member($author);
            $n['task'] = ['id' => $n['task_id'], 'title' => $taskTitle];
            $notificationPayload[] = $n;
        }

        $data = [
            'user' => JsonPresenter::member($user),
            'projects' => $projectPayload,
            'personalStages' => $personalStages,
            'tasks' => $taskPayload,
            'labels' => $labels,
            'notifications' => $notificationPayload,
        ];

        if (($user['role'] ?? '') === 'admin') {
            $configured = $this->db->select('SELECT member_pin_hash FROM workspace_security WHERE id = 1')->fetch();
            $data['security'] = ['member_pin_configured' => !empty($configured['member_pin_hash'])];
        }

        jsonResponse($data);
    }

    private function visibleLabels(int $uid, array $projectIds): array
    {
        if (!$projectIds) {
            return $this->db->select('SELECT * FROM labels WHERE project_id IS NULL AND member_id = ?', [$uid])->fetchAll();
        }
        $in = implode(',', array_map('intval', $projectIds));
        return $this->db->select("SELECT * FROM labels WHERE project_id IN ({$in}) OR (project_id IS NULL AND member_id = ?)", [$uid])->fetchAll();
    }

    private function mapTaskSummary(array $task): array
    {
        $extras = [
            'subtasks' => $this->db->select('SELECT * FROM subtasks WHERE task_id = ? ORDER BY position', [$task['id']])->fetchAll(),
            'labels' => array_map(
                [JsonPresenter::class, 'label'],
                $this->db->select(
                    'SELECT l.* FROM labels l JOIN task_labels tl ON tl.label_id = l.id WHERE tl.task_id = ?',
                    [$task['id']]
                )->fetchAll()
            ),
        ];
        if ($task['owner_id']) {
            $extras['assignee'] = $this->db->select('SELECT * FROM members WHERE id = ?', [$task['owner_id']])->fetch();
        }
        if ($task['created_by']) {
            $extras['creator'] = $this->db->select('SELECT * FROM members WHERE id = ?', [$task['created_by']])->fetch();
        }
        if ($task['member_stage_id']) {
            $ms = $this->db->select('SELECT * FROM member_stages WHERE id = ?', [$task['member_stage_id']])->fetch();
            $extras['member_stage'] = $ms ? JsonPresenter::stage($ms) : null;
        }
        if ($task['product_stage_id']) {
            $ps = $this->db->select('SELECT * FROM product_stages WHERE id = ?', [$task['product_stage_id']])->fetch();
            $extras['stage'] = $ps ? JsonPresenter::stage($ps) : null;
        }
        return JsonPresenter::task($task, $extras);
    }

    private function mapTaskDetail(array $task): array
    {
        $summary = $this->mapTaskSummary($task);
        $comments = [];
        foreach ($this->db->select('SELECT * FROM comments WHERE task_id = ? ORDER BY id', [$task['id']])->fetchAll() as $c) {
            $user = $this->db->select('SELECT * FROM members WHERE id = ?', [$c['member_id']])->fetch();
            $atts = $this->db->select('SELECT * FROM attachments WHERE comment_id = ?', [$c['id']])->fetchAll();
            $comments[] = JsonPresenter::comment($c, $user, $atts);
        }
        $attachments = [];
        foreach ($this->db->select('SELECT * FROM attachments WHERE task_id = ? AND comment_id IS NULL', [$task['id']])->fetchAll() as $a) {
            $user = $this->db->select('SELECT * FROM members WHERE id = ?', [$a['member_id']])->fetch();
            $attachments[] = JsonPresenter::attachment($a, $user);
        }
        $activities = $this->db->select(
            'SELECT ta.*, m.name, m.job_title, m.color, m.role, m.active, m.avatar_path FROM task_activities ta JOIN members m ON m.id = ta.member_id WHERE ta.task_id = ? ORDER BY ta.created_at DESC',
            [$task['id']]
        )->fetchAll();
        $activityPayload = [];
        foreach ($activities as $a) {
            $a['member'] = JsonPresenter::member([
                'id' => $a['member_id'], 'name' => $a['name'], 'job_title' => $a['job_title'], 'color' => $a['color'],
                'role' => $a['role'], 'active' => $a['active'], 'avatar_path' => $a['avatar_path'],
            ]);
            unset($a['name'], $a['job_title'], $a['color'], $a['role'], $a['active'], $a['avatar_path']);
            $activityPayload[] = $a;
        }
        $summary['comments'] = $comments;
        $summary['attachments'] = $attachments;
        $summary['activities'] = $activityPayload;
        return $summary;
    }

    private function findTask(int $id): ?array
    {
        return $this->db->select('SELECT * FROM tasks WHERE id = ?', [$id])->fetch() ?: null;
    }

    private function findProject(int $id): ?array
    {
        return $this->db->select('SELECT * FROM projects WHERE id = ?', [$id])->fetch() ?: null;
    }

    private function checkTaskAccess(array $task, bool $write = false): void
    {
        if (!empty($task['project_id'])) {
            return;
        }
        if ((int) $task['owner_id'] !== (int) $this->user()['id']) {
            abortHttp(404);
        }
    }

    private function canUpdateTask(array $task): bool
    {
        return (int) $task['created_by'] === (int) $this->user()['id'];
    }

    private function storagePath(string $relative): string
    {
        return STORAGE_PRIVATE . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function saveUpload(array $file, string $folder): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException(['file' => ['بارگذاری فایل ناموفق بود.']]);
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $name = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
        $relative = $folder . '/' . $name;
        $dest = $this->storagePath($relative);
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0775, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('save failed');
        }
        return [
            'path' => $relative,
            'file_name' => $name,
            'original_name' => mb_substr(basename($file['name']), 0, 200),
            'mime' => $file['type'] ?? 'application/octet-stream',
            'size' => (int) ($file['size'] ?? 0),
        ];
    }

    public function taskDetail($taskId)
    {
        AuthGate::requireSharedAccess();
        AuthGate::requireUser();
        AuthGate::requireSecuritySetupComplete();
        $task = $this->findTask((int) $taskId);
        if (!$task) {
            abortHttp(404);
        }
        $this->checkTaskAccess($task);
        jsonResponse($this->mapTaskDetail($task));
    }

    public function project(array $request)
    {
        $this->gateApi();
        $uid = (int) $this->user()['id'];
        try {
            $v = (new Validator($request, [
                'name' => 'required|string|max:150',
                'color' => 'required|in:project-marker-1,project-marker-2,project-marker-3,project-marker-4,project-marker-5,project-marker-6,project-marker-7,project-marker-8',
                'description' => 'nullable|string|max:2000',
                'goal' => 'required|array',
                'goal.title' => 'required|string|max:200',
                'goal.start_date' => 'required|date_format:Y-m-d',
                'goal.target_date' => 'required|date_format:Y-m-d|after_or_equal:goal.start_date',
            ]))->validate();
        } catch (ValidationException $e) {
            jsonError('اطلاعات پروژه معتبر نیست.', $e->errors);
        }

        try {
            $this->db->beginTransaction();
            $this->db->insert('projects', ['name', 'description', 'created_by', 'color'], [
                $v['name'], $v['description'] ?? null, $uid, $v['color'],
            ]);
            $pid = (int) $this->db->lastInsertId();
            $this->db->query('INSERT INTO project_members (project_id, member_id) VALUES (?, ?)', [$pid, $uid]);
            $g = $v['goal'];
            $this->db->insert('goals', ['project_id', 'title', 'description', 'success_criterion', 'start_date', 'target_date'], [
                $pid, $g['title'], $g['description'] ?? null, $g['success_criterion'] ?? '', $g['start_date'], $g['target_date'],
            ]);
            foreach (($v['stages'] ?? []) as $i => $s) {
                $this->db->insert('product_stages', ['project_id', 'name', 'description', 'start_date', 'end_date', 'position'], [
                    $pid, $s['title'], $s['description'] ?? null, $s['start_date'] ?? null, $s['end_date'] ?? null, $i,
                ]);
            }
            $this->db->commit();
            $project = $this->db->select('SELECT * FROM projects WHERE id = ?', [$pid])->fetch();
            jsonResponse($project, 201);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            jsonError('ساخت پروژه ناموفق بود.', [], 500);
        }
    }

    public function complete(array $request, $taskId)
    {
        $this->gateApi();
        $task = $this->findTask((int) $taskId);
        if (!$task || !$this->canUpdateTask($task)) {
            abortHttp(403);
        }
        $this->checkTaskAccess($task, true);
        try {
            $v = (new Validator($request, ['completed' => 'required|boolean']))->validate();
        } catch (ValidationException $e) {
            jsonError('اطلاعات معتبر نیست.', $e->errors);
        }
        $completed = filter_var($v['completed'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $completedAt = $completed ? date('Y-m-d H:i:s') : null;
        $this->db->update('tasks', $task['id'], ['completed_at'], [$completedAt]);
        $task['completed_at'] = $completedAt;
        jsonResponse($task);
    }

    public function settings(array $request)
    {
        $this->gateApi();
        $uid = (int) $this->user()['id'];
        try {
            $v = (new Validator($request, [
                'name' => ['required', 'string', 'max:100', 'unique:members,name,' . $uid],
                'job_title' => 'required|string|max:100',
            ]))->validate();
        } catch (ValidationException $e) {
            jsonError('اطلاعات معتبر نیست.', $e->errors);
        }
        $this->db->update('members', $uid, ['name', 'job_title'], [$v['name'], $v['job_title']]);
        $member = $this->db->select('SELECT * FROM members WHERE id = ?', [$uid])->fetch();
        jsonResponse(JsonPresenter::member($member));
    }

    public function notifications(array $request, $notificationId = null)
    {
        $this->gateApi();
        $uid = (int) $this->user()['id'];
        if ($notificationId) {
            $n = $this->db->select('SELECT * FROM notifications WHERE id = ?', [(int) $notificationId])->fetch();
            if (!$n || (int) $n['member_id'] !== $uid) {
                abortHttp(404);
            }
            if (!$n['read_at']) {
                $this->db->update('notifications', $n['id'], ['read_at'], [date('Y-m-d H:i:s')]);
            }
        } else {
            $this->db->query('UPDATE notifications SET read_at = NOW() WHERE member_id = ? AND read_at IS NULL', [$uid]);
        }
        jsonResponse(['ok' => true]);
    }

    public function avatar($memberId)
    {
        AuthGate::requireSharedAccess();
        AuthGate::requireSecuritySetupComplete();
        $member = $this->db->select('SELECT * FROM members WHERE id = ?', [(int) $memberId])->fetch();
        if (!$member || empty($member['avatar_path'])) {
            abortHttp(404);
        }
        $path = $this->storagePath($member['avatar_path']);
        if (!is_file($path)) {
            abortHttp(404);
        }
        header('Content-Type: image/webp');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function download($attachmentId)
    {
        AuthGate::requireSharedAccess();
        AuthGate::requireUser();
        AuthGate::requireSecuritySetupComplete();
        $a = $this->db->select('SELECT * FROM attachments WHERE id = ?', [(int) $attachmentId])->fetch();
        if (!$a) {
            abortHttp(404);
        }
        $task = $this->findTask((int) $a['task_id']);
        $this->checkTaskAccess($task);
        $path = $this->storagePath($a['path']);
        if (!is_file($path)) {
            abortHttp(404);
        }
        header('Content-Type: ' . ($a['mime'] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . rawurlencode($a['original_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

}
