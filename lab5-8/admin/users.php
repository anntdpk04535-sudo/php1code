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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechShop Admin — Quản Lý Thành Viên</title>
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
            border-left: 3px solid var(--gold);
            padding-left: 12px;
        }

        /* LAYOUT HÀNG GRID CHO FORM THÊM THÀNH VIÊN */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 13px; font-weight: 500; color: var(--text-dim); }

        .form-group input {
            padding: 11px 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            outline: none;
            font-size: 14px;
            background: var(--surface-2);
            color: var(--text);
            font-family: var(--font-body);
            transition: border-color 0.2s ease;
        }
        .form-group input::placeholder { color: var(--text-faint); }
        .form-group input:focus { border-color: var(--gold); }

        /* BẢNG DỮ LIỆU THÀNH VIÊN */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 14px 12px; text-align: left; border-bottom: 1px solid var(--border-soft); font-size: 13.5px; }
        th { background: var(--bg-soft); color: var(--text-faint); font-weight: 600; font-family: var(--font-mono); font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.03em; }
        tr:hover td { background: rgba(255, 255, 255, 0.015); }

        /* NÚT THÊM MỚI */
        .btn-add {
            position: relative;
            background: var(--gold);
            color: var(--bg);
            border: none;
            padding: 11px 22px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 700;
            font-family: var(--font-mono);
            font-size: 13.5px;
            margin-top: 15px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 10px 22px -8px rgba(216, 184, 122, 0.4);
        }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 14px 28px -8px rgba(216, 184, 122, 0.45); background: var(--gold-strong); }

        /* BADGE PHÂN BIỆT QUYỀN HẠN */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-weight: 600; font-size: 11px; }
        .badge-admin { background: var(--danger-dim); color: var(--danger); }
        .badge-user { background: var(--blue-dim); color: var(--blue); }

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
</head>

<body>

    <div class="sidebar">
        <h2><i class="fa-solid fa-screwdriver-wrench"></i> TECHSHOP ADMIN</h2>
        <a href="index.php"><i class="fa-solid fa-house"></i> Bảng Điều Khiển</a>
        <a href="orders.php"><i class="fa-solid fa-box"></i> Quản lý đơn hàng</a>
        <a href="products.php"><i class="fa-solid fa-tags"></i> Quản lý sản phẩm</a>
        <a href="users.php" class="active"><i class="fa-solid fa-users"></i> Quản lý người dùng</a>
        <a href="reports.php"><i class="fa-solid fa-triangle-exclamation"></i> Quản lý khiếu nại</a>
        <a href="../index.php" class="lnk-exit"><i class="fa-solid fa-arrow-left"></i> Trang chủ User</a>
    </div>

    <div class="main-content">
        <div class="header-panel">
            <h2>Quản Lý Người Dùng Hệ Thống</h2>
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Quay lại Dashboard</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= $error ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $success ?></div>
        <?php endif; ?>

        <div class="card">
            <h3><i class="fa-solid fa-user-plus"></i> Tạo Tài Khoản Thành Viên Mới</h3>
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
                <button type="submit" class="btn-add"><i class="fa-solid fa-plus"></i> Tạo tài khoản</button>
            </form>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-users"></i> Danh Sách Các Tài Khoản Trên Hệ Thống</h3>
            <table>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Họ và Tên</th>
                    <th>Tên Đăng Nhập</th>
                    <th>Email</th>
                    <th style="text-align: center; width: 150px;">Vai Trò (Role)</th>
                </tr>
                <?php if (empty($users)): ?>
                    <tr><td colspan="5" style="text-align:center; color:var(--text-faint);">Chưa có tài khoản nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td style="color: var(--text-dim); font-family: var(--font-mono);"><?= htmlspecialchars($u['id']) ?></td>
                            <td><strong style="color: var(--text);"><?= htmlspecialchars($u['full_name']) ?></strong></td>
                            <td style="color: var(--text-dim);"><?= htmlspecialchars($u['username']) ?></td>
                            <td style="color: var(--text-dim);"><?= htmlspecialchars($u['email']) ?></td>
                            <td style="text-align: center;">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge badge-admin">ADMIN</span>
                                <?php else: ?>
                                    <span class="badge badge-user">USER</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>
    </div>
</body>

</html>