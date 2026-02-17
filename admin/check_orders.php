<?php
include '../config/database.php';

// Menghitung jumlah pesanan dengan status 'pending'
$query = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$data = mysqli_fetch_assoc($query);

header('Content-Type: application/json');
echo json_encode(['new_orders' => $data['total']]);
?>