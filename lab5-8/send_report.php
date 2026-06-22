<?php
session_start();
require_once __DIR__ . "/db_utils.php";
$db = new DB_UTILS();

$message = '';
$error = '';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user']['id'] ?? null;
$fullname = $_SESSION['user']['full_name'] ?? '';
$email = $_SESSION['user']['email'] ?? '';

$cart_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['qty'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($title) || empty($content)) {
        $error = "Vui lòng điền đầy đủ tiêu đề và nội dung khiếu nại!";
    } else {
        $db->execute(
            "INSERT INTO reports (user_id, fullname, email, title, content, status) VALUES (?, ?, ?, ?, ?, 'Chờ xử lý')",
            [$user_id, $fullname, $email, $title, $content]
        );
        $message = "Gửi khiếu nại thành công! Ban quản trị sẽ kiểm tra và phản hồi ngay bên dưới.";
    }
}

$my_reports = $db->getAll("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC", [$user_id]);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khiếu Nại & Hỗ Trợ - TechShop</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #0a0d12; --bg-soft: #0d1117; --surface: #12161d; --surface-2: #161b23;
            --border: #232a35; --border-soft: #1a2028; --text: #e7ebf0; --text-dim: #8993a4; --text-faint: #565f70;
            --accent: #00e6c3; --accent-strong: #2dffd6; --accent-dim: rgba(0, 230, 195, 0.12); --accent-border: rgba(0, 230, 195, 0.35); --accent-glow: rgba(0, 230, 195, 0.25);
            --gold: #d8b87a; --gold-strong: #eccb8f;
            --warn: #ffb454; --warn-dim: rgba(255, 180, 84, 0.12);
            --admin: #4ee6a8;
            --danger: #ff5e72; --danger-dim: rgba(255, 94, 114, 0.14);
            --radius-lg: 18px; --radius-md: 12px; --radius-sm: 8px;
            --font-display: 'Space Grotesk', sans-serif; --font-body: 'Inter', sans-serif; --font-mono: 'JetBrains Mono', monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(to right, rgba(255, 255, 255, 0.025) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: radial-gradient(ellipse 75% 50% at 50% 0%, black 25%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        a { color: inherit; }

        /* ===== HEADER (đồng bộ với index.php) ===== */
        header {
            background: rgba(10, 13, 18, 0.85);
            backdrop-filter: blur(10px);
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-soft);
            position: sticky;
            top: 0;
            z-index: 100;
            flex-wrap: wrap;
            gap: 14px;
        }

        .brand {
            font-family: var(--font-display);
            font-size: 21px;
            font-weight: 700;
            color: var(--text);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 9px;
            letter-spacing: -0.01em;
        }

        .brand svg {
            width: 22px;
            height: 22px;
            color: var(--accent);
            filter: drop-shadow(0 0 6px var(--accent-glow));
        }

        .nav-links { display: flex; align-items: center; gap: 22px; flex-wrap: wrap; }
        .nav-links > a {
            text-decoration: none;
            color: var(--text-dim);
            font-weight: 500;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: color 0.2s ease;
        }
        .nav-links > a:hover { color: var(--accent); }

        .nav-warning { color: var(--warn) !important; font-weight: 600 !important; }
        .nav-admin { color: var(--admin) !important; font-weight: 600 !important; }
        .nav-logout { color: var(--danger) !important; }
        .nav-login { color: var(--accent) !important; font-weight: 600 !important; }

        .btn-register {
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 7px 14px;
            transition: all 0.2s ease;
        }
        .btn-register:hover {
            border-color: var(--accent-border);
            background: var(--accent-dim);
            color: var(--accent) !important;
        }

        .btn-cart-nav {
            background: var(--surface-2);
            border: 1px solid var(--border);
            color: var(--text) !important;
            padding: 8px 16px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600 !important;
            transition: all 0.2s ease;
        }
        .btn-cart-nav:hover { border-color: var(--accent-border); color: var(--accent) !important; }

        .badge-cart {
            background: var(--danger);
            color: #fff;
            padding: 1px 7px;
            border-radius: 999px;
            font-size: 11px;
            font-family: var(--font-mono);
            font-weight: 700;
            box-shadow: 0 0 10px rgba(255, 94, 114, 0.45);
        }

        /* ===== PAGE BODY ===== */
        .container {
            position: relative;
            z-index: 1;
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 24px 60px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: var(--font-mono);
            font-size: 12.5px;
            color: var(--text-faint);
            margin-bottom: 22px;
        }
        .breadcrumb a { color: var(--text-dim); text-decoration: none; }
        .breadcrumb a:hover { color: var(--accent); }

        .report-box {
            background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 36px;
            margin-bottom: 32px;
        }

        .report-header { text-align: center; margin-bottom: 28px; }
        .report-header h1 {
            font-family: var(--font-display);
            font-size: 24px;
            color: var(--text);
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .report-header h1 i { color: var(--accent); }
        .report-header p { color: var(--text-dim); font-size: 14px; }

        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 18px; }
        .form-group label { font-size: 13px; font-weight: 500; color: var(--text-dim); }
        .form-group input, .form-group textarea {
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            outline: none;
            font-size: 14px;
            background: var(--surface-2);
            color: var(--text);
            font-family: var(--font-body);
            transition: border-color 0.2s ease;
        }
        .form-group input::placeholder, .form-group textarea::placeholder { color: var(--text-faint); }
        .form-group input:focus, .form-group textarea:focus { border-color: var(--accent-border); }
        .form-group input[readonly] { background: var(--bg-soft); color: var(--text-faint); cursor: not-allowed; }

        .btn-submit {
            position: relative;
            background: var(--accent);
            color: var(--bg);
            border: none;
            padding: 15px;
            border-radius: var(--radius-sm);
            font-family: var(--font-mono);
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            box-shadow: 0 14px 28px -10px var(--accent-glow);
            overflow: hidden;
        }
        .btn-submit::before {
            content: "";
            position: absolute;
            top: 0; left: -120%;
            width: 60%; height: 100%;
            background: linear-gradient(120deg, transparent, rgba(255,255,255,0.5), transparent);
            transform: skewX(-20deg);
            transition: left 0.5s ease;
        }
        .btn-submit:hover::before { left: 130%; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 18px 34px -10px var(--accent-glow); }

        .alert {
            padding: 14px 18px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: var(--accent-dim); color: var(--accent); border: 1px solid var(--accent-border); }
        .alert-danger { background: var(--danger-dim); color: var(--danger); border: 1px solid var(--danger); }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: var(--text-dim);
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 22px;
            transition: color 0.2s ease;
        }
        .btn-back:hover { color: var(--accent); }

        .history-box {
            background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 30px;
        }
        .history-box h2 {
            font-family: var(--font-display);
            font-size: 17px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid var(--border-soft);
            padding-bottom: 14px;
        }
        .history-box h2 i { color: var(--accent); }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px 14px; text-align: left; border-bottom: 1px solid var(--border-soft); font-size: 13.5px; vertical-align: top; }
        th { background: var(--bg-soft); color: var(--text-dim); font-weight: 600; font-family: var(--font-mono); font-size: 12px; letter-spacing: 0.02em; }
        tr:last-child td { border-bottom: none; }

        .badge { font-weight: 600; padding: 5px 12px; border-radius: 999px; font-size: 12px; display: inline-block; }

        /* Đồng bộ Class CSS động */
        .status-Chờ-xử-lý { background: var(--warn-dim); color: var(--warn); }
        .status-Đang-giải-quyết { background: rgba(99, 179, 237, 0.14); color: #63b3ed; }
        .status-Đã-đóng { background: var(--accent-dim); color: var(--accent); }

        .admin-reply {
            background: var(--accent-dim);
            border: 1px solid var(--accent-border);
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-top: 10px;
            color: var(--text-dim);
            font-size: 13px;
        }
        .admin-reply strong {
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
            font-size: 12.5px;
        }

        @media (max-width: 768px) {
            header { padding: 14px 18px; }
            .container { padding: 28px 16px 50px; }
            .report-box { padding: 24px; }
            .report-box > form > div[style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
        }
        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="brand">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 3H5L7 14H18L20 6H6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="9" cy="19" r="1.6" stroke="currentColor" stroke-width="2"/>
                <circle cx="17" cy="19" r="1.6" stroke="currentColor" stroke-width="2"/>
            </svg>
            TechShop
        </a>
        <div class="nav-links">
            <a href="index.php">Trang chủ</a>
            <a href="DonHang.php"><i class="fa-solid fa-box"></i> Đơn hàng của tôi</a>
            <a href="send_report.php" class="nav-warning">
                <i class="fa-solid fa-triangle-exclamation"></i> Khiếu nại</a>

            <?php if (isset($_SESSION['user'])): ?>
                <a href="cart.php" class="btn-cart-nav"><i class="fa-solid fa-cart-shopping"></i> Giỏ hàng <span id="cartBadge"
                        class="badge-cart"><?= $cart_count ?></span></a>
                <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                    <a href="admin/index.php" class="nav-admin"><i class="fa-solid fa-gauge"></i> Trang Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="nav-logout">Đăng xuất
                    (<?= htmlspecialchars($_SESSION['user']['full_name']) ?>)</a>
            <?php else: ?>
                <a href="login.php" class="nav-login">Đăng nhập</a>
                <a href="register.php" class="btn-register">Đăng ký</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <div class="breadcrumb">
            <a href="index.php">Trang chủ</a>
        </div>

        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại trang chủ mua sắm</a>

        <div class="report-box">
            <div class="report-header">
                <h1><i class="fas fa-headset"></i> Trung Tâm Hỗ Trợ & Khiếu Nại</h1>
                <p>Gửi thắc mắc của bạn, chúng tôi sẽ tiếp nhận và phản hồi ngay phía dưới trang này.</p>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?=htmlspecialchars($message)?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?=htmlspecialchars($error)?></div><?php endif; ?>

            <form method="POST">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Tên tài khoản</label>
                        <input type="text" value="<?=htmlspecialchars($fullname)?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Email liên hệ</label>
                        <input type="text" value="<?=htmlspecialchars($email)?>" readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label>Tiêu đề cần hỗ trợ</label>
                    <input type="text" name="title" placeholder="Ví dụ: Lỗi trừ tiền đơn hàng #123..." required>
                </div>
                <div class="form-group">
                    <label>Nội dung chi tiết</label>
                    <textarea name="content" rows="4" placeholder="Nhập nội dung khiếu nại cụ thể tại đây..." required></textarea>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Gửi yêu cầu hỗ trợ</button>
            </form>
        </div>

        <div class="history-box">
            <h2><i class="fas fa-history"></i> Lịch sử phản hồi từ Ban quản trị</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 100px;">Mã thư</th>
                        <th style="width: 140px;">Thời gian</th>
                        <th>Chi tiết khiếu nại & Trả lời từ Admin</th>
                        <th style="width: 150px; text-align: center;">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_reports)): ?>
                        <tr id="empty_row"><td colspan="4" style="text-align:center; color:var(--text-faint); padding:40px;">Bạn chưa gửi bất kỳ khiếu nại nào.</td></tr>
                    <?php else: ?>
                        <?php foreach ($my_reports as $row): 
                            $statusClass = 'status-' . str_replace(' ', '-', $row['status']);
                        ?>
                            <tr id="user_report_row_<?= $row['id'] ?>">
                                <td><strong style="color: var(--accent); font-family: var(--font-mono);">#<?= $row['id'] ?></strong></td>
                                <td><small style="color:var(--text-faint);"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></small></td>
                                <td class="report-content-td">
                                    <div style="font-weight: 600; color:var(--text); margin-bottom:4px;"><?= htmlspecialchars($row['title']) ?></div>
                                    <div style="color:var(--text-dim); font-size:13px; white-space:pre-line;"><?= htmlspecialchars($row['content']) ?></div>
                                    
                                    <div class="reply-container">
                                        <?php if (!empty($row['admin_note'])): ?>
                                            <div class="admin-reply">
                                                <strong><i class="fas fa-reply"></i> Phản hồi từ Admin:</strong>
                                                <span class="reply-text"><?= nl2br(htmlspecialchars($row['admin_note'])) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge <?= $statusClass ?> user-status-badge"><?= htmlspecialchars($row['status']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const userId = <?= (int)$user_id ?>;
        
        setInterval(() => {
            fetch(`admin/check_reports.php?user_id=${userId}`)
                .then(res => res.json())
                .then(res => {
                    if (res && res.status === 'success') {
                        res.data.forEach(report => {
                            const row = document.getElementById(`user_report_row_${report.id}`);
                            if (row) {
                                // 1. Cập nhật chữ và màu sắc của Badge trạng thái quy trình
                                const badge = row.querySelector('.user-status-badge');
                                if (badge && badge.innerText.trim() !== report.status) {
                                    badge.innerText = report.status;
                                    badge.className = `badge status-${report.status.replace(/\s+/g, '-')} user-status-badge`;
                                }

                                // 2. Cập nhật nội dung văn bản phản hồi từ Admin Note
                                const replyContainer = row.querySelector('.reply-container');
                                if (report.admin_note) {
                                    replyContainer.innerHTML = `
                                        <div class="admin-reply">
                                            <strong><i class="fas fa-reply"></i> Phản hồi từ Admin:</strong>
                                            <span class="reply-text">${report.admin_note.replace(/\n/g, '<br>')}</span>
                                        </div>`;
                                } else {
                                    replyContainer.innerHTML = '';
                                }
                            }
                        });
                    }
                }).catch(err => console.log(err));
        }, 3000); // Quét đồng bộ 3 giây/lần
    </script>
</body>
</html>