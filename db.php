<?php

$host     = "localhost";
$user     = "root";
$pass     = "";          
$database = "strivex";

$conn = mysqli_connect($host, $user, $pass, $database);

if (!$conn) {
    die("<h2 style='color:red;font-family:sans-serif;'>
        Database connection failed: " . mysqli_connect_error() . "<br>
        Make sure XAMPP/WAMP is running and you have imported strivex.sql
    </h2>");
}

mysqli_set_charset($conn, "utf8");
?>
