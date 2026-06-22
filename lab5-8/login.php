<?php
session_start();
if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=lab4;charset=utf8", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Vui lòng điền đầy đủ tài khoản và mật khẩu!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'full_name' => $user['full_name'],
                'username' => $user['username'],
                'email' => $user['email'],  // ← thêm email vào session
                'role' => $user['role'],
            ];
            header("Location: index.php");
            exit;
        } else {
            $error = 'Tài khoản hoặc mật khẩu không chính xác!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập — TechShop</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #0a0d12;
            --bg-soft: #0d1117;
            --surface: #12161d;
            --surface-2: #161b23;
            --border: #232a35;
            --border-soft: #1a2028;
            --text: #e7ebf0;
            --text-dim: #8993a4;
            --text-faint: #565f70;

            --accent: #00e6c3;
            --accent-strong: #2dffd6;
            --accent-dim: rgba(0, 230, 195, 0.12);
            --accent-border: rgba(0, 230, 195, 0.35);
            --accent-glow: rgba(0, 230, 195, 0.25);

            --gold-strong: #eccb8f;
            --danger: #ff5e72;
            --danger-dim: rgba(255, 94, 114, 0.12);

            --radius-lg: 18px;
            --radius-md: 12px;
            --radius-sm: 8px;

            --font-display: 'Space Grotesk', sans-serif;
            --font-body: 'Inter', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(to right, rgba(255, 255, 255, 0.025) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: radial-gradient(ellipse 65% 55% at 50% 35%, black 25%, transparent 75%);
            pointer-events: none;
            z-index: 0;
        }

        body::after {
            content: "";
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 560px;
            height: 560px;
            background: radial-gradient(ellipse at center, rgba(0, 230, 195, 0.10), transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .auth-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 400px;
        }

        .back-home {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
            color: var(--text-dim);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 22px;
            transition: color 0.2s ease;
        }

        .back-home:hover { color: var(--accent); }
        .back-home:hover .arrow { transform: translateX(-3px); }
        .back-home .arrow { display: inline-block; transition: transform 0.2s ease; }

        .brand-mark {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 19px;
            color: var(--text);
            margin-bottom: 22px;
        }

        .brand-mark svg {
            width: 22px;
            height: 22px;
            color: var(--accent);
            filter: drop-shadow(0 0 6px var(--accent-glow));
        }

        .auth-container {
            position: relative;
            background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 36px 32px;
            box-shadow: 0 30px 60px -25px rgba(0, 0, 0, 0.65);
        }

        .auth-container::before,
        .auth-container::after {
            content: "";
            position: absolute;
            width: 16px;
            height: 16px;
            border-color: var(--accent);
            border-style: solid;
            opacity: 0.6;
        }

        .auth-container::before {
            top: -1px;
            left: -1px;
            border-width: 2px 0 0 2px;
            border-radius: 4px 0 0 0;
        }

        .auth-container::after {
            bottom: -1px;
            right: -1px;
            border-width: 0 2px 2px 0;
            border-radius: 0 0 4px 0;
        }

        .auth-eyebrow {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            font-family: var(--font-mono);
            font-size: 11.5px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .auth-eyebrow::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 8px 1px var(--accent-glow);
        }

        h2 {
            text-align: center;
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 24px;
            color: var(--text);
            margin-bottom: 26px;
            letter-spacing: -0.01em;
        }

        .form-group { margin-bottom: 18px; }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-family: var(--font-mono);
            font-weight: 600;
            color: var(--text-faint);
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrap i {
            position: absolute;
            left: 14px;
            color: var(--text-faint);
            font-size: 14px;
            transition: color 0.2s ease;
            pointer-events: none;
        }

        .input-wrap:focus-within i { color: var(--accent); }

        .form-group input {
            width: 100%;
            padding: 12px 14px 12px 40px;
            background: var(--bg-soft);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            outline: none;
            font-size: 14px;
            font-family: var(--font-body);
            color: var(--text);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-group input::placeholder { color: var(--text-faint); }

        .form-group input:focus {
            border-color: var(--accent-border);
            box-shadow: 0 0 0 3px var(--accent-dim);
        }

        .btn-auth {
            position: relative;
            width: 100%;
            background: var(--accent);
            color: var(--bg);
            border: none;
            padding: 14px;
            border-radius: var(--radius-sm);
            font-family: var(--font-mono);
            font-size: 14.5px;
            font-weight: 700;
            letter-spacing: 0.03em;
            cursor: pointer;
            overflow: hidden;
            margin-top: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            box-shadow: 0 14px 30px -12px var(--accent-glow);
            transition: transform 0.15s ease, box-shadow 0.2s ease;
        }

        .btn-auth::before {
            content: "";
            position: absolute;
            top: 0;
            left: -120%;
            width: 60%;
            height: 100%;
            background: linear-gradient(120deg, transparent, rgba(255, 255, 255, 0.55), transparent);
            transform: skewX(-20deg);
            transition: left 0.5s ease;
        }

        .btn-auth:hover::before { left: 130%; }
        .btn-auth:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 36px -10px var(--accent-glow);
        }
        .btn-auth:active { transform: translateY(0); }

        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            margin-bottom: 18px;
            background: var(--surface-2);
            border: 1px solid var(--border-soft);
            border-left: 3px solid var(--danger);
            color: var(--text);
        }

        .alert i { color: var(--danger); font-size: 14px; }

        p.redirect {
            text-align: center;
            margin-top: 22px;
            font-size: 13.5px;
            color: var(--text-dim);
        }

        p.redirect a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }

        p.redirect a:hover { text-decoration: underline; }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }
    </style>
</head>

<body>
    <div class="auth-wrapper">
        <a href="index.php" class="back-home"><span class="arrow">←</span> Quay lại trang chủ</a>

        <div class="brand-mark">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 3H5L7 14H18L20 6H6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="9" cy="19" r="1.6" stroke="currentColor" stroke-width="2"/>
                <circle cx="17" cy="19" r="1.6" stroke="currentColor" stroke-width="2"/>
            </svg>
            TechShop
        </div>

        <div class="auth-container">
            <div class="auth-eyebrow">Đăng nhập hệ thống</div>
            <h2>Chào mừng trở lại</h2>

            <?php if ($error): ?>
                <div class="alert"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label>Tên đăng nhập</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="username" placeholder="Nhập username của bạn"
                            value="<?= htmlspecialchars($username ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Mật khẩu</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" placeholder="Nhập mật khẩu" required>
                    </div>
                </div>
                <button type="submit" class="btn-auth">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Đăng nhập
                </button>
            </form>
            <p class="redirect">Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
        </div>
    </div>
</body>

</html>