<?php
declare(strict_types=1);

// Run against a separate restored legacy database. The full dump must not be imported over Event Forge.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/installer.php';
require_once __DIR__ . '/store.php';

if ($argc !== 3 || !preg_match('/\A-?[0-9]+\z/', $argv[2])) {
    fwrite(STDERR, "Usage: php business/import-legacy.php /private/legacy-db.php PUBLIC_STATUS_CODE\n");
    exit(1);
}
$target = null;
try {
    $config = require $argv[1];
    if (!is_array($config)) {
        throw new RuntimeException('The source config must return an Event Forge style database configuration array.');
    }
    $source = eventforge_connect_with_config($config);
    $target = eventforge_bootstrap_connection();
    if (!$target || !business_schema_ready($target)) {
        throw new RuntimeException('Set up or upgrade the directory in the panel first.');
    }
    $sql = 'SELECT m.*, p.name AS photo_filename, l.name AS logo_filename, a.name AS audio_filename
        FROM members m LEFT JOIN directus_files p ON m.photo=p.id
        LEFT JOIN directus_files l ON m.logo=l.id LEFT JOIN directus_files a ON m.audio=a.id ORDER BY m.id';
    $rows = $source->query($sql);
    if (!$rows) {
        throw new RuntimeException('Could not read legacy members: ' . $source->error);
    }
    if (!$target->begin_transaction()) {
        throw new RuntimeException('Could not begin import transaction.');
    }
    $count = 0;
    while ($row = $rows->fetch_assoc()) {
        $input = array_intersect_key($row, business_fields());
        foreach (['photo', 'logo', 'audio'] as $field) {
            if ($row[$field] !== null && $row[$field . '_filename'] === null) {
                throw new RuntimeException('Unresolved ' . $field . ' on member ID ' . $row['id']);
            }
            $input[$field] = (string) ($row[$field . '_filename'] ?? '');
        }
        $input['status'] = $row['status'];
        $input['sort'] = $row['sort'];
        $input['is_member'] = $row['is_member'];
        $input['is_published'] = (string) ((int) $row['status'] === (int) $argv[2] ? 1 : 0);
        // Import values directly to preserve legacy whitespace, nullable fields, and historic URL formats.
        foreach (array_keys(business_fields()) as $field) {
            $input[$field] = in_array($field, ['photo', 'logo', 'audio'], true)
                ? $row[$field . '_filename'] : $row[$field];
        }
        foreach (['name', 'category', 'description', 'address', 'phone', 'website'] as $field) {
            $input[$field] = $input[$field] ?? '';
        }
        $input['id'] = $row['id'];
        $values = array_values($input);
        $stmt = $target->prepare('INSERT INTO business_directory_listings (`' . implode('`,`', array_keys($input))
            . '`) VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')');
        if (!$stmt || !$stmt->bind_param(str_repeat('s', count($values)), ...$values) || !$stmt->execute()) {
            throw new RuntimeException('Import stopped at member ID ' . $row['id'] . '. ' . ($stmt ? $stmt->error : $target->error));
        }
        $count++;
    }
    if (!$target->commit()) {
        throw new RuntimeException('Could not commit directory import.');
    }
    echo 'Imported ' . $count . " members with original IDs and media filenames. Copy the corresponding files into business/storage/uploads/.\n";
} catch (Throwable $e) {
    if ($target) {
        $target->rollback();
    }
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
