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

	"ACT_POW_TOT" => ["avg"],
	"ACT_POW_L1"  => ["avg"],
	"ACT_POW_L2"  => ["avg"],
	"ACT_POW_L3"  => ["avg"],

	"REACT_POW_TOT" => ["avg"],
	"REACT_POW_L1"  => ["avg"],
	"REACT_POW_L2"  => ["avg"],
	"REACT_POW_L3"  => ["avg"],

	"APP_POW_TOT" => ["avg"],
	"APP_POW_L1"  => ["avg"],
	"APP_POW_L2"  => ["avg"],
	"APP_POW_L3"  => ["avg"],

	"ACT_ENG_IMP" => ["abs"],
	"ACT_ENG_EXP" => ["abs"],

	"U_L1N" => ["avg"],
	"U_L2N" => ["avg"],
	"U_L3N" => ["avg"],

	"U_L1L2" => ["avg"],
	"U_L2L3" => ["avg"],
	"U_L3L1" => ["avg"],

	"I_L1" => ["avg"],
	"I_L2" => ["avg"],
	"I_L3" => ["avg"],

	"PF_TOT" => ["avg"],
	"PF_L1"  => ["avg"],
	"PF_L2"  => ["avg"],
	"PF_L3"  => ["avg"],

	"ANG_U_L1L2" => ["avg"],
	"ANG_U_L2L3" => ["avg"],
	"ANG_U_L3L1" => ["avg"],

	"ANG_I_L1" => ["avg"],
	"ANG_I_L2" => ["avg"],
	"ANG_I_L3" => ["avg"],

	"FREQ" => ["avg"]
];

// Validate required fields
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

// DB connection
$db = db_connect_smartmeter();

// Prepare insert
$stmt = $db->prepare("
    INSERT INTO smartmeter_metrics (
        time_dataset,

        act_pow_tot,
        act_pow_l1,
        act_pow_l2,
        act_pow_l3,

        react_pow_tot,
        react_pow_l1,
        react_pow_l2,
        react_pow_l3,

        app_pow_tot,
        app_pow_l1,
        app_pow_l2,
        app_pow_l3,

        act_eng_imp,
        act_eng_exp,

        u_l1n,
        u_l2n,
        u_l3n,

        u_l1l2,
        u_l2l3,
        u_l3l1,

        i_l1,
        i_l2,
        i_l3,

        pf_tot,
        pf_l1,
        pf_l2,
        pf_l3,

        ang_u_l1l2,
        ang_u_l2l3,
        ang_u_l3l1,

        ang_i_l1,
        ang_i_l2,
        ang_i_l3,

        freq
    ) VALUES (
        ?,
		?, ?, ?, ?,
		?, ?, ?, ?,
		?, ?, ?, ?,
		?, ?,
		?, ?, ?,
		?, ?, ?,
		?, ?, ?,
		?, ?, ?, ?,
		?, ?, ?,
		?, ?, ?,
		?
    )
");

// Bind values
$i = 1;

$stmt->bindValue($i++, ($data["time_dataset"] / 1000), SQLITE3_INTEGER);

function bindAvg($stmt, &$i, $data, $key)
{
	$stmt->bindValue($i++, $data[$key]["avg"], SQLITE3_FLOAT);
}

function bindAbs($stmt, &$i, $data, $key)
{
	$stmt->bindValue($i++, $data[$key]["abs"], SQLITE3_FLOAT);
}

bindAvg($stmt, $i, $data, "ACT_POW_TOT");
bindAvg($stmt, $i, $data, "ACT_POW_L1");
bindAvg($stmt, $i, $data, "ACT_POW_L2");
bindAvg($stmt, $i, $data, "ACT_POW_L3");

bindAvg($stmt, $i, $data, "REACT_POW_TOT");
bindAvg($stmt, $i, $data, "REACT_POW_L1");
bindAvg($stmt, $i, $data, "REACT_POW_L2");
bindAvg($stmt, $i, $data, "REACT_POW_L3");

bindAvg($stmt, $i, $data, "APP_POW_TOT");
bindAvg($stmt, $i, $data, "APP_POW_L1");
bindAvg($stmt, $i, $data, "APP_POW_L2");
bindAvg($stmt, $i, $data, "APP_POW_L3");

bindAbs($stmt, $i, $data, "ACT_ENG_IMP");
bindAbs($stmt, $i, $data, "ACT_ENG_EXP");

bindAvg($stmt, $i, $data, "U_L1N");
bindAvg($stmt, $i, $data, "U_L2N");
bindAvg($stmt, $i, $data, "U_L3N");

bindAvg($stmt, $i, $data, "U_L1L2");
bindAvg($stmt, $i, $data, "U_L2L3");
bindAvg($stmt, $i, $data, "U_L3L1");

bindAvg($stmt, $i, $data, "I_L1");
bindAvg($stmt, $i, $data, "I_L2");
bindAvg($stmt, $i, $data, "I_L3");

bindAvg($stmt, $i, $data, "PF_TOT");
bindAvg($stmt, $i, $data, "PF_L1");
bindAvg($stmt, $i, $data, "PF_L2");
bindAvg($stmt, $i, $data, "PF_L3");

bindAvg($stmt, $i, $data, "ANG_U_L1L2");
bindAvg($stmt, $i, $data, "ANG_U_L2L3");
bindAvg($stmt, $i, $data, "ANG_U_L3L1");

bindAvg($stmt, $i, $data, "ANG_I_L1");
bindAvg($stmt, $i, $data, "ANG_I_L2");
bindAvg($stmt, $i, $data, "ANG_I_L3");

bindAvg($stmt, $i, $data, "FREQ");

// Execute
$result = $stmt->execute();

if (!$result) {
	http_response_code(500);
	echo json_encode(["error" => "DB insert failed"]);
	exit;
}

echo json_encode(["status" => "ok"]);
