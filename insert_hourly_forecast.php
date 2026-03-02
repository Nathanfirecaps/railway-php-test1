<?php
// ---- CORS HEADERS ----
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$conn = new mysqli(
    getenv("MYSQLHOST"),
    getenv("MYSQLUSER"),
    getenv("MYSQLPASSWORD"),
    getenv("MYSQLDATABASE"),
    getenv("MYSQLPORT")
);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit();
}

// Auto-create table
$conn->query("
CREATE TABLE IF NOT EXISTS hourly_forecasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    horizon INT NOT NULL,
    heat_index FLOAT,
    danger_raw FLOAT,
    danger_calibrated FLOAT,
    INDEX idx_ts_horizon (ts, horizon)
)
");

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data["forecasts"])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON, expected 'forecasts' array"]);
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO hourly_forecasts
    (horizon, heat_index, danger_raw, danger_calibrated)
    VALUES (?, ?, ?, ?)
");

$inserted = 0;
foreach ($data["forecasts"] as $f) {
    $stmt->bind_param(
        "iddd",
        $f["horizon"],
        $f["heat_index"],
        $f["danger_raw"],
        $f["danger_calibrated"]
    );
    $stmt->execute();
    $inserted++;
}

echo json_encode(["status" => "ok", "inserted" => $inserted]);
