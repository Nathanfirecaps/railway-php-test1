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
    heat_index_C FLOAT,

    pm1_0 FLOAT,
    pm2_5 FLOAT,
    pm10  FLOAT,

    water_level_m FLOAT,
    installation_height_m FLOAT,
    rainfall_mm FLOAT,
    vibration_detected TINYINT,
    vibration_intensity FLOAT DEFAULT 0
)
");

// Ensure columns exist for older deployments (without failing if they already exist)
$res = $conn->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bme_readings' AND COLUMN_NAME = 'vibration_detected'");
if ($res) {
    $row = $res->fetch_assoc();
    $exists = intval($row['c'] ?? 0);
    if ($exists === 0) {
        $conn->query("ALTER TABLE bme_readings ADD COLUMN vibration_detected TINYINT DEFAULT 0");
    }
}
$res2 = $conn->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bme_readings' AND COLUMN_NAME = 'vibration_intensity'");
if ($res2) {
    $row2 = $res2->fetch_assoc();
    $exists2 = intval($row2['c'] ?? 0);
    if ($exists2 === 0) {
        $conn->query("ALTER TABLE bme_readings ADD COLUMN vibration_intensity FLOAT DEFAULT 0");
    }
}

// ---- READ INPUT ----
$data = json_decode(file_get_contents("php://input"), true);

// ---- INSERT (UPDATED) ----
$stmt = $conn->prepare("
    INSERT INTO bme_readings
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
        rainfall_mm,
        vibration_detected,
        vibration_intensity
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$vib_detected = intval($data["vibration_detected"] ?? 0);
$vib_intensity = floatval($data["vibration_intensity"] ?? 0);

$stmt->bind_param(
    "ddddddddddid",
    $data["temperature"],
    $data["humidity"],
    $data["pressure"],
    $data["heat_index"],

    $data["pm1_0"],
    $data["pm2_5"],
    $data["pm10"],

    $data["water_level"],
    $data["installation_height"],
    $data["rain_mm"],
    $vib_detected,
    $vib_intensity
);

$stmt->execute();

echo json_encode(["status" => "ok"]);
