<?php
include 'auth.php';
include 'conexao.php';

// Verificar se há uma ação de delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // Verificar se o tipo está em uso
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM equipment WHERE type_id = ?");
        $stmtCheck->execute([$id]);
        if ($stmtCheck->fetchColumn() > 0) {
            $erro = "Não é possível excluir este tipo pois existem equipamentos associados a ele.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM equipment_types WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: equipment_types.php");
            exit;
        }
    } catch (PDOException $e) {
        $erro = "Erro ao excluir: " . $e->getMessage();
    }
}

// Buscar todos os tipos de equipamento
$stmt = $pdo->query("
    SELECT et.*, 
           COUNT(e.id) as equipment_count 
    FROM equipment_types et
    LEFT JOIN equipment e ON e.type_id = et.id
    GROUP BY et.id
    ORDER BY et.name
");
$tipos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Tipos de Equipamento</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            margin: 0;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
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
        .btn {
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            margin: 2px;
            cursor: pointer;
        }
        .btn-create {
            background-color: #4CAF50;
            color: white;
        }
        .btn-edit {
            background-color: #2196F3;
            color: white;
        }
        .btn-delete {
            background-color: #f44336;
            color: white;
        }
        .actions {
            white-space: nowrap;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }        .count-badge {
            background-color: #666;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.8em;
        }
        .btn-expand {
            background-color: #FF9800;
            color: white;
        }
        .equipment-container {
            background-color: #f9f9f9;
        }
        .equipment-list-container {
            padding: 0;
        }
        .equipment-list {
            padding: 15px;
            display: none;
        }
        .equipment-list table {
            width: 100%;
            margin-top: 0;
            border: none;
        }
        .equipment-list th {
            background-color: #eaeaea;
        }
        .loading-indicator {
            padding: 15px;
            text-align: center;
            color: #666;
        }
        .btn-collapse {
            background-color: #607D8B;
            color: white;
        }
        .equipment-item:nth-child(even) {
            background-color: #f5f5f5;
        }
        .equipment-item:hover {
            background-color: #e9e9e9;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <h1>Tipos de Equipamento</h1>

    <?php if (isset($erro)): ?>
        <div class="error"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <div style="margin: 20px 0;">
        <a href="equipment_type_form.php" class="btn btn-create">+ Novo Tipo de Equipamento</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Descrição</th>
                <th>Qtd. Equipamentos</th>
                <th>Criado em</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>            <?php foreach ($tipos as $tipo): ?>
                <tr class="tipo-row" data-type-id="<?php echo $tipo['id']; ?>">
                    <td><?php echo htmlspecialchars($tipo['id']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($tipo['name']); ?>
                        <?php if ($tipo['equipment_count'] > 0): ?>
                            <span class="count-badge"><?php echo $tipo['equipment_count']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($tipo['description'] ?: '-'); ?></td>
                    <td><?php echo $tipo['equipment_count']; ?></td>
                    <td><?php echo $tipo['created_at'] ? date('d/m/Y H:i', strtotime($tipo['created_at'])) : '-'; ?></td>
                    <td class="actions">
                        <a href="equipment_type_form.php?id=<?php echo $tipo['id']; ?>" 
                           class="btn btn-edit">Editar</a>
                        <?php if ($tipo['equipment_count'] == 0): ?>
                            <a href="equipment_types.php?delete=<?php echo $tipo['id']; ?>" 
                               class="btn btn-delete"
                               onclick="return confirm('Tem certeza que deseja excluir este tipo?')">
                                Excluir
                            </a>
                        <?php elseif ($tipo['equipment_count'] > 0): ?>
                            <a href="#" 
                               class="btn btn-expand"
                               onclick="toggleEquipments(<?php echo $tipo['id']; ?>, this); return false;">
                                Expandir
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- Container for equipment list -->
                <tr class="equipment-container" id="equipment-container-<?php echo $tipo['id']; ?>" style="display: none;">
                    <td colspan="6" class="equipment-list-container">
                        <div class="loading-indicator" id="loading-<?php echo $tipo['id']; ?>">Carregando...</div>
                        <div class="equipment-list" id="equipment-list-<?php echo $tipo['id']; ?>"></div>
                    </td>
                </tr>
            <?php endforeach; ?>        </tbody>
    </table>
    
    <script>
        // Função para alternar a exibição da lista de equipamentos
        function toggleEquipments(typeId, button) {
            const container = document.getElementById('equipment-container-' + typeId);
            const equipmentList = document.getElementById('equipment-list-' + typeId);
            const loadingIndicator = document.getElementById('loading-' + typeId);
            
            // Se o container está oculto, vamos mostrá-lo
            if (container.style.display === 'none') {
                container.style.display = 'table-row';
                loadingIndicator.style.display = 'block';
                equipmentList.style.display = 'none';
                
                // Alterar o texto do botão
                button.textContent = 'Recolher';
                button.classList.remove('btn-expand');
                button.classList.add('btn-collapse');
                
                // Carregar os equipamentos via AJAX
                fetchEquipments(typeId);
            } else {
                container.style.display = 'none';
                
                // Alterar o texto do botão
                button.textContent = 'Expandir';
                button.classList.remove('btn-collapse');
                button.classList.add('btn-expand');
            }
        }
        
        // Função para buscar os equipamentos do servidor
        function fetchEquipments(typeId) {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_equipments_by_type.php?type_id=' + typeId, true);
            
            xhr.onload = function() {
                const loadingIndicator = document.getElementById('loading-' + typeId);
                const equipmentList = document.getElementById('equipment-list-' + typeId);
                
                if (this.status === 200) {
                    loadingIndicator.style.display = 'none';
                    equipmentList.style.display = 'block';
                    equipmentList.innerHTML = this.responseText;
                } else {
                    loadingIndicator.style.display = 'none';
                    equipmentList.style.display = 'block';
                    equipmentList.innerHTML = '<p style="color: red;">Erro ao carregar equipamentos.</p>';
                }
            };
            
            xhr.onerror = function() {
                const loadingIndicator = document.getElementById('loading-' + typeId);
                const equipmentList = document.getElementById('equipment-list-' + typeId);
                
                loadingIndicator.style.display = 'none';
                equipmentList.style.display = 'block';
                equipmentList.innerHTML = '<p style="color: red;">Erro ao carregar equipamentos. Verifique sua conexão.</p>';
            };
            
            xhr.send();
        }
    </script>
</body>
</html>
