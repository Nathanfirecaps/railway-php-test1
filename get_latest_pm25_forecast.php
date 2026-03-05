<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

date_default_timezone_set('Asia/Manila');

$host = getenv('MYSQLHOST');
$port = getenv('MYSQLPORT');
$db   = getenv('MYSQLDATABASE');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// Get the latest batch timestamp
$tsRow = $pdo->query("
    SELECT ts FROM pm25_forecasts ORDER BY ts DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$tsRow) {
    echo json_encode(["ts" => null, "forecasts" => []]);
    exit;
}

$latestTs = $tsRow['ts'];

// Fetch all 12 horizon rows for that batch
$stmt = $pdo->prepare("
    SELECT horizon, predicted_pm25, danger_probability, danger_flag, ts
    FROM pm25_forecasts
    WHERE ts = :ts
    ORDER BY horizon ASC
");
$stmt->execute([':ts' => $latestTs]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "ts"        => $latestTs,
    "forecasts" => $rows,
]);
