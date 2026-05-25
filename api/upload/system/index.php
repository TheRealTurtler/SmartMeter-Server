<?php
require_once __DIR__ . '/../../db.php';

header("Content-Type: application/json");

// Read JSON
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// Validate JSON
if (!$data || !is_array($data)) {
	http_response_code(400);
	echo json_encode(["error" => "Invalid JSON"]);
	exit;
}

// Required fields
$required = [
	"time_dataset",
	"UPTIME" => ["abs"],
	"MCU_USAGE_1MIN" => ["abs"],
	"MCU_USAGE_5MIN" => ["abs"],
	"MCU_USAGE_15MIN" => ["abs"],
	"RAM_USAGE_BYTE" => ["avg"],
	"RAM_USAGE_PERC" => ["avg"],
	"WIFI_RSSI" => ["avg"],
	"TEMP" => ["avg"]
];

foreach ($required as $key => $value) {
	if (is_array($value)) {
		if (!isset($data[$key])) {
			http_response_code(400);
			echo json_encode(["error" => "Missing group: $key"]);
			exit;
		}
		foreach ($value as $sub) {
			if (!isset($data[$key][$sub])) {
				http_response_code(400);
				echo json_encode(["error" => "Missing field: $key.$sub"]);
				exit;
			}
		}
	} else {
		if (!isset($data[$value])) {
			http_response_code(400);
			echo json_encode(["error" => "Missing field: $value"]);
			exit;
		}
	}
}

// DB Connection
$db = db_connect_system();

// Prepare insert
$stmt = $db->prepare("
    INSERT INTO system_metrics (
        time_dataset,
		uptime,
        mcu_1min, mcu_5min, mcu_15min,
        ram_bytes_avg, ram_perc_avg,
        wifi_avg,
        temp_avg
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

// Bind values
$i = 1;

$stmt->bindValue($i++, ($data["time_dataset"] / 1000), SQLITE3_INTEGER);

function bindAbs($stmt, &$i, $data, $key)
{
	$stmt->bindValue($i++, $data[$key]["abs"], SQLITE3_FLOAT);
}

function bindAvg($stmt, &$i, $data, $key)
{
	$stmt->bindValue($i++, $data[$key]["avg"], SQLITE3_FLOAT);
}

bindAbs($stmt, $i, $data, "UPTIME");

bindAbs($stmt, $i, $data, "MCU_USAGE_1MIN");
bindAbs($stmt, $i, $data, "MCU_USAGE_5MIN");
bindAbs($stmt, $i, $data, "MCU_USAGE_15MIN");

bindAvg($stmt, $i, $data, "RAM_USAGE_BYTE");
bindAvg($stmt, $i, $data, "RAM_USAGE_PERC");

bindAvg($stmt, $i, $data, "WIFI_RSSI");

bindAvg($stmt, $i, $data, "TEMP");

// Execute
$result = $stmt->execute();

if (!$result) {
	http_response_code(500);
	echo json_encode(["error" => "DB insert failed"]);
	exit;
}

// Delete entries older than 30 days
$limit = (time() - 30 * 24 * 60 * 60);

$stmt = $db->prepare("
    DELETE FROM system_metrics
    WHERE time_dataset < :limit
");

$stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
$stmt->execute();

echo json_encode(["status" => "ok"]);
