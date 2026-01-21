<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CalamiTech – Live Sensor Data</title>
    <style>
        body {
            background:#0f172a;
            color:#e5e7eb;
            font-family:Arial, sans-serif;
            text-align:center;
            padding-top:40px;
        }
        .card {
            background:#020617;
            border:1px solid #38bdf8;
            border-radius:12px;
            padding:20px;
            width:360px;
            margin:auto;
        }
        h1 { color:#38bdf8; }
        p { font-size:16px; margin:6px 0; }
        hr {
            border:0;
            border-top:1px solid #1e293b;
            margin:10px 0;
        }
        small { color:#94a3b8; }
    </style>
</head>
<body>

<h1>CalamiTech Live Monitoring</h1>

<div class="card">
    <p>🌡 Temp: <b><span id="temp">--</span> °C</b></p>
    <p>💧 Humidity: <b><span id="hum">--</span> %</b></p>
    <p>🔥 Heat Index: <b><span id="hi">--</span> °C</b></p>
    <p>🌬 Pressure: <b><span id="pres">--</span> hPa</b></p>

    <hr>

    <p>🫁 PM1.0: <b><span id="pm1">--</span> µg/m³</b></p>
    <p>🫁 PM2.5: <b><span id="pm25">--</span> µg/m³</b></p>
    <p>🫁 PM10: <b><span id="pm10">--</span> µg/m³</b></p>

    <hr>

    <p>🌊 Water Level: <b><span id="water">--</span> m</b></p>
    <p>📏 Installation Height: <b><span id="height">--</span> m</b></p>
    <p>🌧 Rainfall: <b><span id="rain">--</span> mm</b></p>

    <small>Last update: <span id="time">--</span></small>
</div>

<div class="card" style="margin-top:16px;">
    <h2 style="color:#38bdf8; margin:0 0 10px 0; font-size:18px;">Averaged (10 min)</h2>

    <p>🌡 Temp: <b><span id="avg_temp">--</span> °C</b></p>
    <p>💧 Humidity: <b><span id="avg_hum">--</span> %</b></p>
    <p>🔥 Heat Index: <b><span id="avg_hi">--</span> °C</b></p>
    <p>🌬 Pressure: <b><span id="avg_pres">--</span> hPa</b></p>

    <hr>

    <p>🫁 PM1.0: <b><span id="avg_pm1">--</span> µg/m³</b></p>
    <p>🫁 PM2.5: <b><span id="avg_pm25">--</span> µg/m³</b></p>
    <p>🫁 PM10: <b><span id="avg_pm10">--</span> µg/m³</b></p>

    <hr>

    <p>🌊 Water Level: <b><span id="avg_water">--</span> m</b></p>
    <p>📏 Installation Height: <b><span id="avg_height">--</span> m</b></p>
    <p>🌧 Rainfall: <b><span id="avg_rain">--</span> mm</b></p>

    <small>Last averaged: <span id="avg_time">--</span></small>
</div>

<script>
function updateData() {
    Promise.all([
        fetch("get_latest.php").then(res => res.json()).catch(() => null),
        fetch("get_latest_averaged.php").then(res => res.json()).catch(() => null)
    ])
    .then(([data, avg]) => {
        if (data) {
            document.getElementById("temp").textContent   = data.temperature_C;
            document.getElementById("hum").textContent    = data.humidity_rh;
            document.getElementById("hi").textContent     = data.heat_index_C;
            document.getElementById("pres").textContent   = data.pressure_hPa;

            document.getElementById("pm1").textContent    = data.pm1_0;
            document.getElementById("pm25").textContent   = data.pm2_5;
            document.getElementById("pm10").textContent   = data.pm10;

            document.getElementById("water").textContent  = data.water_level_m;
            document.getElementById("height").textContent = data.installation_height_m;
            document.getElementById("rain").textContent   = data.rainfall_mm;
            document.getElementById("time").textContent   = data.ts;
        }

        if (avg) {
            document.getElementById("avg_temp").textContent   = avg.temperature_C;
            document.getElementById("avg_hum").textContent    = avg.humidity_rh;
            document.getElementById("avg_hi").textContent     = avg.heat_index_C;
            document.getElementById("avg_pres").textContent   = avg.pressure_hPa;

            document.getElementById("avg_pm1").textContent    = avg.pm1_0;
            document.getElementById("avg_pm25").textContent   = avg.pm2_5;
            document.getElementById("avg_pm10").textContent   = avg.pm10;

            document.getElementById("avg_water").textContent  = avg.water_level_m;
            document.getElementById("avg_height").textContent = avg.installation_height_m;
            document.getElementById("avg_rain").textContent   = avg.rainfall_mm;
            document.getElementById("avg_time").textContent   = avg.ts;
        }
    })
    .catch(err => console.error(err));
}

updateData();
setInterval(updateData, 2000);
</script>

</body>
</html>
