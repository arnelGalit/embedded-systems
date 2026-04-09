// LAB 4 - ENHANCEMENTS W/ SOIL MOISTURE SENSOR
#include <WiFi.h>
#include <Wire.h>
#include <DHT.h>
#include <LiquidCrystal_I2C.h>

LiquidCrystal_I2C lcd(0x27, 16, 2);

// WiFi Connection
const char* ssid = "ssid";     
const char* password = "password";  

// Components
const int waterLevelSensorPin = 34;
const int soilMoisturePin = 35;   
const int buzzerPin = 25;
const int ledDry = 26;
const int ledMoist = 27;
const int ledWet = 14;
#define DHTPIN 4        
#define DHTTYPE DHT11   

DHT dht(DHTPIN, DHTTYPE);

// Water level thresholds
//const int veryLowThreshold = 600;
//const int lowThreshold = 900;
//const int normalThreshold = 1200;
//const int highThreshold = 1500;

//  SOIL MOISTURE CALIBRATION
int dryValue = 1900;   
int wetValue = 700;    

WiFiServer server(80);

void setup() {
  Serial.begin(115200);

  pinMode(waterLevelSensorPin, INPUT);
  pinMode(soilMoisturePin, INPUT);
  pinMode(buzzerPin, OUTPUT);

  pinMode(ledDry, OUTPUT);
  pinMode(ledMoist, OUTPUT);
  pinMode(ledWet, OUTPUT);

  //DHT sensor
  dht.begin();

  // LCD
  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();

  lcd.setCursor(0,0);
  lcd.print("Connecting WiFi...");

  // WiFi
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
  }

  lcd.clear();
  lcd.setCursor(0,0);
  lcd.print("WiFi Connected");

  Serial.println("WiFi Connected!");
  Serial.println(WiFi.localIP());

  server.begin();
}

void loop() {
  
  // Water sensor
  int sensorValue = analogRead(waterLevelSensorPin);

  // Soil sensor raw value
  int soilValue = analogRead(soilMoisturePin);

  //DHT Sensor
  float temperature = dht.readTemperature();
  float humidity = dht.readHumidity();

  // Convert to percentage
  int soilPercent = map(soilValue, dryValue, wetValue, 0, 100);
  soilPercent = constrain(soilPercent, 0, 100);

  String status;
  String soilStatus;
  String systemAction;

  // DETERMINE SOIL STATUS FIRST 
  if (soilPercent < 25) {
  soilStatus = "DRY";
} else if (soilPercent <= 70) {
  soilStatus = "MOIST";
} else {
  soilStatus = "WET";
}

// --- DETERMINE WATER LEVEL STATUS ---
 if(sensorValue <= 100 ){
    status = "Empty!";
} else if(sensorValue <= 800){
    status = "Low water level";
} else if(sensorValue <= 1900){
    status = "Half tank";
} else {
    status = "Full tank";
}

  // --- RESET LEDS ---
  digitalWrite(ledDry, LOW);
  digitalWrite(ledMoist, LOW);
  digitalWrite(ledWet, LOW);

  // --- LED ACCORDING TO SOIL STATUS ---
  if (soilStatus == "DRY") {
    digitalWrite(ledDry, HIGH);
  } else if (soilStatus == "MOIST") {
    digitalWrite(ledMoist, HIGH);
  } else if (soilStatus == "WET") {
    digitalWrite(ledWet, HIGH);
  }

  // --- BUZZER: TRIGGER WHEN SOIL IS DRY ---
  if (soilStatus == "DRY" || status == "Empty!" || status == "Low water level") {
    for (int i = 0; i < 3; i++) {
      digitalWrite(buzzerPin, HIGH);
      delay(80);
      digitalWrite(buzzerPin, LOW);
      delay(80);
    }
    delay(400);
  } else {
    digitalWrite(buzzerPin, LOW);
  }

  // --- SYSTEM ACTION ---
  if (soilStatus == "DRY" && (status == "Empty!" || status == "Low water level")) {
    systemAction = "Soil is dry AND water tank is empty/low!";
} else if (soilStatus == "DRY") {
    systemAction = "Soil is dry. Needs watering.";
} else if (soilStatus == "WET") {
    systemAction = "STOP WATERING";
} else {
    systemAction = "NORMAL";
}

  // --- LCD DISPLAY ---
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("W:"); 
  lcd.print(sensorValue);
  lcd.print("-"); 
  lcd.print(status);

  lcd.setCursor(0, 1);
  lcd.print("S:"); 
  lcd.print(soilPercent); 
  lcd.print("%-"); 
  lcd.print(soilStatus);

  // --- SERIAL OUTPUT ---
  Serial.print("Water: "); Serial.print(sensorValue);
  Serial.print(" ("); Serial.print(status); Serial.print(") | Soil: ");
  Serial.print(soilPercent); Serial.print("% ("); Serial.print(soilStatus); 
  Serial.print(") | Soil Status: "); Serial.println(systemAction);

  // --- WEB SERVER ---
  WiFiClient client = server.available();
  if (client) {
    client.readStringUntil('\r');
    client.flush();

  String html = "<!DOCTYPE html><html><head>";
  html += "<meta http-equiv='refresh' content='2'>";
  html += "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
  html += "<title>Plant and Environment Monitoring</title>";

  // CSS styling
  html += "<style>";
  html += "body { font-family: Arial, sans-serif; background-color: #f0f8ff; margin:0; padding:20px; display:flex; flex-direction: column; align-items:center; }";
  html += "h1 { color: #2e8b57; text-align: center; margin-bottom: 20px; }";
  html += ".dashboard { display: flex; flex-wrap: wrap; justify-content: center; gap: 15px; width: 100%; max-width: 900px; margin-bottom: 20px; }";
  html += ".card { background-color: #ffffff; padding: 20px; border-radius: 10px; min-width: 150px; text-align: center; box-shadow: 0 4px 8px rgba(0,0,0,0.2); flex: 1; }";
  html += ".card h2 { margin: 0 0 10px 0; font-size: 18px; color: #333; }";
  html += ".card p { font-size: 16px; margin: 5px 0; }";
  html += ".status { font-weight: bold; padding: 5px 10px; border-radius: 5px; color: white; }";
  html += ".dry { background-color: #ff4500; }";
  html += ".humid { background-color: #1e90ff; }";
  html += ".normal { background-color: #32cd32; }";
  html += "</style>";
  html += "</head><body>";

  html += "<h1>Plant and Environment Monitoring</h1>";

  // Top row: Water Level, Soil Moisture, System Action
  html += "<div class='dashboard'>";

  // Water Level Card
  html += "<div class='card'>";
  html += "<h2>Water Level</h2>";
  html += "<p>" + String(sensorValue) + " ";
  if(status == "Low / Almost Empty"){
      html += "<span class='status dry'>" + status + "</span>";
  } else if(status == "Half") {
      html += "<span class='status humid'>" + status + "</span>";
  } else {
      html += "<span class='status normal'>" + status + "</span>";
  }
  html += "</p></div>";

  // Soil Moisture Card
  html += "<div class='card'>";
  html += "<h2>Soil Moisture</h2>";
  html += "<p>" + String(soilPercent) + "% ";
  if(soilStatus == "DRY"){
      html += "<span class='status dry'>" + soilStatus + "</span>";
  } else if(soilStatus == "HUMID"){
      html += "<span class='status humid'>" + soilStatus + "</span>";
  } else {
      html += "<span class='status normal'>" + soilStatus + "</span>";
  }
  html += "</p></div>";

  // System Action Card 
  html += "<div class='card'>";
  html += "<h2>Soil Status/Warning</h2>";
  html += "<p style='color: red; font-weight: bold;'>" + systemAction + "</p>";
  html += "</div>";

  html += "</div>"; // end top row

  // Bottom row: Temperature and Humidity
  html += "<div class='dashboard'>";

  // Temperature Card
  html += "<div class='card'>";
  html += "<h2>Temperature</h2>";
  html += "<p>" + String(temperature) + " C</p>";
  html += "</div>";

  // Humidity Card
  html += "<div class='card'>";
  html += "<h2>Humidity</h2>";
  html += "<p>" + String(humidity) + " %</p>";
  html += "</div>";

  html += "</div>"; // end bottom row

  html += "</body></html>";

  // Send HTML
  client.println("HTTP/1.1 200 OK");
  client.println("Content-Type: text/html");
  client.println();
  client.print(html);
  client.stop();
      }

  delay(1000);
}
