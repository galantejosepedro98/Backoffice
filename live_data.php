<?php
// Include authentication and database connection
include 'auth.php';
include 'conexao.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get event ID
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Validate event_id
if (!$event_id) {
    echo json_encode(['error' => 'Event ID is required']);
    exit;
}

// Prepare data array
$data = [];

// Get total tickets count with breakdown by type (based on bilheteira system)
$stmtTotalTickets = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN t.type = 'paid' OR t.type = 'web' THEN 1 END) as paid_tickets,
        COUNT(CASE WHEN t.type = 'invite' THEN 1 END) as invites,
        COUNT(DISTINCT t.user_id) as customer_count
    FROM tickets t
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ? 
    AND t.active = 1
    AND (
        (t.type = 'paid' OR t.type = 'web') AND (o.status = 1 OR o.payment_status = 1)
        OR 
        t.type = 'invite'
    )
");
$stmtTotalTickets->execute([$event_id]);
$ticketStats = $stmtTotalTickets->fetch();
$data['totalTickets'] = (int)$ticketStats['total'];
$data['paidTickets'] = (int)$ticketStats['paid_tickets'];
$data['invites'] = (int)$ticketStats['invites'];
$data['customerCount'] = (int)$ticketStats['customer_count'];

// Get check-in counts with breakdown by ticket type
$stmtCheckIns = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN t.type = 'paid' OR t.type = 'web' THEN 1 END) as paid_checkins,
        COUNT(CASE WHEN t.type = 'invite' THEN 1 END) as invite_checkins
    FROM tickets t
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ? 
    AND t.active = 1
    AND t.status = 1
    AND (
        (t.type = 'paid' OR t.type = 'web') AND (o.status = 1 OR o.payment_status = 1)
        OR 
        t.type = 'invite'
    )
");
$stmtCheckIns->execute([$event_id]);
$checkInStats = $stmtCheckIns->fetch();
$data['totalCheckIns'] = (int)$checkInStats['total']; 
$data['paidCheckIns'] = (int)$checkInStats['paid_checkins'];
$data['inviteCheckIns'] = (int)$checkInStats['invite_checkins'];

// Calculate check-in percentage
$data['checkInPercentage'] = $data['totalTickets'] > 0 ? round(($data['totalCheckIns'] / $data['totalTickets']) * 100) : 0;
$data['paidCheckInPercentage'] = $data['paidTickets'] > 0 ? round(($data['paidCheckIns'] / $data['paidTickets']) * 100) : 0;
$data['inviteCheckInPercentage'] = $data['invites'] > 0 ? round(($data['inviteCheckIns'] / $data['invites']) * 100) : 0;

// Get revenue with breakdown by payment method (based on bilheteira system)
$stmtRevenue = $pdo->prepare("
    SELECT
        SUM(t.price) as total,
        SUM(CASE WHEN o.pos_id IS NULL THEN t.price END) as online_cost,
        SUM(CASE WHEN o.pos_id IS NOT NULL THEN t.price END) as pos_cost,
        SUM(CASE WHEN o.payment_method = 'Card' THEN t.price END) as card_amount,
        SUM(CASE WHEN o.payment_method = 'Cash' THEN t.price END) as cash_amount
    FROM tickets t
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ?
    AND t.active = 1
    AND (t.type = 'paid' OR t.type = 'web')
    AND (o.status = 1 OR o.payment_status = 1)
");
$stmtRevenue->execute([$event_id]);
$revenueStats = $stmtRevenue->fetch();

$data['totalRevenue'] = $revenueStats['total'] ? $revenueStats['total'] / 100 : 0; // Assuming price is stored in cents
$data['onlineRevenue'] = $revenueStats['online_cost'] ? $revenueStats['online_cost'] / 100 : 0;
$data['posRevenue'] = $revenueStats['pos_cost'] ? $revenueStats['pos_cost'] / 100 : 0;
$data['cardRevenue'] = $revenueStats['card_amount'] ? $revenueStats['card_amount'] / 100 : 0;
$data['cashRevenue'] = $revenueStats['cash_amount'] ? $revenueStats['cash_amount'] / 100 : 0;

// Create revenue by method object for compatibility with existing code
$paymentMethodRevenue = [
    'Online' => $data['onlineRevenue'],
    'Onsite' => $data['posRevenue']
];
$data['revenueByMethod'] = $paymentMethodRevenue;

// Get recent check-ins (last 10) with more details
$stmtRecentCheckIns = $pdo->prepare("
    SELECT 
        t.id, 
        t.ticket, 
        JSON_UNQUOTE(JSON_EXTRACT(t.owner, '$.name')) as name,
        JSON_UNQUOTE(JSON_EXTRACT(t.owner, '$.email')) as email, 
        t.updated_at,
        t.type,
        t.product_id,
        p.name as product_name, 
        JSON_UNQUOTE(JSON_EXTRACT(t.logs, '$[0].zone')) as zone,
        JSON_UNQUOTE(JSON_EXTRACT(t.logs, '$[0].time')) as check_time,
        o.payment_method
    FROM tickets t
    LEFT JOIN orders o ON t.order_id = o.id
    LEFT JOIN products p ON t.product_id = p.id
    WHERE t.event_id = ? 
    AND t.status = 1 
    AND t.active = 1
    AND (
        (t.type = 'paid' OR t.type = 'web') AND (o.status = 1 OR o.payment_status = 1)
        OR 
        t.type = 'invite'
    )
    ORDER BY t.updated_at DESC
    LIMIT 10
");
$stmtRecentCheckIns->execute([$event_id]);
$recentCheckIns = $stmtRecentCheckIns->fetchAll(PDO::FETCH_ASSOC);

// Process timestamps for recent check-ins
foreach ($recentCheckIns as &$checkIn) {
    $dt = new DateTime($checkIn['updated_at']);
    $checkIn['formatted_time'] = $dt->format('H:i:s');
}

$data['recentCheckIns'] = $recentCheckIns;

// Process timestamps for recent check-ins
foreach ($data['recentCheckIns'] as &$checkIn) {
    $dt = new DateTime($checkIn['updated_at']);
    $checkIn['formatted_time'] = $dt->format('H:i:s');
}

// Get check-ins by time intervals (hourly) with breakdown by ticket type
$stmtHourly = $pdo->prepare("
    SELECT 
        HOUR(t.updated_at) as hour,
        COUNT(*) as count,
        COUNT(CASE WHEN t.type = 'paid' OR t.type = 'web' THEN 1 END) as paid_count,
        COUNT(CASE WHEN t.type = 'invite' THEN 1 END) as invite_count
    FROM tickets t
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ? 
    AND t.status = 1 
    AND t.active = 1
    AND DATE(t.updated_at) = CURDATE()
    AND (
        (t.type = 'paid' OR t.type = 'web') AND (o.status = 1 OR o.payment_status = 1)
        OR 
        t.type = 'invite'
    )
    GROUP BY HOUR(t.updated_at)
    ORDER BY HOUR(t.updated_at)
");
$stmtHourly->execute([$event_id]);
$hourlyCheckIns = $stmtHourly->fetchAll(PDO::FETCH_ASSOC);

// Format hourly data with separate datasets for paid tickets and invites
$hourLabels = [];
$hourData = [];
$paidHourData = [];
$inviteHourData = [];

for ($i = 0; $i < 24; $i++) {
    $hourLabels[] = sprintf("%02d:00", $i);
    $hourData[] = 0;
    $paidHourData[] = 0;
    $inviteHourData[] = 0;
}

foreach ($hourlyCheckIns as $item) {
    $hour = (int)$item['hour'];
    $hourData[$hour] = (int)$item['count'];
    $paidHourData[$hour] = (int)$item['paid_count'];
    $inviteHourData[$hour] = (int)$item['invite_count'];
}

$data['hourlyChart'] = [
    'labels' => $hourLabels,
    'data' => $hourData,
    'paidData' => $paidHourData,
    'inviteData' => $inviteHourData
];

// Return data as JSON
echo json_encode($data);
?>
