<?php
include 'auth.php';
include 'conexao.php';

// Verificar se há uma ação de delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // Verificar se o cartão está em uso
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM equipment_internet_cards WHERE internet_card_id = ?");
        $stmtCheck->execute([$id]);
        if ($stmtCheck->fetchColumn() > 0) {
            $erro = "Não é possível excluir este cartão pois ele está associado a um equipamento.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM internet_cards WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: internet_cards.php");
            exit;
        }
    } catch (PDOException $e) {
        $erro = "Erro ao excluir: " . $e->getMessage();
    }
}

// Buscar todos os cartões de internet com seus equipamentos associados
$stmt = $pdo->query("
    SELECT ic.*,
           GROUP_CONCAT(
               CONCAT(et.name, ' - ', e.serial_number, ' (', 
                     CASE eic.status 
                         WHEN 'active' THEN 'Ativo'
                         ELSE 'Inativo'
                     END,
                     ')'
               ) SEPARATOR ', '
           ) as equipment_list
    FROM internet_cards ic
    LEFT JOIN equipment_internet_cards eic ON ic.id = eic.internet_card_id    LEFT JOIN equipment e ON eic.equipment_id = e.id
    LEFT JOIN equipment_types et ON e.type_id = et.id
    GROUP BY ic.id
    ORDER BY ic.id ASC
");
$cards = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cartões de Internet</title>
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
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85em;
        }
        .status-active {
            background-color: #4CAF50;
            color: white;
        }
        .status-inactive {
            background-color: #f44336;
            color: white;
        }
        .status-expired {
            background-color: #FF9800;
            color: white;
        }
        .provider-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .provider-MEO {
            background-color: #00A878;
            color: white;
        }
        .provider-VODAFONE {
            background-color: #E60000;
            color: white;
        }
        .provider-NOS {
            background-color: #003B7A;
            color: white;
        }
        .provider-UZO {
            background-color: #FF4081;
            color: white;
        }        .provider-NOWO {
            background-color: #673AB7;
            color: white;
        }
        
        /* Estilo para destacar o cartão após edição */
        @keyframes highlightFade {
            0% { background-color: #ffeb3b; }
            100% { background-color: transparent; }
        }
        
        .highlight-row {
            animation: highlightFade 2s ease-out;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <h1>Cartões de Internet</h1>

    <?php if (isset($erro)): ?>
        <div class="error"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>    <div style="margin: 20px 0; display: flex; justify-content: space-between; align-items: center; gap: 15px;">
        <a href="internet_card_form.php" class="btn btn-create">+ Novo Cartão de Internet</a>
        <div style="display: flex; gap: 10px; align-items: center; flex: 1;">
            <select id="filterStatus" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="all">Todos</option>
                <option value="associated">Associados</option>
                <option value="unassociated">Não Associados</option>
            </select>
            <div style="flex: 1;">
                <input type="text" id="searchInput" placeholder="Pesquisar cartões..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
        </div>
    </div>

    <table><thead>            <tr>                <th>ID</th>
                <th>Operadora</th>
                <th>Número</th>
                <th>Número de Ativação</th>
                <th>PIN/PUK</th>
                <th>Status</th>
                <th>Equipamentos</th>
                <th>Notas</th>
                <th>Criado em</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cards as $card): ?>                <tr id="card-<?php echo $card['id']; ?>">                    <td><?php echo htmlspecialchars($card['id']); ?></td>
                    <td>
                        <span class="provider-badge provider-<?php echo $card['provider']; ?>">
                            <?php echo htmlspecialchars($card['provider']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($card['phone_number'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($card['activation_number']); ?></td>
                    <td>
                        <?php if ($card['pin'] || $card['puk']): ?>
                            <?php if ($card['pin']): ?>PIN: <?php echo htmlspecialchars($card['pin']); ?><?php endif; ?>
                            <?php if ($card['pin'] && $card['puk']): ?><br><?php endif; ?>
                            <?php if ($card['puk']): ?>PUK: <?php echo htmlspecialchars($card['puk']); ?><?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $statusClass = '';
                        switch($card['status']) {
                            case 'active':
                                $statusText = 'Ativo';
                                $statusClass = 'status-active';
                                break;
                            case 'inactive':
                                $statusText = 'Inativo';
                                $statusClass = 'status-inactive';
                                break;
                            case 'expired':
                                $statusText = 'Expirado';
                                $statusClass = 'status-expired';
                                break;
                            default:
                                $statusText = 'Desconhecido';
                        }
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                    </td>                    <td><?php echo htmlspecialchars($card['equipment_list'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($card['notes'] ?: '-'); ?></td>
                    <td><?php echo $card['created_at'] ? date('d/m/Y H:i', strtotime($card['created_at'])) : '-'; ?></td>                    <td class="actions">                        <?php
                        // Capturar a URL base sem parâmetros específicos de cartão
                        $parsed_url = parse_url($_SERVER['REQUEST_URI']);
                        $query = [];
                        
                        // Preservar apenas parâmetros de filtro, não IDs específicos
                        if (isset($parsed_url['query'])) {
                            parse_str($parsed_url['query'], $query_params);
                            // Remover parâmetros relacionados a cartões específicos
                            unset($query_params['id']);
                            unset($query_params['delete']);
                            
                            if (!empty($query_params)) {
                                $query = http_build_query($query_params);
                            }
                        }
                        
                        $base_path = $parsed_url['path'];
                        $return_url = $base_path . (!empty($query) ? '?' . $query : '') . '#card-' . $card['id'];
                        ?>
                        <a href="internet_card_form.php?id=<?php echo $card['id']; ?>&return_url=<?php echo urlencode($return_url); ?>" 
                           class="btn btn-edit">Editar</a>
                        <a href="internet_cards.php?delete=<?php echo $card['id']; ?>" 
                           class="btn btn-delete"
                           onclick="return confirm('Tem certeza que deseja excluir este cartão?')">
                            Excluir
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>        </tbody>
    </table>    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const filterStatus = document.getElementById('filterStatus');
        const rows = document.querySelectorAll('table tbody tr');
        
        // Função para scroll suave até o cartão específico
        if (window.location.hash) {
            const targetElement = document.querySelector(window.location.hash);
            if (targetElement) {
                setTimeout(() => {
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    targetElement.classList.add('highlight-row');
                    setTimeout(() => {
                        targetElement.classList.remove('highlight-row');
                    }, 2000);
                }, 100);
            }
        }

        function filterRows() {
            const searchTerm = searchInput.value.toLowerCase();
            const filterValue = filterStatus.value;

            rows.forEach(row => {
                let text = '';
                row.querySelectorAll('td').forEach(cell => {
                    text += cell.textContent + ' ';
                });

                const equipmentCell = row.querySelector('td:nth-child(7)'); // Coluna de equipamentos
                const hasEquipment = equipmentCell.textContent.trim() !== '-';
                
                let showByFilter = true;
                if (filterValue === 'associated') {
                    showByFilter = hasEquipment;
                } else if (filterValue === 'unassociated') {
                    showByFilter = !hasEquipment;
                }

                const showBySearch = text.toLowerCase().includes(searchTerm);
                
                row.style.display = (showByFilter && showBySearch) ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', filterRows);
        filterStatus.addEventListener('change', filterRows);

        // Aplicar filtros iniciais
        filterRows();
    });
    </script>
</body>
</html>
