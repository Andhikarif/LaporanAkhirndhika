<?php
$host     = "127.0.0.1";     // atau IP server database
$username = "root";          // sesuaikan dengan user MySQL Anda
$password = "";              // sesuaikan dengan password MySQL Anda
$database = "bengkel1";       // nama database

// Buat koneksi
$koneksi = new mysqli($host, $username, $password, $database);

// Periksa koneksi
if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}
?>
