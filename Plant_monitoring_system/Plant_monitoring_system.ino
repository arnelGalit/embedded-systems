// PLANT MONITORING SYSTEM - SIMPLIFIED VERSION
// ESP32 sends sensor data to database
// Web dashboard reads from database via live_dashboard.php

#include <WiFi.h>
#include <Wire.h>
#include <DHT.h>
#include <LiquidCrystal_I2C.h>
#include <HTTPClient.h>

// WiFi Connection - Change this if necessary
const char* ssid     = "SKYbroadband4F00"; 
const char* password = "180225597";

// Components
const int waterLevelSensorPin = 34;
const int soilMoisturePin     = 35;
const int buzzerPin           = 25;
const int ledDry              = 26;
const int ledMoist            = 27;
const int ledWet              = 14;

// Mini Water Pump
const int pumpPin  = 23;
bool      pumpState = false;

#define DHTPIN  4
#define DHTTYPE DHT11
DHT dht(DHTPIN, DHTTYPE);

unsigned long lastDHTReadTime = 0;
const unsigned long dhtInterval = 2000;

float temperature = 0.0;
float humidity    = 0.0;

// Soil Moisture Calibration
int dryValue = 1900;
int wetValue = 700;

// DB Config
String HOST_NAME = "http://192.168.0.33"; // Change this if necessary (ipconfig)
String PATH_NAME = "/plant_monitoring/insert_sensor.php"; // Change this if necessary 

unsigned long lastDBInsertTime = 0;
const unsigned long dbInterval = 30000; // Send record to DB every 30 seconds - Change this if necessary

WiFiServer server(80);

// ---------------------------------------------------------------
void setup() {
  Serial.begin(115200);

  pinMode(waterLevelSensorPin, INPUT);
  pinMode(soilMoisturePin,     INPUT);
  pinMode(buzzerPin,           OUTPUT);
  pinMode(ledDry,              OUTPUT);
  pinMode(ledMoist,            OUTPUT);
  pinMode(ledWet,              OUTPUT);

  dht.begin();

  pinMode(pumpPin, OUTPUT);
  digitalWrite(pumpPin, HIGH);

  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) { delay(500); }

  Serial.println("WiFi Connected! IP: " + WiFi.localIP().toString());

  server.begin();
}

// ---------------------------------------------------------------
void sendToDatabase(int soilPercent, float temp, float hum) {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  String url = HOST_NAME + PATH_NAME
             + "?soil_moisture=" + String(soilPercent)
             + "&temperature="   + String(temp, 1)
             + "&humidity="      + String(hum, 1);

  http.begin(url);
  int code = http.GET();
  if (code == HTTP_CODE_OK) {
    Serial.println("DB OK: " + http.getString());
  } else {
    Serial.printf("DB failed. HTTP %d\n", code);
  }
  http.end();
}

// ---------------------------------------------------------------
void loop() {
  int sensorValue = analogRead(waterLevelSensorPin);
  int soilValue   = analogRead(soilMoisturePin);

  unsigned long now = millis();

  // Soil %
  int soilPercent = constrain(map(soilValue, dryValue, wetValue, 0, 100), 0, 100);

  // DB insert every dbInterval
  if (now - lastDBInsertTime >= dbInterval) {
    lastDBInsertTime = now;
    sendToDatabase(soilPercent, temperature, humidity);
  }

  // ---- THRESHOLD ------------------------------------------------

  // Soil moisture threshold and description
  String soilStatus, systemAction;
  if      (soilPercent < 25)  { soilStatus = "DRY";   systemAction = "The soil is dry. Watering is recommended."; }
  else if (soilPercent <= 70) { soilStatus = "MOIST"; systemAction = "Soil has adequate moisture for healthy plant growth."; }
  else                        { soilStatus = "WET";   systemAction = "Too much water. Stop watering!"; }

  // Temperature threshold and description
  String tempStatus, tempDesc;
  if      (temperature > 35)                            { tempStatus = "Extreme Heat";     tempDesc = "High evaporation rate; severe wilting and heat stress risk"; }
  else if (temperature >= 28 && temperature <= 35)      { tempStatus = "Warm";             tempDesc = "Plants consume water faster; monitor soil moisture closely"; }
  else if (temperature >= 18 && temperature <= 27)      { tempStatus = "Optimal";          tempDesc = "Ideal growth range for most tropical and indoor plants"; }
  else                                                  { tempStatus = "Cold Environment"; tempDesc = "Plant metabolism slows; growth becomes slower"; }

  // Humidity threshold and description
  String humidityStatus, humidityDesc, humidityPrefix;
  if      (humidity > 80)                         { humidityPrefix = "STATUS"; humidityStatus = "Excessively Humid"; humidityDesc = "High risk of leaf mold, fungus, and stagnant air"; }
  else if (humidity >= 50 && humidity <= 80)      { humidityPrefix = "STATUS"; humidityStatus = "Optimal Humidity";  humidityDesc = "Perfect transpiration zone for healthy leaf respiration"; }
  else if (humidity >= 30 && humidity <= 49)      { humidityPrefix = "STATUS"; humidityStatus = "Dry Air";           humidityDesc = "Harmless for short periods, but watch for crisp leaf edges"; }
  else                                            { humidityPrefix = "ALERT";  humidityStatus = "Extremely Dry";     humidityDesc = "Air is too dry; plant will lose moisture rapidly"; }

  // Update DHT readings
  if (now - lastDHTReadTime >= dhtInterval) {
    lastDHTReadTime = now;
    float t = dht.readTemperature();
    float h = dht.readHumidity();
    if (!isnan(t) && !isnan(h)) { temperature = t; humidity = h; }

    Serial.println("========== SENSOR READINGS ==========");
    Serial.print("Soil Moisture : "); Serial.print(soilPercent); Serial.print("% ("); Serial.print(soilStatus); Serial.println(")");
    Serial.print("Temperature   : "); Serial.print(temperature, 1); Serial.println(" C"); 
    Serial.print("Humidity      : "); Serial.print(humidity, 1); Serial.println("%"); 
    Serial.println("=====================================");
  }

  // ---- LEDs (Status Indicators) ----
  digitalWrite(ledDry,   soilStatus == "DRY"   ? HIGH : LOW);
  digitalWrite(ledMoist, soilStatus == "MOIST" ? HIGH : LOW);
  digitalWrite(ledWet,   soilStatus == "WET"   ? HIGH : LOW);

  // ---- Buzzer (Alert when soil is dry) ----
  if (soilStatus == "DRY") {
    for (int i = 0; i < 3; i++) {
      digitalWrite(buzzerPin, HIGH); delay(80);
      digitalWrite(buzzerPin, LOW);  delay(80);
    }
    delay(400);
  } else {
    digitalWrite(buzzerPin, LOW);
  }

  // ---- Web Server (Pump Control Only) ----
  WiFiClient client = server.available();
  if (!client) { delay(10); return; }

  String request = client.readStringUntil('\r');
  client.flush();

  // Pump control - can be called from web interface
  if      (request.indexOf("GET /pump_on")  != -1) { if (soilStatus == "DRY") pumpState = true; }
  else if (request.indexOf("GET /pump_off") != -1) { pumpState = false; }
  digitalWrite(pumpPin, pumpState ? LOW : HIGH);

  // Respond to pump requests
  if (request.indexOf("GET /pump_") != -1) {
    client.println("HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n");
    Serial.println(pumpState ? "PUMPING WATER..." : "PUMP OFF");
    client.stop();
    return;
  }

  // Respond with JSON status (optional - for API calls)
  if (request.indexOf("GET /status") != -1) {
    String json = "{\"soil\":" + String(soilPercent) + ",\"temp\":" + String(temperature, 1) + ",\"humidity\":" + String(humidity, 1) + "}";
    client.println("HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n");
    client.print(json);
    client.stop();
    return;
  }

  // Default response
  client.println("HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n");
  client.println("Plant Monitor Active - Visit http://192.168.0.33/plant_monitoring/live_dashboard.php for dashboard");
  client.stop();
  delay(10);
}
