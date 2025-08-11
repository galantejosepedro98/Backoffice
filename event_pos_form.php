<?php
include 'auth.php';
include 'conexao.php';

$id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;
$error = null;
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

// Buscar PDVs já associados ao evento
$stmt = $pdo->prepare("
    SELECT ep.*, p.name as pos_name
    FROM event_pos ep
    JOIN pos p ON ep.pos_id = p.id
    WHERE ep.event_id = ?
");
$stmt->execute([$id]);
$current_assignments = $stmt->fetchAll();

// Buscar todos os PDVs disponíveis
$stmt = $pdo->query("SELECT * FROM pos ORDER BY name");
$all_pos = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Começar transação
        $pdo->beginTransaction();

        // Remover associações existentes
        $stmt = $pdo->prepare("DELETE FROM event_pos WHERE event_id = ?");
        $stmt->execute([$id]);

        // Adicionar novas associações
        if (isset($_POST['pos']) && is_array($_POST['pos'])) {
            $stmt = $pdo->prepare("
                INSERT INTO event_pos 
                (event_id, pos_id, created_at)
                VALUES (?, ?, NOW())
            ");

            foreach ($_POST['pos'] as $pos_id) {
                $stmt->execute([
                    $id,
                    $pos_id
                ]);
            }        }

        $pdo->commit();
        header("Location: event_management.php?id=" . $id);
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
    <title>Associar PDVs ao Evento</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            margin: 0;
        }
        .event-info {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .pos-list {
            margin-top: 20px;
        }
        .pos-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            display: none; /* Hide by default */
        }
        .pos-item.visible {
            display: block;
        }
        .pos-item:hover {
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <h1>Associar PDVs ao Evento</h1>    <div class="event-info">
        <h3><?php echo htmlspecialchars($event['name']); ?></h3>
        <?php if ($event['location']): ?>
            <p>Local: <?php echo htmlspecialchars($event['location']); ?></p>
        <?php endif; ?>
        <p>Data: <?php echo date('d/m/Y', strtotime($event['start_at'])); ?></p>
    </div>    <div style="margin-bottom: 20px;">
        <a href="criar_pos.php?return_to=event_pos_form&event_id=<?php echo $id; ?>" class="btn btn-primary">+ Criar Novo Ponto de Venda</a>
        <a href="importar_pos.php?return_to=event_pos_form&event_id=<?php echo $id; ?>" class="btn btn-primary">📥 Importar Pontos de Venda</a>
    </div>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Pesquisar pontos de venda..." autocomplete="off">
        </div>

        <div class="pos-list">
            <div class="no-results">Nenhum ponto de venda encontrado.</div>
            <?php foreach ($all_pos as $pos): ?>
                <?php 
                $is_assigned = false;
                $current_assignment = null;
                foreach ($current_assignments as $assignment) {
                    if ($assignment['pos_id'] == $pos['id']) {
                        $is_assigned = true;
                        $current_assignment = $assignment;
                        break;
                    }
                }
                ?>
                <div class="pos-item" data-name="<?php echo htmlspecialchars(strtolower($pos['name'])); ?>">
                    <label>
                        <input type="checkbox" name="pos[]" value="<?php echo $pos['id']; ?>"
                               <?php echo $is_assigned ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($pos['name']); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="eventos.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const posItems = document.querySelectorAll('.pos-item');
        const noResults = document.querySelector('.no-results');
        const maxVisibleItems = 10;

        function filterPOS() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;
            let hasResults = false;

            posItems.forEach(item => {
                const name = item.getAttribute('data-name');
                const matches = name.includes(searchTerm);
                
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
        filterPOS();

        // Filter on input
        searchInput.addEventListener('input', filterPOS);
    });
    </script>
</body>
</html>
