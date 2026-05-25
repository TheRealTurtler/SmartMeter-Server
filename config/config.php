<?php

return [

	// RAM database
	'db_path_ram' => '/dev/shm/smartmeter-server/metrics_ram.sqlite',

	// Disk database
	'db_path_disk' => __DIR__ . '/../data/metrics_disk.sqlite',

	// System data
	'db_system' => 'RAM',

	// Smartmeter data
	'db_smartmeter' => 'DISK'
];
