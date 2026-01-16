<?php
// ---- CORS HEADERS (REQUIRED FOR VERCEL + PI) ----
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ---- DATABASE CONNECTION ----
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

// ---- AUTO-CREATE TABLE (UPDATED WITH PM COLUMNS) ----
$conn->query("
CREATE TABLE IF NOT EXISTS bme_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    temperature_C FLOAT,
    humidity_rh FLOAT,
    pressure_hPa FLOAT,
    gas_resistance_ohm FLOAT,
    heat_index_C FLOAT,

    pm1_0 FLOAT,
    pm2_5 FLOAT,
    pm10  FLOAT,

    water_level_m FLOAT,
    installation_height_m FLOAT,
    rainfall_mm FLOAT
)
");

// ---- READ INPUT ----
$data = json_decode(file_get_contents("php://input"), true);

// ---- INSERT (UPDATED) ----
$stmt = $conn->prepare("
    INSERT INTO bme_readings
    (
        temperature_C,
        humidity_rh,
        pressure_hPa,
        gas_resistance_ohm,
        heat_index_C,

        pm1_0,
        pm2_5,
        pm10,

        water_level_m,
        installation_height_m,
        rainfall_mm
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ddddddddddd",
    $data["temperature"],
    $data["humidity"],
    $data["pressure"],
    $data["gas"],
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
