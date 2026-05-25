const API = "/smartmeter/api/read/system/index.php";

let currentDay = new Date();
let charts = [];
let overlayChart = null;

let autoRefreshInterval = null;
let midnightTimer = null;

let lastDatasetTime = 0;

const dayPicker = document.getElementById("dayPicker");

/* ---------------------------
   Date Picker
----------------------------*/

dayPicker.addEventListener("click", (e) => {
	e.preventDefault();
	e.target.showPicker();
});

dayPicker.addEventListener("mousedown", (e) => {
	e.preventDefault();
});

dayPicker.addEventListener("change", (e) => {
	currentDay = new Date(e.target.value);
	loadDay(currentDay);
});

/* ---------------------------
   Helpers
----------------------------*/

function isToday(date) {
	const d = new Date(date);
	const t = new Date();

	d.setHours(0, 0, 0, 0);
	t.setHours(0, 0, 0, 0);

	return d.getTime() === t.getTime();
}

function shouldAutoRefresh(date) {
	const d = new Date(date);
	const now = new Date();

	d.setHours(0, 0, 0, 0);
	now.setHours(0, 0, 0, 0);

	return d >= now;
}

function formatDay(d) {
	return d.toISOString().split("T")[0];
}

function apiFormat(d) {
	return d.toISOString().slice(0, 16);
}

function formatUptime(seconds) {
	const sec = Math.floor(seconds % 60);
	const min = Math.floor((seconds / 60) % 60);
	const hr = Math.floor((seconds / 3600) % 24);
	const days = Math.floor(seconds / 86400);

	const pad = (n) => String(n).padStart(2, "0");

	let out = "";

	if (days > 0) out += `${days}d `;
	if (days > 0 || hr > 0) out += `${pad(hr)}:`;
	else out += "00:";

	out += `${pad(min)}:${pad(sec)}`;

	return out.trim();
}

function getNextMidnight() {
	const next = new Date();
	next.setHours(24, 0, 0, 0);
	return next;
}

function getLatestTimestamp(rows) {
	if (!rows || rows.length === 0) return 0;
	return Math.max(...rows.map(r => r.time_dataset || 0));
}

/* ---------------------------
   Timeline
----------------------------*/

const labels = [];

for (let h = 0; h < 24; h++) {
	for (let m = 0; m < 60; m += 5) {
		labels.push(`${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`);
	}
}

function mapTo24h(rows, key) {
	const arr = new Array(288).fill(null);

	rows.forEach(r => {
		const d = new Date(r.time_dataset * 1000);

		const slot = d.getHours() * 12 + Math.floor(d.getMinutes() / 5);
		arr[slot] = r[key];
	});

	return arr;
}

function extractData(rows) {
	return {
		mcu: mapTo24h(rows, "MCU_USAGE_5MIN").map(v => v?.abs ?? null),
		ram: mapTo24h(rows, "RAM_USAGE_PERC").map(v => v?.avg ?? null),
		wifi: mapTo24h(rows, "WIFI_RSSI").map(v => v?.avg ?? null),
		temp: mapTo24h(rows, "TEMP").map(v => v?.avg ?? null)
	};
}

/* ---------------------------
   Chart Factory
----------------------------*/

function makeChart(ctx, data, color, yConfig) {
	return new Chart(ctx, {
		type: "line",
		data: {
			labels,
			datasets: [{
				data,
				borderColor: color,
				tension: 0.3,
				pointRadius: 0
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,

			interaction: {
				mode: "index",
				axis: "x",
				intersect: false
			},

			plugins: {
				legend: { display: false },
				tooltip: { enabled: true, mode: "index" }
			},

			elements: {
				point: { radius: 0 },
				line: { borderWidth: 2 }
			},

			scales: {
				x: {
					ticks: { maxTicksLimit: 24 }
				},
				y: yConfig
			}
		}
	});
}

/* ---------------------------
   Colors
----------------------------*/

const colors = {
	mcu: "#2a9d8f",
	ram: "#457b9d",
	wifi: "#8d99ae",
	temp: "#d62828"
};

/* ---------------------------
   Unified API apply
----------------------------*/

function applyDataset(json) {
	const rows = json.data || [];

	if (rows.length > 0) {
		document.getElementById("uptime").innerText =
			formatUptime(rows[rows.length - 1].UPTIME.abs);
	}

	const latest = getLatestTimestamp(rows);

	if (latest <= lastDatasetTime) return;

	lastDatasetTime = latest;

	drawCharts(rows);
}

/* ---------------------------
   Load Day
----------------------------*/

function loadDay(day) {
	lastDatasetTime = 0;

	const from = new Date(day);
	from.setHours(0, 0, 0, 0);

	const to = new Date(day);
	to.setHours(23, 59, 59, 999);

	dayPicker.value = formatDay(day);

	fetch(`${API}?from=${apiFormat(from)}&to=${apiFormat(to)}`)
		.then(r => r.json())
		.then(json => {
			applyDataset(json);

			updateAutoRefreshState();
			scheduleMidnightSwitch();
		});
}

/* ---------------------------
   Auto Refresh
----------------------------*/

function fetchAndUpdateIfNeeded() {
	if (!shouldAutoRefresh(currentDay)) return;

	const from = new Date(currentDay);
	from.setHours(0, 0, 0, 0);

	const to = new Date(currentDay);
	to.setHours(23, 59, 59, 999);

	fetch(`${API}?from=${apiFormat(from)}&to=${apiFormat(to)}`)
		.then(r => r.json())
		.then(applyDataset);
}

function updateAutoRefreshState() {
	if (!shouldAutoRefresh(currentDay)) {
		if (autoRefreshInterval) {
			clearInterval(autoRefreshInterval);
			autoRefreshInterval = null;
		}
		return;
	}

	if (!autoRefreshInterval) {
		autoRefreshInterval = setInterval(fetchAndUpdateIfNeeded, 30000);
	}
}

/* ---------------------------
   Midnight Switch
----------------------------*/

function scheduleMidnightSwitch() {
	if (midnightTimer) clearTimeout(midnightTimer);

	if (!isToday(currentDay)) return;

	const ms = getNextMidnight() - new Date();

	midnightTimer = setTimeout(() => {
		currentDay = new Date();
		loadDay(currentDay);
	}, ms);
}

/* ---------------------------
   Charts Render
----------------------------*/

function drawCharts(rows) {
	charts.forEach(c => c.destroy());
	charts = [];

	const data = rows.length ? extractData(rows) : {
		mcu: Array(288).fill(null),
		ram: Array(288).fill(null),
		wifi: Array(288).fill(null),
		temp: Array(288).fill(null)
	};

	const configs = [
		[mcuChart, data.mcu, colors.mcu, { min: 0, max: 100 }],
		[ramChart, data.ram, colors.ram, { min: 0, max: 100 }],
		[wifiChart, data.wifi, colors.wifi, { min: -100, max: -50 }],
		[tempChart, data.temp, colors.temp, { min: 0, max: 80 }]
	];

	configs.forEach(([el, d, col, y]) => {
		const chart = makeChart(el, d, col, y);
		charts.push(chart);

		el.onclick = () => openOverlay(chart.config);
	});
}

/* ---------------------------
   Overlay
----------------------------*/

function openOverlay(chartConfig) {
	document.getElementById("overlay").classList.remove("hidden");

	const ctx = document.getElementById("overlayCanvas").getContext("2d");

	if (overlayChart) overlayChart.destroy();

	overlayChart = new Chart(ctx, chartConfig);
}

function closeOverlay() {
	document.getElementById("overlay").classList.add("hidden");

	if (overlayChart) {
		overlayChart.destroy();
		overlayChart = null;
	}
}

/* ---------------------------
   Navigation
----------------------------*/

function prevDay() {
	currentDay.setDate(currentDay.getDate() - 1);
	loadDay(currentDay);
}

function nextDay() {
	currentDay.setDate(currentDay.getDate() + 1);
	loadDay(currentDay);
}

function goToToday() {
	currentDay = new Date();
	loadDay(currentDay);
}

/* ---------------------------
   Init
----------------------------*/

loadDay(currentDay);
