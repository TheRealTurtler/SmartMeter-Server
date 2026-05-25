<?php

function db_connect_system()
{
	// Load configuration
	$config = require __DIR__ . '/../config/config.php';

	if ($config['db_system'] == 'DISK')
		return db_connect_disk();

	return db_connect_ram();
}

function db_connect_smartmeter()
{
	// Load configuration
	$config = require __DIR__ . '/../config/config.php';

	if ($config['db_smartmeter'] == 'DISK')
		return db_connect_disk();

	return db_connect_ram();
}

// Connect to RAM database and ensure directory + tables exist
function db_connect_ram()
{
	static $db = null;

	if ($db !== null) {
		return $db;
	}

	// Load configuration
	$config = require __DIR__ . '/../config/config.php';
	$dbPath = $config['db_path_ram'];

	// Ensure directory exists
	check_and_create_dir($dbPath);

	// Open or create database file
	$db = new SQLite3($dbPath);

	// Initialize required tables
	db_init_system($db);

	return $db;
}

// Connect to disk database and ensure directory + tables exist
function db_connect_disk()
{
	static $db = null;

	if ($db !== null) {
		return $db;
	}

	// Load configuration
	$config = require __DIR__ . '/../config/config.php';
	$dbPath = $config['db_path_disk'];

	// Ensure directory exists
	check_and_create_dir($dbPath);

	// Open or create database file
	$db = new SQLite3($dbPath);

	// Initialize required tables
	db_init_smartmeter($db);

	return $db;
}

/* ---------------------------------------------------------
   Directory creation helper
   --------------------------------------------------------- */

// Ensure the directory for the SQLite file exists
function check_and_create_dir(string $dbPath)
{
	// Extract directory from full file path
	$dir = dirname($dbPath);

	// Create directory if missing
	if (!is_dir($dir)) {
		mkdir($dir, 0775, true);
	}
}

/* ---------------------------------------------------------
   Database initialization routines
   --------------------------------------------------------- */

// Create required tables for system data
function db_init_system(SQLite3 $db)
{
	$db->exec("
        CREATE TABLE IF NOT EXISTS system_metrics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            time_dataset INTEGER NOT NULL,
            uptime INTEGER NOT NULL,
            mcu_1min REAL NOT NULL,
            mcu_5min REAL NOT NULL,
            mcu_15min REAL NOT NULL,
            ram_bytes_avg REAL NOT NULL,
            ram_perc_avg REAL NOT NULL,
            wifi_avg REAL NOT NULL,
            temp_avg REAL NOT NULL
        );
    ");
}

// Create required tables for smartmeter data
function db_init_smartmeter(SQLite3 $db)
{
	$db->exec("
        CREATE TABLE IF NOT EXISTS smartmeter_metrics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            time_dataset INTEGER NOT NULL,

            act_pow_tot REAL NOT NULL,
            act_pow_l1 REAL NOT NULL,
            act_pow_l2 REAL NOT NULL,
            act_pow_l3 REAL NOT NULL,

            react_pow_tot REAL NOT NULL,
            react_pow_l1 REAL NOT NULL,
            react_pow_l2 REAL NOT NULL,
            react_pow_l3 REAL NOT NULL,

            app_pow_tot REAL NOT NULL,
            app_pow_l1 REAL NOT NULL,
            app_pow_l2 REAL NOT NULL,
            app_pow_l3 REAL NOT NULL,

            act_eng_imp REAL NOT NULL,
            act_eng_exp REAL NOT NULL,

            u_l1n REAL NOT NULL,
            u_l2n REAL NOT NULL,
            u_l3n REAL NOT NULL,

            u_l1l2 REAL NOT NULL,
            u_l2l3 REAL NOT NULL,
            u_l3l1 REAL NOT NULL,

            i_l1 REAL NOT NULL,
            i_l2 REAL NOT NULL,
            i_l3 REAL NOT NULL,

            pf_tot REAL NOT NULL,
            pf_l1 REAL NOT NULL,
            pf_l2 REAL NOT NULL,
            pf_l3 REAL NOT NULL,

            ang_u_l1l2 REAL NOT NULL,
            ang_u_l2l3 REAL NOT NULL,
            ang_u_l3l1 REAL NOT NULL,

            ang_i_l1 REAL NOT NULL,
            ang_i_l2 REAL NOT NULL,
            ang_i_l3 REAL NOT NULL,

            freq REAL NOT NULL
        );
    ");
}
