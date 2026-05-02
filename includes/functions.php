<?php

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(Auth::csrfToken()) . '">';
}

function getTemplates(): array {
    return Database::fetchAll(
        'SELECT * FROM templates WHERE is_active = 1 ORDER BY sort_order ASC'
    );
}

function getTemplate(int $id): ?array {
    return Database::fetchOne('SELECT * FROM templates WHERE id = ? AND is_active = 1', [$id]);
}

function getTemplateBySlug(string $slug): ?array {
    return Database::fetchOne('SELECT * FROM templates WHERE slug = ? AND is_active = 1', [$slug]);
}

function getTemplateThemes(int $templateId): array {
    return Database::fetchAll(
        'SELECT * FROM template_themes WHERE template_id = ? ORDER BY is_default DESC, name ASC',
        [$templateId]
    );
}

function renderTemplate(string $slug, array $sections, array $customization): string {
    $tplFile = TEMPLATES_PATH . $slug . '/template.php';
    if (!file_exists($tplFile)) {
        $tplFile = TEMPLATES_PATH . 'classic/template.php';
    }
    ob_start();
    include $tplFile;
    return ob_get_clean();
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

function validatePassword(string $p): ?string {
    if (strlen($p) < 8)                          return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $p))              return 'Password must contain an uppercase letter.';
    if (!preg_match('/[0-9]/', $p))              return 'Password must contain a number.';
    return null;
}

function sanitizeColor(string $color): string {
    return preg_match('/^#[0-9a-fA-F]{3,6}$/', $color) ? $color : '#2c3e50';
}

function safeFontName(string $font): string {
    $allowed = ['Arial','Calibri','Georgia','Times New Roman','Helvetica','Verdana','Trebuchet MS','Tahoma'];
    return in_array($font, $allowed, true) ? $font : 'Arial';
}
