<?php
session_start();
require_once "./db_utils.php";
$db = new DB_UTILS();

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

if (empty($_SESSION['cart'])) {
  header("Location: index.php");
  exit;
}

$totalAll = 0;
foreach ($_SESSION['cart'] as $item) {
  $totalAll += ($item['gia'] * $item['qty']);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fullname = trim($_POST['fullname'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $address = trim($_POST['address'] ?? '');
  $note = trim($_POST['note'] ?? '');
  $payment = $_POST['payment'] ?? 'cod';

  if (empty($fullname) || empty($phone) || empty($email) || empty($address)) {
    $error = 'Vui lòng điền đầy đủ thông tin giao hàng và Email!';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Định dạng Email không hợp lệ!';
  } else {
    $order_id = 'DH-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    $created_at = date('Y-m-d H:i:s');
    $user_id = $_SESSION['user']['id'] ?? null;
    $status = in_array($payment, ['vnpay', 'momo']) ? 'Chờ thanh toán' : 'Đã đặt';

    $db->connection->beginTransaction();
    try {
      $db->execute(
        "INSERT INTO orders (order_id, user_id, fullname, phone, email, address, note, payment, total, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
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
        header("Location: vnpay_create_payment.php?order_id=" . urlencode($order_id) . "&amount=" . $totalAll);
        exit;
      } elseif ($payment === 'momo') {
        header("Location: momo_create_payment.php?order_id=" . urlencode($order_id) . "&amount=" . $totalAll);
        exit;
      } else {
        echo "<script>alert('Đặt hàng thành công! Mã đơn hàng: $order_id'); window.location.href='DonHang.php';</script>";
        exit;
      }
    } catch (Exception $e) {
      $db->connection->rollBack();
      $error = 'Lỗi hệ thống: ' . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Thanh Toán Đơn Hàng</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Arial, sans-serif;
    }

    body {
      background: linear-gradient(135deg, #f0f4ff 0%, #faf5ff 100%);
      min-height: 100vh;
      padding: 30px 20px;
    }

    .page-title {
      text-align: center;
      font-size: 26px;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 28px;
      letter-spacing: -0.5px;
    }

    .page-title span {
      color: #6366f1;
    }

    .layout {
      max-width: 960px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr 380px;
      gap: 24px;
      align-items: start;
    }

    /* ── Cards ── */
    .card {
      background: white;
      border-radius: 16px;
      padding: 28px;
      box-shadow: 0 2px 16px rgba(0, 0, 0, 0.06);
    }

    .card-title {
      font-size: 15px;
      font-weight: 700;
      color: #475569;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .card-title::after {
      content: '';
      flex: 1;
      height: 1px;
      background: #e2e8f0;
    }

    /* ── Form fields ── */
    .form-group {
      margin-bottom: 16px;
    }

    .form-group label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: #64748b;
      margin-bottom: 6px;
    }

    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 11px 14px;
      border: 1.5px solid #e2e8f0;
      border-radius: 10px;
      font-size: 14px;
      color: #1e293b;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
      background: #fafafa;
    }

    .form-group input:focus,
    .form-group textarea:focus {
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
      background: white;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    /* ── Payment cards ── */
    .payment-options {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-top: 4px;
    }

    .payment-card {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.2s;
      position: relative;
    }

    .payment-card:hover {
      border-color: #a5b4fc;
      background: #f8f7ff;
    }

    .payment-card.selected {
      border-color: #6366f1;
      background: #f0f0ff;
    }

    .payment-card input[type="radio"] {
      display: none;
    }

    .payment-logo {
      width: 52px;
      height: 52px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      flex-shrink: 0;
      overflow: hidden;
      padding: 4px;
    }

    .logo-cod {
      background: #f0fdf4;
    }

    .logo-vnpay {
      background: #eff6ff;
    }

    .logo-momo {
      background: #fdf2f8;
    }

    .payment-info {
      flex: 1;
    }

    .payment-name {
      font-weight: 700;
      font-size: 14px;
      color: #1e293b;
    }

    .payment-desc {
      font-size: 12px;
      color: #94a3b8;
      margin-top: 2px;
    }

    .payment-check {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      border: 2px solid #cbd5e1;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      flex-shrink: 0;
    }

    .payment-card.selected .payment-check {
      border-color: #6366f1;
      background: #6366f1;
    }

    .payment-card.selected .payment-check::after {
      content: '';
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: white;
    }

    /* ── Order summary ── */
    .summary-items {
      margin-bottom: 16px;
    }

    .summary-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 0;
      border-bottom: 1px dashed #f1f5f9;
      font-size: 14px;
    }

    .summary-item:last-child {
      border-bottom: none;
    }

    .item-name {
      color: #475569;
      flex: 1;
    }

    .item-qty {
      color: #94a3b8;
      font-size: 12px;
      margin: 0 12px;
    }

    .item-price {
      font-weight: 600;
      color: #1e293b;
      white-space: nowrap;
    }

    .summary-total {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 0 0;
      border-top: 2px solid #e2e8f0;
      margin-top: 8px;
    }

    .total-label {
      font-weight: 700;
      color: #475569;
      font-size: 14px;
    }

    .total-price {
      font-size: 22px;
      font-weight: 800;
      color: #ef4444;
    }

    /* ── Submit button ── */
    .btn-submit {
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, #6366f1, #8b5cf6);
      color: white;
      border: none;
      font-weight: 700;
      border-radius: 12px;
      cursor: pointer;
      font-size: 16px;
      margin-top: 20px;
      transition: all 0.2s;
      letter-spacing: 0.3px;
      box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
    }

    .btn-submit:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45);
    }

    .btn-submit:active {
      transform: translateY(0);
    }

    .btn-back {
      display: block;
      text-align: center;
      margin-top: 14px;
      color: #94a3b8;
      text-decoration: none;
      font-size: 13px;
      transition: color 0.2s;
    }

    .btn-back:hover {
      color: #6366f1;
    }

    /* ── Alert ── */
    .alert {
      background: #fef2f2;
      color: #dc2626;
      padding: 12px 16px;
      border-radius: 10px;
      margin-bottom: 20px;
      font-size: 14px;
      border: 1px solid #fecaca;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    @media (max-width: 720px) {
      .layout {
        grid-template-columns: 1fr;
      }

      .form-row {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>

  <p class="page-title">🛒 Xác Nhận <span>Đặt Hàng</span></p>

  <form method="POST">
    <div class="layout">

      <!-- ── CỘT TRÁI: Thông tin giao hàng + Thanh toán ── -->
      <div>
        <!-- Thông tin giao hàng -->
        <div class="card" style="margin-bottom: 20px;">
          <div class="card-title">📦 Thông tin giao hàng</div>

          <?php if ($error): ?>
            <div class="alert">⚠️ <?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <div class="form-group">
            <label>Họ và tên người nhận</label>
            <input type="text" name="fullname" value="<?= htmlspecialchars($_SESSION['user']['full_name'] ?? '') ?>"
              placeholder="Nguyễn Văn A" required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Số điện thoại</label>
              <input type="text" name="phone" placeholder="09xxxxxxxx" required>
            </div>
            <div class="form-group">
              <label>Email nhận thông báo</label>
              <input type="email" name="email" value="<?= htmlspecialchars($_SESSION['user']['email'] ?? '') ?>"
                placeholder="example@gmail.com" required>
            </div>
          </div>

          <div class="form-group">
            <label>Địa chỉ giao hàng</label>
            <input type="text" name="address" placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành..."
              required>
          </div>

          <div class="form-group">
            <label>Ghi chú cho shipper <span style="color:#cbd5e1; font-weight:400">(tuỳ chọn)</span></label>
            <textarea name="note" rows="2" placeholder="VD: Gọi trước 30 phút, giao giờ hành chính..."></textarea>
          </div>
        </div>

        <!-- Phương thức thanh toán -->
        <div class="card">
          <div class="card-title">💳 Phương thức thanh toán</div>
          <div class="payment-options">

            <!-- COD -->
            <label class="payment-card selected" id="card-cod">
              <input type="radio" name="payment" value="cod" checked>
              <div class="payment-logo logo-cod" style="font-size:28px;">💵</div>
              <div class="payment-info">
                <div class="payment-name">Tiền mặt khi nhận hàng (COD)</div>
                <div class="payment-desc">Thanh toán khi shipper giao hàng đến tay bạn</div>
              </div>
              <div class="payment-check"></div>
            </label>

            <!-- VNPay -->
            <label class="payment-card" id="card-vnpay">
              <input type="radio" name="payment" value="vnpay">
              <div class="payment-logo logo-vnpay">
                <img
                  src="https://www.bing.com/th/id/OIP.pn3RUm1xk1HiAxWIgC6CIwHaHa?w=193&h=193&c=8&rs=1&qlt=90&o=6&dpr=1.3&pid=3.1&rm=2"
                  alt="VNPay" style="width:42px; height:42px; object-fit:contain; border-radius:8px;">
              </div>
              <div class="payment-info">
                <div class="payment-name">VNPay</div>
                <div class="payment-desc">Thanh toán qua cổng VNPay — ATM, Visa, QR Code</div>
              </div>
              <div class="payment-check"></div>
            </label>

            <!-- MoMo -->
            <label class="payment-card" id="card-momo">
              <input type="radio" name="payment" value="momo">
              <div class="payment-logo logo-momo">
                <img
                  src="https://www.bing.com/th/id/OIP.zCOk6lgPI0ku_feP568Q5AHaHa?w=193&h=193&c=8&rs=1&qlt=90&o=6&dpr=1.3&pid=3.1&rm=2"
                  alt="MoMo" style="width:42px; height:42px; object-fit:contain; border-radius:8px;">
              </div>
              <div class="payment-info">
                <div class="payment-name">Ví MoMo</div>
                <div class="payment-desc">Thanh toán nhanh qua ví điện tử MoMo</div>
              </div>
              <div class="payment-check"></div>
            </label>

          </div>
        </div>
      </div>

      <!-- ── CỘT PHẢI: Tóm tắt đơn hàng ── -->
      <div class="card">
        <div class="card-title">🧾 Đơn hàng của bạn</div>

        <div class="summary-items">
          <?php foreach ($_SESSION['cart'] as $item): ?>
            <div class="summary-item">
              <span class="item-name"><?= htmlspecialchars($item['ten']) ?></span>
              <span class="item-qty">×<?= $item['qty'] ?></span>
              <span class="item-price"><?= number_format($item['gia'] * $item['qty'], 0, ',', '.') ?>đ</span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="summary-total">
          <span class="total-label">Tổng thanh toán</span>
          <span class="total-price"><?= number_format($totalAll, 0, ',', '.') ?>đ</span>
        </div>

        <button type="submit" class="btn-submit">✅ Xác Nhận Đặt Hàng</button>
        <a href="cart.php" class="btn-back">← Quay lại giỏ hàng</a>
      </div>

    </div>
  </form>

  <script>
    // Highlight payment card khi chọn
    document.querySelectorAll('.payment-card').forEach(card => {
      card.addEventListener('click', () => {
        document.querySelectorAll('.payment-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
      });
    });
  </script>

</body>

</html>