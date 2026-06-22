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
$user_email = $_SESSION['user']['email'] ?? '';
$user_fullname = $_SESSION['user']['full_name'] ?? '';

$cart_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['qty'];
    }
}

// =========================================================================
// ── XỬ LÝ CHỨC NĂNG MUA LẠI (THÊM SẢN PHẨM CŨ VÀO GIỎ HÀNG) ────────────────
// =========================================================================
if (isset($_GET['reorder_id'])) {
    $reorder_id = $_GET['reorder_id'];
    
    // Kiểm tra tính hợp pháp của đơn hàng xem có đúng của user này không
    $check_order = $db->getOne(
        "SELECT order_id FROM orders WHERE order_id = ? AND (user_id = ? OR email = ? OR fullname = ?)",
        [$reorder_id, $user_id, $user_email, $user_fullname]
    );

    if ($check_order) {
        // Lấy chi tiết các sản phẩm thuộc đơn hàng cũ này
        $items = $db->getAll("SELECT * FROM order_items WHERE order_id = ?", [$reorder_id]);
        
        if (!empty($items)) {
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            $reorderStockWarnings = [];

            foreach ($items as $item) {
                $p_id = $item['product_id'];
                
                // Lấy thông tin mới nhất của sản phẩm trong bảng products (để lấy ảnh, giá và tồn kho hiện tại)
                $prod = $db->getOne("SELECT * FROM products WHERE product_id = ?", [$p_id]);
                
                if ($prod) {
                    $cleanPrice = (int) preg_replace('/[^\d]/', '', $prod['price']);
                    $hinh = !empty($prod['image']) ? $prod['image'] : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500';
                    $stock = (int) ($prod['stock'] ?? 0);

                    // Số lượng đang có sẵn trong giỏ trước khi mua lại
                    $currentQtyInCart = isset($_SESSION['cart'][$p_id]) ? $_SESSION['cart'][$p_id]['qty'] : 0;
                    // Số lượng muốn thêm vào, nhưng không được vượt quá tồn kho hiện có
                    $desiredQty = $item['quantity'];
                    $maxCanAdd = max(0, $stock - $currentQtyInCart);
                    $qtyToAdd = min($desiredQty, $maxCanAdd);

                    if ($qtyToAdd < $desiredQty) {
                        $reorderStockWarnings[] = $prod['description'] . " (chỉ thêm được $qtyToAdd/$desiredQty do giới hạn tồn kho còn $stock)";
                    }

                    if ($qtyToAdd > 0) {
                        if (isset($_SESSION['cart'][$p_id])) {
                            $_SESSION['cart'][$p_id]['qty'] += $qtyToAdd;
                        } else {
                            $_SESSION['cart'][$p_id] = [
                                'id' => $prod['product_id'],
                                'ten' => $prod['description'] ?? $prod['tenSP'] ?? 'Sản phẩm',
                                'gia' => $cleanPrice,
                                'hinh_anh' => $hinh,
                                'qty' => $qtyToAdd
                            ];
                        }
                    }
                }
            }

            if (!empty($reorderStockWarnings)) {
                $_SESSION['cart_flash_error'] = 'Một số sản phẩm không đủ tồn kho để mua lại đầy đủ: ' . implode('; ', $reorderStockWarnings);
            }

            // Chuyển hướng thẳng tới trang giỏ hàng sau khi nạp xong dữ liệu
            header("Location: cart.php");
            exit;
        } else {
            $error = "Đơn hàng này không có sản phẩm nào để mua lại!";
        }
    } else {
        $error = "Yêu cầu không hợp lệ hoặc đơn hàng không tồn tại!";
    }
}

// ── AJAX LẤY CHI TIẾT ĐƠN HÀNG + TIMELINE DẠNG CỘT dọc (SHOPEE STYLE) ──
if (isset($_GET['ajax_detail'])) {
    $view_id = $_GET['ajax_detail'];
    $detail = $db->getOne(
        "SELECT * FROM orders WHERE order_id = ?
         AND (user_id = ? OR email = ? OR fullname = ?)",
        [$view_id, $user_id, $user_email, $user_fullname]
    );

    if ($detail) {
        $detail_items = $db->getAll("SELECT * FROM order_items WHERE order_id = ?", [$view_id]);
        $st = $detail['status'];
        ?>
        <div class="modal-detail-content" data-order-id="<?= htmlspecialchars($detail['order_id']) ?>">
            <div class="modal-header">
                <h2>Chi tiết đơn hàng #<?= htmlspecialchars($detail['order_id']) ?></h2>
                <button onclick="closeDetailModal()" class="close-btn">✕</button>
            </div>

            <div class="modal-body">
                
                <div class="shopee-timeline">
                    <?php if ($st === 'Đã hủy'): ?>
                        <div class="timeline-item status-cancelled">
                            <div class="timeline-badge"><i class="fas fa-times-circle"></i></div>
                            <div class="timeline-panel">
                                <div class="timeline-title">Đơn hàng đã bị hủy</div>
                                <p class="timeline-desc">Hệ thống đã ghi nhận yêu cầu hủy bỏ giao dịch đơn hàng này.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="timeline-item <?= ($st === 'Hoàn tất') ? 'active' : 'disabled' ?>">
                            <div class="timeline-badge"><i class="fas fa-check-circle"></i></div>
                            <div class="timeline-panel">
                                <div class="timeline-title">Đơn hàng hoàn tất</div>
                                <p class="timeline-desc"><?= ($st === 'Hoàn tất') ? 'Người mua đã nhận hàng và hài lòng với sản phẩm.' : 'Chờ khách hàng nghiệm thu sau khi nhận.' ?></p>
                            </div>
                        </div>

                        <div class="timeline-item <?= in_array($st, ['Đang giao hàng', 'Hoàn tất']) ? 'active' : 'disabled' ?>">
                            <div class="timeline-badge"><i class="fas fa-truck"></i></div>
                            <div class="timeline-panel">
                                <div class="timeline-title">Đang giao hàng</div>
                                <p class="timeline-desc"><?= in_array($st, ['Đang giao hàng', 'Hoàn tất']) ? 'Kiện hàng đang được nhân viên vận chuyển giao tới địa chỉ của bạn.' : 'Đang chuẩn bị đóng gói đóng hộp sản phẩm.' ?></p>
                            </div>
                        </div>

                        <div class="timeline-item active">
                            <div class="timeline-badge"><i class="fas fa-clipboard-list"></i></div>
                            <div class="timeline-panel">
                                <div class="timeline-title">Đặt đơn hàng thành công</div>
                                <p class="timeline-desc">Đơn hàng đã được khởi tạo thành công trên hệ thống và chờ xét duyệt.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <div>
                        <?php if (in_array($st, ['Hoàn tất', 'Đã hủy'])): ?>
                            <a href="DonHang.php?reorder_id=<?= urlencode($detail['order_id']) ?>" class="btn btn-reorder"><i class="fas fa-redo"></i> Mua lại đơn này</a>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <?php if (in_array($st, ['Đã đặt', 'Chờ thanh toán'])): ?>
                            <button onclick="openCancelFromDetail('<?= htmlspecialchars($detail['order_id']) ?>')" class="btn btn-danger">❌ Yêu cầu hủy đơn</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">👤 Người nhận</div><?= htmlspecialchars($detail['fullname']) ?>
                    </div>
                    <div class="info-item">
                        <div class="info-label">📞 Điện thoại</div><?= htmlspecialchars($detail['phone']) ?>
                    </div>
                    <div class="info-item">
                        <div class="info-label">✉️ Email</div><?= htmlspecialchars($detail['email']) ?>
                    </div>
                    <div class="info-item">
                        <div class="info-label">💳 Thanh toán</div><?= paymentLabel($detail['payment']) ?>
                    </div>
                    <div class="info-item" style="grid-column:1/-1">
                        <div class="info-label">📍 Địa chỉ</div><?= htmlspecialchars($detail['address']) ?>
                    </div>
                </div>

                <?php if (!empty($detail['cancel_reason'])): ?>
                    <div class="reason-box">
                        <strong>❌ Lý do hủy:</strong><br><?= nl2br(htmlspecialchars($detail['cancel_reason'])) ?>
                    </div>
                <?php endif; ?>

                <h3 style="margin:24px 0 12px;">🛍 Sản phẩm</h3>
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th style="text-align:center">SL</th>
                            <th style="text-align:right">Đơn giá</th>
                            <th style="text-align:right">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detail_items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['description'] ?? 'Sản phẩm') ?></td>
                                <td style="text-align:center">×<?= $item['quantity'] ?></td>
                                <td style="text-align:right"><?= number_format($item['price'], 0, ',', '.') ?> ₫</td>
                                <td style="text-align:right;font-weight:700;color:var(--gold-strong)">
                                    <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?> ₫
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="total-price">
                    Tổng thanh toán: <strong><?= number_format($detail['total'], 0, ',', '.') ?> ₫</strong>
                </div>
            </div>
        </div>
        <?php
    } else {
        echo "<p style='padding:40px;text-align:center;color:#ff5e72;'>Không tìm thấy đơn hàng!</p>";
    }
    exit;
}

// ── XỬ LÝ HỦY ĐƠN HÀNG ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $orderId = $_POST['order_id'] ?? '';
    $reason = trim($_POST['cancel_reason'] ?? '');

    if (!empty($orderId)) {
        $current = $db->getOne(
            "SELECT status FROM orders WHERE order_id = ? AND (user_id = ? OR email = ? OR fullname = ?)",
            [$orderId, $user_id, $user_email, $user_fullname]
        );
        $cancellable = ['Đã đặt', 'Chờ thanh toán'];

        if (!$current)
            $error = "Không tìm thấy đơn hàng!";
        elseif (!in_array($current['status'], $cancellable))
            $error = "Đơn hàng không thể hủy ở trạng thái hiện tại!";
        elseif (empty($reason))
            $error = "Vui lòng nhập lý do hủy!";
        else {
            $db->execute("UPDATE orders SET status = 'Đã hủy', cancel_reason = ? WHERE order_id = ?", [$reason, $orderId]);
            header("Location: DonHang.php?cancel=success");
            exit;
        }
    }
}

if (isset($_GET['cancel']) && $_GET['cancel'] === 'success') {
    $message = "Hủy đơn hàng thành công!";
}

// ── DANH SÁCH ĐƠN HÀNG ─────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? '';
$filter_payment = $_GET['payment'] ?? '';

$sql = "SELECT * FROM orders WHERE (user_id = ? OR email = ? OR fullname = ?)";
$params = [$user_id, $user_email, $user_fullname];

if (!empty($search)) {
    $sql .= " AND (order_id LIKE ? OR fullname LIKE ? OR phone LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}
if (!empty($filter_status)) {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
}
if (!empty($filter_payment)) {
    $sql .= " AND payment = ?";
    $params[] = $filter_payment;
}
$sql .= " ORDER BY created_at DESC";
$orders = $db->getAll($sql, $params);

function statusClass(string $s): string
{
    return match ($s) {
        'Đã đặt' => 'st-dadat', 'Chờ thanh toán' => 'st-cho', 'Đã thanh toán' => 'st-paid',
        'Đang giao hàng' => 'st-giao', 'Hoàn tất' => 'st-hoantat',
        'Thanh toán thất bại' => 'st-fail', 'Đã hủy' => 'st-huy',
        default => 'st-dadat',
    };
}

function paymentLabel(string $p): string
{
    return match ($p) {
        'vnpay' => '<img src="https://www.bing.com/th/id/OIP.pn3RUm1xk1HiAxWIgC6CIwHaHa?w=193&h=193&c=8&rs=1&qlt=90&o=6&dpr=1.3&pid=3.1&rm=2" style="height:22px;vertical-align:middle;border-radius:4px"> VNPay',
        'momo' => '<img src="https://www.bing.com/th/id/OIP.zCOk6lgPI0ku_feP568Q5AHaHa?w=193&h=193&c=8&rs=1&qlt=90&o=6&dpr=1.3&pid=3.1&rm=2" style="height:22px;vertical-align:middle;border-radius:4px"> MoMo',
        default => '💵 Thanh toán khi nhận hàng',
    };
}

$cancellableStatuses = ['Đã đặt', 'Chờ thanh toán'];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn Hàng Của Tôi — TechShop</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 36px 28px 60px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: var(--font-mono);
            font-size: 12.5px;
            color: var(--text-faint);
            margin-bottom: 18px;
        }
        .breadcrumb a { color: var(--text-dim); text-decoration: none; }
        .breadcrumb a:hover { color: var(--accent); }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 26px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-header h1 {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 700;
            color: var(--text);
            padding-left: 14px;
            border-left: 2px solid var(--accent);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i { color: var(--accent); font-size: 22px; }

        .alert {
            padding: 14px 18px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: var(--accent-dim); color: var(--accent); border: 1px solid var(--accent-border); }
        .alert-danger { background: var(--danger-dim); color: var(--danger); border: 1px solid var(--danger); }

        .filter-bar {
            background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group { flex: 1; min-width: 220px; }
        .filter-group label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-dim); font-size: 13px; }

        input, select, textarea {
            font-family: var(--font-body);
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface-2);
            color: var(--text);
            outline: none;
            transition: border-color 0.2s ease;
        }
        input::placeholder, textarea::placeholder { color: var(--text-faint); }
        input:focus, select:focus, textarea:focus { border-color: var(--accent-border); }
        select option { background: var(--surface-2); color: var(--text); }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            font-size: 13.5px;
            font-family: var(--font-body);
        }
        .btn:hover { transform: translateY(-2px); }

        .btn-primary { background: var(--accent-dim); color: var(--accent); border: 1px solid var(--accent-border); }
        .btn-primary:hover { background: rgba(0, 230, 195, 0.2); }

        .btn-danger { background: var(--danger-dim); color: var(--danger); border: 1px solid var(--danger); }
        .btn-danger:hover { background: rgba(255, 94, 114, 0.22); }

        .btn-reorder { background: var(--warn-dim); color: var(--warn); border: 1px solid var(--warn); }
        .btn-reorder:hover { background: rgba(255, 180, 84, 0.22); }

        .btn-muted { background: var(--surface-2); color: var(--text-dim); border: 1px solid var(--border); }
        .btn-muted:hover { color: var(--text); border-color: var(--border-soft); }

        .btn-report { background: var(--warn-dim); color: var(--warn); border: 1px solid var(--warn); }
        .btn-report:hover { background: rgba(255, 180, 84, 0.22); }

        .card {
            background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-soft);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-header h2 {
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
        }
        .card-header span { color: var(--text-dim); font-size: 13.5px; }
        .card-header strong { color: var(--accent); }

        table { width: 100%; border-collapse: collapse; }
        th { background: var(--bg-soft); padding: 14px 20px; text-align: left; font-weight: 600; color: var(--text-dim); font-size: 13px; font-family: var(--font-mono); letter-spacing: 0.02em; }
        td { padding: 16px 20px; border-bottom: 1px solid var(--border-soft); font-size: 14px; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255, 255, 255, 0.015); }

        .order-id-cell { color: var(--accent); font-family: var(--font-mono); font-weight: 700; }

        .badge { padding: 6px 14px; border-radius: 999px; font-size: 12.5px; font-weight: 600; display: inline-block; }
        .st-dadat { background: var(--warn-dim); color: var(--warn); }
        .st-cho { background: var(--warn-dim); color: var(--warn); }
        .st-paid { background: var(--accent-dim); color: var(--accent); }
        .st-giao { background: rgba(99, 179, 237, 0.14); color: #63b3ed; }
        .st-hoantat { background: var(--accent-dim); color: var(--accent); }
        .st-fail { background: var(--danger-dim); color: var(--danger); }
        .st-huy { background: var(--danger-dim); color: var(--danger); }

        .empty-orders { padding: 80px 20px; text-align: center; color: var(--text-faint); font-family: var(--font-mono); font-size: 13px; }

        /* TIMELINE CỘT DỌC DARK STYLE */
        .shopee-timeline {
            position: relative;
            background: var(--bg-soft);
            border-radius: var(--radius-md);
            padding: 25px;
            border: 1px solid var(--border-soft);
            margin-bottom: 25px;
        }

        .shopee-timeline::before {
            content: '';
            position: absolute;
            top: 40px;
            bottom: 40px;
            left: 43px;
            width: 2px;
            background: var(--border);
            z-index: 1;
        }

        .timeline-item { position: relative; display: flex; margin-bottom: 25px; z-index: 2; }
        .timeline-item:last-child { margin-bottom: 0; }

        .timeline-badge {
            width: 38px; height: 38px; border-radius: 50%; background: var(--surface-2); border: 2px solid var(--border);
            color: var(--text-faint); display: flex; align-items: center; justify-content: center; font-size: 16px; margin-right: 20px; z-index: 3; transition: all 0.3s ease;
        }

        .timeline-panel { flex: 1; padding-top: 6px; }
        .timeline-title { font-size: 15px; font-weight: 600; color: var(--text-dim); margin-bottom: 4px; }
        .timeline-desc { font-size: 13px; color: var(--text-faint); line-height: 1.5; }

        .timeline-item.active .timeline-badge { border-color: var(--accent); color: var(--accent); background: var(--surface-2); box-shadow: 0 0 0 5px var(--accent-dim); }
        .timeline-item.active .timeline-title { color: var(--accent); font-weight: 700; }
        .timeline-item.active .timeline-desc { color: var(--text-dim); }
        .timeline-item.active ~ .timeline-item::before { background: var(--accent); }
        .timeline-item.status-cancelled .timeline-badge { border-color: var(--danger); color: var(--danger); box-shadow: 0 0 0 5px var(--danger-dim); }
        .timeline-item.status-cancelled .timeline-title { color: var(--danger); font-weight: 700; }
        .timeline-item.status-cancelled .timeline-desc { color: var(--text-dim); }
        .timeline-item.disabled { opacity: 0.55; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.7); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal {
            background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            width: 95%;
            max-width: 700px;
            max-height: 90vh;
            overflow: auto;
            box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.6);
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-soft);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--surface-2);
        }
        .modal-header h2 { font-family: var(--font-display); font-size: 17px; font-weight: 600; color: var(--text); }
        .modal-body { padding: 24px; }
        .close-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-dim); transition: color 0.2s ease; }
        .close-btn:hover { color: var(--text); }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .info-item { background: var(--bg-soft); border: 1px solid var(--border-soft); padding: 16px; border-radius: var(--radius-md); font-size: 14px; }
        .info-label { font-size: 12px; font-weight: 600; color: var(--text-faint); margin-bottom: 6px; font-family: var(--font-mono); }
        .reason-box { background: var(--danger-dim); border-left: 3px solid var(--danger); color: var(--text); padding: 16px; border-radius: var(--radius-sm); margin: 20px 0; font-size: 14px; }
        .total-price { text-align: right; font-size: 20px; font-weight: 700; color: var(--gold-strong); margin-top: 20px; font-family: var(--font-mono); }
        .modal-body h3 { font-family: var(--font-display); font-size: 16px; color: var(--text); }

        .detail-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .detail-table th { padding: 10px; font-size: 12.5px; background: var(--bg-soft); }
        .detail-table td { padding: 12px 10px; font-size: 13.5px; border-bottom: 1px solid var(--border-soft); }

        @media (max-width: 768px) {
            header { padding: 14px 18px; }
            .container { padding: 24px 16px 50px; }
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

        <div class="page-header">
            <h1><i class="fas fa-box"></i> Đơn Hàng Của Tôi</h1>
            <div style="display: flex; gap: 10px;">
                <a href="send_report.php" class="btn btn-report">
                    <i class="fas fa-exclamation-triangle"></i> Báo cáo sự cố / Khiếu nại
                </a>
                <a href="index.php" class="btn btn-muted">← Về Trang Chủ</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label>Tìm kiếm</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Mã đơn, tên, SĐT...">
            </div>
            <div class="filter-group">
                <label>Trạng thái</label>
                <select name="status">
                    <option value="">Tất cả</option>
                    <?php foreach (['Đã đặt', 'Chờ thanh toán', 'Đã thanh toán', 'Đang giao hàng', 'Hoàn tất', 'Thanh toán thất bại', 'Đã hủy'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Thanh toán</label>
                <select name="payment">
                    <option value="">Tất cả</option>
                    <option value="cod">💵 COD</option>
                    <option value="vnpay">VNPay</option>
                    <option value="momo">MoMo</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Tìm kiếm</button>
                <a href="DonHang.php" class="btn btn-muted">Xóa lọc</a>
            </div>
        </form>

        <div class="card">
            <div class="card-header">
                <h2><i class="fa-solid fa-list-check"></i> Lịch sử đơn hàng</h2>
                <span>Tìm thấy <strong><?= count($orders) ?></strong> đơn</span>
            </div>

            <?php if (empty($orders)): ?>
                <div class="empty-orders">Chưa có đơn hàng nào</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Ngày đặt</th>
                            <th>Thanh toán</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th style="text-align:center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr id="order_row_<?= htmlspecialchars($o['order_id']) ?>">
                                <td><span class="order-id-cell"><?= htmlspecialchars($o['order_id']) ?></span></td>
                                <td style="color: var(--text-dim);"><?= htmlspecialchars($o['created_at']) ?></td>
                                <td><?= paymentLabel($o['payment']) ?></td>
                                <td><strong style="color: var(--gold-strong); font-family: var(--font-mono);"><?= number_format($o['total'], 0, ',', '.') ?> ₫</strong></td>
                                <td>
                                    <span class="badge <?= statusClass($o['status']) ?> status-text">
                                        <?= htmlspecialchars($o['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align:center">
                                    <div style="display:flex; gap:6px; justify-content:center; align-items:center;">
                                        <button onclick="openDetailModal('<?= htmlspecialchars($o['order_id']) ?>')" class="btn btn-primary"><i class="fa-solid fa-eye"></i> Chi tiết</button>
                                        
                                        <span class="action-btn-container">
                                            <?php if (in_array($o['status'], $cancellableStatuses)): ?>
                                                <button onclick="openCancelModal('<?= htmlspecialchars($o['order_id']) ?>')" class="btn btn-danger">Hủy</button>
                                            <?php elseif (in_array($o['status'], ['Hoàn tất', 'Đã hủy'])): ?>
                                                <a href="DonHang.php?reorder_id=<?= urlencode($o['order_id']) ?>" class="btn btn-reorder"><i class="fas fa-redo"></i> Mua lại</a>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal-overlay" id="detailModal">
        <div class="modal">
            <div id="detailContent">Đang tải...</div>
        </div>
    </div>

    <div class="modal-overlay" id="cancelModal">
        <div class="modal" style="max-width:460px;">
            <div style="padding:32px;">
                <h3 style="font-family: var(--font-display); margin-bottom: 8px;">❌ Xác nhận hủy đơn hàng</h3>
                <p style="color: var(--text-dim); margin-bottom: 16px; font-size: 14px;">Vui lòng cho biết lý do hủy:</p>
                <form method="POST" action="DonHang.php">
                    <input type="hidden" name="action" value="cancel_order">
                    <input type="hidden" name="order_id" id="modal_order_id">
                    <textarea name="cancel_reason" id="modal_reason" rows="5" required placeholder="Lý do..."></textarea>
                    <div style="margin-top:20px;display:flex;gap:12px;justify-content:flex-end;">
                        <button type="button" onclick="closeCancelModal()" class="btn btn-muted">Giữ lại</button>
                        <button type="submit" class="btn btn-danger">Xác nhận hủy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentOpenOrderId = null;

        function openDetailModal(orderId) {
            currentOpenOrderId = orderId;
            document.getElementById('detailModal').classList.add('active');
            loadModalContent(orderId);
        }

        function loadModalContent(orderId) {
            fetch(`DonHang.php?ajax_detail=${encodeURIComponent(orderId)}`)
                .then(res => res.text())
                .then(html => {
                    if(currentOpenOrderId === orderId) {
                        document.getElementById('detailContent').innerHTML = html;
                    }
                })
                .catch(() => {
                    document.getElementById('detailContent').innerHTML = "<p style='color:#ff5e72;padding:40px;text-align:center;'>Lỗi khi tải chi tiết!</p>";
                });
        }

        function closeDetailModal() {
            currentOpenOrderId = null;
            document.getElementById('detailModal').classList.remove('active');
        }

        function openCancelModal(orderId) {
            document.getElementById('modal_order_id').value = orderId;
            document.getElementById('modal_reason').value = '';
            document.getElementById('cancelModal').classList.add('active');
        }

        function openCancelFromDetail(orderId) {
            closeDetailModal();
            setTimeout(() => openCancelModal(orderId), 300);
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').classList.remove('active');
        }

        document.getElementById('detailModal').addEventListener('click', function (e) { if (e.target === this) closeDetailModal(); });
        document.getElementById('cancelModal').addEventListener('click', function (e) { if (e.target === this) closeCancelModal(); });

        // =========================================================================
        // REAL-TIME LONG-POLLING AUTO-REFRESH (TỰ ĐỘNG BIẾN ĐỔI NÚT THEO TRẠNG THÁI)
        // =========================================================================
        function mapClass(status) {
            if (status === 'Đã đặt') return 'st-dadat';
            if (status === 'Đang giao hàng') return 'st-giao';
            if (status === 'Hoàn tất') return 'st-hoantat';
            if (status === 'Đã hủy') return 'st-huy';
            return 'st-dadat';
        }

        setInterval(() => {
            const rows = document.querySelectorAll('tr[id^="order_row_"]');
            rows.forEach(row => {
                const orderId = row.id.replace('order_row_', '');

                fetch(`get_status.php?order_id=${encodeURIComponent(orderId)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.status && data.status !== 'error') {
                            const newStatus = data.status;
                            const badgeSpan = row.querySelector('.status-text');
                            const currentStatusText = badgeSpan.innerText.trim();

                            if (currentStatusText !== newStatus) {
                                // 1. Cập nhật Badge text và màu sắc
                                badgeSpan.innerText = newStatus;
                                badgeSpan.className = `badge ${mapClass(newStatus)} status-text`;

                                // 2. Đồng bộ Real-time đổi nút "Hủy" thành nút "Mua lại" khi đơn đóng thành công
                                const actionContainer = row.querySelector('.action-btn-container');
                                if (['Hoàn tất', 'Đã hủy'].includes(newStatus)) {
                                    actionContainer.innerHTML = `<a href="DonHang.php?reorder_id=${encodeURIComponent(orderId)}" class="btn btn-reorder"><i class="fas fa-redo"></i> Mua lại</a>`;
                                } else {
                                    actionContainer.innerHTML = ''; // Ẩn nút hủy khi đang giao hàng
                                }

                                // 3. Tải lại modal chi tiết nếu đang mở xem đơn hàng này
                                if (currentOpenOrderId === orderId) {
                                    loadModalContent(orderId);
                                }
                            }
                        }
                    })
                    .catch(err => console.log('Lỗi real-time:', err));
            });
        }, 3000);
    </script>
</body>
</html>