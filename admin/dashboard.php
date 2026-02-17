<?php
session_start();
include '../config/database.php';

// Redirect jika belum login
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Ambil data statistik
$total_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM orders"))['total'];
$total_menu = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM menu"))['total'];

// PERBAIKAN: Total pendapatan sekarang hanya menghitung pesanan yang tidak dibatalkan (status != 'cancelled')
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total) as total FROM orders WHERE status != 'cancelled'"))['total'];
if (!$total_revenue) $total_revenue = 0;

// Ambil 5 order terbaru
$recent_orders = mysqli_query($conn, "SELECT * FROM orders ORDER BY created_at DESC LIMIT 5");

// Ambil data untuk chart (orders per hari dalam 7 hari terakhir)
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = '$date'");
    $row = mysqli_fetch_assoc($result);
    $chart_data[] = [
        'date' => date('d M', strtotime($date)),
        'count' => $row['count']
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Restoran Kita</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3a0ca3;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --warning: #f72585;
            --info: #560bad;
            --dark: #14213d;
            --dark-light: #2b2d42;
            --light: #f8f9fa;
            --light-gray: #e9ecef;
            --gray: #8d99ae;
            --border-radius: 16px;
            --border-radius-sm: 10px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --box-shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            --gradient-primary: linear-gradient(135deg, var(--primary), var(--primary-dark));
            --gradient-secondary: linear-gradient(135deg, var(--secondary), #b5179e);
            --gradient-success: linear-gradient(135deg, #4cc9f0, #4361ee);
            --gradient-warning: linear-gradient(135deg, #f72585, #7209b7);
        }

        /* Welcome Section */
        .welcome-section {
            background: var(--gradient-primary);
            color: white;
            padding: 40px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
        
        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        
        .welcome-section::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        
        .welcome-section h2 {
            font-weight: 800;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
            font-size: 2.2rem;
        }
        
        .welcome-section p {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
            max-width: 600px;
        }
        
        .date-time {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: var(--border-radius-sm);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .date-time .date {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .date-time .time {
            font-size: 1.8rem;
            font-weight: 700;
            color: #ffd166;
            font-family: 'Courier New', monospace;
        }
        
        .date-time .time i {
            font-size: 1.4rem;
            margin-right: 10px;
        }

        /* Stats Cards */
        .dashboard-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--box-shadow-hover);
        }
        
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }
        
        .dashboard-card.orders::before {
            background: var(--gradient-primary);
        }
        
        .dashboard-card.menu::before {
            background: var(--gradient-secondary);
        }
        
        .dashboard-card.revenue::before {
            background: var(--gradient-success);
        }
        
        .dashboard-card.users::before {
            background: var(--gradient-warning);
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 1.5rem;
            color: white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
        }
        
        .card-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: inherit;
            opacity: 0.8;
        }
        
        .card-icon i {
            position: relative;
            z-index: 1;
        }
        
        .card-icon.orders {
            background: var(--gradient-primary);
        }
        
        .card-icon.menu {
            background: var(--gradient-secondary);
        }
        
        .card-icon.revenue {
            background: var(--gradient-success);
        }
        
        .card-icon.users {
            background: var(--gradient-warning);
        }
        
        .card-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 5px;
            background: linear-gradient(135deg, var(--dark), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .card-label {
            color: var(--gray);
            font-size: 0.95rem;
            margin-bottom: 15px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stats-change {
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            font-weight: 600;
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
            border: 1px solid rgba(76, 201, 240, 0.2);
        }
        
        .stats-change.positive {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-color: rgba(40, 167, 69, 0.2);
        }
        
        .stats-change i {
            font-size: 0.8rem;
        }

        /* Charts Section */
        .chart-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            height: 420px;
            position: relative;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }
        
        .chart-container h5 {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 15px;
        }
        
        .chart-container h5::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--gradient-primary);
            border-radius: 3px;
        }
        
        #ordersChart {
            height: 300px !important;
            width: 100% !important;
        }

        /* Recent Activity */
        .recent-activity {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            height: 420px;
            position: relative;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }
        
        .recent-activity h5 {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 15px;
        }
        
        .recent-activity h5::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--gradient-warning);
            border-radius: 3px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }
        
        .activity-item:hover {
            background: rgba(67, 97, 238, 0.03);
            padding-left: 15px;
            padding-right: 15px;
            margin: 0 -15px;
            border-radius: 10px;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        .activity-details {
            flex: 1;
            min-width: 0;
        }
        
        .activity-title {
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .activity-desc {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .activity-time {
            color: var(--gray);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .activity-time i {
            color: var(--primary);
        }
        
        .activity-amount {
            font-weight: 700;
            color: var(--primary);
            background: rgba(67, 97, 238, 0.1);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--light-gray);
            opacity: 0.5;
        }
        
        .empty-state p {
            font-size: 1.1rem;
        }

        /* Quick Actions */
        .quick-actions {
            margin-top: 40px;
        }
        
        .quick-actions h5 {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 15px;
        }
        
        .quick-actions h5::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--gradient-success);
            border-radius: 3px;
        }
        
        .action-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.03);
            text-decoration: none;
            color: var(--dark);
            display: block;
            position: relative;
            overflow: hidden;
        }
        
        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--box-shadow-hover);
            color: var(--dark);
        }
        
        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }
        
        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 20px;
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
        }
        
        .action-content h6 {
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }
        
        .action-content small {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .action-arrow {
            position: absolute;
            top: 25px;
            right: 25px;
            color: var(--primary);
            opacity: 0;
            transform: translateX(-10px);
            transition: var(--transition);
        }
        
        .action-card:hover .action-arrow {
            opacity: 1;
            transform: translateX(0);
        }

        /* Scrollbar Styling */
        .recent-activity::-webkit-scrollbar {
            width: 6px;
        }
        
        .recent-activity::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 10px;
        }
        
        .recent-activity::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        
        .recent-activity::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Loading Animation */
        .loading {
            position: relative;
        }
        
        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            border: 3px solid var(--light-gray);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Animation for cards */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .dashboard-card {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }
        
        .dashboard-card:nth-child(1) { animation-delay: 0.1s; }
        .dashboard-card:nth-child(2) { animation-delay: 0.2s; }
        .dashboard-card:nth-child(3) { animation-delay: 0.3s; }
        .dashboard-card:nth-child(4) { animation-delay: 0.4s; }

        /* Badge for new orders */
        .new-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--warning);
            color: white;
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(247, 37, 133, 0.3);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Custom tooltip for chart */
        .chartjs-tooltip {
            background: var(--dark) !important;
            border-radius: 10px !important;
            border: none !important;
            box-shadow: var(--box-shadow) !important;
            padding: 12px 15px !important;
            font-family: inherit !important;
        }
        
        .chartjs-tooltip-key {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 2px;
            margin-right: 5px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .welcome-section {
                padding: 30px 20px;
            }
            
            .welcome-section h2 {
                font-size: 1.8rem;
            }
            
            .date-time {
                padding: 15px;
                margin-top: 20px;
            }
            
            .dashboard-card {
                padding: 20px;
            }
            
            .card-value {
                font-size: 2rem;
            }
            
            .chart-container,
            .recent-activity {
                padding: 20px;
                height: 350px;
            }
            
            #ordersChart {
                height: 250px !important;
            }
            
            .activity-item {
                padding: 15px 0;
            }
            
            .action-card {
                padding: 20px;
            }
            
            .card-icon {
                width: 50px;
                height: 50px;
                font-size: 1.3rem;
            }
        }
        
        @media (max-width: 576px) {
            .welcome-section h2 {
                font-size: 1.5rem;
            }
            
            .card-value {
                font-size: 1.8rem;
            }
            
            .chart-container,
            .recent-activity {
                height: 300px;
            }
            
            #ordersChart {
                height: 200px !important;
            }
            
            .activity-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .action-icon {
                width: 50px;
                height: 50px;
                font-size: 1.3rem;
            }
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
                    <a href="dashboard.php" class="active">
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
                    <a href="order.php">
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
                <!-- Lihat Website Utama dengan Logo Mata -->
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
            
            <h4 class="mb-0">Dashboard</h4>
            
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
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>Selamat Datang, <?php echo $_SESSION['admin']; ?>! 👋</h2>
                        <p class="mb-0">Selamat bekerja di sistem admin Restoran Kita. Berikut adalah ringkasan aktivitas hari ini.</p>
                    </div>
                    <div class="col-md-4">
                        <div class="date-time">
                            <div class="date"><?php echo date('l, d F Y'); ?></div>
                            <div class="time">
                                <i class="fas fa-clock me-2"></i>
                                <span id="liveTime"><?php echo date('H:i:s'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="dashboard-card orders">
                        <div class="card-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="card-value"><?php echo $total_orders; ?></div>
                        <div class="card-label">Total Order</div>
                        <div class="stats-change positive">
                            <i class="fas fa-arrow-up me-1"></i>Aktivitas Tinggi
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="dashboard-card menu">
                        <div class="card-icon menu">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <div class="card-value"><?php echo $total_menu; ?></div>
                        <div class="card-label">Menu Tersedia</div>
                        <div class="stats-change positive">
                            <i class="fas fa-check me-1"></i>Aktif
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="dashboard-card revenue">
                        <div class="card-icon revenue">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="card-value">Rp <?php echo number_format($total_revenue); ?></div>
                        <div class="card-label">Total Pendapatan</div>
                        <div class="stats-change positive">
                            <i class="fas fa-chart-line me-1"></i>Profit
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="dashboard-card users">
                        <div class="card-icon users">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="card-value">
                            <?php 
                            $unique_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT nama_pelanggan) as total FROM orders"))['total'];
                            echo $unique_customers;
                            ?>
                        </div>
                        <div class="card-label">Pelanggan</div>
                        <div class="stats-change positive">
                            <i class="fas fa-user-plus me-1"></i>Terdaftar
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts & Recent Activity -->
            <div class="row mb-4">
                <div class="col-lg-8 mb-4">
                    <div class="chart-container">
                        <h5 class="mb-4">Statistik Order 7 Hari Terakhir</h5>
                        <canvas id="ordersChart"></canvas>
                    </div>
                </div>
                
                <div class="col-lg-4 mb-4">
                    <div class="recent-activity">
                        <h5 class="mb-4">Order Terbaru</h5>
                        <div class="activity-list">
                            <?php 
                            $order_count = 0;
                            if ($recent_orders && mysqli_num_rows($recent_orders) > 0): 
                                while($order = mysqli_fetch_assoc($recent_orders)): 
                                    $order_count++;
                                    $is_new = (strtotime($order['created_at']) > strtotime('-1 hour'));
                            ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title"><?php echo htmlspecialchars($order['nama_pelanggan']); ?></div>
                                    <div class="activity-desc"><?php echo htmlspecialchars($order['menu']); ?> (<?php echo $order['jumlah']; ?>x)</div>
                                    <div class="activity-time">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo date('H:i', strtotime($order['created_at'])); ?>
                                        <span class="activity-amount">Rp <?php echo number_format($order['total']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-shopping-cart"></i>
                                <p>Belum ada order hari ini</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <h5 class="mb-4">Aksi Cepat</h5>
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <a href="menu.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <div class="action-content">
                                <h6>Kelola Menu</h6>
                                <small>Tambah & edit menu restoran</small>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <a href="order.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="action-content">
                                <h6>Lihat Order</h6>
                                <small>Kelola semua pesanan</small>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <a href="report.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="action-content">
                                <h6>Laporan</h6>
                                <small>Analisis keuangan</small>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <a href="../index.php" target="_blank" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-globe"></i>
                            </div>
                            <div class="action-content">
                                <h6>Website Utama</h6>
                                <small>Lihat halaman depan restoran</small>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-external-link-alt"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
        
        // Chart Configuration
        const ctx = document.getElementById('ordersChart').getContext('2d');
        
        // Create gradient for bars
        const barGradient = ctx.createLinearGradient(0, 0, 0, 400);
        barGradient.addColorStop(0, 'rgba(67, 97, 238, 1)');
        barGradient.addColorStop(0.7, 'rgba(67, 97, 238, 0.8)');
        barGradient.addColorStop(1, 'rgba(67, 97, 238, 0.6)');

        const hoverGradient = ctx.createLinearGradient(0, 0, 0, 400);
        hoverGradient.addColorStop(0, 'rgba(114, 9, 183, 1)');
        hoverGradient.addColorStop(1, 'rgba(114, 9, 183, 0.8)');

        const ordersChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($chart_data, 'date')); ?>,
                datasets: [{
                    label: 'Jumlah Order',
                    data: <?php echo json_encode(array_column($chart_data, 'count')); ?>,
                    backgroundColor: barGradient,
                    hoverBackgroundColor: hoverGradient,
                    borderColor: 'rgba(67, 97, 238, 0.3)',
                    borderWidth: 0,
                    borderRadius: 10,
                    borderSkipped: false,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(20, 33, 61, 0.95)',
                        padding: 15,
                        cornerRadius: 10,
                        borderColor: 'rgba(67, 97, 238, 0.3)',
                        borderWidth: 1,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                return `Order: ${context.parsed.y}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false,
                            drawTicks: false
                        },
                        ticks: {
                            color: 'var(--gray)',
                            font: {
                                size: 12
                            },
                            padding: 10,
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: 'var(--gray)',
                            font: {
                                size: 12
                            },
                            padding: 10
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                animation: {
                    duration: 1500,
                    easing: 'easeOutQuart'
                }
            }
        });
        
        // Live Time Update with seconds
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('liveTime').textContent = timeString;
        }
        updateTime();
        setInterval(updateTime, 1000);
        
        // Add animation to cards when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.dashboard-card, .action-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                }, index * 100);
            });
        });
    </script>
</body>
</html>