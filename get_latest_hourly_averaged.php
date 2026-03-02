<?php
// ---- CORS HEADERS ----
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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

// ---- SET TIMEZONE TO ASIA/MANILA (UTC+8) ----
$conn->query("SET time_zone = '+08:00'");

$res = $conn->query("
    SELECT *
    FROM hourly_averages
    ORDER BY ts DESC
    LIMIT 1
");

if (!$res) {
    http_response_code(500);
    echo json_encode(["error" => "Query failed"]);
    exit();
}

echo json_encode($res->fetch_assoc());
