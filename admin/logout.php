<?php
// Memastikan tidak ada output sebelum session_start
ob_start();
session_start();

// Konfirmasi logout
if (isset($_GET['confirm']) && $_GET['confirm'] == 'true') {
    // Hapus semua session
    session_unset();
    session_destroy();
    
    // Pastikan session tertulis sebelum redirect
    session_write_close();
    
    // Redirect ke halaman login dengan pesan logout
    header("Location: login.php?logout=success");
    exit();
}

// Ambil informasi session
$username = $_SESSION['admin'] ?? 'Administrator';
$login_time = $_SESSION['login_time'] ?? time();
$duration = time() - $login_time;
$duration_formatted = '';
if ($duration >= 3600) {
    $hours = floor($duration / 3600);
    $minutes = floor(($duration % 3600) / 60);
    $duration_formatted = $hours . ' jam ' . $minutes . ' menit';
} else {
    $minutes = floor($duration / 60);
    $seconds = $duration % 60;
    $duration_formatted = $minutes . ' menit ' . $seconds . ' detik';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Keluar - Restoran Kita</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@300;400;500;600;700&display=swap');

        :root {
            --primary: #8b5cf6;
            --primary-glow: rgba(139, 92, 246, 0.5);
            --danger: #ff4b5c;
            --danger-glow: rgba(255, 75, 92, 0.4);
            --bg-deep: #050505;
            --card-bg: rgba(15, 15, 20, 0.7);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --border-glass: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-deep);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            perspective: 1000px;
        }

        /* --- Animated Background --- */
        .mesh-gradient {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: 
                radial-gradient(at 0% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(255, 75, 92, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(139, 92, 246, 0.1) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(255, 75, 92, 0.15) 0px, transparent 50%);
            filter: blur(80px);
            animation: meshMove 20s ease infinite alternate;
        }

        @keyframes meshMove {
            0% { transform: scale(1); }
            100% { transform: scale(1.2); }
        }

        .grid-system {
            position: fixed;
            inset: 0;
            background-image: linear-gradient(var(--border-glass) 1px, transparent 1px),
                              linear-gradient(90deg, var(--border-glass) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(circle at center, black, transparent 90%);
            opacity: 0.2;
            z-index: -1;
            transform: rotateX(45deg) scale(2);
        }

        /* --- Logout Card --- */
        .logout-wrapper {
            position: relative;
            width: 100%;
            max-width: 450px;
            padding: 20px;
            z-index: 10;
        }

        .main-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid var(--border-glass);
            border-radius: 32px;
            padding: 45px 35px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
            transform-style: preserve-3d;
            animation: cardEntrance 1s cubic-bezier(0.23, 1, 0.32, 1) forwards;
        }

        @keyframes cardEntrance {
            0% { opacity: 0; transform: translateY(50px) rotateX(-10deg); }
            100% { opacity: 1; transform: translateY(0) rotateX(0); }
        }

        /* --- Icon Animation --- */
        .icon-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 30px;
        }

        .icon-circle {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--danger), #f87171);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            z-index: 2;
            box-shadow: 0 0 30px var(--danger-glow);
        }

        .sonar {
            position: absolute;
            inset: -5px;
            border: 2px solid var(--danger);
            border-radius: 50%;
            opacity: 0;
            animation: sonarWave 2s linear infinite;
        }

        .sonar-2 { animation-delay: 1s; }

        @keyframes sonarWave {
            0% { transform: scale(1); opacity: 0.5; }
            100% { transform: scale(1.6); opacity: 0; }
        }

        /* --- Typography --- */
        .title {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 2.2rem;
            letter-spacing: -1px;
            margin-bottom: 10px;
            background: linear-gradient(to bottom, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: var(--text-dim);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 35px;
        }

        /* --- Info Section --- */
        .info-box {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 35px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .info-item {
            text-align: left;
        }

        .info-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
            margin-bottom: 4px;
            font-weight: 700;
        }

        .info-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* --- Buttons --- */
        .actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .btn-modern {
            padding: 16px 28px;
            border-radius: 18px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
        }

        .btn-confirm {
            background: #fff;
            color: #000;
            box-shadow: 0 10px 20px rgba(255, 255, 255, 0.1);
        }

        .btn-confirm:hover {
            transform: translateY(-5px);
            background: var(--danger);
            color: #fff;
            box-shadow: 0 15px 30px var(--danger-glow);
        }

        .btn-back {
            background: transparent;
            color: #fff;
            border: 1px solid var(--border-glass);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: #fff;
            transform: translateY(-3px);
        }

        /* --- Footer --- */
        .footer {
            margin-top: 35px;
            padding-top: 25px;
            border-top: 1px solid var(--border-glass);
        }

        .warning-text {
            color: #fbbf24;
            font-size: 0.8rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .copyright {
            color: var(--text-dim);
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        /* --- Particles --- */
        #canvas-particles {
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .main-card { padding: 35px 25px; }
            .info-box { grid-template-columns: 1fr; }
            .title { font-size: 1.8rem; }
        }
    </style>
</head>
<body>

    <div class="mesh-gradient"></div>
    <div class="grid-system"></div>
    <canvas id="canvas-particles"></canvas>

    <div class="logout-wrapper">
        <div class="main-card">
            <div class="icon-container">
                <div class="sonar"></div>
                <div class="sonar-2"></div>
                <div class="icon-circle">
                    <i class="fas fa-power-off"></i>
                </div>
            </div>

            <h1 class="title">Sign Out</h1>
            <p class="subtitle">Apakah Anda yakin ingin mengakhiri sesi manajemen Anda saat ini?</p>

            <div class="info-box">
                <div class="info-item">
                    <div class="info-label">Active User</div>
                    <div class="info-value"><?php echo htmlspecialchars($username); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Session Time</div>
                    <div class="info-value"><?php echo $duration_formatted; ?></div>
                </div>
            </div>

            <div class="actions">
                <a href="logout.php?confirm=true" class="btn-modern btn-confirm" id="confirmExit">
                    <i class="fas fa-sign-out-alt"></i>
                    Keluar Sekarang
                </a>
                <a href="dashboard.php" class="btn-modern btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Kembali Ke Dashboard
                </a>
            </div>

            <div class="footer">
                <div class="warning-text">
                    <i class="fas fa-shield-halved"></i>
                    Sesi aman akan segera ditutup
                </div>
                <div class="copyright">
                    TERMINAL ADMIN &bull; <?php echo date('Y'); ?> RESTORAN KITA
                </div>
            </div>
        </div>
    </div>

    <script>
        // Particle Background System
        const canvas = document.getElementById('canvas-particles');
        const ctx = canvas.getContext('2d');
        let particles = [];

        function initCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }

        class Particle {
            constructor() {
                this.reset();
            }
            reset() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 2 + 0.5;
                this.speedX = Math.random() * 0.5 - 0.25;
                this.speedY = Math.random() * 0.5 - 0.25;
                this.life = Math.random() * 0.5 + 0.3;
            }
            update() {
                this.x += this.speedX;
                this.y += this.speedY;
                if (this.x > canvas.width || this.x < 0 || this.y > canvas.height || this.y < 0) {
                    this.reset();
                }
            }
            draw() {
                ctx.fillStyle = `rgba(139, 92, 246, ${this.life})`;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        function createParticles() {
            for (let i = 0; i < 80; i++) {
                particles.push(new Particle());
            }
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(p => {
                p.update();
                p.draw();
            });
            requestAnimationFrame(animate);
        }

        window.addEventListener('resize', initCanvas);
        initCanvas();
        createParticles();
        animate();

        // Button Interactions
        const btn = document.getElementById('confirmExit');
        btn.addEventListener('click', function() {
            this.style.pointerEvents = 'none';
            this.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Memproses...';
        });

        // Mouse Parallax Effect on Card
        const card = document.querySelector('.main-card');
        document.addEventListener('mousemove', (e) => {
            const x = (window.innerWidth / 2 - e.pageX) / 40;
            const y = (window.innerHeight / 2 - e.pageY) / 40;
            card.style.transform = `rotateY(${x}deg) rotateX(${y}deg)`;
        });

        // Reset card on mouse leave
        document.addEventListener('mouseleave', () => {
            card.style.transform = `rotateY(0deg) rotateX(0deg)`;
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>