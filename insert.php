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

// ---- AUTO-CREATE TABLE ----
$conn->query("
CREATE TABLE IF NOT EXISTS bme_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    temperature_C FLOAT,
    humidity_rh FLOAT,
    pressure_hPa FLOAT,
    gas_resistance_ohm FLOAT,
    heat_index_C FLOAT,
    water_level_m FLOAT,
    installation_height_m FLOAT
)
");

// ---- READ INPUT ----
$data = json_decode(file_get_contents("php://input"), true);

// ---- INSERT ----
$stmt = $conn->prepare("
    INSERT INTO bme_readings
    (
        temperature_C,
        humidity_rh,
        pressure_hPa,
        gas_resistance_ohm,
        heat_index_C,
        water_level_m,
        installation_height_m
    )
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ddddddd",
    $data["temperature"],
    $data["humidity"],
    $data["pressure"],
    $data["gas"],
    $data["heat_index"],
    $data["water_level"],
    $data["installation_height"]
);

$stmt->execute();

echo json_encode(["status" => "ok"]);

