<?php
session_start();
require_once "./db_utils.php";
$db = new DB_UTILS();

// 1. LẤY MÃ SẢN PHẨM TỪ THANH ĐỊA CHỈ (URL)
$product_id = $_GET['id'] ?? '';

// 2. TRUY VẤN THÔNG TIN CHI TIẾT CỦA SẢN PHẨM ĐÓ
$product = $db->getOne("SELECT * FROM products WHERE product_id = ?", [$product_id]);

// Nếu không tìm thấy sản phẩm, chuyển hướng về trang chủ
if (!$product) {
    header("Location: index.php");
    exit;
}

// Làm sạch giá tiền để định dạng hiển thị cho chuẩn
$cleanPrice = (int) preg_replace('/[^\d]/', '', $product['price']);

// Tính tổng số lượng hàng trong giỏ để hiển thị trên Header
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
    <title>
        <?= htmlspecialchars($product['tenSP'] ?? $product['description'] ?? 'Chi tiết sản phẩm') ?> - TechShop
    </title>
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
        }

        /* HEADER ĐỒNG BỘ TRANG CHỦ */
        header {
            background: #fff;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .brand {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-links a {
            text-decoration: none;
            color: #475569;
            font-weight: 600;
            font-size: 15px;
        }

        .nav-links a:hover {
            color: #2563eb;
        }

        .btn-cart-nav {
            background: #eff6ff;
            color: #2563eb;
            padding: 8px 16px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .badge {
            background: #dc2626;
            color: white;
            padding: 2px 6px;
            border-radius: 50%;
            font-size: 12px;
        }

        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .btn-back-home {
            display: inline-block;
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
            font-size: 15px;
        }

        /* LAYOUT CHI TIẾT SẢN PHẨM */
        .product-detail-wrapper {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            display: flex;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .detail-img-box {
            flex: 1;
            background: #f1f5f9;
            max-width: 500px;
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .detail-img-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .detail-info-box {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .detail-id {
            font-size: 13px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .detail-title {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 15px;
            line-height: 1.3;
        }

        .detail-price {
            font-size: 26px;
            font-weight: 800;
            color: #dc2626;
            margin-bottom: 25px;
        }

        /* KHỐI THÔNG SỐ / MÔ TẢ PHỤ */
        .detail-desc {
            font-size: 15px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 30px;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
        }

        .policy-list {
            margin-bottom: 30px;
            list-style: none;
            font-size: 14px;
            color: #64748b;
        }

        .policy-list li {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action-buy {
            display: inline-block;
            text-align: center;
            background: #2563eb;
            color: #fff;
            padding: 15px 40px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            transition: background 0.2s;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
        }

        .btn-action-buy:hover {
            background: #1d4ed8;
        }
    </style>
</head>

<body>
    <header>
        <a href="index.php" class="brand">🛒 TechShop</a>
        <div class="nav-links">
            <a href="index.php">Trang chủ</a>
            <a href="DonHang.php">📦 Đơn hàng của tôi</a>
            <a href="send_report.php" style="color: #f59e0b; font-weight: bold;">
                <i class="fas fa-exclamation-circle"></i> Khiếu nại</a>

            <?php if (isset($_SESSION['user'])): ?>
                <a href="cart.php" class="btn-cart-nav">Giỏ hàng <span class="badge">
                        <?= $cart_count ?>
                    </span></a>
                <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                    <a href="admin/orders.php" style="color: #10b981; font-weight: bold;">🛠️ Trang Admin</a>
                <?php endif; ?>
                <a href="logout.php" style="color:#dc2626;">Đăng xuất (
                    <?= htmlspecialchars($_SESSION['user']['full_name']) ?>)
                </a>
            <?php else: ?>
                <a href="login.php" style="color: #2563eb;">Đăng nhập</a>
                <a href="register.php">Đăng ký</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <a href="index.php" class="btn-back-home">← Quay lại danh sách sản phẩm</a>

        <div class="product-detail-wrapper">
            <div class="detail-img-box">
                <img src="<?= htmlspecialchars($product['image'] ?? '') ?>"
                    onerror="this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600'">
            </div>

            <div class="detail-info-box">
                <div class="detail-id">Mã sản phẩm:
                    <?= htmlspecialchars($product['product_id']) ?>
                </div>
                <h1 class="detail-title">
                    <?= htmlspecialchars($product['tenSP'] ?? $product['description'] ?? 'Sản phẩm') ?>
                </h1>
                <div class="detail-price">
                    <?= number_format($cleanPrice, 0, ',', '.') ?>đ
                </div>

                <div class="detail-desc">
                    <strong>Mô tả sản phẩm:</strong><br>
                    Sản phẩm
                    <?= htmlspecialchars($product['tenSP'] ?? $product['description'] ?? 'thiết bị') ?> chính hãng chất
                    lượng cao, đầy đủ phụ kiện đi kèm từ nhà sản xuất. Cam kết hiệu năng ổn định, đáp ứng hoàn hảo nhu
                    cầu sử dụng của bạn.
                </div>

                <ul class="policy-list">
                    <li>🛡️ Bảo hành chính hãng 12 tháng đổi mới</li>
                    <li>🚚 Miễn phí vận chuyển toàn quốc cho đơn hàng từ 10 triệu</li>
                    <li>💳 Hỗ trợ trả góp 0% lãi suất qua thẻ tín dụng</li>
                </ul>

                <div>
                    <a href="cart.php?add=<?= urlencode($product['product_id']) ?>" class="btn-action-buy">🛒 CHỌN MUA
                        NGAY</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>