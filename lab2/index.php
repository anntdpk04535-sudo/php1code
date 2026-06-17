<?php
// 1. Khởi tạo session (Bắt buộc phải đặt ở đầu file)
session_start();

$message = "";
$errors = [];

// 2. Xử lý khi người dùng nhấn nút "Lưu thay đổi" (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Lấy dữ liệu từ form
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $dob      = $_POST['dob'] ?? '';
    $address  = trim($_POST['address'] ?? '');

    // 3. Validate dữ liệu
    if (empty($fullname)) {
        $errors['fullname'] = "Họ tên không được để trống.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Email không đúng định dạng.";
    }

    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors['phone'] = "Số điện thoại phải gồm 10 chữ số.";
    }

    // 4. Nếu không có lỗi -> Lưu vào Session
    if (empty($errors)) {
        $_SESSION['user_profile'] = [
            'fullname' => $fullname,
            'email'    => $email,
            'phone'    => $phone,
            'dob'      => $dob,
            'address'  => $address
        ];
        $message = "Cập nhật hồ sơ thành công!";
    }
}

// Lấy dữ liệu cũ từ session để hiển thị lên form (nếu có)
$data = $_SESSION['user_profile'] ?? [];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tài khoản của tôi - PHP</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5; display: flex; justify-content: center; padding: 40px; }
        .container { background: white; padding: 0; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); display: flex; width: 950px; overflow: hidden; }
        
        /* Sidebar */
        .sidebar { width: 250px; background-color: #fff; border-right: 1px solid #eee; padding: 20px 0; }
        .sidebar h3 { padding: 0 20px; font-size: 18px; margin-bottom: 20px; }
        .menu-item { padding: 12px 25px; cursor: pointer; color: #444; font-weight: 500; }
        .menu-item.active { background-color: #e7f0ff; color: #007bff; border-left: 4px solid #007bff; }

        /* Content */
        .content { flex-grow: 1; padding: 40px; }
        .content h4 { margin-top: 0; font-size: 18px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .input-group { margin-bottom: 10px; }
        label { display: block; margin-bottom: 8px; font-size: 14px; color: #666; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        input:focus { border-color: #3b82f6; outline: none; }
        .full-width { grid-column: span 2; }
        
        button { background-color: #2d6a4f; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 20px; transition: 0.3s; }
        button:hover { background-color: #1b4332; }

        /* Alert & Errors */
        .alert-success { background-color: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .error-text { color: #dc3545; font-size: 12px; margin-top: 5px; }
    </style>
</head>
<body>

<div class="container">
    <div class="sidebar">
        <h3>Tài khoản của tôi</h3>
        <div class="menu-item active">Hồ sơ</div>
        <div class="menu-item">Đơn hàng</div>
        <div class="menu-item">Giỏ hàng</div>
    </div>

    <div class="content">
        <h4>Thông tin cá nhân</h4>

        <?php if ($message): ?>
            <div class="alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="grid">
                <div class="input-group">
                    <label>Họ tên</label>
                    <input type="text" name="fullname" value="<?php echo htmlspecialchars($data['fullname'] ?? 'admin'); ?>">
                    <?php if(isset($errors['fullname'])): ?>
                        <div class="error-text"><?php echo $errors['fullname']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="input-group">
                    <label>Email</label>
                    <input type="text" name="email" value="<?php echo htmlspecialchars($data['email'] ?? ''); ?>">
                    <?php if(isset($errors['email'])): ?>
                        <div class="error-text"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="input-group">
                    <label>SĐT</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($data['phone'] ?? ''); ?>">
                    <?php if(isset($errors['phone'])): ?>
                        <div class="error-text"><?php echo $errors['phone']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="input-group">
                    <label>Sinh Nhật</label>
                    <input type="date" name="dob" value="<?php echo htmlspecialchars($data['dob'] ?? ''); ?>">
                </div>

                <div class="input-group full-width">
                    <label>Ảnh Cá Nhân</label>
                    <input type="file" name="avatar">
                </div>

                <div class="input-group full-width">
                    <label>Địa chỉ mặc định</label>
                    <input type="text" name="address" value="<?php echo htmlspecialchars($data['address'] ?? ''); ?>">
                </div>
            </div>
            
            <button type="submit">Lưu thay đổi</button>
        </form>
    </div>
</div>

</body>
</html>