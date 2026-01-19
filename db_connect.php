<?php
$conn = mysqli_init();

// Use system CA certificates for the cloud connection
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

// Get cloud variables, or use local defaults if not found
$host = getenv('DB_HOST') ?: "localhost";
$user = getenv('DB_USER') ?: "root";
$pass = getenv('DB_PASSWORD') ?: "Admin123";
$name = getenv('DB_NAME') ?: "trainingc";
$port = getenv('DB_PORT') ?: 3306;

mysqli_real_connect($conn, $host, $user, $pass, $name, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>