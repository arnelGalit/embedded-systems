#include "BluetoothSerial.h"
#include "DHT.h"

#define DHTPIN 32     // DHT11 DATA pin connected to GPIO32
#define DHTTYPE DHT11
#define LED 4         // LED connected to GPIO4

BluetoothSerial SerialBT;
DHT dht(DHTPIN, DHTTYPE);

void setup() {
  Serial.begin(115200);
  SerialBT.begin("ESP32_BT_Monitor");

  pinMode(LED, OUTPUT);
  dht.begin();

  Serial.println("Bluetooth device started. Pair with ESP32_BT_Monitor");
}

void loop() {

  if (SerialBT.available()) {
    char command = SerialBT.read();

    if (command == '1') {
      digitalWrite(LED, HIGH);
      SerialBT.println("LED ON");
    }

    if (command == '0') {
      digitalWrite(LED, LOW);
      SerialBT.println("LED OFF");
    }

    if (command == 'T') {
      float temp = dht.readTemperature();
      float hum = dht.readHumidity();

      SerialBT.print("Temperature: ");
      SerialBT.print(temp);
      SerialBT.println(" C");

      SerialBT.print("Humidity: ");
      SerialBT.print(hum);
      SerialBT.println(" %");
    }
  }

  delay(1000);
}