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

// Biến lưu thông tin sản phẩm cần sửa (nếu có)
$edit_product = null;

// 1. XỬ LÝ KHI CLICK NÚT "SỬA" (Lấy thông tin sản phẩm đổ lên form)
if (isset($_GET['edit_id'])) {
    $edit_id = trim($_GET['edit_id']);
    $edit_product = $db->getOne("SELECT * FROM products WHERE product_id = ?", [$edit_id]);
}

// 2. XỬ LÝ TÁC VỤ THÊM HOẶC CẬP NHẬT SẢN PHẨM (GỬI FORM)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Tác vụ: THÊM SẢN PHẨM MỚI
    if ($_POST['action'] === 'add_product') {
        $pid = trim($_POST['product_id']);
        $desc = trim($_POST['description']);
        $price = trim($_POST['price']);
        $image = trim($_POST['image']);

        if (empty($pid) || empty($desc) || empty($price)) {
            $error = 'Vui lòng điền đầy đủ các thông tin bắt buộc!';
        } else {
            // KIỂM TRA TRÙNG MÃ SẢN PHẨM (PRODUCT_ID)
            $check = $db->getOne("SELECT * FROM products WHERE product_id = ?", [$pid]);
            if ($check) {
                $error = "Lỗi: Mã sản phẩm '<strong>$pid</strong>' đã tồn tại trên hệ thống! Vui lòng dùng mã khác.";
            } else {
                $db->execute("INSERT INTO products (product_id, description, price, image) VALUES (?, ?, ?, ?)", [$pid, $desc, $price, $image]);
                $success = 'Thêm sản phẩm mới thành công!';
                // Chuyển hướng để xóa dữ liệu form cũ tránh trùng lặp khi F5
                header("Location: products.php?success=" . urlencode($success));
                exit;
            }
        }
    }

    // Tác vụ: CẬP NHẬT SẢN PHẨM (SỬA)
    if ($_POST['action'] === 'update_product') {
        $pid = trim($_POST['product_id']); // Khóa chính cố định không đổi
        $desc = trim($_POST['description']);
        $price = trim($_POST['price']);
        $image = trim($_POST['image']);

        if (empty($desc) || empty($price)) {
            $error = 'Không được để trống tên sản phẩm và giá bán!';
        } else {
            $db->execute("UPDATE products SET description = ?, price = ?, image = ? WHERE product_id = ?", [$desc, $price, $image, $pid]);
            $success = 'Cập nhật thông tin sản phẩm thành công!';
            header("Location: products.php?success=" . urlencode($success));
            exit;
        }
    }
}

// 3. XỬ LÝ TÁC VỤ XÓA SẢN PHẨM
if (isset($_GET['delete_id'])) {
    $delete_id = trim($_GET['delete_id']);
    // Thực hiện xóa khỏi CSDL
    $db->execute("DELETE FROM products WHERE product_id = ?", [$delete_id]);
    $success = 'Đã xóa sản phẩm thành công khỏi hệ thống!';
    header("Location: products.php?success=" . urlencode($success));
    exit;
}

// Nhận thông báo thành công sau khi chuyển hướng (nếu có)
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Lấy danh sách sản phẩm mới nhất đổ ra bảng
$products = $db->getAll("SELECT * FROM products ORDER BY product_id DESC");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Admin - Quản Lý Sản Phẩm</title>
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

        /* SIDEBAR MENU */
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
            border-left: 4px solid #0284c7;
            padding-left: 10px;
        }

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
            border-color: #0284c7;
        }

        .form-group input[readonly] {
            background: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
        }

        /* BẢNG DỮ LIỆU */
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

        /* CÁC NÚT BẤM */
        .btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 15px;
            transition: background 0.2s;
        }

        .btn:hover {
            background: #059669;
        }

        .btn-update {
            background: #0284c7;
        }

        .btn-update:hover {
            background: #0369a1;
        }

        .btn-cancel {
            background: #64748b;
            margin-left: 5px;
            text-decoration: none;
            display: inline-block;
            padding: 10px 15px;
            color: white;
            border-radius: 6px;
            font-weight: bold;
            font-size: 14px;
        }

        .btn-cancel:hover {
            background: #475569;
        }

        .action-links a {
            text-decoration: none;
            font-weight: bold;
            font-size: 13px;
            margin-right: 10px;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .lnk-edit {
            color: #0284c7;
            background: #e0f2fe;
        }

        .lnk-edit:hover {
            background: #bae6fd;
        }

        .lnk-delete {
            color: #dc2626;
            background: #fee2e2;
        }

        .lnk-delete:hover {
            background: #fca5a5;
        }

        /* THÔNG BÁO */
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
        <a href="products.php" class="active">🏷️ Quản lý sản phẩm</a>
        <a href="users.php">👥 Quản lý người dùng</a>
        <a href="reports.php">⚠️ Quản lý khiếu nại</a>
        <a href="../index.php" style="margin-top: 80px; background: #b91c1c; text-align: center; color: white;">Trang
            chủ User</a>
    </div>

    <div class="main-content">
        <div class="header-panel">
            <h2>Quản Lý Danh Sách Sản Phẩm</h2>
            <a href="index.php" style="text-decoration:none; color:#0284c7; font-weight:bold;">← Quay lại Dashboard</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card">
            <?php if ($edit_product): ?>
                <h3>✏️ Cập Nhật Thông Tin Sản Phẩm</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_product">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Mã sản phẩm (Không thể sửa)</label>
                            <input type="text" name="product_id"
                                value="<?= htmlspecialchars($edit_product['product_id']) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Tên sản phẩm mới</label>
                            <input type="text" name="description"
                                value="<?= htmlspecialchars($edit_product['description']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Giá tiền</label>
                            <input type="text" name="price" value="<?= htmlspecialchars($edit_product['price']) ?>"
                                required>
                        </div>
                        <div class="form-group">
                            <label>Đường dẫn hình ảnh (URL)</label>
                            <input type="text" name="image" value="<?= htmlspecialchars($edit_product['image']) ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-update">💾 Lưu thay đổi</button>
                    <a href="products.php" class="btn-cancel">Hủy bỏ</a>
                </form>
            <?php else: ?>
                <h3>➕ Thêm Sản Phẩm Mới</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_product">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Mã sản phẩm (Duy nhất)</label>
                            <input type="text" name="product_id" placeholder="Ví dụ: SP001" required>
                        </div>
                        <div class="form-group">
                            <label>Tên sản phẩm</label>
                            <input type="text" name="description" placeholder="Tên thiết bị..." required>
                        </div>
                        <div class="form-group">
                            <label>Giá bán hiển thị</label>
                            <input type="text" name="price" placeholder="Ví dụ: 15.000.000đ" required>
                        </div>
                        <div class="form-group">
                            <label>Ảnh sản phẩm (Đường dẫn URL)</label>
                            <input type="text" name="image" placeholder="https://...">
                        </div>
                    </div>
                    <button type="submit" class="btn">➕ Thêm vào hệ thống</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Danh Sách Sản Phẩm Hiện Có</h3>
            <table>
                <tr>
                    <th style="width: 80px;">Hình Ảnh</th>
                    <th>Mã SP</th>
                    <th>Tên Sản Phẩm</th>
                    <th>Giá Bán</th>
                    <th style="text-align: center; width: 150px;">Hành Động</th>
                </tr>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td>
                            <img src="<?= htmlspecialchars($p['image']) ?>" width="50" height="50"
                                style="object-fit:cover; border-radius:6px; background:#f1f5f9;"
                                onerror="this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=100'">
                        </td>
                        <td><strong><?= htmlspecialchars($p['product_id']) ?></strong></td>
                        <td><?= htmlspecialchars($p['description']) ?></td>
                        <td style="color:#dc2626; font-weight:bold;"><?= htmlspecialchars($p['price']) ?></td>
                        <td class="action-links" style="text-align: center;">
                            <a href="products.php?edit_id=<?= urlencode($p['product_id']) ?>" class="lnk-edit">Sửa</a>
                            <a href="products.php?delete_id=<?= urlencode($p['product_id']) ?>" class="lnk-delete"
                                onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm mã [<?= htmlspecialchars($p['product_id']) ?>] không?')">Xóa</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>

</html>