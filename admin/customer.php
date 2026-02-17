<?php
session_start();
include '../config/database.php';

// Cek session admin
if (!isset($_SESSION['admin'])) { 
    header("Location: login.php"); 
    exit(); 
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'total_duit_desc';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build WHERE conditions array
$whereConditions = array();

// Add search filter
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $whereConditions[] = "nama_pelanggan LIKE '%$search%'";
}

// Check table structure for date column
$checkTable = mysqli_query($conn, "SHOW COLUMNS FROM orders");
$columns = array();
while($col = mysqli_fetch_assoc($checkTable)) {
    $columns[] = $col['Field'];
}

// Determine date column name
$dateColumn = 'tanggal';
if (in_array('tanggal_order', $columns)) {
    $dateColumn = 'tanggal_order';
} elseif (in_array('order_date', $columns)) {
    $dateColumn = 'order_date';
} elseif (in_array('date', $columns)) {
    $dateColumn = 'date';
} elseif (in_array('created_at', $columns)) {
    $dateColumn = 'created_at';
}

// Add date filter
if (!empty($start_date) && !empty($end_date)) {
    $start_date = mysqli_real_escape_string($conn, $start_date);
    $end_date = mysqli_real_escape_string($conn, $end_date);
    $whereConditions[] = "$dateColumn BETWEEN '$start_date' AND '$end_date 23:59:59'";
} elseif (!empty($start_date)) {
    $start_date = mysqli_real_escape_string($conn, $start_date);
    $whereConditions[] = "$dateColumn >= '$start_date'";
} elseif (!empty($end_date)) {
    $end_date = mysqli_real_escape_string($conn, $end_date);
    $whereConditions[] = "$dateColumn <= '$end_date 23:59:59'";
}

// Build WHERE clause
$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Build ORDER BY clause
$orderClause = 'ORDER BY total_duit DESC';
switch ($sort) {
    case 'name_asc':
        $orderClause = 'ORDER BY nama_pelanggan ASC';
        break;
    case 'name_desc':
        $orderClause = 'ORDER BY nama_pelanggan DESC';
        break;
    case 'orders_asc':
        $orderClause = 'ORDER BY total_order ASC';
        break;
    case 'orders_desc':
        $orderClause = 'ORDER BY total_order DESC';
        break;
    case 'total_asc':
        $orderClause = 'ORDER BY total_duit ASC';
        break;
    case 'total_desc':
        $orderClause = 'ORDER BY total_duit DESC';
        break;
}

// Query customer data with filters
$query = "
    SELECT 
        nama_pelanggan, 
        COUNT(*) as total_order, 
        SUM(total) as total_duit
";

// Add last_order column if date column exists
if (in_array($dateColumn, $columns)) {
    $query .= ", MAX($dateColumn) as last_order";
}

$query .= "
    FROM orders 
    $whereClause
    GROUP BY nama_pelanggan 
    $orderClause
";

$customers = mysqli_query($conn, $query);

if (!$customers) {
    die("Query error: " . mysqli_error($conn));
}

// Get statistics - use the same WHERE clause
$statsQuery = mysqli_query($conn, "
    SELECT 
        COUNT(DISTINCT nama_pelanggan) as total_customers,
        SUM(total) as total_revenue
    FROM orders 
    $whereClause
");

$stats = $statsQuery ? mysqli_fetch_assoc($statsQuery) : array('total_customers' => 0, 'total_revenue' => 0);

// Get total orders
$totalOrdersQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders $whereClause");
$totalOrders = $totalOrdersQuery ? mysqli_fetch_assoc($totalOrdersQuery) : array('total' => 0);

// Get top customer
$topCustomerQuery = mysqli_query($conn, "
    SELECT nama_pelanggan, SUM(total) as total_spent 
    FROM orders 
    $whereClause
    GROUP BY nama_pelanggan 
    ORDER BY total_spent DESC 
    LIMIT 1
");
$topCustomer = $topCustomerQuery ? mysqli_fetch_assoc($topCustomerQuery) : array('nama_pelanggan' => '-', 'total_spent' => 0);

// Check if export is requested
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Set headers for Excel download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Data_Pelanggan_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    // Generate Excel content
    $excelData = "Data Pelanggan - Restoran Kita\n\n";
    $excelData .= "Tanggal Export: " . date('d F Y H:i:s') . "\n";
    
    if (!empty($search) || !empty($start_date) || !empty($end_date)) {
        $excelData .= "Filter Aktif: ";
        $filters = [];
        if (!empty($search)) $filters[] = "Pencarian: \"$search\"";
        if (!empty($start_date)) $filters[] = "Dari: $start_date";
        if (!empty($end_date)) $filters[] = "Sampai: $end_date";
        $excelData .= implode(', ', $filters) . "\n";
    }
    
    $excelData .= "\n";
    $excelData .= "No\tNama Pelanggan\tJumlah Pesanan\tTotal Transaksi\tStatus\tKategori\n";
    
    $counter = 1;
    mysqli_data_seek($customers, 0); // Reset pointer
    while($row = mysqli_fetch_assoc($customers)) {
        $category = $row['total_duit'] > 500000 ? 'Premium' : 'Regular';
        $status = '';
        if ($row['total_duit'] > 1000000) {
            $status = 'Pelanggan Setia';
        } elseif ($row['total_duit'] > 500000) {
            $status = 'Pelanggan Aktif';
        } else {
            $status = 'Pelanggan Baru';
        }
        
        $excelData .= $counter . "\t" . 
                      $row['nama_pelanggan'] . "\t" . 
                      $row['total_order'] . "\t" . 
                      "Rp " . number_format($row['total_duit']) . "\t" . 
                      $status . "\t" . 
                      $category . "\n";
        $counter++;
    }
    
    $excelData .= "\n\nSTATISTIK:\n";
    $excelData .= "Total Pelanggan: " . number_format($stats['total_customers']) . "\n";
    $excelData .= "Total Pesanan: " . number_format($totalOrders['total']) . "\n";
    $excelData .= "Total Pendapatan: Rp " . number_format($stats['total_revenue']) . "\n";
    $excelData .= "Pelanggan Teratas: " . $topCustomer['nama_pelanggan'] . " (Rp " . number_format($topCustomer['total_spent']) . ")\n";
    
    // Output Excel content
    echo $excelData;
    exit();
}

// Reset pointer for display
mysqli_data_seek($customers, 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pelanggan - Restoran Kita</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
        }
        
        .admin-body {
            display: flex;
            min-height: 100vh;
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--primary-color) 0%, #1a2530 100%);
            color: white;
            position: fixed;
            height: 100vh;
            box-shadow: 3px 0 15px rgba(0,0,0,0.1);
            z-index: 100;
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 20px 15px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background-color: rgba(0,0,0,0.2);
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .sidebar-menu ul {
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin: 5px 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .sidebar-menu a:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--secondary-color);
        }
        
        .sidebar-menu a.active {
            background-color: rgba(52, 152, 219, 0.2);
            color: white;
            border-left-color: var(--secondary-color);
        }
        
        .sidebar-menu i {
            width: 30px;
            font-size: 1.1rem;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 0;
            width: calc(100% - 250px);
        }
        
        .admin-header {
            background-color: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-bottom: 1px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-header h4 {
            margin: 0;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .content-wrapper {
            padding: 30px;
        }
        
        /* Stat Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            border: 1px solid #eaeaea;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.08);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .stat-icon.customer {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }
        
        .stat-icon.order {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }
        
        .stat-icon.revenue {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--accent-color);
        }
        
        .stat-icon.top-customer {
            background-color: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Search & Filter */
        .search-filter-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            border: 1px solid #eaeaea;
        }
        
        .filter-section-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .search-box input {
            padding-left: 45px;
            border-radius: 8px;
            border: 1px solid #ddd;
            height: 45px;
            width: 100%;
        }
        
        .filter-select {
            height: 45px;
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 0 15px;
            width: 100%;
        }
        
        .date-input {
            height: 45px;
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 0 15px;
            width: 100%;
        }
        
        .date-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .date-input-group .form-control {
            flex: 1;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .btn-filter {
            height: 45px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 45px;
        }
        
        .btn-reset {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            color: #6c757d;
        }
        
        .btn-reset:hover {
            background-color: #e9ecef;
            color: var(--dark-color);
        }
        
        /* Table Container */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #eaeaea;
        }
        
        .table-header {
            padding: 20px 25px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h5 {
            margin: 0;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            color: var(--primary-color);
            font-weight: 600;
            padding: 15px 20px;
            white-space: nowrap;
        }
        
        .table tbody tr {
            transition: all 0.2s;
        }
        
        .table tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .table tbody td {
            padding: 15px 20px;
            vertical-align: middle;
            border-bottom: 1px solid #eaeaea;
        }
        
        .customer-name {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .order-count {
            display: inline-block;
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .total-amount {
            font-weight: 700;
            color: var(--success-color);
        }
        
        .last-order {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .badge-premium {
            background-color: #ffd700;
            color: #8a6d00;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .badge-regular {
            background-color: #e9ecef;
            color: #495057;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        .active-filters {
            background-color: #e7f3ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid var(--secondary-color);
        }
        
        .filter-tag {
            display: inline-flex;
            align-items: center;
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            padding: 5px 12px;
            margin-right: 8px;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }
        
        .filter-tag i {
            margin-right: 5px;
            color: #6c757d;
        }
        
        .filter-tag .remove-filter {
            margin-left: 8px;
            color: #dc3545;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .filter-tag .remove-filter:hover {
            color: #b02a37;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h3,
            .sidebar-menu span {
                display: none;
            }
            
            .sidebar-menu a {
                justify-content: center;
                padding: 15px 0;
            }
            
            .sidebar-menu i {
                width: auto;
                font-size: 1.3rem;
            }
            
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .search-filter-container .row > div {
                margin-bottom: 15px;
            }
            
            .filter-buttons {
                flex-direction: column;
            }
        }
        
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 15px;
            }
            
            .admin-header {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .stat-card {
                margin-bottom: 15px;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .date-input-group {
                flex-direction: column;
            }
        }
        
        @media (max-width: 576px) {
            .filter-buttons {
                flex-direction: row;
                justify-content: space-between;
            }
            
            .btn-filter, .btn-reset {
                flex: 1;
            }
        }
        
        /* Print Styles */
        @media print {
            .sidebar, .search-filter-container, .filter-buttons, .active-filters, .btn-success {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            
            .table-container {
                box-shadow: none !important;
                border: 1px solid #000 !important;
            }
        }
    </style>
</head>
<body class="admin-body">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-crown me-2"></i>Admin Panel</h3>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                <li><a href="menu.php"><i class="fas fa-utensils"></i><span>Kelola Menu</span></a></li>
                <li><a href="order.php"><i class="fas fa-shopping-cart"></i><span>Data Order</span></a></li>
                <li><a href="customer.php" class="active"><i class="fas fa-users"></i><span>Pelanggan</span></a></li>
                <li><a href="report.php"><i class="fas fa-chart-bar"></i><span>Laporan</span></a></li>
                <li>
                    <a href="../index.php" target="_blank">
                        <i class="fas fa-eye"></i>
                        <span>Lihat Website Utama</span>
                    </a>
                </li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <header class="admin-header">
            <h4><i class="fas fa-users me-2"></i>Data Pelanggan</h4>
            <div class="d-flex align-items-center">
                <span class="me-3 text-muted">
                    <i class="fas fa-calendar-alt me-1"></i>
                    <?php echo date('d F Y'); ?>
                </span>
            </div>
        </header>
        
        <div class="content-wrapper">
            <!-- Statistik Cards -->
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon customer">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['total_customers']); ?></div>
                        <div class="stat-label">Total Pelanggan</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon revenue">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-value">Rp <?php echo number_format($stats['total_revenue']); ?></div>
                        <div class="stat-label">Total Pendapatan</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon order">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($totalOrders['total']); ?></div>
                        <div class="stat-label">Total Pesanan</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon top-customer">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div class="stat-value"><?php echo htmlspecialchars($topCustomer['nama_pelanggan']); ?></div>
                        <div class="stat-label">Top Pelanggan (Rp <?php echo number_format($topCustomer['total_spent']); ?>)</div>
                    </div>
                </div>
            </div>
            
            <!-- Active Filters -->
            <?php if(!empty($search) || !empty($start_date) || !empty($end_date)): ?>
            <div class="active-filters">
                <div class="filter-section-title">Filter Aktif:</div>
                <?php if(!empty($search)): ?>
                    <span class="filter-tag">
                        <i class="fas fa-search"></i>
                        Pencarian: "<?php echo htmlspecialchars($search); ?>"
                        <span class="remove-filter" onclick="removeFilter('search')">
                            <i class="fas fa-times"></i>
                        </span>
                    </span>
                <?php endif; ?>
                
                <?php if(!empty($start_date)): ?>
                    <span class="filter-tag">
                        <i class="fas fa-calendar"></i>
                        Dari: <?php echo htmlspecialchars($start_date); ?>
                        <span class="remove-filter" onclick="removeFilter('start_date')">
                            <i class="fas fa-times"></i>
                        </span>
                    </span>
                <?php endif; ?>
                
                <?php if(!empty($end_date)): ?>
                    <span class="filter-tag">
                        <i class="fas fa-calendar"></i>
                        Sampai: <?php echo htmlspecialchars($end_date); ?>
                        <span class="remove-filter" onclick="removeFilter('end_date')">
                            <i class="fas fa-times"></i>
                        </span>
                    </span>
                <?php endif; ?>
                
                <span class="filter-tag" style="background-color: #fff3cd; border-color: #ffc107;">
                    <i class="fas fa-filter"></i>
                    <?php echo mysqli_num_rows($customers); ?> Data Ditemukan
                    <span class="remove-filter" onclick="resetAllFilters()">
                        <i class="fas fa-times"></i> Hapus Semua
                    </span>
                </span>
            </div>
            <?php endif; ?>
            
            <!-- Search and Filter -->
            <div class="search-filter-container">
                <form method="GET" action="" class="row g-3" id="filterForm">
                    <div class="col-md-3">
                        <div class="filter-section-title">Cari Pelanggan</div>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control" name="search" placeholder="Nama pelanggan..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="filter-section-title">Filter Tanggal</div>
                        <div class="date-input-group">
                            <input type="text" class="form-control date-input" id="start_date" name="start_date" placeholder="Dari tanggal" value="<?php echo htmlspecialchars($start_date); ?>" autocomplete="off">
                            <span class="text-muted">s/d</span>
                            <input type="text" class="form-control date-input" id="end_date" name="end_date" placeholder="Sampai tanggal" value="<?php echo htmlspecialchars($end_date); ?>" autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="filter-section-title">Urutkan</div>
                        <select class="form-select filter-select" name="sort">
                            <option value="total_desc" <?php echo $sort == 'total_desc' ? 'selected' : ''; ?>>Total Tertinggi</option>
                            <option value="total_asc" <?php echo $sort == 'total_asc' ? 'selected' : ''; ?>>Total Terendah</option>
                            <option value="orders_desc" <?php echo $sort == 'orders_desc' ? 'selected' : ''; ?>>Pesanan Terbanyak</option>
                            <option value="orders_asc" <?php echo $sort == 'orders_asc' ? 'selected' : ''; ?>>Pesanan Tersedikit</option>
                            <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Nama A-Z</option>
                            <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Nama Z-A</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="filter-section-title">&nbsp;</div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary btn-filter">
                                <i class="fas fa-filter me-2"></i> Terapkan Filter
                            </button>
                            <button type="button" class="btn btn-reset btn-filter" onclick="resetAllFilters()">
                                <i class="fas fa-redo me-2"></i> Reset
                            </button>
                            <a href="?<?php 
                                $params = $_GET;
                                $params['export'] = 'excel';
                                echo http_build_query($params);
                            ?>" class="btn btn-success btn-filter" onclick="showExportLoading()">
                                <i class="fas fa-file-excel me-2"></i> Export Excel
                            </a>
                            <button type="button" class="btn btn-secondary btn-filter" onclick="window.print()">
                                <i class="fas fa-print me-2"></i> Print
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Table Container -->
            <div class="table-container">
                <div class="table-header">
                    <h5><i class="fas fa-list me-2"></i>Daftar Pelanggan</h5>
                    <div>
                        <span class="text-muted me-3">
                            Total <?php echo mysqli_num_rows($customers); ?> pelanggan
                        </span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" id="customerTable">
                        <thead>
                            <tr>
                                <th width="35%">Nama Pelanggan</th>
                                <th width="20%">Jumlah Pesanan</th>
                                <th width="25%">Total Transaksi</th>
                                <th width="20%">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($customers) > 0): ?>
                                <?php 
                                $counter = 0;
                                while($c = mysqli_fetch_assoc($customers)): 
                                    $counter++;
                                    $isPremium = $c['total_duit'] > 500000;
                                    $hasLastOrder = isset($c['last_order']) && !empty($c['last_order']);
                                ?>
                                <tr>
                                    <td>
                                        <div class="customer-name">
                                            <?php echo htmlspecialchars($c['nama_pelanggan']); ?>
                                            <?php if($isPremium): ?>
                                                <span class="badge-premium">Premium</span>
                                            <?php else: ?>
                                                <span class="badge-regular">Regular</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if($hasLastOrder): ?>
                                        <div class="last-order">
                                            <i class="far fa-clock me-1"></i>
                                            Terakhir pesan: <?php echo date('d/m/Y', strtotime($c['last_order'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="order-count">
                                            <?php echo $c['total_order']; ?> Pesanan
                                        </span>
                                    </td>
                                    <td class="total-amount">
                                        <i class="fas fa-wallet me-1"></i>
                                        Rp <?php echo number_format($c['total_duit']); ?>
                                    </td>
                                    <td>
                                        <?php if($c['total_duit'] > 1000000): ?>
                                            <span class="badge bg-success">Pelanggan Setia</span>
                                        <?php elseif($c['total_duit'] > 500000): ?>
                                            <span class="badge bg-primary">Pelanggan Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pelanggan Baru</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <i class="fas fa-user-slash"></i>
                                            <p>Tidak ada data pelanggan ditemukan</p>
                                            <?php if(!empty($search) || !empty($start_date) || !empty($end_date)): ?>
                                                <a href="customer.php" class="btn btn-outline-primary">Tampilkan Semua Data</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Info Footer -->
            <div class="mt-4 text-center text-muted">
                <small>
                    <i class="fas fa-info-circle me-1"></i>
                    Data pelanggan diambil dari riwayat pesanan. Pelanggan dengan total transaksi > Rp 1.000.000 dikategorikan sebagai "Pelanggan Setia".
                </small>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay for Export -->
    <div id="exportLoading" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; color: white; font-size: 24px; text-align: center; padding-top: 20%;">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3">Menyiapkan file Excel...</p>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
    <script>
        // Initialize date pickers
        flatpickr("#start_date", {
            dateFormat: "Y-m-d",
            locale: "id",
            allowInput: true,
            maxDate: "today"
        });
        
        flatpickr("#end_date", {
            dateFormat: "Y-m-d",
            locale: "id",
            allowInput: true,
            maxDate: "today"
        });
        
        // Sidebar toggle for mobile
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            
            function adjustSidebar() {
                if (window.innerWidth < 992) {
                    sidebar.style.width = '70px';
                    mainContent.style.marginLeft = '70px';
                    mainContent.style.width = 'calc(100% - 70px)';
                } else {
                    sidebar.style.width = '250px';
                    mainContent.style.marginLeft = '250px';
                    mainContent.style.width = 'calc(100% - 250px)';
                }
            }
            
            window.addEventListener('resize', adjustSidebar);
            adjustSidebar();
        });
        
        // Remove individual filter
        function removeFilter(filterName) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filterName);
            window.location.href = url.toString();
        }
        
        // Reset all filters
        function resetAllFilters() {
            window.location.href = 'customer.php';
        }
        
        // Show loading when exporting
        function showExportLoading() {
            document.getElementById('exportLoading').style.display = 'block';
            setTimeout(() => {
                document.getElementById('exportLoading').style.display = 'none';
            }, 3000);
        }
        
        // Validate date range
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                if (start > end) {
                    alert('Tanggal awal tidak boleh lebih besar dari tanggal akhir!');
                    e.preventDefault();
                    return false;
                }
            }
            return true;
        });
        
        // Alternative export method using HTML table (works offline)
        function exportToExcelAlternative() {
            // Create a temporary table for export
            const table = document.getElementById('customerTable');
            if (!table) {
                alert('Tabel tidak ditemukan!');
                return;
            }
            
            const rows = table.querySelectorAll('tbody tr');
            if (rows.length === 0 || rows[0].querySelector('.empty-state')) {
                alert('Tidak ada data untuk diexport!');
                return;
            }
            
            // Create HTML table string
            let html = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="UTF-8">
                <title>Data Pelanggan</title>
                <!--[if gte mso 9]>
                <xml>
                    <x:ExcelWorkbook>
                        <x:ExcelWorksheets>
                            <x:ExcelWorksheet>
                                <x:Name>Data Pelanggan</x:Name>
                                <x:WorksheetOptions>
                                    <x:DisplayGridlines/>
                                </x:WorksheetOptions>
                            </x:ExcelWorksheet>
                        </x:ExcelWorksheets>
                    </x:ExcelWorkbook>
                </xml>
                <![endif]-->
                <style>
                    table { border-collapse: collapse; width: 100%; }
                    th { background-color: #f2f2f2; font-weight: bold; padding: 8px; border: 1px solid #ddd; }
                    td { padding: 8px; border: 1px solid #ddd; }
                    .text-right { text-align: right; }
                    .text-center { text-align: center; }
                </style>
            </head>
            <body>
                <h2>Data Pelanggan - Restoran Kita</h2>
                <p>Tanggal Export: ${new Date().toLocaleDateString('id-ID')}</p>
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Pelanggan</th>
                            <th>Jumlah Pesanan</th>
                            <th>Total Transaksi</th>
                            <th>Status</th>
                            <th>Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            let rowNum = 1;
            rows.forEach(row => {
                if (row.querySelector('.empty-state')) return;
                
                const cells = row.querySelectorAll('td');
                const nameText = cells[0].querySelector('.customer-name').textContent.trim();
                const name = nameText.replace('Premium', '').replace('Regular', '').trim();
                const isPremium = nameText.includes('Premium');
                const category = isPremium ? 'Premium' : 'Regular';
                const orders = cells[1].querySelector('.order-count').textContent.trim().split(' ')[0];
                const total = cells[2].querySelector('.total-amount').textContent.trim();
                const status = cells[3].querySelector('.badge').textContent.trim();
                
                html += `
                    <tr>
                        <td class="text-center">${rowNum}</td>
                        <td>${name}</td>
                        <td class="text-center">${orders}</td>
                        <td class="text-right">${total}</td>
                        <td>${status}</td>
                        <td>${category}</td>
                    </tr>
                `;
                
                rowNum++;
            });
            
            html += `
                    </tbody>
                </table>
            </body>
            </html>
            `;
            
            // Create and trigger download
            const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `Data_Pelanggan_${new Date().toISOString().slice(0,10)}.xls`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Clean up
            setTimeout(() => URL.revokeObjectURL(url), 100);
        }
    </script>
</body>
</html>