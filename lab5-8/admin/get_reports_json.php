<?php
// admin/get_reports_json.php
session_start();
require_once "../db_utils.php";
$db = new DB_UTILS();

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $reports = $db->getAll("SELECT * FROM reports ORDER BY created_at DESC");
    echo json_encode(['status' => 'success', 'data' => $reports]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error']);
}