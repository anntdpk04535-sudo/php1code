<?php
require_once __DIR__ . '/db_utils.php';
$error = ''; $success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['forgot'])) {
    $db = new DB_UTILS();
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];

    if (!empty($username) && !empty($email) && !empty($new_password)) {
        // Xác thực xem user và email có khớp nhau không
        $user = $db->getOne("SELECT id FROM users WHERE username = ? AND email = ?", [$username, $email]);
        
        if ($user) {
            if (strlen($new_password) < 6) {
                $error = "Mật khẩu mới phải từ 6 ký tự trở lên.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $db->execute("UPDATE users SET password = ? WHERE id = ?", [$hashed_password, $user['id']]);
                $success = "Đổi mật khẩu thành công! Bạn sẽ được chuyển hướng về trang đăng nhập.";
                header("refresh:2;url=login.php");
            }
        } else {
            $error = "Thông tin tài khoản hoặc email không khớp với hệ thống.";
        }
    } else {
        $error = "Vui lòng nhập đầy đủ thông tin.";
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quên mật khẩu - Mini mart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
  <main class="card p-4 shadow-sm border-0 w-100" style="max-width: 450px;">
      <h3 class="fw-bold text-center mb-3 text-primary">Khôi phục mật khẩu</h3>
      
      <?php if(!empty($error)): ?> <div class="alert alert-danger"><?= $error ?></div> <?php endif; ?>
      <?php if(!empty($success)): ?> <div class="alert alert-success"><?= $success ?></div> <?php endif; ?>

      <form method="post" action="">
        <div class="mb-3">
          <label class="form-label fw-semibold">Tên tài khoản cần lấy lại</label>
          <input type="text" name="username" class="form-control" placeholder="Nhập username" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Email đăng ký tương ứng</label>
          <input type="email" name="email" class="form-control" placeholder="Nhập email" required>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold">Mật khẩu mới</label>
          <input type="password" name="new_password" class="form-control" placeholder="Nhập mật khẩu mới" required>
        </div>
        <button type="submit" name="forgot" class="btn btn-primary w-100 mb-3">Đổi mật khẩu</button>
        <div class="text-center">
            <a href="login.php" class="text-decoration-none small fw-semibold">Quay lại Đăng nhập</a>
        </div>
      </form>
  </main>
</body>
</html>