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

$limit = isset($_GET['limit']) ? max(1, min(200, intval($_GET['limit']))) : 50;
$only_unacked = isset($_GET['unacked']) ? (intval($_GET['unacked']) === 1) : false;

$sql = "
    SELECT
        alert_id,
        hazard_type,
        message,
        severity,
        payload_json,
        t_detect,
        t_ack,
        response_time_sec,
        created_at
    FROM alert_logs
";
if ($only_unacked) {
    $sql .= " WHERE t_ack IS NULL ";
}
$sql .= " ORDER BY created_at DESC LIMIT :lim ";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
    $r['response_time_sec'] = isset($r['response_time_sec']) ? floatval($r['response_time_sec']) : null;
    $r['payload'] = !empty($r['payload_json']) ? json_decode($r['payload_json'], true) : null;
    unset($r['payload_json']);
}
unset($r);

echo json_encode(["alerts" => $rows]);
