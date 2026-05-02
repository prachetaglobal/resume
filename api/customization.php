<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Resume.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::boot();
if (!Auth::check())                          jsonResponse(['ok' => false, 'msg' => 'Unauthorized'], 401);
if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) jsonResponse(['ok' => false, 'msg' => 'Invalid token'], 403);

$resumeId = (int)($_POST['resume_id'] ?? 0);
$userId   = Auth::id();

if (!Resume::getById($resumeId, $userId)) jsonResponse(['ok' => false, 'msg' => 'Not found'], 404);

$data = [
    'primary_color'     => sanitizeColor($_POST['primary_color']     ?? '#2c3e50'),
    'accent_color'      => sanitizeColor($_POST['accent_color']      ?? '#3498db'),
    'font_heading'      => safeFontName($_POST['font_heading']        ?? 'Arial'),
    'font_body'         => safeFontName($_POST['font_body']           ?? 'Calibri'),
    'font_size_heading' => min(max((int)($_POST['font_size_heading'] ?? 16), 12), 24),
    'font_size_body'    => min(max((int)($_POST['font_size_body']    ?? 11),  9), 14),
    'line_height'       => round(min(max((float)($_POST['line_height'] ?? 1.5), 1.0), 2.2), 1),
    'section_spacing'   => min(max((int)($_POST['section_spacing']  ?? 16),  8), 40),
    'page_margin'       => min(max((int)($_POST['page_margin']      ?? 20), 10), 40),
];

Resume::saveCustomization($resumeId, $data);
Resume::touch($resumeId);
jsonResponse(['ok' => true]);
