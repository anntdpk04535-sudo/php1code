<?php
session_start();
require_once "./db_utils.php";
$db = new DB_UTILS();

$product_id = $_GET['id'] ?? '';
$product = $db->getOne("SELECT * FROM products WHERE product_id = ?", [$product_id]);

if (!$product) {
    header("Location: index.php");
    exit;
}

$cleanPrice = (int) preg_replace('/[^\d]/', '', $product['price']);

$cart_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['qty'];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['tenSP'] ?? $product['description'] ?? 'Chi tiết sản phẩm') ?> - TechShop</title>
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

            --gold: #d8b87a;
            --gold-strong: #eccb8f;

            --warn: #ffb454;
            --warn-dim: rgba(255, 180, 84, 0.12);

            --admin: #4ee6a8;

            --danger: #ff5e72;
            --danger-dim: rgba(255, 94, 114, 0.14);

            --radius-lg: 18px;
            --radius-md: 12px;
            --radius-sm: 8px;

            --font-display: 'Space Grotesk', sans-serif;
            --font-body: 'Inter', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text);
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

        /* ===== HEADER ===== */
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

        .nav-links {
            display: flex;
            align-items: center;
            gap: 22px;
            flex-wrap: wrap;
        }

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

        .nav-links > a:hover {
            color: var(--accent);
        }

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

        .btn-cart-nav:hover {
            border-color: var(--accent-border);
            color: var(--accent) !important;
        }

        .badge {
            background: var(--danger);
            color: #fff;
            padding: 1px 7px;
            border-radius: 999px;
            font-size: 11px;
            font-family: var(--font-mono);
            font-weight: 700;
            box-shadow: 0 0 10px rgba(255, 94, 114, 0.45);
        }

        /* ===== BREADCRUMB ===== */
        .container {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 28px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: var(--font-mono);
            font-size: 12.5px;
            color: var(--text-faint);
            padding: 28px 0 0;
        }

        .breadcrumb a {
            color: var(--text-dim);
            text-decoration: none;
        }

        .breadcrumb a:hover { color: var(--accent); }

        /* ===== PRODUCT DETAIL ===== */
        .detail-wrapper {
            display: grid;
            grid-template-columns: 1fr 1.1fr;
            gap: 44px;
            background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 36px;
            margin: 22px 0 60px;
        }

        .img-container {
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-md);
            background: var(--bg-soft);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 440px;
        }

        .img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .detail-info-box {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .detail-id {
            font-family: var(--font-mono);
            font-size: 12px;
            color: var(--accent);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            margin-bottom: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .detail-id::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 8px 1px var(--accent-glow);
        }

        .detail-title {
            font-family: var(--font-display);
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 18px;
            line-height: 1.25;
            letter-spacing: -0.01em;
        }

        .detail-price {
            font-family: var(--font-mono);
            font-size: 30px;
            font-weight: 700;
            color: var(--gold-strong);
            text-shadow: 0 0 18px rgba(216, 184, 122, 0.2);
            margin-bottom: 28px;
        }

        .detail-desc {
            color: var(--text-dim);
            font-size: 14.5px;
            line-height: 1.75;
            border-top: 1px solid var(--border-soft);
            padding-top: 20px;
            margin-bottom: 28px;
        }

        .detail-desc strong { color: var(--text); }

        .policy-list {
            list-style: none;
            margin-bottom: 32px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .policy-list li {
            font-size: 13.5px;
            color: var(--text-dim);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .policy-list li i {
            color: var(--accent);
            width: 16px;
        }

        .detail-actions {
            display: flex;
            gap: 12px;
        }

        .btn-action-buy {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            flex: 1;
            background: var(--accent);
            color: var(--bg);
            text-decoration: none;
            font-family: var(--font-mono);
            font-weight: 700;
            font-size: 13.5px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            padding: 16px 28px;
            border-radius: var(--radius-sm);
            border: none;
            overflow: hidden;
            box-shadow: 0 14px 28px -10px var(--accent-glow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-action-buy::before {
            content: "";
            position: absolute;
            top: 0;
            left: -120%;
            width: 60%;
            height: 100%;
            background: linear-gradient(120deg, transparent, rgba(255, 255, 255, 0.5), transparent);
            transform: skewX(-20deg);
            transition: left 0.5s ease;
        }

        .btn-action-buy:hover::before { left: 130%; }
        .btn-action-buy:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 34px -10px var(--accent-glow);
        }

        @media (max-width: 760px) {
            header { padding: 14px 18px; }
            .container { padding: 0 18px; }
            .detail-wrapper { grid-template-columns: 1fr; padding: 22px; gap: 26px; }
            .img-container { height: 280px; }
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
                        class="badge"><?= $cart_count ?></span></a>
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
            <a href="index.php">Trang chủ</a> <span>/</span> <span style="color: var(--text-dim);">Chi tiết sản phẩm</span>
        </div>

        <div class="detail-wrapper">
            <div class="img-container">
                <img src="<?= htmlspecialchars($product['image'] ?? '') ?>"
                    onerror="this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500'">
            </div>
            <div class="detail-info-box">
                <div class="detail-id">Mã SP: <?= htmlspecialchars($product['product_id']) ?></div>
                <h1 class="detail-title">
                    <?= htmlspecialchars($product['tenSP'] ?? $product['description'] ?? 'Sản phẩm') ?></h1>
                <div class="detail-price"><?= number_format($cleanPrice, 0, ',', '.') ?>đ</div>

                <div class="detail-desc">
                    <strong>Mô tả thiết bị:</strong><br>
                    Sản phẩm <?= htmlspecialchars($product['tenSP'] ?? $product['description'] ?? 'thiết bị') ?> cấu
                    hình mạnh mẽ chính hãng, đáp ứng tối đa hiệu năng xử lý hệ thống và trò chơi thế hệ mới.
                </div>

                <ul class="policy-list">
                    <li><i class="fa-solid fa-bolt"></i> Bảo hành phần cứng hạ tầng 12 tháng</li>
                    <li><i class="fa-solid fa-truck-fast"></i> Giao hàng an toàn qua luồng mã hóa</li>
                    <li><i class="fa-solid fa-credit-card"></i> Hỗ trợ trả góp tín dụng liên kết 0%</li>
                </ul>

                <div class="detail-actions">
                    <a href="cart.php?add=<?= urlencode($product['product_id']) ?>" class="btn-action-buy">
                        <i class="fa-solid fa-cart-plus"></i> Thêm vào giỏ hàng
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>