<?php
declare(strict_types=1);

function business_fields(): array
{
    return ['name' => 160, 'category' => 100, 'subcategory' => 100,
        'description' => 10000, 'address' => 255, 'city' => 100, 'state' => 100,
        'zip' => 100, 'phone' => 100, 'owner' => 100, 'email' => 100,
        'website' => 500, 'sponsor_level' => 100, 'photo' => 255, 'logo' => 255,
        'audio' => 255, 'geolocation' => 100, 'qrcode' => 255];
}

function business_columns(mysqli $db): array
{
    $result = $db->query("SELECT column_name FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'business_directory_listings'");
    if (!$result) {
        throw new RuntimeException('Could not inspect directory schema: ' . $db->error);
    }
    $columns = [];
    while ($row = $result->fetch_row()) {
        $columns[] = $row[0];
    }
    return $columns;
}

function business_schema_ready(mysqli $db): bool
{
    return !array_diff(array_merge(array_keys(business_fields()), ['id', 'is_published', 'is_member', 'status', 'sort']), business_columns($db));
}

function business_install(mysqli $db): void
{
    $lock = 'business_' . substr(hash('sha256', (string) $db->query('SELECT DATABASE()')->fetch_row()[0]), 0, 40);
    $locked = $db->query("SELECT GET_LOCK('{$lock}', 10)");
    if (!$locked || (int) $locked->fetch_row()[0] !== 1) {
        throw new RuntimeException('Another directory upgrade is in progress. Try again shortly.');
    }
    try {
        if (!$db->query("CREATE TABLE IF NOT EXISTS business_directory_listings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            category VARCHAR(100) NOT NULL DEFAULT '',
            description TEXT NOT NULL,
            address VARCHAR(255) NOT NULL DEFAULT '',
            phone VARCHAR(100) NOT NULL DEFAULT '',
            website VARCHAR(500) NOT NULL DEFAULT '',
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            KEY business_public_name (is_published, name, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
            throw new RuntimeException('Directory setup failed: ' . $db->error);
        }
        $columns = business_columns($db);
        $additions = [];
        foreach (business_fields() as $field => $max) {
            if (!in_array($field, $columns, true)) {
                $additions[] = '`' . $field . '` VARCHAR(' . $max . ') DEFAULT NULL';
            }
        }
        foreach (['is_member' => 'TINYINT(1) DEFAULT NULL', 'status' => 'INT NOT NULL DEFAULT 0',
            'sort' => 'INT UNSIGNED DEFAULT NULL'] as $field => $definition) {
            if (!in_array($field, $columns, true)) {
                $additions[] = '`' . $field . '` ' . $definition;
            }
        }
        foreach ($additions as $addition) {
            if (!$db->query('ALTER TABLE business_directory_listings ADD COLUMN ' . $addition)) {
                throw new RuntimeException('Directory upgrade failed: ' . $db->error);
            }
        }
        $phone = $db->query("SELECT character_maximum_length FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'business_directory_listings' AND column_name = 'phone'")->fetch_row();
        if ((int) $phone[0] < 100 && !$db->query("ALTER TABLE business_directory_listings MODIFY phone VARCHAR(100) NOT NULL DEFAULT ''")) {
            throw new RuntimeException('Directory phone field upgrade failed: ' . $db->error);
        }
    } finally {
        $db->query("SELECT RELEASE_LOCK('{$lock}')");
    }
}

function business_public_record(array $row): array
{
    // Keep internal contact and legacy workflow fields out of the public feed.
    unset($row['is_published'], $row['owner'], $row['status'], $row['sort']);
    $base = business_media_url();
    foreach (['photo', 'logo', 'audio'] as $field) {
        $row[$field . '_url'] = empty($row[$field]) ? null : $base . rawurlencode($row[$field]);
    }
    return $row;
}

function business_media_url(): string
{
    require_once __DIR__ . '/../includes/installer.php';
    return eventforge_public_path('business/media.php?file=');
}

function business_list(mysqli $db, bool $publicOnly): array
{
    $sql = 'SELECT * FROM business_directory_listings';
    if ($publicOnly) {
        $sql .= ' WHERE is_published = 1';
    }
    $result = $db->query($sql . ' ORDER BY name, id');
    if (!$result) {
        throw new RuntimeException('Directory lookup failed: ' . $db->error);
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        $rows[] = $publicOnly ? business_public_record($row) : $row;
    }
    return $rows;
}

function business_save(mysqli $db, int $id, array $input): int
{
    $existing = [];
    if ($id > 0) {
        $result = $db->query('SELECT * FROM business_directory_listings WHERE id=' . $id);
        if (!$result) {
            throw new RuntimeException('Directory lookup failed: ' . $db->error);
        }
        $existing = $result->fetch_assoc();
        if (!$existing) {
            throw new InvalidArgumentException('This business no longer exists.');
        }
    }
    $fields = [];
    foreach (business_fields() as $key => $max) {
        $value = $input[$key] ?? $existing[$key] ?? '';
        if (!is_string($value) || preg_match('/\A.{0,' . $max . '}\z/us', $value) !== 1) {
            throw new InvalidArgumentException('Check the length and format of ' . $key . '.');
        }
        $fields[$key] = trim($value);
    }
    if ($fields['name'] === '') {
        throw new InvalidArgumentException('Enter a business name.');
    }
    foreach (['website', 'qrcode'] as $field) {
        if ($fields[$field] !== '' && $fields[$field] !== ($existing[$field] ?? null)
            && (!preg_match('~^https?://~i', $fields[$field]) || !filter_var($fields[$field], FILTER_VALIDATE_URL))) {
            throw new InvalidArgumentException('Enter a complete HTTP or HTTPS URL for ' . $field . '.');
        }
    }
    if ($fields['email'] !== '' && $fields['email'] !== ($existing['email'] ?? null)
        && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }
    foreach (['photo', 'logo', 'audio'] as $field) {
        if ($fields[$field] !== '' && !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._ -]*\.(jpg|jpeg|png|webp|gif|mp3|wav|ogg)\z/i', $fields[$field])) {
            throw new InvalidArgumentException('Use a media filename for ' . $field . '.');
        }
    }
    foreach (['status' => [-2147483648, 2147483647], 'sort' => [0, 4294967295], 'is_member' => [0, 1]] as $key => [$min, $max]) {
        $value = $input[$key] ?? $existing[$key] ?? ($key === 'status' ? 0 : null);
        if ($key !== 'status' && ($value === '' || $value === null)) {
            $fields[$key] = null;
            continue;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($number === false) {
            throw new InvalidArgumentException('Enter a valid value for ' . $key . '.');
        }
        $fields[$key] = $number;
    }
    $fields['is_published'] = (int) (($input['is_published'] ?? null) === '1');
    $names = array_keys($fields);
    $values = array_values($fields);
    if ($id > 0) {
        $assignments = array_map(function (string $field): string { return '`' . $field . '`=?'; }, $names);
        $sql = 'UPDATE business_directory_listings SET ' . implode(', ', $assignments) . ' WHERE id=?';
        $values[] = $id;
    } else {
        $sql = 'INSERT INTO business_directory_listings (`' . implode('`,`', $names) . '`) VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')';
    }
    $stmt = $db->prepare($sql);
    if (!$stmt || !$stmt->bind_param(str_repeat('s', count($values)), ...$values) || !$stmt->execute()) {
        throw new RuntimeException('Business save failed: ' . ($stmt ? $stmt->error : $db->error));
    }
    return $id ?: (int) $db->insert_id;
}

function business_receive_uploads(array &$input, array $uploads): array
{
    $savedPaths = [];
    try {
        foreach (['photo', 'logo', 'audio'] as $field) {
            $file = $uploads[$field . '_upload'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)
                || !is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name']) > 10 * 1024 * 1024) {
                throw new InvalidArgumentException('Upload a file smaller than 10 MB for ' . $field . '.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $types = $field === 'audio'
                ? ['audio/mpeg' => 'mp3', 'audio/x-wav' => 'wav', 'audio/wav' => 'wav', 'audio/ogg' => 'ogg', 'application/ogg' => 'ogg']
                : ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (!isset($types[$mime])) {
                throw new InvalidArgumentException('Choose a supported ' . ($field === 'audio' ? 'audio' : 'image') . ' file for ' . $field . '.');
            }
            $directory = __DIR__ . '/storage/uploads';
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException('Could not create business upload directory.');
            }
            $name = bin2hex(random_bytes(16)) . '.' . $types[$mime];
            $path = $directory . '/' . $name;
            if (!move_uploaded_file($file['tmp_name'], $path)) {
                throw new RuntimeException('Could not store uploaded media.');
            }
            $savedPaths[] = $path;
            $input[$field] = $name;
        }
        return $savedPaths;
    } catch (Throwable $e) {
        foreach ($savedPaths as $path) {
            unlink($path);
        }
        throw $e;
    }
}
