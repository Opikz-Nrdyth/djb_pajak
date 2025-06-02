<?php
$host = "localhost";
$username = "root";
$password = "";
$database_name = "djb_pajak";

$conn = new mysqli($host, $username, $password, $database_name);

if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}
