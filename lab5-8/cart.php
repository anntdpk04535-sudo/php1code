<?php
session_start();
require_once "./db_utils.php";
$db = new DB_UTILS();

// Kiểm tra xem đây có phải là một yêu cầu gửi ngầm từ Ajax không
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == 1;

// Khóa bảo mật: Nếu chưa đăng nhập
if (!isset($_SESSION['user'])) {
  if ($is_ajax) {
    echo json_encode(['status' => 'error', 'reason' => 'login', 'message' => 'Vui lòng đăng nhập để mua hàng!']);
    exit;
  }
  // Nếu truy cập trực tiếp truyền thống, đá về trang login
  header("Location: login.php");
  exit;
}

if (!isset($_SESSION['cart'])) {
  $_SESSION['cart'] = [];
}

// 1. XỬ LÝ THÊM SẢN PHẨM HOẶC TĂNG SỐ LƯỢNG
if (isset($_GET['add'])) {
  $product_id = $_GET['add'];
  $prod = $db->getOne("SELECT * FROM products WHERE product_id = ?", [$product_id]);

  $stockError = '';

  if (!$prod) {
    $stockError = 'Sản phẩm không tồn tại hoặc đã bị gỡ khỏi hệ thống!';
  } else {
    $stock = (int) ($prod['stock'] ?? 0);
    $currentQtyInCart = isset($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id]['qty'] : 0;

    if ($stock <= 0) {
      $stockError = 'Rất tiếc, sản phẩm này hiện đã hết hàng!';
    } elseif ($currentQtyInCart + 1 > $stock) {
      $stockError = "Số lượng trong giỏ hàng đã đạt mức tối đa hiện có ($stock sản phẩm)!";
    } else {
      $cleanPrice = (int) preg_replace('/[^\d]/', '', $prod['price']);
      $hinh = !empty($prod['image']) ? $prod['image'] : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500';

      if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id]['qty']++;
      } else {
        $_SESSION['cart'][$product_id] = [
          'id' => $prod['product_id'],
          'ten' => $prod['description'] ?? $prod['tenSP'] ?? 'Sản phẩm',
          'gia' => $cleanPrice,
          'hinh_anh' => $hinh,
          'qty' => 1
        ];
      }
    }
  }

  // Tính lại tổng số lượng mặt hàng trong giỏ để cập nhật Badge thời gian thực
  $cart_count = 0;
  foreach ($_SESSION['cart'] as $item) {
    $cart_count += $item['qty'];
  }

  // Nếu là Ajax (Nút Thêm giỏ), trả về kết quả JSON và kết thúc ngay tại đây (Không load lại trang)
  if ($is_ajax) {
    if ($stockError !== '') {
      echo json_encode([
        'status' => 'error',
        'reason' => 'stock',
        'message' => $stockError,
        'cart_count' => $cart_count
      ]);
    } else {
      echo json_encode([
        'status' => 'success',
        'cart_count' => $cart_count
      ]);
    }
    exit;
  }

  // Nếu là nút "Mua ngay" truyền thống, chuyển hướng thẳng vào trang giỏ hàng (kèm thông báo lỗi nếu có)
  if ($stockError !== '') {
    $_SESSION['cart_flash_error'] = $stockError;
  }
  header("Location: cart.php");
  exit;
}

// 2. XỬ LÝ TĂNG SỐ LƯỢNG TRÊN TRANG GIỎ HÀNG
if (isset($_GET['increase'])) {
  $product_id = $_GET['increase'];
  if (isset($_SESSION['cart'][$product_id])) {
    // Luôn lấy tồn kho mới nhất từ CSDL để tránh trường hợp tồn kho đã thay đổi
    $prod = $db->getOne("SELECT stock FROM products WHERE product_id = ?", [$product_id]);
    $stock = $prod ? (int) $prod['stock'] : 0;

    if ($_SESSION['cart'][$product_id]['qty'] + 1 > $stock) {
      $_SESSION['cart_flash_error'] = "Số lượng đã đạt mức tối đa hiện có ($stock sản phẩm)!";
    } else {
      $_SESSION['cart'][$product_id]['qty']++;
    }
  }
  header("Location: cart.php");
  exit;
}

// 3. XỬ LÝ GIẢM SỐ LƯỢNG TRÊN TRANG GIỎ HÀNG
if (isset($_GET['decrease'])) {
  $product_id = $_GET['decrease'];
  if (isset($_SESSION['cart'][$product_id])) {
    $_SESSION['cart'][$product_id]['qty']--;
    if ($_SESSION['cart'][$product_id]['qty'] <= 0) {
      unset($_SESSION['cart'][$product_id]);
    }
  }
  header("Location: cart.php");
  exit;
}

// 4. XỬ LÝ XÓA HẲN SẢN PHẨM KHỎI GIỎ HÀNG
if (isset($_GET['remove'])) {
  $product_id = $_GET['remove'];
  unset($_SESSION['cart'][$product_id]);
  header("Location: cart.php");
  exit;
}

// Lấy thông báo lỗi (nếu có) từ lần thao tác trước rồi xóa khỏi session
$flashError = $_SESSION['cart_flash_error'] ?? '';
unset($_SESSION['cart_flash_error']);

// Tính tổng số lượng & tổng tiền để hiển thị (không ảnh hưởng logic xử lý ở trên)
$totalAll = 0;
$totalQty = 0;
if (!empty($_SESSION['cart'])) {
  foreach ($_SESSION['cart'] as $item) {
    $totalAll += $item['gia'] * $item['qty'];
    $totalQty += $item['qty'];
  }
}

// Lấy tồn kho hiện tại của từng sản phẩm trong giỏ (để giới hạn nút tăng số lượng trên giao diện)
$stockMap = [];
if (!empty($_SESSION['cart'])) {
  $ids = array_keys($_SESSION['cart']);
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stockRows = $db->getAll("SELECT product_id, stock FROM products WHERE product_id IN ($placeholders)", $ids);
  foreach ($stockRows as $row) {
    $stockMap[$row['product_id']] = (int) $row['stock'];
  }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Giỏ Hàng — TechShop</title>
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
      --gold-dim: rgba(216, 184, 122, 0.12);

      --danger: #ff5e72;
      --danger-dim: rgba(255, 94, 114, 0.12);

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

    html, body {
      min-height: 100%;
    }

    body {
      font-family: var(--font-body);
      background: var(--bg);
      color: var(--text);
      padding: 56px 20px;
      position: relative;
      overflow-x: hidden;
    }

    /* Lưới nền mờ + glow công nghệ */
    body::before {
      content: "";
      position: fixed;
      inset: 0;
      background-image:
        linear-gradient(to right, rgba(255, 255, 255, 0.025) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
      background-size: 42px 42px;
      mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black 30%, transparent 75%);
      pointer-events: none;
      z-index: 0;
    }

    body::after {
      content: "";
      position: fixed;
      top: -200px;
      left: 50%;
      transform: translateX(-50%);
      width: 900px;
      height: 500px;
      background: radial-gradient(ellipse at center, rgba(0, 230, 195, 0.10), transparent 70%);
      pointer-events: none;
      z-index: 0;
    }

    .cart-page {
      position: relative;
      z-index: 1;
      max-width: 980px;
      margin: 0 auto;
    }

    /* Eyebrow */
    .eyebrow {
      display: flex;
      align-items: center;
      gap: 10px;
      font-family: var(--font-mono);
      font-size: 12px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 14px;
    }

    .eyebrow::before {
      content: "";
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--accent);
      box-shadow: 0 0 8px 1px var(--accent-glow);
    }

    .cart-container {
      background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 38px clamp(20px, 4vw, 44px);
      box-shadow: 0 30px 60px -25px rgba(0, 0, 0, 0.65);
    }

    .cart-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      gap: 20px;
      margin-bottom: 30px;
      padding-bottom: 22px;
      border-bottom: 1px solid var(--border-soft);
      flex-wrap: wrap;
    }

    .cart-header h1 {
      font-family: var(--font-display);
      font-size: clamp(24px, 3.4vw, 30px);
      font-weight: 700;
      color: var(--text);
      letter-spacing: -0.01em;
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .item-count-badge {
      font-family: var(--font-mono);
      font-size: 12px;
      font-weight: 600;
      color: var(--accent);
      background: var(--accent-dim);
      border: 1px solid var(--accent-border);
      border-radius: 999px;
      padding: 4px 12px;
    }

    .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      text-decoration: none;
      color: var(--text-dim);
      font-weight: 500;
      font-size: 13.5px;
      font-family: var(--font-body);
      padding: 9px 16px;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      transition: all 0.2s ease;
    }

    .btn-back:hover {
      color: var(--accent);
      border-color: var(--accent-border);
      background: var(--accent-dim);
    }

    .btn-back .arrow {
      transition: transform 0.2s ease;
    }

    .btn-back:hover .arrow {
      transform: translateX(-3px);
    }

    /* === DANH SÁCH SẢN PHẨM (dạng card, không dùng table) === */
    .cart-items {
      display: flex;
      flex-direction: column;
      gap: 14px;
      margin-bottom: 28px;
    }

    .cart-items-head {
      display: grid;
      grid-template-columns: 1fr 130px 150px 130px 60px;
      gap: 16px;
      padding: 0 18px 10px;
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--text-faint);
    }

    .cart-item {
      display: grid;
      grid-template-columns: 1fr 130px 150px 130px 60px;
      align-items: center;
      gap: 16px;
      background: var(--surface-2);
      border: 1px solid var(--border-soft);
      border-left: 2px solid transparent;
      border-radius: var(--radius-md);
      padding: 16px 18px;
      transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.2s ease;
    }

    .cart-item:hover {
      border-color: var(--border);
      border-left-color: var(--accent);
      box-shadow: 0 0 0 1px rgba(0, 230, 195, 0.06), 0 14px 30px -18px rgba(0, 230, 195, 0.25);
      transform: translateY(-1px);
    }

    .product-cell {
      display: flex;
      align-items: center;
      gap: 14px;
      min-width: 0;
    }

    .item-img-wrap {
      width: 64px;
      height: 64px;
      flex-shrink: 0;
      border-radius: var(--radius-sm);
      padding: 2px;
      background: linear-gradient(135deg, rgba(0, 230, 195, 0.35), rgba(0, 230, 195, 0.02));
    }

    .item-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 7px;
      background: var(--bg-soft);
      display: block;
    }

    .item-title {
      font-family: var(--font-body);
      font-weight: 600;
      color: var(--text);
      font-size: 14.5px;
      line-height: 1.4;
      overflow: hidden;
      text-overflow: ellipsis;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
    }

    .item-price {
      font-family: var(--font-mono);
      color: var(--text-dim);
      font-size: 13.5px;
    }

    .item-total-price {
      font-family: var(--font-mono);
      color: var(--gold-strong);
      font-weight: 700;
      font-size: 15px;
      text-shadow: 0 0 14px rgba(216, 184, 122, 0.18);
    }

    /* Bộ tăng giảm số lượng */
    .quantity-control {
      display: inline-flex;
      align-items: center;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      overflow: hidden;
      background: var(--bg-soft);
      width: fit-content;
    }

    .btn-qty {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 30px;
      height: 30px;
      text-decoration: none;
      color: var(--text-dim);
      font-family: var(--font-mono);
      font-weight: 600;
      font-size: 15px;
      transition: all 0.2s ease;
    }

    .btn-qty:hover {
      background: var(--accent-dim);
      color: var(--accent);
    }

    .btn-qty-disabled {
      opacity: 0.35;
      cursor: not-allowed;
      pointer-events: none;
    }

    .item-stock-note {
      font-family: var(--font-mono);
      font-size: 10.5px;
      color: var(--warn);
      margin-top: 6px;
      text-align: center;
    }

    .cart-alert {
      position: relative;
      z-index: 1;
      max-width: 980px;
      margin: 0 auto 18px;
      background: var(--danger-dim);
      border: 1px solid var(--danger);
      color: var(--danger);
      padding: 14px 18px;
      border-radius: var(--radius-sm);
      font-size: 14px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .qty-number {
      width: 36px;
      text-align: center;
      font-family: var(--font-mono);
      font-weight: 700;
      font-size: 13.5px;
      color: var(--text);
      border-left: 1px solid var(--border);
      border-right: 1px solid var(--border);
      height: 30px;
      line-height: 30px;
    }

    /* Nút xóa */
    .btn-remove {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 34px;
      height: 34px;
      text-decoration: none;
      color: var(--text-faint);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      background: var(--bg-soft);
      transition: all 0.2s ease;
      font-size: 15px;
    }

    .btn-remove:hover {
      color: var(--danger);
      border-color: rgba(255, 94, 114, 0.4);
      background: var(--danger-dim);
    }

    /* === KHU VỰC TỔNG TIỀN: HUD readout === */
    .cart-summary {
      display: flex;
      justify-content: flex-end;
      margin-top: 8px;
    }

    .total-readout {
      position: relative;
      background: var(--bg-soft);
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: 20px 26px;
      min-width: 280px;
      text-align: right;
    }

    .total-readout::before,
    .total-readout::after,
    .total-readout .corner-tl,
    .total-readout .corner-br {
      content: "";
      position: absolute;
      width: 14px;
      height: 14px;
      border-color: var(--accent);
      border-style: solid;
      opacity: 0.7;
    }

    .total-readout::before {
      top: -1px;
      left: -1px;
      border-width: 2px 0 0 2px;
      border-radius: 4px 0 0 0;
    }

    .total-readout::after {
      bottom: -1px;
      right: -1px;
      border-width: 0 2px 2px 0;
      border-radius: 0 0 4px 0;
    }

    .total-label {
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--text-faint);
      margin-bottom: 6px;
    }

    .total-value {
      font-family: var(--font-mono);
      font-size: 30px;
      font-weight: 700;
      color: var(--gold-strong);
      text-shadow: 0 0 22px rgba(216, 184, 122, 0.28);
      letter-spacing: -0.01em;
    }

    .action-group {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 24px;
      gap: 16px;
      flex-wrap: wrap;
    }

    /* Nút thanh toán kiểu terminal */
    .btn-checkout {
      position: relative;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      font-family: var(--font-mono);
      font-weight: 700;
      font-size: 14.5px;
      letter-spacing: 0.04em;
      color: var(--bg);
      background: var(--accent);
      padding: 15px 30px;
      border-radius: var(--radius-sm);
      overflow: hidden;
      box-shadow: 0 0 0 1px var(--accent-border), 0 14px 30px -12px var(--accent-glow);
      transition: transform 0.15s ease, box-shadow 0.2s ease;
    }

    .btn-checkout::before {
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

    .btn-checkout:hover {
      transform: translateY(-1px);
      box-shadow: 0 0 0 1px var(--accent-strong), 0 18px 36px -10px var(--accent-glow);
    }

    .btn-checkout:hover::before {
      left: 130%;
    }

    .btn-checkout:active {
      transform: translateY(0);
    }

    /* === EMPTY STATE === */
    .empty-cart {
      text-align: center;
      padding: 70px 20px;
    }

    .empty-icon {
      width: 64px;
      height: 64px;
      margin: 0 auto 22px;
      color: var(--accent);
      opacity: 0.85;
    }

    .empty-icon svg {
      width: 100%;
      height: 100%;
      filter: drop-shadow(0 0 14px rgba(0, 230, 195, 0.3));
    }

    .empty-text {
      font-family: var(--font-display);
      color: var(--text);
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 8px;
    }

    .empty-subtext {
      font-family: var(--font-mono);
      color: var(--text-faint);
      font-size: 12.5px;
      margin-bottom: 28px;
      letter-spacing: 0.04em;
    }

    .btn-shop-now {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--accent);
      color: var(--bg);
      font-family: var(--font-mono);
      font-weight: 700;
      font-size: 13.5px;
      letter-spacing: 0.03em;
      padding: 13px 28px;
      border-radius: var(--radius-sm);
      text-decoration: none;
      box-shadow: 0 14px 30px -12px var(--accent-glow);
      transition: transform 0.15s ease;
    }

    .btn-shop-now:hover {
      transform: translateY(-1px);
    }

    /* === RESPONSIVE === */
    @media (max-width: 760px) {
      .cart-items-head { display: none; }

      .cart-item {
        grid-template-columns: 1fr;
        gap: 12px;
      }

      .product-cell { grid-column: 1 / -1; }

      .item-price::before { content: "Đơn giá: "; color: var(--text-faint); }
      .item-total-price::before { content: "Tạm tính: "; color: var(--text-faint); font-weight: 500; }

      .cart-item > .item-price,
      .cart-item > .item-total-price {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
      }

      .cart-item > div[style] { text-align: left !important; }

      .quantity-control { width: fit-content; }

      .total-readout { width: 100%; }
      .cart-summary { justify-content: stretch; }
      .action-group { flex-direction: column-reverse; align-items: stretch; }
      .btn-checkout, .btn-back { justify-content: center; }
    }

    /* Focus accessibility */
    a:focus-visible, .btn-qty:focus-visible {
      outline: 2px solid var(--accent);
      outline-offset: 2px;
    }

    @media (prefers-reduced-motion: reduce) {
      * { transition: none !important; animation: none !important; }
    }
  </style>
</head>

<body>
  <div class="cart-page">

    <?php if ($flashError): ?>
      <div class="cart-alert"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <?php if (empty($_SESSION['cart'])): ?>
      <div class="cart-container">
        <div class="eyebrow">Giỏ hàng</div>
        <div class="empty-cart">
          <div class="empty-icon">
            <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M8 10H14L19 38H50L56 18H17" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="24" cy="50" r="3.5" stroke="currentColor" stroke-width="2.5"/>
              <circle cx="46" cy="50" r="3.5" stroke="currentColor" stroke-width="2.5"/>
            </svg>
          </div>
          <p class="empty-text">Giỏ hàng đang trống</p>
          <p class="empty-subtext">Chưa có sản phẩm nào được thêm vào hệ thống</p>
          <a href="index.php" class="btn-shop-now">Khám phá sản phẩm →</a>
        </div>
      </div>
    <?php else: ?>
      <div class="eyebrow">Giỏ hàng</div>

      <div class="cart-container">
        <div class="cart-header">
          <h1>
            Giỏ hàng của bạn
            <span class="item-count-badge"><?= $totalQty ?> sản phẩm</span>
          </h1>
          <a href="index.php" class="btn-back"><span class="arrow">←</span> Tiếp tục xem sản phẩm</a>
        </div>

        <div class="cart-items">
          <div class="cart-items-head">
            <span>Sản phẩm</span>
            <span>Giá bán</span>
            <span style="text-align:center;">Số lượng</span>
            <span>Tạm tính</span>
            <span></span>
          </div>

          <?php foreach ($_SESSION['cart'] as $item):
            $rowTotal = $item['gia'] * $item['qty'];
            $imagePath = !empty($item['hinh_anh']) ? htmlspecialchars($item['hinh_anh']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500';
            $itemStock = $stockMap[$item['id']] ?? 0;
            $atMaxStock = $item['qty'] >= $itemStock;
            ?>
            <div class="cart-item">
              <div class="product-cell">
                <div class="item-img-wrap">
                  <img src="<?= $imagePath ?>"
                    onerror="this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500'"
                    alt="<?= htmlspecialchars($item['ten']) ?>" class="item-img">
                </div>
                <div class="item-title"><?= htmlspecialchars($item['ten']) ?></div>
              </div>

              <div class="item-price"><?= number_format($item['gia'], 0, ',', '.') ?>đ</div>

              <div style="text-align:center;">
                <div class="quantity-control">
                  <a href="cart.php?decrease=<?= urlencode($item['id']) ?>" class="btn-qty" aria-label="Giảm số lượng">−</a>
                  <div class="qty-number"><?= $item['qty'] ?></div>
                  <?php if ($atMaxStock): ?>
                    <span class="btn-qty btn-qty-disabled" aria-label="Đã đạt số lượng tối đa của sản phẩm">+</span>
                  <?php else: ?>
                    <a href="cart.php?increase=<?= urlencode($item['id']) ?>" class="btn-qty" aria-label="Tăng số lượng">+</a>
                  <?php endif; ?>
                </div>
                <?php if ($atMaxStock): ?>
                  <div class="item-stock-note">Đã đạt số lượng tối đa của sản phẩm(<?= $itemStock ?>)</div>
                <?php endif; ?>
              </div>

              <div class="item-total-price"><?= number_format($rowTotal, 0, ',', '.') ?>đ</div>

              <div style="text-align:center;">
                <a href="cart.php?remove=<?= urlencode($item['id']) ?>"
                  onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này khỏi giỏ hàng không?');"
                  class="btn-remove" title="Xóa mặt hàng này" aria-label="Xóa sản phẩm">✕</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="cart-summary">
          <div class="total-readout">
            <div class="total-label">Tổng thanh toán</div>
            <div class="total-value"><?= number_format($totalAll, 0, ',', '.') ?>đ</div>
          </div>
        </div>

        <div class="action-group">
          <a href="index.php" class="btn-back"><span class="arrow">←</span> Tiếp tục mua hàng</a>
          <a href="checkout.php" class="btn-checkout">[ Tiến hành đặt hàng ]</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>

</html>