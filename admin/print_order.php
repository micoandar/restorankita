<?php
session_start();
include '../config/database.php';

// Redirect jika belum login
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

// Cek apakah parameter ID ada
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID order tidak ditemukan!");
}

$order_id = (int)$_GET['id'];

// Cek apakah kolom session_order_id ada di tabel
$check_column_query = "SHOW COLUMNS FROM orders LIKE 'session_order_id'";
$column_result = mysqli_query($conn, $check_column_query);
$has_session_column = mysqli_num_rows($column_result) > 0;

// Cek apakah kolom telepon ada di tabel
$check_telepon_query = "SHOW COLUMNS FROM orders LIKE 'telepon'";
$telepon_result = mysqli_query($conn, $check_telepon_query);
$has_telepon_column = mysqli_num_rows($telepon_result) > 0;

// Ambil data order berdasarkan ID
if ($has_session_column) {
    // Jika ada session_order_id, ambil berdasarkan itu
    $orders_query = "
        SELECT 
            session_order_id,
            MIN(id) as id,
            MIN(created_at) as created_at,
            nama_pelanggan,";
    
    if ($has_telepon_column) {
        $orders_query .= "telepon,";
    }
    
    $orders_query .= "
            GROUP_CONCAT(menu SEPARATOR ', ') as menu_items,
            GROUP_CONCAT(jumlah SEPARATOR ', ') as quantities,
            GROUP_CONCAT(harga SEPARATOR ', ') as prices,
            SUM(total) as total,
            MIN(status) as status,
            GROUP_CONCAT(COALESCE(catatan, '') SEPARATOR ' | ') as catatan,
            COUNT(*) as item_count
        FROM orders 
        WHERE id = '$order_id' 
        GROUP BY session_order_id, nama_pelanggan";
    
    if ($has_telepon_column) {
        $orders_query .= ", telepon";
    }
} else {
    // Jika tidak ada session_order_id, ambil berdasarkan ID biasa
    $orders_query = "
        SELECT 
            id,
            created_at,
            nama_pelanggan,";
    
    if ($has_telepon_column) {
        $orders_query .= "telepon,";
    }
    
    $orders_query .= "
            menu as menu_items,
            jumlah as quantities,
            harga as prices,
            total,
            status,
            catatan,
            1 as item_count
        FROM orders 
        WHERE id = '$order_id'";
}

$order_result = mysqli_query($conn, $orders_query);

if (!$order_result || mysqli_num_rows($order_result) == 0) {
    die("Order tidak ditemukan!");
}

$order = mysqli_fetch_assoc($order_result);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            
            .no-print {
                display: none !important;
            }
            
            .container {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            
            .invoice-container {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .btn-print {
                display: none !important;
            }
            
            .footer {
                display: none !important;
            }
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .invoice-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            padding: 30px;
            max-width: 800px;
        }
        
        .invoice-header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .restaurant-name {
            color: #007bff;
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .restaurant-tagline {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .invoice-title {
            text-align: right;
            color: #495057;
        }
        
        .invoice-title h2 {
            font-weight: 700;
            color: #007bff;
            margin-bottom: 5px;
        }
        
        .invoice-title .invoice-number {
            color: #6c757d;
            font-size: 1rem;
        }
        
        .customer-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            width: 120px;
        }
        
        .info-value {
            color: #212529;
        }
        
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .table-items thead {
            background-color: #007bff;
            color: white;
        }
        
        .table-items th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .table-items tbody tr {
            border-bottom: 1px solid #dee2e6;
        }
        
        .table-items tbody tr:last-child {
            border-bottom: none;
        }
        
        .table-items td {
            padding: 15px;
            vertical-align: top;
        }
        
        .table-items tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
        
        .total-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
        }
        
        .total-label {
            font-weight: 600;
            color: #495057;
        }
        
        .total-value {
            font-weight: 700;
            color: #007bff;
            font-size: 1.1rem;
        }
        
        .grand-total {
            border-top: 2px solid #dee2e6;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .grand-total .total-label {
            font-size: 1.2rem;
        }
        
        .grand-total .total-value {
            font-size: 1.4rem;
            color: #28a745;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.9rem;
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
        
        .notes-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .notes-title {
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
        }
        
        .notes-content {
            color: #212529;
            line-height: 1.6;
        }
        
        .print-btn-container {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-print:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }
        
        .btn-back {
            background: #6c757d;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 10px;
        }
        
        .btn-back:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }
        
        .header-actions {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-actions no-print">
            <a href="order.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Kembali ke Order
            </a>
        </div>
        
        <div class="invoice-container">
            <!-- Header -->
            <div class="invoice-header">
                <div class="row">
                    <div class="col-md-6">
                        <div class="restaurant-name">Restoran Kita</div>
                        <div class="restaurant-tagline">
                            <i class="fas fa-map-marker-alt me-1"></i> Jl. Contoh No. 123, Kota Kita
                        </div>
                        <div class="restaurant-tagline">
                            <i class="fas fa-phone me-1"></i> (021) 1234-5678
                        </div>
                        <div class="restaurant-tagline">
                            <i class="fas fa-envelope me-1"></i> info@restorankita.com
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="invoice-title">
                            <h2>INVOICE</h2>
                            <div class="invoice-number">
                                #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                            </div>
                            <div class="mt-2">
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Info -->
            <div class="customer-info">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="d-flex mb-2">
                            <div class="info-label">Pelanggan:</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['nama_pelanggan']); ?></div>
                        </div>
                        <?php if($has_telepon_column && !empty($order['telepon'])): ?>
                        <div class="d-flex mb-2">
                            <div class="info-label">Telepon:</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['telepon']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="d-flex mb-2">
                            <div class="info-label">Tanggal Order:</div>
                            <div class="info-value"><?php echo date('d F Y', strtotime($order['created_at'])); ?></div>
                        </div>
                        <div class="d-flex mb-2">
                            <div class="info-label">Waktu Order:</div>
                            <div class="info-value"><?php echo date('H:i', strtotime($order['created_at'])); ?> WIB</div>
                        </div>
                        <div class="d-flex mb-2">
                            <div class="info-label">Invoice Date:</div>
                            <div class="info-value"><?php echo date('d/m/Y'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Items Table -->
            <div class="table-responsive">
                <table class="table-items">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Menu</th>
                            <th>Jumlah</th>
                            <th>Harga Satuan</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $menus = explode(', ', $order['menu_items']);
                        $quantities = explode(', ', $order['quantities']);
                        $prices = explode(', ', $order['prices']);
                        $total = 0;
                        
                        for($i = 0; $i < count($menus); $i++):
                            $subtotal = $quantities[$i] * $prices[$i];
                            $total += $subtotal;
                        ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars($menus[$i]); ?></td>
                            <td><?php echo $quantities[$i]; ?> porsi</td>
                            <td>Rp <?php echo number_format($prices[$i]); ?></td>
                            <td>Rp <?php echo number_format($subtotal); ?></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Notes -->
            <?php if(!empty($order['catatan']) && trim($order['catatan']) != '' && trim($order['catatan']) != '|'): ?>
            <div class="notes-section">
                <div class="notes-title">
                    <i class="fas fa-sticky-note me-2"></i>Catatan:
                </div>
                <div class="notes-content">
                    <?php echo nl2br(htmlspecialchars($order['catatan'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Total Section -->
            <div class="total-section">
                <div class="total-row">
                    <div class="total-label">Jumlah Item:</div>
                    <div class="total-value"><?php echo $order['item_count']; ?> item</div>
                </div>
                <div class="total-row grand-total">
                    <div class="total-label">TOTAL PEMBAYARAN:</div>
                    <div class="total-value">Rp <?php echo number_format($total); ?></div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Terima Kasih Atas Pesanan Anda!</strong></p>
                        <p>Silakan menunggu pesanan Anda disiapkan.</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Informasi Pembayaran:</strong></p>
                        <p>Pembayaran dapat dilakukan secara tunai atau transfer ke:</p>
                        <p>BCA: 123-456-7890 (Restoran Kita)</p>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="mb-0">Invoice ini dicetak pada: <?php echo date('d F Y H:i:s'); ?></p>
                    <p class="mb-0">Staff: <?php echo $_SESSION['admin']; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Print Button -->
        <div class="print-btn-container no-print">
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Invoice
            </button>
        </div>
    </div>
    
    <script>
        // Auto print jika diakses dari tombol print
        window.onload = function() {
            // Cek jika ada parameter auto_print
            const urlParams = new URLSearchParams(window.location.search);
            const autoPrint = urlParams.get('auto_print');
            
            if (autoPrint === '1') {
                window.print();
            }
        };
        
        // Tambahkan event untuk tombol print dengan animasi
        document.querySelector('.btn-print').addEventListener('click', function() {
            // Tambahkan parameter auto_print untuk print otomatis
            const newUrl = window.location.href + (window.location.search ? '&' : '?') + 'auto_print=1';
            window.history.replaceState({}, document.title, newUrl);
        });
    </script>
</body>
</html>