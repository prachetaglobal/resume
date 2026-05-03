<?php
require_once __DIR__ . '/Database.php';

class Resume {

    // ----------------------------------------------------------------
    // RESUME CRUD
    // ----------------------------------------------------------------

    public static function create(int $userId, string $title = 'My Resume', int $templateId = 1): int {
        $uuid = self::uuid4();

        Database::beginTransaction();
        try {
            $id = (int) Database::insert(
                'INSERT INTO resumes (uuid, user_id, template_id, title) VALUES (?,?,?,?)',
                [$uuid, $userId, $templateId, $title]
            );

            // Default customisation row
            Database::insert(
                'INSERT INTO resume_customizations (resume_id) VALUES (?)',
                [$id]
            );

            // Default sections
            $defaults = [
                ['personal',    'Personal Information', 0],
                ['summary',     'Professional Summary', 1],
                ['experience',  'Work Experience',      2],
                ['education',   'Education',            3],
                ['skills',      'Skills',               4],
                ['certifications','Certifications',     5],
                ['projects',    'Projects',             6],
                ['languages',   'Languages',            7],
            ];
            foreach ($defaults as [$type, $title, $order]) {
                $secId = (int) Database::insert(
                    'INSERT INTO resume_sections (resume_id, type, title, sort_order) VALUES (?,?,?,?)',
                    [$id, $type, $title, $order]
                );
                // Personal section gets one default item
                if ($type === 'personal') {
                    Database::insert(
                        'INSERT INTO resume_items (section_id, sort_order) VALUES (?,0)',
                        [$secId]
                    );
                }
            }

            Database::commit();
            return $id;
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    public static function getById(int $id, int $userId): ?array {
        return Database::fetchOne(
            'SELECT r.*, t.slug AS template_slug, t.name AS template_name
             FROM resumes r JOIN templates t ON t.id = r.template_id
             WHERE r.id = ? AND r.user_id = ?',
            [$id, $userId]
        );
    }

    public static function getAllByUser(int $userId): array {
        return Database::fetchAll(
            'SELECT r.*, t.name AS template_name, t.slug AS template_slug
             FROM resumes r JOIN templates t ON t.id = r.template_id
             WHERE r.user_id = ? ORDER BY r.updated_at DESC',
            [$userId]
        );
    }

    public static function delete(int $id, int $userId): bool {
        $rows = Database::query(
            'DELETE FROM resumes WHERE id = ? AND user_id = ?',
            [$id, $userId]
        )->rowCount();
        return $rows > 0;
    }

    public static function updateTitle(int $id, int $userId, string $title): bool {
        return Database::query(
            'UPDATE resumes SET title = ? WHERE id = ? AND user_id = ?',
            [trim($title), $id, $userId]
        )->rowCount() > 0;
    }

    public static function switchTemplate(int $id, int $userId, int $templateId): bool {
        return Database::query(
            'UPDATE resumes SET template_id = ? WHERE id = ? AND user_id = ?',
            [$templateId, $id, $userId]
        )->rowCount() > 0;
    }

    public static function touch(int $id): void {
        Database::query('UPDATE resumes SET updated_at = NOW() WHERE id = ?', [$id]);
    }

    // ----------------------------------------------------------------
    // SECTIONS
    // ----------------------------------------------------------------

    public static function getSections(int $resumeId): array {
        return Database::fetchAll(
            'SELECT * FROM resume_sections WHERE resume_id = ? ORDER BY sort_order ASC',
            [$resumeId]
        );
    }

    public static function addSection(int $resumeId, string $type, string $title): int {
        $order = (int) Database::fetchOne(
            'SELECT COALESCE(MAX(sort_order),0)+1 AS o FROM resume_sections WHERE resume_id = ?',
            [$resumeId]
        )['o'];
        return (int) Database::insert(
            'INSERT INTO resume_sections (resume_id, type, title, sort_order) VALUES (?,?,?,?)',
            [$resumeId, $type, $title, $order]
        );
    }

    public static function updateSectionTitle(int $sectionId, int $resumeId, string $title): bool {
        return Database::query(
            'UPDATE resume_sections SET title = ? WHERE id = ? AND resume_id = ?',
            [trim($title), $sectionId, $resumeId]
        )->rowCount() > 0;
    }

    public static function toggleSection(int $sectionId, int $resumeId, bool $visible): void {
        Database::query(
            'UPDATE resume_sections SET is_visible = ? WHERE id = ? AND resume_id = ?',
            [(int)$visible, $sectionId, $resumeId]
        );
    }

    public static function reorderSections(int $resumeId, array $orderedIds): void {
        foreach ($orderedIds as $order => $sectionId) {
            Database::query(
                'UPDATE resume_sections SET sort_order = ? WHERE id = ? AND resume_id = ?',
                [$order, (int)$sectionId, $resumeId]
            );
        }
    }

    // ----------------------------------------------------------------
    // ITEMS
    // ----------------------------------------------------------------

    public static function getItems(int $sectionId): array {
        $items = Database::fetchAll(
            'SELECT * FROM resume_items WHERE section_id = ? ORDER BY sort_order ASC',
            [$sectionId]
        );
        foreach ($items as &$item) {
            $item['fields'] = self::getFields($item['id']);
        }
        return $items;
    }

    public static function addItem(int $sectionId): int {
        $order = (int) Database::fetchOne(
            'SELECT COALESCE(MAX(sort_order),0)+1 AS o FROM resume_items WHERE section_id = ?',
            [$sectionId]
        )['o'];
        return (int) Database::insert(
            'INSERT INTO resume_items (section_id, sort_order) VALUES (?,?)',
            [$sectionId, $order]
        );
    }

    public static function deleteItem(int $itemId, int $resumeId): bool {
        // Verify item belongs to this resume
        $row = Database::fetchOne(
            'SELECT ri.id FROM resume_items ri
             JOIN resume_sections rs ON rs.id = ri.section_id
             WHERE ri.id = ? AND rs.resume_id = ?',
            [$itemId, $resumeId]
        );
        if (!$row) return false;
        Database::query('DELETE FROM resume_items WHERE id = ?', [$itemId]);
        return true;
    }

    public static function reorderItems(int $sectionId, array $orderedIds): void {
        foreach ($orderedIds as $order => $itemId) {
            Database::query(
                'UPDATE resume_items SET sort_order = ? WHERE id = ? AND section_id = ?',
                [$order, (int)$itemId, $sectionId]
            );
        }
    }

    // ----------------------------------------------------------------
    // FIELDS
    // ----------------------------------------------------------------

    public static function getFields(int $itemId): array {
        $rows = Database::fetchAll(
            'SELECT field_key, field_value FROM resume_fields WHERE item_id = ? ORDER BY sort_order ASC, id ASC',
            [$itemId]
        );
        $out = [];
        foreach ($rows as $r) $out[$r['field_key']] = $r['field_value'];
        return $out;
    }

    public static function reorderFields(int $itemId, array $orderedKeys): void {
        foreach ($orderedKeys as $order => $key) {
            Database::query(
                'UPDATE resume_fields SET sort_order = ? WHERE item_id = ? AND field_key = ?',
                [$order, $itemId, $key]
            );
        }
    }

    public static function saveFields(int $itemId, array $fields): void {
        foreach ($fields as $key => $value) {
            $key   = trim($key);
            $value = trim((string)$value);
            if ($key === '') continue;

            $existing = Database::fetchOne(
                'SELECT id FROM resume_fields WHERE item_id = ? AND field_key = ?',
                [$itemId, $key]
            );
            if ($existing) {
                Database::query(
                    'UPDATE resume_fields SET field_value = ? WHERE id = ?',
                    [$value, $existing['id']]
                );
            } else {
                Database::insert(
                    'INSERT INTO resume_fields (item_id, field_key, field_value) VALUES (?,?,?)',
                    [$itemId, $key, $value]
                );
            }
        }
    }

    // ----------------------------------------------------------------
    // CUSTOMIZATIONS
    // ----------------------------------------------------------------

    public static function getCustomization(int $resumeId): array {
        $row = Database::fetchOne(
            'SELECT * FROM resume_customizations WHERE resume_id = ?',
            [$resumeId]
        );
        if (!$row) {
            Database::insert('INSERT INTO resume_customizations (resume_id) VALUES (?)', [$resumeId]);
            $row = Database::fetchOne(
                'SELECT * FROM resume_customizations WHERE resume_id = ?', [$resumeId]
            );
        }
        return $row;
    }

    public static function saveCustomization(int $resumeId, array $data): void {
        $allowed = [
            'primary_color','accent_color','font_heading','font_body',
            'font_size_heading','font_size_body','line_height','section_spacing','page_margin'
        ];
        $sets   = [];
        $values = [];
        foreach ($allowed as $key) {
            if (isset($data[$key])) {
                $sets[]   = "$key = ?";
                $values[] = $data[$key];
            }
        }
        if (empty($sets)) return;
        $values[] = $resumeId;
        Database::query(
            'UPDATE resume_customizations SET ' . implode(', ', $sets) . ' WHERE resume_id = ?',
            $values
        );
    }

    // ----------------------------------------------------------------
    // FULL DATA (for template rendering)
    // ----------------------------------------------------------------

    public static function buildData(int $resumeId): array {
        $sections = self::getSections($resumeId);
        foreach ($sections as &$sec) {
            $sec['items'] = self::getItems($sec['id']);
        }
        return $sections;
    }

    // ----------------------------------------------------------------

    private static function uuid4(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
