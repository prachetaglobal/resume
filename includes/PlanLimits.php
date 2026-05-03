<?php
/**
 * PlanLimits — reads plan settings from the DB (plan_settings table).
 * Falls back to the constants defined in config/app.php if the table
 * has not been migrated yet, so the app never breaks mid-upgrade.
 */
class PlanLimits {

    /** In-request cache: plan => settings row */
    private static array $cache = [];

    /**
     * Return the settings row for a given plan.
     * Columns: max_resumes, max_daily_exports, exports_enabled
     */
    public static function get(string $plan): array {
        if (isset(self::$cache[$plan])) {
            return self::$cache[$plan];
        }

        try {
            $row = Database::fetchOne(
                'SELECT * FROM plan_settings WHERE plan = ?',
                [$plan]
            );
        } catch (Throwable $e) {
            $row = null; // table doesn't exist yet
        }

        if (!$row) {
            // Hardcoded fallback — mirrors the seed defaults
            $row = self::defaults($plan);
        }

        self::$cache[$plan] = $row;
        return $row;
    }

    /** Max resumes allowed for a plan (-1 = unlimited) */
    public static function maxResumes(string $plan): int {
        return (int) self::get($plan)['max_resumes'];
    }

    /** Max PDF exports per day (-1 = unlimited) */
    public static function maxDailyExports(string $plan): int {
        return (int) self::get($plan)['max_daily_exports'];
    }

    /** Whether PDF export is enabled at all for a plan */
    public static function exportsEnabled(string $plan): bool {
        return (bool) self::get($plan)['exports_enabled'];
    }

    /**
     * Check if a user has hit their daily export quota.
     * Counts rows in resume_export_log for today.
     */
    public static function canExport(int $userId, string $plan): bool {
        if (!self::exportsEnabled($plan)) return false;

        $max = self::maxDailyExports($plan);
        if ($max === -1) return true;

        try {
            $row = Database::fetchOne(
                "SELECT COUNT(*) AS cnt FROM resume_export_log
                  WHERE user_id = ? AND DATE(exported_at) = CURDATE()",
                [$userId]
            );
            return ((int)($row['cnt'] ?? 0)) < $max;
        } catch (Throwable $e) {
            return true; // table not yet created — allow
        }
    }

    /** Log a PDF export for rate-limiting. */
    public static function logExport(int $userId, int $resumeId): void {
        try {
            Database::query(
                'INSERT INTO resume_export_log (user_id, resume_id) VALUES (?, ?)',
                [$userId, $resumeId]
            );
        } catch (Throwable $e) {
            // silently ignore if table missing
        }
    }

    /** Flush the in-request cache (useful in admin after saving). */
    public static function flushCache(): void {
        self::$cache = [];
    }

    // ── Private ────────────────────────────────────────────────────────────

    private static function defaults(string $plan): array {
        $defaults = [
            'free'       => ['plan' => 'free',       'max_resumes' => MAX_RESUMES_FREE, 'max_daily_exports' => 3,  'exports_enabled' => 1],
            'pro'        => ['plan' => 'pro',         'max_resumes' => MAX_RESUMES_PRO,  'max_daily_exports' => -1, 'exports_enabled' => 1],
            'enterprise' => ['plan' => 'enterprise',  'max_resumes' => -1,               'max_daily_exports' => -1, 'exports_enabled' => 1],
        ];
        return $defaults[$plan] ?? $defaults['free'];
    }
}
