<?php
// 1. Khởi chạy session để kiểm tra quyền Admin
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Nạp lớp tiện ích database
require_once __DIR__ . '/db_utils.php';
$db = new DB_UTILS();

// 3. Kiểm tra nghiêm ngặt quyền truy cập của Admin
if (!isset($_SESSION['user']) || trim($_SESSION['user']['role']) !== 'admin') {
    die("Bạn không có quyền quản trị cấp cao để thực hiện thao tác này.");
}

// 4. Lấy các tham số từ URL xuống công khai
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$error = ''; $success = '';

// Khởi tạo các biến chứa dữ liệu form ban đầu
$name = ''; $category_id = ''; $price = ''; $quantity = ''; $description = ''; $image = '';

/* ==========================================================================
   XỬ LÝ ĐỔ DỮ LIỆU CŨ VÀO FORM KHI BẤM SỬA (Sửa lỗi khớp cả 'edit' và 'update')
   ========================================================================== */
if (($action === 'edit' || $action === 'update') && $id > 0) {
    $product = $db->getOne("SELECT * FROM products WHERE id = ?", [$id]);
    if ($product) {
        $name = $product['name'];
        $category_id = $product['category_id'];
        $price = $product['price'];
        $quantity = $product['quantity'];
        $description = $product['description'];
        $image = $product['image']; // Lưu lại đường dẫn ảnh cũ để phòng trường hợp user không đổi ảnh
    } else {
        $error = "Sản phẩm không tồn tại trên hệ thống.";
    }
}

/* ==========================================================================
   XỬ LÝ XÓA SẢN PHẨM
   ========================================================================== */
if ($action === 'delete' && $id > 0) {
    $db->execute("DELETE FROM products WHERE id = ?", [$id]);
    header("Location: index.php");
    exit;
}

/* ==========================================================================
   XỬ LÝ LƯU THÔNG TIN KHI SUBMIT FORM (INSERT / UPDATE)
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category_id = $_POST['category_id'] ?: null;
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];
    $description = trim($_POST['description']);
    $image_name = $image; // Mặc định giữ lại đường dẫn ảnh cũ

    // Xử lý upload file hình ảnh mới nếu người dùng chọn file
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $folder = 'uploads/';
            if (!file_exists(__DIR__ . '/' . $folder)) {
                mkdir(__DIR__ . '/' . $folder, 0777, true);
            }
            // Tạo tên ảnh độc nhất lưu vào thư mục
            $new_filename = time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/' . $folder . $new_filename)) {
                $image_name = $folder . $new_filename; // Lưu đầy đủ đường dẫn dạng: uploads/filename.jpg
            }
        } else {
            $error = "Định dạng file ảnh không được hệ thống hỗ trợ.";
        }
    }

    // Nếu không có lỗi, tiến hành cập nhật hoặc thêm mới vào Database
    if (empty($error)) {
        if ($id > 0) {
            // Chế độ UPDATE sản phẩm cũ
            $sql = "UPDATE products SET category_id = ?, name = ?, price = ?, quantity = ?, image = ?, description = ? WHERE id = ?";
            $db->execute($sql, [$category_id, $name, $price, $quantity, $image_name, $description, $id]);
            header("Location: index.php");
            exit;
        } else {
            // Chế độ CREATE sản phẩm mới
            $sql = "INSERT INTO products (category_id, name, price, quantity, image, description) VALUES (?, ?, ?, ?, ?, ?)";
            $db->execute($sql, [$category_id, $name, $price, $quantity, $image_name, $description]);
            header("Location: index.php");
            exit;
        }
    }
}

// Lấy danh sách danh mục đổ vào thẻ Select trong form
$categories = $db->getAll("SELECT * FROM categories");
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quản lý sản phẩm - Mini mart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="style.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="card p-4 shadow-sm border-0 mx-auto" style="max-width: 650px;">
      <h3 class="fw-bold mb-4 text-center"><?= $id > 0 ? 'Chỉnh sửa sản phẩm hiện tại' : 'Thêm mới sản phẩm' ?></h3>
      
      <?php if(!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>

      <form method="POST" action="" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label fw-semibold">Tên sản phẩm</label>
          <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Danh mục</label>
          <select name="category_id" class="form-select">
             <option value="">Chọn danh mục phù hợp</option>
             <?php foreach($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $category_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
             <?php endforeach; ?>
          </select>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Giá sản phẩm (VNĐ)</label>
                <input type="number" name="price" class="form-control" value="<?= htmlspecialchars($price) ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Số lượng trong kho</label>
                <input type="number" name="quantity" class="form-control" value="<?= htmlspecialchars($quantity) ?>" required>
            </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Hình ảnh sản phẩm</label>
          <input type="file" name="image" class="form-control mb-2">
          <?php if(!empty($image)): ?>
            <div class="text-muted small">Ảnh hiện tại:</div>
            <img src="<?= $image ?>" alt="Current image" style="max-height: 80px; class="mt-1 rounded border">
          <?php endif; ?>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold">Mô tả chi tiết</label>
          <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($description) ?></textarea>
        </div>
        <div class="d-flex justify-content-between">
            <a href="index.php" class="btn btn-outline-secondary">Quay về trang chủ</a>
            <button type="submit" class="btn btn-primary">Lưu thông tin sản phẩm</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>