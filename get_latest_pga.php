<?php
/**
 * get_latest_pga.php
 *
 * Returns the latest PGA-related values from bme_readings.
 *
 * Response:
 * {
 *   "ts": "YYYY-mm-dd HH:ii:ss",
 *   "pga": 0.1234,
 *   "vibration_detected": 0,
 *   "vibration_intensity": 0.0
 * }
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
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
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

$stmt = $pdo->query(
    "SELECT ts, COALESCE(pga, 0) AS pga, COALESCE(vibration_detected, 0) AS vibration_detected, COALESCE(vibration_intensity, 0) AS vibration_intensity
     FROM bme_readings
     ORDER BY ts DESC
     LIMIT 1"
);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(null);
    exit;
}

echo json_encode([
    "ts" => $row["ts"],
    "pga" => floatval($row["pga"]),
    "vibration_detected" => intval($row["vibration_detected"]),
    "vibration_intensity" => floatval($row["vibration_intensity"]),
]);
