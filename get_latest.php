<?php
// ---- CORS HEADERS (REQUIRED FOR VERCEL) ----
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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

// ---- GET LATEST ROW ----
$res = $conn->query("
    SELECT *
    FROM bme_readings
    ORDER BY ts DESC
    LIMIT 1
");

echo json_encode($res->fetch_assoc());

