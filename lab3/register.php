<?php
require_once __DIR__ . '/db_utils.php';
$error = ''; $success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $db = new DB_UTILS();
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($fullname) || empty($email) || empty($username) || empty($password)) {
        $error = "Vui lòng điền toàn bộ thông tin bắt buộc.";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu tối thiểu phải từ 6 ký tự.";
    } else {
        $check = $db->getOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($check) {
            $error = "Tài khoản hoặc email này đã có người sử dụng.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, 'user')";
            if ($db->execute($sql, [$username, $fullname, $email, $hashed_password])) {
                $success = "Tạo tài khoản thành công! Đang chuyển hướng...";
                header("refresh:2;url=login.php");
            }
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Đăng ký - Mini mart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
  <main class="container py-5">
    <div class="auth-panel p-4 p-md-5 mx-auto" style="max-width:520px">
      <div class="text-center mb-4">
        <a class="navbar-brand fw-bold text-decoration-none" href="#">Mini mart</a>
        <h1 class="h4 fw-bold mt-2 text-secondary">Tạo tài khoản mới</h1>
      </div>
      
      <?php if(!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>
      <?php if(!empty($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="mb-3">
          <label class="form-label fw-semibold">Họ và tên</label>
          <input type="text" name="fullname" class="form-control" placeholder="Nhập họ tên" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Địa chỉ Email</label>
          <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Tên tài khoản</label>
          <input type="text" name="username" class="form-control" placeholder="Tên đăng nhập hệ thống" required>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold">Mật khẩu</label>
          <input type="password" name="password" class="form-control" placeholder="Tối thiểu 6 ký tự" required>
        </div>
        <button type="submit" name="register" class="btn btn-primary w-100 mb-3">Đăng Ký</button>
        <div class="text-center">
            <span class="text-muted">Đã có tài khoản?</span> <a href="login.php" class="text-decoration-none fw-semibold">Đăng nhập</a>
        </div>
      </form>
    </div>
  </main>
</body>
</html>