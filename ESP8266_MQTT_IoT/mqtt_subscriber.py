
# -----------------------------------------
# ECE 570 - MQTT to Hostinger Database Relay
# ESP8266 -> HiveMQ -> Python -> MySQL
# -----------------------------------------

import os
import json
import uuid
from datetime import datetime
from urllib.request import Request, urlopen
from urllib.error import HTTPError, URLError
import paho.mqtt.client as mqtt

# MQTT settings
BROKER = "broker.hivemq.com"
TOPIC = "testtopic/temp/outTopic/janice570"

# Hostinger settings
API_URL = "https://jbargoria.com/mqtt_pot_insert.php"
API_KEY = os.environ.get("MQTT_RELAY_KEY", "").strip()

if not API_KEY:
    raise SystemExit("ERROR: MQTT_RELAY_KEY is missing!")


# Connect and subscribe to HiveMQ
def on_connect(client, userdata, flags, reason_code, properties):
    if reason_code == 0:
        client.subscribe(TOPIC)
        print("Connected to HiveMQ!")
        print("Subscribed to:", TOPIC)
    else:
        print("MQTT connection failed:", reason_code)


# Receive MQTT data and forward to Hostinger
def on_message(client, userdata, msg):

    # Get the laptop's local time (PST/PDT)
    local_time = datetime.now().astimezone()
    timestamp = local_time.isoformat(timespec="seconds")

    try:
        adc = int(msg.payload.decode().strip())

        if not 0 <= adc <= 1024:
            print("Invalid ADC value:", adc)
            return

    except ValueError:
        print("Invalid MQTT data")
        return

    # Prepare data for PHP
    data = {
        "value": adc,
        "topic": msg.topic,
        "recorded_at": timestamp
    }

    request = Request(
        API_URL,
        data=json.dumps(data).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "X-Relay-Key": API_KEY
        },
        method="POST"
    )

    print("\n-------------------------------")
    print("Local Time:", local_time.strftime("%Y-%m-%d %I:%M:%S %p %Z"))
    print("Potentiometer:", adc)

    try:
        with urlopen(request, timeout=7) as response:
            result = json.load(response)

        if result.get("success"):
            print("Hostinger: Data Saved!")
            print("Database ID:", result.get("id"))
        else:
            print("Hostinger: Insert not confirmed")

    except HTTPError as e:
        print("Hostinger HTTP Error:", e.code)
        print(e.read().decode("utf-8", errors="replace")[:200])

    except (URLError, TimeoutError, OSError, ValueError) as e:
        print("Hostinger Error:", e)


# Create MQTT client
client = mqtt.Client(
    callback_api_version=mqtt.CallbackAPIVersion.VERSION2,
    client_id="python-relay-" + uuid.uuid4().hex[:8]
)

client.on_connect = on_connect
client.on_message = on_message

# Start relay
print("ECE 570 - MQTT Database Relay")
print("Connecting to:", BROKER)

try:
    client.connect(BROKER, 1883, 60)
    client.loop_forever()

except KeyboardInterrupt:
    print("\nMQTT Relay Stopped")
    client.disconnect()
