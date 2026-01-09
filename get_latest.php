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

$res = $conn->query("
    SELECT *
    FROM bme_readings
    ORDER BY ts DESC
    LIMIT 1
");

echo json_encode($res->fetch_assoc());
