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

// Auto-create table if it doesn't exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS pm25_forecasts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        horizon INT NOT NULL,
        predicted_pm25 FLOAT,
        danger_probability FLOAT,
        danger_flag TINYINT,
        INDEX idx_ts (ts),
        INDEX idx_ts_horizon (ts, horizon)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['forecasts']) || !is_array($input['forecasts'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid 'forecasts' array"]);
    exit;
}

$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("
    INSERT INTO pm25_forecasts (ts, horizon, predicted_pm25, danger_probability, danger_flag)
    VALUES (:ts, :horizon, :predicted_pm25, :danger_probability, :danger_flag)
");

$inserted = 0;
foreach ($input['forecasts'] as $fc) {
    if (!isset($fc['horizon'])) continue;

    $stmt->execute([
        ':ts'                  => $now,
        ':horizon'             => intval($fc['horizon']),
        ':predicted_pm25'      => isset($fc['predicted_pm25']) ? floatval($fc['predicted_pm25']) : null,
        ':danger_probability'  => isset($fc['danger_probability']) ? floatval($fc['danger_probability']) : null,
        ':danger_flag'         => isset($fc['danger_flag']) ? intval($fc['danger_flag']) : null,
    ]);
    $inserted++;
}

echo json_encode(["status" => "ok", "inserted" => $inserted]);
