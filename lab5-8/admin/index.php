<?php
session_start();
require_once "../db_utils.php";
$db = new DB_UTILS();

// CHẶN BẢO MẬT: Nếu không có quyền Admin, lập tức từ chối quyền truy cập
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// 1. ĐỌC DỮ LIỆU BỘ LỌC TỪ URL (GET) ĐỂ XỬ LÝ TRUY VẤN SQL ĐỘNG
$dateFrom = $_GET['dateFrom'] ?? '';
$dateTo = $_GET['dateTo'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$paymentFilter = $_GET['payment'] ?? 'all';
$searchBox = trim($_GET['search'] ?? '');
$groupBy = $_GET['groupBy'] ?? 'month'; 

// Xây dựng câu điều kiện WHERE tổng quát
$whereClauses = ["1=1"];
$params = [];

if (!empty($dateFrom)) {
    $whereClauses[] = "DATE(created_at) >= ?";
    $params[] = $dateFrom;
}
if (!empty($dateTo)) {
    $whereClauses[] = "DATE(created_at) <= ?";
    $params[] = $dateTo;
}
if ($statusFilter !== 'all') {
    $whereClauses[] = "status = ?";
    $params[] = $statusFilter;
}
if ($paymentFilter !== 'all') {
    $whereClauses[] = "payment = ?";
    $params[] = $paymentFilter;
}
if (!empty($searchBox)) {
    $whereClauses[] = "(order_id LIKE ? OR fullname LIKE ? OR phone LIKE ?)";
    $likeStr = "%$searchBox%";
    $params[] = $likeStr; $params[] = $likeStr; $params[] = $likeStr;
}

$whereSql = implode(" AND ", $whereClauses);

// TRUY VẤN SỐ LIỆU ĐỂ ĐỔ VÀO BỘ LỌC DÙNG CHUNG
$all_statuses = $db->getAll("SELECT DISTINCT status FROM orders WHERE status IS NOT NULL");
$all_payments = $db->getAll("SELECT DISTINCT payment FROM orders WHERE payment IS NOT NULL");

// =========================================================================
// 2. XÂY DỰNG MẢNG ĐIỀU KIỆN RIÊNG CHO ĐƠN "HOÀN TẤT"
// =========================================================================
$whereNetClauses = ["status = 'Hoàn tất'"];
$netParams = [];

if (!empty($dateFrom)) { $whereNetClauses[] = "DATE(created_at) >= ?"; $netParams[] = $dateFrom; }
if (!empty($dateTo)) { $whereNetClauses[] = "DATE(created_at) <= ?"; $netParams[] = $dateTo; }
if ($paymentFilter !== 'all') { $whereNetClauses[] = "payment = ?"; $netParams[] = $paymentFilter; }
if (!empty($searchBox)) {
    $whereNetClauses[] = "(order_id LIKE ? OR fullname LIKE ? OR phone LIKE ?)";
    $netParams[] = $likeStr; $netParams[] = $likeStr; $netParams[] = $likeStr;
}
$whereNetSql = implode(" AND ", $whereNetClauses);

// =========================================================================
// 3. TÍNH TOÁN CÁC CHỈ SỐ KPI NÂNG CAO SAU KHI LỌC DỮ LIỆU
// =========================================================================
$total_orders_count = $db->getOne("SELECT COUNT(*) as total FROM orders WHERE $whereSql", $params)['total'] ?? 0;
$gross_revenue = $db->getOne("SELECT SUM(total) as total FROM orders WHERE $whereSql", $params)['total'] ?? 0;
$net_revenue = $db->getOne("SELECT SUM(total) as total FROM orders WHERE $whereNetSql", $netParams)['total'] ?? 0;
$success_orders_count = $db->getOne("SELECT COUNT(*) as total FROM orders WHERE status = 'Hoàn tất' AND $whereSql", $params)['total'] ?? 0;

$estimated_profit = $net_revenue * 0.45;
$bad_orders_count = $db->getOne("SELECT COUNT(*) as total FROM orders WHERE status IN ('Đã hủy', 'Hoàn tiền') AND $whereSql", $params)['total'] ?? 0;
$bad_rate = $total_orders_count > 0 ? ($bad_orders_count / $total_orders_count * 100) : 0;
$aov = $success_orders_count > 0 ? ($net_revenue / $success_orders_count) : 0;

$total_units_sold = $db->getOne("
    SELECT SUM(oi.quantity) as total 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    WHERE o.status = 'Hoàn tất' AND $whereSql", $params)['total'] ?? 0;

$repeat_customers_query = $db->getAll("
    SELECT phone, COUNT(order_id) as order_cnt 
    FROM orders 
    WHERE status = 'Hoàn tất' AND $whereSql
    GROUP BY phone", $params);

$total_unique_customers = count($repeat_customers_query);
$repeat_customers_count = 0;
foreach ($repeat_customers_query as $c) {
    if ($c['order_cnt'] >= 2) { $repeat_customers_count++; }
}
$repeat_rate = $total_unique_customers > 0 ? ($repeat_customers_count / $total_unique_customers * 100) : 0;

// =========================================================================
// 4. TRUY VẤN SỐ LIỆU CHO 4 HỘP HERO TIÊU CHÍ ĐẦU TRANG
// =========================================================================
$hero_product = $db->getOne("
    SELECT p.description, COUNT(oi.product_id) as sales 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id
    JOIN orders o ON oi.order_id = o.order_id
    WHERE o.status = 'Hoàn tất' AND $whereSql
    GROUP BY p.product_id ORDER BY sales DESC LIMIT 1", $params);

$hero_customer = $db->getOne("
    SELECT fullname, SUM(total) as spent 
    FROM orders WHERE status = 'Hoàn tất' AND $whereSql 
    GROUP BY phone, fullname ORDER BY spent DESC LIMIT 1", $params);

$hero_payment = $db->getOne("
    SELECT payment, SUM(total) as rev 
    FROM orders WHERE status = 'Hoàn tất' AND $whereSql 
    GROUP BY payment ORDER BY rev DESC LIMIT 1", $params);

$hero_province = $db->getOne("
    SELECT address as prov, COUNT(*) as cnt 
    FROM orders WHERE status = 'Hoàn tất' AND $whereSql 
    GROUP BY prov ORDER BY cnt DESC LIMIT 1", $params);

// =========================================================================
// 5. PHÂN NHÓM ĐỘNG THEO THỜI GIAN
// =========================================================================
$dateGroupSql = "DATE_FORMAT(created_at, '%m/%Y')"; 
if ($groupBy === 'day') { $dateGroupSql = "DATE_FORMAT(created_at, '%d/%m/%Y')"; }
elseif ($groupBy === 'quarter') { $dateGroupSql = "CONCAT('Q', QUARTER(created_at), '/', YEAR(created_at))"; }
elseif ($groupBy === 'year') { $dateGroupSql = "YEAR(created_at)"; }

$chart_timeline = $db->getAll("
    SELECT $dateGroupSql as o_date, SUM(total) as daily_rev 
    FROM orders 
    WHERE $whereNetSql
    GROUP BY o_date 
    ORDER BY created_at ASC LIMIT 30", $netParams);

$labels_timeline = []; $values_timeline = []; $values_profit = [];
foreach ($chart_timeline as $row) {
    if (!empty($row['o_date'])) {
        $labels_timeline[] = $row['o_date'];
        $values_timeline[] = (int)$row['daily_rev'];
        $values_profit[] = (int)($row['daily_rev'] * 0.45);
    }
}

$chart_statuses = $db->getAll("SELECT status, COUNT(*) as cnt FROM orders WHERE $whereSql GROUP BY status", $params);
$labels_status = []; $values_status = [];
foreach ($chart_statuses as $row) {
    $labels_status[] = !empty($row['status']) ? $row['status'] : 'Chờ xử lý';
    $values_status[] = (int)$row['cnt'];
}

$chart_payments = $db->getAll("SELECT payment, SUM(total) as rev FROM orders WHERE $whereNetSql GROUP BY payment", $netParams);
$labels_payment = []; $values_payment = [];
foreach ($chart_payments as $row) {
    $labels_payment[] = !empty($row['payment']) ? $row['payment'] : 'Khác';
    $values_payment[] = (int)$row['rev'];
}

// =========================================================================
// 6. TRUY VẤN CHO CÁC BẢNG SỐ LIỆU CHI TIẾT
// =========================================================================
$top_products = $db->getAll("
    SELECT p.product_id, p.description, p.price, p.image, COUNT(oi.product_id) as total_sales
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN orders o ON oi.order_id = o.order_id
    WHERE o.status = 'Hoàn tất' AND $whereSql
    GROUP BY p.product_id
    ORDER BY total_sales DESC LIMIT 5", $params);

$top_customers = $db->getAll("
    SELECT fullname, phone, COUNT(order_id) as total_orders, SUM(total) as total_spent, address as prov
    FROM orders
    WHERE $whereNetSql
    GROUP BY phone, fullname, prov
    ORDER BY total_spent DESC LIMIT 5", $netParams);

$top_provinces = $db->getAll("
    SELECT address as prov, COUNT(order_id) as total_orders, SUM(total) as total_spent
    FROM orders
    WHERE $whereNetSql
    GROUP BY prov
    ORDER BY total_spent DESC LIMIT 5", $netParams);

$recent_orders = $db->getAll("SELECT * FROM orders WHERE $whereSql ORDER BY created_at DESC LIMIT 6", $params);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>TechShop - Dashboard Thống Kê Tổng Lực</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --good: #10b981;
            --bad: #ef4444;
            --warn: #f59e0b;
            --muted: #64748b;
            --line: #e2e8f0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #f8fafc; display: flex; min-height: 100vh; color: #1e293b; }

        /* FIXED FIXED SIDEBAR KHÔNG ĐÈ CHỮ */
        .sidebar {
            width: 260px; background: #1e293b; color: white; padding: 20px; position: fixed; height: 100vh; top: 0; left: 0; z-index: 100;
        }
        .sidebar h2 {
            font-size: 20px; margin-bottom: 30px; text-align: center; border-bottom: 1px solid #334155; padding-bottom: 15px; color: #38bdf8; font-weight: 800;
        }
        .sidebar a {
            display: block; color: #cbd5e1; text-decoration: none; padding: 12px 15px; border-radius: 8px; margin-bottom: 10px; font-weight: bold; transition: all 0.2s;
        }
        .sidebar a:hover { background: #334155; color: white; }
        .sidebar a.active { background: #0284c7; color: white; }

        /* ĐẨY LỀ TRÁI TOÀN KHỐI 280PX TRÁNH ĐÈ TEXT */
        .main-content { margin-left: 280px; flex: 1; padding: 30px 30px 30px 10px; overflow-x: hidden; }
        
        .panel-card {
            background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid var(--line); margin-bottom: 25px;
        }
        .header-panel { display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap; }

        .hero-metrics-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 25px;
        }
        .hero-tile {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); color: white; padding: 20px; border-radius: 16px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }
        .hero-tile .tile-label { font-size: 12px; opacity: 0.85; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        .hero-tile .tile-value { font-size: 14px; font-weight: 800; margin-top: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .hero-tile .tile-hint { font-size: 11px; opacity: 0.75; margin-top: 6px; }

        .filter-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; align-items: end;
        }
        .filter-grid label { display: block; font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; margin-bottom: 6px; }
        .filter-grid input, .filter-grid select {
            width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px; font-size: 13px; outline: none; background: #f8fafc;
        }
        
        .btn-action {
            background: var(--primary); color: white; border: none; padding: 10px 14px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        }
        .btn-action:hover { opacity: 0.9; }

        .kpi-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 25px;
        }
        @media (max-width: 1200px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
        .kpi-card {
            background: white; padding: 18px; border-radius: 14px; border: 1px solid var(--line); position: relative; box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .kpi-card .icon { font-size: 24px; margin-bottom: 8px; background: #f8fafc; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: 10px; border: 1px solid var(--line); }
        .kpi-card .label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; }
        .kpi-card .value { font-size: 18px; font-weight: 800; color: #1e293b; margin-top: 6px; }

        .grid-charts { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        @media (max-width: 1024px) { .grid-charts { grid-template-columns: 1fr; } }
        
        .chart-container { min-height: 320px; position: relative; }
        canvas { width: 100%; height: 260px; display: block; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }

        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid var(--line); vertical-align: middle;}
        th { background: #f8fafc; color: var(--muted); font-weight: 700; text-transform: uppercase; font-size: 11px; }
        tr:hover td { background: #f8fafc; }

        .bar-cell { min-width: 100px; }
        .bar-bg { height: 6px; border-radius: 10px; background: #e2e8f0; overflow: hidden; margin-top: 4px; }
        .bar-fill { height: 100%; border-radius: 10px; background: linear-gradient(90deg, #6366f1, #00d4ff); }

        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; }
        .st-dadat { background: #fef3c7; color: #d97706; }
        .st-giao { background: #e0f2fe; color: #0369a1; }
        .st-hoantat { background: #d1fae5; color: #065f46; }
        .st-huy { background: #fee2e2; color: #b91c1c; }
        .prod-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid var(--line); }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>🛠️ TECHSHOP ADMIN</h2>
        <a href="index.php" class="active">🏠 Bảng Điều Khiển</a>
        <a href="orders.php">📦 Quản lý đơn hàng</a>
        <a href="products.php">🏷️ Quản lý sản phẩm</a>
        <a href="users.php">👥 Quản lý người dùng</a>
        <a href="../index.php" style="margin-top: 50px; background: #b91c1c; text-align: center; color: white;">Trang chủ User</a>
    </div>

    <div class="main-content">
        <div class="panel-card header-panel">
            <div>
                <h1 style="font-size:26px; font-weight:800; color:#1e293b; letter-spacing:-0.5px;">Dashboard phân tích kinh doanh hệ thống</h1>
                <small style="color: var(--muted);">Dữ liệu cập nhật thời gian thực dựa trên cấu trúc MySQL</small>
            </div>
            </div>

        <div class="hero-metrics-grid">
            <div class="hero-tile">
                <div class="tile-label">Sản phẩm dẫn đầu</div>
                <div class="tile-value"><?= htmlspecialchars($hero_product['description'] ?? 'Chưa có') ?></div>
                <div class="tile-hint"><?= isset($hero_product['sales']) ? $hero_product['sales'].' lượt mua thực' : 'Bán chạy nhất' ?></div>
            </div>
            <div class="hero-tile" style="background: linear-gradient(135deg, #7c5cff 0%, #a78bfa 100%)">
                <div class="tile-label">Khách mua nhiều nhất</div>
                <div class="tile-value"><?= htmlspecialchars($hero_customer['fullname'] ?? 'Chưa có') ?></div>
                <div class="tile-hint"><?= isset($hero_customer['spent']) ? number_format($hero_customer['spent']).'đ chi tiêu' : 'Khách VIP' ?></div>
            </div>
            <div class="hero-tile" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%)">
                <div class="tile-label">Kênh thanh toán mạnh nhất</div>
                <div class="tile-value"><?= htmlspecialchars($hero_payment['payment'] ?? 'Chưa có') ?></div>
                <div class="tile-hint"><?= isset($hero_payment['rev']) ? number_format($hero_payment['rev']).'đ tích lũy' : 'Doanh thu cao nhất' ?></div>
            </div>
            <div class="hero-tile" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%)">
                <div class="tile-label">Khu vực mạnh nhất</div>
                <div class="tile-value"><?= htmlspecialchars($hero_province['prov'] ?? 'Chưa có') ?></div>
                <div class="tile-hint"><?= isset($hero_province['cnt']) ? $hero_province['cnt'].' đơn thành công' : 'Theo số đơn' ?></div>
            </div>
        </div>

        <form method="GET" class="panel-card">
            <div class="filter-grid">
                <div><label>Từ ngày</label><input type="date" name="dateFrom" value="<?= htmlspecialchars($dateFrom) ?>"></div>
                <div><label>Đến ngày</label><input type="date" name="dateTo" value="<?= htmlspecialchars($dateTo) ?>"></div>
                <div>
                    <label>Nhóm thời gian</label>
                    <select name="groupBy">
                        <option value="day" <?= $groupBy === 'day' ? 'selected' : '' ?>>Theo ngày</option>
                        <option value="month" <?= $groupBy === 'month' ? 'selected' : '' ?>>Theo tháng</option>
                        <option value="quarter" <?= $groupBy === 'quarter' ? 'selected' : '' ?>>Theo quý</option>
                        <option value="year" <?= $groupBy === 'year' ? 'selected' : '' ?>>Theo năm</option>
                    </select>
                </div>
                <div>
                    <label>Trạng thái đơn</label>
                    <select name="status">
                        <option value="all">Tất cả</option>
                        <?php foreach($all_statuses as $st): ?>
                            <option value="<?= htmlspecialchars($st['status']) ?>" <?= $statusFilter===$st['status']?'selected':'' ?>><?= htmlspecialchars($st['status']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Thanh toán</label>
                    <select name="payment">
                        <option value="all">Tất cả</option>
                        <?php foreach($all_payments as $py): ?>
                            <option value="<?= htmlspecialchars($py['payment']) ?>" <?= $paymentFilter===$py['payment']?'selected':'' ?>><?= htmlspecialchars($py['payment']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Tìm kiếm</label><input type="text" name="search" value="<?= htmlspecialchars($searchBox) ?>" placeholder="Mã đơn, tên khách..."></div>
                <div><button type="submit" class="btn-action"><i class="fas fa-filter"></i> Lọc dữ liệu</button></div>
                <div><a href="index.php" class="btn-action" style="background:#e2e8f0; color:#475569;">Xóa lọc</a></div>
            </div>
        </form>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="icon" style="color:var(--primary);">💰</div>
                <div class="label">Tổng doanh thu</div>
                <div class="value"><?= number_format($gross_revenue, 0, ',', '.') ?>đ</div>
            </div>
            <div class="kpi-card">
                <div class="icon" style="color:var(--good);">✅</div>
                <div class="label">Doanh thu thực thu</div>
                <div class="value" style="color:var(--good);"><?= number_format($net_revenue, 0, ',', '.') ?>đ</div>
            </div>
            <div class="kpi-card">
                <div class="icon" style="color:#8b5cf6;">📈</div>
                <div class="label">Lợi nhuận gộp (45%)</div>
                <div class="value" style="color:#8b5cf6;"><?= number_format($estimated_profit, 0, ',', '.') ?>đ</div>
            </div>
            <div class="kpi-card">
                <div class="icon" style="color:var(--warn);">🛒</div>
                <div class="label">Đơn TB (AOV)</div>
                <div class="value"><?= number_format($aov, 0, ',', '.') ?>đ</div>
            </div>
            <div class="kpi-card">
                <div class="icon" style="color:#0369a1;">🧾</div>
                <div class="label">Tổng số lượng đơn</div>
                <div class="value"><?= number_format($total_orders_count) ?> đơn</div>
            </div>
            <div class="kpi-card">
                <div class="icon" style="color:var(--good);">📦</div>
                <div class="label font">Sản phẩm bán ra</div>
                <div class="value"><?= number_format($total_units_sold) ?> món</div>
            </div>
            <div class="kpi-card">
                <div class="icon" style="color:var(--bad);">↩️</div>
                <div class="label">Tỷ lệ hủy / hoàn</div>
                <div class="value" style="color:var(--bad);"><?= number_format($bad_rate, 1) ?>%</div>
            </div>
            <div class="kpi-card">
                <div class="icon" style="color:#14b8a6;">👥</div>
                <div class="label">Khách mua lặp</div>
                <div class="value"><?= number_format($repeat_rate, 1) ?>%</div>
            </div>
        </div>

        <div class="grid-charts">
            <div class="panel-card chart-container">
                <h3>📈 Biến động doanh thu & Lợi nhuận gộp</h3>
                <canvas id="canvasTimeline"></canvas>
            </div>
            <div class="panel-card chart-container">
                <h3>📊 Cơ cấu trạng thái đơn hàng</h3>
                <canvas id="canvasStatus"></canvas>
            </div>
            <div class="panel-card chart-container">
                <h3>💳 Doanh thu theo phương thức thanh toán</h3>
                <canvas id="canvasPayment"></canvas>
            </div>
        </div>

        <div class="grid-2">
            <div class="panel-card">
                <h3>🔥 Top sản phẩm bán chạy nhất</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Hình</th>
                            <th>Mã SP / Tên sản phẩm</th>
                            <th style="text-align: center;">Lượt bán</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($top_products)): ?>
                            <tr><td colspan="3" style="text-align:center; color:var(--muted);">Chưa có dữ liệu</td></tr>
                        <?php else: 
                            $max_sales = $top_products[0]['total_sales'] ?: 1;
                            foreach($top_products as $p): 
                                $pct = ($p['total_sales'] / $max_sales) * 100;
                        ?>
                            <tr>
                                <td><img src="<?= htmlspecialchars($p['image']) ?>" class="prod-thumb" onerror="this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=100'"></td>
                                <td>
                                    <div style="font-weight:700; color:#1e293b;"><?= htmlspecialchars($p['description']) ?></div>
                                    <small style="color:var(--muted);">Mã: <?= htmlspecialchars($p['product_id']) ?></small>
                                </td>
                                <td class="bar-cell" style="text-align: center;">
                                    <span style="font-weight: 800; color:var(--primary);"><?= $p['total_sales'] ?> sản phẩm</span>
                                    <div class="bar-bg"><div class="bar-fill" style="width: <?= $pct ?>%"></div></div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="panel-card">
                <h3>👑 Bảng xếp hạng khách hàng VIP thân thiết</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Thông tin khách</th>
                            <th style="text-align: center;">Khu vực</th>
                            <th style="text-align: right;">Tổng chi tiêu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($top_customers)): ?>
                            <tr><td colspan="3" style="text-align:center; color:var(--muted);">Chưa có dữ liệu</td></tr>
                        <?php else: 
                            $max_spent = $top_customers[0]['total_spent'] ?: 1;
                            foreach($top_customers as $c): 
                                $pct_spent = ($c['total_spent'] / $max_spent) * 100;
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight:700; color:#1e293b;"><?= htmlspecialchars($c['fullname']) ?></div>
                                    <small style="color:var(--muted);"><?= htmlspecialchars($c['phone']) ?> • <?= $c['total_orders'] ?> đơn</small>
                                </td>
                                <td><span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:11px;"><?= htmlspecialchars($c['prov'] ?: 'Chưa rõ') ?></span></td>
                                <td class="bar-cell" style="text-align: right;">
                                    <span style="font-weight: 800; color:var(--good);"><?= number_format($c['total_spent'], 0, ',', '.') ?>đ</span>
                                    <div class="bar-bg" style="margin-left:auto; width:100px;"><div class="bar-fill" style="width: <?= $pct_spent ?>%"></div></div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid-2">
            <div class="panel-card">
                <h3>📍 Hiệu suất phân phối theo địa chỉ / Khu vực</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Địa chỉ / Khu vực</th>
                            <th style="text-align: center;">Số lượng đơn</th>
                            <th style="text-align: right;">Doanh số thực thu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($top_provinces)): ?>
                            <tr><td colspan="3" style="text-align:center; color:var(--muted);">Chưa bóc tách được dữ liệu.</td></tr>
                        <?php else: 
                            $max_prov_spent = $top_provinces[0]['total_spent'] ?: 1;
                            foreach($top_provinces as $tp): 
                                $pct_prov = ($tp['total_spent'] / $max_prov_spent) * 100;
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($tp['prov'] ?: 'Chưa ghi nhận') ?></strong></td>
                                <td style="text-align: center;"><span class="badge" style="background:#f1f5f9; color:#475569;"><?= $tp['total_orders'] ?> đơn</span></td>
                                <td class="bar-cell" style="text-align: right;">
                                    <span style="font-weight: 800; color:#06b6d4;"><?= number_format($tp['total_spent'], 0, ',', '.') ?>đ</span>
                                    <div class="bar-bg" style="margin-left:auto; width:100px;"><div class="bar-fill" style="width: <?= $pct_prov ?>%"></div></div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="panel-card">
                <h3>🛒 Các giao dịch phát sinh gần đây</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Mã Đơn</th>
                            <th>Khách Hàng</th>
                            <th>Tổng Tiền</th>
                            <th>Trạng Thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_orders)): ?>
                            <tr><td colspan="4" style="text-align:center; color:var(--muted);">Chưa có đơn hàng nào.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $ro): 
                                $st_class = match($ro['status']) { 'Đã đặt'=>'st-dadat', 'Đang giao hàng'=>'st-giao', 'Hoàn tất'=>'st-hoantat', default=>'st-huy' };
                                ?>
                                <tr>
                                    <td><strong>#<?= htmlspecialchars($ro['order_id']) ?></strong></td>
                                    <td>
                                        <div style="font-weight:600;"><?= htmlspecialchars($ro['fullname']) ?></div>
                                        <small style="color:var(--muted);"><?= date('d/m H:i', strtotime($ro['created_at'])) ?></small>
                                    </td>
                                    <td style="color:#dc2626; font-weight:700;"><?= number_format($ro['total'], 0, ',', '.') ?>đ</td>
                                    <td><span class="badge <?= $st_class ?>"><?= htmlspecialchars($ro['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let dataTimelineLabels = <?= json_encode($labels_timeline) ?>;
        let dataTimelineValues = <?= json_encode($values_timeline) ?>;
        let dataProfitValues = <?= json_encode($values_profit) ?>;

        if (dataTimelineValues.length === 1) {
            dataTimelineLabels.unshift("Bắt đầu");
            dataTimelineValues.unshift(0);
            dataProfitValues.unshift(0);
        }

        const dataStatusLabels = <?= json_encode($labels_status) ?>;
        const dataStatusValues = <?= json_encode($values_status) ?>;
        const dataPaymentLabels = <?= json_encode($labels_payment) ?>;
        const dataPaymentValues = <?= json_encode($values_payment) ?>;

        const PALETTE = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];

        function maxNice(v){ if(v<=0) return 1; const p = Math.pow(10, Math.floor(Math.log10(v))); return Math.ceil(v/p)*p; }
        function shortMoney(n) { n = Math.abs(n || 0); if(n >= 1e6) return (n/1e6).toFixed(1) + ' tr'; if(n >= 1e3) return (n/1e3).toFixed(0) + 'k'; return String(n); }

        function initCanvas(id) {
            const canvas = document.getElementById(id);
            const ctx = canvas.getContext('2d');
            const rect = canvas.getBoundingClientRect();
            const dpr = window.devicePixelRatio || 1;
            canvas.width = Math.floor(rect.width * dpr);
            canvas.height = Math.floor(rect.height * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            return { ctx, w: rect.width, h: rect.height };
        }

        function drawLineChart() {
            const { ctx, w, h } = initCanvas('canvasTimeline');
            if(!dataTimelineValues.length) return;
            
            // TĂNG PAD.R TỪ 15 LÊN 45 ĐỂ CHỮ 06/2026 KHÔNG BỊ KHUẤT BIÊN PHẢI
            const pad = {l:55, r:45, t:35, b:40};
            const cw = w - pad.l - pad.r, ch = h - pad.t - pad.b;
            const maxV = maxNice(Math.max(...dataTimelineValues, 1) * 1.15);

            ctx.strokeStyle = '#f1f5f9'; ctx.lineWidth = 1;
            for(let i=0; i<=4; i++) {
                const y = pad.t + (ch * i / 4);
                ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(pad.l + cw, y); ctx.stroke();
                ctx.fillStyle = '#64748b'; ctx.font = '600 10px sans-serif'; ctx.textAlign = 'right';
                ctx.fillText(shortMoney(maxV * (1 - i/4)), pad.l - 8, y + 3);
            }

            const xAt = i => pad.l + (dataTimelineValues.length === 1 ? cw/2 : i * cw / (dataTimelineValues.length - 1));
            const yAt = v => pad.t + ch - (v / maxV) * ch;

            ctx.beginPath();
            dataTimelineValues.forEach((v, i) => { if(i===0) ctx.moveTo(xAt(i), yAt(v)); else ctx.lineTo(xAt(i), yAt(v)); });
            ctx.strokeStyle = '#4f46e5'; ctx.lineWidth = 3.5; ctx.stroke();

            ctx.beginPath();
            dataProfitValues.forEach((v, i) => { if(i===0) ctx.moveTo(xAt(i), yAt(v)); else ctx.lineTo(xAt(i), yAt(v)); });
            ctx.strokeStyle = '#a78bfa'; ctx.lineWidth = 2.5; ctx.stroke();

            ctx.fillStyle = '#4f46e5'; ctx.font = '600 11px sans-serif'; ctx.textAlign = 'left';
            ctx.fillText('● Doanh thu thực', pad.l, pad.t - 12);
            ctx.fillStyle = '#a78bfa';
            ctx.fillText('● Lợi nhuận gộp', pad.l + 120, pad.t - 12);

            dataTimelineLabels.forEach((l, i) => {
                if(i % Math.max(1, Math.ceil(dataTimelineLabels.length / 6)) === 0 || i === dataTimelineLabels.length - 1) {
                    ctx.fillStyle = '#64748b'; ctx.font = '600 10px sans-serif'; ctx.textAlign = 'center';
                    ctx.fillText(l, xAt(i), h - 15);
                }
            });
        }

        function drawPieChart() {
            const { ctx, w, h } = initCanvas('canvasStatus');
            if(!dataStatusValues.length) return;
            const total = dataStatusValues.reduce((a, b) => a + b, 0) || 1;
            const cx = w * 0.35, cy = h * 0.5, radius = Math.min(w * 0.23, h * 0.35);
            let startAngle = -Math.PI / 2;

            dataStatusValues.forEach((v, i) => {
                const angle = (v / total) * Math.PI * 2;
                ctx.beginPath(); ctx.moveTo(cx, cy); ctx.arc(cx, cy, radius, startAngle, startAngle + angle); ctx.closePath();
                ctx.fillStyle = PALETTE[i % PALETTE.length]; ctx.fill();
                startAngle += angle;

                const lx = w * 0.65, ly = 40 + i * 24;
                ctx.fillStyle = PALETTE[i % PALETTE.length]; ctx.fillRect(lx, ly - 8, 10, 10);
                ctx.fillStyle = '#1e293b'; ctx.font = '600 11px sans-serif'; ctx.textAlign = 'left';
                ctx.fillText(dataStatusLabels[i] + ' (' + v + ')', lx + 15, ly);
            });
        }

        function drawVerticalBar() {
            const { ctx, w, h } = initCanvas('canvasPayment');
            if(!dataPaymentValues.length) return;
            const pad = {l:45, r:15, t:20, b:40};
            const cw = w - pad.l - pad.r, ch = h - pad.t - pad.b;
            const maxV = maxNice(Math.max(...dataPaymentValues, 1) * 1.15);

            const gap = 12;
            const bw = (cw - gap * (dataPaymentValues.length - 1)) / dataPaymentValues.length;

            dataPaymentValues.forEach((v, i) => {
                const bh = (v / maxV) * ch;
                const x = pad.l + i * (bw + gap);
                const y = pad.t + ch - bh;

                ctx.fillStyle = '#f59e0b';
                ctx.fillRect(x, y, bw, bh);

                ctx.fillStyle = '#64748b'; ctx.font = '600 10px sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(dataPaymentLabels[i], x + bw / 2, h - 15);
                ctx.fillText(shortMoney(v), x + bw / 2, y - 5);
            });
        }

        function drawAllCharts() { drawLineChart(); drawPieChart(); drawVerticalBar(); }
        window.addEventListener('resize', drawAllCharts);
        window.addEventListener('load', drawAllCharts);
    </script>
</body>
</html>