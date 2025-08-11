<?php
include 'auth.php';
include 'conexao.php';

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Validate event_id
if (!$event_id) {
    die('ID do evento não fornecido');
}

// Get event information
$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtEvent->execute([$event_id]);
$event = $stmtEvent->fetch();

if (!$event) {
    die('Evento não encontrado');
}

// Date filter
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Prepare date filter SQL
$params = [$event_id];
$date_sql = '';
if ($date_filter) {
    $date_sql = ' AND DATE(t.updated_at) = ?';
    $params[] = $date_filter;
}

// Get total valid ticket count (from paid orders and active tickets)
$stmtTotalTickets = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM tickets t
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ? 
    AND t.active = 1
    AND (
        (t.type = 'paid' OR t.type = 'web') AND (o.status = 1 OR o.payment_status = 1)
        OR 
        t.type = 'invite'
    )
    $date_sql
");
$stmtTotalTickets->execute($params);
$totalTickets = $stmtTotalTickets->fetch()['total'];

// Get paid tickets count (excluding invites)
$stmtPaidTickets = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM tickets t
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ? 
    AND t.active = 1
    AND (t.type = 'paid' OR t.type = 'web')
    AND (o.status = 1 OR o.payment_status = 1)
    $date_sql
");
$stmtPaidTickets->execute($params);
$paidTickets = $stmtPaidTickets->fetch()['total'];

// Get invites count
$stmtInvites = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM tickets t
    WHERE t.event_id = ? 
    AND t.active = 1
    AND t.type = 'invite'
    " . ($date_filter ? " AND DATE(t.updated_at) = ?" : "") . "
");
$stmtInvites->execute($date_filter ? [$event_id, $date_filter] : [$event_id]);
$invites = $stmtInvites->fetch()['total'];

// Get check-in counts with breakdown by ticket type (conta cada bilhete apenas 1 vez)
$stmtCheckIns = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT t.id) as total,
        COUNT(DISTINCT CASE WHEN (t.type = 'paid' OR t.type = 'web') THEN t.id END) as online_checkins,
        COUNT(DISTINCT CASE WHEN (t.type = 'pos' AND o.alert = 'unmarked') THEN t.id END) as local_checkins,
        COUNT(DISTINCT CASE WHEN t.type = 'invite' THEN t.id END) as invite_checkins
    FROM tickets t
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ? 
    AND t.active = 1
    AND t.status = 1
    AND (
        ((t.type = 'paid' OR t.type = 'web') AND (o.status = 1 OR o.payment_status = 1))
        OR (t.type = 'pos' AND (o.status = 1 OR o.payment_status = 1) AND o.alert = 'unmarked')
        OR t.type = 'invite'
    )
    $date_sql
");
$stmtCheckIns->execute($params);
$checkInStats = $stmtCheckIns->fetch();
$totalCheckIns = (int)$checkInStats['total'];
$onlineCheckIns = (int)$checkInStats['online_checkins'];
$localCheckIns = (int)$checkInStats['local_checkins'];
$inviteCheckIns = (int)$checkInStats['invite_checkins'];

// Calculate check-in percentage for each category
$onlineCheckInPercentage = $onlineTickets > 0 ? round(($onlineCheckIns / $onlineTickets) * 100) : 0;
$localCheckInPercentage = $localTickets > 0 ? round(($localCheckIns / $localTickets) * 100) : 0;
$inviteCheckInPercentage = $invites > 0 ? round(($inviteCheckIns / $invites) * 100) : 0;

// Get total revenue (only from paid tickets with confirmed orders)
$stmtRevenue = $pdo->prepare("
    SELECT SUM(t.price) as total 
    FROM tickets t
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ?
    AND t.active = 1
    AND (t.type = 'paid' OR t.type = 'web')
    AND (o.status = 1 OR o.payment_status = 1)
    $date_sql
");
$stmtRevenue->execute($params);
$totalRevenue = $stmtRevenue->fetch()['total'];
$totalRevenue = $totalRevenue ? $totalRevenue / 100 : 0; // Assuming price is stored in cents

// Get revenue for local sales (POS only, alert = 'unmarked')
$stmtPosRevenue = $pdo->prepare("
    SELECT SUM(t.price) as pos_cost
    FROM tickets t
    INNER JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ?
    AND t.active = 1
    AND t.type = 'pos'
    AND (o.status = 1 OR o.payment_status = 1)
    AND o.alert = 'unmarked'
    $date_sql
");
$stmtPosRevenue->execute($params);
$posRevenue = $stmtPosRevenue->fetch()['pos_cost'];
$posRevenue = $posRevenue ? $posRevenue / 100 : 0;

// Get revenue for online sales (paid/web)
$stmtOnlineRevenue = $pdo->prepare("
    SELECT SUM(t.price) as online_cost
    FROM tickets t
    INNER JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ?
    AND t.active = 1
    AND (t.type = 'paid' OR t.type = 'web')
    AND (o.status = 1 OR o.payment_status = 1)
    $date_sql
");
$stmtOnlineRevenue->execute($params);
$onlineRevenue = $stmtOnlineRevenue->fetch()['online_cost'];
$onlineRevenue = $onlineRevenue ? $onlineRevenue / 100 : 0;

// Get revenue for marked orders (POS only, alert = 'marked' or 'resolved')
$stmtMarkedRevenue = $pdo->prepare("
    SELECT SUM(t.price) as marked_cost
    FROM tickets t
    INNER JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ?
    AND t.active = 1
    AND t.type = 'pos'
    AND (o.status = 1 OR o.payment_status = 1)
    AND (o.alert = 'marked' OR o.alert = 'resolved')
    $date_sql
");
$stmtMarkedRevenue->execute($params);
$markedRevenue = $stmtMarkedRevenue->fetch()['marked_cost'];
$markedRevenue = $markedRevenue ? $markedRevenue / 100 : 0;

// Get revenue for extras (POS orders with extras, not null, not invite/easypay.pt, alert = 'unmarked')
$stmtExtrasRevenue = $pdo->prepare("
    SELECT SUM(o.total) as extras_total
    FROM orders o
    WHERE o.event_id = ?
      AND o.pos_id IS NOT NULL
      AND o.total IS NOT NULL AND o.total != 0
      AND o.payment_method NOT IN ('invite', 'easypay.pt')
      AND o.extras IS NOT NULL
      AND o.alert = 'unmarked'
      " . ($date_filter ? " AND DATE(o.updated_at) = ?" : "") . "
");
$stmtExtrasRevenue->execute($date_filter ? [$event_id, $date_filter] : [$event_id]);
$extrasRevenue = $stmtExtrasRevenue->fetch()['extras_total'];
$extrasRevenue = $extrasRevenue ? $extrasRevenue / 100 : 0;

// Soma simples de receita total (online + local + extras)
$totalRevenue = $onlineRevenue + $posRevenue + $extrasRevenue;

// Atualizar o array de breakdown
$paymentMethodRevenue = [
    'Online' => $onlineRevenue,
    'Onsite' => $posRevenue,
    'Marked' => $markedRevenue,
    'Extras' => $extrasRevenue
];

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
$recentCheckIns = $stmtRecentCheckIns->fetchAll();

// Get ticket types distribution
$stmtTicketTypes = $pdo->prepare("
    SELECT 
        p.name, 
        COUNT(*) as count,
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

// Get check-ins by zone
$stmtZones = $pdo->prepare("
    SELECT 
        z.name, 
        COUNT(DISTINCT t.id) as count
    FROM tickets t
    JOIN zones z ON t.check_in_zone = z.id
    WHERE t.event_id = ? 
      AND t.active = 1
      AND t.status = 1
      AND t.type != 'physical'
    GROUP BY z.id
    ORDER BY z.name
");
$stmtZones->execute([$event_id]);
$checkInsByZone = $stmtZones->fetchAll();

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
$hourlyCheckIns = $stmtHourly->fetchAll();

// Get extras usage
$stmtExtras = $pdo->prepare("
    SELECT 
        JSON_UNQUOTE(JSON_EXTRACT(t.extras, '$[0].name')) as name,
        COUNT(*) as count
    FROM tickets t
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ? 
    AND t.hasExtras = 1
    AND t.active = 1
    AND (
        (t.type = 'paid' OR t.type = 'web') AND (o.status = 1 OR o.payment_status = 1)
        OR 
        t.type = 'invite'
    )
    GROUP BY JSON_UNQUOTE(JSON_EXTRACT(t.extras, '$[0].name'))
");
$stmtExtras->execute([$event_id]);
$extrasUsage = $stmtExtras->fetchAll();

// Convert check-in data for Chart.js
$hourLabels = [];
$hourData = [];
for ($i = 0; $i < 24; $i++) {
    $hourLabels[] = sprintf("%02d:00", $i);
    $hourData[] = 0;
}

foreach ($hourlyCheckIns as $item) {
    $hourData[$item['hour']] = (int)$item['count'];
}

// Get online tickets count (paid/web)
$stmtOnlineTickets = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM tickets t
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ? 
    AND t.active = 1
    AND (t.type = 'paid' OR t.type = 'web')
    AND (o.status = 1 OR o.payment_status = 1)
    $date_sql
");
$stmtOnlineTickets->execute($params);
$onlineTickets = $stmtOnlineTickets->fetch()['total'];

// Get local tickets count (POS only, alert = 'unmarked')
$stmtLocalTickets = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM tickets t
    INNER JOIN orders o ON t.order_id = o.id
    WHERE t.event_id = ? 
    AND t.active = 1
    AND t.type = 'pos'
    AND (o.status = 1 OR o.payment_status = 1)
    AND o.alert = 'unmarked'
    $date_sql
");
$stmtLocalTickets->execute($params);
$localTickets = $stmtLocalTickets->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIVE Dashboard - <?php echo htmlspecialchars($event['name']); ?></title>    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="live_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-6">
                <h1 id="event-title">
                    <span class="live-indicator text-danger">
                        <i class="fas fa-circle"></i> LIVE
                    </span>
                    <?php echo htmlspecialchars($event['name']); ?>
                </h1>
                <p class="text-muted">
                    <?php echo date('d/m/Y', strtotime($event['start_at'])); ?> - 
                    <?php echo date('d/m/Y', strtotime($event['end_at'])); ?> | 
                    <?php echo htmlspecialchars($event['location']); ?>
                </p>
            </div>
            <div class="col-md-6 text-end">
                <span class="refresh-countdown">Atualização em <span id="countdown">15</span>s</span>
                <button class="btn btn-outline-primary ms-2" onclick="fetchLiveData();">
                    <i class="fas fa-sync-alt"></i> Atualizar Agora
                </button>
                <a href="eventos.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>        <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
    <form method="get" style="display: flex; align-items: center; gap: 10px;">
        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
        <label for="date" style="font-weight: bold;">Filtrar por data:</label>
        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <?php if ($date_filter): ?>
            <a href="live_dashboard.php?event_id=<?php echo $event_id; ?>" class="btn btn-secondary">Limpar</a>
        <?php endif; ?>
    </form>
</div>        <div class="row">
            <!-- Main content area -->
            <div class="col-md-12">
                <!-- Main Stats -->                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <div class="card">
                            <div class="stats-card card-blue">                        
                                <div class="icon"><i class="fas fa-ticket-alt"></i></div>
                                <div class="number" id="totalTicketsStatic"><?php echo number_format($onlineTickets + $localTickets + $invites); ?></div>
                                <div class="label">Bilhetes Totais</div>
                                <div class="breakdown">
                                    <span class="badge bg-info"><?php echo number_format($onlineTickets); ?> online</span>
                                    <span class="badge bg-warning"><?php echo number_format($localTickets); ?> local</span>
                                    <span class="badge bg-secondary"><?php echo number_format($invites); ?> convites</span>
                                </div>
                            </div>
                        </div>
                    </div>
                      <div class="col-lg-3 col-md-6">
                        <div class="card">
                            <div class="stats-card card-green">                        
                                <div class="icon"><i class="fas fa-users"></i></div>
                                <div class="number" data-value="<?php echo $totalCheckIns; ?>"><?php echo number_format($totalCheckIns); ?></div>
                                <div class="label">Check-ins</div>
                                <div class="breakdown">
                                    <span class="badge bg-info"><?php echo number_format($onlineCheckIns); ?> online (<?php echo $onlineCheckInPercentage; ?>%)</span>
                                    <span class="badge bg-warning"><?php echo number_format($localCheckIns); ?> local (<?php echo $localCheckInPercentage; ?>%)</span>
                                    <span class="badge bg-secondary"><?php echo number_format($inviteCheckIns); ?> convites (<?php echo $inviteCheckInPercentage; ?>%)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <div class="card">
                            <div class="stats-card card-purple">                        
                                <div class="icon"><i class="fas fa-euro-sign"></i></div>
                                <div class="number" data-value="<?php echo $totalRevenue; ?>" data-currency="true"><?php echo number_format($totalRevenue, 2, ',', '.'); ?>€</div>
                                <div class="label">Receita Total</div>                                <div class="breakdown">
                                    <?php if (isset($paymentMethodRevenue['Online'])): ?>
                                        <span class="badge bg-info"><?php echo number_format($paymentMethodRevenue['Online'], 2, ',', '.'); ?>€ online</span>
                                    <?php endif; ?>
                                    <?php if (isset($paymentMethodRevenue['Onsite'])): ?>
                                        <span class="badge bg-warning"><?php echo number_format($paymentMethodRevenue['Onsite'], 2, ',', '.'); ?>€ local</span>
                                    <?php endif; ?>
                                    <?php if (isset($paymentMethodRevenue['Marked'])): ?>
                                        <span class="badge bg-success"><?php echo number_format($paymentMethodRevenue['Marked'], 2, ',', '.'); ?>€ marked</span>
                                    <?php endif; ?>
                                    <?php if (isset($paymentMethodRevenue['Extras'])): ?>
                                        <span class="badge bg-danger"><?php echo number_format($paymentMethodRevenue['Extras'], 2, ',', '.'); ?>€ extras</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                  <div class="row mt-4">
                    <!-- Recent Check-ins -->
                    <div class="col-lg-5 col-md-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-list me-2"></i> Últimos Check-ins
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush check-ins-list">                                    <?php foreach ($recentCheckIns as $checkIn): ?>                                        <li class="list-group-item check-in-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($checkIn['name']); ?></strong>
                                                    <div class="text-muted small">
                                                        <?php if (!empty($checkIn['product_name'])): ?>
                                                            <span class="fw-semi-bold"><?php echo htmlspecialchars($checkIn['product_name']); ?></span> · 
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($checkIn['zone'] ?? 'Zona não especificada'); ?>
                                                    </div>
                                                    <div class="mt-1">
                                                        <?php if ($checkIn['type'] == 'invite'): ?>
                                                            <span class="badge bg-secondary">Convite</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-primary">Pago</span>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($checkIn['payment_method'])): ?>
                                                            <?php 
                                                            $badgeClass = $checkIn['payment_method'] == 'Card' ? 'bg-success' : 
                                                                        ($checkIn['payment_method'] == 'Cash' ? 'bg-danger' : 'bg-info');
                                                            ?>
                                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($checkIn['payment_method']); ?></span>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($checkIn['email'])): ?>
                                                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($checkIn['email']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="check-in-time">
                                                    <?php 
                                                    $dt = new DateTime($checkIn['updated_at']);
                                                    echo $dt->format('H:i:s');
                                                    ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if (empty($recentCheckIns)): ?>
                                        <li class="list-group-item text-center py-3">Nenhum check-in ainda</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Check-ins by Hour -->
                    <div class="col-lg-7 col-md-12">
                        <div class="card">                            <div class="card-header bg-success text-white">
                                <i class="fas fa-chart-line me-2"></i> Check-ins por Hora (Hoje)
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="hourlyCheckInsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <!-- Tickets by Type -->
                    <div class="col-md-6">
                        <div class="card">                            <div class="card-header bg-warning text-dark">
                                <i class="fas fa-tags me-2"></i> Bilhetes por Tipo
                                <div class="float-end">
                                    <span class="badge rounded-pill bg-light text-dark me-2">
                                        <i class="fas fa-circle text-primary me-1"></i> Bilhetes Pagos
                                    </span>
                                    <span class="badge rounded-pill bg-light text-dark">
                                        <i class="fas fa-circle text-purple me-1"></i> Convites
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="ticketTypesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Check-ins by Zone -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <i class="fas fa-map-marker-alt me-2"></i> Check-ins por Zona
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="zoneCheckInsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Extras Usage -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <i class="fas fa-plus-circle me-2"></i> Utilização de Extras
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="extrasUsageChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="live_dashboard.js"></script>
    <script>
        // Initialize charts with data
        document.addEventListener('DOMContentLoaded', function() {            const hourlyData = {
                labels: <?php echo json_encode($hourLabels); ?>,
                data: <?php echo json_encode($hourData); ?>,
                paidData: <?php 
                    // Create array for paid check-ins if available, otherwise create empty array
                    $paidData = [];
                    foreach ($hourlyCheckIns as $item) {
                        $hour = (int)$item['hour'];
                        $paidData[$hour] = (int)$item['paid_count'];
                    }
                    // Fill in missing hours with zeros
                    for ($i = 0; $i < 24; $i++) {
                        if (!isset($paidData[$i])) $paidData[$i] = 0;
                    }
                    ksort($paidData);
                    echo json_encode(array_values($paidData));
                ?>,
                inviteData: <?php 
                    // Create array for invite check-ins if available, otherwise create empty array
                    $inviteData = [];
                    foreach ($hourlyCheckIns as $item) {
                        $hour = (int)$item['hour'];
                        $inviteData[$hour] = (int)$item['invite_count'];
                    }
                    // Fill in missing hours with zeros
                    for ($i = 0; $i < 24; $i++) {
                        if (!isset($inviteData[$i])) $inviteData[$i] = 0;
                    }
                    ksort($inviteData);
                    echo json_encode(array_values($inviteData));
                ?>
            };
              const ticketTypesData = {
                labels: <?php echo json_encode(array_column($ticketTypes, 'name')); ?>,
                data: <?php echo json_encode(array_column($ticketTypes, 'count')); ?>,
                types: <?php echo json_encode(array_column($ticketTypes, 'ticket_type')); ?>
            };
            
            const zoneData = {
                labels: <?php echo json_encode(array_column($checkInsByZone, 'name')); ?>,
                data: <?php echo json_encode(array_column($checkInsByZone, 'count')); ?>
            };
            
            const extrasData = {
                labels: <?php echo json_encode(array_column($extrasUsage, 'name')); ?>,
                data: <?php echo json_encode(array_column($extrasUsage, 'count')); ?>
            };
            
            // Initialize charts
            initializeCharts(hourlyData, ticketTypesData, zoneData, extrasData);
            
            // Start fetch cycle
            window.fetchLiveData = function() {
                const urlParams = new URLSearchParams(window.location.search);
                const eventId = urlParams.get('event_id');
                
                if (!eventId) return;
                
                // Fetch dashboard data
                fetch(`live_data.php?event_id=${eventId}`)
                    .then(response => response.json())
                    .then(data => {
                        updateDashboardData(data);
                    })
                    .catch(error => {
                        console.error('Error fetching live data:', error);
                    });
                    
                // Fetch event statistics
                fetch(`event_stats.php?event_id=${eventId}`)
                    .then(response => response.json())
                    .then(data => {
                        updateEventStats(data);
                    })
                    .catch(error => {
                        console.error('Error fetching event stats:', error);
                    });
            }
        });
    </script>
</body>
</html>
