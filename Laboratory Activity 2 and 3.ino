#include "DHT.h"

#define DHTPIN 21       
#define DHTTYPE DHT11   

#define GREEN_LED 18  
#define YELLOW_LED 19  
#define BLUE_LED 5     

DHT dht(DHTPIN, DHTTYPE);

void setup() {
  Serial.begin(115200);
  while (!Serial); 
  dht.begin();

  pinMode(GREEN_LED, OUTPUT);
  pinMode(YELLOW_LED, OUTPUT);
  pinMode(BLUE_LED, OUTPUT);

  // Ensure everything starts OFF
  digitalWrite(GREEN_LED, LOW);
  digitalWrite(YELLOW_LED, LOW);
  digitalWrite(BLUE_LED, LOW);
}

void loop() {
  if (Serial.available() > 0) {
    char command = Serial.read();
    
    // Clear the buffer of extra characters (\n, \r)
    while(Serial.available() > 0) { Serial.read(); }

    // Logic Check: Is it a valid command?
    if (command == 'T' || command == 't' || 
        command == 'H' || command == 'h' || 
        command == 'A' || command == 'a') {

      // Reset LEDs before showing new state
      digitalWrite(GREEN_LED, LOW);
      digitalWrite(YELLOW_LED, LOW);
      digitalWrite(BLUE_LED, LOW);

      float h = dht.readHumidity();
      float t = dht.readTemperature();

      if (command == 'T' || command == 't') {
        Serial.print("Temperature = ");
        if (isnan(t)) Serial.println("Sensor Error!");
        else { Serial.print(t); Serial.println(" C"); }
        digitalWrite(GREEN_LED, HIGH);
      } 
      else if (command == 'H' || command == 'h') {
        Serial.print("Humidity = ");
        if (isnan(h)) Serial.println("Sensor Error!");
        else { Serial.print(h); Serial.println(" %"); }
        digitalWrite(YELLOW_LED, HIGH);
      } 
      else if (command == 'A' || command == 'a') {
        Serial.print("All: ");
        if (isnan(t) || isnan(h)) Serial.println("Sensor Error!");
        else { 
          Serial.print("Temperature = "); Serial.print(t); 
          Serial.print("C | Humidity = "); Serial.print(h); Serial.println("%"); 
        }
        digitalWrite(BLUE_LED, HIGH);
      }
    } 
    // Logic: If the letter is NOT T, H, or A
    else if (command != '\n' && command != '\r') {
      // 1. Turn OFF all LEDs
      digitalWrite(GREEN_LED, LOW);
      digitalWrite(YELLOW_LED, LOW);
      digitalWrite(BLUE_LED, LOW);
      Serial.println("'Invalid Input. Valid Commands: [T] Temp, [H] Humidity, [A] All");
    }
  }
}
