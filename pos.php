<?php
include 'auth.php';
include 'conexao.php';

// Buscar apenas os pontos de venda que têm extras associados
$stmtPos = $pdo->prepare("
    SELECT DISTINCT p.*, e.event_id 
    FROM pos p 
    INNER JOIN extra_pos ep ON ep.pos_id = p.id 
    INNER JOIN extras e ON e.id = ep.extra_id
    ORDER BY p.name
");
$stmtPos->execute();
$pontos_venda = $stmtPos->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pontos de Venda</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; margin: 0; }
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

    <div class="container">
        <h1>Pontos de Venda</h1>
        
        <table>
            <thead>                <tr>
                    <th></th>
                    <th>ID</th>
                    <th>Nome</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pontos_venda as $pos): 
                    // Buscar extras associados a este POS
                    $stmtExtras = $pdo->prepare("
                        SELECT e.* FROM extras e 
                        INNER JOIN extra_pos ep ON ep.extra_id = e.id 
                        WHERE ep.pos_id = ?
                    ");
                    $stmtExtras->execute([$pos['id']]);
                    $extras = $stmtExtras->fetchAll();
                    $numExtras = count($extras);
                ?>
                    <tr class="pos-row">                        <td>
                            <button class="expand-button" onclick="toggleExtras(<?php echo $pos['id']; ?>)">▶</button>
                        </td>
                        <td><?php echo htmlspecialchars($pos['id']); ?></td>
                        <td><?php echo htmlspecialchars($pos['name']); ?> (<?php echo $numExtras; ?> extras)</td></tr>
                    <tr id="extras-<?php echo $pos['id']; ?>" class="extras-row">                        <td colspan="3">                                <div style="margin-bottom: 10px;">
                                    <a href="criar_extra.php?pos_id=<?php echo $pos['id']; ?>&event_id=<?php echo $pos['event_id']; ?>" 
                                       style="padding: 8px 15px; background-color: #4CAF50; color: white; 
                                              text-decoration: none; border-radius: 4px; font-size: 14px;">
                                        + Adicionar Extra
                                    </a>
                                </div>
                                <table class="extras-table" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>Preço</th>
                                            <th>Tipo de Taxa</th>
                                            <th>Tipo</th>
                                            <th>TOC Item Code</th>
                                            <th>TOC Item ID</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($extras as $extra): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($extra['id']); ?></td>
                                                <td><?php echo htmlspecialchars($extra['display_name']); ?></td>
                                                <td><?php echo number_format($extra['price'], 2); ?>€</td>
                                                <td><?php echo htmlspecialchars($extra['tax_type']); ?>%</td>
                                                <td><?php echo htmlspecialchars($extra['type']); ?></td>
                                                <td><?php echo htmlspecialchars($extra['toconline_item_code'] ?: '-'); ?></td>
                                                <td><?php echo htmlspecialchars($extra['toconline_item_id'] ?: '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </td>                        </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    function toggleExtras(posId) {
        const extrasRow = document.getElementById('extras-' + posId);
        const button = event.target;
        const allButtons = document.querySelectorAll('.expand-button');
        
        // Fecha todas as outras linhas de extras
        document.querySelectorAll('.extras-row').forEach(row => {
            if (row.id !== 'extras-' + posId) {
                row.style.display = 'none';
            }
        });
        
        // Remove a classe active de todos os botões
        allButtons.forEach(btn => {
            if (btn !== button) {
                btn.innerHTML = '▶';
                btn.classList.remove('active');
            }
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
    </script>
</body>
</html>
