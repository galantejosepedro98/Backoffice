<?php
// Página inicial para gestão de encomendas (orders)
// Aqui será listado o conteúdo da tabela orders

include 'auth.php';
include 'conexao.php';

// Buscar as primeiras 100 encomendas para exemplo
$stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 100");
$orders = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Encomendas</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>Encomendas</h1>
    <table>
        <tr>
            <?php foreach(array_keys($orders[0] ?? []) as $col): ?>
                <th><?= htmlspecialchars($col) ?></th>
            <?php endforeach; ?>
        </tr>
        <?php foreach($orders as $order): ?>
        <tr>
            <?php foreach($order as $val): ?>
                <td><?= htmlspecialchars($val) ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
