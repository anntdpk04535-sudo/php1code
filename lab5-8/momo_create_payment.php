<?php
session_start();
require_once("config.php");

if (empty($_GET['order_id']) || empty($_GET['amount'])) {
    die('Thiếu thông tin đơn hàng!');
}

$orderId = $_GET['order_id'];
$amount = (int) $_GET['amount'];
$orderInfo = "Thanh toan don hang " . $orderId;
$requestId = $momo_PartnerCode . time();
$extraData = "";
$requestType = "payWithMethod"; // Cho phép chọn ATM/QR/Visa...

// ── Tạo chữ ký HMAC SHA256 ───────────────────────────────────────────────────
$rawHash = "accessKey=" . $momo_AccessKey
    . "&amount=" . $amount
    . "&extraData=" . $extraData
    . "&ipnUrl=" . $momo_NotifyUrl
    . "&orderId=" . $orderId
    . "&orderInfo=" . $orderInfo
    . "&partnerCode=" . $momo_PartnerCode
    . "&redirectUrl=" . $momo_ReturnUrl
    . "&requestId=" . $requestId
    . "&requestType=" . $requestType;

$signature = hash_hmac('sha256', $rawHash, $momo_SecretKey);

// ── Build payload JSON ────────────────────────────────────────────────────────
$payload = json_encode([
    'partnerCode' => $momo_PartnerCode,
    'partnerName' => "Test Store",
    'storeId' => $momo_PartnerCode,
    'requestId' => $requestId,
    'amount' => $amount,
    'orderId' => $orderId,
    'orderInfo' => $orderInfo,
    'redirectUrl' => $momo_ReturnUrl,
    'ipnUrl' => $momo_NotifyUrl,
    'lang' => 'vi',
    'extraData' => $extraData,
    'requestType' => $requestType,
    'signature' => $signature,
]);

// ── Gửi request đến MoMo ─────────────────────────────────────────────────────
$ch = curl_init($momo_Endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload),
    ],
    CURLOPT_SSL_VERIFYPEER => false, // Sandbox: bỏ qua SSL
]);
$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    die("Lỗi kết nối MoMo: " . $error);
}

$result = json_decode($response, true);

// ── Redirect sang trang thanh toán MoMo ──────────────────────────────────────
if (isset($result['payUrl']) && !empty($result['payUrl'])) {
    header('Location: ' . $result['payUrl']);
    exit;
} else {
    $errMsg = $result['message'] ?? 'Không rõ lỗi';
    die("MoMo trả về lỗi: " . htmlspecialchars($errMsg));
}