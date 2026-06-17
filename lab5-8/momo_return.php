<?php
session_start();
require_once("config.php");
require_once("db_utils.php");

$db = new DB_UTILS();

// ── 1. Nhận tham số MoMo trả về ──────────────────────────────────────────────
$partnerCode = $_GET['partnerCode'] ?? '';
$orderId = $_GET['orderId'] ?? '';
$requestId = $_GET['requestId'] ?? '';
$amount = $_GET['amount'] ?? 0;
$orderInfo = $_GET['orderInfo'] ?? '';
$orderType = $_GET['orderType'] ?? '';
$transId = $_GET['transId'] ?? '';
$resultCode = $_GET['resultCode'] ?? '-1';
$message = $_GET['message'] ?? '';
$payType = $_GET['payType'] ?? '';
$responseTime = $_GET['responseTime'] ?? '';
$extraData = $_GET['extraData'] ?? '';
$signature = $_GET['signature'] ?? '';

// ── 2. Xác minh chữ ký ───────────────────────────────────────────────────────
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
$validSig = hash_equals($expectedSig, $signature);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Kết Quả Thanh Toán MoMo</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .card {
            background: white;
            max-width: 480px;
            width: 100%;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .icon {
            font-size: 56px;
            margin-bottom: 16px;
        }

        h2 {
            font-size: 22px;
            margin-bottom: 10px;
        }

        .info {
            background: #f8fafc;
            border-radius: 8px;
            padding: 14px;
            margin: 20px 0;
            text-align: left;
            font-size: 14px;
            line-height: 2;
        }

        .info span {
            font-weight: bold;
        }

        a.btn {
            display: inline-block;
            margin-top: 10px;
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            font-size: 15px;
        }

        .btn-home {
            background: #2563eb;
            color: white;
        }

        .btn-order {
            background: #10b981;
            color: white;
            margin-left: 10px;
        }

        .success h2 {
            color: #059669;
        }

        .fail h2 {
            color: #dc2626;
        }

        .invalid h2 {
            color: #92400e;
        }
    </style>
</head>

<body>
    <div class="card">
        <?php if (!$validSig): ?>
            <div class="invalid">
                <div class="icon">⚠️</div>
                <h2>Chữ ký không hợp lệ!</h2>
                <p style="color:#78716c; margin-top:8px;">Phản hồi từ MoMo có thể đã bị giả mạo.</p>
            </div>

        <?php elseif ($resultCode == '0'): ?>
            <?php
            // Thanh toán thành công → cập nhật DB
            $db->execute(
                "UPDATE orders SET status = 'Đã thanh toán' WHERE order_id = ?",
                [$orderId]
            );
            ?>
            <div class="success">
                <div class="icon">✅</div>
                <h2>Thanh toán MoMo thành công!</h2>
                <div class="info">
                    <div>Mã đơn hàng: <span>
                            <?= htmlspecialchars($orderId) ?>
                        </span></div>
                    <div>Số tiền: <span>
                            <?= number_format((int) $amount, 0, ',', '.') ?>đ
                        </span></div>
                    <div>Mã giao dịch MoMo: <span>
                            <?= htmlspecialchars($transId) ?>
                        </span></div>
                    <div>Trạng thái: <span style="color:#059669">Đã thanh toán</span></div>
                </div>
            </div>

        <?php else: ?>
            <?php
            // Thanh toán thất bại → cập nhật DB
            $db->execute(
                "UPDATE orders SET status = 'Thanh toán thất bại' WHERE order_id = ?",
                [$orderId]
            );
            ?>
            <div class="fail">
                <div class="icon">❌</div>
                <h2>Thanh toán MoMo thất bại!</h2>
                <div class="info">
                    <div>Mã đơn hàng: <span>
                            <?= htmlspecialchars($orderId) ?>
                        </span></div>
                    <div>Lý do: <span>
                            <?= htmlspecialchars($message) ?>
                        </span></div>
                    <div>Mã lỗi: <span>
                            <?= htmlspecialchars($resultCode) ?>
                        </span></div>
                </div>
                <p style="color:#78716c; font-size:14px;">Đơn hàng đã được lưu, bạn có thể thử lại trong trang quản lý đơn
                    hàng.</p>
            </div>
        <?php endif; ?>

        <div style="margin-top:20px;">
            <a href="index.php" class="btn btn-home">🏠 Trang chủ</a>
            <a href="DonHang.php" class="btn btn-order">📦 Đơn hàng của tôi</a>
        </div>
    </div>
</body>

</html>