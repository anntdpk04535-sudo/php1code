<?php
require_once __DIR__ . '/db_utils.php';
$db = new DB_UTILS();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Bạn không có quyền truy cập chức năng này.");
}

$error = ''; $success = '';
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$cat_name = '';

// 1. Xử lý Xóa Danh mục
if ($action === 'delete' && $id > 0) {
    // Kiểm tra xem danh mục có sản phẩm nào không trước khi xóa
    $count_prod = $db->getValue("SELECT COUNT(*) FROM products WHERE category_id = ?", [$id]);
    if ($count_prod > 0) {
        $error = "Không thể xóa! Danh mục này đang chứa sản phẩm.";
    } else {
        $db->execute("DELETE FROM categories WHERE id = ?", [$id]);
        header("Location: categories-manage.php");
        exit;
    }
}

// 2. Lấy thông tin danh mục khi ấn Sửa
if ($action === 'edit' && $id > 0) {
    $category = $db->getOne("SELECT * FROM categories WHERE id = ?", [$id]);
    if ($category) {
        $cat_name = $category['name'];
    }
}

// 3. Xử lý Thêm mới hoặc Cập nhật gửi lên từ Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cat_name = trim($_POST['name']);
    if (empty($cat_name)) {
        $error = "Vui lòng nhập tên danh mục.";
    } else {
        if ($id > 0) { // Chế độ Update
            $db->execute("UPDATE categories SET name = ? WHERE id = ?", [$cat_name, $id]);
            header("Location: categories-manage.php");
            exit;
        } else { // Chế độ Insert
            $db->execute("INSERT INTO categories (name) VALUES (?)", [$cat_name]);
            $success = "Thêm danh mục thành công!";
            $cat_name = '';
        }
    }
}

$categories = $db->getAll("SELECT * FROM categories ORDER BY id DESC");
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quản lý danh mục - NovaMart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold m-0">Quản lý Danh mục sản phẩm</h2>
        <a href="index.php" class="btn btn-secondary">Quay về Trang chủ</a>
    </div>

    <?php if(!empty($error)): ?> <div class="alert alert-danger"><?= $error ?></div> <?php endif; ?>
    <?php if(!empty($success)): ?> <div class="alert alert-success"><?= $success ?></div> <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card p-4 border-0 shadow-sm">
                <h4 class="fw-bold mb-3"><?= $id > 0 ? "Cập nhật danh mục" : "Thêm danh mục mới" ?></h4>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Tên danh mục</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($cat_name) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><?= $id > 0 ? "Cập nhật" : "Thêm mới" ?></button>
                    <?php if($id > 0): ?>
                        <a href="categories-manage.php" class="btn btn-link w-100 text-muted mt-2">Hủy bỏ</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card p-3 border-0 shadow-sm">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Tên danh mục</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($categories as $c): ?>
                        <tr>
                            <td><?= $c['id'] ?></td>
                            <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                            <td class="text-end">
                                <a href="categories-manage.php?action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-warning">Sửa</a>
                                <a href="categories-manage.php?action=delete&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa danh mục này?')">Xóa</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
  </div>
</body>
</html>