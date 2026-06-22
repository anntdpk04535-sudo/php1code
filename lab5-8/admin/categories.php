<?php
session_start();
require_once "../db_utils.php";
$db = new DB_UTILS();

// Chặn bảo mật: chỉ admin mới vào được
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$error = '';
$success = '';
$edit_cat = null;

// 1. LẤY DANH MỤC CẦN SỬA
if (isset($_GET['edit_id'])) {
    $edit_cat = $db->getOne("SELECT * FROM categories WHERE category_id = ?", [(int)$_GET['edit_id']]);
}

// 2. XỬ LÝ FORM POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // THÊM DANH MỤC
    if ($_POST['action'] === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if (empty($name) || empty($slug)) {
            $error = 'Tên danh mục và slug không được để trống!';
        } else {
            $check = $db->getOne("SELECT category_id FROM categories WHERE slug = ?", [$slug]);
            if ($check) {
                $error = "Slug '<strong>$slug</strong>' đã tồn tại! Vui lòng dùng slug khác.";
            } else {
                $db->execute(
                    "INSERT INTO categories (name, slug, icon, description) VALUES (?, ?, ?, ?)",
                    [$name, $slug, $icon, $desc]
                );
                header("Location: categories.php?success=" . urlencode("Thêm danh mục thành công!"));
                exit;
            }
        }
    }

    // CẬP NHẬT DANH MỤC
    if ($_POST['action'] === 'update_category') {
        $id   = (int)$_POST['category_id'];
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if (empty($name) || empty($slug)) {
            $error = 'Tên danh mục và slug không được để trống!';
        } else {
            $db->execute(
                "UPDATE categories SET name = ?, slug = ?, icon = ?, description = ? WHERE category_id = ?",
                [$name, $slug, $icon, $desc, $id]
            );
            header("Location: categories.php?success=" . urlencode("Cập nhật danh mục thành công!"));
            exit;
        }
    }
}

// 3. XÓA DANH MỤC
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    // Đặt lại category_id cho sản phẩm thuộc danh mục bị xóa (về NULL)
    $db->execute("UPDATE products SET category_id = NULL WHERE category_id = ?", [$del_id]);
    $db->execute("DELETE FROM categories WHERE category_id = ?", [$del_id]);
    header("Location: categories.php?success=" . urlencode("Đã xóa danh mục thành công!"));
    exit;
}

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

$categories = $db->getAll("
    SELECT c.*, COUNT(p.product_id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.category_id
    GROUP BY c.category_id
    ORDER BY c.category_id ASC
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechShop Admin — Quản Lý Danh Mục</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #0a0d12; --bg-soft: #0d1117; --surface: #12161d; --surface-2: #161b23;
            --border: #232a35; --border-soft: #1a2028; --text: #e7ebf0; --text-dim: #8993a4; --text-faint: #565f70;
            --accent: #00e6c3; --accent-strong: #2dffd6; --accent-dim: rgba(0,230,195,0.12); --accent-border: rgba(0,230,195,0.35); --accent-glow: rgba(0,230,195,0.25);
            --gold: #d8b87a; --gold-strong: #eccb8f;
            --warn: #ffb454; --warn-dim: rgba(255,180,84,0.12);
            --admin: #4ee6a8;
            --blue: #63b3ed; --blue-dim: rgba(99,179,237,0.12);
            --danger: #ff5e72; --danger-dim: rgba(255,94,114,0.14);
            --radius-lg: 18px; --radius-md: 12px; --radius-sm: 8px;
            --font-display: 'Space Grotesk', sans-serif;
            --font-body: 'Inter', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
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

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 260px;
            background: var(--surface);
            border-right: 1px solid var(--border-soft);
            color: var(--text);
            padding: 24px 18px;
            position: fixed;
            height: 100vh;
            top: 0; left: 0;
            z-index: 100;
            display: flex;
            flex-direction: column;
        }

        .sidebar h2 {
            font-family: var(--font-display);
            font-size: 16px; font-weight: 700;
            margin-bottom: 26px; text-align: center;
            border-bottom: 1px solid var(--border-soft);
            padding-bottom: 18px; color: var(--text);
            display: flex; align-items: center; justify-content: center;
            gap: 9px; letter-spacing: -0.01em;
        }
        .sidebar h2 i { color: var(--accent); filter: drop-shadow(0 0 6px var(--accent-glow)); }

        .sidebar a {
            display: flex; align-items: center; gap: 10px;
            color: var(--text-dim); text-decoration: none;
            padding: 12px 14px; border-radius: var(--radius-sm);
            margin-bottom: 6px; font-weight: 500; font-size: 14px;
            transition: all 0.2s ease;
        }
        .sidebar a:hover { background: var(--surface-2); color: var(--text); }
        .sidebar a.active { background: var(--accent-dim); color: var(--accent); border: 1px solid var(--accent-border); font-weight: 600; }
        .sidebar a.lnk-exit {
            margin-top: auto;
            background: var(--danger-dim); color: var(--danger);
            justify-content: center; border: 1px solid var(--danger); font-weight: 600;
        }
        .sidebar a.lnk-exit:hover { background: rgba(255, 94, 114, 0.22); }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 260px; flex: 1;
            padding: 28px 30px 40px;
            position: relative; z-index: 1;
        }

        /* ===== HEADER PANEL ===== */
        .header-panel {
            background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%);
            padding: 18px 24px; border-radius: var(--radius-lg);
            border: 1px solid var(--border); margin-bottom: 24px;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 12px;
        }
        .header-panel h2 { font-family: var(--font-display); font-size: 19px; font-weight: 700; color: var(--text); }
        .header-panel a { color: var(--accent); font-weight: 600; text-decoration: none; font-size: 13.5px; display: inline-flex; align-items: center; gap: 6px; }
        .header-panel a:hover { color: var(--accent-strong); }

        /* ===== ALERTS ===== */
        .alert { padding: 14px 16px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-danger  { background: var(--danger-dim); color: var(--danger); border: 1px solid var(--danger); }
        .alert-success { background: var(--accent-dim); color: var(--accent); border: 1px solid var(--accent-border); }

        /* ===== CARD ===== */
        .card {
            background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%);
            padding: 24px; border-radius: var(--radius-lg);
            border: 1px solid var(--border); margin-bottom: 24px;
        }
        .card h3 {
            margin-bottom: 18px; color: var(--text);
            font-family: var(--font-display); font-size: 16px; font-weight: 600;
            border-left: 3px solid var(--accent); padding-left: 12px;
            display: flex; align-items: center; gap: 10px;
        }

        /* ===== FORM ===== */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 13px; font-weight: 500; color: var(--text-dim); }
        .form-group input, .form-group textarea {
            padding: 11px 14px; border: 1px solid var(--border);
            border-radius: var(--radius-sm); outline: none;
            font-size: 14px; background: var(--surface-2);
            color: var(--text); font-family: var(--font-body);
            transition: border-color 0.2s ease;
        }
        .form-group input::placeholder, .form-group textarea::placeholder { color: var(--text-faint); }
        .form-group input:focus, .form-group textarea:focus { border-color: var(--accent-border); }
        .form-group textarea { resize: vertical; min-height: 72px; }
        .form-group small { font-size: 11.5px; color: var(--text-faint); }

        /* ===== BUTTONS ===== */
        .btn {
            position: relative;
            background: var(--accent); color: var(--bg); border: none;
            padding: 11px 22px; border-radius: var(--radius-sm);
            cursor: pointer; font-weight: 700; font-family: var(--font-mono);
            font-size: 13.5px; margin-top: 15px;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s ease; overflow: hidden;
            box-shadow: 0 10px 22px -8px var(--accent-glow);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 14px 28px -8px var(--accent-glow); }
        .btn-update { background: var(--blue); color: #fff; box-shadow: 0 10px 22px -8px var(--blue-dim); }
        .btn-update:hover { background: #7ec3f2; }
        .btn-cancel {
            background: var(--surface-2); margin-left: 8px; text-decoration: none;
            display: inline-flex; align-items: center; gap: 7px;
            padding: 11px 18px; color: var(--text-dim);
            border: 1px solid var(--border); border-radius: var(--radius-sm);
            font-weight: 600; font-size: 13.5px; margin-top: 15px; transition: all 0.2s ease;
        }
        .btn-cancel:hover { color: var(--text); border-color: var(--border-soft); }

        /* ===== TABLE ===== */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 14px 12px; text-align: left; border-bottom: 1px solid var(--border-soft); font-size: 13.5px; }
        th { background: var(--bg-soft); color: var(--text-faint); font-weight: 600; font-family: var(--font-mono); font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.03em; }
        tr:hover td { background: rgba(255, 255, 255, 0.015); }
        tr:last-child td { border-bottom: none; }

        .cat-icon { font-size: 18px; color: var(--accent); }
        .cat-name { font-weight: 600; color: var(--text); }
        .cat-slug { font-family: var(--font-mono); font-size: 12px; color: var(--accent); background: var(--accent-dim); padding: 3px 9px; border-radius: 6px; border: 1px solid var(--accent-border); }
        .cat-count { font-family: var(--font-mono); font-size: 12px; color: var(--gold-strong); font-weight: 700; background: rgba(216,184,122,0.1); border: 1px solid rgba(216,184,122,0.25); padding: 3px 10px; border-radius: 999px; }

        .action-links { display: flex; gap: 8px; }
        .lnk-edit   { text-decoration: none; font-weight: 600; font-size: 12.5px; padding: 5px 11px; border-radius: var(--radius-sm); display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s ease; color: var(--blue); background: var(--blue-dim); border: 1px solid rgba(99, 179, 237, 0.3); }
        .lnk-edit:hover { background: rgba(99, 179, 237, 0.22); }
        .lnk-delete { text-decoration: none; font-weight: 600; font-size: 12.5px; padding: 5px 11px; border-radius: var(--radius-sm); display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s ease; color: var(--danger); background: var(--danger-dim); border: 1px solid var(--danger); }
        .lnk-delete:hover { background: rgba(255, 94, 114, 0.22); }

        .empty-row td { text-align: center; color: var(--text-faint); font-family: var(--font-mono); font-size: 13px; padding: 40px 0; }

        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
    </style>
</head>
<body>

<div class="sidebar">
    <h2><i class="fa-solid fa-screwdriver-wrench"></i> TECHSHOP ADMIN</h2>
    <a href="index.php"><i class="fa-solid fa-house"></i> Bảng Điều Khiển</a>
    <a href="orders.php"><i class="fa-solid fa-box"></i> Quản lý đơn hàng</a>
    <a href="products.php"><i class="fa-solid fa-tags"></i> Quản lý sản phẩm</a>
    <a href="categories.php" class="active"><i class="fa-solid fa-layer-group"></i> Quản lý danh mục</a>
    <a href="users.php"><i class="fa-solid fa-users"></i> Quản lý người dùng</a>
    <a href="reports.php"><i class="fa-solid fa-triangle-exclamation"></i> Quản lý khiếu nại</a>
    <a href="../index.php" class="lnk-exit"><i class="fa-solid fa-arrow-left"></i> Trang chủ User</a>
</div>

<div class="main-content">
    <div class="header-panel">
        <h2><i class="fa-solid fa-layer-group" style="color:var(--accent);margin-right:8px;"></i>Quản Lý Danh Mục</h2>
        <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Quay lại Dashboard</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= $error ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- FORM THÊM / SỬA -->
    <div class="card">
        <?php if ($edit_cat): ?>
            <h3><i class="fa-solid fa-pen"></i> Cập Nhật Danh Mục</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_category">
                <input type="hidden" name="category_id" value="<?= (int)$edit_cat['category_id'] ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Tên danh mục <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="name" value="<?= htmlspecialchars($edit_cat['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Slug (URL thân thiện) <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="slug" value="<?= htmlspecialchars($edit_cat['slug']) ?>" required>
                        <small>Ví dụ: laptop, dien-thoai, phu-kien (chữ thường, dùng dấu gạch ngang)</small>
                    </div>
                    <div class="form-group">
                        <label>Icon (Font Awesome class)</label>
                        <input type="text" name="icon" value="<?= htmlspecialchars($edit_cat['icon'] ?? '') ?>" placeholder="fa-solid fa-laptop">
                        <small>Xem tại <a href="https://fontawesome.com/icons" target="_blank" style="color:var(--accent)">fontawesome.com/icons</a></small>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Mô tả danh mục</label>
                        <textarea name="description"><?= htmlspecialchars($edit_cat['description'] ?? '') ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-update"><i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi</button>
                <a href="categories.php" class="btn-cancel"><i class="fa-solid fa-xmark"></i> Hủy bỏ</a>
            </form>
        <?php else: ?>
            <h3><i class="fa-solid fa-plus"></i> Thêm Danh Mục Mới</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_category">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Tên danh mục <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="name" placeholder="Ví dụ: Laptop, Điện thoại..." required>
                    </div>
                    <div class="form-group">
                        <label>Slug (URL thân thiện) <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="slug" id="slugInput" placeholder="Ví dụ: laptop, dien-thoai" required>
                        <small>Chữ thường, không dấu, dùng dấu gạch ngang. Tự tạo khi bạn nhập tên.</small>
                    </div>
                    <div class="form-group">
                        <label>Icon (Font Awesome class)</label>
                        <input type="text" name="icon" placeholder="fa-solid fa-laptop">
                        <small>Xem tại <a href="https://fontawesome.com/icons" target="_blank" style="color:var(--accent)">fontawesome.com/icons</a></small>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Mô tả danh mục</label>
                        <textarea name="description" placeholder="Mô tả ngắn về danh mục này..."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn"><i class="fa-solid fa-plus"></i> Thêm danh mục</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- DANH SÁCH DANH MỤC -->
    <div class="card">
        <h3><i class="fa-solid fa-list"></i> Danh Sách Danh Mục (<?= count($categories) ?>)</h3>
        <table>
            <tr>
                <th style="width:60px;">Icon</th>
                <th>Tên Danh Mục</th>
                <th>Slug</th>
                <th>Mô tả</th>
                <th style="width:110px;text-align:center;">Sản phẩm</th>
                <th style="width:150px;text-align:center;">Hành động</th>
            </tr>
            <?php if (empty($categories)): ?>
                <tr class="empty-row"><td colspan="6">Chưa có danh mục nào.</td></tr>
            <?php else: ?>
                <?php foreach ($categories as $c): ?>
                <tr>
                    <td class="cat-icon">
                        <?php if (!empty($c['icon'])): ?>
                            <i class="<?= htmlspecialchars($c['icon']) ?>"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-folder" style="color:var(--text-faint)"></i>
                        <?php endif; ?>
                    </td>
                    <td class="cat-name"><?= htmlspecialchars($c['name']) ?></td>
                    <td><span class="cat-slug"><?= htmlspecialchars($c['slug']) ?></span></td>
                    <td style="color:var(--text-dim);font-size:13px;max-width:260px;">
                        <?= htmlspecialchars($c['description'] ?? '—') ?>
                    </td>
                    <td style="text-align:center;">
                        <span class="cat-count"><?= (int)$c['product_count'] ?> SP</span>
                    </td>
                    <td>
                        <div class="action-links">
                            <a href="categories.php?edit_id=<?= (int)$c['category_id'] ?>" class="lnk-edit">
                                <i class="fa-solid fa-pen"></i> Sửa
                            </a>
                            <a href="categories.php?delete_id=<?= (int)$c['category_id'] ?>" class="lnk-delete"
                               onclick="return confirm('Xóa danh mục [<?= htmlspecialchars($c['name']) ?>]?\nCác sản phẩm thuộc danh mục này sẽ được đặt lại về Không phân loại.')">
                                <i class="fa-solid fa-trash"></i> Xóa
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</div>

<script>
// Tự động tạo slug từ tên danh mục
const nameInput = document.querySelector('input[name="name"]');
const slugInput = document.getElementById('slugInput');
if (nameInput && slugInput) {
    nameInput.addEventListener('input', function () {
        const val = this.value;
        const slug = val
            .toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd').replace(/Đ/g, 'd')
            .replace(/[^a-z0-9\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
        slugInput.value = slug;
    });
}
</script>

</body>
</html>