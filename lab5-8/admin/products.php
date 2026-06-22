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
        $stock = trim($_POST['stock'] ?? '');

        // Cho phép người dùng gõ giá chỉ bằng số thuần (không cần nhập dấu chấm phân cách)
        // Nếu lỡ dán giá có sẵn dấu chấm (vd: 15.000.000) thì vẫn xử lý được
        $priceDigits = str_replace('.', '', $price);

        if (empty($pid) || empty($desc) || $price === '' || $stock === '') {
            $error = 'Vui lòng điền đầy đủ các thông tin bắt buộc (bao gồm tồn kho)!';
        } elseif (!is_numeric($priceDigits) || (float) $priceDigits < 0) {
            // Giá phải là số và không được âm
            $error = 'Giá sản phẩm phải là một số không âm! (Chỉ cần nhập số, không cần gõ dấu chấm)';
        } elseif (!ctype_digit($stock)) {
            // ctype_digit chỉ chấp nhận số nguyên không âm (không dấu trừ, không thập phân)
            $error = 'Số lượng tồn kho phải là một số nguyên không âm!';
        } else {
            $stock = (int) $stock;
            // Định dạng lại giá theo kiểu 1.000.000 để hiển thị đẹp, người dùng không cần tự gõ dấu chấm
            $priceFormatted = number_format((float) $priceDigits, 0, ',', '.');
            // KIỂM TRA TRÙNG MÃ SẢN PHẨM (PRODUCT_ID)
            $check = $db->getOne("SELECT * FROM products WHERE product_id = ?", [$pid]);
            if ($check) {
                $error = "Lỗi: Mã sản phẩm '<strong>$pid</strong>' đã tồn tại trên hệ thống! Vui lòng dùng mã khác.";
            } else {
                $cat_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
                $db->execute("INSERT INTO products (product_id, description, price, image, stock, category_id) VALUES (?, ?, ?, ?, ?, ?)", [$pid, $desc, $priceFormatted, $image, $stock, $cat_id]);
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
        $stock = trim($_POST['stock'] ?? '');

        // Cho phép người dùng gõ giá chỉ bằng số thuần (không cần nhập dấu chấm phân cách)
        $priceDigits = str_replace('.', '', $price);

        if (empty($desc) || $price === '' || $stock === '') {
            $error = 'Không được để trống tên sản phẩm, giá bán và tồn kho!';
        } elseif (!is_numeric($priceDigits) || (float) $priceDigits < 0) {
            // Giá phải là số và không được âm
            $error = 'Giá sản phẩm phải là một số không âm! (Chỉ cần nhập số, không cần gõ dấu chấm)';
        } elseif (!ctype_digit($stock)) {
            $error = 'Số lượng tồn kho phải là một số nguyên không âm!';
        } else {
            $stock = (int) $stock;
            // Định dạng lại giá theo kiểu 1.000.000 để hiển thị đẹp, người dùng không cần tự gõ dấu chấm
            $priceFormatted = number_format((float) $priceDigits, 0, ',', '.');
            $cat_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $db->execute("UPDATE products SET description = ?, price = ?, image = ?, stock = ?, category_id = ? WHERE product_id = ?", [$desc, $priceFormatted, $image, $stock, $cat_id, $pid]);
            $success = 'Cập nhật thông tin sản phẩm thành công!';
            header("Location: products.php?success=" . urlencode($success));
            exit;
        }
    }

    // Tác vụ: NHẬP THÊM TỒN KHO (cộng dồn số lượng nhập vào kho hiện có, không cần vào form sửa)
    if ($_POST['action'] === 'restock') {
        $pid = trim($_POST['product_id'] ?? '');
        $addQty = trim($_POST['add_qty'] ?? '');

        if (empty($pid) || $addQty === '' || !ctype_digit($addQty) || (int) $addQty <= 0) {
            $error = 'Số lượng nhập kho phải là một số nguyên dương lớn hơn 0!';
        } else {
            $addQty = (int) $addQty;
            $db->execute("UPDATE products SET stock = stock + ? WHERE product_id = ?", [$addQty, $pid]);
            $success = "Đã nhập thêm $addQty sản phẩm vào kho thành công!";
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
$products = $db->getAll("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.category_id = p.category_id
    ORDER BY p.product_id DESC
");

// Lấy danh sách danh mục để hiện dropdown
$categories = $db->getAll("SELECT category_id, name FROM categories ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechShop Admin — Quản Lý Sản Phẩm</title>
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
        .form-group input:focus { border-color: var(--accent-border); }
        .form-group input[readonly] { background: var(--bg-soft); color: var(--text-faint); cursor: not-allowed; }

        /* BẢNG DỮ LIỆU */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 14px 12px; text-align: left; border-bottom: 1px solid var(--border-soft); font-size: 13.5px; }
        th { background: var(--bg-soft); color: var(--text-faint); font-weight: 600; font-family: var(--font-mono); font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.03em; }
        tr:hover td { background: rgba(255, 255, 255, 0.015); }

        /* CÁC NÚT BẤM */
        .btn {
            position: relative;
            background: var(--accent);
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
            overflow: hidden;
            box-shadow: 0 10px 22px -8px var(--accent-glow);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 14px 28px -8px var(--accent-glow); }

        .btn-update { background: var(--blue); box-shadow: 0 10px 22px -8px var(--blue-dim); }
        .btn-update:hover { background: #7ec3f2; }

        .btn-cancel {
            background: var(--surface-2);
            margin-left: 8px;
            text-decoration: none;
            display: inline-block;
            padding: 11px 18px;
            color: var(--text-dim);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 13.5px;
            margin-top: 15px;
            transition: all 0.2s ease;
        }
        .btn-cancel:hover { color: var(--text); border-color: var(--border-soft); }

        .action-links a {
            text-decoration: none;
            font-weight: 600;
            font-size: 12.5px;
            margin-right: 8px;
            padding: 5px 11px;
            border-radius: var(--radius-sm);
            display: inline-block;
            transition: all 0.2s ease;
        }

        .lnk-edit { color: var(--blue); background: var(--blue-dim); border: 1px solid rgba(99, 179, 237, 0.3); }
        .lnk-edit:hover { background: rgba(99, 179, 237, 0.22); }

        .lnk-delete { color: var(--danger); background: var(--danger-dim); border: 1px solid var(--danger); }
        .lnk-delete:hover { background: rgba(255, 94, 114, 0.22); }

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

        /* TỒN KHO */
        .stock-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 11.5px;
            font-family: var(--font-mono);
        }
        .stock-ok { background: var(--accent-dim); color: var(--accent); border: 1px solid var(--accent-border); }
        .stock-low { background: var(--warn-dim); color: var(--warn); border: 1px solid rgba(255, 180, 84, 0.4); }
        .stock-out { background: var(--danger-dim); color: var(--danger); border: 1px solid var(--danger); }

        .restock-form {
            margin-top: 8px;
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .restock-form input {
            width: 70px;
            padding: 6px 8px;
            font-size: 12px;
            background: var(--surface-2);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 6px;
            outline: none;
        }
        .restock-form input::placeholder { color: var(--text-faint); }
        .restock-form input:focus { border-color: var(--accent-border); }
        .restock-btn {
            border: 1px solid rgba(99, 179, 237, 0.3);
            background: var(--blue-dim);
            color: var(--blue);
            cursor: pointer;
            font-size: 11.5px;
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 6px;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .restock-btn:hover { background: rgba(99, 179, 237, 0.22); }

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
        <a href="products.php" class="active"><i class="fa-solid fa-tags"></i> Quản lý sản phẩm</a>
        <a href="categories.php"><i class="fa-solid fa-layer-group"></i> Quản lý danh mục</a>
        <a href="users.php"><i class="fa-solid fa-users"></i> Quản lý người dùng</a>
        <a href="reports.php"><i class="fa-solid fa-triangle-exclamation"></i> Quản lý khiếu nại</a>
        <a href="../index.php" class="lnk-exit"><i class="fa-solid fa-arrow-left"></i> Trang chủ User</a>
    </div>

    <div class="main-content">
        <div class="header-panel">
            <h2>Quản Lý Danh Sách Sản Phẩm</h2>
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Quay lại Dashboard</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= $error ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card">
            <?php if ($edit_product): ?>
                <h3><i class="fa-solid fa-pen"></i> Cập Nhật Thông Tin Sản Phẩm</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_product">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Mã sản phẩm</label>
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
                            <input type="number" name="price" min="0" step="1"
                                value="<?= (int) str_replace('.', '', $edit_product['price']) ?>"
                                placeholder="Ví dụ: 15000000" required>
                        </div>
                        <div class="form-group">
                            <label>Đường dẫn hình ảnh (URL)</label>
                            <input type="text" name="image" value="<?= htmlspecialchars($edit_product['image']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Số lượng tồn kho</label>
                            <input type="number" name="stock" min="0" step="1"
                                value="<?= htmlspecialchars($edit_product['stock'] ?? 0) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Danh mục</label>
                            <select name="category_id" style="padding:11px 14px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--surface-2);color:var(--text);font-size:14px;outline:none;">
                                <option value="">— Không phân loại —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['category_id'] ?>"
                                        <?= ($edit_product['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-update"><i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi</button>
                    <a href="products.php" class="btn-cancel">Hủy bỏ</a>
                </form>
            <?php else: ?>
                <h3><i class="fa-solid fa-plus"></i> Thêm Sản Phẩm Mới</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_product">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Mã sản phẩm</label>
                            <input type="text" name="product_id" placeholder="Ví dụ: SP001" required>
                        </div>
                        <div class="form-group">
                            <label>Tên sản phẩm</label>
                            <input type="text" name="description" placeholder="Tên thiết bị..." required>
                        </div>
                        <div class="form-group">
                            <label>Giá bán hiển thị</label>
                            <input type="number" name="price" min="0" step="1" placeholder="Ví dụ: 15000000" required>
                        </div>
                        <div class="form-group">
                            <label>Ảnh sản phẩm (Đường dẫn URL)</label>
                            <input type="text" name="image" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label>Số lượng tồn kho</label>
                            <input type="number" name="stock" min="0" step="1" placeholder="Ví dụ: 50" required>
                        </div>
                        <div class="form-group">
                            <label>Danh mục</label>
                            <select name="category_id" style="padding:11px 14px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--surface-2);color:var(--text);font-size:14px;outline:none;">
                                <option value="">— Không phân loại —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['category_id'] ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn"><i class="fa-solid fa-plus"></i> Thêm vào hệ thống</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-list"></i> Danh Sách Sản Phẩm Hiện Có</h3>
            <table>
                <tr>
                    <th style="width: 100px;">Hình Ảnh</th>
                    <th>Mã SP</th>
                    <th>Tên Sản Phẩm</th>
                    <th>Danh Mục</th>
                    <th>Giá Bán</th>
                    <th style="width: 160px;">Tồn Kho</th>
                    <th style="text-align: center; width: 150px;">Hành Động</th>
                </tr>
                <?php foreach ($products as $p):
                    $stock = (int) ($p['stock'] ?? 0);
                    $stockClass = $stock <= 0 ? 'stock-out' : ($stock < 10 ? 'stock-low' : 'stock-ok');
                    $stockLabel = $stock <= 0 ? 'Hết hàng' : $stock . ' sản phẩm';
                    ?>
                    <tr>
                        <td>
                            <img src="<?= htmlspecialchars($p['image']) ?>" width="50" height="50"
                                style="object-fit:cover; border-radius:8px; background:var(--surface-2); border: 1px solid var(--border-soft);"
                                onerror="this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=100'">
                        </td>
                        <td><strong style="color: var(--accent); font-family: var(--font-mono);"><?= htmlspecialchars($p['product_id']) ?></strong></td>
                        <td style="color: var(--text);"><?= htmlspecialchars($p['description']) ?></td>
                        <td>
                            <?php if (!empty($p['category_name'])): ?>
                                <span style="background:var(--accent-dim);color:var(--accent);border:1px solid var(--accent-border);padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:600;">
                                    <?= htmlspecialchars($p['category_name']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-faint);font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--gold-strong); font-weight:700; font-family: var(--font-mono);"><?= htmlspecialchars($p['price']) ?></td>
                        <td>
                            <span class="stock-badge <?= $stockClass ?>"><?= $stockLabel ?></span>
                            <form method="POST" class="restock-form">
                                <input type="hidden" name="action" value="restock">
                                <input type="hidden" name="product_id" value="<?= htmlspecialchars($p['product_id']) ?>">
                                <input type="number" name="add_qty" min="1" step="1" placeholder="SL nhập">
                                <button type="submit" class="restock-btn" title="Nhập thêm sản phẩm">
                                    <i class="fa-solid fa-box-archive"></i> Nhập kho
                                </button>
                            </form>
                        </td>
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