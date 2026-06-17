<?php
// BẮT BUỘC: Phải có dòng này ở đầu tiên để kích hoạt Session lưu quyền Admin
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_utils.php';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['pass'];

    if (!empty($username) && !empty($password)) {
        $db = new DB_UTILS();
        $user = $db->getOne("SELECT * FROM users WHERE username = ?", [$username]);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] == 0) {
                $error = "Tài khoản này hiện đang bị khóa.";
            } else {
                // Lưu thông tin vào Session một cách chính xác
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'fullname' => $user['fullname'],
                    'role' => trim($user['role']) // Khử khoảng trắng thừa nếu có
                ];
                
                // Chuyển hướng thẳng về trang chủ
                header('Location: index.php');
                exit;
            }
        } else {
            $error = "Tên đăng nhập hoặc mật khẩu không hợp lệ.";
        }
    } else {
        $error = "Vui lòng nhập đầy đủ tài khoản và mật khẩu.";
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Đăng nhập - NovaMart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="style.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body class="auth-body">
  <main class="container py-5">
    <div class="auth-panel p-4 p-md-5 mx-auto" style="max-width:460px">
      <div class="text-center mb-4">
        <a class="navbar-brand fw-bold text-decoration-none" href="#">NovaMart</a>
        <h1 class="h4 fw-bold mt-2 text-secondary">Đăng nhập hệ thống</h1>
      </div>

      <?php if(!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>

      <form method="post" action="">
        <div class="mb-3">
          <label class="form-label fw-semibold">Tài khoản</label>
          <input type="text" name="username" class="form-control" placeholder="Nhập tên đăng nhập" required>
        </div>
        <div class="mb-3">
          <div class="d-flex justify-content-between">
            <label class="form-label fw-semibold">Mật khẩu</label>
            <a href="forgot-password.php" class="text-decoration-none small">Quên mật khẩu?</a>
          </div>
          <input type="password" name="pass" class="form-control" placeholder="Nhập mật khẩu" required>
        </div>
        <button type="submit" name="login" class="btn btn-primary w-100 mb-3">Đăng Nhập</button>
        <div class="text-center">
            <span class="text-muted">Chưa có tài khoản?</span> <a href="register.php" class="text-decoration-none fw-semibold">Đăng ký ngay</a>
        </div>
      </form>
    </div>
  </main>
</body>
</html>