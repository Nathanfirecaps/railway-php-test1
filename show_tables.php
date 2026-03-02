<?php
// Temporary debug endpoint – DELETE after use
header("Access-Control-Allow-Origin: *");
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
    exit();
}

$tables = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_row()) {
    $name = $row[0];
    $cols = [];
    $desc = $conn->query("DESCRIBE `$name`");
    while ($c = $desc->fetch_assoc()) {
        $cols[] = $c['Field'] . ' (' . $c['Type'] . ')';
    }
    $count = $conn->query("SELECT COUNT(*) AS cnt FROM `$name`")->fetch_assoc()['cnt'];
    $tables[] = [
        "table" => $name,
        "rows" => intval($count),
        "columns" => $cols
    ];
}

echo json_encode($tables, JSON_PRETTY_PRINT);
