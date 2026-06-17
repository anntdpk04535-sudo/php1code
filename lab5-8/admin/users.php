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

// XỬ LÝ TÁC VỤ: ADMIN THÊM TÀI KHOẢN USER MỚI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($username) || empty($password) || empty($full_name) || empty($email)) {
        $error = 'Vui lòng điền đầy đủ tất cả các thông tin tài khoản!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Định dạng Email không hợp lệ!';
    } else {
        // KIỂM TRA TRÙNG TÊN ĐĂNG NHẬP (USERNAME) HOẶC EMAIL
        $checkUser = $db->getOne("SELECT * FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($checkUser) {
            $error = 'Tên đăng nhập hoặc địa chỉ Email này đã tồn tại trên hệ thống!';
        } else {
            // Thực hiện thêm mới với role mặc định luôn là 'user'
            $db->execute(
                "INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, 'user')",
                [$username, $password, $full_name, $email]
            );
            $success = "Đã tạo tài khoản thành viên [<strong>$username</strong>] thành công!";
            // Chuyển hướng để xóa dữ liệu form tránh bị gửi trùng khi F5
            header("Location: users.php?success=" . urlencode($success));
            exit;
        }
    }
}

// Nhận thông báo thành công sau khi chuyển hướng (nếu có)
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Lấy danh sách thành viên đổ ra bảng (Hiển thị tất cả tài khoản)
$users = $db->getAll("SELECT id, full_name, email, username, role FROM users ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Admin - Quản Lý Thành Viên</title>
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

        /* SIDEBAR ĐIỀU HƯỚNG CỐ ĐỊNH */
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

        /* KHỐI NỘI DUNG CHÍNH BÊN PHẢI */
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
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card h3 {
            margin-bottom: 15px;
            color: #1e293b;
            font-size: 16px;
            border-left: 4px solid #f59e0b;
            padding-left: 10px;
        }

        /* LAYOUT HÀNG GRID CHO FORM THÊM THÀNH VIÊN */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }

        .form-group input {
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            outline: none;
            font-size: 14px;
        }

        .form-group input:focus {
            border-color: #f59e0b;
        }

        /* BẢNG DỮ LIỆU THÀNH VIÊN */
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
        }

        th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
        }

        tr:hover {
            background: #f8fafc;
        }

        /* NÚT THÊM MỚI */
        .btn-add {
            background: #f59e0b;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 15px;
            transition: background 0.2s;
        }

        .btn-add:hover {
            background: #d97706;
        }

        /* BADGE PHÂN BIỆT QUYỀN HẠN */
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
            display: inline-block;
        }

        .badge-admin {
            background: #fee2e2;
            color: #dc2626;
        }

        .badge-user {
            background: #dbeafe;
            color: #2563eb;
        }

        /* ALERT THÔNG BÁO */
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
</head>

<body>

    <div class="sidebar">
        <h2>🛠️ TECHSHOP ADMIN</h2>
        <a href="index.php">🏠 Bảng Điều Khiển</a>
        <a href="orders.php">📦 Quản lý đơn hàng</a>
        <a href="products.php">🏷️ Quản lý sản phẩm</a>
        <a href="users.php" class="active">👥 Quản lý người dùng</a>
        <a href="reports.php">⚠️ Quản lý khiếu nại</a>
        <a href="../index.php" style="margin-top: 80px; background: #b91c1c; text-align: center; color: white;">Trang
            chủ User</a>
    </div>

    <div class="main-content">
        <div class="header-panel">
            <h2>Quản Lý Người Dùng Hệ Thống</h2>
            <a href="index.php" style="text-decoration:none; color:#f59e0b; font-weight:bold;">← Quay lại Dashboard</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>➕ Tạo Tài Khoản Thành Viên Mới </h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Tên đăng nhập (Username)</label>
                        <input type="text" name="username" placeholder="Nhập tên đăng nhập viết liền..." required>
                    </div>
                    <div class="form-group">
                        <label>Mật khẩu đăng nhập</label>
                        <input type="password" name="password" placeholder="Nhập mật khẩu..." required>
                    </div>
                    <div class="form-group">
                        <label>Họ và tên thành viên</label>
                        <input type="text" name="full_name" placeholder="Ví dụ: Nguyễn Văn A" required>
                    </div>
                    <div class="form-group">
                        <label>Địa chỉ Email</label>
                        <input type="email" name="email" placeholder="example@gmail.com" required>
                    </div>
                </div>
                <button type="submit" class="btn-add">➕ Tạo tài khoản</button>
            </form>
        </div>

        <div class="card">
            <h3>Danh Sách Các Tài Khoản Trên Hệ Thống</h3>
            <table>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Họ và Tên</th>
                    <th>Tên Đăng Nhập</th>
                    <th>Email</th>
                    <th style="text-align: center; width: 150px;">Vai Trò (Role)</th>
                </tr>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['id']) ?></td>
                        <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td style="text-align: center;">
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-admin">ADMIN</span>
                            <?php else: ?>
                                <span class="badge badge-user">USER</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>

</html>