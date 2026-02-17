<?php 
include 'config/database.php';

// Cek apakah request berasal dari order.php (skip loading)
$from_order = isset($_GET['from_order']) && $_GET['from_order'] == 'true';
$scroll_to_menu = isset($_GET['scroll_to_menu']) && $_GET['scroll_to_menu'] == 'true';

// Ambil kategori dari menu untuk tombol filter
$categories_query = mysqli_query($conn, "SELECT DISTINCT kategori FROM menu WHERE kategori IS NOT NULL AND TRIM(kategori) != '' ORDER BY kategori");
$all_categories = ['Semua'];
while ($cat = mysqli_fetch_assoc($categories_query)) {
    if (!empty($cat['kategori'])) {
        $all_categories[] = $cat['kategori'];
    }
}

// Default tampilkan semua menu
$menu_query = "SELECT * FROM menu ORDER BY id DESC";
$result = mysqli_query($conn, $menu_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restoran Kita - Culinary Perfection</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #ff6b6b;
            --secondary: #ffd700;
            --dark: #121212;
            --light: #f8fafc;
            --accent: #4ecdc4;
            --font-main: 'Plus Jakarta Sans', sans-serif;
            --font-title: 'Playfair Display', serif;
            --glass: rgba(255, 255, 255, 0.08);
            --transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            overflow-x: hidden;
            background-color: var(--light);
            font-family: var(--font-main);
            color: var(--dark);
        }

        /* ==================== NEW PREMIUM LOADING SCREEN ==================== */
        .loading-screen {
            position: fixed;
            inset: 0;
            background: #0f0f0f; /* Lebih gelap untuk kontras */
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            transition: opacity 0.8s ease-in-out, transform 0.8s ease-in-out;
        }

        /* Container Spinner */
        .loader-wrapper {
            position: relative;
            width: 150px;
            height: 150px;
            margin-bottom: 30px;
        }

        /* Lingkaran Luar */
        .loader-ring {
            position: absolute;
            inset: 0;
            border: 4px solid transparent;
            border-top-color: var(--primary);
            border-right-color: var(--secondary);
            border-radius: 50%;
            animation: spinRing 1.5s cubic-bezier(0.4, 0, 0.2, 1) infinite;
            box-shadow: 0 0 20px rgba(255, 107, 107, 0.2);
        }

        /* Lingkaran Dalam */
        .loader-ring::before {
            content: '';
            position: absolute;
            inset: 15px;
            border: 4px solid transparent;
            border-bottom-color: var(--secondary);
            border-left-color: var(--primary);
            border-radius: 50%;
            animation: spinRingReverse 2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
        }

        /* Icon Tengah */
        .loader-icon {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            animation: pulseIcon 2s ease-in-out infinite;
        }

        /* Teks Judul dengan Efek Shimmer */
        .loader-title {
            font-family: var(--font-title);
            font-size: 2rem;
            letter-spacing: 5px;
            font-weight: 700;
            background: linear-gradient(90deg, #fff, var(--secondary), #fff);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shineText 3s linear infinite;
            margin-bottom: 10px;
        }

        /* Persentase Loading */
        .loader-percent {
            font-family: monospace;
            color: rgba(255, 255, 255, 0.6);
            font-size: 1rem;
            letter-spacing: 2px;
        }

        /* Animations */
        @keyframes spinRing {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes spinRingReverse {
            0% { transform: rotate(360deg); }
            100% { transform: rotate(0deg); }
        }

        @keyframes pulseIcon {
            0%, 100% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 1; text-shadow: 0 0 15px var(--primary); }
        }

        @keyframes shineText {
            to { background-position: 200% center; }
        }

        /* Class untuk menyembunyikan loader saat selesai */
        .loading-screen.fade-out {
            opacity: 0;
            transform: scale(1.1); /* Efek zoom out sedikit saat hilang */
            pointer-events: none;
        }

        /* ==================== NAVIGATION ==================== */
        .navbar {
            padding: 20px 0;
            transition: var(--transition);
            z-index: 1000;
        }

        .navbar.scrolled {
            padding: 12px 0;
            background: rgba(18, 18, 18, 0.85) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .navbar-brand {
            font-family: var(--font-title);
            font-size: 1.8rem;
            font-weight: 800;
            color: white !important;
        }

        .nav-link {
            font-weight: 500;
            color: rgba(255,255,255,0.8) !important;
            margin: 0 15px;
            position: relative;
            transition: var(--transition);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--secondary);
            transition: var(--transition);
        }

        .nav-link:hover::after { width: 100%; }
        .nav-link:hover { color: var(--secondary) !important; }

        /* ==================== HERO SECTION ==================== */
        .hero {
            height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            background: var(--dark);
            overflow: hidden;
            color: white;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.7)), 
                              url('https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            transform: scale(1.1);
            transition: transform 0.1s linear;
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero h1 {
            font-family: var(--font-title);
            font-size: clamp(3rem, 8vw, 5.5rem);
            line-height: 1.1;
            margin-bottom: 25px;
            opacity: 0;
            transform: translateY(30px);
            text-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .hero p {
            font-size: 1.2rem;
            color: rgba(255,255,255,0.9);
            max-width: 600px;
            margin-bottom: 40px;
            opacity: 0;
            transform: translateY(30px);
            text-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }

        /* Style khusus untuk hero yang langsung muncul (dari order.php) */
        .hero.immediate-show h1,
        .hero.immediate-show .hero-subtitle,
        .hero.immediate-show p,
        .hero.immediate-show #heroBtn {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }

        .btn-modern {
            padding: 18px 40px;
            border-radius: 0;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn-primary-custom {
            background: var(--primary);
            color: white;
            border: none;
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3);
        }

        .btn-primary-custom:hover {
            color: var(--dark);
            background: var(--secondary);
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(255, 215, 0, 0.4);
        }

        /* ==================== MENU SECTION ==================== */
        .section-padding { padding: 120px 0; }

        .section-header {
            text-align: center;
            margin-bottom: 80px;
        }

        .section-header h2 {
            font-family: var(--font-title);
            font-size: 3.5rem;
            margin-bottom: 20px;
        }

        .category-filter {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 50px;
        }

        .filter-btn {
            background: white;
            border: 1px solid #eee;
            padding: 12px 30px;
            border-radius: 100px;
            font-weight: 600;
            color: var(--dark);
            transition: var(--transition);
        }

        .filter-btn.active, .filter-btn:hover {
            background: var(--dark);
            color: white;
            border-color: var(--dark);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }

        .card-menu {
            background: white;
            border: none;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0,0,0,0.05);
            transition: var(--transition);
            height: 100%;
        }

        .card-menu:hover {
            transform: translateY(-15px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.12);
        }

        .img-wrapper {
            position: relative;
            height: 280px;
            overflow: hidden;
        }

        .card-menu img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 1s ease;
        }

        .card-menu:hover img { transform: scale(1.15); }

        .category-tag {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255,255,255,0.9);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--primary);
            z-index: 2;
        }

        .card-content { padding: 30px; }

        .card-content h3 {
            font-family: var(--font-title);
            font-size: 1.6rem;
            margin-bottom: 15px;
        }

        .price-tag {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
            display: block;
            margin-top: 20px;
        }

        /* ==================== FOOTER ==================== */
        footer {
            background: var(--dark);
            color: white;
            padding: 100px 0 50px;
        }

        .footer-logo {
            font-family: var(--font-title);
            font-size: 2.5rem;
            color: var(--secondary);
            margin-bottom: 30px;
            display: block;
            text-decoration: none;
        }

        .social-circle {
            width: 45px;
            height: 45px;
            background: rgba(255,255,255,0.05);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 10px;
            color: white;
            text-decoration: none;
            transition: var(--transition);
        }

        .social-circle:hover {
            background: var(--primary);
            transform: translateY(-5px);
        }

        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: all 1s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body <?php if($from_order && $scroll_to_menu): ?>onload="scrollToMenuSection()"<?php endif; ?>>

    <?php if(!$from_order): ?>
    <div class="loading-screen" id="loader">
        <div class="loader-wrapper">
            <div class="loader-ring"></div>
            <div class="loader-icon">
                <i class="fas fa-utensils"></i>
            </div>
        </div>
        <div class="loader-title">RESTORAN KITA</div>
        <div class="loader-percent" id="loaderPercent">0%</div>
    </div>
    <?php endif; ?>

    <div id="page-content" style="<?php 
        if($from_order) { 
            echo 'display:block; opacity:1;'; 
        } else { 
            echo 'display:none; opacity:0; transition: opacity 1s ease;'; 
        } 
    ?>">
        
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
            <div class="container">
                <a class="navbar-brand" href="index.php">RESTORAN KITA</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="index.php">Beranda</a></li>
                        <li class="nav-item"><a class="nav-link" href="#menu">Menu</a></li>
                        <li class="nav-item"><a class="nav-link" href="#contact">Kontak</a></li>
                        <li class="nav-item">
                            <a class="btn btn-outline-warning ms-lg-3 px-4" href="admin/login.php">ADMIN LOGIN</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <section class="hero <?php echo $from_order ? 'immediate-show' : ''; ?>">
            <div class="hero-bg" id="heroBg"></div>
            <div class="container hero-content">
                <div class="row">
                    <div class="col-lg-8">
                        <span class="text-uppercase fw-bold mb-3 d-block hero-subtitle" style="color: var(--secondary); letter-spacing: 4px;">Premium Dining Experience</span>
                        <h1 id="heroTitle">Serius Enaknya<br>Santai Nikmatnya</h1>
                        <p id="heroPara">Setiap sajian kami diciptakan dari resep berkualitas dan bahan pilihan terbaik, menghadirkan pengalaman bersantap yang berkesan.</p>
                        <div id="heroBtn">
                            <a href="#menu" class="btn btn-modern btn-primary-custom">Lihat Menu Kami</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        
        <section id="menu" class="section-padding">
            <div class="container">
                <div class="section-header reveal">
                    <h2>Spesial Menu Kami</h2>
                    <p class="text-muted">Setiap hidangan memiliki cerita unik di balik kelezatannya</p>
                </div>

                <div class="category-filter reveal">
                    <?php foreach ($all_categories as $category): ?>
                        <button class="filter-btn <?php echo $category === 'Semua' ? 'active' : ''; ?>" 
                                onclick="filterMenu('<?php echo strtolower(str_replace(' ', '-', $category)); ?>', this)">
                            <?php echo $category; ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="menu-grid" id="menu-container">
                    <?php 
                    if ($result && mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $catClass = strtolower(str_replace(' ', '-', $row['kategori']));
                            $img = !empty($row['gambar']) ? 'uploads/'.$row['gambar'] : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600';
                    ?>
                    <div class="menu-item-wrap reveal" data-category="<?php echo $catClass; ?>">
                        <div class="card-menu">
                            <div class="img-wrapper">
                                <span class="category-tag"><?php echo $row['kategori']; ?></span>
                                <img src="<?php echo $img; ?>" alt="<?php echo $row['nama']; ?>">
                            </div>
                            <div class="card-content">
                                <h3><?php echo $row['nama']; ?></h3>
                                <p class="text-muted small"><?php echo substr($row['deskripsi'], 0, 100); ?>...</p>
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <span class="price-tag">Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></span>
                                    <a href="order.php?menu=<?php echo urlencode($row['nama']); ?>&harga=<?php echo $row['harga']; ?>" 
                                       class="btn btn-dark rounded-pill px-4">Pesan</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php 
                        }
                    } 
                    ?>
                </div>
            </div>
        </section>

        <footer id="contact">
            <div class="container">
                <div class="row g-5">
                    <div class="col-lg-5">
                        <a href="index.php" class="footer-logo">RESTORAN KITA</a>
                        <p class="text-white-50 mb-4">Menjadi ikon kuliner kota sejak 2025. Kami terus berinovasi untuk menyajikan hidangan berkualitas yang dapat dinikmati semua kalangan dalam suasana terbaik.</p>
                        <div class="socials">
                            <a href="#" class="social-circle"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="social-circle"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-circle"><i class="fab fa-tiktok"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <h4 class="mb-4">Kontak</h4>
                        <ul class="list-unstyled text-white-50">
                            <li class="mb-3"><i class="fas fa-map-marker-alt me-3 text-warning"></i> Jl. Kuliner No. 45, Kota Kita</li>
                            <li class="mb-3"><i class="fas fa-phone me-3 text-warning"></i> (021) 9876 5432</li>
                            <li class="mb-3"><i class="fas fa-envelope me-3 text-warning"></i> hello@restorankita.com</li>
                        </ul>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <h4 class="mb-4">Waktu Operasional</h4>
                        <div class="d-flex justify-content-between text-white-50 mb-2">
                            <span>Senin - Jumat</span>
                            <span>10:00 - 22:00</span>
                        </div>
                        <div class="d-flex justify-content-between text-white-50">
                            <span>Sabtu - Minggu</span>
                            <span>09:00 - 23:00</span>
                        </div>
                    </div>
                </div>
                <hr class="my-5 opacity-10">
                <div class="text-center text-white-50 small">
                    &copy; <?php echo date('Y'); ?> Restoran Kita. Crafted for Perfection.
                </div>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Function untuk scroll ke menu section
        function scrollToMenuSection() {
            const menuSection = document.getElementById('menu');
            if (menuSection) {
                // Hitung offset untuk navbar fixed
                const navbar = document.getElementById('mainNav');
                const navbarHeight = navbar ? navbar.offsetHeight : 0;
                
                window.scrollTo({
                    top: menuSection.offsetTop - navbarHeight - 20,
                    behavior: 'smooth'
                });
                
                // Add active class ke semua reveal elements di section menu
                const menuReveals = menuSection.querySelectorAll('.reveal');
                menuReveals.forEach(el => {
                    setTimeout(() => {
                        el.classList.add('active');
                    }, 300);
                });
            }
        }

        // Function untuk inisialisasi reveal animation
        function initRevealAnimation() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if(entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.classList.add('active');
                        }, 200);
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
        }

        <?php if(!$from_order): ?>
        // UPDATED JS: Loading Logic with Counter (hanya jika tidak dari order.php)
        window.addEventListener('DOMContentLoaded', () => {
            const loader = document.getElementById('loader');
            const percentText = document.getElementById('loaderPercent');
            const content = document.getElementById('page-content');
            
            let progress = 0;
            // Interval dipercepat sedikit agar animasi terasa responsif tapi tetap terlihat
            let interval = setInterval(() => {
                // Random increment
                progress += Math.floor(Math.random() * 5) + 1;
                
                if (progress > 100) progress = 100;
                
                // Update text
                percentText.innerText = progress + '%';

                if(progress >= 100) {
                    clearInterval(interval);
                    setTimeout(finishLoading, 500); // Tahan sebentar di 100%
                }
            }, 50);

            function finishLoading() {
                // Tambahkan class fade-out untuk trigger animasi CSS
                loader.classList.add('fade-out');
                
                // Tampilkan konten
                content.style.display = 'block';
                
                setTimeout(() => {
                    // Hapus loader dari DOM setelah transisi CSS selesai (0.8s)
                    loader.style.display = 'none';
                    
                    // Fade in konten
                    content.style.opacity = '1';
                    
                    // Jalankan hero animation
                    animateHero();
                    
                    // Inisialisasi reveal animation
                    initRevealAnimation();
                }, 800);
            }
        });

        // Hero Entrance Animation (hanya jika tidak dari order.php)
        function animateHero() {
            const subtitle = document.querySelector('.hero-subtitle');
            const h1 = document.getElementById('heroTitle');
            const p = document.getElementById('heroPara');
            const btn = document.getElementById('heroBtn');

            [subtitle, h1, p, btn].forEach((el, i) => {
                setTimeout(() => {
                    el.style.transition = 'all 1s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, 200 * i);
            });
        }
        <?php else: ?>
        // Jika dari order.php, langsung inisialisasi animasi
        window.addEventListener('DOMContentLoaded', () => {
            // Langsung aktifkan reveal elements
            initRevealAnimation();
            
            <?php if($scroll_to_menu): ?>
            // Scroll ke menu section dengan delay sedikit
            setTimeout(() => {
                scrollToMenuSection();
            }, 100);
            <?php endif; ?>
        });
        <?php endif; ?>

        // Parallax Effect for Restaurant Background
        document.addEventListener('mousemove', (e) => {
            const bg = document.getElementById('heroBg');
            const x = (window.innerWidth - e.pageX * 2) / 80;
            const y = (window.innerHeight - e.pageY * 2) / 80;
            if(bg) bg.style.transform = `scale(1.1) translate(${x}px, ${y}px)`;
        });

        // Navbar Scroll Effect
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('mainNav');
            if(window.scrollY > 100) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        });

        // Filter Functionality
        function filterMenu(category, btn) {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const items = document.querySelectorAll('.menu-item-wrap');
            items.forEach(item => {
                item.style.transition = 'all 0.5s ease';
                if(category === 'semua' || item.getAttribute('data-category') === category) {
                    item.style.display = 'block';
                    setTimeout(() => {
                        item.style.opacity = '1';
                        item.style.transform = 'scale(1)';
                    }, 10);
                } else {
                    item.style.opacity = '0';
                    item.style.transform = 'scale(0.8)';
                    setTimeout(() => { item.style.display = 'none'; }, 500);
                }
            });
        }

        // Smooth scroll untuk anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if(targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if(targetElement) {
                    const navbar = document.getElementById('mainNav');
                    const navbarHeight = navbar ? navbar.offsetHeight : 0;
                    
                    window.scrollTo({
                        top: targetElement.offsetTop - navbarHeight - 20,
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>