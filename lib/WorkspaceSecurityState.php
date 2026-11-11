<?php

namespace Lib;

use Database\Database;

class WorkspaceSecurityState
{
    public static function singleton(Database $db): void
    {
        $row = $db->select('SELECT id FROM workspace_security WHERE id = 1')->fetch();
        if (!$row) {
            $db->insert('workspace_security', ['id', 'member_pin_hash', 'admin_pin_configured_at'], [1, null, null]);
        }
    }

    public static function adminPinConfigured(Database $db): bool
    {
        $security = $db->select('SELECT admin_pin_configured_at FROM workspace_security WHERE id = 1')->fetch();
        if ($security && $security['admin_pin_configured_at']) {
            return true;
        }
        return (bool) $db->select("SELECT 1 FROM members WHERE role = 'admin' AND pin_hash IS NOT NULL LIMIT 1")->fetch();
    }

    public static function adminCount(Database $db): int
    {
        return (int) $db->select("SELECT COUNT(*) AS c FROM members WHERE role = 'admin'")->fetch()['c'];
    }

    public static function bootstrapSetupAvailable(Database $db): bool
    {
        return !self::adminPinConfigured($db) && self::adminCount($db) === 0;
    }
}
