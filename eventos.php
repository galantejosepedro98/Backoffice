<?php
include 'auth.php';
include 'conexao.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Eventos - Painel Local</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #eee; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <h1>Eventos Ativos</h1>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Local</th>
                <th>Data Início</th>
                <th>Data Fim</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>        <?php
        $stmt = $pdo->query("SELECT * FROM events WHERE status = 1 ORDER BY start_at DESC");
        while ($row = $stmt->fetch()) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['id']) . '</td>';
            echo '<td>' . htmlspecialchars($row['name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['location']) . ' - ' . htmlspecialchars($row['city']) . '</td>';
            echo '<td>' . date('d/m/Y H:i', strtotime($row['start_at'])) . '</td>';
            echo '<td>' . date('d/m/Y H:i', strtotime($row['end_at'])) . '</td>';            echo '<td>' . ($row['status'] ? 'Ativo' : 'Inativo') . '</td>';            echo '<td>';
            echo '<a href="categorias.php?event_id=' . $row['id'] . '" title="Ver Categorias" style="text-decoration: none; font-size: 18px; margin-right: 10px;">📋</a>';
            echo '<a href="event_management.php?id=' . $row['id'] . '" title="Gestão" style="text-decoration: none; font-size: 18px; margin-right: 10px;">⚙️</a>';
            echo '<a href="bilhetes.php?event_id=' . $row['id'] . '" title="Tickets" style="text-decoration: none; font-size: 18px; margin-right: 10px;">🎟️</a>';
            echo '<a href="live_dashboard.php?event_id=' . $row['id'] . '" title="LIVE Dashboard" style="text-decoration: none; font-size: 18px; color: red; margin-right: 10px;">📊</a>';
            echo '</td>';
            echo '</tr>';
        }
        ?>
        </tbody>
    </table>
</body>
</html>
