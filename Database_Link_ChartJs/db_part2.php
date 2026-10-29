
<?php
// ===========================================
// ECE 570 - IoT Database Assignment Part 2
// Display, Insert, Plot and Show JSON Data
// ===========================================

header("Content-Type: text/html; charset=UTF-8");

// Load private database configuration
require_once dirname(__DIR__) . "/db_config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Connect to MariaDB
try {
    $conn = new mysqli(
        $db_host,
        $db_user,
        $db_password,
        $db_name,
        $db_port
    );
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Database connection failed.");
}

// Safely display database values
function safe($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$message = "";
$message_type = "";

// ===========================================
// SECTION 2: INSERT DATA USING HTTP GET
// ===========================================

if (
    isset($_GET['nodeId']) ||
    isset($_GET['nodeTemp']) ||
    isset($_GET['humidity']) ||
    isset($_GET['timeReceived'])
) {
    try {
        // Read parameters from URL
        $node = $_GET['nodeId'] ?? '';
        $temp = $_GET['nodeTemp'] ?? '';
        $humidity = $_GET['humidity'] ?? '';
        $time = $_GET['timeReceived'] ?? '';

        // Check parameter types
        if (
            !is_string($node) ||
            !is_string($temp) ||
            !is_string($humidity) ||
            !is_string($time)
        ) {
            throw new InvalidArgumentException(
                "Invalid request parameters."
            );
        }

        // Check required values
        if (
            $node === '' ||
            $temp === '' ||
            $humidity === ''
        ) {
            throw new InvalidArgumentException(
                "Missing sensor information."
            );
        }

        // Validate numeric values
        if (
            !is_numeric($temp) ||
            !is_numeric($humidity)
        ) {
            throw new InvalidArgumentException(
                "Temperature and humidity must be numeric."
            );
        }

        // Validate sensor name format
        if (!preg_match('/^node_[0-9]+$/', $node)) {
            throw new InvalidArgumentException(
                "Invalid node name."
            );
        }

        // Check valid sensor ranges
        if (
            $temp < -10 ||
            $temp > 100 ||
            $humidity < 0 ||
            $humidity > 100
        ) {
            throw new InvalidArgumentException(
                "Sensor values are out of range."
            );
        }

        // Check that sensor is registered
        $stmt = $conn->prepare(
            "SELECT node_name
             FROM sensor_register
             WHERE node_name = ?"
        );

        $stmt->bind_param("s", $node);
        $stmt->execute();

        $registered = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registered) {
            throw new InvalidArgumentException(
                "Sensor is not registered."
            );
        }

        // ===================================
        // Validate or generate timestamp
        // ===================================

        if ($time !== '') {
            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $time
            );

            if (
                !$date ||
                $date->format('Y-m-d H:i:s') !== $time
            ) {
                throw new InvalidArgumentException(
                    "Invalid date/time format."
                );
            }
        } else {
            // Use MariaDB server time
            $result_time = $conn->query(
                "SELECT NOW() AS server_time"
            );

            $time = $result_time->fetch_assoc()[
                'server_time'
            ];
        }

        // ===================================
        // Check for duplicate timestamp
        // ===================================

        $stmt = $conn->prepare(
            "SELECT id
             FROM sensor_data
             WHERE node_name = ?
             AND time_received = ?"
        );

        $stmt->bind_param("ss", $node, $time);
        $stmt->execute();

        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            throw new RuntimeException(
                "Duplicate timestamp for this sensor.",
                409
            );
        }

        // ===================================
        // Insert valid sensor reading
        // ===================================

        $stmt = $conn->prepare(
            "INSERT INTO sensor_data
             (node_name, time_received,
              temperature, humidity)
             VALUES (?, ?, ?, ?)"
        );

        $temp = (float)$temp;
        $humidity = (float)$humidity;

        $stmt->bind_param(
            "ssdd",
            $node,
            $time,
            $temp,
            $humidity
        );

        $stmt->execute();
        $stmt->close();

        $message = "Sensor reading inserted successfully!";
        $message_type = "success";

    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());

        if ($e->getCode() == 1062) {
            $message = "Duplicate sensor timestamp.";
            http_response_code(409);

        } elseif ($e->getCode() == 1452) {
            $message = "Sensor is not registered.";
            http_response_code(400);

        } elseif (
            $e->getCode() == 4025 ||
            $e->getCode() == 3819
        ) {
            $message = "Sensor value is out of range.";
            http_response_code(400);

        } else {
            $message = "Unable to insert sensor reading.";
            http_response_code(500);
        }

        $message_type = "error";

    } catch (InvalidArgumentException $e) {
        $message = $e->getMessage();
        $message_type = "error";
        http_response_code(400);

    } catch (RuntimeException $e) {
        $message = $e->getMessage();
        $message_type = "error";
        http_response_code(409);
    }
}

// ===========================================
// SECTION 1: REGISTERED SENSORS
// ===========================================

$sql_register = "
    SELECT node_name, manufacturer,
           longitude, latitude
    FROM sensor_register
    ORDER BY node_name
";

// Retrieve sensor readings
$sql_data = "
    SELECT id, node_name, time_received,
           temperature, humidity
    FROM sensor_data
    ORDER BY node_name, time_received
";

// ===========================================
// SECTION 2: AVERAGES FOR NODE_1
// ===========================================

$sql_average = "
    SELECT
        AVG(temperature) AS avg_temperature,
        AVG(humidity) AS avg_humidity
    FROM sensor_data
    WHERE node_name = 'node_1'
";

// ===========================================
// SECTION 3: CHART DATA FOR NODE_1
// ===========================================

$sql_chart = "
    SELECT time_received, temperature
    FROM sensor_data
    WHERE node_name = 'node_1'
    ORDER BY time_received
";

// ===========================================
// NEW SECTION: JSON DATA FOR NODE_1
// ===========================================

$sql_json = "
    SELECT node_name, time_received,
           temperature, humidity
    FROM sensor_data
    WHERE node_name = 'node_1'
    ORDER BY time_received
";

// Execute all SELECT queries
try {
    $result_register = $conn->query($sql_register);
    $result_data = $conn->query($sql_data);
    $result_average = $conn->query($sql_average);
    $result_chart = $conn->query($sql_chart);
    $result_json = $conn->query($sql_json);

    // Get averages
    $average = $result_average->fetch_assoc();

    // Prepare Chart.js arrays
    $chart_times = [];
    $chart_temperatures = [];

    while ($row = $result_chart->fetch_assoc()) {
        $chart_times[] = $row['time_received'];
        $chart_temperatures[] = (float)$row['temperature'];
    }

    // NEW: Prepare JSON records
    $sensor_json = [];

    while ($row = $result_json->fetch_assoc()) {
        $sensor_json[] = [
            "node_name" => $row["node_name"],
            "time_received" => $row["time_received"],
            "temperature" => (float)$row["temperature"],
            "humidity" => (float)$row["humidity"]
        ];
    }

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Unable to retrieve sensor information.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Sensor Readings Database</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f8;
            color: #222;
            margin: 30px;
        }

        h1 {
            color: #0f766e;
            text-align: center;
        }

        h2 {
            color: #0f766e;
            margin-top: 35px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 15px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #0f766e;
            color: white;
        }

        tr:nth-child(even) {
            background: #eaf5f3;
        }

        a {
            color: #0f766e;
        }

        .average-box {
            background: white;
            border: 1px solid #ddd;
            border-left: 5px solid #0f766e;
            border-radius: 6px;
            padding: 20px;
            margin-top: 20px;
        }

        .average-box p {
            font-size: 18px;
            margin: 12px 0;
        }

        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .chart-box {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            margin-top: 20px;
        }

        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }

        /* NEW: JSON display */
        .json-box {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            margin-top: 20px;
        }

        .json-box pre {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            font-size: 14px;
        }
    </style>
</head>

<body>

<h1>Sensor Database</h1>

<p>Janice Bargoria</p>
<p>ECE 570 - Internet of Things</p>

<!-- Display insertion message -->
<?php if ($message !== ""): ?>
    <div class="message <?= safe($message_type) ?>">
        <?= safe($message) ?>
    </div>
<?php endif; ?>

<!-- ========================================
     FIRST TABLE: REGISTERED SENSORS
     ======================================== -->

<h2>Registered Sensor Nodes</h2>

<div class="table-container">
<table>
    <thead>
        <tr>
            <th>Node Name</th>
            <th>Manufacturer</th>
            <th>Longitude</th>
            <th>Latitude</th>
        </tr>
    </thead>

    <tbody>
    <?php while ($row = $result_register->fetch_assoc()): ?>
        <tr>
            <td><?= safe($row['node_name']) ?></td>
            <td><?= safe($row['manufacturer']) ?></td>
            <td><?= safe($row['longitude']) ?></td>
            <td><?= safe($row['latitude']) ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</div>

<!-- ========================================
     SECOND TABLE: SENSOR READINGS
     ======================================== -->

<h2>Data Received</h2>

<div class="table-container">
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Node Name</th>
            <th>Time Received</th>
            <th>Temperature (°C)</th>
            <th>Humidity (%)</th>
        </tr>
    </thead>

    <tbody>
    <?php while ($row = $result_data->fetch_assoc()): ?>
        <tr>
            <td><?= safe($row['id']) ?></td>
            <td><?= safe($row['node_name']) ?></td>
            <td><?= safe($row['time_received']) ?></td>
            <td><?= safe($row['temperature']) ?></td>
            <td><?= safe($row['humidity']) ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</div>

<!-- ========================================
     AVERAGE SENSOR READINGS
     ======================================== -->

<h2>Average Sensor Readings - node_1</h2>

<div class="average-box">
    <p>
        Average Temperature for node_1:
        <strong>
            <?php
            if ($average['avg_temperature'] !== null) {
                echo number_format(
                    (float)$average['avg_temperature'], 2
                ) . " °C";
            } else {
                echo "No readings available";
            }
            ?>
        </strong>
    </p>

    <p>
        Average Humidity for node_1:
        <strong>
            <?php
            if ($average['avg_humidity'] !== null) {
                echo number_format(
                    (float)$average['avg_humidity'], 2
                ) . " %";
            } else {
                echo "No readings available";
            }
            ?>
        </strong>
    </p>
</div>

<!-- ========================================
     TEMPERATURE GRAPH USING CHART.JS
     ======================================== -->

<h2>Temperature vs. Time - node_1</h2>

<div class="chart-box">
    <div class="chart-container">
        <canvas id="temperatureChart"></canvas>
    </div>
</div>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Convert PHP arrays to JavaScript arrays
    const times = <?= json_encode(
        $chart_times,
        JSON_HEX_TAG | JSON_HEX_AMP |
        JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;

    const temperatures = <?= json_encode(
        $chart_temperatures
    ) ?>;

    // Create green line graph
    new Chart(
        document.getElementById('temperatureChart'),
        {
            type: 'line',

            data: {
                labels: times,

                datasets: [{
                    label: 'Temperature (°C)',
                    data: temperatures,

                    // Your custom green colors
                    borderColor: 'rgb(0, 128, 0)',
                    backgroundColor: 'rgba(0, 127, 0, 0.95)',
                    pointBackgroundColor: 'rgb(0, 128, 0)',

                    borderWidth: 2,
                    tension: 0.2,
                    fill: false
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                plugins: {
                    title: {
                        display: true,
                        text: 'Sensor Node 1'
                    }
                },

                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Time'
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            autoSkip: true
                        }
                    },

                    y: {
                        title: {
                            display: true,
                            text: 'Temperature (°C)'
                        }
                    }
                }
            }
        }
    );
</script>

<!-- ========================================
     NEW: SENSOR DATA IN JSON FORMAT
     ======================================== -->

<h2>Sensor Node 1 - JSON Data</h2>

<div class="json-box">
    <pre><?= safe(json_encode(
        $sensor_json,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    )) ?></pre>
</div>

<!-- Back to homepage -->
<p>
    <a href="index.html">Back to Main IoT Page</a>
</p>

</body>
</html>

<?php
$conn->close();
?>
