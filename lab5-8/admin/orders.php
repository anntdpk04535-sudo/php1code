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
        // 2. Chặn nếu đơn hàng đã kết thúc (Hoàn tất hoặc Đã hủy)
        elseif ($currentStatus === 'Hoàn tất') {
            $error = "Đơn hàng [$orderId] đã HOÀN TẤT, không thể thay đổi trạng thái!";
        }
        elseif ($currentStatus === 'Đã hủy') {
            $error = "Đơn hàng [$orderId] đã BỊ HỦY, không thể thay đổi trạng thái!";
        }
        // 3. Nếu hiện tại là "Đã đặt" -> CHỈ được lên "Đang giao hàng" hoặc "Đã hủy"
        elseif ($currentStatus === 'Đã đặt' && !in_array($newStatus, ['Đang giao hàng', 'Đã hủy'])) {
            $error = "Đơn hàng đang ở trạng thái 'Đã đặt'. Bạn chỉ có thể chuyển sang 'Đang giao hàng' hoặc 'Đã hủy'!";
        }
        // 4. Nếu hiện tại là "Đang giao hàng" -> CHỈ được lên "Hoàn tất" hoặc "Đã hủy" (Không được quay lại "Đã đặt")
        elseif ($currentStatus === 'Đang giao hàng' && !in_array($newStatus, ['Hoàn tất', 'Đã hủy'])) {
            $error = "Đơn hàng đang ở trạng thái 'Đang giao hàng'. Bạn chỉ có thể chuyển sang 'Hoàn tất' hoặc 'Đã hủy' và không thể quay lại bước trước!";
        }
        // 5. Kiểm tra bắt buộc nhập lý do nếu chuyển trạng thái sang hủy đơn
        elseif ($newStatus === 'Đã hủy' && empty($cancelReason)) {
            $error = "Bạn phải nhập lý do hủy đơn hàng [$orderId]!";
        } 
        else {
            // Thực thi cập nhật cơ sở dữ liệu nếu vượt qua tất cả các chốt chặn
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
                $success = "Cập nhật trạng thái đơn hàng [$orderId] thành công!";
            }

            header("Location: orders.php?success=" . urlencode($success));
            exit;
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
    <title>Admin - Quản Lý Đơn Hàng</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: #f1f5f9;
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            background: #1e293b;
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            top: 0;
            left: 0;
        }

        .sidebar h2 {
            font-size: 20px;
            margin-bottom: 30px;
            text-align: center;
            border-bottom: 1px solid #334155;
            padding-bottom: 15px;
            color: #38bdf8;
        }

        .sidebar a {
            display: block;
            color: #cbd5e1;
            text-decoration: none;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-weight: bold;
            transition: all 0.2s;
        }

        .sidebar a:hover {
            background: #334155;
            color: white;
        }

        .sidebar a.active {
            background: #0284c7;
            color: white;
        }

        /* CONTENT */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 30px;
        }

        .header-panel {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            vertical-align: middle;
        }

        th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
        }

        select {
            padding: 6px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 13px;
            outline: none;
        }

        select:disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }

        button {
            background: #0284c7;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
        }

        button:hover:not(:disabled) {
            background: #0369a1;
        }

        button:disabled {
            background: #cbd5e1;
            color: #94a3b8;
            cursor: not-allowed;
        }

        /* Giao diện động nhập lý do hủy */
        .reason-box {
            display: none;
            margin-top: 5px;
        }

        .reason-box input {
            padding: 5px;
            font-size: 12px;
            border: 1px solid #fca5a5;
            border-radius: 4px;
            width: 150px;
            outline: none;
        }

        /* Màu trạng thái */
        .status-badge {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-shipping {
            background: #e0f2fe;
            color: #0369a1;
        }

        .status-success {
            background: #d1fae5;
            color: #065f46;
        }

        .status-cancel {
            background: #fee2e2;
            color: #b91c1c;
        }

        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
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
        <h2>🛠️ TECHSHOP ADMIN</h2>
        <a href="index.php">🏠 Bảng Điều Khiển</a>
        <a href="orders.php" class="active">📦 Quản lý đơn hàng</a>
        <a href="products.php">🏷️ Quản lý sản phẩm</a>
        <a href="users.php">👥 Quản lý người dùng</a>
        <a href="reports.php">⚠️ Quản lý khiếu nại</a>
        <a href="../index.php" style="margin-top: 80px; background: #b91c1c; text-align: center; color: white;">Trang chủ User</a>
    </div>

    <div class="main-content">
        <div class="header-panel">
            <h2>Quản Lý Đơn Hàng Hệ Thống</h2>
            <a href="index.php" style="text-decoration:none; color:#0284c7; font-weight:bold;">← Quay lại Dashboard</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card">
            <table>
                <tr>
                    <th>Mã Đơn</th>
                    <th>Khách Hàng</th>
                    <th>Tổng Tiền</th>
                    <th>Ngày Đặt</th>
                    <th>Trạng Thái Hiện Tại</th>
                    <th>Cập Nhật Trạng Thái</th>
                </tr>
                <?php foreach ($orders as $o):
                    // Định nghĩa class màu sắc cho Badge dựa trên trạng thái
                    $badgeClass = 'status-pending';
                    if ($o['status'] === 'Đang giao hàng') $badgeClass = 'status-shipping';
                    if ($o['status'] === 'Hoàn tất') $badgeClass = 'status-success';
                    if ($o['status'] === 'Đã hủy') $badgeClass = 'status-cancel';

                    // Khóa kiểm soát form nếu đơn đã kết thúc hoàn toàn (Hoàn tất hoặc Hủy)
                    $isLocked = ($o['status'] === 'Hoàn tất' || $o['status'] === 'Đã hủy');
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($o['order_id']) ?></strong></td>
                        <td>
                            <div><?= htmlspecialchars($o['fullname']) ?></div>
                            <small style="color:#64748b;"><?= htmlspecialchars($o['phone']) ?></small>
                        </td>
                        <td style="color:#dc2626; font-weight:bold;"><?= number_format($o['total'], 0, ',', '.') ?>đ</td>
                        <td><?= htmlspecialchars($o['created_at']) ?></td>
                        <td>
                            <span class="status-badge <?= $badgeClass ?>"><?= htmlspecialchars($o['status']) ?></span>
                            <?php if (!empty($o['cancel_reason'])): ?>
                                <div style="font-size:12px; color:#b91c1c; margin-top:4px; max-width:180px;">
                                    <strong>Lý do hủy:</strong> <?= htmlspecialchars($o['cancel_reason']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($o['order_id']) ?>">

                                <div style="display:flex; gap:5px;">
                                    <select name="status" onchange="handleStatusChange(this, '<?= $o['order_id'] ?>')" <?= $isLocked ? 'disabled' : '' ?>>
                                        <?php if ($o['status'] === 'Đã đặt'): ?>
                                            <option value="Đã đặt" selected>Đã đặt</option>
                                            <option value="Đang giao hàng">Đang giao hàng</option>
                                            <option value="Đã hủy">Hủy đơn</option>
                                        <?php elseif ($o['status'] === 'Đang giao hàng'): ?>
                                            <option value="Đang giao hàng" selected>Đang giao hàng</option>
                                            <option value="Hoàn tất">Hoàn tất</option>
                                            <option value="Đã hủy">Hủy đơn</option>
                                        <?php else: ?>
                                            <option value="<?= htmlspecialchars($o['status']) ?>" selected><?= htmlspecialchars($o['status']) ?></option>
                                        <?php endif; ?>
                                    </select>

                                    <button type="submit" <?= $isLocked ? 'disabled' : '' ?>>Lưu</button>
                                </div>

                                <div class="reason-box" id="reason_box_<?= $o['order_id'] ?>">
                                    <input type="text" name="cancel_reason" id="reason_input_<?= $o['order_id'] ?>" placeholder="Nhập lý do hủy bắt buộc...">
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>

</html>