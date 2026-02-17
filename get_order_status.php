<?php
session_start();
include 'config/database.php';

if (isset($_GET['order_id']) || isset($_GET['session_id'])) {
    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    $session_id = isset($_GET['session_id']) ? mysqli_real_escape_string($conn, $_GET['session_id']) : '';
    
    // Cek kolom yang tersedia
    $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM orders");
    $columns = [];
    while ($row = mysqli_fetch_assoc($check_columns)) {
        $columns[] = $row['Field'];
    }
    
    $query_select = "status";
    if (in_array('status_pembayaran', $columns)) {
        $query_select .= ", status_pembayaran";
    }
    
    // Cari order berdasarkan session_id jika ada
    if (!empty($session_id) && in_array('session_id', $columns)) {
        $query = "SELECT $query_select FROM orders WHERE session_id = '$session_id' LIMIT 1";
    } else if ($order_id > 0) {
        $query = "SELECT $query_select FROM orders WHERE id = '$order_id' LIMIT 1";
    } else {
        echo json_encode(['error' => 'Invalid parameters']);
        exit();
    }
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $order = mysqli_fetch_assoc($result);
        $response = [
            'status' => $order['status'],
            'payment_status' => isset($order['status_pembayaran']) ? $order['status_pembayaran'] : 'pending'
        ];
        echo json_encode($response);
    } else {
        echo json_encode(['error' => 'Order not found']);
    }
} else {
    echo json_encode(['error' => 'No parameters provided']);
}
?>