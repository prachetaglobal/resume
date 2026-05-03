<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Resume.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::boot();
if (!Auth::check()) jsonResponse(['ok' => false, 'msg' => 'Unauthorized'], 401);

$action   = $_POST['action'] ?? $_GET['action'] ?? '';
$resumeId = (int)($_POST['resume_id'] ?? $_GET['resume_id'] ?? 0);
$userId   = Auth::id();

// Verify ownership for all actions that need it
if (in_array($action, ['save_title','save_fields','delete_item','add_item','switch_template','reorder_sections','reorder_items','reorder_fields'])) {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        jsonResponse(['ok' => false, 'msg' => 'Invalid token'], 403);
    }
    if ($resumeId && !Resume::getById($resumeId, $userId)) {
        jsonResponse(['ok' => false, 'msg' => 'Not found'], 404);
    }
}

switch ($action) {

    case 'save_title':
        $title = trim($_POST['title'] ?? '');
        if ($title === '') jsonResponse(['ok' => false, 'msg' => 'Title required']);
        Resume::updateTitle($resumeId, $userId, $title);
        Resume::touch($resumeId);
        jsonResponse(['ok' => true]);

    case 'save_fields':
        $itemId = (int)($_POST['item_id'] ?? 0);
        $fields = $_POST['fields'] ?? [];
        if (!is_array($fields)) jsonResponse(['ok' => false, 'msg' => 'Invalid fields']);
        Resume::saveFields($itemId, $fields);
        Resume::touch($resumeId);
        jsonResponse(['ok' => true]);

    case 'add_item':
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $newId     = Resume::addItem($sectionId);
        Resume::touch($resumeId);
        jsonResponse(['ok' => true, 'item_id' => $newId]);

    case 'delete_item':
        $itemId = (int)($_POST['item_id'] ?? 0);
        $ok     = Resume::deleteItem($itemId, $resumeId);
        if ($ok) Resume::touch($resumeId);
        jsonResponse(['ok' => $ok]);

    case 'reorder_sections':
        $sections = $_POST['sections'] ?? [];
        if (is_string($sections)) {
            $sections = json_decode($sections, true);
        }
        if (!is_array($sections)) $sections = [];
        
        Resume::reorderSections($resumeId, $sections);
        Resume::touch($resumeId);
        jsonResponse(['ok' => true]);

    case 'reorder_items':
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $ids       = array_map('intval', $_POST['ids'] ?? []);
        Resume::reorderItems($sectionId, $ids);
        Resume::touch($resumeId);
        jsonResponse(['ok' => true]);

    case 'reorder_fields':
        $itemId = (int)($_POST['item_id'] ?? 0);
        $keys   = $_POST['keys'] ?? [];
        Resume::reorderFields($itemId, $keys);
        Resume::touch($resumeId);
        jsonResponse(['ok' => true]);

    case 'toggle_section':
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $visible   = (bool)(int)($_POST['visible'] ?? 1);
        Resume::toggleSection($sectionId, $resumeId, $visible);
        Resume::touch($resumeId);
        jsonResponse(['ok' => true]);

    case 'switch_template':
        $templateId = (int)($_POST['template_id'] ?? 0);
        Resume::switchTemplate($resumeId, $userId, $templateId);
        Resume::touch($resumeId);
        jsonResponse(['ok' => true, 'reload' => true]);

    default:
        jsonResponse(['ok' => false, 'msg' => 'Unknown action'], 400);
}
