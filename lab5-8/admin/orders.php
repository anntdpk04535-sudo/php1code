<?php
session_start();
require_once "../db_utils.php";
$db = new DB_UTILS();

// CHẶN BẢO MẬT: Nếu không có quyền Admin, lập tức từ chối quyền truy cập
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$error = '';
$success = '';

// XỬ LÝ CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'update_status'
) {

    $orderId = $_POST['order_id'];
    $newStatus = $_POST['status'];
    $cancelReason = trim($_POST['cancel_reason'] ?? '');

    $currentOrder = $db->getOne(
        "SELECT status FROM orders WHERE order_id = ?",
        [$orderId]
    );

    if ($currentOrder) {
        $currentStatus = $currentOrder['status'];

        // LÀM MỚI LOGIC KIỂM TRA CHẶN: Chỉ cho đi từng bước và không được quay lại
        
        // 1. Nếu trạng thái không thay đổi, bỏ qua không xử lý
        if ($currentStatus === $newStatus) {
            $error = "Trạng thái mới trùng với trạng thái hiện tại của đơn hàng!";
        }
        // 2. Chặn nếu đơn hàng đã kết thúc (Hoàn tất, Đã hủy hoặc Thanh toán thất bại)
        elseif ($currentStatus === 'Hoàn tất') {
            $error = "Đơn hàng [$orderId] đã HOÀN TẤT, không thể thay đổi trạng thái!";
        }
        elseif ($currentStatus === 'Đã hủy') {
            $error = "Đơn hàng [$orderId] đã BỊ HỦY, không thể thay đổi trạng thái!";
        }
        elseif ($currentStatus === 'Thanh toán thất bại') {
            $error = "Đơn hàng [$orderId] THANH TOÁN THẤT BẠI, không thể duyệt hay thay đổi trạng thái!";
        }
        // 2.5. VALIDATE: CHỈ ĐƯỢC DUYỆT ĐƠN KHI ĐÃ THANH TOÁN
        //      Đơn thanh toán online (VNPay) đang ở trạng thái "Chờ thanh toán" (khách chưa trả tiền)
        //      thì admin KHÔNG được duyệt (chuyển sang "Đang giao hàng"), chỉ được phép hủy đơn.
        elseif ($currentStatus === 'Chờ thanh toán' && $newStatus !== 'Đã hủy') {
            $error = "Đơn hàng [$orderId] CHƯA THANH TOÁN! Bạn chỉ có thể duyệt đơn (chuyển sang 'Đang giao hàng') sau khi khách hàng đã thanh toán thành công. Hiện tại bạn chỉ có thể hủy đơn này.";
        }
        // 3. Nếu hiện tại là "Đã đặt" (đơn COD) hoặc "Đã thanh toán" (đơn VNPay đã trả tiền)
        //    -> CHỈ được lên "Đang giao hàng" (duyệt đơn) hoặc "Đã hủy"
        elseif (in_array($currentStatus, ['Đã đặt', 'Đã thanh toán']) && !in_array($newStatus, ['Đang giao hàng', 'Đã hủy'])) {
            $error = "Đơn hàng đang ở trạng thái '$currentStatus'. Bạn chỉ có thể chuyển sang 'Đang giao hàng' hoặc 'Đã hủy'!";
        }
        // 4. Nếu hiện tại là "Đang giao hàng" -> CHỈ được lên "Hoàn tất" hoặc "Đã hủy" (Không được quay lại bước trước)
        elseif ($currentStatus === 'Đang giao hàng' && !in_array($newStatus, ['Hoàn tất', 'Đã hủy'])) {
            $error = "Đơn hàng đang ở trạng thái 'Đang giao hàng'. Bạn chỉ có thể chuyển sang 'Hoàn tất' hoặc 'Đã hủy' và không thể quay lại bước trước!";
        }
        // 5. Kiểm tra bắt buộc nhập lý do nếu chuyển trạng thái sang hủy đơn
        elseif ($newStatus === 'Đã hủy' && empty($cancelReason)) {
            $error = "Bạn phải nhập lý do hủy đơn hàng [$orderId]!";
        } 
        else {
            // Xác định loại thao tác có ảnh hưởng đến tồn kho hay không
            // Admin DUYỆT đơn -> trừ tồn kho (chỉ xảy ra khi đơn COD "Đã đặt" hoặc VNPay "Đã thanh toán")
            $isApproving = (in_array($currentStatus, ['Đã đặt', 'Đã thanh toán']) && $newStatus === 'Đang giao hàng');
            $isCancellingApproved = ($currentStatus === 'Đang giao hàng' && $newStatus === 'Đã hủy'); // Hủy đơn đã duyệt -> hoàn lại tồn kho đã trừ

            $stockBlockError = '';

            $db->connection->beginTransaction();
            try {
                if ($isApproving) {
                    $orderItems = $db->getAll("SELECT product_id, quantity FROM order_items WHERE order_id = ?", [$orderId]);

                    // Bước 1: Kiểm tra đủ tồn kho cho TẤT CẢ sản phẩm trong đơn trước khi trừ bất kỳ sản phẩm nào
                    foreach ($orderItems as $oi) {
                        $p = $db->getOne("SELECT description, stock FROM products WHERE product_id = ?", [$oi['product_id']]);
                        $availableStock = $p ? (int) $p['stock'] : 0;
                        if (!$p || $availableStock < $oi['quantity']) {
                            $pname = $p['description'] ?? $oi['product_id'];
                            $stockBlockError = "Không thể duyệt đơn [$orderId]: sản phẩm \"$pname\" chỉ còn $availableStock trong kho (đơn hàng cần {$oi['quantity']})!";
                            break;
                        }
                    }

                    // Bước 2: Nếu đủ tồn kho cho toàn bộ đơn hàng -> tiến hành trừ kho từng sản phẩm
                    if ($stockBlockError === '') {
                        foreach ($orderItems as $oi) {
                            $db->execute(
                                "UPDATE products SET stock = stock - ? WHERE product_id = ? AND stock >= ?",
                                [$oi['quantity'], $oi['product_id'], $oi['quantity']]
                            );
                        }
                    }
                } elseif ($isCancellingApproved) {
                    // Hoàn lại tồn kho đã trừ ở bước duyệt đơn trước đó
                    $orderItems = $db->getAll("SELECT product_id, quantity FROM order_items WHERE order_id = ?", [$orderId]);
                    foreach ($orderItems as $oi) {
                        $db->execute(
                            "UPDATE products SET stock = stock + ? WHERE product_id = ?",
                            [$oi['quantity'], $oi['product_id']]
                        );
                    }
                }

                if ($stockBlockError !== '') {
                    $db->connection->rollBack();
                    $error = $stockBlockError;
                } else {
                    // Thực thi cập nhật trạng thái đơn hàng
                    if ($newStatus === 'Đã hủy') {
                        $db->execute(
                            "UPDATE orders SET status = ?, cancel_reason = ? WHERE order_id = ?",
                            [$newStatus, $cancelReason, $orderId]
                        );
                        $success = "Đã hủy đơn hàng [$orderId] thành công!";
                    } else {
                        $db->execute(
                            "UPDATE orders SET status = ?, cancel_reason = NULL WHERE order_id = ?",
                            [$newStatus, $orderId]
                        );
                        $success = $isApproving
                            ? "Đã duyệt đơn hàng [$orderId]"
                            : "Cập nhật trạng thái đơn hàng [$orderId] thành công!";
                    }

                    $db->connection->commit();
                    header("Location: orders.php?success=" . urlencode($success));
                    exit;
                }
            } catch (Exception $e) {
                $db->connection->rollBack();
                $error = 'Lỗi hệ thống khi cập nhật tồn kho: ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Lấy danh sách đơn hàng kèm cột lý do hủy
$orders = $db->getAll("SELECT * FROM orders ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechShop Admin — Quản Lý Đơn Hàng</title>
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
            --violet: #a78bfa; --violet-dim: rgba(167, 139, 250, 0.12);
            --blue: #63b3ed; --blue-dim: rgba(99, 179, 237, 0.12);
            --danger: #ff5e72; --danger-dim: rgba(255, 94, 114, 0.14);
            --radius-lg: 18px; --radius-md: 12px; --radius-sm: 8px;
            --font-display: 'Space Grotesk', sans-serif; --font-body: 'Inter', sans-serif; --font-mono: 'JetBrains Mono', monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-body);
            background: var(--bg);
            display: flex;
            min-height: 100vh;
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
            mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black 25%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        a { color: inherit; }

        /* ===== SIDEBAR (đồng bộ với admin/index.php) ===== */
        .sidebar {
            width: 260px;
            background: var(--surface);
            border-right: 1px solid var(--border-soft);
            color: var(--text);
            padding: 24px 18px;
            position: fixed;
            height: 100vh;
            top: 0;
            left: 0;
            z-index: 100;
            display: flex;
            flex-direction: column;
        }

        .sidebar h2 {
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 26px;
            text-align: center;
            border-bottom: 1px solid var(--border-soft);
            padding-bottom: 18px;
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            letter-spacing: -0.01em;
        }
        .sidebar h2 i { color: var(--accent); filter: drop-shadow(0 0 6px var(--accent-glow)); }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-dim);
            text-decoration: none;
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            margin-bottom: 6px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .sidebar a:hover { background: var(--surface-2); color: var(--text); }
        .sidebar a.active { background: var(--accent-dim); color: var(--accent); border: 1px solid var(--accent-border); font-weight: 600; }
        .sidebar a.lnk-exit {
            margin-top: auto;
            background: var(--danger-dim);
            color: var(--danger);
            justify-content: center;
            border: 1px solid var(--danger);
            font-weight: 600;
        }
        .sidebar a.lnk-exit:hover { background: rgba(255, 94, 114, 0.22); }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 28px 30px 40px;
            position: relative;
            z-index: 1;
        }

        .header-panel {
            background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%);
            padding: 18px 24px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .header-panel h2 { font-family: var(--font-display); font-size: 19px; font-weight: 700; color: var(--text); }
        .header-panel a { color: var(--accent); font-weight: 600; text-decoration: none; font-size: 13.5px; display: inline-flex; align-items: center; gap: 6px; }
        .header-panel a:hover { color: var(--accent-strong); }

        .card {
            background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%);
            padding: 24px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            margin-bottom: 24px;
        }

        .card h3 {
            margin-bottom: 18px;
            color: var(--text);
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 600;
            border-left: 3px solid var(--accent);
            padding-left: 12px;
        }

        /* BẢNG DỮ LIỆU */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 14px 12px; text-align: left; border-bottom: 1px solid var(--border-soft); font-size: 13.5px; vertical-align: middle; }
        th { background: var(--bg-soft); color: var(--text-faint); font-weight: 600; font-family: var(--font-mono); font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.03em; }
        tr:hover td { background: rgba(255, 255, 255, 0.015); }

        select {
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13px;
            outline: none;
            background: var(--surface-2);
            color: var(--text);
            font-family: var(--font-body);
            transition: border-color 0.2s ease;
        }
        select:focus { border-color: var(--accent-border); }
        select option { background: var(--surface-2); color: var(--text); }

        select:disabled {
            background: var(--bg-soft);
            color: var(--text-faint);
            cursor: not-allowed;
            border-color: var(--border-soft);
        }

        /* NÚT BẤM */
        .btn {
            position: relative;
            background: var(--accent);
            color: var(--bg);
            border: none;
            padding: 9px 18px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 700;
            font-family: var(--font-mono);
            font-size: 12.5px;
            transition: all 0.2s ease;
            box-shadow: 0 10px 22px -8px var(--accent-glow);
        }
        .btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 14px 28px -8px var(--accent-glow); }
        .btn:disabled {
            background: var(--bg-soft);
            color: var(--text-faint);
            cursor: not-allowed;
            box-shadow: none;
        }

        /* Giao diện động nhập lý do hủy */
        .reason-box {
            display: none;
            margin-top: 5px;
        }

        .reason-box input {
            padding: 7px 10px;
            font-size: 12px;
            border: 1px solid var(--danger);
            border-radius: var(--radius-sm);
            width: 170px;
            outline: none;
            background: var(--surface-2);
            color: var(--text);
            font-family: var(--font-body);
        }
        .reason-box input::placeholder { color: var(--text-faint); }

        /* Màu trạng thái */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-weight: 600; font-size: 11px; }
        .st-dadat { background: var(--warn-dim); color: var(--warn); }
        .st-cho { background: var(--warn-dim); color: var(--warn); }
        .st-paid { background: var(--accent-dim); color: var(--accent); }
        .st-giao { background: var(--blue-dim); color: var(--blue); }
        .st-hoantat { background: var(--accent-dim); color: var(--accent); }
        .st-huy { background: var(--danger-dim); color: var(--danger); }
        .st-failed { background: var(--danger-dim); color: var(--danger); }

        /* THÔNG BÁO */
        .alert {
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-danger { background: var(--danger-dim); color: var(--danger); border: 1px solid var(--danger); }
        .alert-success { background: var(--accent-dim); color: var(--accent); border: 1px solid var(--accent-border); }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }
    </style>
    <script>
        // Hàm kiểm tra Client-side: Hiển thị Input nhập lý do nếu admin chọn "Đã hủy"
        function handleStatusChange(selectElement, orderId) {
            var reasonBox = document.getElementById('reason_box_' + orderId);
            var reasonInput = document.getElementById('reason_input_' + orderId);

            if (selectElement.value === 'Đã hủy') {
                reasonBox.style.display = 'block';
                reasonInput.required = true;
            } else {
                reasonBox.style.display = 'none';
                reasonInput.required = false;
                reasonInput.value = '';
            }
        }
    </script>
</head>

<body>
    <div class="sidebar">
        <h2><i class="fa-solid fa-screwdriver-wrench"></i> TECHSHOP ADMIN</h2>
        <a href="index.php"><i class="fa-solid fa-house"></i> Bảng Điều Khiển</a>
        <a href="orders.php" class="active"><i class="fa-solid fa-box"></i> Quản lý đơn hàng</a>
        <a href="products.php"><i class="fa-solid fa-tags"></i> Quản lý sản phẩm</a>
        <a href="users.php"><i class="fa-solid fa-users"></i> Quản lý người dùng</a>
        <a href="reports.php"><i class="fa-solid fa-triangle-exclamation"></i> Quản lý khiếu nại</a>
        <a href="../index.php" class="lnk-exit"><i class="fa-solid fa-arrow-left"></i> Trang chủ User</a>
    </div>

    <div class="main-content">
        <div class="header-panel">
            <h2>Quản Lý Đơn Hàng Hệ Thống</h2>
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Quay lại Dashboard</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= $error ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card">
            <h3><i class="fa-solid fa-box"></i> Danh Sách Đơn Hàng</h3>
            <table>
                <tr>
                    <th>Mã Đơn</th>
                    <th>Khách Hàng</th>
                    <th>Tổng Tiền</th>
                    <th>Ngày Đặt</th>
                    <th>Trạng Thái Hiện Tại</th>
                    <th>Cập Nhật Trạng Thái</th>
                </tr>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--text-faint);">Chưa có đơn hàng nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $o):
                        // Định nghĩa class màu sắc cho Badge dựa trên trạng thái
                        $badgeClass = 'st-dadat';
                        if ($o['status'] === 'Chờ thanh toán') $badgeClass = 'st-cho';
                        if ($o['status'] === 'Đã thanh toán') $badgeClass = 'st-paid';
                        if ($o['status'] === 'Đang giao hàng') $badgeClass = 'st-giao';
                        if ($o['status'] === 'Hoàn tất') $badgeClass = 'st-hoantat';
                        if ($o['status'] === 'Đã hủy') $badgeClass = 'st-huy';
                        if ($o['status'] === 'Thanh toán thất bại') $badgeClass = 'st-failed';

                        // Khóa kiểm soát form nếu đơn đã kết thúc hoàn toàn (Hoàn tất, Hủy hoặc Thanh toán thất bại)
                        $isLocked = in_array($o['status'], ['Hoàn tất', 'Đã hủy', 'Thanh toán thất bại']);
                        ?>
                        <tr>
                            <td><strong style="color: var(--accent); font-family: var(--font-mono);">#<?= htmlspecialchars($o['order_id']) ?></strong></td>
                            <td>
                                <div style="font-weight:600; color: var(--text);"><?= htmlspecialchars($o['fullname']) ?></div>
                                <small style="color:var(--text-faint);"><?= htmlspecialchars($o['phone']) ?></small>
                            </td>
                            <td style="color:var(--gold-strong); font-weight:700; font-family: var(--font-mono);"><?= number_format($o['total'], 0, ',', '.') ?>đ</td>
                            <td style="color: var(--text-dim);"><?= htmlspecialchars($o['created_at']) ?></td>
                            <td>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($o['status']) ?></span>
                                <?php if ($o['status'] === 'Chờ thanh toán'): ?>
                                    <div style="font-size:12px; color:var(--warn); margin-top:6px; max-width:180px;">
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($o['cancel_reason'])): ?>
                                    <div style="font-size:12px; color:var(--danger); margin-top:6px; max-width:180px;">
                                        <strong>Lý do hủy:</strong> <?= htmlspecialchars($o['cancel_reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:flex; flex-direction:column; gap:6px; align-items:flex-start;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($o['order_id']) ?>">

                                    <div style="display:flex; gap:6px;">
                                        <select name="status" onchange="handleStatusChange(this, '<?= $o['order_id'] ?>')" <?= $isLocked ? 'disabled' : '' ?>>
                                            <?php if ($o['status'] === 'Đã đặt' || $o['status'] === 'Đã thanh toán'): ?>
                                                <option value="<?= htmlspecialchars($o['status']) ?>" selected><?= htmlspecialchars($o['status']) ?></option>
                                                <option value="Đang giao hàng">Đang giao hàng</option>
                                                <option value="Đã hủy">Hủy đơn</option>
                                            <?php elseif ($o['status'] === 'Chờ thanh toán'): ?>
                                                <option value="Chờ thanh toán" selected>Chờ thanh toán</option>
                                                <option value="Đã hủy">Hủy đơn (chưa thanh toán)</option>
                                            <?php elseif ($o['status'] === 'Đang giao hàng'): ?>
                                                <option value="Đang giao hàng" selected>Đang giao hàng</option>
                                                <option value="Hoàn tất">Hoàn tất</option>
                                                <option value="Đã hủy">Hủy đơn</option>
                                            <?php else: ?>
                                                <option value="<?= htmlspecialchars($o['status']) ?>" selected><?= htmlspecialchars($o['status']) ?></option>
                                            <?php endif; ?>
                                        </select>

                                        <button type="submit" class="btn" <?= $isLocked ? 'disabled' : '' ?>>Lưu</button>
                                    </div>

                                    <div class="reason-box" id="reason_box_<?= $o['order_id'] ?>">
                                        <input type="text" name="cancel_reason" id="reason_input_<?= $o['order_id'] ?>" placeholder="Nhập lý do hủy bắt buộc...">
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>
    </div>
</body>

</html>