<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\App\Core\Bootstrap::loadEnvironment(dirname(__DIR__));
$db = \App\Database\Database::getConnection();

echo "=== Applying Enterprise Database Upgrades ===" . PHP_EOL;

// 1. Tables from SQL migration
$sql = file_get_contents(dirname(__DIR__) . '/database/migrations/2026_11_05_enterprise_architecture_upgrade.sql');
$db->exec($sql);
echo "[OK] Infrastructure tables created/verified: document_jobs, app_cache, login_attempts" . PHP_EOL;

// 2. Helper to check if index exists
function indexExists(PDO $db, string $table, string $indexName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = :table 
          AND INDEX_NAME = :index
    ");
    $stmt->execute(['table' => $table, 'index' => $indexName]);
    return (int)$stmt->fetchColumn() > 0;
}

// 3. Helper to check if column exists
function columnExists(PDO $db, string $table, string $columnName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = :table 
          AND COLUMN_NAME = :col
    ");
    $stmt->execute(['table' => $table, 'col' => $columnName]);
    return (int)$stmt->fetchColumn() > 0;
}

// 4. Apply 4 Composite Indexes
$indexes = [
    [
        'table' => 'applications',
        'name'  => 'idx_app_student_event_status',
        'cols'  => '(student_id, symposium_event_id, application_status)'
    ],
    [
        'table' => 'symposium_events',
        'name'  => 'idx_se_venue_date_time',
        'cols'  => '(venue_id, event_date, start_time, end_time, status)'
    ],
    [
        'table' => 'symposium_events',
        'name'  => 'idx_se_date_status',
        'cols'  => '(event_date, status, start_time)'
    ],
    [
        'table' => 'generated_certificates',
        'name'  => 'idx_gc_cert_lookup',
        'cols'  => '(application_id, symposium_event_id, recipient_student_id, is_current)'
    ]
];

foreach ($indexes as $idx) {
    if (indexExists($db, $idx['table'], $idx['name'])) {
        echo "[SKIP] Index {$idx['name']} on {$idx['table']} already exists." . PHP_EOL;
    } else {
        $db->exec("CREATE INDEX `{$idx['name']}` ON `{$idx['table']}` {$idx['cols']}");
        echo "[OK] Created composite index {$idx['name']} on {$idx['table']}." . PHP_EOL;
    }
}

// 5. Apply Unique Constraint for Active Applications
// We use a virtual generated column active_student_event_key which is NULL when status is Withdrawn or Cancelled
if (!columnExists($db, 'applications', 'active_student_event_key')) {
    $db->exec("
        ALTER TABLE `applications` 
        ADD COLUMN `active_student_event_key` VARCHAR(60) GENERATED ALWAYS AS (
            IF(`application_status` NOT IN ('Withdrawn', 'Cancelled'), CONCAT(`student_id`, '_', `symposium_event_id`), NULL)
        ) VIRTUAL
    ");
    echo "[OK] Added virtual generated column active_student_event_key to applications." . PHP_EOL;
} else {
    echo "[SKIP] Column active_student_event_key already exists on applications." . PHP_EOL;
}

if (indexExists($db, 'applications', 'uq_student_event_active')) {
    echo "[SKIP] Unique constraint uq_student_event_active already exists." . PHP_EOL;
} else {
    $db->exec("ALTER TABLE `applications` ADD UNIQUE KEY `uq_student_event_active` (`active_student_event_key`)");
    echo "[OK] Created UNIQUE constraint uq_student_event_active on applications(active_student_event_key)." . PHP_EOL;
}

echo "=== All Database Upgrades Applied Successfully ===" . PHP_EOL;
