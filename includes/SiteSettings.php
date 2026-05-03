<?php
/**
 * SiteSettings — read/write key-value pairs from the site_settings table.
 * Falls back to APP_NAME / hardcoded defaults if the table doesn't exist.
 */
class SiteSettings {

    private static array $cache = [];
    private static bool  $loaded = false;

    /** Load all settings into cache once per request. */
    private static function load(): void {
        if (self::$loaded) return;
        try {
            $rows = Database::fetchAll('SELECT `key`, `value` FROM site_settings');
            foreach ($rows as $r) self::$cache[$r['key']] = $r['value'];
        } catch (Throwable $e) {
            // Table not yet created — silently use defaults
        }
        self::$loaded = true;
    }

    public static function get(string $key, string $default = ''): string {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, string $value): void {
        try {
            Database::query(
                'INSERT INTO site_settings (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
                [$key, $value]
            );
            self::$cache[$key] = $value;
        } catch (Throwable $e) { /* silently ignore */ }
    }

    public static function setMany(array $pairs): void {
        foreach ($pairs as $key => $value) self::set($key, $value);
    }

    public static function appName(): string {
        return self::get('app_name', APP_NAME);
    }

    public static function appTagline(): string {
        return self::get('app_tagline', 'Build ATS-Ready Resumes in Minutes');
    }

    public static function logoUrl(): string {
        return self::get('app_logo_url', '');
    }

    public static function registrationAllowed(): bool {
        return self::get('allow_registration', '1') === '1';
    }

    public static function maintenanceMode(): bool {
        return self::get('maintenance_mode', '0') === '1';
    }

    public static function flushCache(): void {
        self::$cache  = [];
        self::$loaded = false;
    }

    /** Return all settings as an associative array. */
    public static function all(): array {
        self::load();
        return self::$cache;
    }
}
