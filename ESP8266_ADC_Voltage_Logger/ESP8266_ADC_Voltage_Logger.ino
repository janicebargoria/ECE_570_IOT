const int ADC_PIN = A0;

void setup() {
  Serial.begin(115200);

  // Excel column headings
  Serial.println("Time_s,ADC_Value,Voltage_V");
}

void loop() {

  float timestamp = millis() / 1000.0;

  int adcValue = analogRead(ADC_PIN);

  float voltage = adcValue * 2 * (3.3 / 1023.0);

  Serial.print(timestamp, 2);
  Serial.print(",");

  Serial.println(voltage, 3);

  delay(30000);
}