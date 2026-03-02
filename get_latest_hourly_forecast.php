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

// Get the latest forecast batch (all 12 horizons from the most recent ts)
$res = $conn->query("
    SELECT horizon, heat_index, danger_raw, danger_calibrated, ts
    FROM hourly_forecasts
    WHERE ts = (SELECT MAX(ts) FROM hourly_forecasts)
    ORDER BY horizon ASC
");

if (!$res) {
    http_response_code(500);
    echo json_encode(["error" => "Query failed"]);
    exit();
}

$forecasts = [];
while ($row = $res->fetch_assoc()) {
    $forecasts[] = $row;
}

echo json_encode([
    "ts" => count($forecasts) > 0 ? $forecasts[0]["ts"] : null,
    "forecasts" => $forecasts
]);
