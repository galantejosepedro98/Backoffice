<?php
include 'auth.php';
include 'conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$error = null;

// Buscar informações do equipamento
$stmt = $pdo->prepare("
    SELECT e.*, et.name as type_name 
    FROM equipment e
    LEFT JOIN equipment_types et ON e.type_id = et.id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$equipment = $stmt->fetch();

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;

if (!$equipment) {
    header("Location: " . ($event_id ? "event_management.php?id=" . $event_id : "equipment.php"));
    exit;
}

// Buscar cartões de internet associados
$stmt = $pdo->prepare("
    SELECT eic.*, ic.id as card_id, ic.provider, ic.phone_number, ic.activation_number
    FROM equipment_internet_cards eic
    JOIN internet_cards ic ON eic.internet_card_id = ic.id
    WHERE eic.equipment_id = ?
");
$stmt->execute([$id]);
$current_assignments = $stmt->fetchAll();

// Buscar todos os cartões de internet disponíveis (não associados ou associados a este equipamento)
$stmt = $pdo->query("
    SELECT ic.*, 
           CASE 
               WHEN EXISTS (
                   SELECT 1 
                   FROM equipment_internet_cards eic2 
                   WHERE eic2.internet_card_id = ic.id 
                   AND eic2.status = 'active'
                   AND eic2.equipment_id != " . ($id ?: 0) . "
               ) THEN 'Em Uso'
               ELSE 'Disponível'
           END as card_status
    FROM internet_cards ic
    WHERE NOT EXISTS (
        SELECT 1 
        FROM equipment_internet_cards eic 
        WHERE eic.internet_card_id = ic.id 
        AND eic.status = 'active'
        AND eic.equipment_id != " . ($id ?: 0) . "
    )
    ORDER BY ic.id DESC
");
$all_cards = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Começar transação
        $pdo->beginTransaction();

        // Remover associações existentes que não estão no novo conjunto
        $stmt = $pdo->prepare("DELETE FROM equipment_internet_cards WHERE equipment_id = ?");
        $stmt->execute([$id]);

        // Adicionar novas associações
        if (isset($_POST['cards']) && is_array($_POST['cards'])) {
            $stmt = $pdo->prepare("
                INSERT INTO equipment_internet_cards 
                (equipment_id, internet_card_id, status, notes, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");

            foreach ($_POST['cards'] as $card_id) {
                $status = $_POST['status'][$card_id] ?? 'active';
                $notes = $_POST['notes'][$card_id] ?? null;
                
                $stmt->execute([
                    $id,
                    $card_id,
                    $status,
                    $notes
                ]);
            }        }            $pdo->commit();            if (isset($_POST['event_id'])) {
                $redirect_url = "event_management.php?id=" . $_POST['event_id'];
                if (isset($_POST['pos_id'])) {
                    $redirect_url .= "&pos_id=" . $_POST['pos_id'];
                }
                
                // Preservar filtros
                if (isset($_POST['category']) && !empty($_POST['category'])) {
                    $redirect_url .= "&category=" . urlencode($_POST['category']);
                }
                if (isset($_POST['search']) && !empty($_POST['search'])) {
                    $redirect_url .= "&search=" . urlencode($_POST['search']);
                }
                
                header("Location: " . $redirect_url);
            } else {
                $return_type = isset($_GET['return_type']) ? (int)$_GET['return_type'] : 0;
                $url = "equipment.php";
                if ($return_type > 0) {
                    $url .= "?type=" . $return_type;
                }
                $url .= "#equipment-" . $id;
                header("Location: " . $url);
            }
            exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Associar Internet ao Equipamento</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            margin: 0;
        }
        .equipment-info {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .card-list {
            margin-top: 20px;
        }
        .card-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            display: none; /* Hide by default */
        }
        .card-item.visible {
            display: block;
        }
        .card-item:hover {
            background-color: #f9f9f9;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 60px;
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
            margin-bottom: 10px;
        }
        #searchInput:focus {
            border-color: #4CAF50;
            outline: none;
        }
        .no-results {
            padding: 20px;
            text-align: center;
            color: #666;
            background: #f5f5f5;
            border-radius: 4px;
            display: none;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-secondary {
            background-color: #666;
            color: white;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .provider-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
            margin-left: 5px;
        }
        .provider-MEO { background-color: #00A878; color: white; }
        .provider-VODAFONE { background-color: #E60000; color: white; }
        .provider-NOS { background-color: #003B7A; color: white; }
        .provider-UZO { background-color: #FF4081; color: white; }
        .provider-NOWO { background-color: #673AB7; color: white; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <h1>Associar Internet ao Equipamento</h1>

    <div class="equipment-info">
        <h3><?php echo htmlspecialchars($equipment['name']); ?></h3>
        <p>Tipo: <?php echo htmlspecialchars($equipment['type_name']); ?></p>
        <?php if ($equipment['serial_number']): ?>
            <p>Número de Série: <?php echo htmlspecialchars($equipment['serial_number']); ?></p>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>    <form method="POST">
        <?php if(isset($_GET['event_id'])): ?>
            <input type="hidden" name="event_id" value="<?php echo (int)$_GET['event_id']; ?>">
        <?php endif; ?>
        
        <?php if(isset($_GET['pos_id'])): ?>
            <input type="hidden" name="pos_id" value="<?php echo (int)$_GET['pos_id']; ?>">
        <?php endif; ?>
        
        <?php if(isset($_GET['category'])): ?>
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($_GET['category']); ?>">
        <?php endif; ?>
        
        <?php if(isset($_GET['search'])): ?>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
        <?php endif; ?>
        
        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Pesquisar cartões de internet..." autocomplete="off">
        </div>

        <div class="card-list">
            <div class="no-results">Nenhum cartão de internet encontrado.</div>
            <?php foreach ($all_cards as $card): ?>
                <?php 
                $is_assigned = false;
                $current_assignment = null;
                foreach ($current_assignments as $assignment) {
                    if ($assignment['internet_card_id'] == $card['id']) {
                        $is_assigned = true;
                        $current_assignment = $assignment;
                        break;
                    }
                }                $display_name = 'ID: ' . $card['id'] . ' - ' . 
                    ($card['phone_number'] ? $card['phone_number'] : $card['activation_number']) .
                    ' [' . $card['card_status'] . ']';

                $search_text = strtolower($card['id'] . ' ' . $card['provider'] . ' ' . $card['phone_number'] . ' ' . $card['activation_number']);
                ?>
                <div class="card-item" data-search="<?php echo htmlspecialchars($search_text); ?>">
                    <label>
                        <input type="checkbox" name="cards[]" value="<?php echo $card['id']; ?>"
                               <?php echo $is_assigned ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($display_name); ?>
                        <span class="provider-badge provider-<?php echo $card['provider']; ?>">
                            <?php echo htmlspecialchars($card['provider']); ?>
                        </span>
                    </label>

                    <div class="form-group" style="margin-top: 10px;">
                        <label>Status neste Equipamento:</label>
                        <select name="status[<?php echo $card['id']; ?>]">
                            <option value="active" <?php echo (!$current_assignment || $current_assignment['status'] === 'active') ? 'selected' : ''; ?>>
                                Ativo
                            </option>
                            <option value="inactive" <?php echo ($current_assignment && $current_assignment['status'] === 'inactive') ? 'selected' : ''; ?>>
                                Inativo
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Observações:</label>
                        <textarea name="notes[<?php echo $card['id']; ?>]" placeholder="Ex: Data de ativação, localização, responsável..."><?php 
                            echo $current_assignment ? htmlspecialchars($current_assignment['notes']) : ''; 
                        ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>        <div style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <?php 
            $cancel_url = $event_id ? 'event_management.php?id=' . $event_id : 'equipment.php';
            if ($event_id) {
                if (isset($_GET['pos_id'])) {
                    $cancel_url .= "&pos_id=" . (int)$_GET['pos_id'];
                }
                if (isset($_GET['category'])) {
                    $cancel_url .= "&category=" . urlencode($_GET['category']);
                }
                if (isset($_GET['search'])) {
                    $cancel_url .= "&search=" . urlencode($_GET['search']);
                }
            }
            ?>
            <a href="<?php echo $cancel_url; ?>" class="btn btn-secondary">Cancelar</a>
            <?php if ($event_id): ?>
                <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                <?php if (isset($_GET['pos_id'])): ?>
                    <input type="hidden" name="pos_id" value="<?php echo (int)$_GET['pos_id']; ?>">
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const cardItems = document.querySelectorAll('.card-item');
        const noResults = document.querySelector('.no-results');
        const maxVisibleItems = 10;

        function filterCards() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;
            let hasResults = false;

            cardItems.forEach(item => {
                const searchText = item.getAttribute('data-search');
                const matches = searchText.includes(searchTerm);
                
                if (matches && (searchTerm.length > 0 || visibleCount < maxVisibleItems)) {
                    item.classList.add('visible');
                    visibleCount++;
                    hasResults = true;
                } else {
                    item.classList.remove('visible');
                }
            });

            noResults.style.display = hasResults ? 'none' : 'block';
        }

        // Initial filter
        filterCards();

        // Filter on input
        searchInput.addEventListener('input', filterCards);
    });
    </script>
</body>
</html>
