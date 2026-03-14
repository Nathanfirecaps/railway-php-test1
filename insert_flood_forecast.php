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
    CREATE TABLE IF NOT EXISTS flood_forecasts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        horizon INT NOT NULL,
        predicted_water_level FLOAT,
        flood_probability FLOAT,
        flood_level TINYINT,
        flood_label VARCHAR(32),
        danger_flag TINYINT,
        decision_threshold FLOAT,
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
    INSERT INTO flood_forecasts
    (ts, horizon, predicted_water_level, flood_probability,
     flood_level, flood_label, danger_flag, decision_threshold)
    VALUES
    (:ts, :horizon, :predicted_water_level, :flood_probability,
     :flood_level, :flood_label, :danger_flag, :decision_threshold)
");

$inserted = 0;
foreach ($input['forecasts'] as $fc) {
    if (!isset($fc['horizon'])) {
        continue;
    }

    $stmt->execute([
        ':ts'                    => $now,
        ':horizon'               => intval($fc['horizon']),
        ':predicted_water_level' => isset($fc['predicted_water_level']) ? floatval($fc['predicted_water_level']) : null,
        ':flood_probability'     => isset($fc['flood_probability']) ? floatval($fc['flood_probability']) : null,
        ':flood_level'           => isset($fc['flood_level']) ? intval($fc['flood_level']) : null,
        ':flood_label'           => isset($fc['flood_label']) ? strval($fc['flood_label']) : null,
        ':danger_flag'           => isset($fc['danger_flag']) ? intval($fc['danger_flag']) : null,
        ':decision_threshold'    => isset($fc['decision_threshold']) ? floatval($fc['decision_threshold']) : null,
    ]);
    $inserted++;
}

echo json_encode(["status" => "ok", "inserted" => $inserted]);
