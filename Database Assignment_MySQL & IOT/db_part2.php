
<?php
// ===========================================
// Display sensor registration and readings
// ===========================================

header("Content-Type: text/html; charset=UTF-8");

// Load private database configuration.
// db_config.php is stored one level above public_html.
require_once dirname(__DIR__) . "/db_config.php";

// Connect to MariaDB
$conn = new mysqli(
    $db_host,
    $db_user,
    $db_password,
    $db_name,
    $db_port
);

// Check connection
if ($conn->connect_error) {
    error_log($conn->connect_error);
    exit("Database connection failed.");
}

// Set character encoding
$conn->set_charset("utf8mb4");

// Retrieve registered sensors
$sql_register = "
    SELECT node_name, manufacturer,
           longitude, latitude
    FROM sensor_register
    ORDER BY node_name
";

$result_register = $conn->query($sql_register);

// Retrieve sensor readings
// Sorted by Node Name, then Time
$sql_data = "
    SELECT id, node_name, time_received,
           temperature, humidity
    FROM sensor_data
    ORDER BY node_name, time_received
";

$result_data = $conn->query($sql_data);

// Check queries
if (!$result_register || !$result_data) {
    error_log($conn->error);
    exit("Unable to retrieve sensor information.");
}

// Escape database text before displaying it
function safe($value) {
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title> Sensor Readings Database</title>

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
            margin-top: 35px;
            color: #0f766e;
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
    </style>
</head>

<body>

<h1> Sensor Database </h1>

<p> Janice Bargoria</p>
<p>ECE 570 - Internet of Things</p>

<!-- First Table -->
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

<!-- Second Table -->
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

<p>
    <a href="index.html">Back to Main IoT Page</a>
</p>

</body>
</html>

<?php
// Close the database connection
$conn->close();
?>
