<?php
/**
 * migrate_add_pga_columns.php
 *
 * One-shot/idempotent schema migration for Railway MySQL.
 *
 * Adds missing columns for sensor-reading tables:
 * - bme_readings.pga
 * - averaged_readings.pga
 * - bme_readings.vibration_intensity (safety)
 * - averaged_readings.vibration_intensity (safety)
 *
 * Usage after deploy:
 *   GET https://<your-railway-domain>/migrate_add_pga_columns.php
 *
 * Optional hardening:
 *   Set MIGRATION_KEY in Railway env and call:
 *   .../migrate_add_pga_columns.php?key=YOUR_KEY
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$requiredKey = getenv('MIGRATION_KEY');
if ($requiredKey !== false && $requiredKey !== '') {
    $providedKey = isset($_GET['key']) ? (string)$_GET['key'] : '';
    if ($providedKey !== (string)$requiredKey) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'error' => 'Forbidden: invalid migration key'
        ]);
        exit;
    }
}

$host = getenv('MYSQLHOST');
$port = getenv('MYSQLPORT');
$db   = getenv('MYSQLDATABASE');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Database connection failed',
        'details' => $e->getMessage(),
    ]);
    exit;
}

function tableExists(PDO $pdo, string $dbName, string $table): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl"
    );
    $stmt->execute([':db' => $dbName, ':tbl' => $table]);
    return intval($stmt->fetchColumn()) > 0;
}

function columnExists(PDO $pdo, string $dbName, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND COLUMN_NAME = :col"
    );
    $stmt->execute([':db' => $dbName, ':tbl' => $table, ':col' => $column]);
    return intval($stmt->fetchColumn()) > 0;
}

function addColumnIfMissing(PDO $pdo, string $dbName, string $table, string $column, string $definition, array &$result): void {
    if (!tableExists($pdo, $dbName, $table)) {
        $result[] = [
            'table' => $table,
            'column' => $column,
            'status' => 'skipped_table_missing'
        ];
        return;
    }

    if (columnExists($pdo, $dbName, $table, $column)) {
        $result[] = [
            'table' => $table,
            'column' => $column,
            'status' => 'already_exists'
        ];
        return;
    }

    $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
    $pdo->exec($sql);

    $result[] = [
        'table' => $table,
        'column' => $column,
        'status' => 'added'
    ];
}

$changes = [];

try {
    addColumnIfMissing($pdo, $db, 'bme_readings', 'pga', 'FLOAT DEFAULT 0', $changes);
    addColumnIfMissing($pdo, $db, 'averaged_readings', 'pga', 'FLOAT DEFAULT 0', $changes);

    // Safety migration in case these are missing in older schemas.
    addColumnIfMissing($pdo, $db, 'bme_readings', 'vibration_intensity', 'FLOAT DEFAULT 0', $changes);
    addColumnIfMissing($pdo, $db, 'averaged_readings', 'vibration_intensity', 'FLOAT DEFAULT 0', $changes);

    echo json_encode([
        'ok' => true,
        'database' => $db,
        'changes' => $changes
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Migration failed',
        'details' => $e->getMessage(),
        'changes' => $changes
    ]);
}
