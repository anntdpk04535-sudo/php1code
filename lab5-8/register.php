<?php
session_start();
require_once "./db_utils.php";
$db = new DB_UTILS();

if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($fullname) || empty($email) || empty($username) || empty($password)) {
        $error = 'Vui lòng điền đầy đủ thông tin!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Định dạng email không hợp lệ!';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải chứa ít nhất 6 ký tự!';
    } else {
        $check = $db->getValue("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($check > 0) {
            $error = 'Tên tài khoản hoặc email đăng ký đã tồn tại!';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (full_name, email, username, password, role) VALUES (?, ?, ?, ?, 'user')";
            if ($db->execute($sql, [$fullname, $email, $username, $hashedPassword])) {
                $success = 'Đăng ký tài khoản thành công! Bạn có thể <a href="login.php">Đăng nhập</a> ngay.';
                $fullname = $email = $username = '';
            } else {
                $error = 'Có lỗi xảy ra từ máy chủ, vui lòng thử lại sau!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký — TechShop</title>
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
            --success: #4ee6a8;
            --success-dim: rgba(78, 230, 168, 0.12);
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
            padding: 40px 20px;
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
            max-width: 420px;
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

        .form-group { margin-bottom: 16px; }

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

        .hint {
            font-family: var(--font-mono);
            font-size: 11px;
            color: var(--text-faint);
            margin-top: 6px;
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
            margin-top: 8px;
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

        .btn-auth::before { content: ""; }
        .btn-auth:hover::before { left: 130%; }
        .btn-auth:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 36px -10px var(--accent-glow);
        }
        .btn-auth:active { transform: translateY(0); }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            line-height: 1.5;
            margin-bottom: 18px;
            background: var(--surface-2);
            border: 1px solid var(--border-soft);
            color: var(--text);
        }

        .alert-danger { border-left: 3px solid var(--danger); }
        .alert-danger i { color: var(--danger); }

        .alert-success { border-left: 3px solid var(--success); }
        .alert-success i { color: var(--success); }

        .alert i { font-size: 14px; margin-top: 1px; }
        .alert a { color: var(--accent); font-weight: 600; text-decoration: none; }
        .alert a:hover { text-decoration: underline; }

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
            <div class="auth-eyebrow">Tạo tài khoản mới</div>
            <h2>Đăng Ký Thành Viên</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <span><?= $success ?></span></div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="form-group">
                    <label>Họ và tên</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-id-card"></i>
                        <input type="text" name="full_name" placeholder="Ví dụ: Nguyễn Văn A"
                            value="<?= htmlspecialchars($fullname ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email liên hệ</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" name="email" placeholder="example@gmail.com"
                            value="<?= htmlspecialchars($email ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Tên tài khoản (Username)</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="username" placeholder="Nhập tên đăng nhập duy nhất"
                            value="<?= htmlspecialchars($username ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Mật khẩu</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" placeholder="Tối thiểu từ 6 ký tự trở lên" required>
                    </div>
                    <div class="hint">tối thiểu 6 ký tự</div>
                </div>
                <button type="submit" class="btn-auth">
                    <i class="fa-solid fa-user-plus"></i> Đăng ký ngay
                </button>
            </form>
            <p class="redirect">Đã có tài khoản thành viên? <a href="login.php">Đăng nhập</a></p>
        </div>
    </div>
</body>

</html>