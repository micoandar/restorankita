<?php
// Inisialisasi session untuk keranjang belanja
session_start();

// Mengambil data dari URL (untuk entry point dari landing page)
$menu = isset($_GET['menu']) ? $_GET['menu'] : '';
$harga = isset($_GET['harga']) ? $_GET['harga'] : 0;

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Tambah item ke keranjang dari URL
if (!empty($menu) && $harga > 0) {
    $item_exists = false;
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['menu'] == $menu) {
            $_SESSION['cart'][$key]['jumlah'] += 1;
            $_SESSION['cart'][$key]['subtotal'] = $_SESSION['cart'][$key]['harga'] * $_SESSION['cart'][$key]['jumlah'];
            $item_exists = true;
            break;
        }
    }
    
    if (!$item_exists) {
        $_SESSION['cart'][] = [
            'menu' => $menu,
            'harga' => $harga,
            'jumlah' => 1,
            'subtotal' => $harga
        ];
    }
    header("Location: order.php");
    exit();
}

// Hapus item dari keranjang
if (isset($_GET['remove'])) {
    $index = $_GET['remove'];
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
    header("Location: order.php");
    exit();
}

// Update jumlah item
if (isset($_POST['update_cart'])) {
    $index = $_POST['index'];
    $new_jumlah = (int)$_POST['jumlah'];
    if (isset($_SESSION['cart'][$index])) {
        if ($new_jumlah > 0) {
            $_SESSION['cart'][$index]['jumlah'] = $new_jumlah;
            $_SESSION['cart'][$index]['subtotal'] = $_SESSION['cart'][$index]['harga'] * $new_jumlah;
        } else {
            unset($_SESSION['cart'][$index]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
        }
    }
    header("Location: order.php");
    exit();
}

// Kosongkan keranjang
if (isset($_GET['clear_cart'])) {
    $_SESSION['cart'] = [];
    header("Location: order.php");
    exit();
}

// Hitung total keranjang
$cart_total = 0;
$cart_count = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_total += $item['subtotal'];
    $cart_count += $item['jumlah'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Restoran Kita</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #FF6B6B;
            --primary-dark: #FF5252;
            --primary-light: #FFE5E5;
            --secondary: #4ECDC4;
            --secondary-dark: #36B9B0;
            --accent: #FF9F43;
            --dark: #1A1F36;
            --dark-soft: #2D3748;
            --light-bg: #F8FAFC;
            --light-card: #FFFFFF;
            --border-color: #E2E8F0;
            --success: #10B981;
            --warning: #F59E0B;
            --border-radius-xl: 24px;
            --border-radius-lg: 20px;
            --border-radius-md: 16px;
            --border-radius-sm: 12px;
            --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.05);
            --shadow-medium: 0 8px 30px rgba(0, 0, 0, 0.08);
            --shadow-heavy: 0 15px 50px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --gradient-primary: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
            --gradient-secondary: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark);
            min-height: 100vh;
            background-image: 
                radial-gradient(at 10% 20%, rgba(255, 107, 107, 0.05) 0px, transparent 50%),
                radial-gradient(at 90% 80%, rgba(78, 205, 196, 0.05) 0px, transparent 50%);
            background-attachment: fixed;
        }

        /* Glassmorphism Effects */
        .glass {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow-soft);
        }

        /* Navbar */
        .navbar {
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            background: rgba(255, 255, 255, 0.92);
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 107, 107, 0.1);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.03);
        }

        .navbar-brand {
            font-weight: 800;
            color: var(--dark) !important;
            letter-spacing: -0.5px;
            font-size: 1.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 22px;
            height: 22px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            color: white;
            box-shadow: 0 2px 10px rgba(255, 107, 107, 0.3);
        }

        /* Main Content */
        .order-wrapper {
            padding-top: 130px;
            padding-bottom: 120px;
        }

        /* Modern Cards */
        .card-modern {
            background: var(--light-card);
            border-radius: var(--border-radius-xl);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: var(--shadow-medium);
            padding: 40px;
            height: 100%;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .card-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient-primary);
        }

        .section-title {
            font-weight: 800;
            font-size: 1.6rem;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--dark);
        }

        .section-title-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient-primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        /* Cart Items */
        .cart-item {
            background: rgba(248, 250, 252, 0.8);
            border-radius: var(--border-radius-md);
            padding: 25px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid transparent;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .cart-item:hover {
            background: white;
            border-color: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-soft);
        }

        .cart-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--gradient-primary);
            opacity: 0;
            transition: var(--transition);
        }

        .cart-item:hover::before {
            opacity: 1;
        }

        .item-info h6 {
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 1.1rem;
            color: var(--dark);
        }

        .item-info span {
            color: var(--primary);
            font-weight: 600;
            font-size: 1rem;
        }

        /* Quantity Control */
        .qty-stepper {
            display: flex;
            align-items: center;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .btn-step {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            background: var(--light-bg);
            color: var(--dark);
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .btn-step:hover {
            background: var(--gradient-primary);
            color: white;
            transform: scale(1.05);
        }

        .qty-input {
            width: 45px;
            text-align: center;
            font-weight: 700;
            border: none;
            font-size: 1rem;
            outline: none;
            background: transparent;
        }

        /* Payment Options */
        .payment-option {
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-md);
            padding: 25px 15px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            height: 100%;
            background: white;
            position: relative;
        }

        .payment-option:hover {
            border-color: var(--primary);
            background: var(--primary-light);
            transform: translateY(-3px);
        }

        .payment-option.active {
            border-color: var(--primary);
            background: var(--primary-light);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.1);
        }

        .payment-option i {
            font-size: 2.2rem;
            margin-bottom: 15px;
            display: block;
        }

        .payment-option.active::after {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 10px;
            width: 24px;
            height: 24px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }

        /* Form Elements */
        .form-label {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--dark-soft);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-control {
            border-radius: var(--border-radius-md);
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            background: white;
            transition: var(--transition);
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(255, 107, 107, 0.15);
            background: white;
            transform: translateY(-1px);
        }

        /* Summary Box */
        .summary-box {
            background: linear-gradient(135deg, var(--dark) 0%, #2D3748 100%);
            color: white;
            border-radius: var(--border-radius-lg);
            padding: 30px;
            margin-top: 30px;
            box-shadow: var(--shadow-medium);
            position: relative;
            overflow: hidden;
        }

        .summary-box::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        .total-amount {
            font-size: 2rem;
            font-weight: 800;
            background: var(--gradient-secondary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* Checkout Button */
        .btn-checkout {
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: var(--border-radius-md);
            padding: 22px;
            font-weight: 700;
            width: 100%;
            margin-top: 25px;
            transition: var(--transition);
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.3);
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .btn-checkout:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(255, 107, 107, 0.4);
        }

        .btn-checkout:active:not(:disabled) {
            transform: translateY(-1px);
        }

        .btn-checkout::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        .btn-checkout:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }

        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(20, 20);
                opacity: 0;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #F8FAFC 0%, #EDF2F7 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 3.5rem;
            color: var(--primary-light);
        }

        .empty-state h5 {
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--dark-soft);
            margin-bottom: 25px;
        }

        /* Fixed Bottom Bar */
        .fixed-bottom-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 20px 25px;
            box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.08);
            z-index: 1001;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top-left-radius: 25px;
            border-top-right-radius: 25px;
            border-top: 1px solid rgba(255, 107, 107, 0.1);
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-in {
            animation: fadeIn 0.6s ease-out forwards;
        }

        /* Loading State */
        .loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            transform: translate(-50%, -50%);
        }

        @keyframes spin {
            to {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .order-wrapper {
                padding-top: 110px;
                padding-bottom: 100px;
            }
            
            .card-modern {
                padding: 25px;
            }
            
            .section-title {
                font-size: 1.4rem;
            }
            
            .cart-item {
                padding: 20px;
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .item-info {
                text-align: center;
            }
            
            .total-amount {
                font-size: 1.7rem;
            }
        }

        @media (max-width: 991px) {
            .fixed-bottom-bar {
                display: flex;
            }
        }

        @media (min-width: 992px) {
            .fixed-bottom-bar {
                display: none;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-utensils me-2"></i>RESTORAN KITA
            </a>
            <div class="d-flex align-items-center">
                <!-- MODIFIKASI: Link ke index.php dengan parameter untuk skip loading dan langsung scroll ke menu -->
                <a href="index.php?from_order=true&scroll_to_menu=true" class="btn btn-outline-primary rounded-pill px-4 me-3 d-none d-md-flex align-items-center">
                    <i class="fas fa-plus-circle me-2"></i> Tambah Menu
                </a>
                <div class="position-relative">
                    <i class="fas fa-shopping-bag fs-4" style="color: var(--primary);"></i>
                    <?php if($cart_count > 0): ?>
                        <div class="cart-badge">
                            <?php echo $cart_count; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container order-wrapper">
        <div class="row g-5">
            <div class="col-lg-7 animate-in">
                <div class="card-modern">
                    <div class="section-title">
                        <div class="section-title-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        Pesanan Anda
                    </div>

                    <?php if(empty($_SESSION['cart'])): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-shopping-basket"></i>
                            </div>
                            <h5 class="fw-800">Keranjang masih kosong</h5>
                            <p class="text-secondary">Mulai petualangan rasa Anda dengan memilih menu favorit!</p>
                            <!-- MODIFIKASI: Link ke index.php dengan parameter untuk skip loading dan langsung scroll ke menu -->
                            <a href="index.php?from_order=true&scroll_to_menu=true" class="btn btn-primary rounded-pill px-4 py-2 fw-bold">
                                <i class="fas fa-utensils me-2"></i> Jelajahi Menu
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="cart-scroll" style="max-height: 500px; overflow-y: auto; padding-right: 10px;">
                            <?php foreach($_SESSION['cart'] as $index => $item): ?>
                                <div class="cart-item">
                                    <div class="item-info">
                                        <h6><?php echo htmlspecialchars($item['menu']); ?></h6>
                                        <span>Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></span>
                                        <div class="mt-2 text-muted small">
                                            Subtotal: <strong>Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></strong>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <form method="post" class="qty-stepper">
                                                <input type="hidden" name="index" value="<?php echo $index; ?>">
                                                <input type="hidden" name="update_cart" value="1">
                                                <button type="button" class="btn-step" 
                                                        onclick="updateQuantity(<?php echo $index; ?>, <?php echo $item['jumlah']-1; ?>)">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" class="qty-input" 
                                                       id="qty-<?php echo $index; ?>" 
                                                       value="<?php echo $item['jumlah']; ?>" 
                                                       readonly>
                                                <button type="button" class="btn-step" 
                                                        onclick="updateQuantity(<?php echo $index; ?>, <?php echo $item['jumlah']+1; ?>)">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <div>
                                            <a href="?remove=<?php echo $index; ?>" 
                                               class="btn btn-outline-danger rounded-circle p-2"
                                               onclick="return confirm('Hapus item ini dari keranjang?')"
                                               title="Hapus Item">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                            <div>
                                <span class="text-muted">Total <?php echo count($_SESSION['cart']); ?> item</span>
                            </div>
                            <div>
                                <a href="?clear_cart" 
                                   class="btn btn-outline-secondary rounded-pill px-3"
                                   onclick="return confirm('Yakin ingin mengosongkan keranjang?')">
                                    <i class="fas fa-trash me-2"></i> Kosongkan
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-5 animate-in" style="animation-delay: 0.2s;">
                <div class="card-modern">
                    <div class="section-title">
                        <div class="section-title-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        Pembayaran
                    </div>

                    <form action="process_order.php" method="post" id="checkoutForm">
                        <?php foreach($_SESSION['cart'] as $item): ?>
                            <input type="hidden" name="menu[]" value="<?php echo htmlspecialchars($item['menu']); ?>">
                            <input type="hidden" name="harga[]" value="<?php echo $item['harga']; ?>">
                            <input type="hidden" name="jumlah[]" value="<?php echo $item['jumlah']; ?>">
                        <?php endforeach; ?>

                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-user text-primary"></i> Nama Pelanggan
                            </label>
                            <input type="text" class="form-control" name="nama" 
                                   placeholder="Masukkan nama lengkap Anda" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-sticky-note text-warning"></i> Catatan Spesial
                            </label>
                            <textarea class="form-control" name="catatan" rows="3" 
                                      placeholder="Contoh: Tanpa bawang, Pedas level 3, Pakai sedikit garam, dll."></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-wallet text-primary"></i> Metode Pembayaran
                            </label>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="payment-option active" id="btn-cash" onclick="selectPay('cash')">
                                        <i class="fas fa-money-bill-wave text-success"></i>
                                        <span class="fw-bold d-block mt-2">Tunai</span>
                                        <small class="text-muted d-block mt-1">Bayar di kasir</small>
                                        <input type="radio" name="metode_pembayaran" value="cash" checked hidden>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="payment-option" id="btn-qris" onclick="selectPay('qris')">
                                        <i class="fas fa-qrcode text-primary"></i>
                                        <span class="fw-bold d-block mt-2">QRIS</span>
                                        <small class="text-muted d-block mt-1">Scan & Bayar</small>
                                        <input type="radio" name="metode_pembayaran" value="qris" hidden>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="summary-box">
                            <h6 class="fw-bold mb-3 text-white-50">Rincian Pembayaran</h6>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="opacity-75">Subtotal (<?php echo $cart_count; ?> item)</span>
                                <span>Rp <?php echo number_format($cart_total, 0, ',', '.'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top border-secondary">
                                <div>
                                    <span class="fw-bold fs-5">Total Tagihan</span>
                                    <br>
                                    <small class="opacity-75">Sudah termasuk semua biaya</small>
                                </div>
                                <span class="total-amount">Rp <?php echo number_format($cart_total, 0, ',', '.'); ?></span>
                            </div>
                        </div>

                        <button type="submit" name="submit" id="submitBtn" class="btn-checkout" 
                                <?php echo empty($_SESSION['cart']) ? 'disabled' : ''; ?>>
                            <i class="fas fa-paper-plane me-2"></i> Konfirmasi & Bayar
                        </button>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="fas fa-lock me-1"></i> Data Anda aman dan terlindungi
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="fixed-bottom-bar d-lg-none">
        <div>
            <span class="text-secondary small d-block">Total Pembayaran</span>
            <span class="fw-800 fs-4 text-primary">Rp <?php echo number_format($cart_total, 0, ',', '.'); ?></span>
        </div>
        <button onclick="submitOrder()" class="btn btn-primary rounded-pill px-4 py-3 fw-bold shadow" 
                <?php echo empty($_SESSION['cart']) ? 'disabled' : ''; ?>>
            <i class="fas fa-check-circle me-2"></i> Pesan Sekarang
        </button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Payment method selection
        function selectPay(method) {
            // Toggle active UI
            document.querySelectorAll('.payment-option').forEach(el => {
                el.classList.remove('active');
            });
            document.getElementById('btn-' + method).classList.add('active');
            
            // Set radio value
            document.querySelector(`input[value="${method}"]`).checked = true;
        }

        // Update quantity function
        function updateQuantity(index, newQty) {
            if (newQty < 1) {
                if (confirm('Hapus item ini dari keranjang?')) {
                    window.location.href = `?remove=${index}`;
                }
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'post';
            form.innerHTML = `
                <input type="hidden" name="index" value="${index}">
                <input type="hidden" name="jumlah" value="${newQty}">
                <input type="hidden" name="update_cart" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Form submission with loading state
        function submitOrder() {
            const form = document.getElementById('checkoutForm');
            const submitBtn = document.getElementById('submitBtn');
            
            // Validate form
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i> Memproses...';
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            
            // Add slight delay for better UX
            setTimeout(() => {
                form.submit();
            }, 800);
        }

        // Handle form submission
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            if (this.querySelector('.loading')) {
                e.preventDefault();
                return false;
            }
            
            const submitBtn = this.querySelector('.btn-checkout');
            submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i> Memproses...';
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            
            // Add smooth animation
            document.querySelectorAll('.animate-in').forEach(el => {
                el.style.opacity = '0.7';
                el.style.transform = 'scale(0.98)';
            });
        });

        // Add ripple effect to buttons
        document.querySelectorAll('.btn-step, .payment-option, .btn-checkout').forEach(button => {
            button.addEventListener('click', function(e) {
                if (this.classList.contains('loading')) return;
                
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const ripple = document.createElement('span');
                ripple.style.position = 'absolute';
                ripple.style.background = 'rgba(255, 255, 255, 0.6)';
                ripple.style.borderRadius = '50%';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s linear';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.width = '100px';
                ripple.style.height = '100px';
                ripple.style.marginLeft = '-50px';
                ripple.style.marginTop = '-50px';
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Initial animation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.animate-in').forEach((el, index) => {
                el.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Add cart item count animation
            const cartBadge = document.querySelector('.cart-badge');
            if (cartBadge) {
                cartBadge.style.transform = 'scale(0)';
                setTimeout(() => {
                    cartBadge.style.transition = 'transform 0.3s ease';
                    cartBadge.style.transform = 'scale(1)';
                }, 300);
            }
        });
    </script>
</body>
</html>