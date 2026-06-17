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
$fullname = $_SESSION['user']['full_name'] ?? '';
$email = $_SESSION['user']['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($title) || empty($content)) {
        $error = "Vui lòng điền đầy đủ tiêu đề và nội dung khiếu nại!";
    } else {
        $db->execute(
            "INSERT INTO reports (user_id, fullname, email, title, content, status) VALUES (?, ?, ?, ?, ?, 'Chờ xử lý')",
            [$user_id, $fullname, $email, $title, $content]
        );
        $message = "Gửi khiếu nại thành công! Ban quản trị sẽ kiểm tra và phản hồi ngay bên dưới.";
    }
}

$my_reports = $db->getAll("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC", [$user_id]);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Khiếu Nại & Hỗ Trợ - TechShop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); min-height: 100vh; padding: 40px 20px; color: #334155; }
        .container { max-width: 900px; margin: 0 auto; }
        .report-box { background: white; padding: 35px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 35px; }
        .report-header { text-align: center; margin-bottom: 25px; }
        .report-header h1 { font-size: 24px; color: #1e293b; font-weight: 800; margin-bottom: 6px; }
        .report-header p { color: #64748b; font-size: 14px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
        .form-group label { font-size: 13px; font-weight: 600; color: #475569; }
        .form-group input, .form-group textarea { padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 10px; outline: none; font-size: 14px; transition: all 0.2s; }
        .form-group input:focus, .form-group textarea:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
        .form-group input[readonly] { background: #f1f5f9; color: #64748b; cursor: not-allowed; }
        .btn-submit { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 15px; cursor: pointer; transition: transform 0.2s; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.2); }
        .alert { padding: 16px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        .btn-back { display: inline-flex; align-items: center; gap: 6px; text-decoration: none; color: #64748b; font-weight: 600; font-size: 14px; margin-bottom: 20px; }
        .btn-back:hover { color: #1e293b; }
        .history-box { background: white; padding: 30px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .history-box h2 { font-size: 18px; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: top; }
        th { background: #f8fafc; color: #64748b; font-weight: 600; }
        .badge { font-weight: bold; padding: 4px 10px; border-radius: 20px; font-size: 12px; display: inline-block; }
        
        /* Đồng bộ Class CSS động */
        .status-Chờ-xử-lý { background: #fef3c7; color: #d97706; }
        .status-Đang-giải-quyết { background: #e0f2fe; color: #0369a1; }
        .status-Đã-đóng { background: #d1fae5; color: #065f46; }
        
        .admin-reply { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 12px 16px; border-radius: 8px; margin-top: 10px; color: #16a34a; font-size: 13px; }
        .admin-reply strong { color: #15803d; display: flex; align-items: center; gap: 4px; margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại trang chủ mua sắm</a>

        <div class="report-box">
            <div class="report-header">
                <h1><i class="fas fa-headset" style="color:#6366f1;"></i> Trung Tâm Hỗ Trợ & Khiếu Nại</h1>
                <p>Gửi thắc mắc của bạn, chúng tôi sẽ tiếp nhận và phản hồi ngay phía dưới trang này.</p>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><?=$message?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?=$error?></div><?php endif; ?>

            <form method="POST">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Tên tài khoản</label>
                        <input type="text" value="<?=htmlspecialchars($fullname)?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Email liên hệ</label>
                        <input type="text" value="<?=htmlspecialchars($email)?>" readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label>Tiêu đề cần hỗ trợ</label>
                    <input type="text" name="title" placeholder="Ví dụ: Lỗi trừ tiền đơn hàng #123..." required>
                </div>
                <div class="form-group">
                    <label>Nội dung chi tiết</label>
                    <textarea name="content" rows="4" placeholder="Nhập nội dung khiếu nại cụ thể tại đây..." required></textarea>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Gửi yêu cầu hỗ trợ</button>
            </form>
        </div>

        <div class="history-box">
            <h2><i class="fas fa-history" style="color:#64748b;"></i> Lịch sử phản hồi từ Ban quản trị</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 100px;">Mã thư</th>
                        <th style="width: 140px;">Thời gian</th>
                        <th>Chi tiết khiếu nại & Trả lời từ Admin</th>
                        <th style="width: 150px; text-align: center;">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_reports)): ?>
                        <tr id="empty_row"><td colspan="4" style="text-align:center; color:#94a3b8; padding:30px;">Bạn chưa gửi bất kỳ khiếu nại nào.</td></tr>
                    <?php else: ?>
                        <?php foreach ($my_reports as $row): 
                            $statusClass = 'status-' . str_replace(' ', '-', $row['status']);
                        ?>
                            <tr id="user_report_row_<?= $row['id'] ?>">
                                <td><strong>#<?= $row['id'] ?></strong></td>
                                <td><small style="color:#64748b;"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></small></td>
                                <td class="report-content-td">
                                    <div style="font-weight: 700; color:#1e293b; margin-bottom:4px;"><?= htmlspecialchars($row['title']) ?></div>
                                    <div style="color:#475569; font-size:13px; white-space:pre-line;"><?= htmlspecialchars($row['content']) ?></div>
                                    
                                    <div class="reply-container">
                                        <?php if (!empty($row['admin_note'])): ?>
                                            <div class="admin-reply">
                                                <strong><i class="fas fa-reply"></i> Phản hồi từ Admin:</strong>
                                                <span class="reply-text"><?= nl2br(htmlspecialchars($row['admin_note'])) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge <?= $statusClass ?> user-status-badge"><?= htmlspecialchars($row['status']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const userId = <?= (int)$user_id ?>;
        
        setInterval(() => {
            fetch(`admin/check_reports.php?user_id=${userId}`)
                .then(res => res.json())
                .then(res => {
                    if (res && res.status === 'success') {
                        res.data.forEach(report => {
                            const row = document.getElementById(`user_report_row_${report.id}`);
                            if (row) {
                                // 1. Cập nhật chữ và màu sắc của Badge trạng thái quy trình
                                const badge = row.querySelector('.user-status-badge');
                                if (badge && badge.innerText.trim() !== report.status) {
                                    badge.innerText = report.status;
                                    badge.className = `badge status-${report.status.replace(/\s+/g, '-')} user-status-badge`;
                                }

                                // 2. Cập nhật nội dung văn bản phản hồi từ Admin Note
                                const replyContainer = row.querySelector('.reply-container');
                                if (report.admin_note) {
                                    replyContainer.innerHTML = `
                                        <div class="admin-reply">
                                            <strong><i class="fas fa-reply"></i> Phản hồi từ Admin:</strong>
                                            <span class="reply-text">${report.admin_note.replace(/\n/g, '<br>')}</span>
                                        </div>`;
                                } else {
                                    replyContainer.innerHTML = '';
                                }
                            }
                        });
                    }
                }).catch(err => console.log(err));
        }, 3000); // Quét đồng bộ 3 giây/lần
    </script>
</body>
</html>