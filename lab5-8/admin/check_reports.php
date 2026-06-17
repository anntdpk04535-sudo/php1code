<?php
// admin/check_reports.php
session_start();
require_once "../db_utils.php";
$db = new DB_UTILS();

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    if (isset($_GET['user_id'])) {
        // Đối với User: Trả về trạng thái và note mới nhất của tất cả các đơn report của họ
        $uid = (int)$_GET['user_id'];
        $my_reports = $db->getAll("SELECT id, status, admin_note FROM reports WHERE user_id = ?", [$uid]);
        echo json_encode(['status' => 'success', 'data' => $my_reports]);
    } else {
        // Đối với Admin: Trả về tổng số lượng bản ghi của hệ thống
        $total_reports = $db->getOne("SELECT COUNT(*) as total FROM reports")['total'] ?? 0;
        echo json_encode(['status' => 'success', 'total' => (int)$total_reports]);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error']);
}