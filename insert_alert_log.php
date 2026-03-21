<?php
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

$pdo->exec(" 
    CREATE TABLE IF NOT EXISTS alert_logs (
        alert_id VARCHAR(128) PRIMARY KEY,
        hazard_type VARCHAR(32) NOT NULL,
        message TEXT,
        severity VARCHAR(16),
        payload_json MEDIUMTEXT,
        t_detect DATETIME(6) NOT NULL,
        t_ack DATETIME(6) NULL,
        response_time_sec FLOAT NULL,
        created_at DATETIME(6) NOT NULL,
        INDEX idx_hazard_created (hazard_type, created_at),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$input = json_decode(file_get_contents("php://input"), true);
if (!$input || !isset($input['alert_id']) || !isset($input['hazard_type'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields: alert_id, hazard_type"]);
    exit;
}

$alert_id = trim((string)$input['alert_id']);
$hazard_type = trim((string)$input['hazard_type']);
$message = isset($input['message']) ? (string)$input['message'] : null;
$severity = isset($input['severity']) ? (string)$input['severity'] : null;
$payload_json = isset($input['payload']) ? json_encode($input['payload']) : null;

if ($alert_id === '' || $hazard_type === '') {
    http_response_code(400);
    echo json_encode(["error" => "alert_id and hazard_type must be non-empty"]);
    exit;
}

$stmt = $pdo->prepare(" 
    INSERT INTO alert_logs
    (alert_id, hazard_type, message, severity, payload_json, t_detect, created_at)
    VALUES
    (:alert_id, :hazard_type, :message, :severity, :payload_json, NOW(6), NOW(6))
    ON DUPLICATE KEY UPDATE
        hazard_type = VALUES(hazard_type),
        message = VALUES(message),
        severity = VALUES(severity),
        payload_json = VALUES(payload_json)
");

$stmt->execute([
    ':alert_id' => $alert_id,
    ':hazard_type' => $hazard_type,
    ':message' => $message,
    ':severity' => $severity,
    ':payload_json' => $payload_json,
]);

echo json_encode([
    "ok" => true,
    "alert_id" => $alert_id,
    "hazard_type" => $hazard_type,
]);
