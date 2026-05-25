<?php
require_once __DIR__ . '/../../db.php';

header("Content-Type: application/json");

// Convert timestamps "yyyy-MM-ddThh:mm" to UNIX seconds
function parseTimestamp($str)
{
	$str = str_replace("T", " ", $str);
	$ts = strtotime($str);
	if ($ts === false) {
		return null;
	}
	return $ts;
}

// DB connection
$db = db_connect_system();


// ------------------------------------------------------------
// Case 1: no parameters -> return latest dataset
// ------------------------------------------------------------
if (!isset($_GET["from"]) && !isset($_GET["to"])) {

	$result = $db->query("
        SELECT *
        FROM system_metrics
        ORDER BY time_dataset DESC
        LIMIT 1
    ");

	$row = $result->fetchArray(SQLITE3_ASSOC);

	if (!$row) {
		echo json_encode(["error" => "No data available"]);
		exit;
	}

	echo json_encode([
		"count" => 1,
		"data" => [formatSystemRow($row)]
	]);

	exit;
}


// ------------------------------------------------------------
// Case 2: from/to provided -> range query
// ------------------------------------------------------------
$now = time();
$from = isset($_GET["from"]) ? parseTimestamp($_GET["from"]) : $now;
$to   = isset($_GET["to"])   ? parseTimestamp($_GET["to"])   : $now;

if ($from === null || $to === null) {
	http_response_code(400);
	echo json_encode(["error" => "Invalid timestamp format"]);
	exit;
}

// Only allow 7 days in a single request
$maxTo = $from + 7 * 24 * 60 * 60;

if ($to > $maxTo) {
	$to = $maxTo;
}

if ($from >= $to) {
	http_response_code(400);
	echo json_encode(["error" => "Invalid timestamp range"]);
	exit;
}


// Query range
$stmt = $db->prepare("
    SELECT *
    FROM system_metrics
    WHERE time_dataset BETWEEN :from AND :to
    ORDER BY time_dataset ASC
");

$stmt->bindValue(":from", $from, SQLITE3_INTEGER);
$stmt->bindValue(":to",   $to,   SQLITE3_INTEGER);

$result = $stmt->execute();

$rows = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	$rows[] = formatSystemRow($row);
}

echo json_encode([
	"from" => $from,
	"to"   => $to,
	"count" => count($rows),
	"data" => $rows
]);


// ------------------------------------------------------------
// Helper: Convert DB row into System JSON structure
// ------------------------------------------------------------
function formatSystemRow(array $row)
{
	return [
		"time_dataset" => (int)$row["time_dataset"],

		"UPTIME" => ["abs" => (int)$row["uptime"]],

		"MCU_USAGE_1MIN" => ["abs" => (float)$row["mcu_1min"]],
		"MCU_USAGE_5MIN" => ["abs" => (float)$row["mcu_5min"]],
		"MCU_USAGE_15MIN" => ["abs" => (float)$row["mcu_15min"]],

		"RAM_USAGE_BYTE" => ["avg" => (float)$row["ram_bytes_avg"]],
		"RAM_USAGE_PERC" => ["avg" => (float)$row["ram_perc_avg"]],

		"WIFI_RSSI" => ["avg" => (float)$row["wifi_avg"]],

		"TEMP" => ["avg" => (float)$row["temp_avg"]]
	];
}
