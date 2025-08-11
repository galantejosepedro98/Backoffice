<?php 
include 'auth.php';
include 'conexao.php';

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Buscar informações do evento
$stmtEvento = $pdo->prepare("SELECT name FROM events WHERE id = ?");
$stmtEvento->execute([$event_id]);
$evento = $stmtEvento->fetch();

if (!$evento) {
    die('Evento não encontrado');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Categorias de Extras - <?php echo htmlspecialchars($evento['name']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #eee; }
        .expand-button { cursor: pointer; padding: 5px 10px; background: #f0f0f0; border: none; border-radius: 3px; }
        .expand-button:hover { background: #e0e0e0; }
        .extras-row { display: none; }
        .extras-row td { background-color: #f9f9f9; padding-left: 20px; }
        .extras-table { margin: 10px 0; }
        .active { background-color: #e7e7e7; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <p><a href="eventos.php">&larr; Voltar para Lista de Eventos</a></p>
    <h1>Categorias de Extras - <?php echo htmlspecialchars($evento['name']); ?></h1>
    
    <div style="margin: 20px 0;">
        <a href="criar_categoria.php?event_id=<?php echo $event_id; ?>" 
           style="padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;">
            + Criar Nova Categoria
        </a>
    </div>
    
    <table>
        <thead>
            <tr>
                <th></th>
                <th>ID</th>
                <th>Nome</th>
                <th>Ordem</th>
                <th>Criado em</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->prepare("SELECT * FROM extra_categories WHERE event_id = ? ORDER BY `order`");
            $stmt->execute([$event_id]);
            while ($row = $stmt->fetch()) {
                // Buscar extras desta categoria
                $stmtExtras = $pdo->prepare("SELECT extras.* FROM extras WHERE extra_category_id = ?");
                $stmtExtras->execute([$row['id']]);
                $extras = $stmtExtras->fetchAll();
                
                // Buscar os POS para cada extra
                $stmtPos = $pdo->prepare("
                    SELECT p.* FROM pos p 
                    INNER JOIN extra_pos ep ON ep.pos_id = p.id 
                    WHERE ep.extra_id = ?
                ");
                $numExtras = count($extras);
                
                echo '<tr class="category-row">';
                echo '<td><button class="expand-button" onclick="toggleExtras(' . $row['id'] . ')">' . 
                     ($numExtras > 0 ? '▶' : '○') . '</button></td>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . ' (' . $numExtras . ' extras)</td>';
                echo '<td>' . htmlspecialchars($row['order']) . '</td>';
                echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                echo '</tr>';
                
                // Linha expansível com os extras
                if ($numExtras > 0) {
                    echo '<tr id="extras-' . $row['id'] . '" class="extras-row">';
                } else {
                    echo '<tr id="extras-' . $row['id'] . '" class="extras-row" style="display: none;">';
                }
                echo '<td colspan="6">';
                echo '<div style="margin-bottom: 10px;">
                        <a href="criar_extra.php?category_id=' . $row['id'] . '&event_id=' . $event_id . '" 
                           style="padding: 8px 15px; background-color: #4CAF50; color: white; 
                                  text-decoration: none; border-radius: 4px; font-size: 14px;">
                            + Novo Extra
                        </a>
                      </div>';
                echo '<table class="extras-table" style="width: 100%;">';
                echo '<thead><tr>';
                echo '<th></th>'; // Coluna para o botão expandir
                echo '<th>ID</th>';
                echo '<th>Nome</th>';
                echo '<th>Criado em</th>';
                echo '<th>Preço</th>';
                echo '<th>Tipo de Taxa</th>';
                echo '<th>Tipo</th>';
                echo '<th>TOC Item Code</th>';
                echo '<th>TOC Item ID</th>';
                echo '</tr></thead><tbody>';
                
                foreach ($extras as $extra) {
                    // Buscar POS associados a este extra
                    $stmtPos->execute([$extra['id']]);
                    $posList = $stmtPos->fetchAll();
                    $numPos = count($posList);
                    
                    echo '<tr class="extra-row">';
                    echo '<td><button class="expand-button pos-button" onclick="togglePos(' . $extra['id'] . ')">' . 
                         ($numPos > 0 ? '▶' : '○') . '</button></td>';
                    echo '<td>' . htmlspecialchars($extra['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($extra['display_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($extra['created_at']) . '</td>';
                    echo '<td>' . number_format($extra['price'], 2) . '€</td>';
                    echo '<td>' . htmlspecialchars($extra['tax_type']) . '%</td>';
                    echo '<td>' . htmlspecialchars($extra['type']) . '</td>';
                    echo '<td>' . htmlspecialchars($extra['toconline_item_code'] ?: '-') . '</td>';
                    echo '<td>' . htmlspecialchars($extra['toconline_item_id'] ?: '-') . '</td>';
                    echo '</tr>';
                    
                    // Linha expansível com os POS
                    if ($numPos > 0) {
                        echo '<tr id="pos-' . $extra['id'] . '" class="pos-row" style="display: none;">';
                        echo '<td colspan="9" style="padding-left: 50px;">';
                        echo '<strong>Pontos de Venda:</strong><br>';
                        echo '<ul style="list-style: none; padding: 10px;">';
                        foreach ($posList as $pos) {
                            echo '<li style="margin: 5px 0;">• ' . htmlspecialchars($pos['name']) . '</li>';
                        }
                        echo '</ul>';
                        echo '</td>';
                        echo '</tr>';
                    }
                }
                
                echo '</tbody></table>';
                echo '</td>';
                echo '</tr>';
            }
            ?>
        </tbody>
    </table>

    <script>
    function toggleExtras(categoryId) {
        const extrasRow = document.getElementById('extras-' + categoryId);
        const button = event.target;
        const allButtons = document.querySelectorAll('.expand-button:not(.pos-button)');
        
        // Fecha todas as outras linhas de extras
        document.querySelectorAll('.extras-row').forEach(row => {
            if (row.id !== 'extras-' + categoryId) {
                row.style.display = 'none';
            }
        });
        
        // Remove a classe active de todos os botões
        allButtons.forEach(btn => {
            btn.innerHTML = '▶';
            btn.classList.remove('active');
        });
        
        // Alterna a visibilidade da linha selecionada
        if (extrasRow.style.display === 'none' || extrasRow.style.display === '') {
            extrasRow.style.display = 'table-row';
            button.innerHTML = '▼';
            button.classList.add('active');
        } else {
            extrasRow.style.display = 'none';
            button.innerHTML = '▶';
            button.classList.remove('active');
        }
    }

    function togglePos(extraId) {
        const posRow = document.getElementById('pos-' + extraId);
        const button = event.target;
        
        // Alterna a visibilidade da linha selecionada
        if (posRow.style.display === 'none' || posRow.style.display === '') {
            posRow.style.display = 'table-row';
            button.innerHTML = '▼';
            button.classList.add('active');
        } else {
            posRow.style.display = 'none';
            button.innerHTML = '▶';
            button.classList.remove('active');
        }
    }
    </script>
        </tbody>
    </table>
</body>
</html>
