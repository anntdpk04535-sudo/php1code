<?php
session_start();
require_once "../db_utils.php";
$db = new DB_UTILS();

// CHẶN BẢO MẬT: Nếu không có quyền Admin, lập tức từ chối quyền truy cập
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$success = '';
$error = '';

// AJAX ĐỂ QUÉT DỮ LIỆU MỚI (Dùng cho tính năng Real-time tải lại bảng)
if (isset($_GET['ajax_load_list'])) {
    $reports = $db->getAll("SELECT * FROM reports ORDER BY created_at DESC");
    if (empty($reports)) {
        echo '<tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:40px;">Hộp thư trống! Chưa có khiếu nại nào từ người dùng.</td></tr>';
    } else {
        foreach ($reports as $r) {
            $badge = 'status-wait';
            if ($r['status'] === 'Đang giải quyết') $badge = 'status-process';
            if ($r['status'] === 'Đã đóng') $badge = 'status-closed';
            
            $isLocked = ($r['status'] === 'Đã đóng');
            ?>
            <tr id="admin_report_row_<?= $r['id'] ?>">
                <td><strong>#<?= $r['id'] ?></strong></td>
                <td>
                    <div style="font-weight:600; color:#1e293b;"><?= htmlspecialchars($r['fullname']) ?></div>
                    <small style="color:#64748b;"><?= htmlspecialchars($r['email']) ?></small>
                    <div style="font-size:11px; color:#94a3b8; margin-top:4px;"><?= $r['created_at'] ?></div>
                </td>
                <td>
                    <div style="font-weight:600; color:#0284c7; margin-bottom:4px;">Tiêu đề: <?= htmlspecialchars($r['title']) ?></div>
                    <div style="color:#475569; font-size:13px; line-height:1.4; white-space:pre-line;"><?= htmlspecialchars($r['content']) ?></div>
                </td>
                <td><span class="badge <?= $badge ?> admin-status-text"><?= htmlspecialchars($r['status']) ?></span></td>
                <td>
                    <form method="POST" class="report-admin-form">
                        <input type="hidden" name="action" value="update_report">
                        <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                        
                        <label style="font-size:12px; font-weight:bold; color:#64748b;">Trạng thái quy trình:</label>
                        <select name="status" <?= $isLocked ? 'disabled' : '' ?>>
                            <?php if ($r['status'] === 'Chờ xử lý'): ?>
                                <option value="Chờ xử lý" selected>Chờ xử lý</option>
                                <option value="Đang giải quyết">Đang giải quyết</option>
                                <option value="Đã đóng">Đã đóng (Hoàn tất)</option>
                            <?php elseif ($r['status'] === 'Đang giải quyết'): ?>
                                <option value="Đang giải quyết" selected>Đang giải quyết</option>
                                <option value="Đã đóng">Đã đóng (Hoàn tất)</option>
                            <?php else: ?>
                                <option value="Đã đóng" selected>Đã đóng (Hoàn tất)</option>
                            <?php endif; ?>
                        </select>

                        <label style="font-size:12px; font-weight:bold; color:#64748b;">Nội dung phản hồi (Admin note):</label>
                        <textarea name="admin_note" rows="3" placeholder="Nhập câu trả lời tại đây..." <?= $isLocked ? 'readonly' : '' ?>><?= htmlspecialchars($r['admin_note'] ?? '') ?></textarea>
                        
                        <button type="submit" <?= $isLocked ? 'disabled' : '' ?>>💾 Lưu phản hồi</button>
                    </form>
                </td>
            </tr>
            <?php
        }
    }
    exit;
}

// XỬ LÝ CẬP NHẬT TRẠNG THÁI CỐ ĐỊNH PHÍA BACKEND
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_report') {
    $report_id = $_POST['report_id'];
    $newStatus = $_POST['status'];
    $admin_note = trim($_POST['admin_note'] ?? '');

    $currentReport = $db->getOne("SELECT status FROM reports WHERE id = ?", [$report_id]);

    if ($currentReport) {
        $currentStatus = $currentReport['status'];

        if ($currentStatus === $newStatus) {
            // Cho phép cập nhật ghi chú khi trạng thái giữ nguyên
            $db->execute("UPDATE reports SET admin_note = ? WHERE id = ?", [$admin_note, $report_id]);
            $success = "Cập nhật ghi chú cho báo cáo #$report_id thành công!";
        } 
        // FIX CỨNG LOGIC TRẠNG THÁI MỘT CHIỀU
        elseif ($currentStatus === 'Đã đóng') {
            $error = "Báo cáo này đã ĐÓNG, không thể thay đổi trạng thái!";
        } elseif ($currentStatus === 'Đang giải quyết' && $newStatus === 'Chờ xử lý') {
            $error = "Quy trình không thể quay lại bước 'Chờ xử lý'!";
        } else {
            $db->execute(
                "UPDATE reports SET status = ?, admin_note = ? WHERE id = ?",
                [$newStatus, $admin_note, $report_id]
            );
            $success = "Duyệt quy trình báo cáo #$report_id sang trạng thái [$newStatus] thành công!";
        }
    }
    
    if(!empty($error)) {
        header("Location: reports.php?error=" . urlencode($error));
    } else {
        header("Location: reports.php?success=" . urlencode($success));
    }
    exit;
}

$initial_count = $db->getOne("SELECT COUNT(*) as total FROM reports")['total'] ?? 0;
$reports = $db->getAll("SELECT * FROM reports ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin - Quản Lý Quy Trình Khiếu Nại</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #f1f5f9; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #1e293b; color: white; padding: 20px; position: fixed; height: 100vh; top: 0; left: 0; }
        .sidebar h2 { font-size: 20px; margin-bottom: 30px; text-align: center; border-bottom: 1px solid #334155; padding-bottom: 15px; color: #38bdf8; }
        .sidebar a { display: block; color: #cbd5e1; text-decoration: none; padding: 12px 15px; border-radius: 6px; margin-bottom: 10px; font-weight: bold; transition: all 0.2s; }
        .sidebar a:hover { background: #334155; color: white; }
        .sidebar a.active { background: #0284c7; color: white; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; }
        .header-panel { background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 14px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 14px; vertical-align: top; }
        th { background: #f8fafc; color: #64748b; font-weight: 600; }
        .badge { font-weight: bold; padding: 4px 8px; border-radius: 4px; font-size: 12px; display: inline-block; }
        .status-wait { background: #fef3c7; color: #d97706; }
        .status-process { background: #e0f2fe; color: #0369a1; }
        .status-closed { background: #d1fae5; color: #065f46; }
        .alert-success { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; font-weight: 500; }
        .alert-danger { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; font-weight: 500; }
        textarea, select { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; outline: none; margin-bottom: 6px; }
        button { background: #0284c7; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px; }
        button:hover:not(:disabled) { background: #0369a1; }
        button:disabled { background: #cbd5e1; cursor: not-allowed; }
        .live-indicator { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #16a34a; font-weight: bold; background: #f0fdf4; padding: 4px 10px; border-radius: 20px; border: 1px solid #bbf7d0; }
        .dot { width: 8px; height: 8px; background: #22c55e; border-radius: 50%; animation: blink 1.2s infinite; }
        @keyframes blink { 0% { opacity: 0.3; } 50% { opacity: 1; } 100% { opacity: 0.3; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>🛠️ TECHSHOP ADMIN</h2>
        <a href="index.php">🏠 Bảng Điều Khiển</a>
        <a href="orders.php">📦 Quản lý đơn hàng</a>
        <a href="products.php">🏷️ Quản lý sản phẩm</a>
        <a href="users.php">👥 Quản lý người dùng</a>
        <a href="reports.php" class="active">⚠️ Quản lý khiếu nại</a>
        <a href="../index.php" style="margin-top: 50px; background: #b91c1c; text-align: center; color: white;">Trang chủ User</a>
    </div>

    <div class="main-content">
        <div class="header-panel">
            <h2>Quy Trình Khiếu Nại <div class="live-indicator"><div class="dot"></div> LIVE REALTIME</div></h2>
            <a href="index.php" style="text-decoration:none; color:#0284c7; font-weight:bold;">← Quay lại Dashboard</a>
        </div>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">Mã thư</th>
                        <th style="width: 180px;">Người gửi</th>
                        <th style="width: 250px;">Nội dung sự cố</th>
                        <th style="width: 120px;">Trạng thái</th>
                        <th>Xử lý & Phản hồi</th>
                    </tr>
                </thead>
                <tbody id="report_list_body">
                    <?php if (empty($reports)): ?>
                        <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:40px;">Hộp thư trống! Chưa có khiếu nại nào từ người dùng.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reports as $r): 
                            $badge = 'status-wait';
                            if ($r['status'] === 'Đang giải quyết') $badge = 'status-process';
                            if ($r['status'] === 'Đã đóng') $badge = 'status-closed';
                            
                            $isLocked = ($r['status'] === 'Đã đóng');
                        ?>
                            <tr id="admin_report_row_<?= $r['id'] ?>">
                                <td><strong>#<?= $r['id'] ?></strong></td>
                                <td>
                                    <div style="font-weight:600; color:#1e293b;"><?= htmlspecialchars($r['fullname']) ?></div>
                                    <small style="color:#64748b;"><?= htmlspecialchars($r['email']) ?></small>
                                    <div style="font-size:11px; color:#94a3b8; margin-top:4px;"><?= $r['created_at'] ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:600; color:#0284c7; margin-bottom:4px;">Tiêu đề: <?= htmlspecialchars($r['title']) ?></div>
                                    <div style="color:#475569; font-size:13px; line-height:1.4; white-space:pre-line;"><?= htmlspecialchars($r['content']) ?></div>
                                </td>
                                <td><span class="badge <?= $badge ?> admin-status-text"><?= htmlspecialchars($r['status']) ?></span></td>
                                <td>
                                    <form method="POST" class="report-admin-form">
                                        <input type="hidden" name="action" value="update_report">
                                        <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                        
                                        <label style="font-size:12px; font-weight:bold; color:#64748b;">Trạng thái quy trình:</label>
                                        <select name="status" <?= $isLocked ? 'disabled' : '' ?>>
                                            <?php if ($r['status'] === 'Chờ xử lý'): ?>
                                                <option value="Chờ xử lý" selected>Chờ xử lý</option>
                                                <option value="Đang giải quyết">Đang giải quyết</option>
                                                <option value="Đã đóng">Đã đóng (Hoàn tất)</option>
                                            <?php elseif ($r['status'] === 'Đang giải quyết'): ?>
                                                <option value="Đang giải quyết" selected>Đang giải quyết</option>
                                                <option value="Đã đóng">Đã đóng (Hoàn tất)</option>
                                            <?php else: ?>
                                                <option value="Đã đóng" selected>Đã đóng (Hoàn tất)</option>
                                            <?php endif; ?>
                                        </select>

                                        <label style="font-size:12px; font-weight:bold; color:#64748b;">Nội dung phản hồi (Admin note):</label>
                                        <textarea name="admin_note" rows="3" placeholder="Nhập câu trả lời tại đây..." <?= $isLocked ? 'readonly' : '' ?>><?= htmlspecialchars($r['admin_note'] ?? '') ?></textarea>
                                        
                                        <button type="submit" <?= $isLocked ? 'disabled' : '' ?>>💾 Lưu phản hồi</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        let currentTotalReports = <?= $initial_count ?>;
        setInterval(() => {
            fetch('check_reports.php')
                .then(res => res.json())
                .then(data => {
                    if (data && data.status === 'success') {
                        if (data.total !== currentTotalReports) {
                            currentTotalReports = data.total;
                            fetch('reports.php?ajax_load_list=1')
                                .then(res => res.text())
                                .then(htmlTable => {
                                    document.getElementById('report_list_body').innerHTML = htmlTable;
                                });
                        }
                    }
                }).catch(err => console.log(err));
        }, 3000);
    </script>
</body>
</html>