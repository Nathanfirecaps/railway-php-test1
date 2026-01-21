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

// Create averaged table (safe if it already exists)
$conn->query("
CREATE TABLE IF NOT EXISTS averaged_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    temperature_C FLOAT,
    humidity_rh FLOAT,
    pressure_hPa FLOAT,
    heat_index_C FLOAT,

    pm1_0 FLOAT,
    pm2_5 FLOAT,
    pm10  FLOAT,

    water_level_m FLOAT,
    installation_height_m FLOAT,
    rainfall_mm FLOAT
)
");

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON"]);
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO averaged_readings
    (
        temperature_C,
        humidity_rh,
        pressure_hPa,
        heat_index_C,

        pm1_0,
        pm2_5,
        pm10,

        water_level_m,
        installation_height_m,
        rainfall_mm
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "dddddddddd",
    $data["temperature"],
    $data["humidity"],
    $data["pressure"],
    $data["heat_index"],

    $data["pm1_0"],
    $data["pm2_5"],
    $data["pm10"],

    $data["water_level"],
    $data["installation_height"],
    $data["rain_mm"]
);

$stmt->execute();

echo json_encode(["status" => "ok"]);
