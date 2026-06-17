<?php
// get_status.php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=lab4;charset=utf8", "root", "");
    if (isset($_GET['order_id'])) {
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE order_id = ?");
        $stmt->execute([$_GET['order_id']]);
        $status = $stmt->fetchColumn();
        echo json_encode(['status' => $status]);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error']);
}
?>