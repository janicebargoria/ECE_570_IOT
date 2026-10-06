#include <Arduino.h>

// Built-in LED on ESP8266
#define LED_PIN LED_BUILTIN

void setup() {
  // Configure LED as output
  pinMode(LED_PIN, OUTPUT);

  // Start Serial Monitor
  Serial.begin(115200);

  Serial.println("ESP8266 LED Blink Program Started");
}

void loop() {

  // Turn LED ON
  // ESP8266 built-in LED is active LOW
  digitalWrite(LED_PIN, LOW);

  Serial.println("LED is ON");

  delay(1000);   // Wait 1 second


  // Turn LED OFF
  digitalWrite(LED_PIN, HIGH);

  Serial.println("LED is OFF");

  delay(1000);   // Wait 1 second
}