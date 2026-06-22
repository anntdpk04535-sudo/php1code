<?php
session_start();
require_once "./db_utils.php";
$db = new DB_UTILS();

// Lấy danh sách danh mục
$categories = $db->getAll("SELECT * FROM categories ORDER BY name ASC");

// Lọc theo danh mục (nếu có)
$active_slug = $_GET['danh-muc'] ?? '';
$active_cat  = null;
if ($active_slug) {
    $active_cat = $db->getOne("SELECT * FROM categories WHERE slug = ?", [$active_slug]);
}

// Lấy danh sách sản phẩm từ CSDL đổ ra trang chủ
if ($active_cat) {
    $products = $db->getAll(
        "SELECT * FROM products WHERE category_id = ? ORDER BY product_id DESC",
        [$active_cat['category_id']]
    );
} else {
    $products = $db->getAll("SELECT * FROM products ORDER BY product_id DESC");
}

// Tính số lượng trong giỏ hàng để hiển thị ban đầu khi tải trang
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
    <title>TechShop — Trang Chủ</title>
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

        /* Lưới nền mờ dùng chung toàn site */
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

        /* ===== HERO ===== */
        .hero-banner {
            position: relative;
            color: var(--text);
            padding: 90px 40px 80px;
            text-align: center;
            overflow: hidden;
        }

        .hero-banner::before {
            content: "";
            position: absolute;
            top: -260px;
            left: 50%;
            transform: translateX(-50%);
            width: 900px;
            height: 500px;
            background: radial-gradient(ellipse at center, rgba(0, 230, 195, 0.14), transparent 70%);
            pointer-events: none;
        }

        .hero-eyebrow {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            font-family: var(--font-mono);
            font-size: 12px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 18px;
        }

        .hero-eyebrow::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 8px 1px var(--accent-glow);
        }

        .hero-banner h1 {
            position: relative;
            font-family: var(--font-display);
            font-weight: 700;
            font-size: clamp(32px, 5vw, 52px);
            letter-spacing: -0.02em;
            margin-bottom: 16px;
        }

        .hero-banner h1 .accent-text {
            color: var(--accent);
            text-shadow: 0 0 28px var(--accent-glow);
        }

        .hero-sub {
            position: relative;
            color: var(--text-dim);
            font-size: 15.5px;
            max-width: 480px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* ===== CONTAINER / SECTION ===== */
        .container {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 10px auto 60px;
            padding: 0 28px;
        }

        .section-eyebrow {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: var(--font-mono);
            font-size: 12px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .section-eyebrow::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 8px 1px var(--accent-glow);
        }

        .section-title {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 30px;
            padding-left: 14px;
            border-left: 2px solid var(--accent);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 26px;
        }

        /* ===== PRODUCT CARD ===== */
        .p-card {
            background: var(--surface-2);
            border-radius: var(--radius-md);
            overflow: hidden;
            border: 1px solid var(--border-soft);
            border-left: 2px solid transparent;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.2s ease;
        }

        .p-card:hover {
            transform: translateY(-3px);
            border-color: var(--border);
            border-left-color: var(--accent);
            box-shadow: 0 16px 34px -20px rgba(0, 230, 195, 0.3);
        }

        .p-img-box {
            position: relative;
            width: 100%;
            height: 190px;
            background: var(--bg-soft);
            overflow: hidden;
        }

        .p-stock-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--danger);
            color: #fff;
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 700;
            padding: 4px 11px;
            border-radius: 999px;
            box-shadow: 0 0 10px rgba(255, 94, 114, 0.45);
            z-index: 2;
            letter-spacing: 0.02em;
        }

        .p-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.35s ease;
        }

        .p-card:hover img {
            transform: scale(1.045);
        }

        .p-info {
            padding: 18px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .p-title {
            font-family: var(--font-body);
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text);
            min-height: 42px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .p-price {
            font-family: var(--font-mono);
            color: var(--gold-strong);
            font-weight: 700;
            font-size: 17px;
            margin-bottom: 4px;
            text-shadow: 0 0 14px rgba(216, 184, 122, 0.18);
        }

        .p-stock {
            font-family: var(--font-mono);
            font-size: 12px;
            color: var(--text-dim);
            margin-bottom: 16px;
        }
        .p-stock-low { color: var(--warn); }
        .p-stock-out { color: var(--danger); font-weight: 600; }

        .btn-group-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: auto;
        }

        .btn-row-top {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-align: center;
            padding: 10px;
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 12.5px;
            font-family: var(--font-body);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-detail {
            flex: 1;
            background: var(--bg-soft);
            color: var(--text-dim);
            border: 1px solid var(--border);
        }

        .btn-detail:hover {
            border-color: var(--accent-border);
            color: var(--accent);
        }

        .btn-add-cart {
            flex: 1;
            background: var(--accent-dim);
            color: var(--accent);
            border: 1px solid var(--accent-border);
        }

        .btn-add-cart:hover {
            background: rgba(0, 230, 195, 0.2);
        }

        .btn-buy-now {
            position: relative;
            background: var(--accent);
            color: var(--bg);
            width: 100%;
            border: none;
            font-weight: 700;
            overflow: hidden;
        }

        .btn-buy-now::before {
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

        .btn-buy-now:hover::before { left: 130%; }
        .btn-buy-now:hover { box-shadow: 0 10px 24px -8px var(--accent-glow); }

        .btn-action[disabled] {
            opacity: 0.45;
            cursor: not-allowed;
            pointer-events: none;
        }

        .btn-disabled {
            background: var(--bg-soft) !important;
            color: var(--text-faint) !important;
            border: 1px solid var(--border);
            cursor: not-allowed;
        }

        .empty-products {
            grid-column: 1 / -1;
            text-align: center;
            color: var(--text-faint);
            font-family: var(--font-mono);
            font-size: 13px;
            padding: 40px 0;
        }

        /* ===== TOAST ===== */
        .toast-notification {
            position: fixed;
            top: 90px;
            right: -380px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-left: 3px solid var(--accent);
            color: var(--text);
            padding: 15px 22px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 18px 40px -16px rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 9999;
            transition: right 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .toast-notification.show {
            right: 30px;
        }

        .toast-notification #toastIcon {
            color: var(--accent);
            font-size: 17px;
        }

        .toast-notification.toast-error {
            border-left-color: var(--danger);
        }

        .toast-notification.toast-error #toastIcon {
            color: var(--danger);
        }

        /* ===== CHATBOT ===== */
        #chatbot-wrapper {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 99999;
            font-family: var(--font-body);
        }

        #chatbot-toggle-btn {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            background: var(--surface-2);
            border: 1px solid var(--accent-border);
            cursor: pointer;
            box-shadow: 0 10px 28px -8px var(--accent-glow);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        #chatbot-toggle-btn:hover { transform: scale(1.06); }

        .ai-avatar-icon {
            width: 40px;
            height: 40px;
            object-fit: contain;
            filter: brightness(0) saturate(100%) invert(80%) sepia(40%) saturate(900%) hue-rotate(110deg) brightness(1.05);
        }

        .pulse-ring {
            position: absolute;
            inset: -4px;
            border: 2px solid var(--accent);
            border-radius: 50%;
            animation: aiGlowPulse 1.8s infinite ease-out;
            opacity: 0;
        }

        @keyframes aiGlowPulse {
            0% { transform: scale(0.9); opacity: 0.7; }
            100% { transform: scale(1.2); opacity: 0; }
        }

        #chatbot-box {
            width: 360px;
            height: 480px;
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: 0 24px 60px -16px rgba(0, 0, 0, 0.7);
            flex-direction: column;
            overflow: hidden;
            border: 1px solid var(--border);
            position: absolute;
            bottom: 78px;
            right: 0;
        }

        .chat-header {
            background: linear-gradient(135deg, var(--surface-2), var(--bg-soft));
            border-bottom: 1px solid var(--accent-border);
            color: var(--text);
            padding: 15px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-header strong {
            font-family: var(--font-display);
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .chat-header strong i { color: var(--accent); }

        .online-dot {
            width: 8px;
            height: 8px;
            background: #22c55e;
            border-radius: 50%;
            box-shadow: 0 0 8px #22c55e;
        }

        .chat-header button {
            background: none;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
            font-size: 15px;
            transition: color 0.2s ease;
        }
        .chat-header button:hover { color: var(--text); }

        .chat-body {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            background: var(--bg-soft);
            display: flex;
            flex-direction: column;
            gap: 12px;
            scroll-behavior: smooth;
        }

        .msg-user {
            align-self: flex-end;
            background: var(--accent);
            color: var(--bg);
            padding: 10px 14px;
            border-radius: 14px 14px 0 14px;
            max-width: 85%;
            font-size: 13px;
            line-height: 1.45;
            font-weight: 500;
            word-break: break-word;
        }

        .msg-bot {
            align-self: flex-start;
            background: var(--surface-2);
            border: 1px solid var(--border-soft);
            color: var(--text-dim);
            padding: 10px 14px;
            border-radius: 14px 14px 14px 0;
            max-width: 85%;
            font-size: 13px;
            line-height: 1.45;
            word-break: break-word;
        }

        .chat-footer {
            padding: 12px;
            display: flex;
            gap: 8px;
            border-top: 1px solid var(--border-soft);
            background: var(--surface);
            align-items: center;
        }

        .chat-footer input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 20px;
            outline: none;
            font-size: 13px;
            background: var(--bg-soft);
            color: var(--text);
        }

        .chat-footer input::placeholder { color: var(--text-faint); }
        .chat-footer input:focus { border-color: var(--accent-border); }

        #chat-send-btn {
            background: var(--accent);
            color: var(--bg);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        #chat-send-btn:hover { box-shadow: 0 0 0 4px var(--accent-dim); }

        .chat-products-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            width: 100%;
            margin-top: 6px;
        }

        .chat-prod-card {
            background: var(--surface-2);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-soft);
            padding: 8px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
        }

        .chat-prod-card:hover {
            transform: translateY(-2px);
            border-color: var(--accent-border);
            box-shadow: 0 8px 18px -10px var(--accent-glow);
        }

        .chat-prod-img {
            width: 100%;
            height: 90px;
            object-fit: cover;
            border-radius: 6px;
            background: var(--bg-soft);
        }

        .chat-prod-name {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 32px;
            line-height: 1.4;
        }

        .chat-prod-price {
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 700;
            color: var(--gold-strong);
            margin-top: auto;
        }

        @media (max-width: 760px) {
            header { padding: 14px 18px; }
            .hero-banner { padding: 70px 24px 60px; }
            .container { padding: 0 18px; }
            #chatbot-box { width: 92vw; right: -8px; }
        }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }

        /* ===== CATEGORY FILTER BAR ===== */
        .cat-filter-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 28px;
            padding: 16px 20px;
            background: var(--surface);
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-md);
        }
        .cat-filter-label {
            font-size: 11.5px;
            font-family: var(--font-mono);
            color: var(--text-faint);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-right: 4px;
            white-space: nowrap;
        }
        .cat-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 15px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--border);
            color: var(--text-dim);
            background: var(--surface-2);
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .cat-chip:hover {
            border-color: var(--accent-border);
            color: var(--accent);
            background: var(--accent-dim);
        }
        .cat-chip.active {
            border-color: var(--accent-border);
            color: var(--accent);
            background: var(--accent-dim);
            box-shadow: 0 0 12px -4px var(--accent-glow);
        }
    </style>
</head>

<body>
    <div id="cartToast" class="toast-notification">
        <i id="toastIcon" class="fa-solid fa-circle-check"></i> <span id="toastMessage">Thêm sản phẩm vào giỏ hàng thành công!</span>
    </div>

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

    <div class="hero-banner">
        <div class="hero-eyebrow">TechShop</div>
        <h1>Chào mừng đến với <span class="accent-text">TechShop</span></h1>
        <p class="hero-sub">Thiết bị công nghệ chính hãng, cập nhật liên tục — chọn nhanh, giao gọn, giá rõ ràng.</p>
    </div>

    <div class="container">
        <div class="section-eyebrow">Danh mục</div>
        <h2 class="section-title">
            <?= $active_cat ? htmlspecialchars($active_cat['name']) : 'Sản phẩm nổi bật' ?>
        </h2>

        <?php if (!empty($categories)): ?>
        <div class="cat-filter-bar">
            <span class="cat-filter-label"><i class="fa-solid fa-layer-group"></i> Lọc:</span>
            <a href="index.php" class="cat-chip <?= !$active_slug ? 'active' : '' ?>">
                <i class="fa-solid fa-th-large"></i> Tất cả
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="index.php?danh-muc=<?= urlencode($cat['slug']) ?>"
                   class="cat-chip <?= ($active_slug === $cat['slug']) ? 'active' : '' ?>">
                    <?php if (!empty($cat['icon'])): ?>
                        <i class="<?= htmlspecialchars($cat['icon']) ?>"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="grid">
            <?php if (empty($products)): ?>
                <p class="empty-products"> Hiện tại chưa có sản phẩm nào.</p>
            <?php else: ?>
                <?php foreach ($products as $p):
                    $cleanPrice = (int) preg_replace('/[^\d]/', '', $p['price']);
                    $stock = (int) ($p['stock'] ?? 0);
                    $outOfStock = $stock <= 0;
                    $stockClass = $outOfStock ? 'p-stock-out' : ($stock < 10 ? 'p-stock-low' : '');
                    ?>
                    <div class="p-card">
                        <div class="p-img-box">
                            <img src="<?= htmlspecialchars($p['image'] ?? '') ?>"
                                onerror="this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500'">
                            <?php if ($outOfStock): ?>
                                <span class="p-stock-overlay">Hết hàng</span>
                            <?php endif; ?>
                        </div>
                        <div class="p-info">
                            <div class="p-title"><?= htmlspecialchars($p['tenSP'] ?? $p['description'] ?? 'Sản phẩm') ?></div>
                            <div class="p-price"><?= number_format($cleanPrice, 0, ',', '.') ?>đ</div>
                            <div class="p-stock <?= $stockClass ?>">
                                <?= $outOfStock ? 'Hết hàng' : 'Còn lại: ' . $stock . ' sản phẩm' ?>
                            </div>

                            <div class="btn-group-actions">
                                <div class="btn-row-top">
                                    <a href="chi-tiet.php?id=<?= urlencode($p['product_id']) ?>" class="btn-action btn-detail">
                                        <i class="fa-solid fa-magnifying-glass"></i> Chi tiết
                                    </a>
                                    <button onclick="addToCartAjax('<?= urlencode($p['product_id']) ?>')"
                                        class="btn-action btn-add-cart" <?= $outOfStock ? 'disabled' : '' ?>>
                                        <i class="fa-solid fa-cart-plus"></i> Thêm giỏ
                                    </button>
                                </div>
                                <?php if ($outOfStock): ?>
                                    <span class="btn-action btn-buy-now btn-disabled">
                                        <i class="fa-solid fa-ban"></i> Hết hàng
                                    </span>
                                <?php else: ?>
                                    <a href="cart.php?add=<?= urlencode($p['product_id']) ?>" class="btn-action btn-buy-now">
                                        <i class="fa-solid fa-bolt"></i> Mua ngay
                                    </a>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function addToCartAjax(productId) {
            // Gửi request fetch ngầm sang file cart.php kèm tham số ajax=1
            fetch('cart.php?add=' + productId + '&ajax=1')
                .then(response => response.json())
                .then(data => {
                    const toast = document.getElementById('cartToast');
                    const toastIcon = document.getElementById('toastIcon');
                    const toastMsg = document.getElementById('toastMessage');
                    const badge = document.getElementById('cartBadge');

                    if (data.status === 'success') {
                        // Cập nhật số lượng trên giỏ hàng Header ngay lập tức
                        if (badge) {
                            badge.innerText = data.cart_count;
                        }
                        // Gán thông báo thành công
                        toast.classList.remove('toast-error');
                        toastIcon.className = 'fa-solid fa-circle-check';
                        toastMsg.innerText = "Thêm sản phẩm vào giỏ hàng thành công!";
                    } else {
                        // Gán thông báo lỗi (chưa đăng nhập hoặc không đủ tồn kho)
                        toast.classList.add('toast-error');
                        toastIcon.className = 'fa-solid fa-triangle-exclamation';
                        toastMsg.innerText = data.message;

                        // Chỉ tự động đá sang trang login nếu lỗi thực sự là do chưa đăng nhập
                        if (data.reason === 'login') {
                            setTimeout(() => {
                                window.location.href = 'login.php';
                            }, 1500);
                        }
                    }

                    // Kích hoạt hiệu ứng trượt Toast ra ngoài màn hình
                    toast.classList.add('show');

                    // Tự ẩn thông báo đi sau 2.5 giây
                    setTimeout(() => {
                        toast.classList.remove('show');
                    }, 2500);
                })
                .catch(error => {
                    console.error('Lỗi kết nối hệ thống giỏ hàng:', error);
                });
        }
    </script>
<div id="chatbot-wrapper">
    <button id="chatbot-toggle-btn" onclick="toggleChatbot()">
        <img src="https://cdn-icons-png.flaticon.com/512/4712/4712010.png" alt="AI Avatar" class="ai-avatar-icon">
        <span class="pulse-ring"></span>
    </button>

    <div id="chatbot-box" style="display: none;">
        <div class="chat-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div class="online-dot"></div>
                <strong><i class="fa-solid fa-robot"></i> Trợ lý ảo TechShop</strong>
            </div>
            <button onclick="toggleChatbot()">✕</button>
        </div>

        <div class="chat-body" id="chat-messages-container">
            <div class="msg-bot">Xin chào! Mình là Trợ lý ảo TechShop. Bạn cần tìm sản phẩm trong khoảng giá nào thế? (Hãy gõ thử câu hỏi: **"website có sản phẩm từ 5.000 đến 1.000.000 không"** nhé!)</div>
        </div>

        <div class="chat-footer">
            <input type="text" id="chat-user-input" placeholder="Hỏi khoảng giá sản phẩm..." onkeypress="handleChatKeyPress(event)">
            <button id="chat-send-btn" onclick="sendChatMessage()">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script>
    function toggleChatbot() {
        const chatBox = document.getElementById('chatbot-box');
        chatBox.style.display = (chatBox.style.display === 'none' || chatBox.style.display === '') ? 'flex' : 'none';
    }

    function handleChatKeyPress(event) {
        if (event.key === 'Enter') { sendChatMessage(); }
    }

    function sendChatMessage() {
        const inputEl = document.getElementById('chat-user-input');
        const message = inputEl.value.trim();
        if (message === '') return;

        const container = document.getElementById('chat-messages-container');

        const userMsgDiv = document.createElement('div');
        userMsgDiv.className = 'msg-user';
        userMsgDiv.innerText = message;
        container.appendChild(userMsgDiv);

        inputEl.value = '';
        container.scrollTop = container.scrollHeight;

        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'msg-bot';
        loadingDiv.innerText = '🤖 Đang tìm kiếm sản phẩm...';
        container.appendChild(loadingDiv);
        container.scrollTop = container.scrollHeight;

        let formData = new FormData();
        formData.append('message', message);

        fetch('chatbot_api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(container.contains(loadingDiv)) container.removeChild(loadingDiv);

            if (data && data.status === 'success') {
                const botMsgDiv = document.createElement('div');
                botMsgDiv.className = 'msg-bot';
                botMsgDiv.innerHTML = data.reply;
                container.appendChild(botMsgDiv);

                // HIỂN THỊ SẢN PHẨM TRỰC QUAN DẠNG THẺ CARD KÈM ẢNH
                if (data.products && data.products.length > 0) {
                    const gridDiv = document.createElement('div');
                    gridDiv.className = 'chat-products-grid';

                    data.products.forEach(p => {
                        // Tự động chuẩn hóa đường dẫn ảnh (Chèn thêm admin/ nếu là ảnh upload cục bộ từ admin panel)
                        let imgUrl = p.image;
                        if (imgUrl && !imgUrl.startsWith('http') && !imgUrl.startsWith('admin/')) {
                            imgUrl = 'admin/' + imgUrl;
                        }

                        // Định dạng lại hiển thị chuỗi giá tiền cho đẹp
                        let formattedPrice = p.price;
                        if (!isNaN(p.price) && p.price > 0) {
                            formattedPrice = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
                        }

                        gridDiv.innerHTML += `
                            <a href="chi-tiet.php?id=${p.product_id}" class="chat-prod-card" target="_blank">
                                <img src="${imgUrl}" class="chat-prod-img" onerror="this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=200'">
                                <div class="chat-prod-name">${p.description || 'Sản phẩm công nghệ'}</div>
                                <div class="chat-prod-price">${formattedPrice}</div>
                            </a>
                        `;
                    });
                    container.appendChild(gridDiv);
                }
            }
            container.scrollTop = container.scrollHeight;
        })
        .catch(err => {
            if(container.contains(loadingDiv)) container.removeChild(loadingDiv);
            console.error('Lỗi kết nối Chatbot:', err);
        });
    }
</script>
</body>

</html>