<?php
// Ubah bagian terakhir dari "restoran_db" menjadi "restoran_test"
$conn = mysqli_connect("localhost", "root", "", "restoran_test");

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>