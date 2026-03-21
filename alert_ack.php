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
if (!$input || !isset($input['alert_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing alert_id"]);
    exit;
}

$alert_id = trim((string)$input['alert_id']);
if ($alert_id === '') {
    http_response_code(400);
    echo json_encode(["error" => "alert_id must be non-empty"]);
    exit;
}

$update = $pdo->prepare(" 
    UPDATE alert_logs
    SET
        t_ack = NOW(6),
        response_time_sec = TIMESTAMPDIFF(MICROSECOND, t_detect, NOW(6)) / 1000000
    WHERE alert_id = :alert_id
      AND t_ack IS NULL
");
$update->execute([':alert_id' => $alert_id]);

$select = $pdo->prepare(" 
    SELECT alert_id, response_time_sec, t_ack
    FROM alert_logs
    WHERE alert_id = :alert_id
    LIMIT 1
");
$select->execute([':alert_id' => $alert_id]);
$row = $select->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(["error" => "alert_id not found", "alert_id" => $alert_id]);
    exit;
}

$already_acked = ($update->rowCount() === 0 && !empty($row['t_ack']));
$response_time = isset($row['response_time_sec']) ? floatval($row['response_time_sec']) : null;

error_log("alert_ack: alert_id={$alert_id}, response_time_sec=" . ($response_time === null ? 'null' : $response_time));

echo json_encode([
    "ok" => true,
    "alert_id" => $alert_id,
    "response_time_sec" => $response_time,
    "already_acked" => $already_acked,
]);
