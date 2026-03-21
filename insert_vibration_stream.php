<?php
/**
 * insert_vibration_stream.php — Receives a batch of downsampled ADXL345
 * vibration readings and stores them in a rolling table (auto-pruned to
 * the last 60 seconds of data).
 *
 * POST body (JSON):
 *   { "cursor": int, "sample_rate_hz": float,
 *     "points": [ { "t": epoch_ms, "dyn": float, "ratio": float,
 *                    "pga": float, "intensity": float }, ... ] }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

date_default_timezone_set('Asia/Manila');

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
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// Auto-create table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS vibration_stream (
        id INT AUTO_INCREMENT PRIMARY KEY,
        t_ms BIGINT NOT NULL,
        x FLOAT,
        y FLOAT,
        z FLOAT,
        dyn FLOAT,
        ratio FLOAT,
        pga FLOAT,
        intensity FLOAT,
        INDEX idx_t_ms (t_ms)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['points']) || !is_array($input['points'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid 'points' array"]);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO vibration_stream (t_ms, x, y, z, dyn, ratio, pga, intensity)
    VALUES (:t_ms, :x, :y, :z, :dyn, :ratio, :pga, :intensity)
");

$inserted = 0;
foreach ($input['points'] as $pt) {
    if (!isset($pt['t'])) continue;
    $stmt->execute([
        ':t_ms'      => intval($pt['t']),
        ':x'         => isset($pt['x']) ? floatval($pt['x']) : null,
        ':y'         => isset($pt['y']) ? floatval($pt['y']) : null,
        ':z'         => isset($pt['z']) ? floatval($pt['z']) : null,
        ':dyn'       => isset($pt['dyn']) ? floatval($pt['dyn']) : null,
        ':ratio'     => isset($pt['ratio']) ? floatval($pt['ratio']) : null,
        ':pga'       => isset($pt['pga']) ? floatval($pt['pga']) : null,
        ':intensity' => isset($pt['intensity']) ? floatval($pt['intensity']) : null,
    ]);
    $inserted++;
}

// Prune old data — keep only the last 60 seconds
$cutoff_ms = intval(microtime(true) * 1000) - 60000;
$pdo->prepare("DELETE FROM vibration_stream WHERE t_ms < :cutoff")
    ->execute([':cutoff' => $cutoff_ms]);

echo json_encode(["status" => "ok", "inserted" => $inserted]);
