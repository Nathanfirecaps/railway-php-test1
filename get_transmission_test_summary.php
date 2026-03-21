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

$pdo->exec(" 
    CREATE TABLE IF NOT EXISTS alert_logs (
        alert_id VARCHAR(128) PRIMARY KEY,
        hazard_type VARCHAR(32) NOT NULL,
        message TEXT,
        severity VARCHAR(16),
        payload_json MEDIUMTEXT,
        t_detect DATETIME(6) NOT NULL,
        t_ack DATETIME(6) NULL,
        response_time_sec FLOAT NULL,
        created_at DATETIME(6) NOT NULL,
        INDEX idx_hazard_created (hazard_type, created_at),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec(" 
    CREATE TABLE IF NOT EXISTS data_transmission_tests (
        trial_number INT PRIMARY KEY,
        expected INT NOT NULL,
        received INT NOT NULL,
        data_loss_percent FLOAT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$hazard_type = isset($_GET['hazard_type']) ? trim((string)$_GET['hazard_type']) : '';
$from_ts = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$to_ts = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
$hours_back = isset($_GET['hours_back']) ? intval($_GET['hours_back']) : 0;
$save = isset($_GET['save']) ? intval($_GET['save']) === 1 : false;
$trial_number = isset($_GET['trial_number']) ? intval($_GET['trial_number']) : null;

$where = [];
$params = [];

if ($hazard_type !== '') {
    $where[] = "hazard_type = :hazard_type";
    $params[':hazard_type'] = $hazard_type;
}

if ($hours_back > 0) {
    $where[] = "t_detect >= DATE_SUB(NOW(6), INTERVAL :hours_back HOUR)";
    $params[':hours_back'] = $hours_back;
} else {
    if ($from_ts !== '') {
        $where[] = "t_detect >= :from_ts";
        $params[':from_ts'] = $from_ts;
    }
    if ($to_ts !== '') {
        $where[] = "t_detect <= :to_ts";
        $params[':to_ts'] = $to_ts;
    }
}

$where_sql = '';
if (!empty($where)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

$expected_sql = "SELECT COUNT(*) AS cnt FROM alert_logs $where_sql";
$received_sql = "SELECT COUNT(*) AS cnt FROM alert_logs $where_sql" . (empty($where_sql) ? " WHERE t_ack IS NOT NULL" : " AND t_ack IS NOT NULL");

$expected_stmt = $pdo->prepare($expected_sql);
$received_stmt = $pdo->prepare($received_sql);

foreach ($params as $key => $value) {
    if ($key === ':hours_back') {
        $expected_stmt->bindValue($key, intval($value), PDO::PARAM_INT);
        $received_stmt->bindValue($key, intval($value), PDO::PARAM_INT);
    } else {
        $expected_stmt->bindValue($key, $value);
        $received_stmt->bindValue($key, $value);
    }
}

$expected_stmt->execute();
$received_stmt->execute();

$expected = intval($expected_stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
$received = intval($received_stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
$loss = $expected > 0 ? (($expected - $received) / $expected) * 100.0 : 0.0;

if ($save) {
    if ($trial_number === null || $trial_number <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "trial_number is required when save=1"]);
        exit;
    }

    $save_stmt = $pdo->prepare(" 
        INSERT INTO data_transmission_tests
        (trial_number, expected, received, data_loss_percent)
        VALUES (:trial_number, :expected, :received, :data_loss_percent)
        ON DUPLICATE KEY UPDATE
            expected = VALUES(expected),
            received = VALUES(received),
            data_loss_percent = VALUES(data_loss_percent)
    ");

    $save_stmt->execute([
        ':trial_number' => $trial_number,
        ':expected' => $expected,
        ':received' => $received,
        ':data_loss_percent' => $loss,
    ]);
}

echo json_encode([
    "ok" => true,
    "trial_number" => $trial_number,
    "hazard_type" => $hazard_type !== '' ? $hazard_type : null,
    "window" => [
        "from" => $from_ts !== '' ? $from_ts : null,
        "to" => $to_ts !== '' ? $to_ts : null,
        "hours_back" => $hours_back > 0 ? $hours_back : null,
    ],
    "expected" => $expected,
    "received" => $received,
    "data_loss_percent" => round($loss, 4),
    "saved" => $save,
]);
