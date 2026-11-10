<?php
declare(strict_types=1);

function business_install(mysqli $db): void
{
    $sql = "CREATE TABLE IF NOT EXISTS business_directory_listings (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(160) NOT NULL,
        category VARCHAR(100) NOT NULL DEFAULT '',
        description TEXT NOT NULL,
        address VARCHAR(255) NOT NULL DEFAULT '',
        phone VARCHAR(64) NOT NULL DEFAULT '',
        website VARCHAR(500) NOT NULL DEFAULT '',
        is_published TINYINT(1) NOT NULL DEFAULT 0,
        KEY business_public_name (is_published, name, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$db->query($sql)) {
        throw new RuntimeException('Business directory setup failed: ' . $db->error);
    }
}

function business_list(mysqli $db, bool $publicOnly): array
{
    $sql = 'SELECT id, name, category, description, address, phone, website, is_published
        FROM business_directory_listings';
    if ($publicOnly) {
        $sql .= ' WHERE is_published = 1';
    }
    $result = $db->query($sql . ' ORDER BY name, id');
    if (!$result) {
        throw new RuntimeException('Business directory lookup failed: ' . $db->error);
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        if ($publicOnly) {
            unset($row['is_published']);
        }
        $rows[] = $row;
    }
    return $rows;
}

function business_save(mysqli $db, int $id, array $input): int
{
    $fields = [];
    foreach (['name' => 160, 'category' => 100, 'description' => 10000,
        'address' => 255, 'phone' => 64, 'website' => 500] as $key => $max) {
        $value = $input[$key] ?? '';
        if (!is_string($value) || preg_match('/\A.{0,' . $max . '}\z/us', trim($value)) !== 1) {
            throw new InvalidArgumentException('Check the length and format of ' . $key . '.');
        }
        $fields[$key] = trim($value);
    }
    ['name' => $name, 'category' => $category, 'description' => $description,
        'address' => $address, 'phone' => $phone, 'website' => $website] = $fields;
    if ($name === '') {
        throw new InvalidArgumentException('Enter a business name.');
    }
    if ($website !== '' && (!preg_match('~^https?://~i', $website) || !filter_var($website, FILTER_VALIDATE_URL))) {
        throw new InvalidArgumentException('Enter a complete HTTP or HTTPS website URL.');
    }
    $published = (int) (($input['is_published'] ?? null) === '1');
    if ($id > 0) {
        $stmt = $db->prepare('UPDATE business_directory_listings
            SET name=?, category=?, description=?, address=?, phone=?, website=?, is_published=? WHERE id=?');
        if ($stmt) {
            $stmt->bind_param('ssssssii', $name, $category, $description, $address, $phone, $website, $published, $id);
        }
    } else {
        $stmt = $db->prepare('INSERT INTO business_directory_listings
            (name, category, description, address, phone, website, is_published) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('ssssssi', $name, $category, $description, $address, $phone, $website, $published);
        }
    }
    if (!$stmt || !$stmt->execute()) {
        throw new RuntimeException('Business save failed: ' . ($stmt ? $stmt->error : $db->error));
    }
    return $id ?: (int) $db->insert_id;
}
