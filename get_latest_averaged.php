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

$res = $conn->query("
    SELECT *
    FROM averaged_readings
    ORDER BY ts DESC
    LIMIT 1
");

if (!$res) {
    http_response_code(500);
    echo json_encode(["error" => "Query failed"]);
    exit();
}

echo json_encode($res->fetch_assoc());
