<?php
include 'auth.php';
include 'conexao.php';

// Gerenciar ações
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'remove_equipment':
            if (isset($_GET['id']) && isset($_GET['event_id'])) {
                $equipment_id = (int)$_GET['id'];
                $event_id = (int)$_GET['event_id'];
                $pos_id = isset($_GET['pos_id']) ? (int)$_GET['pos_id'] : null;
                
                // Remove apenas o vínculo com o PDV
                $stmt = $pdo->prepare("DELETE FROM pos_equipment WHERE equipment_id = ?");
                $stmt->execute([$equipment_id]);
                
                $redirect = "event_management.php?id=" . $event_id;
                if ($pos_id) {
                    $redirect .= "&pos_id=" . $pos_id;
                }
                header("Location: " . $redirect);
                exit;
            }
            break;
            
        case 'remove_internet_card':
            if (isset($_GET['equipment_id']) && isset($_GET['event_id'])) {
                $equipment_id = (int)$_GET['equipment_id'];
                $event_id = (int)$_GET['event_id'];
                
                // Remove apenas a associação do cartão com o equipamento
                $stmt = $pdo->prepare("DELETE FROM equipment_internet_cards WHERE equipment_id = ?");
                $stmt->execute([$equipment_id]);
                
                if (isset($_GET['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cartão removido com sucesso'
                    ]);
                    exit;
                }
                
                $pos_id = isset($_GET['pos_id']) ? '&pos_id=' . (int)$_GET['pos_id'] : '';
                header("Location: event_management.php?id=" . $event_id . $pos_id);
                exit;
            }
            break;
            
        case 'delete_equipment':
            if (isset($_GET['id']) && isset($_GET['event_id'])) {
                $equipment_id = (int)$_GET['id'];
                $event_id = (int)$_GET['event_id'];
                $pos_id = isset($_GET['pos_id']) ? '&pos_id=' . (int)$_GET['pos_id'] : '';
                
                // Primeiro remove os cartões de internet associados
                $stmt = $pdo->prepare("DELETE FROM equipment_internet_cards WHERE equipment_id = ?");
                $stmt->execute([$equipment_id]);
                
                // Remove o equipamento do PDV
                $stmt = $pdo->prepare("DELETE FROM pos_equipment WHERE equipment_id = ?");
                $stmt->execute([$equipment_id]);
                
                // Por fim, remove o equipamento
                $stmt = $pdo->prepare("DELETE FROM equipment WHERE id = ?");
                $stmt->execute([$equipment_id]);
                
                header("Location: event_management.php?id=" . $event_id . $pos_id);
                exit;
            }
            break;
        
        case 'get_pdv_html':
            if (isset($_GET['pos_id']) && isset($_GET['event_id'])) {
                $pos_id = (int)$_GET['pos_id'];
                $event_id = (int)$_GET['event_id'];
                  // Buscar dados atualizados do PDV
                $stmt = $pdo->prepare("SELECT 
                    p.*,
                    e.id as equipment_id,
                    e.serial_number,
                    pe.status as equipment_status,
                    pe.notes as equipment_notes,
                    et.name as equipment_type,
                    ic.id as internet_card_id,
                    ic.activation_number as internet_card,
                    ic.phone_number as internet_card_phone,
                    ic.status as internet_card_status,
                    u.id as user_id,
                    u.email as user_email,
                    sp.clear_password as user_password
                FROM pos p 
                JOIN event_pos ep ON ep.pos_id = p.id 
                LEFT JOIN pos_equipment pe ON pe.pos_id = p.id
                LEFT JOIN equipment e ON e.id = pe.equipment_id
                LEFT JOIN equipment_types et ON e.type_id = et.id
                LEFT JOIN equipment_internet_cards eic ON eic.equipment_id = e.id
                LEFT JOIN internet_cards ic ON ic.id = eic.internet_card_id
                LEFT JOIN users u ON u.pos_id = p.id
                LEFT JOIN staff_passwords sp ON sp.user_id = u.id
                WHERE p.id = ? AND ep.event_id = ?
                ORDER BY et.name");
                
                $stmt->execute([$pos_id, $event_id]);
                $results = $stmt->fetchAll();
                
                // Organizar os dados no mesmo formato que a página principal
                $pdv = [
                    'info' => [
                        'id' => $results[0]['id'],
                        'name' => $results[0]['name'],
                        'status' => 'active'  // Default status since column was removed
                    ],
                    'equipment' => [],
                    'users' => []
                ];
                
                foreach ($results as $row) {
                    if ($row['equipment_id'] && !in_array($row['equipment_id'], array_column($pdv['equipment'], 'id'))) {
                        $pdv['equipment'][] = [
                            'id' => $row['equipment_id'],
                            'type' => $row['equipment_type'],
                            'serial_number' => $row['serial_number'],
                            'status' => $row['equipment_status'],
                            'notes' => $row['equipment_notes'],
                            'internet_card_id' => $row['internet_card_id'],
                            'internet_card' => $row['internet_card'],
                            'internet_card_phone' => $row['internet_card_phone'],
                            'internet_card_status' => $row['internet_card_status']
                        ];
                    }
                    if ($row['user_id'] && !in_array($row['user_id'], array_column($pdv['users'], 'id'))) {
                        $pdv['users'][] = [
                            'id' => $row['user_id'],
                            'email' => $row['user_email'],
                            'password' => $row['user_password']
                        ];
                    }
                }
                
                // Iniciar buffer de saída para capturar o HTML
                ob_start();
                include 'pdv_content.php';
                $html = ob_get_clean();
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'html' => $html]);
                exit;
            }
            break;
        
        case 'remove_extra':
            if (isset($_GET['extra_id']) && isset($_GET['pos_id']) && isset($_GET['event_id'])) {
                $extra_id = (int)$_GET['extra_id'];
                $pos_id = (int)$_GET['pos_id'];
                $event_id = (int)$_GET['event_id'];
                
                // Remove o vínculo entre o extra e o PDV
                $stmt = $pdo->prepare("DELETE FROM extra_pos WHERE extra_id = ? AND pos_id = ?");
                $stmt->execute([$extra_id, $pos_id]);
                
                header("Location: event_management.php?id=" . $event_id . "&pos_id=" . $pos_id);
                exit;
            }
            break;
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Buscar informações do evento
$stmt = $pdo->prepare("
    SELECT * FROM events WHERE id = ?
");
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    header("Location: eventos.php");
    exit;
}

// Buscar todos os PDVs associados ao evento, seus equipamentos, usuários e extras
$stmt = $pdo->prepare("SELECT 
        p.*,
        e.id as equipment_id,
        e.serial_number,
        pe.status as equipment_status,
        pe.notes as equipment_notes,
        et.name as equipment_type,
        ic.id as internet_card_id,
        ic.activation_number as internet_card,
        ic.phone_number as internet_card_phone,
        ic.status as internet_card_status,
        u.id as user_id,
        u.email as user_email,
        sp.clear_password as user_password,
        ex.id as extra_id,
        ex.name as extra_name,
        ex.display_name as extra_display_name,
        ex.price as extra_price,
        ex.tax_type as extra_tax_type,
        ex.type as extra_type,
        ex.toconline_item_code as extra_item_code,
        ex.toconline_item_id as extra_item_id,
        ec.id as extra_category_id,
        ec.name as extra_category_name
    FROM pos p 
    JOIN event_pos ep ON ep.pos_id = p.id 
    LEFT JOIN pos_equipment pe ON pe.pos_id = p.id
    LEFT JOIN equipment e ON e.id = pe.equipment_id
    LEFT JOIN equipment_types et ON e.type_id = et.id
    LEFT JOIN equipment_internet_cards eic ON eic.equipment_id = e.id
    LEFT JOIN internet_cards ic ON ic.id = eic.internet_card_id
    LEFT JOIN users u ON u.pos_id = p.id
    LEFT JOIN staff_passwords sp ON sp.user_id = u.id
    LEFT JOIN extra_pos exp ON exp.pos_id = p.id
    LEFT JOIN extras ex ON ex.id = exp.extra_id
    LEFT JOIN extra_categories ec ON ec.id = ex.extra_category_id
    WHERE ep.event_id = ?    ORDER BY p.name, ISNULL(ec.name), ec.name, ex.name");
$stmt->execute([$id]);
$results = $stmt->fetchAll();

// Organizar os resultados por PDV
$pdvs = [];
foreach ($results as $row) {
    $pos_id = $row['id'];
    if (!isset($pdvs[$pos_id])) {        $pdvs[$pos_id] = [
            'info' => [
                'id' => $row['id'],
                'name' => $row['name'],
                'status' => 'active'  // Default status since column was removed
            ],
            'equipment' => [],
            'users' => [],
            'extras' => []
        ];
    }
    
    if ($row['equipment_id'] && !in_array($row['equipment_id'], array_column($pdvs[$pos_id]['equipment'], 'id'))) {
        $pdvs[$pos_id]['equipment'][] = [
            'id' => $row['equipment_id'],
            'type' => $row['equipment_type'],
            'serial_number' => $row['serial_number'],
            'status' => $row['equipment_status'],
            'notes' => $row['equipment_notes'],
            'internet_card_id' => $row['internet_card_id'],
            'internet_card' => $row['internet_card'],
            'internet_card_phone' => $row['internet_card_phone'],
            'internet_card_status' => $row['internet_card_status']
        ];
    }
    
    if ($row['user_id'] && !in_array($row['user_id'], array_column($pdvs[$pos_id]['users'], 'id'))) {
        $pdvs[$pos_id]['users'][] = [
            'id' => $row['user_id'],
            'email' => $row['user_email'],
            'password' => $row['user_password']
        ];
    }

    if ($row['extra_id'] && !in_array($row['extra_id'], array_column($pdvs[$pos_id]['extras'], 'id'))) {
        $pdvs[$pos_id]['extras'][] = [
            'id' => $row['extra_id'],
            'name' => $row['extra_name'],
            'display_name' => $row['extra_display_name'],
            'price' => $row['extra_price'],
            'tax_type' => $row['extra_tax_type'],
            'type' => $row['extra_type'],
            'toconline_item_code' => $row['extra_item_code'],
            'toconline_item_id' => $row['extra_item_id'],
            'category_id' => $row['extra_category_id'],
            'category_name' => $row['extra_category_name']
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gestão do Evento - <?php echo htmlspecialchars($event['name']); ?></title>    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            margin: 0;
        }
        .summary-cards {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            flex: 1;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.2s;
        }
        .summary-card:hover {
            transform: translateY(-2px);
        }
        .card-icon {
            font-size: 24px;
            background: #f8f9fa;
            width: 48px;
            height: 48px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-content {
            flex: 1;
        }
        .card-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 4px;
        }
        .card-label {
            color: #6c757d;
            font-size: 14px;
        }
        .filters-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .search-container {
            flex: 1;
        }
        .category-filter {
            width: 250px;
        }
        .category-filter select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            background-color: white;
        }
        .event-info {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .search-container {
            margin-bottom: 20px;
        }
        #searchInput {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .pos-container {
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .pos-header {
            padding: 15px;
            background-color: #f9f9f9;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pos-header:hover {
            background-color: #f0f0f0;
        }
        .pos-content {
            padding: 0 15px;
            display: none;
            background: #fff;
            border-radius: 0 0 4px 4px;
        }

        .pos-content.active {
            display: block;
            padding-bottom: 20px;
        }

        .pos-content section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .pos-content h4 {
            color: #2c3e50;
            font-size: 1.1em;
            margin: 0 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }

        .pos-content table {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            overflow: hidden;
        }

        .pos-content section + section {
            margin-top: 25px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-assigned {
            background-color: #cce5ff;
            color: #004085;
        }
        .status-returned {
            background-color: #d4edda;
            color: #155724;
        }
        .status-lost {
            background-color: #f8d7da;
            color: #721c24;
        }
        .btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            margin-left: 5px;
        }
        .btn-edit {
            background-color: #007bff;
            color: white;
        }
        .btn-edit:hover {
            background-color: #0056b3;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }        .notes {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .actions-container {
            margin-top: 20px;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
            font-size: 14px;
            padding: 10px 20px;
        }
        .sidebar {
            position: fixed;
            right: 0;
            top: 0;
            width: 400px;
            height: 100%;
            background: white;
            box-shadow: -2px 0 5px rgba(0,0,0,0.1);
            padding: 20px;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar.active {
            transform: translateX(0);
        }
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 4px;
            width: 400px;
        }
        
        #userAssignModal .modal-content {
            margin: 10% auto;
        }
        .modal-title {
            margin: 0 0 15px 0;
            font-size: 18px;
        }
        .modal-buttons {
            margin-top: 20px;
            text-align: right;
        }
        .btn-confirm {
            background-color: #f44336;
            color: white;
            margin-left: 10px;
        }
        .btn-cancel {
            background-color: #666;
            color: white;
        }
        /* Add some spacing for the action buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .btn-remove {
            background-color: #ff9800;
            color: white;
        }

        /* Estilo zebrado para os PDVs */
        .pos-container:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .pos-container:nth-child(odd) {
            background-color: #ffffff;
        }

        /* Estilo zebrado para linhas das tabelas */
        .pos-content table tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .pos-content table tr:nth-child(odd) {
            background-color: #ffffff;
        }

        /* Hover effects */
        .pos-content table tr:hover {
            background-color: #e9ecef;
        }

        .pos-container:hover {
            border-color: #adb5bd;
        }

        /* Ajustes nas tabelas */
        .pos-content table th {
            background-color: #e9ecef;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }

        .pos-content table td {
            border-bottom: 1px solid #dee2e6;
            padding: 12px 8px;
        }

        /* Melhorar contraste das seções */
        .pos-content section {
            border: 1px solid #dee2e6;
            margin: 20px 0;
        }        .pos-header {
            background-color: #f1f3f5;
            border-bottom: 1px solid #dee2e6;
        }

        .edit-pos-btn {
            padding: 2px 8px;
            background: none;
            border: none;
            cursor: pointer;
            margin-left: 10px;
            font-size: 16px;
            vertical-align: middle;
            opacity: 0.7;
        }

        .edit-pos-btn:hover {
            opacity: 1;
        }

        .add-equipment-btn {
            padding: 2px 8px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
            font-size: 14px;
        }

        .add-equipment-btn:hover {
            background: #388E3C;
        }

        /* Espaçamento consistente */
        .pos-content table {
            margin: 0;  /* Remove margem da tabela pois a seção já tem margem */
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        section {
            margin-bottom: 30px;
        }
        h4 {
            margin-bottom: 15px;
            font-size: 1.2em;
            color: #333;
        }
        .extras-category {
            margin-bottom: 20px;
        }
        .extras-category h5 {
            margin: 10px 0;
            padding: 5px 0;
            font-size: 1.1em;
            color: #444;
            border-bottom: 1px solid #eee;
        }
        .equipment-section,
        .users-section,
        .extras-section {
            background: #fff;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .status-inactive {
            background-color: #ffebee;
            color: #c62828;
        }
        .btn {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 14px;
        cursor: pointer;
        text-decoration: none;
        border: none;
        margin-right: 5px;
        }
        .btn:last-child {
        margin-right: 0;
        }
        .btn-primary {
        background-color: #2196f3;
        color: white;
        }
        .btn-primary:hover {
        background-color: #1976d2;
        }
        .btn-edit {
        background-color: #4caf50;
        color: white;
        }
        .btn-edit:hover {
        background-color: #388e3c;
        }
        .btn-remove {
        background-color: #ff9800;
        color: white;
        }
        .btn-remove:hover {
        background-color: #f57c00;
        }
        .btn-delete {
        background-color: #f44336;
        color: white;
        }
        .btn-delete:hover {
        background-color: #d32f2f;
        }
        .action-buttons {
            white-space: nowrap;
            display: flex;
            gap: 5px;
        }
        .action-buttons .btn {
            margin: 0;
        }

        /* Extra sidebar styles */
        .extras-sidebar-container {
            display: none;
            position: fixed;
            top: 0;
            right: 0;
            height: 100vh;
            width: 400px;
            background: white;
            box-shadow: -2px 0 5px rgba(0,0,0,0.2);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
        }        .extras-sidebar-container.active {
            transform: translateX(0);
            display: block;
        }

        iframe#extrasSidebar {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Edit PDV Dialog styles */
        #editPosDialog.modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.2s;
        }

        #editPosDialog .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            animation: slideIn 0.3s;
            overflow: hidden;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-100px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>    <div class="event-info">
        <h1><?php echo htmlspecialchars($event['name']); ?></h1>
        <?php if ($event['location']): ?>
            <p>Local: <?php echo htmlspecialchars($event['location']); ?></p>
        <?php endif; ?>
        <p>Data: <?php echo date('d/m/Y', strtotime($event['start_at'])); ?></p>          <div class="actions-container">            <a href="event_pos_form.php?event_id=<?php echo $id; ?>" class="btn btn-primary">
                + Associar Ponto de Venda
            </a>
            <a href="export_internet_cards.php?event_id=<?php echo $id; ?>" class="btn btn-primary" style="margin-left: 10px;">
                <i class="fas fa-file-excel"></i> Exportar Cartões de Internet
            </a>
            <a href="importar_extras.php?event_id=<?php echo $id; ?>" class="btn btn-primary" style="margin-left: 10px;">
                <i class="fas fa-file-import"></i> Importar Extras
            </a>
            <a href="associar_extras.php?event_id=<?php echo $id; ?>" class="btn btn-primary" style="margin-left: 10px;">
                <i class="fas fa-file-import"></i> Associar Extras
            </a>
            <a href="gerar_instrucoes_gas.php?event_id=<?php echo $id; ?>" class="btn btn-primary" style="margin-left: 10px;">
                <i class="fas fa-file-import"></i> Criar Folhas de Rosto
            </a>
        </div>
    </div>    <div class="summary-cards">
        <div class="summary-card">
            <div class="card-icon">📍</div>
            <div class="card-content">
                <div class="card-value"><?php echo count($pdvs); ?></div>
                <div class="card-label">Pontos de Venda</div>
            </div>
        </div>
        <div class="summary-card">
            <div class="card-icon">🔧</div>
            <div class="card-content">
                <div class="card-value">
                    <?php
                    $totalEquipment = 0;
                    foreach ($pdvs as $pdv) {
                        $totalEquipment += count($pdv['equipment']);
                    }
                    echo $totalEquipment;
                    ?>
                </div>
                <div class="card-label">Equipamentos</div>
            </div>
        </div>
        <div class="summary-card">
            <div class="card-icon">📶</div>
            <div class="card-content">
                <div class="card-value">
                    <?php
                    $totalCards = 0;
                    foreach ($pdvs as $pdv) {
                        foreach ($pdv['equipment'] as $equipment) {
                            if (!empty($equipment['internet_card'])) {
                                $totalCards++;
                            }
                        }
                    }
                    echo $totalCards;
                    ?>
                </div>
                <div class="card-label">Cartões de Internet</div>
            </div>
        </div>
        <div class="summary-card">
            <div class="card-icon">🍽️</div>
            <div class="card-content">
                <div class="card-value">
                    <?php
                    $totalExtras = 0;
                    $uniqueExtras = [];
                    foreach ($pdvs as $pdv) {
                        foreach ($pdv['extras'] as $extra) {
                            if (!in_array($extra['id'], $uniqueExtras)) {
                                $uniqueExtras[] = $extra['id'];
                                $totalExtras++;
                            }
                        }
                    }
                    echo $totalExtras;
                    ?>
                </div>
                <div class="card-label">Extras</div>
            </div>
        </div>
        <div class="summary-card">
            <div class="card-icon">👤</div>
            <div class="card-content">
                <div class="card-value">
                    <?php
                    $totalUsers = 0;
                    $uniqueUsers = [];
                    foreach ($pdvs as $pdv) {
                        foreach ($pdv['users'] as $user) {
                            if (!in_array($user['id'], $uniqueUsers)) {
                                $uniqueUsers[] = $user['id'];
                                $totalUsers++;
                            }
                        }
                    }
                    echo $totalUsers;
                    ?>
                </div>
                <div class="card-label">Usuários</div>
            </div>
        </div>
    </div>

    <div class="filters-container">
        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Pesquisar pontos de venda..." autocomplete="off">
        </div>
        <div class="category-filter">
            <select id="categoryFilter">
                <option value="">Todas as categorias</option>
                <?php
                $stmt = $pdo->query("SELECT id, name FROM pos_categories ORDER BY name");
                while ($category = $stmt->fetch()) {
                    echo '<option value="' . $category['id'] . '">' . htmlspecialchars($category['name']) . '</option>';
                }
                ?>
            </select>
        </div>
    </div><div id="posList">
        <?php foreach ($pdvs as $pos_id => $pdv): ?>
            <?php
            // Get categories for this PDV
            $stmt = $pdo->prepare("
                SELECT GROUP_CONCAT(category_id) as categories 
                FROM pos_categories_assignments 
                WHERE pos_id = ?
            ");
            $stmt->execute([$pos_id]);
            $categories = $stmt->fetch()['categories'];
            ?>
            <div class="pos-container" 
                data-name="<?php echo htmlspecialchars(strtolower($pdv['info']['name'])); ?>" 
                data-pos-id="<?php echo $pos_id; ?>"
                data-categories="<?php echo htmlspecialchars($categories); ?>">                <div class="pos-header" onclick="togglePOS(this)">                    <div>                        <strong><?php echo htmlspecialchars($pdv['info']['name']) . ' - ' . $pos_id; ?></strong>
                        <?php
                        // Renderizar categorias como tags
                        if (!empty($categories)) {
                            $categoryIds = explode(',', $categories);
                            $stmtCat = $pdo->query("SELECT id, name FROM pos_categories");
                            $allCategories = [];
                            while ($cat = $stmtCat->fetch()) {
                                $allCategories[$cat['id']] = $cat['name'];
                            }
                            foreach ($categoryIds as $catId) {
                                if (isset($allCategories[$catId])) {
                                    echo '<span class="pos-category-tag">' . htmlspecialchars($allCategories[$catId]) . '</span> ';
                                }
                            }
                        } else {
                            echo '<span class="pos-category-tag no-category">Sem categoria</span>';
                        }
                        ?>
                        <button class="edit-pos-btn" onclick="editPDV(event, <?php echo $pos_id; ?>)">✏️</button>
                    </div>
                </div>
                <div class="pos-content">                    <section class="equipment-section">
                        <h4>Equipamentos <button class="add-equipment-btn" onclick="openEquipmentSidebar(event, <?php echo $pos_id; ?>)">+</button></h4>
                        <?php if (!empty($pdv['equipment'])): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Número de Série</th>
                                        <th>Status</th>
                                        <th>Cartões de Internet</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pdv['equipment'] as $equipment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($equipment['type']); ?></td>
                                            <td><?php echo htmlspecialchars($equipment['serial_number']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $equipment['status']; ?>">
                                                    <?php
                                                    switch ($equipment['status']) {
                                                        case 'active':
                                                            echo 'Em Uso';
                                                            break;
                                                        case 'inactive':
                                                            echo 'Inativo';
                                                            break;
                                                        default:
                                                            echo ucfirst($equipment['status']);
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td>                                            <?php if ($equipment['internet_card']): ?>
                                                <?php echo htmlspecialchars($equipment['internet_card_id'] . ' - ' . $equipment['internet_card']); ?>
                                                <?php if (!empty($equipment['internet_card_pin']) || !empty($equipment['internet_card_puk'])): ?>
                                                    <?php if (!empty($equipment['internet_card_pin'])): ?>
                                                        <span style="margin-left:8px;">🔑 PIN: <?php echo htmlspecialchars($equipment['internet_card_pin']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($equipment['internet_card_puk'])): ?>
                                                        <span style="margin-left:8px;">🛡️ PUK: <?php echo htmlspecialchars($equipment['internet_card_puk']); ?></span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($equipment['internet_card_status']): ?>
                                                    <span class="status-badge status-<?php echo $equipment['internet_card_status']; ?>">
                                                        <?php echo $equipment['internet_card_status'] === 'active' ? 'Ativo' : 'Inativo'; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <div class="action-buttons" style="margin-top:4px;">
                                                    <a href="internet_card_form.php?id=<?php echo $equipment['internet_card_id']; ?>&event_id=<?php echo $id; ?>&pos_id=<?php echo $pos_id; ?><?php echo isset($_GET['category']) ? '&category='.urlencode($_GET['category']) : ''; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>" class="btn btn-edit" title="Editar Cartão de Internet">✏️</a>
                                                    <button class="btn btn-remove" onclick="removeInternetCard(<?php echo $equipment['id']; ?>, event)">Remover</button>
                                                </div>
                                            <?php else: ?>
                                                <a href="equipment_internet_form.php?id=<?php echo $equipment['id']; ?>&event_id=<?php echo $id; ?>&pos_id=<?php echo $pos_id; ?><?php echo isset($_GET['category']) ? '&category='.urlencode($_GET['category']) : ''; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>" class="btn btn-edit">Adicionar</a>
                                            <?php endif; ?>
                                        </td>                                        <td class="action-buttons">
                                            <a href="equipment_form.php?id=<?php echo $equipment['id']; ?>&event_id=<?php echo $id; ?>&pos_id=<?php echo $pos_id; ?><?php echo isset($_GET['category']) ? '&category='.urlencode($_GET['category']) : ''; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>" class="btn btn-edit">Editar</a>
                                            <button class="btn btn-remove" onclick="showModal('remove', <?php echo $equipment['id']; ?>, <?php echo $pos_id; ?>)">Remover</button>
                                            <button class="btn btn-delete" onclick="showModal('delete', <?php echo $equipment['id']; ?>, <?php echo $pos_id; ?>)">Excluir</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>                    <?php else: ?>
                            <p>Nenhum equipamento associado a este PDV.</p>
                        <?php endif; ?>
                    </section>                    <section class="extras-section">
                        <h4>Extras</h4>
                        <?php if (!empty($pdv['extras'])): ?>
                            <?php 
                            // Organize extras by category
                            $extrasByCategory = [];
                            $noCategory = [];
                            
                            foreach ($pdv['extras'] as $extra) {
                                if (!empty($extra['category_id']) && !empty($extra['category_name'])) {
                                    if (!isset($extrasByCategory[$extra['category_id']])) {
                                        $extrasByCategory[$extra['category_id']] = [
                                            'name' => $extra['category_name'],
                                            'extras' => []
                                        ];
                                    }
                                    $extrasByCategory[$extra['category_id']]['extras'][] = $extra;
                                } else {
                                    $noCategory[] = $extra;
                                }
                            }
                            
                            // Display extras by category
                            foreach ($extrasByCategory as $categoryId => $category): ?>
                                <div class="extras-category">
                                    <h5><?php echo htmlspecialchars($category['name']); ?></h5>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nome</th>
                                                <th>Preço</th>
                                                <th>Tipo de Taxa</th>
                                                <th>Tipo</th>
                                                <th>TOC Item Code</th>
                                                <th>TOC Item ID</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($category['extras'] as $extra): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($extra['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($extra['name']); ?></td>
                                                    <td><?php echo number_format($extra['price'], 2, ',', '.'); ?></td>
                                                    <td><?php echo htmlspecialchars($extra['tax_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($extra['type']); ?></td>
                                                    <td><?php echo htmlspecialchars($extra['toconline_item_code']); ?></td>
                                                    <td><?php echo htmlspecialchars($extra['toconline_item_id']); ?></td>
                                                    <td class="action-buttons">
                                                        <a href="extra_form.php?id=<?php echo $extra['id']; ?>&event_id=<?php echo $id; ?>" class="btn btn-edit">Editar</a>
                                                        <button class="btn btn-remove" onclick="removeExtra(<?php echo $pos_id; ?>, <?php echo $extra['id']; ?>)">Remover</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (!empty($noCategory)): ?>
                                <div class="extras-category">
                                    <h5>Sem Categoria</h5>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nome</th>
                                                <th>Preço</th>
                                                <th>Tipo de Taxa</th>
                                                <th>Tipo</th>
                                                <th>TOC Item Code</th>
                                                <th>TOC Item ID</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($noCategory as $extra): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($extra['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($extra['name']); ?></td>
                                                    <td><?php echo number_format($extra['price'], 2, ',', '.'); ?></td>
                                                    <td><?php echo htmlspecialchars($extra['tax_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($extra['type']); ?></td>
                                                    <td><?php echo htmlspecialchars($extra['toconline_item_code']); ?></td>
                                                    <td><?php echo htmlspecialchars($extra['toconline_item_id']); ?></td>
                                                    <td class="action-buttons">
                                                        <a href="extra_form.php?id=<?php echo $extra['id']; ?>&event_id=<?php echo $id; ?>" class="btn btn-edit">Editar</a>
                                                        <button class="btn btn-remove" onclick="removeExtra(<?php echo $pos_id; ?>, <?php echo $extra['id']; ?>)">Remover</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>Nenhum extra associado a este PDV.</p>
                        <?php endif; ?>
                        <div style="margin-top: 10px;">
                            <button class="btn btn-primary" onclick="openExtrasSidebar(event, <?php echo $pos_id; ?>)">+ Adicionar Extra</button>
                        </div>
                    </section>

                    <section class="users-section">
                        <h4>Usuários</h4>
                        <?php if (!empty($pdv['users'])): ?>
                            <table>
                                <thead>
                                    <tr>                                    <th>Email</th>
                                    <th>Senha</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pdv['users'] as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['password']); ?></td>
                                        <td class="action-buttons">
                                            <a href="users.php?id=<?php echo $user['id']; ?>&event_id=<?php echo $id; ?><?php echo isset($_GET['category']) ? '&category='.urlencode($_GET['category']) : ''; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>" class="btn btn-edit">Editar</a>
                                            <button class="btn btn-remove" onclick="removeUser(<?php echo $pos_id; ?>, <?php echo $user['id']; ?>)">Remover</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div style="margin-top: 10px;">
                            <button class="btn btn-primary" onclick="addUser(<?php echo $pos_id; ?>)">+ Adicionar Usuário</button>
                        </div>
                    <?php else: ?>
                        <p>Nenhum usuário associado a este PDV.</p>
                        <div style="margin-top: 10px;">
                            <button class="btn btn-primary" onclick="addUser(<?php echo $pos_id; ?>)">+ Adicionar Usuário</button>
                        </div>
                    <?php endif; ?>
                    </section>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Action Confirmation Modal -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title" id="modalTitle"></h3>
            <p id="modalMessage"></p>
            <div class="modal-buttons">
                <button class="btn btn-cancel" onclick="closeModal()">Cancelar</button>
                <button class="btn btn-confirm" id="confirmButton">Confirmar</button>
            </div>
        </div>
    </div>

    <script>
        let actionType = '';
        let equipmentId = null;

        // Remove apenas o vínculo com o PDV        // Função auxiliar para preservar os filtros na URL
        function getFilterParams() {
            const urlParams = new URLSearchParams(window.location.search);
            let filterParams = '';
            
            const category = urlParams.get('category');
            if (category) {
                filterParams += `&category=${encodeURIComponent(category)}`;
            }
            
            const search = urlParams.get('search');
            if (search) {
                filterParams += `&search=${encodeURIComponent(search)}`;
            }
            
            return filterParams;
        }
        
        function removeEquipment(id, posId) {
            const filterParams = getFilterParams();
            window.location.href = `event_management.php?action=remove_equipment&id=${id}&event_id=<?php echo $id; ?>&pos_id=${posId}${filterParams}`;
        }

        function deleteEquipment(id, posId) {
            const filterParams = getFilterParams();
            window.location.href = `event_management.php?action=delete_equipment&id=${id}&event_id=<?php echo $id; ?>&pos_id=${posId}${filterParams}`;
        }

        function showModal(type, id, posId) {
            actionType = type;
            equipmentId = id;
            const modal = document.getElementById('actionModal');
            const title = document.getElementById('modalTitle');
            const message = document.getElementById('modalMessage');
            const confirmButton = document.getElementById('confirmButton');

            modal.style.display = 'block';

            if (type === 'delete') {
                title.textContent = 'Excluir Equipamento';
                message.textContent = 'Esta ação excluirá permanentemente o equipamento do sistema. Tem certeza que deseja prosseguir?';
                confirmButton.onclick = () => deleteEquipment(id, posId);
            } else if (type === 'remove') {
                title.textContent = 'Remover Equipamento';
                message.textContent = 'Esta ação removerá o vínculo do equipamento com este PDV, mas manterá o equipamento no sistema. Tem certeza que deseja prosseguir?';
                confirmButton.onclick = () => removeEquipment(id, posId);
            }
        }

        function closeModal() {
            const modal = document.getElementById('actionModal');
            modal.style.display = 'none';
        }

        function removeInternetCard(equipmentId, event) {
            if (confirm('Tem certeza que deseja remover o cartão de internet deste equipamento?')) {
                event.preventDefault();
                const cell = event.target.closest('td');
                const posId = event.target.closest('.pos-container').getAttribute('data-pos-id');
                
                cell.innerHTML = '<span>Removendo...</span>';
                
                fetch(`event_management.php?action=remove_internet_card&equipment_id=${equipmentId}&event_id=<?php echo $id; ?>&pos_id=${posId}&ajax=1`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro na resposta do servidor');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            const filterParams = getFilterParams();
                            cell.innerHTML = '<a href="equipment_internet_form.php?id=' + equipmentId + '&event_id=<?php echo $id; ?>&pos_id=' + posId + filterParams + '" class="btn btn-edit">Adicionar</a>';
                        } else {
                            throw new Error(data.message || 'Erro ao remover o cartão');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Erro ao remover o cartão. Por favor, tente novamente.');
                    });
            }
        }

        function updatePDVContent(posId) {
            const posContainer = document.querySelector(`.pos-container[data-pos-id="${posId}"]`);
            if (posContainer) {
                const content = posContainer.querySelector('.pos-content');
                if (content) {
                    content.innerHTML = '<div style="text-align: center; padding: 20px;">Atualizando...</div>';
                    
                    fetch(`event_management.php?action=get_pdv_html&pos_id=${posId}&event_id=<?php echo $id; ?>`)
                        .then(response => {
                            if (!response.ok) throw new Error('Erro na resposta do servidor');
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                content.innerHTML = data.html;
                                content.classList.add('active');
                                posContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            } else {
                                throw new Error('Resposta inválida do servidor');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            content.innerHTML = '<div style="text-align: center; padding: 20px; color: red;">Erro ao atualizar. Por favor, recarregue a página.</div>';
                        });
                }
            }
        }

        function togglePOS(header) {
            const content = header.nextElementSibling;
            if (content) {
                content.classList.toggle('active');
            }
        }

        function onEquipmentAdded(posId) {
            closeEquipmentSidebar();
            updatePDVContent(posId);
        }
          window.onclick = function(event) {
            const actionModal = document.getElementById('actionModal');
            const userModal = document.getElementById('userAssignModal');
            
            if (event.target == actionModal) {
                closeModal();
            }
            
            if (event.target == userModal) {
                closeUserModal();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            const posContainers = document.querySelectorAll('.pos-container');

            function filterPOS() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const selectedCategory = document.getElementById('categoryFilter').value;

                posContainers.forEach(container => {
                    const name = container.getAttribute('data-name');
                    const categories = (container.getAttribute('data-categories') || '').split(',').map(c => c.trim());
                    
                    const matchesSearch = name.includes(searchTerm);
                    const matchesCategory = !selectedCategory || categories.includes(selectedCategory);
                    
                    if (matchesSearch && matchesCategory) {
                        container.style.display = 'block';
                    } else {
                        container.style.display = 'none';
                    }
                });
            }            searchInput.addEventListener('input', function() {
                // Atualizar a URL com o termo de busca
                const searchTerm = searchInput.value.trim();
                updateUrlParams('search', searchTerm);
                filterPOS();
            });
            
            categoryFilter.addEventListener('change', function() {
                // Atualizar a URL com a categoria selecionada
                const categoryValue = categoryFilter.value;
                updateUrlParams('category', categoryValue);
                filterPOS();
            });
              // Função para atualizar parâmetros na URL sem recarregar a página
            function updateUrlParams(param, value) {
                const url = new URL(window.location.href);
                
                if (value) {
                    url.searchParams.set(param, value);
                } else {
                    url.searchParams.delete(param);
                }
                
                // Atualizar URL sem recarregar a página
                window.history.replaceState({}, '', url);
            }
            
            // Função auxiliar para obter parâmetros de filtro
            function getFilterParams() {
                const urlParams = new URLSearchParams(window.location.search);
                let filterParams = '';
                
                const category = urlParams.get('category');
                if (category) {
                    filterParams += `&category=${encodeURIComponent(category)}`;
                }
                
                const search = urlParams.get('search');
                if (search) {
                    filterParams += `&search=${encodeURIComponent(search)}`;
                }
                
                return filterParams;
            }
            
            // Aplicar filtros da URL quando a página carrega
            const urlParams = new URLSearchParams(window.location.search);
            
            // Se há um filtro de categoria na URL, selecionar no dropdown
            const categoryParam = urlParams.get('category');
            if (categoryParam) {
                const categorySelect = document.getElementById('categoryFilter');
                if (categorySelect) {
                    categorySelect.value = categoryParam;
                }
            }
            
            // Se há um termo de pesquisa na URL, preencher o campo de busca
            const searchParam = urlParams.get('search');
            if (searchParam) {
                const searchField = document.getElementById('searchInput');
                if (searchField) {
                    searchField.value = decodeURIComponent(searchParam);
                }
            }
              // Aplicar os filtros se algum parâmetro foi encontrado
            if (categoryParam || searchParam) {
                filterPOS();
            }
            
            // Adicionar parâmetros de filtro a todos os links relevantes na página
            function addFilterParamsToLinks() {
                const filterParams = getFilterParams();
                if (!filterParams) return; // Se não há filtros, não faz nada
                
                // Lista de padrões de URL para adicionar os parâmetros de filtro
                const urlPatterns = [
                    'equipment_form.php',
                    'equipment_internet_form.php',
                    'users.php',
                    'extra_form.php'
                ];
                
                // Encontrar todos os links na página
                const links = document.querySelectorAll('a[href]');
                
                links.forEach(link => {
                    const href = link.getAttribute('href');
                    
                    // Verificar se o link corresponde a algum dos padrões
                    if (urlPatterns.some(pattern => href.includes(pattern) && href.includes('event_id='))) {
                        // Verifica se o link já tem os parâmetros de filtro
                        if (!href.includes('category=') && !href.includes('search=')) {
                            // Adicionar os parâmetros de filtro ao link
                            link.setAttribute('href', href + filterParams);
                        }
                    }
                });
            }
            
            // Adicionar parâmetros de filtro aos links quando a página carrega
            addFilterParamsToLinks();
            
            // Adicionar observador para monitorar mudanças na DOM
            // Isso garante que links adicionados dinamicamente também recebam os parâmetros
            const observer = new MutationObserver(addFilterParamsToLinks);
            observer.observe(document.body, { childList: true, subtree: true });

            // Verificar se há um pos_id na URL para expandir automaticamente
            const posIdToExpand = urlParams.get('pos_id');
            if (posIdToExpand) {
                const posContainer = document.querySelector(`.pos-container[data-pos-id="${posIdToExpand}"]`);
                if (posContainer) {
                    const content = posContainer.querySelector('.pos-content');
                    if (content) {
                        content.classList.add('active');
                        posContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            }
        });        function editPDV(event, posId) {
            event.stopPropagation(); // Prevent PDV from expanding/collapsing
            
            // Create dialog container if it doesn't exist
            let dialog = document.getElementById('editPosDialog');
            if (!dialog) {
                dialog = document.createElement('div');
                dialog.id = 'editPosDialog';
                dialog.className = 'modal';
                dialog.innerHTML = `
                    <div class="modal-content" style="width: 600px; max-width: 90%;">
                        <iframe id="editPosFrame" style="width: 100%; height: 400px; border: none;"></iframe>
                    </div>
                `;
                document.body.appendChild(dialog);
            }

            // Load the edit form in the iframe
            const iframe = document.getElementById('editPosFrame');
            iframe.src = `editar_pos.php?id=${posId}`;
            dialog.style.display = 'block';
        }

        function closeEditPosDialog() {
            const dialog = document.getElementById('editPosDialog');
            if (dialog) {
                dialog.style.display = 'none';
            }
        }

        function onPosUpdated(pos) {
            // Update the PDV header with new information
            const posContainer = document.querySelector(`.pos-container[data-pos-id="${pos.id}"]`);
            if (posContainer) {
                const header = posContainer.querySelector('.pos-header strong');
                if (header) {
                    header.textContent = `${pos.name} - ${pos.id}`;
                }
                const statusBadge = posContainer.querySelector('.status-badge');
                if (statusBadge) {
                    statusBadge.className = `status-badge status-${pos.status}`;
                    statusBadge.textContent = pos.status === 'active' ? 'Ativo' : 'Inativo';
                }                // Notes functionality removed
            }
        }

        function addExtra(posId) {
            window.location.href = `criar_extra.php?pos_id=${posId}&event_id=<?php echo $id; ?>`;
        }        function removeExtra(posId, extraId) {
            if (confirm('Tem certeza que deseja remover este extra do PDV? O extra continuará disponível para ser usado em outros PDVs.')) {
                window.location.href = `event_management.php?action=remove_extra&extra_id=${extraId}&pos_id=${posId}&event_id=<?php echo $id; ?>`;
            }
        }
          function addUser(posId) {
            // Criar o modal de seleção de usuário se não existir
            let userModal = document.getElementById('userAssignModal');
            if (!userModal) {
                userModal = document.createElement('div');
                userModal.id = 'userAssignModal';
                userModal.className = 'modal';
                userModal.innerHTML = `
                    <div class="modal-content" style="width: 600px; max-width: 90%; padding: 20px;">
                        <h3>Associar Usuário ao PDV</h3>
                        <div id="userAssignContent" style="margin-top: 20px;">
                            <p>Carregando usuários disponíveis...</p>
                        </div>
                        <div class="modal-buttons" style="margin-top: 15px; text-align: right;">
                            <button class="btn btn-cancel" onclick="closeUserModal()">Fechar</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(userModal);
            }
            
            // Mostrar o modal
            userModal.style.display = 'block';
            
            // Carregar a lista de usuários disponíveis
            fetch(`pos_users.php?action=list&pos_id=${posId}&event_id=<?php echo $id; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const userContent = document.getElementById('userAssignContent');
                        if (data.users.length === 0) {
                            userContent.innerHTML = `<p>Não há usuários disponíveis para associação. <a href="criar_user.php?pos_id=${posId}&event_id=<?php echo $id; ?>${getFilterParams()}" target="_blank">Criar novo usuário</a></p>`;
                        } else {
                            let html = `<p>Selecione um usuário para associar a este PDV:</p>
                                        <div style="max-height: 300px; overflow-y: auto; margin-top: 10px;">
                                        <table style="width: 100%;">
                                            <thead>
                                                <tr>
                                                    <th>Nome</th>
                                                    <th>Email</th>
                                                    <th>Ação</th>
                                                </tr>
                                            </thead>
                                            <tbody>`;
                            
                            data.users.forEach(user => {
                                html += `<tr>
                                            <td>${user.name} ${user.l_name || ''}</td>
                                            <td>${user.email}</td>
                                            <td>
                                                <button class="btn btn-primary" onclick="assignUser(${posId}, ${user.id})">Associar</button>
                                            </td>
                                        </tr>`;
                            });
                            
                            html += `</tbody></table></div>
                                    <p style="margin-top: 15px;">
                                        <a href="criar_user.php?pos_id=${posId}&event_id=<?php echo $id; ?>${getFilterParams()}" target="_blank">Criar novo usuário</a>
                                    </p>`;
                            
                            userContent.innerHTML = html;
                        }
                    } else {
                        const userContent = document.getElementById('userAssignContent');
                        userContent.innerHTML = `<p style="color: red;">Erro ao carregar usuários: ${data.error || 'Erro desconhecido'}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    const userContent = document.getElementById('userAssignContent');
                    userContent.innerHTML = '<p style="color: red;">Erro ao carregar usuários. Por favor, tente novamente.</p>';
                });
        }
        
        function closeUserModal() {
            const modal = document.getElementById('userAssignModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
        
        function assignUser(posId, userId) {
            fetch(`pos_users.php?action=assign&pos_id=${posId}&user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeUserModal();
                        updatePDVContent(posId);
                    } else {
                        alert('Erro ao associar usuário: ' + (data.error || 'Erro desconhecido'));
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao associar usuário. Por favor, tente novamente.');
                });
        }
        
        function removeUser(posId, userId) {
            if (confirm('Tem certeza que deseja remover este usuário do PDV?')) {
                // Mostrar indicador de carregamento
                const userRow = event.target.closest('tr');
                if (userRow) {
                    userRow.style.opacity = '0.5';
                }
                
                // Fazer a solicitação AJAX para remover o usuário
                fetch(`pos_users.php?action=remove&pos_id=${posId}&user_id=${userId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Recarregar o conteúdo do PDV para mostrar a atualização
                            updatePDVContent(posId);
                        } else {
                            alert('Erro ao remover usuário: ' + (data.error || 'Erro desconhecido'));
                            if (userRow) {
                                userRow.style.opacity = '1';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        alert('Erro ao remover usuário. Por favor, tente novamente.');
                        if (userRow) {
                            userRow.style.opacity = '1';
                        }
                    });
            }
        }

        /* Extra sidebar scripts */
        function openExtrasSidebar(event, posId) {
            event.stopPropagation();
            const container = document.getElementById('extrasSidebarContainer');
            const sidebar = document.getElementById('extrasSidebar');
            
            // Update iframe source with the proper context
            sidebar.src = `extras_sidebar.php?event_id=<?php echo $id; ?>&pos_id=${posId}`;
            container.classList.add('active');
        }

        // Fechar o sidebar ao clicar fora
        document.addEventListener('click', function(event) {
            const container = document.getElementById('extrasSidebarContainer');
            const clickedElement = event.target;
            
            // Se o container existe e está ativo
            if (container && container.classList.contains('active')) {
                // Verifica se o clique foi fora do container e não foi em um botão que abre o sidebar
                if (!container.contains(clickedElement) && 
                    !clickedElement.closest('[onclick*="openExtrasSidebar"]')) {
                    closeExtrasSidebar();
                }
            }
        });

        function closeExtrasSidebar() {
            const container = document.getElementById('extrasSidebarContainer');
            if (container) {
                container.classList.remove('active');
                // Limpa o src do iframe após a animação de fechamento
                setTimeout(() => {
                    const sidebar = document.getElementById('extrasSidebar');
                    if (sidebar) {
                        sidebar.src = 'about:blank';
                    }
                }, 300);
            }
        }

        // Handle messages from the extras sidebar iframe
        window.addEventListener('message', function(event) {
            if (event.data.type === 'extrasSelected') {
                const selectedExtras = event.data.extras;
                const urlParams = new URLSearchParams(window.location.search);
                const posId = urlParams.get('pos_id');
                
                // Add selected extras to the current PDV
                if (selectedExtras.length > 0 && posId) {
                    Promise.all(selectedExtras.map(extra => 
                        fetch('assign_extra.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                extra_id: extra.id,
                                pos_id: posId,
                                event_id: <?php echo $id; ?>
                            })
                        }).then(r => r.json())
                    )).then(() => {
                        // Refresh the PDV content after all extras are added
                        updatePDVContent(posId);
                        closeExtrasSidebar();
                    });
                }
            }
        });
    </script>

    <?php include 'equipment_sidebar.php'; ?>
    <div class="extras-sidebar-container" id="extrasSidebarContainer">
        <iframe id="extrasSidebar" src="about:blank"></iframe>
    </div>
</body>
</html>
