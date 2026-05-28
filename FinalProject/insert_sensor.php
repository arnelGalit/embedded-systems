<?php
if (isset($_GET['soil_moisture']) && isset($_GET['temperature']) && isset($_GET['humidity'])) {

    $soil_moisture = (int)$_GET['soil_moisture'];
    $temperature   = (float)$_GET['temperature'];
    $humidity      = (float)$_GET['humidity'];

    $connection = new mysqli("localhost", "root", "", "finalprojectembedded"); // Change this if necessary

    if ($connection->connect_error) {
        die("MySQL connection failed: " . $connection->connect_error);
    }

    $sql = "INSERT INTO sensor_data (soil_moisture, temperature, humidity) VALUES ($soil_moisture, $temperature, $humidity)"; // Change this table name if necessary

    if ($connection->query($sql) === TRUE) {
        echo "New record created successfully. ID: " . $connection->insert_id;
    } else {
        echo "Error: " . $sql . " => " . $connection->error;
    }

    $connection->close();

} else {
    echo "Missing parameters. Received: " . http_build_query($_GET);
}
?>