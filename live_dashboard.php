<?php
require 'session_config.php';
requireLogin();

// --- DATABASE CONFIG ---
$connection = new mysqli("localhost", "root", "", "finalprojectembedded");

if ($connection->connect_error) {
    die("<p style='color:red;font-family:sans-serif;padding:20px;'>MySQL connection failed: " . $connection->connect_error . "</p>");
}

// ============================================================
// GET LATEST SENSOR DATA (Most Recent Record)
// ============================================================

$latestQuery = $connection->query("
    SELECT * 
    FROM sensor_data 
    ORDER BY id DESC 
    LIMIT 1
");

$latestData = $latestQuery->fetch_assoc();

if (!$latestData) {
    die("<p style='color:red;font-family:sans-serif;padding:20px;'>No sensor data found in database</p>");
}

// Extract latest readings
$soilPercent  = (int)$latestData['soil_moisture'];
$temperature  = (float)$latestData['temperature'];
$humidity     = (float)$latestData['humidity'];
$lastRecorded = $latestData['date_recorded'];

// ============================================================
// DETERMINE STATUS BASED ON THRESHOLDS
// ============================================================

// Soil moisture threshold and description
$soilStatus = "MOIST";
$systemAction = "Soil has adequate moisture for healthy plant growth.";
if      ($soilPercent < 25)  { $soilStatus = "DRY";   $systemAction = "The soil is dry. Watering is recommended."; }
else if ($soilPercent <= 70) { $soilStatus = "MOIST"; $systemAction = "Soil has adequate moisture for healthy plant growth."; }
else                         { $soilStatus = "WET";   $systemAction = "Too much water. Stop watering!"; }

// Temperature threshold and description
$tempStatus = "Optimal";
$tempDesc = "Ideal growth range for most tropical and indoor plants";
if      ($temperature > 35)                            { $tempStatus = "Extreme Heat";     $tempDesc = "High evaporation rate; severe wilting and heat stress risk"; }
else if ($temperature >= 28 && $temperature <= 35)      { $tempStatus = "Warm";             $tempDesc = "Plants consume water faster; monitor soil moisture closely"; }
else if ($temperature >= 18 && $temperature <= 27)      { $tempStatus = "Optimal";          $tempDesc = "Ideal growth range for most tropical and indoor plants"; }
else                                                  { $tempStatus = "Cold Environment"; $tempDesc = "Plant metabolism slows; growth becomes slower"; }

// Humidity threshold and description
$humidityStatus = "Optimal Humidity";
$humidityDesc = "Perfect transpiration zone for healthy leaf respiration";
$humidityPrefix = "STATUS";
if      ($humidity > 80)                         { $humidityPrefix = "STATUS"; $humidityStatus = "Excessively Humid"; $humidityDesc = "High risk of leaf mold, fungus, and stagnant air"; }
else if ($humidity >= 50 && $humidity <= 80)      { $humidityPrefix = "STATUS"; $humidityStatus = "Optimal Humidity";  $humidityDesc = "Perfect transpiration zone for healthy leaf respiration"; }
else if ($humidity >= 30 && $humidity <= 49)      { $humidityPrefix = "STATUS"; $humidityStatus = "Dry Air";           $humidityDesc = "Harmless for short periods, but watch for crisp leaf edges"; }
else                                            { $humidityPrefix = "ALERT";  $humidityStatus = "Extremely Dry";     $humidityDesc = "Air is too dry; plant will lose moisture rapidly"; }

// Water tank simulation (assuming 0-100% soil moisture maps to water level)
// In real implementation, you would store water level from ESP32
$waterPercent = $soilPercent;
if      ($soilPercent <= 20)  { $waterStatus = "Low";    $waterPercent = 20; }
else if ($soilPercent <= 50) { $waterStatus = "Half";   $waterPercent = 55; }
else if ($soilPercent <= 80) { $waterStatus = "Good";   $waterPercent = 75; }
else                         { $waterStatus = "Full";   $waterPercent = 95; }

// ============================================================
// DETERMINE COLORS
// ============================================================

$soilColor   = ($soilStatus == "DRY")   ? "#e53935" : (($soilStatus == "MOIST") ? "#1e88e5" : "#43a047");
$statusColor = ($waterPercent <= 20) ? "#e53935" : (($waterPercent <= 55) ? "#009ffb" : "#43a047");
$tempColor   = ($tempStatus == "Extreme Heat")     ? "#e53935"
             : (($tempStatus == "Warm")             ? "#fb8c00"
             : (($tempStatus == "Optimal")          ? "#43a047"
             :                                      "#1e88e5"));
$humColor    = ($humidityStatus == "Excessively Humid") ? "#e53935"
             : (($humidityStatus == "Optimal Humidity")  ? "#43a047"
             : (($humidityStatus == "Dry Air")           ? "#fb8c00"
             :                                           "#e53935"));

// Soil donut (r=54, circumference≈339.3)
$circ    = 339.3;
$dashVal = ($soilPercent / 100.0) * $circ;
$dashArr = round($dashVal, 1) . " " . round($circ - $dashVal, 1);

$connection->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Flora Pulse - Live Dashboard</title>

<style>
:root{
  --font:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
  --primary:#C94C6D;
  --primary-dark:#A03B57;
  --body-bg:#faf6f8;
  --card:#fff;
  --shadow:0 3px 14px rgba(201,76,109,.07);
  --r:14px;
}

*{
  box-sizing:border-box;
  margin:0;
  padding:0;
}

body{
  font-family:var(--font);
  background:var(--body-bg);
  min-height:100vh;
  padding:20px 14px 40px;
}

header{
  text-align:center;
  margin-bottom:24px;
}

.logo {
  height: 60px;
  width: auto;
  object-fit: contain;
}

header p{
  color:#888;
  font-size:.82rem;
  margin-top:3px;
}

.grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:14px;
  max-width:900px;
  margin:0 auto;
}

@media(max-width:640px){
  .grid{
    grid-template-columns:1fr;
  }
}

.card{
  background:var(--card);
  border-radius:var(--r);
  padding:18px 16px;
  box-shadow:var(--shadow);
}

.card-label{
  font-size:.72rem;
  font-weight:700;
  color:#888;
  text-transform:uppercase;
  letter-spacing:.5px;
  margin-bottom:12px;
}

.badge{
  display:inline-block;
  padding:3px 12px;
  border-radius:20px;
  font-size:.75rem;
  font-weight:700;
  color:#fff;
}

.big{
  font-size:2rem;
  font-weight:800;
  color:#222;
  line-height:1;
  margin:6px 0;
}

.desc{
  font-size:.76rem;
  color:#777;
  line-height:1.45;
  margin-top:6px;
}

.status-line{
  font-size:.78rem;
  font-weight:700;
  margin-top:8px;
}

.action{
  font-size:.78rem;
  font-weight:600;
  margin-top:6px;
}

.pump-btn{
  width:100%;
  margin-top:12px;
  padding:11px 0;
  background:#1565c0;
  color:#fff;
  border:none;
  border-radius:10px;
  font-size:.88rem;
  font-weight:700;
  cursor:pointer;
  box-shadow:0 3px #0d47a1;
  transition:.1s;
}

.pump-btn:active{
  box-shadow:0 1px #0d47a1;
  transform:translateY(2px);
}

.pump-btn:disabled{
  background:#bbb;
  box-shadow:0 3px #999;
  cursor:not-allowed;
  transform:none;
}

.last-updated{
  text-align:center;
  font-size:.75rem;
  color:#999;
  margin-top:16px;
}

.nav-links{
  display:flex;
  justify-content:center;
  gap:12px;
  margin:18px auto 0;
  max-width:900px;
}

.records-link{
  background:var(--primary);
  color:#fff;
  padding:11px 18px;
  border-radius:12px;
  font-size:.9rem;
  font-weight:700;
  text-decoration:none;
  flex:1;
  text-align:center;
}

.records-link:hover{
  background:#b31eae;
}

</style>

</head>
<body>

<header>
  <img src="florapulse.png" alt="Flora Pulse" class="logo">
  <p>Your Plant's Best Friend - Live Dashboard</p>
</header>

<div class="grid">

  <!-- CARD 1: Water Tank Level -->
  <div class="card" style="display:flex;flex-direction:column;align-items:center;text-align:center;">
    <div class="card-label" style="align-self:flex-start;">Water Tank Level</div>

    <!-- Wave SVG -->
    <div style="position:relative;width:120px;height:170px;border-radius:12px;overflow:hidden;border:1.5px solid #2d3748;background:#1c2330;margin:8px 0;">
      <svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;bottom:0;left:0;width:100%;height:100%;" viewBox="0 0 120 170" preserveAspectRatio="none">
        <path id="w1" fill="<?php echo $statusColor; ?>" opacity="0.85"/>
        <path id="w2" fill="<?php echo $statusColor; ?>" opacity="0.4"/>
      </svg>
      <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;pointer-events:none;">
        <span style="font-size:2rem;font-weight:800;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.4);line-height:1;"><?php echo $waterPercent; ?>%</span>
        <span style="font-family:DM Mono,monospace;font-size:.7rem;color:rgba(255,255,255,.8);text-transform:uppercase;letter-spacing:.05em;text-shadow:0 1px 3px rgba(0,0,0,.3);"><?php echo $waterStatus; ?></span>
      </div>
    </div>

    <span style="font-family:DM Mono,monospace;font-size:.7rem;color:#8b949e;margin-top:4px;">Water Tank Level: <?php echo $waterPercent; ?>%</span>
  </div>

  <!-- CARD 2: Soil Moisture -->
  <div class="card" style="display:flex;flex-direction:column;align-items:center;">
    <div class="card-label" style="align-self:flex-start;">Soil Moisture</div>

    <!-- Donut SVG -->
    <svg width="110" height="110" viewBox="0 0 130 130">
      <circle cx="65" cy="65" r="54" fill="none" stroke="#eee" stroke-width="13"/>
      <circle cx="65" cy="65" r="54" fill="none" stroke="<?php echo $soilColor; ?>" stroke-width="13"
        stroke-dasharray="<?php echo $dashArr; ?>" stroke-linecap="round" transform="rotate(-90 65 65)"/>
      <text x="65" y="70" text-anchor="middle" font-family="-apple-system,sans-serif" font-weight="800" font-size="24" fill="#222"><?php echo $soilPercent; ?>%</text>
    </svg>

    <span class="badge" style="background:<?php echo $soilColor; ?>;margin-top:4px;"><?php echo $soilStatus; ?></span>
    <p class="action" style="color:<?php echo $soilColor; ?>;text-align:center;"><?php echo $systemAction; ?></p>
    
    <?php 
    $canPump = ($soilStatus == "DRY") ? "" : " disabled";
    ?>
    <button class="pump-btn"<?php echo $canPump; ?> onmousedown="startPump()" onmouseup="stopPump()" ontouchstart="startPump()" ontouchend="stopPump()">
      HOLD TO PUMP WATER
    </button>
  </div>

  <!-- CARD 3: Temperature & Humidity -->
  <div class="card">

    <!-- Temperature -->
    <div class="card-label">Temperature</div>
    <div class="big"><?php echo round($temperature, 1); ?><span style="font-size:1rem;font-weight:600;color:#666;"> °C</span></div>
    <div class="status-line" style="color:<?php echo $tempColor; ?>;">STATUS: <?php echo $tempStatus; ?></div>
    <div class="desc"><?php echo $tempDesc; ?></div>

    <hr style="border:none;border-top:1px solid #f0f0f0;margin:14px 0;"/>

    <!-- Humidity -->
    <div class="card-label">Humidity</div>
    <div class="big"><?php echo round($humidity, 1); ?><span style="font-size:1rem;font-weight:600;color:#666;"> %</span></div>
    <div class="status-line" style="color:<?php echo $humColor; ?>"><?php echo $humidityPrefix; ?>: <?php echo $humidityStatus; ?></div>
    <div class="desc"><?php echo $humidityDesc; ?></div>

  </div>

</div>

<div class="last-updated">
  Last updated: <?php echo date('M d, Y @ h:i A', strtotime($lastRecorded)); ?>
</div>

<div class="nav-links">
  <a class="records-link" href="dashboard.php"> View Charts & History</a>
</div>

<script>
// Water wave animation
var _wp=0, _wp2=Math.PI, _wlv=<?php echo $waterPercent; ?>, _wcur=<?php echo $waterPercent; ?>;

function _wwave(amp, freq, ph, yb, W, H) {
  var p = [];
  for(var x=0; x<=W; x+=3) {
    p.push(x + ',' + (yb + Math.sin((x/W)*freq*Math.PI*2 + ph)*amp).toFixed(1));
  }
  return 'M0,'+H+' L0,'+p[0].split(',')[1]+' '+p.map(function(v){return 'L'+v;}).join(' ')+' L'+W+','+H+' Z';
}

function _wanim() {
  _wp+=.04;
  _wp2+=.025;
  _wcur+=(_wlv-_wcur)*.06;
  var yb=170-((_wcur/100)*170), amp=5+(_wcur/100)*5;
  var e1=document.getElementById('w1'), e2=document.getElementById('w2');
  if(e1) {
    e1.setAttribute('d', _wwave(amp, 2, _wp, yb, 120, 170));
    e2.setAttribute('d', _wwave(amp*.7, 3, _wp2, yb+3, 120, 170));
  }
  requestAnimationFrame(_wanim);
}

_wanim();

// Auto-refresh page every 5 seconds
setTimeout(function(){location.reload();}, 5000);

// Pump control - sends request to ESP32
function startPump() {
  fetch('http://192.168.0.33/pump_on').catch(err => console.log('Pump request sent'));
}

function stopPump() {
  fetch('http://192.168.0.33/pump_off').catch(err => console.log('Pump request sent'));
}

</script>

<?php include 'user_menu.php'; ?>


</body>
</html>
