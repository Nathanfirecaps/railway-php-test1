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
CREATE TABLE IF NOT EXISTS hourly_averages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    temperature_C FLOAT,
    temperature_high_C FLOAT,
    temperature_low_C FLOAT,
    humidity_rh FLOAT,
    pressure_hPa FLOAT,
    heat_index_C FLOAT,
    rain_mm FLOAT,
    dew_point_C FLOAT,
    wet_bulb_C FLOAT,
    rain_rate_mm_hr FLOAT,
    cooling_degree_days FLOAT
)
");

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON"]);
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO hourly_averages
    (temperature_C, temperature_high_C, temperature_low_C,
     humidity_rh, pressure_hPa, heat_index_C, rain_mm,
     dew_point_C, wet_bulb_C, rain_rate_mm_hr, cooling_degree_days)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ddddddddddd",
    $data["temperature"],
    $data["temperature_high"],
    $data["temperature_low"],
    $data["humidity"],
    $data["pressure"],
    $data["heat_index"],
    $data["rain_mm"],
    $data["dew_point"],
    $data["wet_bulb"],
    $data["rain_rate"],
    $data["cooling_degree_days"]
);

$stmt->execute();
echo json_encode(["status" => "ok"]);
