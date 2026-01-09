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
            width:340px;
            margin:auto;
        }
        h1 { color:#38bdf8; }
        p { font-size:16px; }
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
    <p>🌊 Water Level: <b><span id="water">--</span> m</b></p>
    <p>📏 Installation Height: <b><span id="height">--</span> m</b></p>
    <small>Last update: <span id="time">--</span></small>
</div>

<script>
function updateData() {
    fetch("get_latest.php")
        .then(res => res.json())
        .then(data => {
            if (!data) return;

            document.getElementById("temp").textContent = data.temperature_C;
            document.getElementById("hum").textContent = data.humidity_rh;
            document.getElementById("hi").textContent = data.heat_index_C;
            document.getElementById("pres").textContent = data.pressure_hPa;
            document.getElementById("water").textContent = data.water_level_m;
            document.getElementById("height").textContent = data.installation_height_m;
            document.getElementById("time").textContent = data.ts;
        });
}

updateData();
setInterval(updateData, 2000);
</script>

</body>
</html>

