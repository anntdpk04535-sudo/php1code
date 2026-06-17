<?php
session_start();
require_once "./db_utils.php";
$db = new DB_UTILS();

// Kiểm tra xem đây có phải là một yêu cầu gửi ngầm từ Ajax không
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == 1;

// Khóa bảo mật: Nếu chưa đăng nhập
if (!isset($_SESSION['user'])) {
  if ($is_ajax) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập để mua hàng!']);
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

  if ($prod) {
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

  // Tính lại tổng số lượng mặt hàng trong giỏ để cập nhật Badge thời gian thực
  $cart_count = 0;
  foreach ($_SESSION['cart'] as $item) {
    $cart_count += $item['qty'];
  }

  // Nếu là Ajax (Nút Thêm giỏ), trả về kết quả JSON và kết thúc ngay tại đây (Không load lại trang)
  if ($is_ajax) {
    echo json_encode([
      'status' => 'success',
      'cart_count' => $cart_count
    ]);
    exit;
  }

  // Nếu là nút "Mua ngay" truyền thống, chuyển hướng thẳng vào trang giỏ hàng
  header("Location: cart.php");
  exit;
}

// 2. XỬ LÝ TĂNG SỐ LƯỢNG TRÊN TRANG GIỎ HÀNG
if (isset($_GET['increase'])) {
  $product_id = $_GET['increase'];
  if (isset($_SESSION['cart'][$product_id])) {
    $_SESSION['cart'][$product_id]['qty']++;
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
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Giỏ Hàng - TechShop</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Arial, sans-serif;
    }

    body {
      background: #f8fafc;
      color: #334155;
      padding: 40px 20px;
    }

    .cart-container {
      max-width: 1000px;
      margin: 0 auto;
      background: white;
      padding: 35px;
      border-radius: 16px;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
      border: 1px solid #e2e8f0;
    }

    .cart-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      border-bottom: 2px solid #f1f5f9;
      padding-bottom: 15px;
    }

    .cart-header h2 {
      font-size: 24px;
      color: #1e293b;
      font-weight: 700;
    }

    .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      color: #2563eb;
      font-weight: 600;
      font-size: 14px;
      transition: color 0.2s;
    }

    .btn-back:hover {
      color: #1d4ed8;
      text-decoration: underline;
    }

    /* Giao diện Bảng sản phẩm mới */
    .cart-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      margin-bottom: 30px;
    }

    .cart-table th {
      background: #f8fafc;
      color: #64748b;
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
      padding: 16px;
      border-bottom: 2px solid #e2e8f0;
    }

    .cart-table td {
      padding: 20px 16px;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: middle;
    }

    .product-cell {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .item-img {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 10px;
      background: #f1f5f9;
      border: 1px solid #e2e8f0;
      transition: transform 0.2s;
    }

    .item-img:hover {
      transform: scale(1.05);
    }

    .item-title {
      font-weight: 600;
      color: #1e293b;
      font-size: 15px;
      line-height: 1.4;
      max-width: 320px;
    }

    .item-price {
      color: #475569;
      font-weight: 500;
      font-size: 15px;
    }

    .item-total-price {
      color: #dc2626;
      font-weight: 700;
      font-size: 16px;
    }

    /* Bộ tăng giảm số lượng tinh tế hơn */
    .quantity-control {
      display: inline-flex;
      align-items: center;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      overflow: hidden;
      background: #fff;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .btn-qty {
      display: block;
      width: 36px;
      height: 36px;
      line-height: 34px;
      text-align: center;
      text-decoration: none;
      color: #475569;
      font-weight: 600;
      font-size: 16px;
      background: #f8fafc;
      transition: all 0.2s;
    }

    .btn-qty:hover {
      background: #e2e8f0;
      color: #1e293b;
    }

    .qty-number {
      width: 45px;
      text-align: center;
      font-weight: 700;
      font-size: 14px;
      color: #1e293b;
    }

    /* Nút xóa sản phẩm */
    .btn-remove {
      text-decoration: none;
      color: #94a3b8;
      font-size: 14px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 6px 12px;
      border-radius: 6px;
      border: 1px solid #e2e8f0;
      transition: all 0.2s;
      background: #fff;
    }

    .btn-remove:hover {
      color: #ef4444;
      border-color: #fca5a5;
      background: #fef2f2;
    }

    /* Khu vực tổng tiền & Thanh toán */
    .cart-summary {
      background: #f8fafc;
      padding: 25px;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
      margin-top: 20px;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
    }

    .total-box {
      font-size: 16px;
      font-weight: 500;
      color: #475569;
      margin-bottom: 20px;
    }

    .total-box span {
      color: #dc2626;
      font-size: 26px;
      font-weight: 800;
      margin-left: 10px;
    }

    .action-group {
      display: flex;
      width: 100%;
      justify-content: space-between;
      align-items: center;
    }

    .btn-checkout {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #10b981;
      color: white;
      padding: 14px 35px;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 700;
      font-size: 16px;
      transition: all 0.2s;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
    }

    .btn-checkout:hover {
      background: #059669;
      transform: translateY(-1px);
      box-shadow: 0 6px 15px rgba(16, 185, 129, 0.3);
    }

    .btn-checkout:active {
      transform: translateY(0);
    }

    .empty-cart {
      text-align: center;
      padding: 60px 0;
    }

    .empty-icon {
      font-size: 64px;
      margin-bottom: 15px;
      display: block;
    }

    .empty-text {
      color: #94a3b8;
      font-size: 16px;
      font-weight: 500;
      margin-bottom: 25px;
    }

    .btn-shop-now {
      display: inline-block;
      background: #2563eb;
      color: white;
      padding: 12px 28px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      transition: background 0.2s;
    }

    .btn-shop-now:hover {
      background: #1d4ed8;
    }
  </style>
</head>

<body>
  <div class="cart-container">

    <?php if (empty($_SESSION['cart'])): ?>
      <div class="empty-cart">
        <span class="empty-icon">🛒</span>
        <p class="empty-text">Giỏ hàng của bạn đang trống rỗng.</p>
        <a href="index.php" class="btn-shop-now">Mua sắm ngay bây giờ</a>
      </div>
    <?php else: ?>
      <div class="cart-header">
        <h2>Giỏ hàng của bạn</h2>
        <a href="index.php" class="btn-back">← Tiếp tục xem sản phẩm</a>
      </div>

      <table class="cart-table">
        <thead>
          <tr>
            <th>Sản phẩm</th>
            <th>Giá bán</th>
            <th style="text-align: center;">Số lượng</th>
            <th>Tạm tính</th>
            <th style="text-align: center;">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $totalAll = 0;
          foreach ($_SESSION['cart'] as $item):
            $rowTotal = $item['gia'] * $item['qty'];
            $totalAll += $rowTotal;
            $imagePath = !empty($item['hinh_anh']) ? htmlspecialchars($item['hinh_anh']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500';
            ?>
            <tr>
              <td>
                <div class="product-cell">
                  <img src="<?= $imagePath ?>"
                    onerror="this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500'"
                    alt="<?= htmlspecialchars($item['ten']) ?>" class="item-img">
                  <div class="item-title"><?= htmlspecialchars($item['ten']) ?></div>
                </div>
              </td>

              <td>
                <div class="item-price"><?= number_format($item['gia'], 0, ',', '.') ?>đ</div>
              </td>

              <td style="text-align: center;">
                <div class="quantity-control">
                  <a href="cart.php?decrease=<?= urlencode($item['id']) ?>" class="btn-qty">−</a>
                  <div class="qty-number"><?= $item['qty'] ?></div>
                  <a href="cart.php?increase=<?= urlencode($item['id']) ?>" class="btn-qty">+</a>
                </div>
              </td>

              <td>
                <div class="item-total-price"><?= number_format($rowTotal, 0, ',', '.') ?>đ</div>
              </td>

              <td style="text-align: center;">
                <!-- ĐÃ SỬA CHUẨN -->
<a href="cart.php?remove=<?= urlencode($item['id']) ?>" 
onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này khỏi giỏ hàng không?');" 
class="btn-remove" title="Xóa mặt hàng này">
                  <span>🗑️</span> Xóa
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="cart-summary">
        <div class="total-box">Tổng tiền thanh toán: <span><?= number_format($totalAll, 0, ',', '.') ?>đ</span></div>
        <div class="action-group">
          <a href="index.php" class="btn-back">← Tiếp tục mua hàng</a>
          <a href="checkout.php" class="btn-checkout">Tiến hành đặt hàng 🚀</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>

</html>