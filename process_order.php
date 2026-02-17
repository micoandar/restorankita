<?php
include 'config/database.php';

// Mulai session
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $catatan = isset($_POST['catatan']) ? mysqli_real_escape_string($conn, $_POST['catatan']) : '';
    $metode_pembayaran = isset($_POST['metode_pembayaran']) ? mysqli_real_escape_string($conn, $_POST['metode_pembayaran']) : 'cash';
    
    // Cek apakah nama pelanggan sudah ada dan masih dalam proses
    $check_customer_query = "SELECT * FROM orders 
                            WHERE nama_pelanggan = '$nama' 
                            AND status IN ('pending', 'processing')
                            AND DATE(created_at) = CURDATE()
                            LIMIT 1";
    $check_customer_result = mysqli_query($conn, $check_customer_query);
    
    if (mysqli_num_rows($check_customer_result) > 0) {
        // Nama sudah digunakan hari ini dan masih ada pesanan yang belum selesai
        echo "<script>
            alert('Maaf, nama \"$nama\" sudah digunakan untuk pesanan yang belum selesai hari ini. Silakan gunakan nama lain atau tunggu pesanan sebelumnya selesai.');
            window.history.back();
        </script>";
        exit();
    }
    
    // Ambil waktu saat ini untuk invoice
    $waktu_pesan = date('Y-m-d H:i:s');
    $waktu_cetak = date('d/m/Y H:i:s');
    
    // Generate unique session_id untuk group order ini
    $session_id = uniqid($nama . '_', true);
    
    // Cek apakah data menu adalah array (multiple items)
    if (isset($_POST['menu']) && is_array($_POST['menu'])) {
        $menus = $_POST['menu'];
        $hargas = $_POST['harga'];
        $jumlahs = $_POST['jumlah'];
        
        $total_all = 0;
        $order_ids = [];
        $first_order_id = 0;
        
        // Cek struktur tabel untuk mengetahui kolom yang tersedia
        $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM orders");
        $columns = [];
        while ($row = mysqli_fetch_assoc($check_columns)) {
            $columns[] = $row['Field'];
        }
        
        // Loop untuk setiap item di keranjang
        for ($i = 0; $i < count($menus); $i++) {
            $menu = mysqli_real_escape_string($conn, $menus[$i]);
            $harga = (int)$hargas[$i];
            $jumlah = (int)$jumlahs[$i];
            $total = $jumlah * $harga;
            $total_all += $total;

            // Bangun query INSERT berdasarkan kolom yang tersedia
            if (in_array('session_id', $columns) && in_array('metode_pembayaran', $columns) && in_array('status_pembayaran', $columns) && in_array('waktu_pesan', $columns)) {
                // Jika ada semua kolom termasuk session_id
                $query = "INSERT INTO orders (nama_pelanggan, menu, harga, jumlah, total, catatan, status, metode_pembayaran, status_pembayaran, waktu_pesan, session_id) 
                          VALUES ('$nama', '$menu', '$harga', '$jumlah', '$total', '$catatan', 'pending', '$metode_pembayaran', 'pending', '$waktu_pesan', '$session_id')";
            } elseif (in_array('metode_pembayaran', $columns) && in_array('status_pembayaran', $columns) && in_array('waktu_pesan', $columns)) {
                // Jika ada semua kolom termasuk waktu_pesan
                $query = "INSERT INTO orders (nama_pelanggan, menu, harga, jumlah, total, catatan, status, metode_pembayaran, status_pembayaran, waktu_pesan) 
                          VALUES ('$nama', '$menu', '$harga', '$jumlah', '$total', '$catatan', 'pending', '$metode_pembayaran', 'pending', '$waktu_pesan')";
            } elseif (in_array('metode_pembayaran', $columns) && in_array('status_pembayaran', $columns)) {
                // Jika ada kolom metode pembayaran dan status pembayaran
                $query = "INSERT INTO orders (nama_pelanggan, menu, harga, jumlah, total, catatan, status, metode_pembayaran, status_pembayaran) 
                          VALUES ('$nama', '$menu', '$harga', '$jumlah', '$total', '$catatan', 'pending', '$metode_pembayaran', 'pending')";
            } elseif (in_array('harga', $columns) && in_array('catatan', $columns) && in_array('status', $columns)) {
                // Jika semua kolom ada kecuali pembayaran
                $query = "INSERT INTO orders (nama_pelanggan, menu, harga, jumlah, total, catatan, status) 
                          VALUES ('$nama', '$menu', '$harga', '$jumlah', '$total', '$catatan', 'pending')";
            } elseif (in_array('harga', $columns) && in_array('status', $columns)) {
                // Jika ada harga dan status, tapi tidak ada catatan
                $query = "INSERT INTO orders (nama_pelanggan, menu, harga, jumlah, total, status) 
                          VALUES ('$nama', '$menu', '$harga', '$jumlah', '$total', 'pending')";
            } elseif (in_array('harga', $columns)) {
                // Jika hanya ada harga
                $query = "INSERT INTO orders (nama_pelanggan, menu, harga, jumlah, total) 
                          VALUES ('$nama', '$menu', '$harga', '$jumlah', '$total')";
            } elseif (in_array('status', $columns)) {
                // Jika hanya ada status
                $query = "INSERT INTO orders (nama_pelanggan, menu, jumlah, total, status) 
                          VALUES ('$nama', '$menu', '$jumlah', '$total', 'pending')";
            } else {
                // Minimal structure (default)
                $query = "INSERT INTO orders (nama_pelanggan, menu, jumlah, total) 
                          VALUES ('$nama', '$menu', '$jumlah', '$total')";
            }
            
            if (mysqli_query($conn, $query)) {
                $new_order_id = mysqli_insert_id($conn);
                $order_ids[] = $new_order_id;
                
                // Simpan ID pertama untuk referensi
                if ($first_order_id == 0) {
                    $first_order_id = $new_order_id;
                }
                
                // Update session_id untuk order pertama
                if ($i == 0 && in_array('session_id', $columns)) {
                    // Update semua item dengan session_id yang sama
                    mysqli_query($conn, "UPDATE orders SET session_id = '$session_id' WHERE id = '$new_order_id'");
                }
            } else {
                echo "<div class='alert alert-danger'>Error menyimpan item: " . mysqli_error($conn) . "</div>";
                echo "<a href='order.php' class='btn btn-primary'>Kembali ke Keranjang</a>";
                exit();
            }
        }
        
        // Update session_id untuk semua item yang baru saja dibuat
        if (in_array('session_id', $columns) && !empty($order_ids)) {
            $order_ids_str = implode(',', $order_ids);
            mysqli_query($conn, "UPDATE orders SET session_id = '$session_id' WHERE id IN ($order_ids_str)");
        }
        
        // Simpan order_ids ke session untuk tracking
        $_SESSION['order_ids'] = $order_ids;
        $_SESSION['last_order_name'] = $nama;
        $_SESSION['metode_pembayaran'] = $metode_pembayaran;
        $_SESSION['order_session_id'] = $session_id;
        
        // Success page untuk multiple items
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Pesanan Berhasil - Restoran Kita</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <link rel="stylesheet" href="assets/css/style.css">
            <style>
                :root {
                    --primary: #FF6B6B;
                    --primary-dark: #FF5252;
                    --primary-light: #FFE5E5;
                    --secondary: #4ECDC4;
                    --secondary-dark: #36B9B0;
                    --accent: #FF9F43;
                    --dark: #1A1F36;
                    --light-bg: #F8FAFC;
                    --gradient-primary: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
                    --gradient-secondary: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
                    --border-radius-xl: 24px;
                    --border-radius-lg: 20px;
                    --border-radius-md: 16px;
                    --shadow-medium: 0 8px 30px rgba(0, 0, 0, 0.08);
                }

                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background-color: var(--light-bg);
                    color: var(--dark);
                    min-height: 100vh;
                }

                /* Success Container */
                .success-container {
                    max-width: 800px;
                    margin: 50px auto;
                    background: white;
                    border-radius: var(--border-radius-xl);
                    box-shadow: var(--shadow-medium);
                    overflow: hidden;
                    border-top: 5px solid var(--primary);
                }

                /* Header Success */
                .success-header {
                    background: var(--gradient-primary);
                    color: white;
                    padding: 40px 30px;
                    text-align: center;
                    position: relative;
                }

                .success-icon {
                    width: 80px;
                    height: 80px;
                    background: rgba(255, 255, 255, 0.2);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                    font-size: 2.5rem;
                }

                .success-header h1 {
                    font-weight: 700;
                    margin-bottom: 10px;
                }

                .success-header p {
                    opacity: 0.9;
                    font-size: 1.1rem;
                }

                /* QRIS Section */
                .qris-section {
                    padding: 30px;
                    text-align: center;
                    background: #f8f9fa;
                    margin: 20px;
                    border-radius: var(--border-radius-lg);
                    border: 2px solid var(--secondary);
                }

                .qris-title {
                    color: var(--dark);
                    margin-bottom: 20px;
                    font-weight: 700;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                }

                .qris-code {
                    max-width: 300px;
                    margin: 20px auto;
                    padding: 20px;
                    background: white;
                    border-radius: 15px;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                }

                .qris-code img {
                    width: 100%;
                    height: auto;
                    border-radius: 10px;
                }

                .payment-amount {
                    font-size: 1.8rem;
                    font-weight: 800;
                    color: var(--primary);
                    margin: 15px 0;
                }

                .timer-container {
                    margin: 15px 0;
                    padding: 15px;
                    background: #fff3cd;
                    border-radius: 10px;
                    border: 1px solid #ffecb5;
                }

                .timer {
                    font-size: 2rem;
                    font-weight: 700;
                    color: #d35400;
                    font-family: monospace;
                }

                .payment-instructions {
                    text-align: left;
                    background: white;
                    padding: 20px;
                    border-radius: 15px;
                    margin-top: 20px;
                    border-left: 4px solid var(--secondary);
                }

                .payment-instructions ol {
                    margin-bottom: 0;
                    padding-left: 20px;
                }

                .payment-instructions li {
                    margin-bottom: 8px;
                    color: var(--dark);
                }

                /* Order Details */
                .order-details {
                    padding: 30px;
                }

                .detail-section {
                    margin-bottom: 30px;
                }

                .section-title {
                    font-weight: 700;
                    color: var(--dark);
                    margin-bottom: 20px;
                    padding-bottom: 10px;
                    border-bottom: 2px solid var(--primary-light);
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                .info-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 15px;
                }

                .info-item {
                    display: flex;
                    justify-content: space-between;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 10px;
                    border-left: 3px solid var(--primary);
                }

                .info-label {
                    font-weight: 600;
                    color: #666;
                }

                .info-value {
                    font-weight: 700;
                    color: var(--dark);
                }

                /* Items Table */
                .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                    background: white;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                }

                .items-table th {
                    background: var(--primary-light);
                    padding: 15px;
                    text-align: left;
                    font-weight: 700;
                    color: var(--dark);
                    border-bottom: 2px solid var(--primary);
                }

                .items-table td {
                    padding: 15px;
                    border-bottom: 1px solid #eee;
                }

                .items-table tr:last-child td {
                    border-bottom: none;
                }

                /* Total Section */
                .total-section {
                    background: linear-gradient(135deg, var(--dark) 0%, #2D3748 100%);
                    color: white;
                    padding: 25px;
                    border-radius: 15px;
                    margin-top: 30px;
                    text-align: right;
                }

                .grand-total {
                    font-size: 2.2rem;
                    font-weight: 800;
                    color: var(--secondary);
                }

                /* Button Group */
                .btn-group {
                    display: flex;
                    gap: 15px;
                    margin-top: 30px;
                    flex-wrap: wrap;
                    justify-content: center;
                }

                .btn-print {
                    background: linear-gradient(135deg, #6c757d, #495057);
                    border: none;
                    color: white;
                    padding: 12px 25px;
                    border-radius: 50px;
                    font-weight: 600;
                    display: inline-flex;
                    align-items: center;
                    transition: all 0.3s ease;
                }

                .btn-print:hover {
                    background: linear-gradient(135deg, #5a6268, #343a40);
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
                }

                .btn-home {
                    background: var(--gradient-primary);
                    border: none;
                    color: white;
                    padding: 12px 25px;
                    border-radius: 50px;
                    font-weight: 600;
                    display: inline-flex;
                    align-items: center;
                    transition: all 0.3s ease;
                }

                .btn-home:hover {
                    background: linear-gradient(135deg, #ff5252, #ff6b6b);
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
                }

                .btn-order-again {
                    background: var(--gradient-secondary);
                    border: none;
                    color: white;
                    padding: 12px 25px;
                    border-radius: 50px;
                    font-weight: 600;
                    display: inline-flex;
                    align-items: center;
                    transition: all 0.3s ease;
                }

                .btn-order-again:hover {
                    background: linear-gradient(135deg, #3db9af, #398d7a);
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(78, 205, 196, 0.3);
                }

                /* Status Badges */
                .badge {
                    padding: 8px 15px;
                    border-radius: 50px;
                    font-weight: 600;
                    font-size: 0.9rem;
                }

                .badge-pending {
                    background: #ffc107;
                    color: #000;
                }

                .badge-processing {
                    background: #17a2b8;
                    color: white;
                }

                .badge-completed {
                    background: #28a745;
                    color: white;
                }

                .badge-cash {
                    background: #20c997;
                    color: white;
                }

                .badge-qris {
                    background: #007bff;
                    color: white;
                }

                .badge-payment-pending {
                    background: #ffc107;
                    color: #000;
                }

                .badge-payment-paid {
                    background: #28a745;
                    color: white;
                }

                /* Print Styles */
                @media print {
                    .no-print, .btn-group, .qris-section, .timer-container {
                        display: none !important;
                    }
                    
                    body {
                        margin: 0;
                        padding: 0;
                        background: white !important;
                    }
                    
                    .success-container {
                        max-width: 100%;
                        margin: 0;
                        box-shadow: none;
                        border: none;
                    }
                    
                    .success-header {
                        background: #f8f9fa !important;
                        color: #000 !important;
                        padding: 20px !important;
                    }
                    
                    .success-icon {
                        background: #dee2e6 !important;
                        color: #000 !important;
                    }
                }

                @media (max-width: 768px) {
                    .success-container {
                        margin: 20px;
                        border-radius: 15px;
                    }
                    
                    .success-header {
                        padding: 30px 20px;
                    }
                    
                    .order-details {
                        padding: 20px;
                    }
                    
                    .qris-section {
                        margin: 15px;
                        padding: 20px;
                    }
                    
                    .btn-group {
                        flex-direction: column;
                        align-items: center;
                    }
                    
                    .btn-print, .btn-home, .btn-order-again {
                        width: 100%;
                        max-width: 300px;
                        justify-content: center;
                    }
                }
            </style>
        </head>
        <body>
            <nav class="navbar navbar-expand-lg navbar-dark no-print" style="background: var(--dark);">
                <div class="container">
                    <a class="navbar-brand" href="index.php">
                        <i class="fas fa-utensils me-2"></i>Restoran Kita
                    </a>
                    <div class="navbar-text text-white">
                        <i class="fas fa-clock me-2"></i>
                        <span id="current-time"><?php echo date('H:i:s'); ?></span>
                    </div>
                </div>
            </nav>
            
            <div class="container">
                <div class="success-container">
                    <!-- Success Header -->
                    <div class="success-header">
                        <div class="success-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <h1>Pesanan Berhasil!</h1>
                        <p>Terima kasih <strong><?php echo htmlspecialchars($nama); ?></strong> telah memesan di Restoran Kita.</p>
                    </div>
                    
                    <?php if($metode_pembayaran == 'qris'): ?>
                    <!-- QRIS Payment Section -->
                    <div class="qris-section">
                        <h3 class="qris-title">
                            <i class="fas fa-qrcode"></i> Pembayaran QRIS
                        </h3>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Harap lakukan pembayaran segera!</strong> Pesanan Anda akan diproses setelah pembayaran dikonfirmasi.
                        </div>
                        
                        <div class="payment-amount">
                            Rp <?php echo number_format($total_all); ?>
                        </div>
                        
                        <div class="timer-container">
                            <p class="mb-2"><i class="fas fa-clock me-2"></i>Selesaikan dalam:</p>
                            <div class="timer" id="payment-timer">15:00</div>
                            <small class="text-muted">Waktu tersisa untuk pembayaran</small>
                        </div>
                        
                        <div class="qris-code">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=RESTORAN-KITA-ORDER-<?php echo $session_id; ?>-<?php echo $total_all; ?>&format=png&color=1a1f36&bgcolor=f8fafc&margin=10" 
                                 alt="QRIS Payment Code">
                            <p class="mt-2 mb-0">
                                <small class="text-muted">Scan QR code untuk pembayaran</small>
                            </p>
                        </div>
                        
                        <div class="payment-instructions">
                            <h6><i class="fas fa-list-ol me-2"></i>Instruksi Pembayaran:</h6>
                            <ol>
                                <li>Buka aplikasi e-wallet atau mobile banking Anda</li>
                                <li>Pilih fitur <strong>"Scan QRIS"</strong> atau <strong>"Bayar QR Code"</strong></li>
                                <li>Arahkan kamera ke QR Code di atas</li>
                                <li>Pastikan jumlah: <strong>Rp <?php echo number_format($total_all); ?></strong></li>
                                <li>Konfirmasi dan selesaikan pembayaran</li>
                                <li>Tunjukkan bukti pembayaran ke kasir untuk konfirmasi</li>
                            </ol>
                        </div>
                        
                        <div class="mt-4">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Penting:</strong> QR Code ini hanya berlaku untuk pesanan ini. Simpan bukti pembayaran Anda.
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Order Details -->
                    <div class="order-details">
                        <!-- Order Information -->
                        <div class="detail-section">
                            <h4 class="section-title">
                                <i class="fas fa-receipt"></i> Informasi Pesanan
                            </h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Nomor Pesanan</span>
                                    <span class="info-value">#<?php echo str_pad($first_order_id, 6, '0', STR_PAD_LEFT); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Nama Pelanggan</span>
                                    <span class="info-value"><?php echo htmlspecialchars($nama); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Tanggal Pesan</span>
                                    <span class="info-value" id="order-time"><?php echo $waktu_cetak; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Metode Pembayaran</span>
                                    <span class="info-value">
                                        <span class="badge badge-<?php echo $metode_pembayaran; ?>">
                                            <?php echo strtoupper($metode_pembayaran); ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Status Pembayaran</span>
                                    <span class="info-value">
                                        <span class="badge badge-payment-pending" id="payment-status">
                                            <?php echo $metode_pembayaran == 'qris' ? 'Menunggu Pembayaran' : 'Bayar di Kasir'; ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Jumlah Pesanan</span>
                                    <span class="info-value"><?php echo count($menus); ?> item</span>
                                </div>
                                <?php if(!empty($catatan)): ?>
                                <div class="info-item" style="grid-column: span 2;">
                                    <span class="info-label">Catatan</span>
                                    <span class="info-value"><?php echo htmlspecialchars($catatan); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Order Status -->
                        <div class="detail-section">
                            <h4 class="section-title">
                                <i class="fas fa-truck"></i> Status Pesanan
                            </h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Status Pesanan</span>
                                    <span class="info-value">
                                        <span class="badge badge-pending" id="order-status">Menunggu Konfirmasi</span>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Terakhir Diperbarui</span>
                                    <span class="info-value" id="last-updated">Baru saja</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Items -->
                        <div class="detail-section">
                            <h4 class="section-title">
                                <i class="fas fa-utensils"></i> Detail Pesanan
                            </h4>
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Menu</th>
                                        <th>Harga</th>
                                        <th>Jumlah</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for($i = 0; $i < count($menus); $i++): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo htmlspecialchars($menus[$i]); ?></td>
                                        <td>Rp <?php echo number_format($hargas[$i]); ?></td>
                                        <td><?php echo $jumlahs[$i]; ?></td>
                                        <td>Rp <?php echo number_format($hargas[$i] * $jumlahs[$i]); ?></td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                            
                            <div class="total-section">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1 text-white-50">Total Pembayaran</h5>
                                        <p class="mb-0 text-white-50"><small>Sudah termasuk semua biaya</small></p>
                                    </div>
                                    <div class="grand-total">
                                        Rp <?php echo number_format($total_all); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="btn-group no-print">
                            <button onclick="window.print()" class="btn-print">
                                <i class="fas fa-print me-2"></i>Cetak Invoice
                            </button>
                            <a href="index.php" class="btn-home">
                                <i class="fas fa-home me-2"></i>Kembali ke Beranda
                            </a>
                            <a href="index.php" class="btn-order-again">
                                <i class="fas fa-plus me-2"></i>Pesan Lagi
                            </a>
                        </div>
                        
                        <div class="mt-4 text-center text-muted">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Halaman ini akan memperbarui status secara otomatis setiap 10 detik
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                // Simpan data order untuk tracking
                const orderIds = <?php echo json_encode($order_ids); ?>;
                const paymentMethod = '<?php echo $metode_pembayaran; ?>';
                const sessionId = '<?php echo $session_id; ?>';
                const totalAmount = <?php echo $total_all; ?>;
                
                // Timer untuk pembayaran QRIS
                <?php if($metode_pembayaran == 'qris'): ?>
                let paymentTimer = 15 * 60; // 15 menit dalam detik
                const timerElement = document.getElementById('payment-timer');
                
                function updatePaymentTimer() {
                    const minutes = Math.floor(paymentTimer / 60);
                    const seconds = paymentTimer % 60;
                    
                    timerElement.textContent = 
                        minutes.toString().padStart(2, '0') + ':' + 
                        seconds.toString().padStart(2, '0');
                    
                    if (paymentTimer <= 0) {
                        timerElement.textContent = "00:00";
                        timerElement.style.color = "#dc3545";
                        
                        // Tampilkan peringatan
                        if (!localStorage.getItem('timer_expired_shown')) {
                            alert('Waktu pembayaran QRIS telah habis! Silakan hubungi kasir untuk bantuan.');
                            localStorage.setItem('timer_expired_shown', 'true');
                        }
                    } else {
                        paymentTimer--;
                    }
                }
                
                // Jalankan timer setiap detik
                setInterval(updatePaymentTimer, 1000);
                updatePaymentTimer(); // Jalankan sekali di awal
                <?php endif; ?>
                
                // Fungsi untuk memperbarui waktu real-time
                function updateRealTime() {
                    const now = new Date();
                    const timeString = now.toLocaleTimeString('id-ID', { 
                        hour: '2-digit', 
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    const dateString = now.toLocaleDateString('id-ID', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric'
                    });
                    const fullDateTime = `${dateString} ${timeString}`;
                    
                    // Update waktu di navbar
                    const currentTimeElement = document.getElementById('current-time');
                    if (currentTimeElement) {
                        currentTimeElement.textContent = timeString;
                    }
                    
                    // Update waktu pesanan
                    const orderTimeElement = document.getElementById('order-time');
                    if (orderTimeElement) {
                        orderTimeElement.textContent = fullDateTime;
                    }
                }
                
                // Fungsi untuk memperbarui status pesanan
                function updateOrderStatus() {
                    if (orderIds.length === 0) return;
                    
                    // Ambil order ID pertama sebagai referensi
                    const firstOrderId = orderIds[0];
                    
                    // Kirim permintaan AJAX untuk mendapatkan status terbaru
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', `get_order_status.php?order_id=${firstOrderId}&session_id=${sessionId}`, true);
                    
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                
                                // Perbarui status pesanan
                                if (response.status) {
                                    const statusElement = document.getElementById('order-status');
                                    const lastUpdatedElement = document.getElementById('last-updated');
                                    
                                    // Hapus class badge sebelumnya
                                    statusElement.className = 'badge';
                                    
                                    // Tambahkan class baru berdasarkan status
                                    if (response.status === 'pending') {
                                        statusElement.textContent = 'Menunggu Konfirmasi';
                                        statusElement.classList.add('badge-pending');
                                    } else if (response.status === 'processing') {
                                        statusElement.textContent = 'Sedang Diproses';
                                        statusElement.classList.add('badge-processing');
                                    } else if (response.status === 'completed') {
                                        statusElement.textContent = 'Selesai';
                                        statusElement.classList.add('badge-completed');
                                        
                                        // Tampilkan notifikasi jika selesai
                                        showCompletionNotification();
                                    }
                                    
                                    // Perbarui waktu terakhir update
                                    const now = new Date();
                                    const timeString = now.toLocaleTimeString('id-ID', { 
                                        hour: '2-digit', 
                                        minute: '2-digit'
                                    });
                                    lastUpdatedElement.textContent = timeString;
                                }
                                
                                // Perbarui status pembayaran
                                if (response.payment_status) {
                                    updatePaymentStatus(response.payment_status);
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                            }
                        }
                    };
                    
                    xhr.onerror = function() {
                        console.error('Request failed');
                    };
                    
                    xhr.send();
                }
                
                // Fungsi untuk memperbarui status pembayaran
                function updatePaymentStatus(paymentStatus) {
                    const paymentStatusElement = document.getElementById('payment-status');
                    if (!paymentStatusElement) return;
                    
                    // Hapus class lama
                    paymentStatusElement.className = 'badge';
                    
                    // Tambahkan class baru
                    if (paymentStatus === 'paid') {
                        paymentStatusElement.textContent = 'Lunas';
                        paymentStatusElement.classList.add('badge-payment-paid');
                        
                        // Jika QRIS dan sudah dibayar, sembunyikan timer
                        if (paymentMethod === 'qris') {
                            const timerContainer = document.querySelector('.timer-container');
                            if (timerContainer) {
                                timerContainer.innerHTML = `
                                    <div class="alert alert-success mb-0">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Pembayaran Diterima!</strong> Terima kasih telah melakukan pembayaran.
                                    </div>
                                `;
                            }
                        }
                    } else if (paymentStatus === 'pending') {
                        paymentStatusElement.textContent = paymentMethod === 'qris' ? 'Menunggu Pembayaran' : 'Bayar di Kasir';
                        paymentStatusElement.classList.add('badge-payment-pending');
                    }
                }
                
                // Fungsi untuk menampilkan notifikasi ketika pesanan selesai
                function showCompletionNotification() {
                    // Cek apakah sudah menampilkan notifikasi sebelumnya
                    if (sessionStorage.getItem('notification_shown') === 'true') {
                        return;
                    }
                    
                    // Buat notifikasi
                    const notification = document.createElement('div');
                    notification.className = 'alert alert-success position-fixed';
                    notification.style.top = '20px';
                    notification.style.right = '20px';
                    notification.style.zIndex = '1050';
                    notification.style.minWidth = '300px';
                    notification.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Pesanan Selesai!</strong> Pesanan Anda telah selesai diproses.
                    `;
                    
                    // Tambahkan ke body
                    document.body.appendChild(notification);
                    
                    // Simpan status notifikasi
                    sessionStorage.setItem('notification_shown', 'true');
                    
                    // Hapus notifikasi setelah 5 detik
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.remove();
                        }
                    }, 5000);
                }
                
                // Fungsi untuk memulai polling status
                function startStatusPolling() {
                    // Perbarui status segera setelah halaman dimuat
                    updateOrderStatus();
                    
                    // Perbarui status setiap 10 detik
                    setInterval(updateOrderStatus, 10000);
                }
                
                // Initialize
                document.addEventListener('DOMContentLoaded', function() {
                    // Perbarui waktu real-time
                    updateRealTime();
                    setInterval(updateRealTime, 1000);
                    
                    // Mulai polling status
                    startStatusPolling();
                    
                    // Clear session cart
                    sessionStorage.removeItem('cart_items');
                    localStorage.removeItem('timer_expired_shown');
                    sessionStorage.removeItem('notification_shown');
                    
                    // Tampilkan reminder untuk QRIS
                    <?php if($metode_pembayaran == 'qris'): ?>
                    setTimeout(() => {
                        alert('Harap lakukan pembayaran QRIS segera! Tunjukkan bukti pembayaran ke kasir untuk konfirmasi.');
                    }, 1000);
                    <?php endif; ?>
                });
            </script>
            
            <!-- Bootstrap JS -->
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
    } else {
        // Fallback untuk single item
        $menu = isset($_POST['menu']) ? mysqli_real_escape_string($conn, $_POST['menu']) : '';
        $harga = isset($_POST['harga']) ? (int)$_POST['harga'] : 0;
        $jumlah = isset($_POST['jumlah']) ? (int)$_POST['jumlah'] : 1;
        $total = $jumlah * $harga;
        
        // Cek apakah nama pelanggan sudah ada dan masih dalam proses
        $check_customer_query = "SELECT * FROM orders 
                                WHERE nama_pelanggan = '$nama' 
                                AND status IN ('pending', 'processing')
                                AND DATE(created_at) = CURDATE()
                                LIMIT 1";
        $check_customer_result = mysqli_query($conn, $check_customer_query);
        
        if (mysqli_num_rows($check_customer_result) > 0) {
            // Nama sudah digunakan hari ini dan masih ada pesanan yang belum selesai
            echo "<script>
                alert('Maaf, nama \"$nama\" sudah digunakan untuk pesanan yang belum selesai hari ini. Silakan gunakan nama lain atau tunggu pesanan sebelumnya selesai.');
                window.history.back();
            </script>";
            exit();
        }
        
        // Generate unique session_id
        $session_id = uniqid($nama . '_', true);

        // Cek struktur tabel untuk mengetahui kolom yang tersedia
        $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM orders");
        $columns = [];
        while ($row = mysqli_fetch_assoc($check_columns)) {
            $columns[] = $row['Field'];
        }
        
        // Bangun query INSERT berdasarkan kolom yang tersedia
        if (in_array('session_id', $columns) && in_array('metode_pembayaran', $columns) && in_array('status_pembayaran', $columns) && in_array('waktu_pesan', $columns)) {
            $query = "INSERT INTO orders (nama_pelanggan, menu, harga, jumlah, total, catatan, status, metode_pembayaran, status_pembayaran, waktu_pesan, session_id) 
                      VALUES ('$nama', '$menu', '$harga', '$jumlah', '$total', '$catatan', 'pending', '$metode_pembayaran', 'pending', '$waktu_pesan', '$session_id')";
        } elseif (in_array('metode_pembayaran', $columns) && in_array('status_pembayaran', $columns) && in_array('waktu_pesan', $columns)) {
            $query = "INSERT INTO orders (nama_pelanggan, menu, harga, jumlah, total, catatan, status, metode_pembayaran, status_pembayaran, waktu_pesan) 
                      VALUES ('$nama', '$menu', '$harga', '$jumlah', '$total', '$catatan', 'pending', '$metode_pembayaran', 'pending', '$waktu_pesan')";
        } elseif (in_array('metode_pembayaran', $columns) && in_array('status_pembayaran', $columns)) {
            $query = "INSERT INTO orders (nama_pelanggan, menu, harga, jumlah, total, catatan, status, metode_pembayaran, status_pembayaran) 
                      VALUES ('$nama', '$menu', '$harga', '$jumlah', '$total', '$catatan', 'pending', '$metode_pembayaran', 'pending')";
        } elseif (in_array('harga', $columns) && in_array('catatan', $columns) && in_array('status', $columns)) {
            $query = "INSERT INTO orders (nama_pelanggan, menu, harga, jumlah, total, catatan, status) 
                      VALUES ('$nama', '$menu', '$harga', '$jumlah', '$total', '$catatan', 'pending')";
        } elseif (in_array('harga', $columns) && in_array('status', $columns)) {
            $query = "INSERT INTO orders (nama_pelanggan, menu, harga, jumlah, total, status) 
                      VALUES ('$nama', '$menu', '$harga', '$jumlah', '$total', 'pending')";
        } elseif (in_array('harga', $columns)) {
            $query = "INSERT INTO orders (nama_pelanggan, menu, harga, jumlah, total) 
                      VALUES ('$nama', '$menu', '$harga', '$jumlah', '$total')";
        } elseif (in_array('status', $columns)) {
            $query = "INSERT INTO orders (nama_pelanggan, menu, jumlah, total, status) 
                      VALUES ('$nama', '$menu', '$jumlah', '$total', 'pending')";
        } else {
            $query = "INSERT INTO orders (nama_pelanggan, menu, jumlah, total) 
                      VALUES ('$nama', '$menu', '$jumlah', '$total')";
        }
        
        if (mysqli_query($conn, $query)) {
            $order_id = mysqli_insert_id($conn);
            
            // Simpan data ke session untuk tracking
            $_SESSION['order_id'] = $order_id;
            $_SESSION['last_order_name'] = $nama;
            $_SESSION['metode_pembayaran'] = $metode_pembayaran;
            $_SESSION['order_session_id'] = $session_id;
            
            // Success page untuk single item
            // ... (kode success page single item tetap sama)
            
        } else {
            echo "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
            echo "<a href='order.php' class='btn btn-primary'>Kembali ke Keranjang</a>";
        }
    }
    
    // Clear session cart after successful order
    unset($_SESSION['cart']);
    
} else {
    header("Location: index.php");
    exit();
}
?>