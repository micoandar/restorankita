<?php
session_start();
include '../config/database.php';

// Redirect jika belum login
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// ============================
// CEK DAN BUAT KOLOM JIKA BELUM ADA
// ============================
$check_payment_columns = mysqli_query($conn, "SHOW COLUMNS FROM orders");
$columns = [];
while ($row = mysqli_fetch_assoc($check_payment_columns)) {
    $columns[] = $row['Field'];
}

// Cek dan tambahkan kolom metode_pembayaran jika belum ada
if (!in_array('metode_pembayaran', $columns)) {
    $add_metode_pembayaran = "ALTER TABLE orders ADD COLUMN metode_pembayaran VARCHAR(20) DEFAULT 'cash'";
    mysqli_query($conn, $add_metode_pembayaran);
    error_log("Kolom metode_pembayaran ditambahkan ke tabel orders");
}

// Cek dan tambahkan kolom status_pembayaran jika belum ada
if (!in_array('status_pembayaran', $columns)) {
    $add_status_pembayaran = "ALTER TABLE orders ADD COLUMN status_pembayaran VARCHAR(20) DEFAULT 'pending'";
    mysqli_query($conn, $add_status_pembayaran);
    error_log("Kolom status_pembayaran ditambahkan ke tabel orders");
}

// Cek dan tambahkan kolom session_id jika belum ada
if (!in_array('session_id', $columns)) {
    $add_session_id = "ALTER TABLE orders ADD COLUMN session_id VARCHAR(100) NULL";
    mysqli_query($conn, $add_session_id);
    error_log("Kolom session_id ditambahkan ke tabel orders");
}

// Generate session_id untuk data yang sudah ada tapi tidak punya session_id
$check_no_session = mysqli_query($conn, "SELECT * FROM orders WHERE session_id IS NULL OR session_id = ''");
if (mysqli_num_rows($check_no_session) > 0) {
    $update_session_query = "UPDATE orders SET session_id = CONCAT(nama_pelanggan, '_', DATE(created_at), '_', FLOOR(RAND() * 10000)) WHERE session_id IS NULL OR session_id = ''";
    mysqli_query($conn, $update_session_query);
}

// Set tanggal default ke semua data (tidak filter tanggal)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Pagination setup
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Query dengan filter - PERBAIKAN: Tanggal optional
$where = "WHERE 1=1"; // Default semua data

// Filter tanggal jika diisi
if (!empty($start_date) && !empty($end_date)) {
    $where .= " AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
} elseif (!empty($start_date)) {
    $where .= " AND DATE(created_at) >= '$start_date'";
} elseif (!empty($end_date)) {
    $where .= " AND DATE(created_at) <= '$end_date'";
}

// Status filter
if (isset($_GET['status']) && $_GET['status'] != 'all') {
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $where .= " AND status = '$status'";
}

// Payment method filter - hanya jika kolom ada
$check_metode = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'metode_pembayaran'");
$has_metode = mysqli_num_rows($check_metode) > 0;

if ($has_metode && isset($_GET['payment_method']) && $_GET['payment_method'] != 'all') {
    $payment_method = mysqli_real_escape_string($conn, $_GET['payment_method']);
    $where .= " AND metode_pembayaran = '$payment_method'";
}

// Payment status filter - hanya jika kolom ada
$check_status_pembayaran = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'status_pembayaran'");
$has_status_pembayaran = mysqli_num_rows($check_status_pembayaran) > 0;

if ($has_status_pembayaran && isset($_GET['payment_status']) && $_GET['payment_status'] != 'all') {
    $payment_status = mysqli_real_escape_string($conn, $_GET['payment_status']);
    $where .= " AND status_pembayaran = '$payment_status'";
}

// Search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $where .= " AND (nama_pelanggan LIKE '%$search%' OR menu LIKE '%$search%')";
}

// Debug: Check if connection is working
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// ============================
// QUERY UNTUK MENGELOMPOKKAN ORDER BERDASARKAN SESSION_ID
// ============================
if (in_array('session_id', $columns)) {
    // Gunakan session_id untuk pengelompokan
    $group_condition = "COALESCE(session_id, CONCAT(nama_pelanggan, '_', DATE(created_at), '_', HOUR(created_at), '_', MINUTE(created_at)))";
} else {
    // Fallback jika session_id tidak ada
    $group_condition = "CONCAT(nama_pelanggan, '_', DATE(created_at), '_', HOUR(created_at), '_', MINUTE(created_at))";
}

// Query untuk mengambil data order dengan pengelompokan yang benar
$grouped_orders_select = "
    SELECT 
        $group_condition as virtual_session_id,
        MIN(id) as id,
        MIN(created_at) as created_at,
        nama_pelanggan,
        GROUP_CONCAT(DISTINCT menu ORDER BY id SEPARATOR '||') as menu_items,
        GROUP_CONCAT(jumlah ORDER BY id SEPARATOR ',') as quantities,
        GROUP_CONCAT(harga ORDER BY id SEPARATOR ',') as prices,
        SUM(total) as total,
        MIN(status) as status";

if ($has_metode) {
    $grouped_orders_select .= ", MIN(metode_pembayaran) as metode_pembayaran";
}

if ($has_status_pembayaran) {
    $grouped_orders_select .= ", MIN(status_pembayaran) as status_pembayaran";
}

// Ambil session_id jika ada
if (in_array('session_id', $columns)) {
    $grouped_orders_select .= ", MIN(session_id) as session_id";
}

$grouped_orders_select .= ",
        GROUP_CONCAT(COALESCE(catatan, '') SEPARATOR '||') as catatan,
        COUNT(*) as item_count
    FROM orders 
    $where 
    GROUP BY $group_condition
    ORDER BY created_at DESC";

// Hitung total records untuk pagination
$total_records_query = "SELECT COUNT(DISTINCT $group_condition) as total FROM orders $where";
$total_records_result = mysqli_query($conn, $total_records_query);
if ($total_records_result) {
    $total_records_data = mysqli_fetch_assoc($total_records_result);
    $total_records = $total_records_data['total'];
} else {
    $total_records = 0;
}

// Hitung total pages
$total_pages = ceil($total_records / $records_per_page);

// Ambil data order dengan pagination
$orders_select = "
    SELECT grouped.* FROM (
        $grouped_orders_select
    ) as grouped
    LIMIT $offset, $records_per_page
";

$orders = mysqli_query($conn, $orders_select);

// Check for query errors
if (!$orders) {
    die("Error fetching orders: " . mysqli_error($conn));
}

// Hitung total revenue - Hanya hitung yang completed
$revenue_query = "
    SELECT SUM(total_sum) as total FROM (
        SELECT $group_condition, SUM(total) as total_sum 
        FROM orders 
        $where AND status = 'completed'
        GROUP BY $group_condition
    ) as completed_orders
";

$revenue_result = mysqli_query($conn, $revenue_query);
if (!$revenue_result) {
    die("Error calculating revenue: " . mysqli_error($conn));
}
$total_revenue_data = mysqli_fetch_assoc($revenue_result);
$total_revenue = $total_revenue_data['total'] ?: 0;

// Total semua order (tanpa filter) - hitung semua record individual
$all_orders_query = "SELECT COUNT(*) as total FROM orders";
$all_orders_result = mysqli_query($conn, $all_orders_query);
$all_orders_data = mysqli_fetch_assoc($all_orders_result);
$all_orders = $all_orders_data['total'] ?: 0;

// ============================
// PERBAIKAN UPDATE STATUS BERDASARKAN SESSION_ID
// ============================
if (isset($_POST['update_status'])) {
    $virtual_session_id = mysqli_real_escape_string($conn, $_POST['session_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    error_log("=== UPDATE STATUS ===");
    error_log("Session ID: " . $virtual_session_id);
    error_log("Status baru: " . $status);
    
    // Update berdasarkan session_id
    if (in_array('session_id', $columns)) {
        // Cek apakah virtual_session_id adalah session_id yang valid
        $check_session_query = "SELECT session_id FROM orders WHERE session_id = '$virtual_session_id' LIMIT 1";
        $check_session_result = mysqli_query($conn, $check_session_query);
        
        if (mysqli_num_rows($check_session_result) > 0) {
            // Ini adalah session_id yang valid
            $update_query = "UPDATE orders 
                            SET status = '$status' 
                            WHERE session_id = '$virtual_session_id'";
        } else {
            // Ini adalah virtual_session_id (gabungan)
            $update_query = "UPDATE orders 
                            SET status = '$status' 
                            WHERE $group_condition = '$virtual_session_id'";
        }
    } else {
        // Tidak ada session_id, gunakan virtual_session_id
        $update_query = "UPDATE orders 
                        SET status = '$status' 
                        WHERE $group_condition = '$virtual_session_id'";
    }
    
    error_log("Query update: " . $update_query);
    
    if (mysqli_query($conn, $update_query)) {
        $affected_rows = mysqli_affected_rows($conn);
        $_SESSION['success'] = "Status order berhasil diperbarui untuk $affected_rows item!";
        error_log("Berhasil update $affected_rows rows");
    } else {
        $_SESSION['error'] = "Gagal memperbarui status: " . mysqli_error($conn);
        error_log("Gagal update: " . mysqli_error($conn));
    }
    
    header("Location: order.php?" . http_build_query($_GET));
    exit();
}

// Update status pembayaran - hanya jika kolom ada
if (isset($_POST['update_payment_status']) && $has_status_pembayaran) {
    $virtual_session_id = mysqli_real_escape_string($conn, $_POST['session_id']);
    $payment_status = mysqli_real_escape_string($conn, $_POST['payment_status']);
    
    error_log("=== UPDATE PAYMENT STATUS ===");
    error_log("Session ID: " . $virtual_session_id);
    error_log("Payment status baru: " . $payment_status);
    
    // Update berdasarkan session_id
    if (in_array('session_id', $columns)) {
        // Cek apakah virtual_session_id adalah session_id yang valid
        $check_session_query = "SELECT session_id FROM orders WHERE session_id = '$virtual_session_id' LIMIT 1";
        $check_session_result = mysqli_query($conn, $check_session_query);
        
        if (mysqli_num_rows($check_session_result) > 0) {
            // Ini adalah session_id yang valid
            $update_query = "UPDATE orders 
                            SET status_pembayaran = '$payment_status' 
                            WHERE session_id = '$virtual_session_id'";
        } else {
            // Ini adalah virtual_session_id (gabungan)
            $update_query = "UPDATE orders 
                            SET status_pembayaran = '$payment_status' 
                            WHERE $group_condition = '$virtual_session_id'";
        }
    } else {
        // Tidak ada session_id, gunakan virtual_session_id
        $update_query = "UPDATE orders 
                        SET status_pembayaran = '$payment_status' 
                        WHERE $group_condition = '$virtual_session_id'";
    }
    
    if (mysqli_query($conn, $update_query)) {
        $affected_rows = mysqli_affected_rows($conn);
        $_SESSION['success'] = "Status pembayaran berhasil diperbarui untuk $affected_rows item!";
    } else {
        $_SESSION['error'] = "Gagal memperbarui status pembayaran: " . mysqli_error($conn);
    }
    
    header("Location: order.php?" . http_build_query($_GET));
    exit();
}

// Hapus semua order dalam kelompok yang sama
if (isset($_GET['hapus'])) {
    $virtual_session_id = mysqli_real_escape_string($conn, $_GET['hapus']);
    
    // Hapus berdasarkan session_id
    if (in_array('session_id', $columns)) {
        // Cek apakah virtual_session_id adalah session_id yang valid
        $check_session_query = "SELECT session_id FROM orders WHERE session_id = '$virtual_session_id' LIMIT 1";
        $check_session_result = mysqli_query($conn, $check_session_query);
        
        if (mysqli_num_rows($check_session_result) > 0) {
            // Ini adalah session_id yang valid
            $delete_query = "DELETE FROM orders 
                            WHERE session_id = '$virtual_session_id'";
        } else {
            // Ini adalah virtual_session_id (gabungan)
            $delete_query = "DELETE FROM orders 
                            WHERE $group_condition = '$virtual_session_id'";
        }
    } else {
        // Tidak ada session_id, gunakan virtual_session_id
        $delete_query = "DELETE FROM orders 
                        WHERE $group_condition = '$virtual_session_id'";
    }
    
    if (mysqli_query($conn, $delete_query)) {
        $affected_rows = mysqli_affected_rows($conn);
        $_SESSION['success'] = "Semua item order ($affected_rows item) berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus order: " . mysqli_error($conn);
    }
    
    header("Location: order.php?" . http_build_query($_GET));
    exit();
}

// Hapus item spesifik dari order
if (isset($_GET['hapus_item'])) {
    $item_id = (int)$_GET['hapus_item'];
    
    // Hapus item spesifik
    $delete_item_query = "DELETE FROM orders WHERE id = '$item_id'";
    if (mysqli_query($conn, $delete_item_query)) {
        $_SESSION['success'] = "Item berhasil dihapus dari order.";
    } else {
        $_SESSION['error'] = "Gagal menghapus item: " . mysqli_error($conn);
    }
    
    header("Location: order.php?" . http_build_query($_GET));
    exit();
}

// Ambil jumlah pelanggan unik
$customers_query = "SELECT COUNT(DISTINCT nama_pelanggan) as total FROM orders $where";
$customers_result = mysqli_query($conn, $customers_query);
if ($customers_result) {
    $unique_customers_data = mysqli_fetch_assoc($customers_result);
    $unique_customers = $unique_customers_data['total'] ?: 0;
} else {
    $unique_customers = 0;
}

// Hitung total pembayaran cash vs qris - hanya jika kolom ada
if ($has_metode && $has_status_pembayaran) {
    $payment_summary_query = "
        SELECT 
            COALESCE(SUM(CASE WHEN metode_pembayaran = 'cash' AND status_pembayaran = 'paid' THEN total ELSE 0 END), 0) as cash_paid,
            COALESCE(SUM(CASE WHEN metode_pembayaran = 'qris' AND status_pembayaran = 'paid' THEN total ELSE 0 END), 0) as qris_paid,
            COALESCE(SUM(CASE WHEN status_pembayaran = 'pending' THEN total ELSE 0 END), 0) as pending_payment
        FROM (
            SELECT $group_condition, 
                   MIN(metode_pembayaran) as metode_pembayaran,
                   MIN(status_pembayaran) as status_pembayaran,
                   SUM(total) as total
            FROM orders 
            $where
            GROUP BY $group_condition
        ) as payment_summary";

    $payment_summary_result = mysqli_query($conn, $payment_summary_query);
    if ($payment_summary_result) {
        $payment_summary = mysqli_fetch_assoc($payment_summary_result);
        $cash_paid = $payment_summary['cash_paid'] ?: 0;
        $qris_paid = $payment_summary['qris_paid'] ?: 0;
        $pending_payment = $payment_summary['pending_payment'] ?: 0;
    } else {
        $cash_paid = 0;
        $qris_paid = 0;
        $pending_payment = 0;
    }
} else {
    $cash_paid = 0;
    $qris_paid = 0;
    $pending_payment = 0;
}

// Debug: Check total data in database
$debug_total_query = "
    SELECT 
        COUNT(DISTINCT $group_condition) as total_groups, 
        COUNT(*) as total_items,
        MIN(created_at) as earliest, 
        MAX(created_at) as latest
    FROM orders $where";
$debug_result = mysqli_query($conn, $debug_total_query);
$debug_data = mysqli_fetch_assoc($debug_result);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Order - Restoran Kita</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
            margin-bottom: 20px;
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 15px;
        }
        
        .stats-icon.orders {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .stats-icon.revenue {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            color: white;
        }
        
        .stats-icon.customers {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
            color: white;
        }
        
        .stats-icon.cash {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .stats-icon.qris {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        
        .stats-icon.pending-payment {
            background: linear-gradient(135deg, #ffc107, #ffb347);
            color: white;
        }
        
        .stats-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .order-status {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .status-processing {
            background-color: rgba(0, 123, 255, 0.1);
            color: #007bff;
        }
        
        .status-completed {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .status-cancelled {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .payment-method-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .payment-cash {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .payment-qris {
            background-color: rgba(0, 123, 255, 0.1);
            color: #007bff;
        }
        
        .payment-status-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .payment-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .payment-paid {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .action-dropdown .dropdown-menu {
            min-width: 150px;
            padding: 10px 0;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: none;
        }
        
        .action-dropdown .dropdown-item {
            padding: 8px 15px;
            display: flex;
            align-items: center;
        }
        
        .action-dropdown .dropdown-item i {
            width: 20px;
            margin-right: 10px;
        }
        
        .action-dropdown .dropdown-item:hover {
            background-color: rgba(255, 107, 107, 0.1);
            color: var(--primary);
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .table-responsive {
            border-radius: 15px;
        }
        
        .dataTables_wrapper {
            padding: 0;
        }
        
        .dataTables_length,
        .dataTables_filter {
            padding: 20px;
        }
        
        .dataTables_info {
            padding: 0 20px;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .dataTables_paginate {
            padding: 20px;
        }
        
        .pagination {
            justify-content: center;
        }
        
        .page-link {
            border: 1px solid #dee2e6;
            color: var(--primary);
        }
        
        .page-item.active .page-link {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .date-range {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .export-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .export-btn:hover {
            background: linear-gradient(135deg, #218838, #1ea97f);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #dee2e6;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .database-info {
            background-color: #f8f9fa;
            padding: 10px 20px;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-reset {
            background: #6c757d;
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-reset:hover {
            background: #5a6268;
            color: white;
        }
        
        .search-btn {
            background: var(--primary);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .search-btn:hover {
            background: var(--primary-dark);
            color: white;
        }
        
        .form-group-icon {
            position: relative;
        }
        
        .form-group-icon .form-control {
            padding-left: 40px;
        }
        
        .form-group-icon i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .badge-items {
            background-color: #e9ecef;
            color: #495057;
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        .item-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }
        
        .item-name {
            flex-grow: 1;
        }
        
        .item-qty {
            margin: 0 10px;
            color: #666;
        }
        
        .item-price {
            color: #28a745;
            font-weight: 500;
        }
        
        .item-total {
            font-weight: 600;
            color: var(--primary);
        }
        
        .item-remove {
            color: #dc3545;
            cursor: pointer;
            margin-left: 10px;
        }
        
        .item-remove:hover {
            color: #bd2130;
        }
        
        .order-group-header {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid var(--primary);
        }
        
        .refresh-btn {
            background: var(--primary);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .refresh-btn:hover {
            background: var(--primary-dark);
            color: white;
        }
        
        .debug-info {
            background-color: #f0f8ff;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 0.8rem;
            color: #0066cc;
            margin-top: 5px;
            border-left: 3px solid #0066cc;
        }
        
        .modal-debug {
            font-size: 0.75rem;
            color: #666;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 3px;
            margin-top: 5px;
        }
        
        .payment-summary {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .payment-summary-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .payment-summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .payment-summary-item:last-child {
            border-bottom: none;
            font-weight: 600;
            color: var(--primary);
        }
        
        .payment-update-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .payment-info-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .payment-info-label {
            min-width: 120px;
            font-weight: 500;
            color: #495057;
        }
        
        .filter-row {
            margin-bottom: 15px;
        }
        
        .filter-label {
            font-weight: 500;
            margin-bottom: 5px;
            color: #495057;
        }
        
        .column-info {
            font-size: 0.8rem;
            color: #6c757d;
            font-style: italic;
        }
        
        .order-group-badge {
            background-color: #6f42c1;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: 5px;
        }
    </style>
</head>
<body class="admin-body">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-utensils me-2"></i>Admin Panel</h3>
            <small>Restoran Kita</small>
        </div>
        
        <div class="sidebar-menu">
            <ul>
                <li>
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="menu.php">
                        <i class="fas fa-utensils"></i>
                        <span>Kelola Menu</span>
                    </a>
                </li>
                <li>
                    <a href="order.php" class="active">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Data Order</span>
                    </a>
                </li>
                <li>
                    <a href="customer.php">
                        <i class="fas fa-users"></i>
                        <span>Pelanggan</span>
                    </a>
                </li>
                <li>
                    <a href="report.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Laporan</span>
                    </a>
                </li>
                <li>
                    <a href="../index.php" target="_blank">
                        <i class="fas fa-eye"></i>
                        <span>Lihat Website Utama</span>
                    </a>
                </li>
                <li>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="admin-header">
            <button class="toggle-btn" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <h4 class="mb-0">Data Order</h4>
            
            <div class="header-right">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?>
                </div>
                <div>
                    <div class="fw-bold"><?php echo $_SESSION['admin']; ?></div>
                    <small class="text-muted">Administrator</small>
                </div>
            </div>
        </header>
        
        <!-- Content -->
        <div class="content-wrapper">
            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-admin">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-admin">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>
            
            <!-- Database Info -->
            <div class="database-info">
                <small>
                    <i class="fas fa-database me-1"></i> 
                    Menampilkan: <?php echo $debug_data['total_groups']; ?> order kelompok 
                    (<?php echo $debug_data['total_items']; ?> item total) |
                    Grouping by: <?php echo in_array('session_id', $columns) ? 'session_id' : 'nama_tanggal_jam_menit'; ?>
                </small>
            </div>
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stats-value"><?php echo $all_orders; ?></div>
                        <div class="stats-label">Total Semua Order (Item)</div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-icon revenue">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stats-value">Rp <?php echo number_format($total_revenue); ?></div>
                        <div class="stats-label">Total Pendapatan</div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-icon customers">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stats-value"><?php echo $unique_customers; ?></div>
                        <div class="stats-label">Pelanggan Unik</div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Summary -->
            <?php if($has_metode && $has_status_pembayaran): ?>
            <div class="payment-summary">
                <div class="payment-summary-title">
                    <i class="fas fa-credit-card me-2"></i>Ringkasan Pembayaran
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-icon cash">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="stats-value">Rp <?php echo number_format($cash_paid); ?></div>
                            <div class="stats-label">Cash (Lunas)</div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-icon qris">
                                <i class="fas fa-qrcode"></i>
                            </div>
                            <div class="stats-value">Rp <?php echo number_format($qris_paid); ?></div>
                            <div class="stats-label">QRIS (Lunas)</div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-icon pending-payment">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stats-value">Rp <?php echo number_format($pending_payment); ?></div>
                            <div class="stats-label">Menunggu Pembayaran</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Kolom pembayaran belum tersedia!</strong> Untuk menampilkan informasi pembayaran, pastikan kolom 'metode_pembayaran' dan 'status_pembayaran' sudah ada di tabel orders.
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Kode ini sudah otomatis menambahkan kolom yang diperlukan. Refresh halaman jika belum muncul.
                    </small>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Filter Card -->
            <div class="filter-card">
                <form method="get" id="filterForm" class="filter-form">
                    <input type="hidden" name="page" value="1">
                    
                    <div class="row filter-row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label filter-label">Tanggal Mulai (opsional)</label>
                            <div class="form-group-icon">
                                <i class="fas fa-calendar-alt"></i>
                                <input type="date" class="form-control" name="start_date" 
                                       value="<?php echo $start_date; ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label filter-label">Tanggal Akhir (opsional)</label>
                            <div class="form-group-icon">
                                <i class="fas fa-calendar-alt"></i>
                                <input type="date" class="form-control" name="end_date" 
                                       value="<?php echo $end_date; ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label filter-label">Status Order</label>
                            <div class="form-group-icon">
                                <i class="fas fa-tags"></i>
                                <select class="form-select" name="status">
                                    <option value="all" <?php echo (!isset($_GET['status']) || $_GET['status'] == 'all') ? 'selected' : ''; ?>>Semua Status</option>
                                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo (isset($_GET['status']) && $_GET['status'] == 'processing') ? 'selected' : ''; ?>>Diproses</option>
                                    <option value="completed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'completed') ? 'selected' : ''; ?>>Selesai</option>
                                    <option value="cancelled" <?php echo (isset($_GET['status']) && $_GET['status'] == 'cancelled') ? 'selected' : ''; ?>>Dibatalkan</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label filter-label">Cari</label>
                            <div class="form-group-icon">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Nama atau menu..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row filter-row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label filter-label">Metode Pembayaran</label>
                            <div class="form-group-icon">
                                <i class="fas fa-credit-card"></i>
                                <select class="form-select" name="payment_method" <?php echo !$has_metode ? 'disabled' : ''; ?>>
                                    <option value="all" <?php echo (!isset($_GET['payment_method']) || $_GET['payment_method'] == 'all') ? 'selected' : ''; ?>>Semua Metode</option>
                                    <option value="cash" <?php echo (isset($_GET['payment_method']) && $_GET['payment_method'] == 'cash') ? 'selected' : ''; ?>>Cash/Tunai</option>
                                    <option value="qris" <?php echo (isset($_GET['payment_method']) && $_GET['payment_method'] == 'qris') ? 'selected' : ''; ?>>QRIS</option>
                                </select>
                                <?php if(!$has_metode): ?>
                                <small class="column-info">Kolom belum tersedia</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label filter-label">Status Pembayaran</label>
                            <div class="form-group-icon">
                                <i class="fas fa-money-check-alt"></i>
                                <select class="form-select" name="payment_status" <?php echo !$has_status_pembayaran ? 'disabled' : ''; ?>>
                                    <option value="all" <?php echo (!isset($_GET['payment_status']) || $_GET['payment_status'] == 'all') ? 'selected' : ''; ?>>Semua Status</option>
                                    <option value="pending" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo (isset($_GET['payment_status']) && $_Get['payment_status'] == 'paid') ? 'selected' : ''; ?>>Lunas</option>
                                </select>
                                <?php if(!$has_status_pembayaran): ?>
                                <small class="column-info">Kolom belum tersedia</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3 d-flex align-items-end">
                            <button type="submit" class="search-btn w-100">
                                <i class="fas fa-filter me-2"></i>Filter Data
                            </button>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="button" class="export-btn" onclick="exportToExcel()">
                            <i class="fas fa-file-excel me-2"></i>Export Excel
                        </button>
                        <button type="button" class="refresh-btn" onclick="window.location.href='order.php'">
                            <i class="fas fa-redo me-2"></i>Refresh
                        </button>
                        <a href="order.php" class="btn-reset">
                            <i class="fas fa-times me-2"></i>Reset Filter
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Orders Table -->
            <div class="table-container">
                <?php if(mysqli_num_rows($orders) > 0): ?>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID Order</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Menu</th>
                                <th>Total Item</th>
                                <th>Total Harga</th>
                                <th>Status Order</th>
                                <?php if($has_metode): ?>
                                <th>Metode Bayar</th>
                                <?php endif; ?>
                                <?php if($has_status_pembayaran): ?>
                                <th>Status Bayar</th>
                                <?php endif; ?>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $order_counter = 0;
                            while($order = mysqli_fetch_assoc($orders)): 
                                $order_counter++;
                                $status = isset($order['status']) ? $order['status'] : 'pending';
                                $statusClass = 'status-' . $status;
                                $statusText = ucfirst($status);
                                
                                $metode_pembayaran = isset($order['metode_pembayaran']) ? $order['metode_pembayaran'] : 'cash';
                                $paymentMethodClass = 'payment-' . $metode_pembayaran;
                                $paymentMethodText = strtoupper($metode_pembayaran);
                                
                                $status_pembayaran = isset($order['status_pembayaran']) ? $order['status_pembayaran'] : 'pending';
                                $paymentStatusClass = 'payment-' . $status_pembayaran;
                                $paymentStatusText = $status_pembayaran == 'paid' ? 'Lunas' : 'Pending';
                                
                                // Parsing yang benar untuk menu_items
                                $menu_items = explode('||', $order['menu_items']);
                                $quantities = explode(',', $order['quantities']);
                                $prices = explode(',', $order['prices']);
                                
                                $item_count = $order['item_count'];
                                
                                // Pastikan jumlah array sama
                                $count = min(count($menu_items), count($quantities), count($prices));
                                
                                // Debug info
                                $virtual_session_id = $order['virtual_session_id'];
                                $real_session_id = isset($order['session_id']) ? $order['session_id'] : $virtual_session_id;
                            ?>
                            <tr>
                                <td>
                                    #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                                    <span class="badge-items"><?php echo $item_count; ?> item</span>
                                    <?php if($item_count > 1): ?>
                                    <span class="order-group-badge" title="Order Kelompok">Group</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($order['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($order['nama_pelanggan']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-info" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#itemsModal<?php echo $order['id']; ?>">
                                        <i class="fas fa-list"></i> Lihat <?php echo $item_count; ?> item
                                    </button>
                                </td>
                                <td><?php echo $item_count; ?></td>
                                <td>Rp <?php echo number_format($order['total']); ?></td>
                                <td>
                                    <span class="order-status <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <?php if($has_metode): ?>
                                <td>
                                    <span class="payment-method-badge <?php echo $paymentMethodClass; ?>">
                                        <?php echo $paymentMethodText; ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <?php if($has_status_pembayaran): ?>
                                <td>
                                    <span class="payment-status-badge <?php echo $paymentStatusClass; ?>">
                                        <?php echo $paymentStatusText; ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <div class="dropdown action-dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" 
                                                   data-bs-target="#detailModal<?php echo $order['id']; ?>">
                                                    <i class="fas fa-eye"></i>Detail Order
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" 
                                                   data-bs-target="#statusModal<?php echo $order['id']; ?>">
                                                    <i class="fas fa-sync-alt"></i>Ubah Status Order
                                                </a>
                                            </li>
                                            <?php if($has_status_pembayaran): ?>
                                            <li>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" 
                                                   data-bs-target="#paymentModal<?php echo $order['id']; ?>">
                                                    <i class="fas fa-money-check-alt"></i>Ubah Status Bayar
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" 
                                                   href="?<?php echo http_build_query(array_merge($_GET, ['hapus' => $virtual_session_id])); ?>" 
                                                   onclick="return confirm('Yakin ingin menghapus seluruh order ini (<?php echo $item_count; ?> item)?')">
                                                    <i class="fas fa-trash"></i>Hapus Semua
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Items Modal -->
                                    <div class="modal fade" id="itemsModal<?php echo $order['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        Detail Item Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                                                        <small class="text-muted ms-2"><?php echo $item_count; ?> item</small>
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="order-group-header">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <strong>Pelanggan:</strong> <?php echo htmlspecialchars($order['nama_pelanggan']); ?>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <strong>Tanggal:</strong> <?php echo date('d F Y H:i:s', strtotime($order['created_at'])); ?>
                                                            </div>
                                                        </div>
                                                        <div class="row mt-2">
                                                            <div class="col-md-6">
                                                                <strong>Status Order:</strong> 
                                                                <span class="order-status <?php echo $statusClass; ?>">
                                                                    <?php echo $statusText; ?>
                                                                </span>
                                                            </div>
                                                            <?php if($has_metode): ?>
                                                            <div class="col-md-6">
                                                                <strong>Metode Bayar:</strong> 
                                                                <span class="payment-method-badge <?php echo $paymentMethodClass; ?>">
                                                                    <?php echo $paymentMethodText; ?>
                                                                </span>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if($has_status_pembayaran): ?>
                                                        <div class="row mt-2">
                                                            <div class="col-md-12">
                                                                <strong>Status Bayar:</strong> 
                                                                <span class="payment-status-badge <?php echo $paymentStatusClass; ?>">
                                                                    <?php echo $paymentStatusText; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                        <div class="modal-debug">
                                                            Session ID: <?php echo substr($real_session_id, 0, 30) . (strlen($real_session_id) > 30 ? '...' : ''); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <h6>Daftar Item:</h6>
                                                    <div class="item-list">
                                                        <?php 
                                                        // Query yang benar untuk mengambil item berdasarkan session_id
                                                        if (in_array('session_id', $columns) && isset($order['session_id']) && !empty($order['session_id'])) {
                                                            // Jika ada session_id, gunakan itu
                                                            $items_query = "SELECT * FROM orders 
                                                                            WHERE session_id = '" . mysqli_real_escape_string($conn, $order['session_id']) . "'
                                                                            ORDER BY id";
                                                        } else {
                                                            // Fallback ke virtual_session_id
                                                            $items_query = "SELECT * FROM orders 
                                                                            WHERE $group_condition = '$virtual_session_id'
                                                                            ORDER BY id";
                                                        }
                                                        
                                                        $items_result = mysqli_query($conn, $items_query);
                                                        
                                                        if ($items_result && mysqli_num_rows($items_result) > 0):
                                                            while($item = mysqli_fetch_assoc($items_result)):
                                                        ?>
                                                        <div class="item-row">
                                                            <div class="item-name">
                                                                <?php echo htmlspecialchars($item['menu']); ?>
                                                                <?php if(!empty($item['catatan'])): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($item['catatan']); ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="item-qty"><?php echo $item['jumlah']; ?> porsi</div>
                                                            <div class="item-price">Rp <?php echo number_format($item['harga']); ?></div>
                                                            <div class="item-total">Rp <?php echo number_format($item['total']); ?></div>
                                                            <div class="item-remove" 
                                                                 onclick="if(confirm('Hapus item ini dari order?')) { 
                                                                     window.location.href='?<?php echo http_build_query(array_merge($_GET, ['hapus_item' => $item['id']])); ?>'
                                                                 }">
                                                                <i class="fas fa-times"></i>
                                                            </div>
                                                        </div>
                                                        <?php 
                                                                endwhile;
                                                            else:
                                                        ?>
                                                        <div class="text-center p-3">
                                                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                                            Tidak dapat mengambil detail item.
                                                        </div>
                                                        <?php 
                                                            endif;
                                                        ?>
                                                    </div>
                                                    
                                                    <div class="mt-3 p-3 bg-light rounded">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <strong>Subtotal:</strong>
                                                            </div>
                                                            <div class="col-md-6 text-end">
                                                                <strong>Rp <?php echo number_format($order['total']); ?></strong>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                    <button type="button" class="btn btn-primary" onclick="printOrder('<?php echo $order['id']; ?>')">
                                                        <i class="fas fa-print me-2"></i>Cetak Invoice
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Detail Modal -->
                                    <div class="modal fade" id="detailModal<?php echo $order['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Detail Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6>Informasi Pelanggan</h6>
                                                            <table class="table table-sm">
                                                                <tr>
                                                                    <td><strong>Nama</strong></td>
                                                                    <td><?php echo htmlspecialchars($order['nama_pelanggan']); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Tanggal Order</strong></td>
                                                                    <td><?php echo date('d F Y H:i:s', strtotime($order['created_at'])); ?></td>
                                                                </tr>
                                                                <?php if($has_metode): ?>
                                                                <tr>
                                                                    <td><strong>Metode Bayar</strong></td>
                                                                    <td>
                                                                        <span class="payment-method-badge <?php echo $paymentMethodClass; ?>">
                                                                            <?php echo $paymentMethodText; ?>
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                                <?php endif; ?>
                                                                <?php if($has_status_pembayaran): ?>
                                                                <tr>
                                                                    <td><strong>Status Bayar</strong></td>
                                                                    <td>
                                                                        <span class="payment-status-badge <?php echo $paymentStatusClass; ?>">
                                                                            <?php echo $paymentStatusText; ?>
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                                <?php endif; ?>
                                                                <tr>
                                                                    <td><strong>Jumlah Item</strong></td>
                                                                    <td><?php echo $item_count; ?> item</td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Session ID</strong></td>
                                                                    <td><small><?php echo substr($real_session_id, 0, 30) . (strlen($real_session_id) > 30 ? '...' : ''); ?></small></td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Ringkasan Pesanan</h6>
                                                            <table class="table table-sm">
                                                                <tr>
                                                                    <td><strong>Total Harga</strong></td>
                                                                    <td class="fw-bold text-primary">Rp <?php echo number_format($order['total']); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Status Order</strong></td>
                                                                    <td>
                                                                        <span class="order-status <?php echo $statusClass; ?>">
                                                                            <?php echo $statusText; ?>
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Tipe Order</strong></td>
                                                                    <td>
                                                                        <?php if($item_count > 1): ?>
                                                                        <span class="badge bg-info">Order Kelompok</span>
                                                                        <?php else: ?>
                                                                        <span class="badge bg-secondary">Single Order</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mt-3">
                                                        <div class="col-12">
                                                            <h6>Daftar Menu:</h6>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Menu</th>
                                                                            <th>Jumlah</th>
                                                                            <th>Harga Satuan</th>
                                                                            <th>Subtotal</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php
                                                                        for($i = 0; $i < $count; $i++):
                                                                            $subtotal = $quantities[$i] * $prices[$i];
                                                                        ?>
                                                                        <tr>
                                                                            <td><?php echo htmlspecialchars($menu_items[$i]); ?></td>
                                                                            <td><?php echo $quantities[$i]; ?></td>
                                                                            <td>Rp <?php echo number_format($prices[$i]); ?></td>
                                                                            <td>Rp <?php echo number_format($subtotal); ?></td>
                                                                        </tr>
                                                                        <?php endfor; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php 
                                                    // Parsing catatan
                                                    $catatan_array = explode('||', $order['catatan']);
                                                    $catatan_text = '';
                                                    foreach($catatan_array as $cat) {
                                                        if (!empty(trim($cat))) {
                                                            $catatan_text .= $cat . "\n";
                                                        }
                                                    }
                                                    
                                                    if(!empty(trim($catatan_text))): 
                                                    ?>
                                                    <div class="row mt-3">
                                                        <div class="col-12">
                                                            <h6>Catatan:</h6>
                                                            <div class="alert alert-light">
                                                                <?php echo nl2br(htmlspecialchars($catatan_text)); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                    <button type="button" class="btn btn-primary" onclick="printOrder('<?php echo $order['id']; ?>')">
                                                        <i class="fas fa-print me-2"></i>Cetak Invoice
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Status Modal -->
                                    <div class="modal fade" id="statusModal<?php echo $order['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="post">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Ubah Status Order</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="session_id" value="<?php echo $real_session_id; ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">Status Saat Ini</label>
                                                            <div class="form-control">
                                                                <span class="order-status <?php echo $statusClass; ?>">
                                                                    <?php echo $statusText; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Status Baru</label>
                                                            <select class="form-select" name="status" required>
                                                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="processing" <?php echo $status == 'processing' ? 'selected' : ''; ?>>Diproses</option>
                                                                <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Selesai</option>
                                                                <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                                                            </select>
                                                        </div>
                                                        <div class="alert alert-info">
                                                            <small>
                                                                <i class="fas fa-info-circle me-2"></i>
                                                                Status akan diubah untuk semua <?php echo $item_count; ?> item dalam order ini.
                                                            </small>
                                                        </div>
                                                        <!-- Debug Info -->
                                                        <div class="debug-info">
                                                            <small>
                                                                <i class="fas fa-bug me-1"></i>
                                                                Session ID: <?php echo substr($real_session_id, 0, 30) . (strlen($real_session_id) > 30 ? '...' : ''); ?><br>
                                                                Order ID: #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?><br>
                                                                Jumlah Item: <?php echo $item_count; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="update_status" class="btn btn-primary">
                                                            <i class="fas fa-save me-2"></i>Simpan Perubahan
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Payment Status Modal -->
                                    <?php if($has_status_pembayaran): ?>
                                    <div class="modal fade" id="paymentModal<?php echo $order['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="post">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Ubah Status Pembayaran</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="session_id" value="<?php echo $real_session_id; ?>">
                                                        <?php if($has_metode): ?>
                                                        <div class="mb-3">
                                                            <label class="form-label">Metode Pembayaran</label>
                                                            <div class="form-control">
                                                                <span class="payment-method-badge <?php echo $paymentMethodClass; ?>">
                                                                    <?php echo $paymentMethodText; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                        <div class="mb-3">
                                                            <label class="form-label">Status Pembayaran Saat Ini</label>
                                                            <div class="form-control">
                                                                <span class="payment-status-badge <?php echo $paymentStatusClass; ?>">
                                                                    <?php echo $paymentStatusText; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Status Pembayaran Baru</label>
                                                            <select class="form-select" name="payment_status" required>
                                                                <option value="pending" <?php echo $status_pembayaran == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="paid" <?php echo $status_pembayaran == 'paid' ? 'selected' : ''; ?>>Lunas</option>
                                                            </select>
                                                        </div>
                                                        <div class="alert alert-info">
                                                            <small>
                                                                <i class="fas fa-info-circle me-2"></i>
                                                                Status pembayaran akan diubah untuk semua <?php echo $item_count; ?> item dalam order ini.
                                                            </small>
                                                        </div>
                                                        <!-- Debug Info -->
                                                        <div class="debug-info">
                                                            <small>
                                                                <i class="fas fa-bug me-1"></i>
                                                                Session ID: <?php echo substr($real_session_id, 0, 30) . (strlen($real_session_id) > 30 ? '...' : ''); ?><br>
                                                                Order ID: #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?><br>
                                                                Jumlah Item: <?php echo $item_count; ?><br>
                                                                Total: Rp <?php echo number_format($order['total']); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="update_payment_status" class="btn btn-primary">
                                                            <i class="fas fa-save me-2"></i>Simpan Perubahan
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination (only if needed) -->
                <?php if($total_pages > 1): ?>
                <div style="padding: 20px; border-top: 1px solid #dee2e6;">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i> Sebelumnya
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php
                            // Tampilkan maksimal 5 halaman
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $start_page + 4);
                            
                            if($start_page > 1) {
                                $start_page = max(1, $end_page - 4);
                            }
                            
                            for($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    Selanjutnya <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <h4>Tidak ada order</h4>
                    <p>Tidak ditemukan order dengan filter yang dipilih.</p>
                    <a href="order.php" class="btn btn-admin-primary mt-2">
                        <i class="fas fa-redo me-2"></i>Tampilkan Semua Order
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar Toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
        
        // Export to Excel function
        function exportToExcel() {
            // Get filtered data
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            const status = document.querySelector('select[name="status"]').value;
            const paymentMethod = document.querySelector('select[name="payment_method"]').value;
            const paymentStatus = document.querySelector('select[name="payment_status"]').value;
            const search = document.querySelector('input[name="search"]').value;
            
            // Create export URL
            let exportUrl = 'export.php?export=excel';
            exportUrl += '&start_date=' + startDate;
            exportUrl += '&end_date=' + endDate;
            exportUrl += '&status=' + status;
            exportUrl += '&payment_method=' + paymentMethod;
            exportUrl += '&payment_status=' + paymentStatus;
            exportUrl += '&search=' + encodeURIComponent(search);
            
            // Download file
            window.location.href = exportUrl;
        }
        
        // Date validation
        const filterForm = document.getElementById('filterForm');
        const formInputs = filterForm.querySelectorAll('input, select');
        
        // Auto-submit when any filter changes
        formInputs.forEach(input => {
            if (input.type !== 'submit') {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
            }
        });
        
        // For search input, add delay to prevent too many requests
        let searchTimeout;
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    filterForm.submit();
                }, 500); // 0.5 second delay
            });
        }
        
        // Date validation
        filterForm.addEventListener('submit', function(e) {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            
            if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
                e.preventDefault();
                alert('Tanggal mulai tidak boleh lebih besar dari tanggal akhir!');
                return false;
            }
            
            return true;
        });
        
        // Print order function
        function printOrder(orderId) {
            window.open('print_order.php?id=' + orderId, '_blank');
        }
        
        // Auto-refresh page every 60 seconds to check for new orders
        setInterval(function() {
            // Check if there are any filters active
            const hasFilters = document.querySelector('input[name="start_date"]').value !== '' || 
                              document.querySelector('input[name="end_date"]').value !== '' ||
                              document.querySelector('select[name="status"]').value !== 'all' ||
                              document.querySelector('select[name="payment_method"]').value !== 'all' ||
                              document.querySelector('select[name="payment_status"]').value !== 'all' ||
                              document.querySelector('input[name="search"]').value !== '';
            
            if (!hasFilters) {
                location.reload();
            }
        }, 60000);
        
        // Debug: Show session ID when clicking update status
        document.addEventListener('DOMContentLoaded', function() {
            const statusModals = document.querySelectorAll('[id^="statusModal"]');
            statusModals.forEach(modal => {
                modal.addEventListener('show.bs.modal', function(event) {
                    const sessionId = this.querySelector('input[name="session_id"]').value;
                    console.log('Session ID for update:', sessionId);
                });
            });
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Quick status update functions
        function quickUpdateStatus(orderId, status) {
            if (confirm('Ubah status order ini menjadi "' + status + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const sessionIdInput = document.createElement('input');
                sessionIdInput.type = 'hidden';
                sessionIdInput.name = 'session_id';
                sessionIdInput.value = orderId;
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status';
                statusInput.value = status;
                
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'update_status';
                submitInput.value = '1';
                
                form.appendChild(sessionIdInput);
                form.appendChild(statusInput);
                form.appendChild(submitInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function quickUpdatePaymentStatus(orderId, status) {
            if (confirm('Ubah status pembayaran ini menjadi "' + (status === 'paid' ? 'Lunas' : 'Pending') + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const sessionIdInput = document.createElement('input');
                sessionIdInput.type = 'hidden';
                sessionIdInput.name = 'session_id';
                sessionIdInput.value = orderId;
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'payment_status';
                statusInput.value = status;
                
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'update_payment_status';
                submitInput.value = '1';
                
                form.appendChild(sessionIdInput);
                form.appendChild(statusInput);
                form.appendChild(submitInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>