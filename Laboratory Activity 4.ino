#include "DHT.h"

// Hardware Configuration
#define DHTPIN 21
#define DHTTYPE DHT11
#define GREEN_LED 18
#define YELLOW_LED 19
#define BLUE_LED 5
#define BUZZER_PIN 17 // Added for audible alert

DHT dht(DHTPIN, DHTTYPE);

// Default Thresholds (Adjustable during runtime)
float tempThreshold = 32.0; 
float humidThreshold = 63.5;

void setup() {
  Serial.begin(115200);
  dht.begin();
  
  pinMode(GREEN_LED, OUTPUT);
  pinMode(YELLOW_LED, OUTPUT);
  pinMode(BLUE_LED, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  
  Serial.println("--- QC Cooperative Server Room Monitor ---");
  Serial.println("Commands: T[value] (Set Temp), H[value] (Set Humidity)");
  Serial.println("Example: T35.5 sets temperature limit to 35.5°C");
  Serial.println("-------------------------------------------");
}

void loop() {
  // 1. Monitor Temperature and Humidity
  float h = dht.readHumidity();
  float t = dht.readTemperature();

  if (isnan(h) || isnan(t)) {
    Serial.println("Sensor Error!");
    return;
  }

  // 2. Handle Runtime Threshold Updates via Serial
  if (Serial.available() > 0) {
    char type = Serial.read();
    float val = Serial.parseFloat();
    
    if (type == 'T' || type == 't') {
      tempThreshold = val;
      Serial.print("New Temp Threshold: "); Serial.println(tempThreshold);
    } else if (type == 'H' || type == 'h') {
      humidThreshold = val;
      Serial.print("New Humidity Threshold: "); Serial.println(humidThreshold);
    }
  }

  // 3. Logic and Automatic Actions
  bool tempAlert = (t > tempThreshold);
  bool humidAlert = (h > humidThreshold);

  // Reset LEDs
  digitalWrite(GREEN_LED, LOW);
  digitalWrite(YELLOW_LED, LOW);
  digitalWrite(BLUE_LED, LOW);
  noTone(BUZZER_PIN);

  if (tempAlert) {
    // CRITICAL: High Temp
    digitalWrite(BLUE_LED, HIGH);
    tone(BUZZER_PIN, 1000); // Audible alert
    Serial.println("ALERT: Temperature Limit Exceeded!");
  } 
  
  if (humidAlert) {
    // WARNING: High Humidity
    digitalWrite(YELLOW_LED, HIGH);
    if(!tempAlert) tone(BUZZER_PIN, 500); // Lower tone for humidity
    Serial.println("WARNING: Humidity Limit Exceeded!");
  }

  if (!tempAlert && !humidAlert) {
    // SAFE
    digitalWrite(GREEN_LED, HIGH);
  }

  // Data Logging to Serial
  Serial.print("Temp: "); Serial.print(t); Serial.print("C | ");
  Serial.print("Hum: "); Serial.print(h); Serial.println("%");

  delay(2000); // DHT11 needs time between reads
}
