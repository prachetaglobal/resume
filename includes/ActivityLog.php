<?php
/**
 * ActivityLog — write audit trail entries to the activity_log table.
 * All writes are fire-and-forget; failures are silently ignored.
 */
class ActivityLog {

    public static function log(
        string  $action,
        ?int    $userId   = null,
        ?string $entity   = null,
        ?int    $entityId = null,
        ?string $detail   = null
    ): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        try {
            Database::query(
                'INSERT INTO activity_log (user_id, action, entity, entity_id, detail, ip)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, $action, $entity, $entityId, $detail, $ip]
            );
        } catch (Throwable $e) { /* table may not exist yet */ }
    }

    // ── Convenience wrappers ──────────────────────────────────────────

    public static function userLogin(int $userId, string $email): void {
        self::log('user.login', $userId, 'user', $userId, "Login: $email");
    }

    public static function userLogout(int $userId): void {
        self::log('user.logout', $userId, 'user', $userId);
    }

    public static function userRegistered(int $userId, string $email): void {
        self::log('user.register', $userId, 'user', $userId, "Registered: $email");
    }

    public static function userUpdated(int $adminId, int $targetUserId, string $detail): void {
        self::log('admin.user.update', $adminId, 'user', $targetUserId, $detail);
    }

    public static function userDeleted(int $adminId, int $targetUserId, string $email): void {
        self::log('admin.user.delete', $adminId, 'user', $targetUserId, "Deleted user: $email");
    }

    public static function resumeCreated(int $userId, int $resumeId, string $title): void {
        self::log('resume.create', $userId, 'resume', $resumeId, "Created: $title");
    }

    public static function resumeDeleted(int $userId, int $resumeId, string $title): void {
        self::log('resume.delete', $userId, 'resume', $resumeId, "Deleted: $title");
    }

    public static function resumeExported(int $userId, int $resumeId, string $title): void {
        self::log('resume.export', $userId, 'resume', $resumeId, "PDF export: $title");
    }

    public static function planSettingsChanged(int $adminId, string $detail): void {
        self::log('admin.plan.update', $adminId, 'plan', null, $detail);
    }

    public static function siteSettingsChanged(int $adminId, string $detail): void {
        self::log('admin.site.update', $adminId, 'site', null, $detail);
    }

    // ── Fetch helpers ─────────────────────────────────────────────────

    public static function recent(int $limit = 100, ?string $action = null): array {
        try {
            if ($action) {
                return Database::fetchAll(
                    'SELECT al.*, u.name AS user_name, u.email AS user_email
                       FROM activity_log al
                       LEFT JOIN users u ON u.id = al.user_id
                      WHERE al.action LIKE ?
                      ORDER BY al.created_at DESC LIMIT ' . (int)$limit,
                    ["%$action%"]
                );
            }
            return Database::fetchAll(
                'SELECT al.*, u.name AS user_name, u.email AS user_email
                   FROM activity_log al
                   LEFT JOIN users u ON u.id = al.user_id
                  ORDER BY al.created_at DESC LIMIT ' . (int)$limit
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}
