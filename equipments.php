<?php
include 'auth.php';
include 'conexao.php';

// Processar exclusão de equipamento
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // Verificar se o equipamento está em uso
        $stmtCheck = $pdo->prepare("
            SELECT COUNT(*) FROM pos_equipment 
            WHERE equipment_id = ? AND status = 'active'
        ");
        $stmtCheck->execute([$id]);
        
        if ($stmtCheck->fetchColumn() > 0) {
            $erro = "Não é possível excluir este equipamento pois ele está em uso em um ponto de venda.";
        } else {
            // Primeiro remove associações com cartões de internet
            $stmt = $pdo->prepare("DELETE FROM equipment_internet_cards WHERE equipment_id = ?");
            $stmt->execute([$id]);
            
            // Remove o equipamento
            $stmt = $pdo->prepare("DELETE FROM equipment WHERE id = ?");
            $stmt->execute([$id]);
            
            // Redirecionar mantendo os filtros
            $redirect_url = "equipments.php";
            if (isset($_GET['search'])) $redirect_url .= "?search=" . urlencode($_GET['search']);
            if (isset($_GET['type'])) {
                $redirect_url .= (strpos($redirect_url, '?') !== false ? '&' : '?') . "type=" . $_GET['type'];
            }
            if (isset($_GET['status']) && $_GET['status'] !== '') {
                $redirect_url .= (strpos($redirect_url, '?') !== false ? '&' : '?') . "status=" . $_GET['status'];
            }
            
            header("Location: " . $redirect_url);
            exit;
        }
    } catch (PDOException $e) {
        $erro = "Erro ao excluir equipamento: " . $e->getMessage();
    }
}

// Verifica se há um termo de busca
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Verifica se há um filtro de tipo
$type_filter = isset($_GET['type']) ? (int)$_GET['type'] : 0;

// Verifica se há um filtro de status
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Buscar todos os tipos de equipamento para o filtro
$stmt_types = $pdo->query("SELECT id, name FROM equipment_types ORDER BY name");
$types = $stmt_types->fetchAll();

// Consulta base para buscar equipamentos com informações de uso
$sql = "
    SELECT 
        e.id,
        e.serial_number,
        e.notes,
        et.id as type_id,
        et.name as type_name,
        CASE 
            WHEN (SELECT COUNT(*) FROM pos_equipment pe WHERE pe.equipment_id = e.id AND pe.status = 'active') > 0 
            THEN 'Em uso' 
            ELSE 'Disponível' 
        END as status,
        (SELECT GROUP_CONCAT(p.name SEPARATOR ', ') 
         FROM pos p 
         JOIN pos_equipment pe ON p.id = pe.pos_id 
         WHERE pe.equipment_id = e.id AND pe.status = 'active') as pos_name,
        (SELECT GROUP_CONCAT(p.id SEPARATOR ',') 
         FROM pos p 
         JOIN pos_equipment pe ON p.id = pe.pos_id 
         WHERE pe.equipment_id = e.id AND pe.status = 'active') as pos_id,
        e.created_at
    FROM equipment e
    LEFT JOIN equipment_types et ON e.type_id = et.id
    WHERE 1=1
";

$params = [];

// Adicionar condições baseadas nos filtros
if (!empty($search_term)) {
    $sql .= " AND (e.serial_number LIKE ? OR e.notes LIKE ?)";
    $params[] = "%{$search_term}%";
    $params[] = "%{$search_term}%";
}

if ($type_filter > 0) {
    $sql .= " AND e.type_id = ?";
    $params[] = $type_filter;
}

if ($status_filter !== '') {
    if ($status_filter === 'inuse') {
        $sql .= " AND (SELECT COUNT(*) FROM pos_equipment pe WHERE pe.equipment_id = e.id AND pe.status = 'active') > 0";
    } else if ($status_filter === 'available') {
        $sql .= " AND (SELECT COUNT(*) FROM pos_equipment pe WHERE pe.equipment_id = e.id AND pe.status = 'active') = 0";
    }
}

$sql .= " ORDER BY e.serial_number ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipments = $stmt->fetchAll();

// Contar equipamentos por status
$total_count = count($equipments);
$in_use_count = 0;
$available_count = 0;

foreach ($equipments as $equipment) {
    if ($equipment['status'] === 'Em uso') {
        $in_use_count++;
    } else {
        $available_count++;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Todos os Equipamentos</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            margin: 0;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .filter-container {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
            box-sizing: border-box;
        }
        
        .filter-buttons {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px 8px; 
            text-align: left; 
        }
        
        th { 
            background-color: #f5f5f5; 
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-align: center;
        }
        
        .status-inuse {
            background-color: #ffecb3;
            color: #e65100;
        }
        
        .status-available {
            background-color: #c8e6c9;
            color: #2e7d32;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #2196F3;
            color: white;
        }
        
        .btn-secondary {
            background-color: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .btn-success {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-danger {
            background-color: #f44336;
            color: white;
        }
        
        .btn-warning {
            background-color: #FF9800;
            color: white;
        }
        
        .statistics {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-around;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5em;
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9em;
            color: #666;
        }
        
        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
        }
        
        .pagination a, .pagination span {
            padding: 8px 16px;
            text-decoration: none;
            border: 1px solid #ddd;
            margin: 0 4px;
            color: #2196F3;
        }
        
        .pagination a:hover {
            background-color: #f1f1f1;
        }
        
        .pagination .active {
            background-color: #2196F3;
            color: white;
            border: 1px solid #2196F3;
        }
        
        .actions {
            display: flex;
            gap: 5px;
        }
        
        .pos-details {
            margin-top: 5px;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="header-container">
        <h1>Todos os Equipamentos</h1>
        <div>
            <a href="equipment_form.php" class="btn btn-success">+ Novo Equipamento</a>
            <a href="bulk_equipment.php" class="btn btn-create">+ Bulk</a>
            <a href="export_equipment.php?<?php echo http_build_query(array_filter([
                'search' => $search_term,
                'type' => $type_filter ?: null,
                'status' => $status_filter
            ])); ?>" class="btn btn-primary" style="background-color: #4CAF50;">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </a>
        </div>
    </div>
    
    <?php if (isset($erro)): ?>
        <div class="error" style="color: red; background-color: #ffebee; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
            <?php echo htmlspecialchars($erro); ?>
        </div>
    <?php endif; ?>

    <div class="statistics">
        <div class="stat-item">
            <span class="stat-number"><?php echo $total_count; ?></span>
            <span class="stat-label">Total de Equipamentos</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?php echo $in_use_count; ?></span>
            <span class="stat-label">Em Uso</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?php echo $available_count; ?></span>
            <span class="stat-label">Disponíveis</span>
        </div>
    </div>

    <div class="filter-container">
        <form method="get" action="equipments.php">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search">Pesquisar:</label>
                    <input type="text" id="search" name="search" placeholder="Número de série ou observações..." 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="filter-group">
                    <label for="type">Tipo de Equipamento:</label>
                    <select id="type" name="type">
                        <option value="0">Todos os tipos</option>
                        <?php foreach ($types as $type): ?>
                        <option value="<?php echo $type['id']; ?>" <?php echo $type_filter == $type['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status">
                        <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>Todos</option>
                        <option value="inuse" <?php echo $status_filter === 'inuse' ? 'selected' : ''; ?>>Em Uso</option>
                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Disponíveis</option>
                    </select>
                </div>
            </div>
            <div class="filter-buttons">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="equipments.php" class="btn btn-secondary">Limpar Filtros</a>
            </div>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Tipo</th>
                <th>Número de Série</th>
                <th>Status</th>
                <th>Ponto de Venda</th>
                <th>Observações</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($equipments) === 0): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">Nenhum equipamento encontrado</td>
                </tr>
            <?php else: ?>
                <?php foreach ($equipments as $equipment): ?>
                    <tr id="equipment-<?php echo $equipment['id']; ?>">
                        <td><?php echo htmlspecialchars($equipment['id']); ?></td>
                        <td><?php echo htmlspecialchars($equipment['type_name']); ?></td>
                        <td><?php echo htmlspecialchars($equipment['serial_number']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $equipment['status'] === 'Em uso' ? 'status-inuse' : 'status-available'; ?>">
                                <?php echo htmlspecialchars($equipment['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($equipment['pos_name'])): ?>
                                <?php 
                                    $pos_names = explode(", ", $equipment['pos_name']);
                                    $pos_ids = explode(",", $equipment['pos_id']);
                                    
                                    for ($i = 0; $i < count($pos_names); $i++): 
                                        $pos_name = $pos_names[$i];
                                        $pos_id = isset($pos_ids[$i]) ? $pos_ids[$i] : '';
                                ?>
                                    <div class="pos-item">
                                        <a href="event_management.php?pos_id=<?php echo $pos_id; ?>">
                                            <?php echo htmlspecialchars($pos_name); ?>
                                        </a>
                                    </div>
                                <?php endfor; ?>
                            <?php else: ?>
                                <em>Não associado</em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($equipment['notes'] ?: '-'); ?></td>
                        <td class="actions">
                            <a href="equipment_form.php?id=<?php echo $equipment['id']; ?>" class="btn btn-primary" target="_self">Editar</a>
                            
                            <?php if ($equipment['status'] === 'Em uso'): ?>
                                <a href="equipment_internet_form.php?id=<?php echo $equipment['id']; ?>" 
                                   class="btn btn-warning">Internet</a>
                            <?php endif; ?>
                            
                            <?php if ($equipment['status'] === 'Disponível'): ?>
                                <a href="equipments.php?delete=<?php echo $equipment['id']; ?>" 
                                   class="btn btn-danger"
                                   onclick="return confirm('Tem certeza que deseja excluir este equipamento?')">
                                    Excluir
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
