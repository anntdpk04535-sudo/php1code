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
        echo '<tr><td colspan="5" style="text-align:center; color:var(--text-faint); padding:40px;">Hộp thư trống! Chưa có khiếu nại nào từ người dùng.</td></tr>';
    } else {
        foreach ($reports as $r) {
            $badge = 'status-wait';
            if ($r['status'] === 'Đang giải quyết') $badge = 'status-process';
            if ($r['status'] === 'Đã đóng') $badge = 'status-closed';
            
            $isLocked = ($r['status'] === 'Đã đóng');
            ?>
            <tr id="admin_report_row_<?= $r['id'] ?>">
                <td><strong style="color: var(--accent); font-family: var(--font-mono);">#<?= $r['id'] ?></strong></td>
                <td>
                    <div style="font-weight:600; color:var(--text);"><?= htmlspecialchars($r['fullname']) ?></div>
                    <small style="color:var(--text-faint);"><?= htmlspecialchars($r['email']) ?></small>
                    <div style="font-size:11px; color:var(--text-faint); margin-top:4px;"><?= $r['created_at'] ?></div>
                </td>
                <td>
                    <div style="font-weight:600; color:var(--blue); margin-bottom:4px;">Tiêu đề: <?= htmlspecialchars($r['title']) ?></div>
                    <div style="color:var(--text-dim); font-size:13px; line-height:1.4; white-space:pre-line;"><?= htmlspecialchars($r['content']) ?></div>
                </td>
                <td><span class="badge <?= $badge ?> admin-status-text"><?= htmlspecialchars($r['status']) ?></span></td>
                <td>
                    <form method="POST" class="report-admin-form">
                        <input type="hidden" name="action" value="update_report">
                        <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                        
                        <label>Trạng thái quy trình:</label>
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

                        <label>Nội dung phản hồi (Admin note):</label>
                        <textarea name="admin_note" rows="3" placeholder="Nhập câu trả lời tại đây..." <?= $isLocked ? 'readonly' : '' ?>><?= htmlspecialchars($r['admin_note'] ?? '') ?></textarea>
                        
                        <button type="submit" class="btn" <?= $isLocked ? 'disabled' : '' ?>><i class="fa-solid fa-floppy-disk"></i> Lưu phản hồi</button>
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechShop Admin — Quản Lý Quy Trình Khiếu Nại</title>
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
        .header-panel h2 { font-family: var(--font-display); font-size: 19px; font-weight: 700; color: var(--text); display: flex; align-items: center; }
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
            border-left: 3px solid var(--warn);
            padding-left: 12px;
        }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 14px 12px; text-align: left; border-bottom: 1px solid var(--border-soft); font-size: 13.5px; vertical-align: top; }
        th { background: var(--bg-soft); color: var(--text-faint); font-weight: 600; font-family: var(--font-mono); font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.03em; }
        tr:hover td { background: rgba(255, 255, 255, 0.015); }

        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-weight: 600; font-size: 11px; }
        .status-wait { background: var(--warn-dim); color: var(--warn); }
        .status-process { background: var(--blue-dim); color: var(--blue); }
        .status-closed { background: var(--accent-dim); color: var(--accent); }

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

        .report-admin-form label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-faint);
            margin-bottom: 6px;
            margin-top: 10px;
        }
        .report-admin-form label:first-child { margin-top: 0; }

        textarea, select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13px;
            outline: none;
            margin-bottom: 8px;
            background: var(--surface-2);
            color: var(--text);
            font-family: var(--font-body);
            transition: border-color 0.2s ease;
        }
        textarea::placeholder { color: var(--text-faint); }
        textarea:focus, select:focus { border-color: var(--accent-border); }
        select option { background: var(--surface-2); color: var(--text); }
        select:disabled, textarea:read-only {
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

        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            color: var(--accent);
            font-weight: 600;
            background: var(--accent-dim);
            padding: 5px 12px;
            border-radius: 999px;
            border: 1px solid var(--accent-border);
            font-family: var(--font-mono);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-left: 12px;
        }
        .dot { width: 7px; height: 7px; background: var(--accent); border-radius: 50%; box-shadow: 0 0 6px var(--accent-glow); animation: blink 1.2s infinite; }
        @keyframes blink { 0% { opacity: 0.3; } 50% { opacity: 1; } 100% { opacity: 0.3; } }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2><i class="fa-solid fa-screwdriver-wrench"></i> TECHSHOP ADMIN</h2>
        <a href="index.php"><i class="fa-solid fa-house"></i> Bảng Điều Khiển</a>
        <a href="orders.php"><i class="fa-solid fa-box"></i> Quản lý đơn hàng</a>
        <a href="products.php"><i class="fa-solid fa-tags"></i> Quản lý sản phẩm</a>
        <a href="users.php"><i class="fa-solid fa-users"></i> Quản lý người dùng</a>
        <a href="reports.php" class="active"><i class="fa-solid fa-triangle-exclamation"></i> Quản lý khiếu nại</a>
        <a href="../index.php" class="lnk-exit"><i class="fa-solid fa-arrow-left"></i> Trang chủ User</a>
    </div>

    <div class="main-content">
        <div class="header-panel">
            <h2>Quy Trình Khiếu Nại <span class="live-indicator"><span class="dot"></span> LIVE REALTIME</span></h2>
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Quay lại Dashboard</a>
        </div>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <div class="card">
            <h3><i class="fa-solid fa-inbox"></i> Hộp Thư Khiếu Nại</h3>
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
                        <tr><td colspan="5" style="text-align:center; color:var(--text-faint); padding:40px;">Hộp thư trống! Chưa có khiếu nại nào từ người dùng.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reports as $r): 
                            $badge = 'status-wait';
                            if ($r['status'] === 'Đang giải quyết') $badge = 'status-process';
                            if ($r['status'] === 'Đã đóng') $badge = 'status-closed';
                            
                            $isLocked = ($r['status'] === 'Đã đóng');
                        ?>
                            <tr id="admin_report_row_<?= $r['id'] ?>">
                                <td><strong style="color: var(--accent); font-family: var(--font-mono);">#<?= $r['id'] ?></strong></td>
                                <td>
                                    <div style="font-weight:600; color:var(--text);"><?= htmlspecialchars($r['fullname']) ?></div>
                                    <small style="color:var(--text-faint);"><?= htmlspecialchars($r['email']) ?></small>
                                    <div style="font-size:11px; color:var(--text-faint); margin-top:4px;"><?= $r['created_at'] ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:600; color:var(--blue); margin-bottom:4px;">Tiêu đề: <?= htmlspecialchars($r['title']) ?></div>
                                    <div style="color:var(--text-dim); font-size:13px; line-height:1.4; white-space:pre-line;"><?= htmlspecialchars($r['content']) ?></div>
                                </td>
                                <td><span class="badge <?= $badge ?> admin-status-text"><?= htmlspecialchars($r['status']) ?></span></td>
                                <td>
                                    <form method="POST" class="report-admin-form">
                                        <input type="hidden" name="action" value="update_report">
                                        <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                        
                                        <label>Trạng thái quy trình:</label>
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

                                        <label>Nội dung phản hồi (Admin note):</label>
                                        <textarea name="admin_note" rows="3" placeholder="Nhập câu trả lời tại đây..." <?= $isLocked ? 'readonly' : '' ?>><?= htmlspecialchars($r['admin_note'] ?? '') ?></textarea>
                                        
                                        <button type="submit" class="btn" <?= $isLocked ? 'disabled' : '' ?>><i class="fa-solid fa-floppy-disk"></i> Lưu phản hồi</button>
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