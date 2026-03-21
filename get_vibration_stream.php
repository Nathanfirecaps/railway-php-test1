<?php
/**
 * get_vibration_stream.php — Returns the latest vibration stream data for
 * live seismograph visualisation on the website.
 *
 * GET params (all optional):
 *   ?seconds=N   — return last N seconds (default 30, max 60)
 *   ?since=MS    — return points with t_ms > MS (cursor-based polling)
 *
 * RESPONSE:
 *   { "points": [ { "t": epoch_ms, "dyn": float, "ratio": float,
 *                    "pga": float, "intensity": float }, ... ] }
 */
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

// Cursor-based mode: return points newer than ?since=<epoch_ms>
if (isset($_GET['since'])) {
    $since_ms = intval($_GET['since']);
    $stmt = $pdo->prepare("
        SELECT t_ms AS t, x, y, z, dyn, ratio, pga, intensity
        FROM vibration_stream
        WHERE t_ms > :since
        ORDER BY t_ms ASC
        LIMIT 1000
    ");
    $stmt->execute([':since' => $since_ms]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast numeric types
    foreach ($rows as &$r) {
        $r['t']         = intval($r['t']);
        $r['x']         = floatval($r['x']);
        $r['y']         = floatval($r['y']);
        $r['z']         = floatval($r['z']);
        $r['dyn']       = floatval($r['dyn']);
        $r['ratio']     = floatval($r['ratio']);
        $r['pga']       = floatval($r['pga']);
        $r['intensity'] = floatval($r['intensity']);
    }
    unset($r);

    echo json_encode(["points" => $rows]);
    exit;
}

// Time-window mode: return last N seconds (default 30, max 60)
$seconds = isset($_GET['seconds']) ? min(60, max(1, intval($_GET['seconds']))) : 30;
$cutoff_ms = intval(microtime(true) * 1000) - ($seconds * 1000);

$stmt = $pdo->prepare("
    SELECT t_ms AS t, x, y, z, dyn, ratio, pga, intensity
    FROM vibration_stream
    WHERE t_ms > :cutoff
    ORDER BY t_ms ASC
");
$stmt->execute([':cutoff' => $cutoff_ms]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cast numeric types
foreach ($rows as &$r) {
    $r['t']         = intval($r['t']);
    $r['x']         = floatval($r['x']);
    $r['y']         = floatval($r['y']);
    $r['z']         = floatval($r['z']);
    $r['dyn']       = floatval($r['dyn']);
    $r['ratio']     = floatval($r['ratio']);
    $r['pga']       = floatval($r['pga']);
    $r['intensity'] = floatval($r['intensity']);
}
unset($r);

echo json_encode(["points" => $rows]);
