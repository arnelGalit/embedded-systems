#include "BluetoothSerial.h"
#include "DHT.h"
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

#define DHTPIN 32
#define DHTTYPE DHT11
#define LED 4
#define BUZZER 16
#define WATER_SENSOR 34 
#define LDR_PIN 33 
#define LED_LDR 17 // Dedicated LED for LDR alarm

#define WATER_THRESHOLD 500 
#define LDR_THRESHOLD 200 

LiquidCrystal_I2C lcd(0x27, 16, 2); 
BluetoothSerial SerialBT;
DHT dht(DHTPIN, DHTTYPE);

unsigned long previousMillis = 0;
const long interval = 2000; 

void setup() {
  Serial.begin(115200);
  SerialBT.begin("ESP32_BT_Monitor_4032");
  
  pinMode(LED, OUTPUT);
  pinMode(BUZZER, OUTPUT);
  pinMode(LED_LDR, OUTPUT); // Initialize the new LDR LED
  pinMode(LDR_PIN, INPUT); 
  
  dht.begin();
  
  Wire.begin(21, 22); 
  lcd.init();
  lcd.backlight();
  
  lcd.setCursor(0, 0);
  lcd.print("ESP32 System");
  lcd.setCursor(0, 1);
  lcd.print("Dual LED Mode");
  delay(1000);
}

void loop() {
  unsigned long currentMillis = millis();
  
  int waterValue = analogRead(WATER_SENSOR);
  int ldrValue = analogRead(LDR_PIN); 
  
  int lightIntensity = map(ldrValue, 0, 4095, 0, 100);

  float t = dht.readTemperature();
  float h = dht.readHumidity();

  bool isLeaking = (waterValue > WATER_THRESHOLD);
  bool isHotAndHumid = (t >= 30.0 && h >= 50.0);
  bool isDark = (ldrValue < LDR_THRESHOLD); 

  // --- UPDATED ALARM LOGIC ---
  
  // 1. Water or Heat/Humidity trigger the main LED and Buzzer
  if (isHotAndHumid || isLeaking) {
    digitalWrite(LED, HIGH);
    digitalWrite(BUZZER, HIGH); 
  } else {
    digitalWrite(LED, LOW);
    digitalWrite(BUZZER, LOW); 
  }

  // 2. Darkness ONLY triggers the LED_LDR
  if (isDark) {
    digitalWrite(LED_LDR, HIGH);
  } else {
    digitalWrite(LED_LDR, LOW);
  }

  if (currentMillis - previousMillis >= interval) {
    previousMillis = currentMillis;
    updateDisplay(t, h, waterValue, ldrValue, lightIntensity, isLeaking, isHotAndHumid, isDark);
  }
}

void updateDisplay(float t, float h, int wVal, int lVal, int intensity, bool leak, bool hot, bool dark) {
  lcd.clear();
  
  lcd.setCursor(0, 0);
  lcd.print("T:"); lcd.print((int)t); lcd.print("C ");
  lcd.print("LIT:"); lcd.print(intensity); lcd.print("%");
  
  lcd.setCursor(0, 1);
  if (leak) {
    lcd.print("!!! WATER !!!");
  } else if (hot) {
    lcd.print("ALRT: HOT/HUMID");
  } else if (dark) {
    lcd.print("ALRT: TOO DARK");
  } else {
    lcd.print("H:"); lcd.print((int)h); lcd.print("%");
    lcd.setCursor(8, 1);
    lcd.print("RawL:"); lcd.print(lVal);
  }

  SerialBT.printf("T:%.1f H:%.1f W:%d L:%d Int:%d%%\n", t, h, wVal, lVal, intensity);
}
