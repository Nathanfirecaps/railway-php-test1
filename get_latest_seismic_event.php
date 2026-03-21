<?php
/**
 * get_latest_seismic_event.php — Returns the most recent saved seismic
 * event waveform for display on the website.
 *
 * GET params (optional):
 *   ?id=N         — return a specific event by ID
 *   ?history=N    — return the N most recent events (metadata only, no waveform)
 *   (default)     — return the single latest event with full waveform
 *
 * RESPONSE (single event):
 *   { "id": int, "ts": datetime, "trigger_time_ms": int,
 *     "peak_pga": float, "peak_intensity": float, "sample_rate_hz": float,
 *     "waveform": [ { "t": epoch_ms, "dyn": float, ... }, ... ] }
 *
 * RESPONSE (?history=N):
 *   [ { "id": int, "ts": datetime, "peak_pga": float,
 *       "peak_intensity": float }, ... ]
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

// History mode — return list of events without waveform data
if (isset($_GET['history'])) {
    $limit = min(100, max(1, intval($_GET['history'])));
    $stmt = $pdo->prepare("
        SELECT id, ts, trigger_time_ms, peak_pga, peak_intensity, sample_rate_hz
        FROM seismic_events
        ORDER BY ts DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['id']              = intval($r['id']);
        $r['trigger_time_ms'] = intval($r['trigger_time_ms']);
        $r['peak_pga']        = floatval($r['peak_pga']);
        $r['peak_intensity']  = floatval($r['peak_intensity']);
        $r['sample_rate_hz']  = floatval($r['sample_rate_hz']);
    }
    unset($r);

    echo json_encode($rows);
    exit;
}

// Single event — by ID or latest
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("
        SELECT id, ts, trigger_time_ms, peak_pga, peak_intensity,
               sample_rate_hz, waveform_json
        FROM seismic_events
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => intval($_GET['id'])]);
} else {
    $stmt = $pdo->query("
        SELECT id, ts, trigger_time_ms, peak_pga, peak_intensity,
               sample_rate_hz, waveform_json
        FROM seismic_events
        ORDER BY ts DESC
        LIMIT 1
    ");
}

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(null);
    exit;
}

$row['id']              = intval($row['id']);
$row['trigger_time_ms'] = intval($row['trigger_time_ms']);
$row['peak_pga']        = floatval($row['peak_pga']);
$row['peak_intensity']  = floatval($row['peak_intensity']);
$row['sample_rate_hz']  = floatval($row['sample_rate_hz']);
$row['waveform']        = json_decode($row['waveform_json'], true);
unset($row['waveform_json']);

echo json_encode($row);
