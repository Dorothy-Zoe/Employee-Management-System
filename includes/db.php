<?php
$dbHost = 'localhost:3306'; 
$dbUser = 'root';
$dbPassword = '';
$dbName = 'emsdb';

$conn = mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName);

// Check if the connection was successful
if (mysqli_connect_errno()) {
    echo "Connection Fail: " . mysqli_connect_error();
    exit; 
}


?>
