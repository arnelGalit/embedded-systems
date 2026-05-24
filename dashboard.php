<?php

// --- DATABASE CONFIG ---
$connection = new mysqli("localhost", "root", "", "plant_monitoring"); // Change this if necessary

if ($connection->connect_error) {
    die("<p style='color:red;font-family:sans-serif;padding:20px;'>MySQL connection failed: " . $connection->connect_error . "</p>");
}

// ============================================================
// PAGINATION SETTINGS
// ============================================================

$limit = 10;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) {
    $page = 1;
}

$offset = ($page - 1) * $limit;

// ============================================================
// TOTAL RECORDS
// ============================================================

$totalQuery = $connection->query("SELECT COUNT(*) AS total FROM sensor_data");

$totalData = $totalQuery->fetch_assoc();

$totalRows = $totalData['total'];

$totalPages = ceil($totalRows / $limit);

// ============================================================
// TABLE DATA (PAGINATED)
// ============================================================

$result = $connection->query("
    SELECT * 
    FROM sensor_data 
    ORDER BY id DESC 
    LIMIT $limit OFFSET $offset
");

// ============================================================
// LINE GRAPH DATA (LAST 50 RECORDS)
// ============================================================

$chartResult = $connection->query("
    SELECT * 
    FROM sensor_data 
    ORDER BY id DESC 
    LIMIT 50
");

$rows      = [];
$labels    = [];
$tempData  = [];
$soilData  = [];
$humidData = [];

// TABLE ROWS
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

// CHART DATA
while ($row = $chartResult->fetch_assoc()) {

    array_unshift($labels,
        isset($row['date_recorded'])
        ? date('M d h:i A', strtotime($row['date_recorded']))
        : "#" . $row['id']
    );

    array_unshift($tempData,  (float)$row['temperature']);
    array_unshift($soilData,  (float)$row['soil_moisture']);
    array_unshift($humidData, (float)$row['humidity']);
}

$connection->close();

$labelsJson = json_encode($labels);
$tempJson   = json_encode($tempData);
$soilJson   = json_encode($soilData);
$humidJson  = json_encode($humidData);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Plant Monitor — Charts and History</title>

<style>

/* ---- RESET & BASE ---- */

*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --green:       #2e7d32;
    --green-light: #e8f5e9;
    --green-dark:  #1b5e20;
    --body-bg:     #f1f8f1;
    --card-bg:     #ffffff;
    --shadow:      0 4px 18px rgba(0,0,0,0.07);
    --radius:      14px;
    --font:        -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                   Helvetica, Arial, sans-serif;
}

body {
    font-family: var(--font);
    background: var(--body-bg);
    min-height: 100vh;
    padding: 24px 16px 48px;
    color: #333;
}

/* ---- HEADER ---- */

header {
    text-align: center;
    margin-bottom: 28px;
}

header h1 {
    font-size: 1.7rem;
    font-weight: 800;
    color: var(--green);
}

header p {
    color: #777;
    font-size: .875rem;
    margin-top: 4px;
}

/* ---- SECTION TITLES ---- */

.section-title {
    font-size: 1rem;
    font-weight: 800;
    color: var(--green);
    margin: 24px auto 10px;
    max-width: 920px;
}

/* ---- CHART GRID ---- */

.chart-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    max-width: 920px;
    margin: 0 auto;
}

@media (max-width: 600px) {
    .chart-grid {
        grid-template-columns: 1fr;
    }
}

.chart-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    padding: 16px;
    box-shadow: var(--shadow);
}

.chart-card h3 {
    font-size: .78rem;
    font-weight: 700;
    color: #555;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 10px;
}

.chart-wrapper {
    position: relative;
    width: 100%;
    height: 220px;
    overflow: hidden;
}

canvas.sparkline {
    width: 100% !important;
    height: 100% !important;
    display: block;
}

/* ---- TABLE ---- */

.table-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    padding: 16px;
    box-shadow: var(--shadow);
    max-width: 920px;
    margin: 0 auto;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: .86rem;
}

thead th {
    background: var(--green-light);
    color: var(--green);
    font-weight: 800;
    padding: 9px 12px;
    text-align: left;
    font-size: .76rem;
    text-transform: uppercase;
    letter-spacing: .4px;
}

thead th:first-child {
    border-radius: 8px 0 0 8px;
}

thead th:last-child {
    border-radius: 0 8px 8px 0;
}

tbody tr:nth-child(even) {
    background: #f7fdf7;
}

tbody tr:hover {
    background: var(--green-light);
}

tbody td {
    padding: 8px 12px;
    border-bottom: 1px solid #efefef;
    color: #444;
}

/* ---- BADGES ---- */

.badge {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 10px;
    font-size: .72rem;
    font-weight: 700;
    color: #fff;
}

.dry {
    background: #e53935;
}

.moist {
    background: #43a047;
}

.wet {
    background: #1e88e5;
}

.no-records {
    text-align: center;
    color: #aaa;
    padding: 30px;
    font-size: .9rem;
}

/* ---- PAGINATION ---- */

.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.pagination a {
    text-decoration: none;
    padding: 8px 14px;
    border-radius: 8px;
    background: white;
    color: var(--green);
    font-weight: 700;
    box-shadow: var(--shadow);
    transition: .2s;
}

.pagination a:hover {
    background: var(--green-light);
}

.pagination a.active {
    background: var(--green);
    color: white;
}

/* ---- TOOLTIP ---- */

#chart-tooltip {
    position: fixed;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: .8rem;
    font-family: var(--font);
    pointer-events: none;
    display: none;
    box-shadow: 0 2px 8px rgba(0,0,0,.12);
    z-index: 1000;
    color: #333;
}

</style>
</head>

<body>

<header>
    <h1>🌱 Plant Monitor — Charts & History</h1>
    <p>Latest monitoring data from sensors</p>
</header>

<div class="section-title">
    Sensor Readings (Last 50 Readings)
</div>

<div class="chart-grid">

    <div class="chart-card">
        <h3>Temperature (°C)</h3>

        <div class="chart-wrapper">
            <canvas class="sparkline" id="tempChart"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3>Soil Moisture (%)</h3>

        <div class="chart-wrapper">
            <canvas class="sparkline" id="soilChart"></canvas>
        </div>
    </div>

    <div class="chart-card" style="grid-column: 1 / -1;">
        <h3>Humidity (%)</h3>

        <div class="chart-wrapper">
            <canvas class="sparkline" id="humidChart"></canvas>
        </div>
    </div>

</div>

<div class="section-title">
    Database Records
</div>

<div class="table-card">

<?php if (empty($rows)): ?>

    <p class="no-records">
        No records found in the database yet.
    </p>

<?php else: ?>

<table>

    <thead>
        <tr>
            <th>ID</th>
            <th>Date Recorded</th>
            <th>Temperature</th>
            <th>Humidity</th>
            <th>Soil Moisture</th>
            <th>Soil Status</th>
        </tr>
    </thead>

    <tbody>

    <?php foreach ($rows as $row):

        $soil = (int)$row['soil_moisture'];

        if ($soil < 25) {
            $soilLabel = "DRY";
            $soilClass = "dry";
        }
        elseif ($soil <= 70) {
            $soilLabel = "MOIST";
            $soilClass = "moist";
        }
        else {
            $soilLabel = "WET";
            $soilClass = "wet";
        }

        $displayTime =
            isset($row['date_recorded'])
            ? date('M d, Y, h:i:s A', strtotime($row['date_recorded']))
            : '---';

    ?>

    <tr>
        <td><?= (int)$row['id'] ?></td>

        <td><?= $displayTime ?></td>

        <td>
            <?= number_format((float)$row['temperature'], 1) ?> °C
        </td>

        <td>
            <?= number_format((float)$row['humidity'], 1) ?> %
        </td>

        <td>
            <?= $soil ?> %
        </td>

        <td>
            <span class="badge <?= $soilClass ?>">
                <?= $soilLabel ?>
            </span>
        </td>
    </tr>

    <?php endforeach; ?>

    </tbody>

</table>

<!-- PAGINATION -->

<div class="pagination">

<?php if ($page > 1): ?>
    <a href="?page=<?= $page - 1 ?>">
        Previous
    </a>
<?php endif; ?>

<?php for ($i = 1; $i <= $totalPages; $i++): ?>

    <a href="?page=<?= $i ?>"
       class="<?= ($i == $page) ? 'active' : '' ?>">
        <?= $i ?>
    </a>

<?php endfor; ?>

<?php if ($page < $totalPages): ?>
    <a href="?page=<?= $page + 1 ?>">
        Next
    </a>
<?php endif; ?>

</div>

<?php endif; ?>

</div>

<div id="chart-tooltip"></div>

<script>

const labels    = <?= $labelsJson ?>;
const tempData  = <?= $tempJson ?>;
const soilData  = <?= $soilJson ?>;
const humidData = <?= $humidJson ?>;

const tooltip = document.getElementById('chart-tooltip');

function drawChart(id, data, labels, color, unit, yMin, yMax) {

    const canvas = document.getElementById(id);

    if (!canvas || !data.length) return;

    const dpr = window.devicePixelRatio || 1;

    const W = canvas.offsetWidth;
    const H = canvas.offsetHeight;

    canvas.width  = W * dpr;
    canvas.height = H * dpr;

    const ctx = canvas.getContext('2d');

    ctx.scale(dpr, dpr);

    const PAD_LEFT   = 42;
    const PAD_RIGHT  = 12;
    const PAD_TOP    = 12;
    const PAD_BOTTOM = 55;

    const plotW = W - PAD_LEFT - PAD_RIGHT;
    const plotH = H - PAD_TOP  - PAD_BOTTOM;

    const minVal = yMin !== null ? yMin : Math.min(...data);
    const maxVal = yMax !== null ? yMax : Math.max(...data);

    const range = maxVal - minVal || 1;

    function xOf(i) {
        return PAD_LEFT + (i / (data.length - 1 || 1)) * plotW;
    }

    function yOf(val) {
        return PAD_TOP + plotH - ((val - minVal) / range) * plotH;
    }

    // GRID LINES

    ctx.font = '10px Arial';

    ctx.fillStyle = '#999';

    ctx.textAlign = 'right';

    const ticks = 4;

    for (let t = 0; t <= ticks; t++) {

        const v = minVal + (range / ticks) * t;

        const y = yOf(v);

        ctx.beginPath();

        ctx.strokeStyle = '#eee';

        ctx.lineWidth = 1;

        ctx.moveTo(PAD_LEFT, y);

        ctx.lineTo(PAD_LEFT + plotW, y);

        ctx.stroke();

        ctx.fillText(v.toFixed(1), PAD_LEFT - 4, y + 3);
    }

    // X LABELS

    ctx.textAlign = 'center';

    const step = Math.max(1, Math.floor(data.length / 6));

    for (let i = 0; i < data.length; i += step) {

        ctx.save();

        ctx.translate(xOf(i), H - 10);

        ctx.rotate(-0.45);

        ctx.fillText(labels[i], 0, 0);

        ctx.restore();
    }

    // GRADIENT

    const grad = ctx.createLinearGradient(
        0,
        PAD_TOP,
        0,
        PAD_TOP + plotH
    );

    grad.addColorStop(0, color + '55');

    grad.addColorStop(1, color + '08');

    // FILLED AREA

    ctx.beginPath();

    ctx.moveTo(xOf(0), yOf(data[0]));

    for (let i = 1; i < data.length; i++) {

        const x0 = xOf(i - 1);
        const y0 = yOf(data[i - 1]);

        const x1 = xOf(i);
        const y1 = yOf(data[i]);

        const cx = (x0 + x1) / 2;

        ctx.bezierCurveTo(cx, y0, cx, y1, x1, y1);
    }

    ctx.lineTo(xOf(data.length - 1), PAD_TOP + plotH);

    ctx.lineTo(xOf(0), PAD_TOP + plotH);

    ctx.closePath();

    ctx.fillStyle = grad;

    ctx.fill();

    // LINE

    ctx.beginPath();

    ctx.moveTo(xOf(0), yOf(data[0]));

    for (let i = 1; i < data.length; i++) {

        const x0 = xOf(i - 1);
        const y0 = yOf(data[i - 1]);

        const x1 = xOf(i);
        const y1 = yOf(data[i]);

        const cx = (x0 + x1) / 2;

        ctx.bezierCurveTo(cx, y0, cx, y1, x1, y1);
    }

    ctx.strokeStyle = color;

    ctx.lineWidth = 2.2;

    ctx.lineJoin = 'round';

    ctx.stroke();

    // DOTS

    for (let i = 0; i < data.length; i++) {

        ctx.beginPath();

        ctx.arc(xOf(i), yOf(data[i]), 3, 0, Math.PI * 2);

        ctx.fillStyle = color;

        ctx.fill();
    }

    // TOOLTIP

    canvas.addEventListener('mousemove', e => {

        const rect = canvas.getBoundingClientRect();

        const mx = e.clientX - rect.left;

        let closest = 0;

        let minDist = Infinity;

        for (let i = 0; i < data.length; i++) {

            const d = Math.abs(xOf(i) - mx);

            if (d < minDist) {
                minDist = d;
                closest = i;
            }
        }

        if (minDist < 30) {

            tooltip.style.display = 'block';

            tooltip.style.left = (e.clientX + 12) + 'px';

            tooltip.style.top = (e.clientY - 28) + 'px';

            tooltip.innerHTML =
                `<strong>${labels[closest]}</strong><br>
                 ${data[closest]} ${unit}`;

        } else {

            tooltip.style.display = 'none';
        }
    });

    canvas.addEventListener('mouseleave', () => {

        tooltip.style.display = 'none';
    });
}

const tMin = Math.max(0, Math.min(...tempData) - 3);

const tMax = Math.max(...tempData) + 3;

drawChart(
    'tempChart',
    tempData,
    labels,
    '#fb8c00',
    '°C',
    tMin,
    tMax
);

drawChart(
    'soilChart',
    soilData,
    labels,
    '#43a047',
    '%',
    0,
    100
);

drawChart(
    'humidChart',
    humidData,
    labels,
    '#1e88e5',
    '%',
    0,
    100
);

window.addEventListener('resize', () => {

    drawChart(
        'tempChart',
        tempData,
        labels,
        '#fb8c00',
        '°C',
        tMin,
        tMax
    );

    drawChart(
        'soilChart',
        soilData,
        labels,
        '#43a047',
        '%',
        0,
        100
    );

    drawChart(
        'humidChart',
        humidData,
        labels,
        '#1e88e5',
        '%',
        0,
        100
    );
});

</script>

</body>
</html>

