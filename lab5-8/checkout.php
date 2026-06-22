<?php
session_start();
require_once "./db_utils.php";
$db = new DB_UTILS();

if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
if (empty($_SESSION['cart'])) { header("Location: index.php"); exit; }

$totalAll = 0;
foreach ($_SESSION['cart'] as $item) {
  $totalAll += ($item['gia'] * $item['qty']);
}
$error = '';

$cart_count = 0;
foreach ($_SESSION['cart'] as $item) {
  $cart_count += $item['qty'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fullname = trim($_POST['fullname'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $address = trim($_POST['address'] ?? '');
  $note = trim($_POST['note'] ?? '');
  $payment = $_POST['payment'] ?? 'cod';

  if (empty($fullname) || empty($phone) || empty($email) || empty($address)) {
    $error = 'Vui lòng điền đầy đủ thông tin giao hàng!';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Định dạng Email không hợp lệ!';
  } else {
    // KIỂM TRA LẠI TỒN KHO TRƯỚC KHI ĐẶT HÀNG (phòng trường hợp tồn kho đã thay đổi
    // kể từ lúc khách thêm vào giỏ — ví dụ người khác vừa mua hết hàng)
    foreach ($_SESSION['cart'] as $item) {
      $prod = $db->getOne("SELECT description, stock FROM products WHERE product_id = ?", [$item['id']]);
      if (!$prod) {
        $error = "Sản phẩm \"{$item['ten']}\" không còn tồn tại trong hệ thống. Vui lòng cập nhật lại giỏ hàng!";
        break;
      }
      $currentStock = (int) $prod['stock'];
      if ($item['qty'] > $currentStock) {
        $error = "Rất tiếc, sản phẩm \"{$prod['description']}\" chỉ còn $currentStock sản phẩm trong kho. Vui lòng cập nhật lại giỏ hàng!";
        break;
      }
    }
  }

  if (empty($error)) {
    $order_id = 'DH-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    $created_at = date('Y-m-d H:i:s');
    $user_id = $_SESSION['user']['id'] ?? null;
    $status = in_array($payment, ['vnpay', 'momo']) ? 'Chờ thanh toán' : 'Đã đặt';

    $db->connection->beginTransaction();
    try {
      $db->execute(
        "INSERT INTO orders (order_id, user_id, fullname, phone, email, address, note, payment, total, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$order_id, $user_id, $fullname, $phone, $email, $address, $note, $payment, $totalAll, $status, $created_at]
      );
      foreach ($_SESSION['cart'] as $item) {
        $db->execute(
          "INSERT INTO order_items (order_id, product_id, quantity, price, description) VALUES (?, ?, ?, ?, ?)",
          [$order_id, $item['id'], $item['qty'], $item['gia'], $item['ten']]
        );
      }
      $db->connection->commit();
      $_SESSION['cart'] = [];

      if ($payment === 'vnpay') {
        header("Location: vnpay_create_payment.php?order_id=" . urlencode($order_id) . "&amount=" . $totalAll); exit;
      } elseif ($payment === 'momo') {
        header("Location: momo_create_payment.php?order_id=" . urlencode($order_id) . "&amount=" . $totalAll); exit;
      } else {
        echo "<script>alert('Đặt hàng thành công! Mã đơn hàng: $order_id'); window.location.href='DonHang.php';</script>"; exit;
      }
    } catch (Exception $e) {
      $db->connection->rollBack(); $error = 'Lỗi hệ thống: ' . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Xác Nhận Đơn Hàng — TechShop</title>
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
    body { font-family: var(--font-body); background: var(--bg); color: var(--text); position: relative; }

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

    /* ===== PAGE BODY ===== */
    .page-wrap { position: relative; z-index: 1; padding: 48px 20px 64px; }

    .breadcrumb {
      display: flex;
      align-items: center;
      gap: 8px;
      font-family: var(--font-mono);
      font-size: 12.5px;
      color: var(--text-faint);
      max-width: 1000px;
      margin: 0 auto 22px;
    }
    .breadcrumb a { color: var(--text-dim); text-decoration: none; }
    .breadcrumb a:hover { color: var(--accent); }

    .page-title {
      font-family: var(--font-display);
      font-size: 26px;
      font-weight: 700;
      color: var(--text);
      max-width: 1000px;
      margin: 0 auto 28px;
      padding-left: 14px;
      border-left: 2px solid var(--accent);
    }

    .layout-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 32px; max-width: 1000px; margin: 0 auto; }
    .card { background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 32px; }
    .card-title {
      font-family: var(--font-display);
      font-size: 17px;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 24px;
      border-bottom: 1px solid var(--border-soft);
      padding-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .card-title i { color: var(--accent); }

    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 500; color: var(--text-dim); }
    .form-control { width: 100%; padding: 12px 16px; background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius-sm); color: var(--text); font-size: 14px; outline: none; font-family: var(--font-body); transition: border-color 0.2s ease; }
    .form-control:focus { border-color: var(--accent-border); }
    .form-control::placeholder { color: var(--text-faint); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    .payment-card { display: flex; align-items: center; gap: 14px; padding: 14px; border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--surface-2); cursor: pointer; margin-bottom: 12px; transition: all 0.2s ease; }
    .payment-card:hover { border-color: var(--accent-border); }
    .payment-card.selected { border-color: var(--accent); background: var(--accent-dim); box-shadow: 0 0 14px var(--accent-glow); }
    .payment-card input { display: none; }
    .payment-name { font-size: 14px; font-weight: 600; color: var(--text); }
    .payment-desc { font-size: 12px; color: var(--text-dim); margin-top: 2px; }

    .summary-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border-soft); font-size: 14px; }
    .total-readout { background: var(--bg-soft); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; text-align: right; margin-top: 20px; }
    .total-label { font-family: var(--font-mono); font-size: 11px; color: var(--text-faint); letter-spacing: 0.1em; }
    .total-value { font-family: var(--font-mono); font-size: 26px; font-weight: 700; color: var(--gold-strong); text-shadow: 0 0 18px rgba(216, 184, 122, 0.2); }

    .btn-submit {
      position: relative;
      width: 100%;
      font-family: var(--font-mono);
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.02em;
      background: var(--accent);
      color: var(--bg);
      padding: 15px;
      border: none;
      border-radius: var(--radius-sm);
      font-size: 14px;
      cursor: pointer;
      box-shadow: 0 14px 28px -10px var(--accent-glow);
      margin-top: 20px;
      overflow: hidden;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
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

    .btn-back { display: block; text-align: center; color: var(--text-faint); text-decoration: none; margin-top: 16px; font-size: 13px; transition: color 0.2s ease; }
    .btn-back:hover { color: var(--text-dim); }

    .alert {
      background: var(--danger-dim);
      border: 1px solid var(--danger);
      color: var(--danger);
      padding: 12px 14px;
      border-radius: var(--radius-sm);
      margin-bottom: 16px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    @media (max-width: 768px) {
      .layout-grid { grid-template-columns: 1fr; }
      .form-row { grid-template-columns: 1fr; }
      header { padding: 14px 18px; }
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

  <div class="page-wrap">
    <div class="breadcrumb">
      <a href="index.php">Trang chủ</a> <span>/</span> <a href="cart.php">Giỏ hàng</a> <span>/</span> <span style="color: var(--text-dim);">Xác nhận đơn hàng</span>
    </div>
    <h1 class="page-title">Xác nhận đơn hàng</h1>

    <form method="POST">
      <div class="layout-grid">
        <div>
          <div class="card" style="margin-bottom: 24px;">
            <div class="card-title"><i class="fa-solid fa-truck-fast"></i> Thông tin giao hàng</div>
            <?php if ($error): ?><div class="alert"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div class="form-group">
              <label>Họ và tên người nhận</label>
              <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($_SESSION['user']['full_name'] ?? '') ?>" required>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Số điện thoại</label>
                <input type="text" name="phone" class="form-control" placeholder="09xxxxxxxx" required>
              </div>
              <div class="form-group">
                <label>Email nhận hóa đơn</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_SESSION['user']['email'] ?? '') ?>" required>
              </div>
            </div>
            <div class="form-group">
              <label>Địa chỉ giao hàng</label>
              <input type="text" name="address" class="form-control" placeholder="Số nhà, tên đường, tỉnh thành..." required>
            </div>
            <div class="form-group">
              <label>Ghi chú thêm</label>
              <textarea name="note" class="form-control" rows="2" placeholder="Ghi chú thêm cho đơn hàng..."></textarea>
            </div>
          </div>

          <div class="card">
            <div class="card-title"><i class="fa-solid fa-credit-card"></i> Phương thức thanh toán</div>
            <label class="payment-card selected">
              <input type="radio" name="payment" value="cod" checked>
              <div>
                <div class="payment-name">Tiền mặt khi nhận hàng (COD)</div>
                <div class="payment-desc">Thanh toán trực tiếp khi shipper giao hàng</div>
              </div>
            </label>
            <label class="payment-card">
              <input type="radio" name="payment" value="vnpay">
              <div>
                <div class="payment-name">Cổng VNPay</div>
                <div class="payment-desc">ATM, Visa hoặc mã QR thanh toán bảo mật</div>
              </div>
            </label>
            <label class="payment-card">
              <input type="radio" name="payment" value="momo">
              <div>
                <div class="payment-name">Ví điện tử MoMo</div>
                <div class="payment-desc">Thanh toán nhanh qua liên kết MoMo</div>
              </div>
            </label>
          </div>
        </div>

        <div>
          <div class="card" style="position: sticky; top: 100px;">
            <div class="card-title"><i class="fa-solid fa-receipt"></i> Tóm tắt đơn hàng</div>
            <?php foreach ($_SESSION['cart'] as $item): ?>
              <div class="summary-item">
                <span style="color: var(--text);"><?= htmlspecialchars($item['ten']) ?> <span style="color: var(--accent);">×<?= $item['qty'] ?></span></span>
                <span style="font-family: var(--font-mono); color: var(--text-dim);"><?= number_format($item['gia'] * $item['qty'], 0, ',', '.') ?>đ</span>
              </div>
            <?php endforeach; ?>
            <div class="total-readout">
              <div class="total-label">TỔNG GIÁ TRỊ</div>
              <div class="total-value"><?= number_format($totalAll, 0, ',', '.') ?>đ</div>
            </div>
            <button type="submit" class="btn-submit"><i class="fa-solid fa-bolt"></i> Đặt hàng ngay</button>
            <a href="cart.php" class="btn-back">← Chỉnh sửa giỏ hàng</a>
          </div>
        </div>
      </div>
    </form>
  </div>

  <script>
    document.querySelectorAll('.payment-card').forEach(card => {
      card.addEventListener('click', () => {
        document.querySelectorAll('.payment-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        card.querySelector('input').checked = true;
      });
    });
  </script>
</body>
</html>