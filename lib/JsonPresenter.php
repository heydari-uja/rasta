<?php

namespace Lib;

class JsonPresenter
{
    public static function avatarUrl(?array $member): ?string
    {
        if (!$member || empty($member['avatar_path'])) {
            return null;
        }
        $v = substr(hash('sha256', $member['avatar_path']), 0, 16);
        return url('avatars/' . $member['id'] . '?v=' . $v);
    }

    public static function member(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        unset($row['pin_hash'], $row['remember_token']);
        $path = $row['avatar_path'] ?? null;
        unset($row['avatar_path']);
        $row['avatar_url'] = $path ? self::avatarUrl(['id' => $row['id'], 'avatar_path' => $path]) : null;
        $row['active'] = (bool) ($row['active'] ?? true);
        return $row;
    }

    public static function stage(array $row): array
    {
        if (isset($row['name'])) {
            $row['title'] = $row['name'];
        }
        return $row;
    }

    public static function label(array $row): array
    {
        if (isset($row['member_id'])) {
            $row['created_by'] = (int) $row['member_id'];
        }
        return $row;
    }

    public static function attachment(array $row, ?array $user = null): array
    {
        if (isset($row['mime'])) {
            $row['mime_type'] = $row['mime'];
        }
        if ($user) {
            $row['user'] = self::member($user);
        }
        return $row;
    }

    public static function comment(array $row, ?array $user = null, array $attachments = []): array
    {
        $row['user'] = self::member($user);
        $row['attachments'] = array_map(function ($a) use ($user) {
            return self::attachment($a, $user);
        }, $attachments);
        return $row;
    }

    public static function task(array $row, array $extras = []): array
    {
        foreach (['subtasks', 'labels', 'activities', 'comments', 'attachments'] as $key) {
            if (isset($extras[$key])) {
                $row[$key] = $extras[$key];
            }
        }
        if (isset($extras['assignee'])) {
            $row['assignee'] = self::member($extras['assignee']);
        }
        if (isset($extras['creator'])) {
            $row['creator'] = self::member($extras['creator']);
        }
        if (isset($extras['member_stage'])) {
            $row['member_stage'] = $extras['member_stage'] ? self::stage($extras['member_stage']) : null;
        }
        if (isset($extras['stage'])) {
            $row['stage'] = $extras['stage'] ? self::stage($extras['stage']) : null;
        }
        return $row;
    }

    public static function project(array $row, array $extras = []): array
    {
        foreach (['goal', 'stages', 'member_stages', 'members', 'memberStages'] as $key) {
            if (isset($extras[$key])) {
                $row[$key] = $extras[$key];
            }
        }
        if (isset($extras['tasks_count'])) {
            $row['tasks_count'] = (int) $extras['tasks_count'];
        }
        if (isset($extras['completed_count'])) {
            $row['completed_count'] = (int) $extras['completed_count'];
        }
        return $row;
    }
}
