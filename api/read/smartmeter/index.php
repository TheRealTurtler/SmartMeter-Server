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
$db = db_connect_smartmeter();

// Case 1: no parameters -> return latest dataset
if (!isset($_GET["from"]) && !isset($_GET["to"])) {

	$result = $db->query("
        SELECT *
        FROM smartmeter_metrics
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
		"data" => [formatSmartmeterRow($row)]
	]);

	exit;
}

// Case 2: from/to provided -> range query
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
    FROM smartmeter_metrics
    WHERE time_dataset BETWEEN :from AND :to
    ORDER BY time_dataset ASC
");

$stmt->bindValue(":from", $from, SQLITE3_INTEGER);
$stmt->bindValue(":to",   $to,   SQLITE3_INTEGER);

$result = $stmt->execute();

$rows = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	$rows[] = formatSmartmeterRow($row);
}

echo json_encode([
	"from" => $from,
	"to"   => $to,
	"count" => count($rows),
	"data" => $rows
]);


// ------------------------------------------------------------
// Helper: Convert DB row into SmartMeter JSON structure
// ------------------------------------------------------------
function formatSmartmeterRow(array $r)
{
	return [
		"time_dataset" => (int)$r["time_dataset"],

		"ACT_POW_TOT" => ["avg" => (float)$r["act_pow_tot"]],
		"ACT_POW_L1"  => ["avg" => (float)$r["act_pow_l1"]],
		"ACT_POW_L2"  => ["avg" => (float)$r["act_pow_l2"]],
		"ACT_POW_L3"  => ["avg" => (float)$r["act_pow_l3"]],

		"REACT_POW_TOT" => ["avg" => (float)$r["react_pow_tot"]],
		"REACT_POW_L1"  => ["avg" => (float)$r["react_pow_l1"]],
		"REACT_POW_L2"  => ["avg" => (float)$r["react_pow_l2"]],
		"REACT_POW_L3"  => ["avg" => (float)$r["react_pow_l3"]],

		"APP_POW_TOT" => ["avg" => (float)$r["app_pow_tot"]],
		"APP_POW_L1"  => ["avg" => (float)$r["app_pow_l1"]],
		"APP_POW_L2"  => ["avg" => (float)$r["app_pow_l2"]],
		"APP_POW_L3"  => ["avg" => (float)$r["app_pow_l3"]],

		"ACT_ENG_IMP" => ["abs" => (float)$r["act_eng_imp"]],
		"ACT_ENG_EXP" => ["abs" => (float)$r["act_eng_exp"]],

		"U_L1N" => ["avg" => (float)$r["u_l1n"]],
		"U_L2N" => ["avg" => (float)$r["u_l2n"]],
		"U_L3N" => ["avg" => (float)$r["u_l3n"]],

		"U_L1L2" => ["avg" => (float)$r["u_l1l2"]],
		"U_L2L3" => ["avg" => (float)$r["u_l2l3"]],
		"U_L3L1" => ["avg" => (float)$r["u_l3l1"]],

		"I_L1" => ["avg" => (float)$r["i_l1"]],
		"I_L2" => ["avg" => (float)$r["i_l2"]],
		"I_L3" => ["avg" => (float)$r["i_l3"]],

		"PF_TOT" => ["avg" => (float)$r["pf_tot"]],
		"PF_L1"  => ["avg" => (float)$r["pf_l1"]],
		"PF_L2"  => ["avg" => (float)$r["pf_l2"]],
		"PF_L3"  => ["avg" => (float)$r["pf_l3"]],

		"ANG_U_L1L2" => ["avg" => (float)$r["ang_u_l1l2"]],
		"ANG_U_L2L3" => ["avg" => (float)$r["ang_u_l2l3"]],
		"ANG_U_L3L1" => ["avg" => (float)$r["ang_u_l3l1"]],

		"ANG_I_L1" => ["avg" => (float)$r["ang_i_l1"]],
		"ANG_I_L2" => ["avg" => (float)$r["ang_i_l2"]],
		"ANG_I_L3" => ["avg" => (float)$r["ang_i_l3"]],

		"FREQ" => ["avg" => (float)$r["freq"]]
	];
}
