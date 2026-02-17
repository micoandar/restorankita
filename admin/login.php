<?php
// Memastikan tidak ada spasi sebelum session_start
ob_start();
session_start();
include '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username'])) {
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    // Menggunakan MD5 sesuai permintaan awal
    $pass = md5($_POST['password']);
    
    $query = "SELECT * FROM admin WHERE username='$user' AND password='$pass'";
    $q = mysqli_query($conn, $query);

    if ($q && mysqli_num_rows($q) > 0) {
        $data = mysqli_fetch_assoc($q);
        $_SESSION['admin'] = $data['username'];
        $_SESSION['login_time'] = time();
        
        session_write_close();
        header("Location: dashboard.php");
        exit();
    } else {
        $error = 'Akses ditolak. Identitas tidak dikenali.';
    }
}

if (isset($_SESSION['admin'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Elite Portal - Restoran Kita</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --accent: #c084fc;
            --accent-glow: rgba(192, 132, 252, 0.5);
            --cyan: #22d3ee;
            --bg-dark: #030712;
            --glass-bg: rgba(17, 24, 39, 0.7);
            --input-bg: rgba(255, 255, 255, 0.03);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* High-End Canvas Background */
        canvas#canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }

        /* Mesh Gradient Orbs */
        .orb {
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            filter: blur(100px);
            z-index: 1;
            opacity: 0.4;
            animation: orbMove 20s infinite alternate;
        }

        .orb-1 { background: var(--accent); top: -10%; left: -10%; }
        .orb-2 { background: var(--cyan); bottom: -10%; right: -10%; animation-delay: -5s; }

        @keyframes orbMove {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(100px, 50px) scale(1.2); }
        }

        .login-wrapper {
            position: relative;
            width: 100%;
            max-width: 450px;
            padding: 25px;
            z-index: 10;
        }

        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 40px;
            padding: 60px 45px;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.8), 
                        inset 0 0 20px rgba(255, 255, 255, 0.02);
            position: relative;
            overflow: hidden;
        }

        /* Glowing Border Animation */
        .login-card::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent), var(--cyan), transparent);
            animation: borderSlide 3s linear infinite;
        }

        @keyframes borderSlide {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .logo-container {
            position: relative;
            width: 90px;
            height: 90px;
            margin: 0 auto 30px;
        }

        .logo-box {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--accent), var(--cyan));
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 35px;
            box-shadow: 0 15px 30px var(--accent-glow);
            transform: rotate(-5deg);
            transition: 0.5s ease;
        }

        .logo-container:hover .logo-box {
            transform: rotate(0deg) scale(1.1);
        }

        .brand-text h1 {
            color: white;
            font-weight: 800;
            font-size: 32px;
            letter-spacing: -1.5px;
            text-align: center;
            margin-bottom: 8px;
        }

        .brand-text p {
            color: #9ca3af;
            font-size: 15px;
            text-align: center;
            margin-bottom: 40px;
        }

        /* Form Customization */
        .form-label {
            color: #d1d5db;
            font-weight: 500;
            margin-left: 10px;
            font-size: 14px;
        }

        .input-group-custom {
            background: var(--input-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            display: flex;
            align-items: center;
            padding: 5px 15px;
            transition: 0.3s;
            margin-bottom: 20px;
        }

        .input-group-custom:focus-within {
            border-color: var(--accent);
            box-shadow: 0 0 15px var(--accent-glow);
            background: rgba(255, 255, 255, 0.05);
        }

        .input-group-custom i {
            color: #6b7280;
            width: 30px;
            transition: 0.3s;
        }

        .input-group-custom:focus-within i {
            color: var(--accent);
        }

        .input-group-custom input {
            background: transparent;
            border: none;
            color: white;
            padding: 15px 10px;
            width: 100%;
            outline: none;
            font-size: 15px;
        }

        .input-group-custom input::placeholder {
            color: #4b5563;
        }

        .btn-elite {
            width: 100%;
            height: 60px;
            background: linear-gradient(90deg, var(--accent), var(--cyan));
            color: #000;
            font-weight: 700;
            border-radius: 18px;
            border: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.4s;
            margin-top: 15px;
            position: relative;
            overflow: hidden;
        }

        .btn-elite:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px var(--accent-glow);
            filter: brightness(1.1);
        }

        /* Error Message */
        .error-overlay {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
            padding: 12px;
            border-radius: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        .footer-nav {
            margin-top: 35px;
            text-align: center;
        }

        .back-link {
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: 0.3s;
        }

        .back-link:hover {
            color: var(--cyan);
        }

        /* Password Eye */
        .toggle-btn {
            color: #4b5563;
            cursor: pointer;
            padding: 10px;
        }

        .toggle-btn:hover { color: white; }

        /* Loader */
        .loader {
            display: none;
            width: 25px;
            height: 25px;
            border: 3px solid rgba(0,0,0,0.2);
            border-top: 3px solid #000;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <canvas id="canvas"></canvas>

    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="logo-container">
                <div class="logo-box">
                    <i class="fas fa-shield-halved"></i>
                </div>
            </div>

            <div class="brand-text">
                <h1>Restoran Kita</h1>
                <p>Admin Control Panel Access</p>
            </div>

            <?php if ($error): ?>
                <div class="error-overlay">
                    <i class="fas fa-triangle-exclamation"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <label class="form-label">Username</label>
                <div class="input-group-custom">
                    <i class="fas fa-at"></i>
                    <input type="text" name="username" placeholder="Masukkan username" required autocomplete="off">
                </div>

                <label class="form-label">Password</label>
                <div class="input-group-custom">
                    <i class="fas fa-key"></i>
                    <input type="password" name="password" id="password" placeholder="Masukkan password" required>
                    <span class="toggle-btn" id="togglePass">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>

                <button type="submit" class="btn-elite" id="btnSubmit">
                    <span id="btnText">SIGN IN TO DASHBOARD</span>
                    <div class="loader" id="loader"></div>
                </button>
            </form>

            <div class="footer-nav">
                <a href="../index.php" class="back-link">
                    <i class="fas fa-arrow-left-long me-2"></i> Kembali ke Website Utama
                </a>
            </div>
        </div>
    </div>

    <script>
        // Particle Background Logic
        const canvas = document.getElementById('canvas');
        const ctx = canvas.getContext('2d');
        let particles = [];

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }

        window.addEventListener('resize', resize);
        resize();

        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 2 + 0.1;
                this.speedX = Math.random() * 1 - 0.5;
                this.speedY = Math.random() * 1 - 0.5;
            }
            update() {
                this.x += this.speedX;
                this.y += this.speedY;
                if (this.x > canvas.width) this.x = 0;
                if (this.x < 0) this.x = canvas.width;
                if (this.y > canvas.height) this.y = 0;
                if (this.y < 0) this.y = canvas.height;
            }
            draw() {
                ctx.fillStyle = 'rgba(255, 255, 255, 0.2)';
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        function init() {
            for (let i = 0; i < 100; i++) {
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

        init();
        animate();

        // Password Visibility
        const togglePass = document.getElementById('togglePass');
        const passInput = document.getElementById('password');

        togglePass.addEventListener('click', () => {
            const type = passInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passInput.setAttribute('type', type);
            togglePass.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

        // Submit Animation
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btnText = document.getElementById('btnText');
            const loader = document.getElementById('loader');
            const btn = document.getElementById('btnSubmit');

            btnText.style.display = 'none';
            loader.style.display = 'inline-block';
            btn.style.opacity = '0.8';
            btn.disabled = true;
        });
    </script>
</body>
</html>
<?php 
ob_end_flush(); 
?>