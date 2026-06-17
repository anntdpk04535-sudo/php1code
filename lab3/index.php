<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header('Location: login.php');
    exit; 
    }

// -- Lấy thông tin người dùng an toàn từ Session sau khi đã kiểm tra
$user_session = $_SESSION['user'];

require_once __DIR__ . '/db_utils.php';
$db = new DB_UTILS();

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 3; //-- Số lượng sản phẩm hiển thị tối đa trên một trang
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// -- Kiểm tra nếu người dùng có nhập từ khóa tìm kiếm
if (!empty($search)) {
    $count_sql = "SELECT COUNT(*) FROM products WHERE name LIKE ?";
    $data_sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.name LIKE ? ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
    $params = ["%$search%"];
} else {
    $count_sql = "SELECT COUNT(*) FROM products";
    $data_sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
    $params = [];
}

// -- Tính toán tổng số trang dựa trên dữ liệu thực tế trong Database
$total_products = $db->getValue($count_sql, $params);
$total_pages = ceil($total_products / $limit);
$products = $db->getAll($data_sql, $params);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mini mart - Danh sách sản phẩm</title>
  <!-- -- Tích hợp Bootstrap 5 và File CSS Custom làm đẹp giao diện -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="style.css" rel="stylesheet">
</head>
<body>
  <!-- -- Thanh Điều Hướng (Navbar) -->
  <nav class="navbar navbar-expand-lg main-navbar mb-4">
    <div class="container">
      <a class="navbar-brand fw-bold" href="index.php">Mini mart</a>
      <div class="ms-auto d-flex align-items-center">
        <!-- --  Hiển thị tên người dùng an toàn bằng htmlspecialchars để chống tấn công XSS -->
        <span class="me-3">Xin chào, <strong><?= htmlspecialchars($user_session['fullname']) ?></strong></span>
       <?php if(isset($user_session['role']) && $user_session['role'] === 'admin'): ?>
    <a href="categories-manage.php" class="btn btn-outline-primary btn-sm me-2">Quản lý Danh mục</a>
    <a href="users-manage.php" class="btn btn-outline-dark btn-sm me-2">Quản lý Tài khoản</a>
<?php endif; ?>
        <a href="logout.php" class="btn btn-danger btn-sm">Đăng xuất</a>
      </div>
    </div>
  </nav>

  <!-- -- Nội dung chính (Main Content) -->
  <main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold m-0">Sản phẩm hiện có</h2>
        <?php if(isset($user_session['role']) && $user_session['role'] === 'admin'): ?>
          <a href="product-crud.php?action=create" class="btn btn-primary">+ Thêm sản phẩm</a>
        <?php endif; ?>
    </div>

    <!-- -- Khối chức năng Tìm Kiếm Sản Phẩm -->
    <div class="card p-3 mb-4 border-0 shadow-sm">
      <form method="GET" action="index.php" class="row g-2">
        <div class="col-md-10">
          <input type="text" name="search" class="form-control" placeholder="Nhập tên sản phẩm để tìm kiếm..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-dark w-100">Tìm kiếm</button>
        </div>
      </form>
    </div>

    <!-- -- Vùng hiển thị Danh sách sản phẩm (Grid Card) -->
    <div class="row g-4">
  <?php if(count($products) > 0): ?>
  <?php foreach($products as $prod): ?>
    <div class="col-md-4 col-lg-3">
      <div class="card product-card">
       <?php 
  // 1. Lấy ra đường dẫn gốc trong database
  $db_image = trim($prod['image']);
  
  // 2. Kiểm tra các kịch bản của đường dẫn ảnh
  if (!empty($db_image) && file_exists($db_image)) {
      // Nếu trong DB lưu đầy đủ 'uploads/filename.jpg' và file đó có thật
      $img_path = $db_image;
  } else if (!empty($db_image) && file_exists('uploads/' . $db_image)) {
      // Nếu trong DB chỉ lưu 'filename.jpg' và file nằm trong thư mục uploads
      $img_path = 'uploads/' . $db_image;
  } else {
      // Nếu chuỗi rỗng hoặc file đã bị xóa mất, hiển thị ảnh mẫu e-commerce sạch sẽ
      $img_path = 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?w=500&auto=format&fit=crop&q=60';
  }
?>

        <div class="product-img-wrapper">
          <img src="<?= $img_path ?>" class="product-card-img" alt="Sản phẩm">
        </div>
        
        <div class="card-body">
          <span class="badge bg-modern-cate mb-2 align-self-start">
            <?= htmlspecialchars($prod['category_name'] ?? 'Mặc định') ?>
          </span>
          
          <h5 class="product-title">
            <?= htmlspecialchars($prod['name']) ?>
          </h5>
          
          <p class="product-price">
            <?= number_format($prod['price'], 0, ',', '.') ?>đ
          </p>
          <p class="product-stock">
            Kho: <?= $prod['quantity'] ?>
          </p>
          
          <?php if(isset($user_session['role']) && trim($user_session['role']) == 'admin'): ?>
            <div class="mt-auto pt-2 border-top d-flex justify-content-between">
              <a href="product-crud.php?action=edit&id=<?= $prod['id'] ?>" class="btn btn-sm btn-outline-warning">Sửa</a>
              <a href="product-crud.php?action=delete&id=<?= $prod['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bạn có thực sự muốn xóa sản phẩm này?')">Xóa</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="col-12 text-center py-5">
      <p class="text-muted fs-5">Không tìm thấy bất kỳ kết quả phù hợp nào.</p>
  </div>
<?php endif; ?>

    <!-- -- Thanh số Phân Trang (Pagination) -->
    <?php if ($total_pages > 1): ?>
      <nav class="mt-5">
        <ul class="pagination justify-content-center">
          <!-- -- Nút trang trước -->
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="index.php?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Trước</a>
          </li>
          
          <!-- -- Vòng lặp hiển thị danh sách số trang -->
          <?php for($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= $page == $i ? 'active' : '' ?>">
              <a class="page-link" href="index.php?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          
          <!-- -- Nút trang kế tiếp -->
          <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link" href="index.php?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Kế tiếp</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  </main>
</body>
</html>