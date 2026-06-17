<?php
/**
 * momo_ipn.php — MoMo IPN (server-to-server callback)
 * MoMo gọi URL này để xác nhận giao dịch ở phía server.
 */
require_once("config.php");
require_once("db_utils.php");

$db = new DB_UTILS();

// Nhận JSON từ MoMo
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid request']);
    exit;
}

$partnerCode = $data['partnerCode'] ?? '';
$orderId = $data['orderId'] ?? '';
$requestId = $data['requestId'] ?? '';
$amount = $data['amount'] ?? 0;
$orderInfo = $data['orderInfo'] ?? '';
$orderType = $data['orderType'] ?? '';
$transId = $data['transId'] ?? '';
$resultCode = $data['resultCode'] ?? '-1';
$message = $data['message'] ?? '';
$payType = $data['payType'] ?? '';
$responseTime = $data['responseTime'] ?? '';
$extraData = $data['extraData'] ?? '';
$signature = $data['signature'] ?? '';

// ── Xác minh chữ ký ──────────────────────────────────────────────────────────
$rawHash = "accessKey=" . $momo_AccessKey
    . "&amount=" . $amount
    . "&extraData=" . $extraData
    . "&message=" . $message
    . "&orderId=" . $orderId
    . "&orderInfo=" . $orderInfo
    . "&orderType=" . $orderType
    . "&partnerCode=" . $partnerCode
    . "&payType=" . $payType
    . "&requestId=" . $requestId
    . "&responseTime=" . $responseTime
    . "&resultCode=" . $resultCode
    . "&transId=" . $transId;

$expectedSig = hash_hmac('sha256', $rawHash, $momo_SecretKey);

header('Content-Type: application/json');

if (!hash_equals($expectedSig, $signature)) {
    echo json_encode(['message' => 'Invalid signature']);
    exit;
}

// Kiểm tra đơn hàng tồn tại
$order = $db->getOne("SELECT * FROM orders WHERE order_id = ?", [$orderId]);
if (!$order) {
    echo json_encode(['message' => 'Order not found']);
    exit;
}

// Chống xử lý trùng lặp
if ($order['status'] === 'Đã thanh toán') {
    echo json_encode(['message' => 'Already confirmed']);
    exit;
}

// Cập nhật trạng thái
if ($resultCode == '0') {
    $db->execute(
        "UPDATE orders SET status = 'Đã thanh toán' WHERE order_id = ?",
        [$orderId]
    );
} else {
    $db->execute(
        "UPDATE orders SET status = 'Thanh toán thất bại' WHERE order_id = ?",
        [$orderId]
    );
}

echo json_encode(['message' => 'OK']);
exit;