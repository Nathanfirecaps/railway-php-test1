<?php
header("Content-Type: application/json");

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
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

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
