<?php
// THÊM ĐOẠN KHỞI TẠO SESSION NÀY VÀO ĐẦU FILE users-manage.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_utils.php';
$db = new DB_UTILS();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Từ chối truy cập không hợp lệ.");
}

// Giữ nguyên đoạn xử lý toggle_status và HTML phía dưới của bạn...

if (isset($_GET['toggle_status']) && isset($_GET['uid'])) {
    $uid = (int)$_GET['uid'];
    $current_status = $db->getValue("SELECT status FROM users WHERE id = ?", [$uid]);
    $new_status = $current_status == 1 ? 0 : 1;
    $db->execute("UPDATE users SET status = ? WHERE id = ?", [$new_status, $uid]);
    header("Location: users-manage.php");
    exit;
}

$all_users = $db->getAll("SELECT id, username, fullname, email, role, status, created_at FROM users ORDER BY id DESC");
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quản lý tài khoản</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="bg-light">
  <div class="container py-5">
     <div class="d-flex justify-content-between align-items-center mb-4">
         <h2 class="fw-bold m-0">Quản lý danh sách tài khoản</h2>
         <a href="index.php" class="btn btn-secondary">Quay về xem Sản phẩm</a>
     </div>
     <div class="card border-0 shadow-sm p-3">
         <table class="table table-hover align-middle">
             <thead class="table-dark">
                 <tr>
                     <th>ID</th>
                     <th>Tài khoản</th>
                     <th>Họ tên</th>
                     <th>Email</th>
                     <th>Quyền</th>
                     <th>Trạng thái</th>
                     <th>Thao tác</th>
                 </tr>
             </thead>
             <tbody>
                 <?php foreach($all_users as $u): ?>
                 <tr>
                     <td><?= $u['id'] ?></td>
                     <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                     <td><?= htmlspecialchars($u['fullname']) ?></td>
                     <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
    <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-primary' ?>">
        <?= htmlspecialchars($u['role']) ?>
    </span>
</td>
                     <td><span class="badge <?= $u['status']==1?'bg-success':'bg-danger' ?>"><?= $u['status']==1?'Active':'Locked' ?></span></td>
                     <td>
                         <?php if($u['username'] !== 'admin'): ?>
                             <a href="users-manage.php?toggle_status=1&uid=<?= $u['id'] ?>" class="btn btn-sm <?= $u['status']==1?'btn-warning':'btn-success' ?>">
                                 <?= $u['status'] == 1 ? 'Khóa' : 'Mở khóa' ?>
                             </a>
                         <?php else: ?>
                             <span class="text-muted small">Mặc định</span>
                         <?php endif; ?>
                     </td>
                 </tr>
                 <?php endforeach; ?>
             </tbody>
         </table>
     </div>
  </div>
</body>
</html>