
/*
 * -----------------------------------------------------
 * Project: ECE 570 - MQTT Assignment
 * File: main.cpp
 * Board: NodeMCU ESP8266
 * Framework: Arduino / PlatformIO
 *
 * Sections A, B, C
 *
 * Inputs:
 * - Potentiometer (A0)
 * - Push button (D5)
 * - MQTT LED commands
 * - Serial Monitor commands
 *
 * Outputs:
 * - LED (D2)
 * - Potentiometer readings via MQTT
 * - Switch events via MQTT
 * - LED state via MQTT
 *
 * Broker: broker.mqttdashboard.com
 * Port: 1883 (MQTT over TCP)
 * -----------------------------------------------------
 */

#include <Arduino.h>
#include <ESP8266WiFi.h>
#include <PubSubClient.h>
#include <cstring>

// =====================================================
// 1. Wi-Fi Configuration
// =====================================================

const char* WIFI_SSID = "JANICE";
const char* WIFI_PASSWORD = "@Janice123@";

// =====================================================
// 2. MQTT Configuration
// =====================================================

const char* MQTT_SERVER =
    "broker.hivemq.com";

const uint16_t MQTT_PORT = 1883;

// Publishes potentiometer measurements
const char* TOPIC_POT =
    "testtopic/temp/outTopic/janice570";

// Receives LED ON/OFF commands from iPhone
const char* TOPIC_LED =
    "testtopic/temp/inTopic";

// Publishes physical switch events
const char* TOPIC_SWITCH =
    "testtopic/temp/switch/janice570";

// Publishes actual LED state
const char* TOPIC_LED_STATE =
    "testtopic/temp/ledState/janice570";

// =====================================================
// 3. Pin Definitions
// =====================================================

#define POT_PIN A0
#define BUTTON_PIN D5
#define LED_PIN D2

// =====================================================
// 4. Timing Configuration
// =====================================================

const unsigned long POT_READ_INTERVAL = 1000;
const unsigned long POT_PUBLISH_INTERVAL = 15000;
const unsigned long SWITCH_RESET_DELAY = 5000;
const unsigned long DEBOUNCE_DELAY = 40;
const unsigned long MQTT_RETRY_INTERVAL = 5000;
const unsigned long WIFI_RETRY_INTERVAL = 10000;

unsigned long lastPotRead = 0;
unsigned long lastPotPublish = 0;
unsigned long lastMQTTAttempt = 0;
unsigned long lastWiFiAttempt = 0;

// =====================================================
// 5. MQTT Client
// =====================================================

WiFiClient espClient;
PubSubClient mqtt(espClient);

// =====================================================
// 6. Button Variables
// =====================================================

bool lastButtonReading = HIGH;
bool stableButtonState = HIGH;

unsigned long lastDebounceTime = 0;

// Small in-memory queue of switch events
const uint8_t MAX_SWITCH_EVENTS = 10;
uint8_t queuedSwitchEvents = 0;

bool waitingToSendZero = false;
unsigned long switchOnePublishedAt = 0;

// =====================================================
// 7. LED Control
// =====================================================

void setLED(bool on) {

    digitalWrite(LED_PIN, on ? HIGH : LOW);

    Serial.println(on ? "LED ON" : "LED OFF");

    // Publish actual LED state
    if (mqtt.connected()) {

        mqtt.publish(
            TOPIC_LED_STATE,
            on ? "1" : "0",
            true
        );
    }
}

// =====================================================
// 8. Read Potentiometer
// =====================================================

void readPotentiometer() {

    int adcValue = analogRead(POT_PIN);

    Serial.print("Potentiometer ADC: ");
    Serial.println(adcValue);
}

// =====================================================
// 9. Publish Potentiometer to HiveMQ
// =====================================================

void publishPotentiometer() {

    if (!mqtt.connected()) {
        return;
    }

    int adcValue = analogRead(POT_PIN);

    char message[12];

    snprintf(
        message,
        sizeof(message),
        "%d",
        adcValue
    );

    bool sent = mqtt.publish(
        TOPIC_POT,
        message
    );

    Serial.print("MQTT Potentiometer: ");
    Serial.print(message);

    if (sent) {
        Serial.println(" | Published");
    } else {
        Serial.println(" | Publish failed");
    }
}

// =====================================================
// 10. Receive MQTT Messages
// =====================================================

void mqttCallback(
    char* topic,
    byte* payload,
    unsigned int length
) {

    Serial.print("MQTT Received Topic: ");
    Serial.println(topic);

    // Only process LED command topic
    if (strcmp(topic, TOPIC_LED) != 0) {
        return;
    }

    if (length == 1 && payload[0] == '1') {

        Serial.println("MQTT Command: LED ON");
        setLED(true);
    }

    else if (length == 1 && payload[0] == '0') {

        Serial.println("MQTT Command: LED OFF");
        setLED(false);
    }
}

// =====================================================
// 11. Control LED from Serial Monitor
// =====================================================

void checkSerial() {

    while (Serial.available() > 0) {

        char command = Serial.read();

        if (command == '1') {
            setLED(true);
        }

        else if (command == '0') {
            setLED(false);
        }
    }
}

// =====================================================
// 12. Detect Push Button
// =====================================================

void checkButton() {

    bool reading = digitalRead(BUTTON_PIN);

    // Detect raw button state change
    if (reading != lastButtonReading) {
        lastDebounceTime = millis();
    }

    // Debounce the switch
    if (millis() - lastDebounceTime >
        DEBOUNCE_DELAY) {

        if (reading != stableButtonState) {

            stableButtonState = reading;

            // Button pressed
            if (stableButtonState == LOW) {

                Serial.println("Button Pressed!");

            }

            // Button released
            else {

                Serial.println("Button Released!");

                if (queuedSwitchEvents <
                    MAX_SWITCH_EVENTS) {

                    queuedSwitchEvents++;

                    Serial.println(
                        "Switch event queued"
                    );

                } else {

                    Serial.println(
                        "Switch queue full!"
                    );
                }
            }
        }
    }

    lastButtonReading = reading;
}

// =====================================================
// 13. Publish Switch Events
// =====================================================

void publishSwitchEvents() {

    if (!mqtt.connected()) {
        return;
    }

    // First publish the zero for a previous event
    if (waitingToSendZero) {

        if (millis() - switchOnePublishedAt >=
            SWITCH_RESET_DELAY) {

            bool sent = mqtt.publish(
                TOPIC_SWITCH,
                "0"
            );

            if (sent) {

                Serial.println(
                    "MQTT Switch: 0 | Published"
                );

                waitingToSendZero = false;
            }
        }

        return;
    }

    // Publish 1 for the next queued button event
    if (queuedSwitchEvents > 0) {

        bool sent = mqtt.publish(
            TOPIC_SWITCH,
            "1"
        );

        if (sent) {

            Serial.println(
                "MQTT Switch: 1 | Published"
            );

            queuedSwitchEvents--;

            switchOnePublishedAt = millis();
            waitingToSendZero = true;
        }
    }
}

// =====================================================
// 14. Connect to MQTT Broker
// =====================================================

void connectMQTT() {

    if (mqtt.connected()) {
        return;
    }

    if (millis() - lastMQTTAttempt <
        MQTT_RETRY_INTERVAL) {
        return;
    }

    lastMQTTAttempt = millis();

    Serial.println("Connecting to MQTT...");

    String clientID = "ESP8266-janice570-";
    clientID += String(ESP.getChipId(), HEX);

    if (mqtt.connect(clientID.c_str())) {

        Serial.println("MQTT CONNECTED!");

        // Subscribe to LED control topic
        if (mqtt.subscribe(TOPIC_LED)) {

            Serial.print("Subscribed to: ");
            Serial.println(TOPIC_LED);
        }

        // Publish current LED state
        mqtt.publish(
            TOPIC_LED_STATE,
            digitalRead(LED_PIN) == HIGH ?
                "1" : "0",
            true
        );

        // Publish potentiometer immediately
        publishPotentiometer();
        lastPotPublish = millis();

    } else {

        Serial.print("MQTT failed, state: ");
        Serial.println(mqtt.state());
    }
}

// =====================================================
// 15. Setup
// =====================================================

void setup() {

    Serial.begin(115200);

    pinMode(LED_PIN, OUTPUT);
    pinMode(BUTTON_PIN, INPUT_PULLUP);

    digitalWrite(LED_PIN, LOW);

    Serial.println();
    Serial.println("==========================");
    Serial.println("ECE 570 MQTT Assignment");
    Serial.println("==========================");

    Serial.println("Starting Wi-Fi...");

    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

    mqtt.setServer(
        MQTT_SERVER,
        MQTT_PORT
    );

    mqtt.setCallback(mqttCallback);

    Serial.println("Setup complete!");
}

// =====================================================
// 16. Main Loop
// =====================================================

void loop() {

    unsigned long now = millis();

    // Local LED control
    checkSerial();

    // Monitor physical button
    checkButton();

    // Display potentiometer every second
    if (now - lastPotRead >=
        POT_READ_INTERVAL) {

        lastPotRead = now;
        readPotentiometer();
    }

    // Check Wi-Fi connection
    if (WiFi.status() != WL_CONNECTED) {

        if (now - lastWiFiAttempt >=
            WIFI_RETRY_INTERVAL) {

            lastWiFiAttempt = now;

            Serial.println(
                "Reconnecting Wi-Fi..."
            );

            WiFi.reconnect();
        }

        return;
    }

    // Reconnect MQTT if disconnected
    if (!mqtt.connected()) {

        connectMQTT();
        return;
    }

    // Process incoming MQTT messages
    mqtt.loop();

    // Publish queued switch events
    publishSwitchEvents();

    // Publish potentiometer every 15 seconds
    if (now - lastPotPublish >=
        POT_PUBLISH_INTERVAL) {

        lastPotPublish = now;
        publishPotentiometer();
    }
}
