// PLANT MONITORING SYSTEM - WITH DYNAMIC WiFi SETTINGS
// ESP32 fetches WiFi & server settings from database API on startup

#include <WiFi.h>
#include <Wire.h>
#include <DHT.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// Placeholder values (will be replaced by API settings)
// IMPORTANT: set TEMP_SSID/TEMP_PASS to a network the ESP32 can join to fetch settings,
// or run this while your dev machine is hosting an access point named accordingly.
const char* CONFIG_URL = "http://192.168.0.33/plant_monitoring/api_settings.php?format=json";
const char* TEMP_SSID = "ssid";      // change to a reachable temporary SSID for provisioning
const char* TEMP_PASS = "password";  // change to the temp SSID password

// Variables that will be populated from API
char ssid[64] = "";
char password[64] = "";
String HOST_NAME = "";
String PATH_NAME = "";
unsigned long dbInterval = 30000;

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

unsigned long lastDBInsertTime = 0;

WiFiServer server(80);

// ---------------------------------------------------------------
// FETCH SETTINGS FROM DATABASE API
// ---------------------------------------------------------------
bool fetchSettingsFromAPI() {
  Serial.println("Attempting to fetch settings from API...");
  
  HTTPClient http;
  http.begin(CONFIG_URL);
  int code = http.GET();
  
  if (code != HTTP_CODE_OK) {
    Serial.printf("Failed to fetch settings. HTTP %d\n", code);
    http.end();
    return false;
  }
  
  String payload = http.getString();
  http.end();
  
  // Parse JSON
  StaticJsonDocument<512> doc;
  DeserializationError error = deserializeJson(doc, payload);
  
  if (error) {
    Serial.print("JSON parsing failed: ");
    Serial.println(error.c_str());
    return false;
  }
  
  // Extract settings
  if (doc.containsKey("wifi_ssid")) {
    strlcpy(ssid, doc["wifi_ssid"] | "ssid", sizeof(ssid));
  }
  if (doc.containsKey("wifi_password")) {
    strlcpy(password, doc["wifi_password"] | "password", sizeof(password));
  }
  if (doc.containsKey("server_url")) {
    HOST_NAME = doc["server_url"].as<String>();
  }
  if (doc.containsKey("api_path")) {
    PATH_NAME = doc["api_path"].as<String>();
  }
  if (doc.containsKey("db_interval")) {
    dbInterval = doc["db_interval"].as<unsigned long>();
  }
  
  Serial.println("Settings fetched successfully!");
  Serial.print("SSID: "); Serial.println(ssid);
  Serial.print("Server: "); Serial.println(HOST_NAME);
  Serial.print("Path: "); Serial.println(PATH_NAME);
  Serial.print("Interval: "); Serial.println(dbInterval);
  
  return true;
}

// ---------------------------------------------------------------
void setup() {
  Serial.begin(115200);
  delay(1000);

  pinMode(waterLevelSensorPin, INPUT);
  pinMode(soilMoisturePin,     INPUT);
  pinMode(buzzerPin,           OUTPUT);
  pinMode(ledDry,              OUTPUT);
  pinMode(ledMoist,            OUTPUT);
  pinMode(ledWet,              OUTPUT);

  dht.begin();

  pinMode(pumpPin, OUTPUT);
  digitalWrite(pumpPin, HIGH);

  Serial.println("\n\nPlant Monitoring System Starting...");
  Serial.println("Connecting to temporary WiFi to fetch settings...");
  
  // First, connect to a temporary WiFi to fetch settings from API
  // You can hardcode a temporary network here for configuration
  // Or use WiFi Manager library for easier setup
  
  WiFi.mode(WIFI_STA);
  WiFi.begin(TEMP_SSID, TEMP_PASS); // Temporary fallback - set these constants above

  int attempts = 0;
  const int maxAttempts = 40; // allow longer time to join temporary network
  while (WiFi.status() != WL_CONNECTED && attempts < maxAttempts) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nTemporary WiFi connected. Fetching settings...");
    
    if (fetchSettingsFromAPI()) {
      WiFi.disconnect();
      delay(1000);
      
      // Now connect with real settings from database
      Serial.println("Connecting to configured WiFi...");
      WiFi.begin(ssid, password);
      
      int attempts = 0;
      const int maxAttempts2 = 40;
      while (WiFi.status() != WL_CONNECTED && attempts < maxAttempts2) {
        delay(500);
        Serial.print(".");
        attempts++;
      }
      
      if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\nWiFi Connected!");
        Serial.print("IP: ");
        Serial.println(WiFi.localIP().toString());
      } else {
        Serial.println("\nFailed to connect with configured WiFi. Using temporary settings.");
      }
    } else {
      Serial.println("Failed to fetch settings. Using default values.");
      // Use defaults or try again later
    }
  } else {
    Serial.println("\nFailed to connect to temporary WiFi. Using default settings.");
  }

  server.begin();
  Serial.println("Web server started on port 80");
}

// ---------------------------------------------------------------
void sendToDatabase(int soilPercent, float temp, float hum) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi not connected. Skipping database insert.");
    return;
  }

  HTTPClient http;
  // Normalize host/path: ensure no duplicate slashes
  String host = HOST_NAME;
  String path = PATH_NAME;
  if (host.endsWith("/")) host.remove(host.length()-1);
  if (!path.startsWith("/")) path = "/" + path;

  String url = host + path
             + "?soil_moisture=" + String(soilPercent)
             + "&temperature="   + String(temp, 1)
             + "&humidity="      + String(hum, 1);
  Serial.print("Sending to DB URL: "); Serial.println(url);
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
