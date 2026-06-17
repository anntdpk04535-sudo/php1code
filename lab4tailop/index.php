<?php

// KẾT NỐI DATABASE
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "product";

$conn = new PDO(
    "mysql:host=$servername;dbname=$dbname;charset=utf8",
    $username,
    $password
);

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$errors = [];

// ======================
// THÊM SẢN PHẨM
// ======================

if (isset($_POST["add"])) {

    $productId = trim($_POST["productId"]);
    $description = trim($_POST["description"]);
    $price = trim($_POST["price"]);
    $image = trim($_POST["image"]);

    // VALIDATE

    if (empty($productId)) {
        $errors[] = "ID sản phẩm không được để trống";
    } else {
        // CHỨC NĂNG: CHECK TRÙNG MÃ SẢN PHẨM KHÔNG CHO THÊM
        $sqlCheck = "SELECT COUNT(*) FROM product WHERE ma_san_pham = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->execute([$productId]);
        if ($stmtCheck->fetchColumn() > 0) {
            $errors[] = "ID sản phẩm này đã tồn tại, vui lòng nhập ID khác";
        }
    }

    if (strlen($description) < 5) {
        $errors[] = "Mô tả phải lớn hơn 5 ký tự";
    }

    if (!is_numeric($price)) {
        $errors[] = "Giá phải là số";
    }

    if ($price <= 0) {
        $errors[] = "Giá phải lớn hơn 0";
    }

    if (!filter_var($image, FILTER_VALIDATE_URL)) {
        $errors[] = "Link hình ảnh không hợp lệ";
    }

    // INSERT DATABASE

    if (empty($errors)) {

        $sql = "INSERT INTO product
        (ma_san_pham, mo_ta, gia, hinh_anh)
        VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            $productId,
            $description,
            $price,
            $image
        ]);

        // FIX LỖI RELOAD TRANG: Chuyển hướng ngay sau khi thêm thành công để xóa dữ liệu POST
        header("Location: index.php?success=1");
        exit; // Dừng kịch bản tại đây
    }
}

// ======================
// XÓA SẢN PHẨM
// ======================

if (isset($_GET["delete"])) {

    $id = $_GET["delete"];

    $sql = "DELETE FROM product WHERE id = ?";

    $stmt = $conn->prepare($sql);

    $stmt->execute([$id]);

    header("Location: index.php");
    exit;
}

// ======================
// LẤY DỮ LIỆU CẦN SỬA
// ======================

$editProduct = null;

if (isset($_GET["edit"])) {

    $id = $_GET["edit"];

    $sql = "SELECT * FROM product WHERE id = ?";

    $stmt = $conn->prepare($sql);

    $stmt->execute([$id]);

    $editProduct = $stmt->fetch();
}

// ======================
// UPDATE SẢN PHẨM
// ======================

if (isset($_POST["update"])) {

    $id = $_POST["id"];

    $productId = trim($_POST["productId"]);
    $description = trim($_POST["description"]);
    $price = trim($_POST["price"]);
    $image = trim($_POST["image"]);
    
    // VALIDATE KHI UPDATE (Bổ sung nếu cần check trùng mã khi sửa)
    if (empty($productId)) {
        $errors[] = "ID sản phẩm không được để trống";
    } else {
        // Khi sửa, cho phép trùng với chính nó nhưng không được trùng với sản phẩm khác
        $sqlCheck = "SELECT COUNT(*) FROM product WHERE ma_san_pham = ? AND id != ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->execute([$productId, $id]);
        if ($stmtCheck->fetchColumn() > 0) {
            $errors[] = "ID sản phẩm này đã được sử dụng bởi sản phẩm khác";
        }
    }

    if (empty($errors)) {
        $sql = "UPDATE product
                SET ma_san_pham = ?,
                    mo_ta = ?,
                    gia = ?,
                    hinh_anh = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            $productId,
            $description,
            $price,
            $image,
            $id
        ]);

        header("Location: index.php");
        exit;
    }
}

// ======================
// HIỂN THỊ DANH SÁCH
// ======================

$sql = "SELECT * FROM product ORDER BY id DESC";

$stmt = $conn->prepare($sql);

$stmt->execute();

$products = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="vi">

<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sản phẩm</title>

    <style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
    }

    body {
        background: #f4f6f8;
        padding: 30px;
        color: #222;
    }

    h1,
    h2 {
        text-align: center;
        margin-bottom: 25px;
    }

    .product-form {
        max-width: 600px;
        margin: 0 auto 35px;
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: bold;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 15px;
    }

    .form-group textarea {
        min-height: 90px;
        resize: vertical;
    }

    button {
        width: 100%;
        padding: 12px;
        border: none;
        border-radius: 8px;
        color: #fff;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
    }

    .btn-add {
        background: #2563eb;
    }

    .btn-add:hover {
        background: #1d4ed8;
    }

    .btn-delete {
        margin-top: 12px;
        background: #dc2626;
    }

    .btn-delete:hover {
        background: #b91c1c;
    }

    .product-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        max-width: 1000px;
        margin: 0 auto;
    }

    .product-card {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
    }

    .product-card:hover {
        transform: translateY(-5px);
    }

    .product-card img {
        width: 100%;
        height: 180px;
        object-fit: cover;
    }

    .product-info {
        padding: 16px;
    }

    .product-id {
        font-size: 14px;
        color: #666;
        margin-bottom: 8px;
    }

    .product-description {
        font-size: 16px;
        margin-bottom: 12px;
        line-height: 1.5;
    }

    .product-price {
        font-size: 20px;
        font-weight: bold;
        color: #e63946;
    }
    .error {
    color: #dc2626;
    background: #fef2f2;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 14px;
    border: 1px solid #fee2e2;
}
    </style>
</head>

<body>

    <h1>Quản lý sản phẩm</h1>

    <form class="product-form" method="POST">

        <h2>
            <?= $editProduct ? "Cập nhật sản phẩm" : "Thêm sản phẩm" ?>
        </h2>

        <?php
        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo "<p class='error'>$error</p>";
            }
        }
        ?>

        <div class="form-group">
            <label>ID sản phẩm</label>
<input type="text" name="productId" value="<?= $editProduct['ma_san_pham'] ?? '' ?>">
        </div>

        <div class="form-group">
            <label>Mô tả</label>

            <textarea name="description"><?= $editProduct['mo_ta'] ?? '' ?></textarea>
        </div>

        <div class="form-group">
            <label>Giá</label>

            <input type="text" name="price" value="<?= $editProduct['gia'] ?? '' ?>">
        </div>

        <div class="form-group">
            <label>Hình ảnh</label>

            <input type="url" name="image" value="<?= $editProduct['hinh_anh'] ?? '' ?>">
        </div>

        <?php if ($editProduct) { ?>

        <input type="hidden" name="id" value="<?= $editProduct['id'] ?>">

        <button class="btn-add" type="submit" name="update">

            Cập nhật sản phẩm

        </button>

        <?php } else { ?>

        <button class="btn-add" type="submit" name="add">

            Thêm sản phẩm

        </button>

        <?php } ?>

    </form>

    <h2>Danh sách sản phẩm</h2>

    <div class="product-list">

        <?php foreach($products as $product) { ?>

        <div class="product-card">

            <img src="<?= $product['hinh_anh'] ?>" alt="">

            <div class="product-info">

                <p>
                    ID:
                    <?= $product['ma_san_pham'] ?>
                </p>

                <p>
                    Mô tả:
                    <?= $product['mo_ta'] ?>
                </p>

                <p class="product-price">
                    Giá:
                    <?= number_format($product['gia']) ?>đ
                </p>

                <a href="?delete=<?= $product['id'] ?>" onclick="return confirm('Bạn có chắc muốn xóa?')">

                    <button class="btn-delete">
                        Xóa
                    </button>

                </a>

                <a href="?edit=<?= $product['id'] ?>">

                    <button class="btn-add" style="margin-top:10px;">

                        Sửa

                    </button>

                </a>

            </div>

        </div>

        <?php } ?>

    </div>

</body>

</html>