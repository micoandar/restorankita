<?php
session_start();
include '../config/database.php';
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }

// Query untuk laporan harian dengan status
$report = mysqli_query($conn, "
    SELECT 
        DATE(created_at) as tgl, 
        COUNT(*) as jml_order,
        SUM(CASE WHEN status = 'completed' OR status = 'selesai' OR status IS NULL OR status = '' THEN 1 ELSE 0 END) as order_selesai,
        SUM(CASE WHEN status = 'cancelled' OR status = 'dibatalkan' OR status = 'cancel' THEN 1 ELSE 0 END) as order_batal,
        SUM(CASE WHEN status = 'completed' OR status = 'selesai' OR status IS NULL OR status = '' THEN total ELSE 0 END) as pendapatan_selesai,
        SUM(CASE WHEN status = 'cancelled' OR status = 'dibatalkan' OR status = 'cancel' THEN total ELSE 0 END) as pendapatan_batal,
        SUM(total) as pendapatan_total
    FROM orders 
    GROUP BY DATE(created_at) 
    ORDER BY tgl DESC
");

// Query untuk statistik keseluruhan
$overall_stats = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'completed' OR status = 'selesai' OR status IS NULL OR status = '' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = 'cancelled' OR status = 'dibatalkan' OR status = 'cancel' THEN 1 ELSE 0 END) as cancelled_orders,
        SUM(CASE WHEN status = 'completed' OR status = 'selesai' OR status IS NULL OR status = '' THEN total ELSE 0 END) as revenue_completed,
        SUM(CASE WHEN status = 'cancelled' OR status = 'dibatalkan' OR status = 'cancel' THEN total ELSE 0 END) as revenue_cancelled,
        SUM(total) as total_revenue,
        AVG(CASE WHEN status = 'completed' OR status = 'selesai' OR status IS NULL OR status = '' THEN total ELSE NULL END) as avg_order_value
    FROM orders
");
$stats = mysqli_fetch_assoc($overall_stats);

// Query untuk melihat semua status yang ada di database (debugging)
$status_check = mysqli_query($conn, "
    SELECT DISTINCT status, COUNT(*) as count 
    FROM orders 
    GROUP BY status 
    ORDER BY status
");

// Tampilkan debug info jika diperlukan
if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "Status yang ada di database:\n";
    while ($row = mysqli_fetch_assoc($status_check)) {
        echo "Status: '" . ($row['status'] ?: 'NULL/EMPTY') . "' - Count: " . $row['count'] . "\n";
    }
    echo "</pre>";
    mysqli_data_seek($status_check, 0);
}

// Hitung persentase
$completion_rate = $stats['total_orders'] > 0 ? ($stats['completed_orders'] / $stats['total_orders']) * 100 : 0;
$cancellation_rate = $stats['total_orders'] > 0 ? ($stats['cancelled_orders'] / $stats['total_orders']) * 100 : 0;

// Validasi data
$validation_total = $stats['completed_orders'] + $stats['cancelled_orders'];
$validation_message = '';
if ($validation_total != $stats['total_orders']) {
    $unclassified = $stats['total_orders'] - $validation_total;
    $validation_message = "⚠️ Catatan: Ada $unclassified order dengan status tidak terklasifikasi";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pendapatan - Admin Restoran Kita</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3a0ca3;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #ffc107;
            --info: #7209b7;
            --dark: #14213d;
            --light: #f8f9fa;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .report-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            box-shadow: var(--box-shadow);
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .stat-card.success {
            border-left-color: var(--success);
        }

        .stat-card.danger {
            border-left-color: var(--danger);
        }

        .stat-card.warning {
            border-left-color: var(--warning);
        }

        .stat-card.info {
            border-left-color: var(--info);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .stat-card.success .stat-number {
            color: var(--success);
        }

        .stat-card.danger .stat-number {
            color: var(--danger);
        }

        .stat-card.warning .stat-number {
            color: var(--warning);
        }

        .stat-card.info .stat-number {
            color: var(--info);
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .stat-detail {
            font-size: 0.85rem;
            color: #8d99ae;
            margin-top: 5px;
        }

        .table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-top: 25px;
        }

        .table-header {
            padding: 20px;
            background: var(--light);
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table th {
            background: var(--dark);
            color: white;
            border: none;
            padding: 15px;
            font-weight: 600;
        }

        .table td {
            padding: 15px;
            vertical-align: middle;
            border-color: #e9ecef;
        }

        .table tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: rgba(76, 201, 240, 0.15);
            color: #4cc9f0;
            border: 1px solid rgba(76, 201, 240, 0.3);
        }

        .badge-danger {
            background: rgba(247, 37, 133, 0.15);
            color: #f72585;
            border: 1px solid rgba(247, 37, 133, 0.3);
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.15);
            color: #b58900;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .badge-info {
            background: rgba(114, 9, 183, 0.15);
            color: #7209b7;
            border: 1px solid rgba(114, 9, 183, 0.3);
        }

        .revenue-positive {
            color: var(--success);
            font-weight: 700;
        }

        .revenue-negative {
            color: var(--danger);
            font-weight: 700;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-print {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.3);
            color: white;
        }

        .btn-export {
            background: var(--light);
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .btn-export:hover {
            background: rgba(67, 97, 238, 0.1);
            transform: translateY(-2px);
        }

        .summary-row {
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.1), rgba(58, 12, 163, 0.05));
            font-weight: 700;
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

        .validation-alert {
            background: rgba(255, 193, 7, 0.15);
            border-left: 4px solid var(--warning);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .debug-link {
            font-size: 0.85rem;
            color: var(--info);
            text-decoration: none;
            margin-left: 10px;
        }

        .debug-link:hover {
            text-decoration: underline;
        }

        @media print {
            .sidebar, .admin-header, .action-buttons {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            
            .table-container {
                box-shadow: none !important;
                border: 1px solid #dee2e6;
            }
            
            .report-header {
                box-shadow: none !important;
                border: 1px solid #dee2e6;
            }
        }

        @media (max-width: 768px) {
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-print, .btn-export {
                width: 100%;
                justify-content: center;
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
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                <li><a href="menu.php"><i class="fas fa-utensils"></i><span>Kelola Menu</span></a></li>
                <li><a href="order.php"><i class="fas fa-shopping-cart"></i><span>Data Order</span></a></li>
                <li><a href="customer.php"><i class="fas fa-users"></i><span>Pelanggan</span></a></li>
                <li><a href="report.php" class="active"><i class="fas fa-chart-bar"></i><span>Laporan</span></a></li>
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
    
    <div class="main-content">
        <header class="admin-header">
            <button class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h4 class="mb-0">
                <i class="fas fa-chart-bar me-2"></i>Laporan Pendapatan
            </h4>
            <div class="header-right">
                <div class="admin-avatar"><?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?></div>
                <div class="fw-bold"><?php echo $_SESSION['admin']; ?></div>
            </div>
        </header>
        
        <div class="content-wrapper">
            <!-- Report Header -->
            <div class="report-header">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <h2><i class="fas fa-file-invoice-dollar me-2"></i>Laporan Pendapatan</h2>
                        <p class="mb-0">Analisis pendapatan berdasarkan status order</p>
                        <?php if($validation_message): ?>
                        <div class="mt-3">
                            <span class="badge-warning status-badge">
                                <i class="fas fa-exclamation-triangle me-1"></i><?php echo $validation_message; ?>
                            </span>
                            <a href="?debug=1" class="debug-link">
                                <i class="fas fa-bug me-1"></i>Lihat detail status
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card success">
                        <div class="stat-number">Rp <?php echo number_format($stats['revenue_completed'] ?? 0); ?></div>
                        <div class="stat-label">Pendapatan Selesai</div>
                        <div class="stat-detail">
                            <i class="fas fa-check-circle me-1"></i>
                            <?php echo number_format($stats['completed_orders'] ?? 0); ?> order selesai
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card danger">
                        <div class="stat-number">Rp <?php echo number_format($stats['revenue_cancelled'] ?? 0); ?></div>
                        <div class="stat-label">Potensi Kerugian (Batal)</div>
                        <div class="stat-detail">
                            <i class="fas fa-times-circle me-1"></i>
                            <?php echo number_format($stats['cancelled_orders'] ?? 0); ?> order dibatalkan
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card info">
                        <div class="stat-number">Rp <?php echo number_format($stats['total_revenue'] ?? 0); ?></div>
                        <div class="stat-label">Total Transaksi</div>
                        <div class="stat-detail">
                            <i class="fas fa-exchange-alt me-1"></i>
                            <?php echo number_format($stats['total_orders'] ?? 0); ?> semua order
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card warning">
                        <div class="stat-number">Rp <?php echo number_format($stats['avg_order_value'] ?? 0, 0); ?></div>
                        <div class="stat-label">Rata-rata/Order</div>
                        <div class="stat-detail">
                            <i class="fas fa-percentage me-1"></i>
                            <?php echo number_format($completion_rate, 1); ?>% selesai
                        </div>
                        <?php if($validation_total != $stats['total_orders']): ?>
                        <div class="stat-detail text-danger">
                            <i class="fas fa-question-circle me-1"></i>
                            <?php echo number_format($stats['total_orders'] - $validation_total); ?> order belum diklasifikasi
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Status Summary -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="stat-card">
                        <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Ringkasan Status Order</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-center">
                                    <span class="badge-success status-badge me-3">
                                        <i class="fas fa-check-circle me-1"></i>SELESAI
                                    </span>
                                    <div>
                                        <div class="fw-bold"><?php echo number_format($stats['completed_orders'] ?? 0); ?> Order</div>
                                        <small class="text-muted"><?php echo number_format($completion_rate, 1); ?>% dari total</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-center">
                                    <span class="badge-danger status-badge me-3">
                                        <i class="fas fa-times-circle me-1"></i>BATAL
                                    </span>
                                    <div>
                                        <div class="fw-bold"><?php echo number_format($stats['cancelled_orders'] ?? 0); ?> Order</div>
                                        <small class="text-muted"><?php echo number_format($cancellation_rate, 1); ?>% dari total</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-center">
                                    <span class="badge-info status-badge me-3">
                                        <i class="fas fa-question-circle me-1"></i>LAINNYA
                                    </span>
                                    <div>
                                        <div class="fw-bold"><?php echo number_format(($stats['total_orders'] ?? 0) - $validation_total); ?> Order</div>
                                        <small class="text-muted">Status belum terklasifikasi</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print me-2"></i>Cetak Laporan
                </button>
                <button onclick="exportToExcel()" class="btn-export">
                    <i class="fas fa-file-excel me-2"></i>Export Excel
                </button>
                <a href="order.php" class="btn btn-outline-primary">
                    <i class="fas fa-shopping-cart me-2"></i>Lihat Detail Order
                </a>
            </div>

            <!-- Report Table -->
            <div class="table-container">
                <div class="table-header">
                    <h5 class="mb-0"><i class="fas fa-table me-2"></i>Rincian Harian</h5>
                    <div class="text-muted">
                        Menampilkan <?php echo mysqli_num_rows($report); ?> hari
                    </div>
                </div>
                <div class="table-responsive">
                    <?php if(mysqli_num_rows($report) > 0): ?>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Total Order</th>
                                <th>Order Selesai</th>
                                <th>Order Batal</th>
                                <th>Pendapatan Selesai</th>
                                <th>Potensi Kerugian</th>
                                <th>Total Transaksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grand_total = 0;
                            $grand_completed = 0;
                            $grand_cancelled = 0;
                            $grand_revenue_completed = 0;
                            $grand_revenue_cancelled = 0;
                            $grand_total_transactions = 0;
                            
                            mysqli_data_seek($report, 0); // Reset pointer
                            while($r = mysqli_fetch_assoc($report)): 
                                $grand_total += $r['jml_order'];
                                $grand_completed += $r['order_selesai'];
                                $grand_cancelled += $r['order_batal'];
                                $grand_revenue_completed += $r['pendapatan_selesai'];
                                $grand_revenue_cancelled += $r['pendapatan_batal'];
                                $grand_total_transactions += $r['pendapatan_total'];
                                
                                // Hitung persentase per hari
                                $daily_completion_rate = $r['jml_order'] > 0 ? ($r['order_selesai'] / $r['jml_order']) * 100 : 0;
                                $daily_cancellation_rate = $r['jml_order'] > 0 ? ($r['order_batal'] / $r['jml_order']) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('d M Y', strtotime($r['tgl'])); ?></strong><br>
                                    <small class="text-muted"><?php echo date('l', strtotime($r['tgl'])); ?></small>
                                </td>
                                <td>
                                    <?php echo number_format($r['jml_order']); ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo number_format($daily_completion_rate, 1); ?>% selesai
                                    </small>
                                </td>
                                <td>
                                    <span class="status-badge badge-success">
                                        <i class="fas fa-check-circle me-1"></i>
                                        <?php echo number_format($r['order_selesai']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge badge-danger">
                                        <i class="fas fa-times-circle me-1"></i>
                                        <?php echo number_format($r['order_batal']); ?>
                                    </span>
                                    <?php if($r['order_batal'] > 0): ?>
                                    <br>
                                    <small class="text-danger">
                                        <?php echo number_format($daily_cancellation_rate, 1); ?>%
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td class="revenue-positive">
                                    Rp <?php echo number_format($r['pendapatan_selesai']); ?>
                                </td>
                                <td class="revenue-negative">
                                    Rp <?php echo number_format($r['pendapatan_batal']); ?>
                                    <?php if($r['pendapatan_batal'] > 0): ?>
                                    <br>
                                    <small class="text-danger">
                                        <?php echo $r['pendapatan_total'] > 0 ? number_format(($r['pendapatan_batal'] / $r['pendapatan_total']) * 100, 1) : 0; ?>%
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>Rp <?php echo number_format($r['pendapatan_total']); ?></strong>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr class="summary-row">
                                <td><strong>GRAND TOTAL</strong></td>
                                <td><?php echo number_format($grand_total); ?></td>
                                <td><?php echo number_format($grand_completed); ?></td>
                                <td><?php echo number_format($grand_cancelled); ?></td>
                                <td class="revenue-positive">Rp <?php echo number_format($grand_revenue_completed); ?></td>
                                <td class="revenue-negative">Rp <?php echo number_format($grand_revenue_cancelled); ?></td>
                                <td><strong>Rp <?php echo number_format($grand_total_transactions); ?></strong></td>
                            </tr>
                            <tr class="summary-row">
                                <td colspan="3">
                                    <span class="status-badge badge-success">
                                        Rate Selesai: <?php echo $grand_total > 0 ? number_format(($grand_completed / $grand_total) * 100, 1) : 0; ?>%
                                    </span>
                                </td>
                                <td colspan="2">
                                    <span class="status-badge badge-danger">
                                        Rate Batal: <?php echo $grand_total > 0 ? number_format(($grand_cancelled / $grand_total) * 100, 1) : 0; ?>%
                                    </span>
                                </td>
                                <td colspan="2" class="text-end">
                                    <span class="badge-warning status-badge">
                                        Pendapatan Bersih: Rp <?php echo number_format($grand_revenue_completed); ?>
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <h5>Tidak ada data laporan</h5>
                        <p>Belum ada transaksi yang tercatat</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });

        // Export to Excel function
        function exportToExcel() {
            let table = document.querySelector('.table');
            let rows = table.querySelectorAll('tr');
            let csv = [];
            
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Clean up text (remove HTML tags and icons)
                    let text = cols[j].innerText.replace(/\n/g, ' ').trim();
                    row.push('"' + text + '"');
                }
                
                csv.push(row.join(','));
            }
            
            let csvString = csv.join('\n');
            let blob = new Blob([csvString], { type: 'text/csv' });
            let url = window.URL.createObjectURL(blob);
            let a = document.createElement('a');
            
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', 'Laporan_Pendapatan_<?php echo date('Y-m-d'); ?>.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // Show validation details
        document.addEventListener('DOMContentLoaded', function() {
            // Add debug info if needed
            const debugLink = document.querySelector('.debug-link');
            if (debugLink) {
                debugLink.addEventListener('click', function(e) {
                    if (!window.location.href.includes('debug=1')) {
                        window.location.href = window.location.href + '?debug=1';
                    }
                });
            }
        });
    </script>
</body>
</html>