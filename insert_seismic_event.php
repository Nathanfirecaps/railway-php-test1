<?php
/**
 * insert_seismic_event.php — Permanently stores a captured seismic event
 * waveform (pre-trigger + event + post-trigger window).
 *
 * POST body (JSON):
 *   { "trigger_time": epoch_ms, "peak_pga": float, "peak_intensity": float,
 *     "sample_rate_hz": float,
 *     "waveform": [ { "t": epoch_ms, "dyn": float, "ratio": float,
 *                      "pga": float, "intensity": float }, ... ] }
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
    CREATE TABLE IF NOT EXISTS seismic_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        trigger_time_ms BIGINT,
        peak_pga FLOAT,
        peak_intensity FLOAT,
        sample_rate_hz FLOAT,
        waveform_json MEDIUMTEXT,
        INDEX idx_ts (ts)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['waveform']) || !is_array($input['waveform'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid 'waveform' array"]);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO seismic_events
    (trigger_time_ms, peak_pga, peak_intensity, sample_rate_hz, waveform_json)
    VALUES (:trigger_time_ms, :peak_pga, :peak_intensity, :sample_rate_hz, :waveform_json)
");

$stmt->execute([
    ':trigger_time_ms' => isset($input['trigger_time']) ? intval($input['trigger_time']) : null,
    ':peak_pga'        => isset($input['peak_pga']) ? floatval($input['peak_pga']) : null,
    ':peak_intensity'  => isset($input['peak_intensity']) ? floatval($input['peak_intensity']) : null,
    ':sample_rate_hz'  => isset($input['sample_rate_hz']) ? floatval($input['sample_rate_hz']) : null,
    ':waveform_json'   => json_encode($input['waveform']),
]);

echo json_encode(["status" => "ok", "id" => $pdo->lastInsertId()]);
