<?php
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

header('Content-Type: application/json');


// -------- IF HISTORY REQUESTED --------
if (isset($_GET['history'])) {

    $limit = intval($_GET['history']); // how many rows to return

    $res = $conn->query("
        SELECT *
        FROM bme_readings
        ORDER BY ts DESC
        LIMIT $limit
    ");

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    echo json_encode($rows);
    exit();
}


// -------- DEFAULT: LATEST DATA --------
$res = $conn->query("
    SELECT *
    FROM bme_readings
    ORDER BY ts DESC
    LIMIT 1
");

echo json_encode($res->fetch_assoc());
