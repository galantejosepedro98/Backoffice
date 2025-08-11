<?php
include 'auth.php';
include 'conexao.php';

// Get event ID
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Validate event_id
if (!$event_id) {
    die('Event ID is required');
}

// Get event statistics with better breakdown (based on bilheteira)
$stmtCapacity = $pdo->prepare("
    SELECT 
        SUM(p.capacity) as total_capacity,
        SUM(p.quantity_sold) as total_sold,
        SUM(CASE WHEN p.invite_only = 1 THEN p.capacity ELSE 0 END) as invite_capacity,
        SUM(CASE WHEN p.invite_only = 1 THEN p.quantity_sold ELSE 0 END) as invite_sold,
        SUM(CASE WHEN p.invite_only = 0 THEN p.capacity ELSE 0 END) as paid_capacity,
        SUM(CASE WHEN p.invite_only = 0 THEN p.quantity_sold ELSE 0 END) as paid_sold,
        e.capacity as event_capacity
    FROM products p
    JOIN events e ON p.event_id = e.id
    WHERE p.event_id = ? AND p.active = 1
    GROUP BY e.id
");
$stmtCapacity->execute([$event_id]);
$capacity = $stmtCapacity->fetch();

// Get Daily Sales with breakdown by online vs pos (based on bilheteira)
$stmtDailySales = $pdo->prepare("
    SELECT 
        DATE(t.created_at) as sale_date,
        COUNT(*) as count,
        SUM(t.price) as revenue,
        COUNT(CASE WHEN o.pos_id IS NULL THEN 1 END) as online_count,
        COUNT(CASE WHEN o.pos_id IS NOT NULL THEN 1 END) as pos_count,
        SUM(CASE WHEN o.pos_id IS NULL THEN t.price ELSE 0 END) as online_revenue,
        SUM(CASE WHEN o.pos_id IS NOT NULL THEN t.price ELSE 0 END) as pos_revenue
    FROM tickets t
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ? 
    AND t.active = 1
    AND (t.type = 'paid' OR t.type = 'web')
    AND (o.status = 1 OR o.payment_status = 1)
    AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(t.created_at)
    ORDER BY sale_date
");
$stmtDailySales->execute([$event_id]);
$dailySales = $stmtDailySales->fetchAll();

// Format sales data for chart
$salesDates = [];
$salesCounts = [];
$salesRevenue = [];

foreach ($dailySales as $day) {
    $dt = new DateTime($day['sale_date']);
    $salesDates[] = $dt->format('d/m');
    $salesCounts[] = (int)$day['count'];
    $salesRevenue[] = (float)($day['revenue'] / 100); // Assuming price is in cents
}

// Get ticket types sold
$stmtTicketTypes = $pdo->prepare("
    SELECT 
        p.name,
        COUNT(*) as count,
        p.capacity,
        ROUND((COUNT(*) / p.capacity) * 100) as percentage,
        t.type as ticket_type
    FROM tickets t
    JOIN products p ON t.product_id = p.id
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ? 
    AND t.active = 1
    AND (
        (t.type = 'paid' OR t.type = 'web') AND (o.status = 1 OR o.payment_status = 1)
        OR 
        t.type = 'invite'
    )
    GROUP BY p.id, t.type
    ORDER BY count DESC
");
$stmtTicketTypes->execute([$event_id]);
$ticketTypes = $stmtTicketTypes->fetchAll();

// Set content type to JSON
header('Content-Type: application/json');

// Prepare response data
$data = [
    'capacity' => [
        'total' => (int)($capacity['total_capacity'] ?? 0),
        'sold' => (int)($capacity['total_sold'] ?? 0),
        'percentage' => (int)($capacity['total_capacity'] > 0 ? round(($capacity['total_sold'] / $capacity['total_capacity']) * 100) : 0)
    ],    'salesChart' => [
        'dates' => $salesDates,
        'counts' => $salesCounts,
        'revenue' => $salesRevenue,
        'online_counts' => array_map(function($day) { return (int)$day['online_count'] ?? 0; }, $dailySales),
        'pos_counts' => array_map(function($day) { return (int)$day['pos_count'] ?? 0; }, $dailySales),
        'online_revenue' => array_map(function($day) { return (float)($day['online_revenue'] ?? 0) / 100; }, $dailySales),
        'pos_revenue' => array_map(function($day) { return (float)($day['pos_revenue'] ?? 0) / 100; }, $dailySales)
    ],
    'ticketTypes' => $ticketTypes
];

// Return data
echo json_encode($data);
?>
