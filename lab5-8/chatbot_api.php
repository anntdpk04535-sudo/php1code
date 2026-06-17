<?php
// chatbot_api.php
session_start();
require_once __DIR__ . "/db_utils.php";
$db = new DB_UTILS();

header('Content-Type: application/json; charset=utf-8');

// Nhận chuỗi văn bản từ phía giao diện gửi lên
$inputData = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($_POST['message'] ?? $inputData['message'] ?? '');

if (empty($userMessage)) {
    echo json_encode(['status' => 'success', 'reply' => 'Chào bạn! Tôi có thể giúp gì cho bạn hôm nay?']);
    exit;
}

// 1. CHUẨN HÓA CHUỖI VĂN BẢN ĐỂ REgEX LỌC SỐ CHÍNH XÁC
$cleanMessage = mb_strtolower($userMessage, 'UTF-8');
// Loại bỏ các từ ngữ gây nhiễu để gom cụm số lại gần nhau hơn
$cleanMessage = str_replace(['website', 'có', 'sản', 'phẩm', 'không', 'giá', 'tìm', 'loại'], '', $cleanMessage);

// Quy đổi đơn vị tiền tệ viết tắt thông dụng của người dùng về mốc số 000
$cleanMessage = str_replace(['tr triệu', 'triệu', 'tr'], '000000', $cleanMessage);
$cleanMessage = str_replace(['k', 'ngàn', 'nghìn'], '000', $cleanMessage);
// Xóa sạch hoàn toàn các dấu chấm, dấu phẩy, chữ đ và khoảng trắng thừa
$cleanMessage = str_replace(['.', ',', 'đ', '₫', 'đồng', ' '], '', $cleanMessage);

$reply = "";
$productsResult = [];

// 2. LOGIC 1: NHẬN DIỆN KHOẢNG GIÁ ĐỘNG (Ví dụ: "từ 100k đến 1 triệu")
if (preg_match('/(\d+)(?:đến|-|đếnkhoảng|—)(\d+)/', $cleanMessage, $matches)) {
    $minPrice = (int)$matches[1];
    $maxPrice = (int)$matches[2];
    
    if ($minPrice > $maxPrice) { $temp = $minPrice; $minPrice = $maxPrice; $maxPrice = $temp; }

    // ĐÃ SỬA: Thay thế REGEXP_REPLACE bằng hàm REPLACE loại bỏ dấu chấm tương thích tốt với MariaDB
    $query = "SELECT * FROM products WHERE CAST(REPLACE(price, '.', '') AS UNSIGNED) BETWEEN ? AND ?";
    $productsResult = $db->getAll($query, [$minPrice, $maxPrice]);
    
    $reply = "Trợ lý ảo tìm thấy các sản phẩm phù hợp có giá từ **" . number_format($minPrice, 0, ',', '.') . "đ** đến **" . number_format($maxPrice, 0, ',', '.') . "đ**:";
}
// LOGIC 2: LỌC GIÁ THẤP HƠN HOẶC BẰNG MỘT MỐC (Ví dụ: "dưới 250k")
elseif (preg_match('/(?:dưới|nhỏhơn|thấphơn)(\d+)/', $cleanMessage, $matches)) {
    $maxPrice = (int)$matches[1];
    
    // ĐÃ SỬA: Thay thế sang hàm REPLACE thông thường
    $query = "SELECT * FROM products WHERE CAST(REPLACE(price, '.', '') AS UNSIGNED) <= ?";
    $productsResult = $db->getAll($query, [$maxPrice]);
    
    $reply = "Dưới đây là các sản phẩm giá tốt dưới **" . number_format($maxPrice, 0, ',', '.') . "đ**:";
}
// LOGIC 3: LỌC GIÁ CAO HƠN HOẶC BẰNG MỘT MỐC (Ví dụ: "trên 1 triệu")
elseif (preg_match('/(?:trên|lớnhơn|caohơn|hơn)(\d+)/', $cleanMessage, $matches)) {
    $minPrice = (int)$matches[1];
    
    // ĐÃ SỬA: Thay thế sang hàm REPLACE thông thường
    $query = "SELECT * FROM products WHERE CAST(REPLACE(price, '.', '') AS UNSIGNED) >= ?";
    $productsResult = $db->getAll($query, [$minPrice]);
    
    $reply = "Đây là danh sách sản phẩm cao cấp có giá trên **" . number_format($minPrice, 0, ',', '.') . "đ**:";
}
// LOGIC 4: PHẢN HỒI THÔNG THƯỜNG KHÔNG CHỨA SỐ
else {
    $rawLow = mb_strtolower($userMessage, 'UTF-8');
    if (str_contains($rawLow, 'chào') || str_contains($rawLow, 'hi') || str_contains($rawLow, 'hello')) {
        $reply = "Xin chào! Mình là trợ lý thông minh TechShop 🤖 Bạn cần tìm sản phẩm trong khoảng giá nào cứ gõ cho mình biết nhé!";
    } else {
        $reply = "Hệ thống chưa bóc tách được tầm giá yêu cầu. Bạn hãy thử gõ câu hỏi chứa khoảng giá như: **'từ 5000 đến 1000000'** để mình hỗ trợ lọc tự động nha.";
    }
}

// Xuất chuỗi dữ liệu JSON
echo json_encode([
    'status' => 'success',
    'reply' => $reply,
    'products' => $productsResult
], JSON_UNESCAPED_UNICODE);
exit;