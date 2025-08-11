<?php
include 'auth.php';
include 'conexao.php';

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Validate event_id
if (!$event_id) {
    die('ID do evento não fornecido');
}

// Get event information
$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtEvent->execute([$event_id]);
$event = $stmtEvent->fetch();

if (!$event) {
    die('Evento não encontrado');
}

// Get all categories for this event for the filter
$stmtCategories = $pdo->prepare("
    SELECT * FROM product_categories 
    WHERE event_id = ? 
    ORDER BY name
");
$stmtCategories->execute([$event_id]);
$categories = $stmtCategories->fetchAll();

// Base query
if ($category_filter) {
    // If category filter is applied, get tickets from that category
    $stmtTickets = $pdo->prepare("
        SELECT p.* FROM products p
        JOIN products_categories pc ON pc.product_id = p.id
        WHERE p.event_id = ? 
        AND pc.product_category_id = ?
        ORDER BY p.name
    ");
    $stmtTickets->execute([$event_id, $category_filter]);
} else {
    // If no category filter, get all tickets
    $stmtTickets = $pdo->prepare("
        SELECT * FROM products 
        WHERE event_id = ? 
        ORDER BY name
    ");
    $stmtTickets->execute([$event_id]);
}
$tickets = $stmtTickets->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bilhetes - <?php echo htmlspecialchars($event['name']); ?></title>
    <style>
        .event-info {
            margin-bottom: 20px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }

        .actions-container {
            margin-top: 20px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .btn {
            display: inline-block;
            padding: 6px 12px;
            margin-bottom: 0;
            font-size: 14px;
            font-weight: 400;
            line-height: 1.42857143;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: 4px;
            text-decoration: none;
        }

        .btn-primary {
            color: #fff;
            background-color: #007bff;
            border-color: #007bff;
        }

        .btn-edit {
            color: #fff;
            background-color: #28a745;
            border-color: #28a745;
        }

        .btn-delete {
            color: #fff;
            background-color: #dc3545;
            border-color: #dc3545;
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

        .action-buttons {
            white-space: nowrap;
        }

        .action-buttons .btn {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="event-info">
        <h1><?php echo htmlspecialchars($event['name']); ?></h1>
        <?php if ($event['location']): ?>
            <p>Local: <?php echo htmlspecialchars($event['location']); ?></p>
        <?php endif; ?>
        <p>Data: <?php echo date('d/m/Y', strtotime($event['start_at'])); ?></p>        <div class="actions-container">
            <a href="criar_bilhete.php?event_id=<?php echo $event_id; ?>" class="btn btn-primary">
                + Criar Novo Bilhete
            </a>
            <a href="bilhetes_categorias.php?event_id=<?php echo $event_id; ?>" class="btn btn-primary">
                Gerenciar Categorias
            </a>
            <a href="associar_categorias_massa.php?event_id=<?php echo $event_id; ?>" class="btn btn-primary">
                Associar Categorias em Massa
            </a>
        </div>
        
        <!-- Filtro por categoria -->
        <div class="filter-container" style="margin-top: 20px; margin-bottom: 20px;">
            <form method="get" action="" style="display: flex; align-items: center;">
                <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                
                <select name="category" style="padding: 8px; margin-right: 10px; border-radius: 4px; border: 1px solid #ddd;">
                    <option value="0">Todas as categorias</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn btn-primary" style="margin-right: 10px;">Filtrar</button>
                
                <?php if ($category_filter): ?>
                    <a href="bilhetes.php?event_id=<?php echo $event_id; ?>" class="btn btn-secondary">Limpar Filtro</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (!empty($tickets)): ?>
        <table>
            <thead>
                <tr>                    <th>ID</th>
                    <th>Nome</th>
                    <th>Preço</th>
                    <th>Tipo de Taxa</th>
                    <th>Tipo</th>
                    <th>TOC Item Code</th>
                    <th>TOC Item ID</th>
                    <th>Categorias</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ticket['id']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['name']); ?></td>                        <td><?php echo number_format($ticket['price'] / 100, 2, '.', ''); ?>€</td>
                        <td><?php echo htmlspecialchars($ticket['tax_type']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['type']); ?></td>                        <td><?php echo htmlspecialchars($ticket['toconline_item_code']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['toconline_item_id']); ?></td>
                        <td>
                            <?php
                            // Get categories for this ticket
                            $stmtTicketCategories = $pdo->prepare("
                                SELECT pc.name
                                FROM products_categories pcat
                                JOIN product_categories pc ON pc.id = pcat.product_category_id
                                WHERE pcat.product_id = ?
                                ORDER BY pc.name
                                LIMIT 3
                            ");
                            $stmtTicketCategories->execute([$ticket['id']]);
                            $ticketCategories = $stmtTicketCategories->fetchAll(PDO::FETCH_COLUMN);
                            
                            if (!empty($ticketCategories)) {
                                echo implode(', ', array_map('htmlspecialchars', $ticketCategories));
                                
                                // If there are more than 3 categories, show a "+X more" message
                                $stmtCategoryCount = $pdo->prepare("
                                    SELECT COUNT(*) FROM products_categories
                                    WHERE product_id = ?
                                ");
                                $stmtCategoryCount->execute([$ticket['id']]);
                                $totalCategories = $stmtCategoryCount->fetchColumn();
                                
                                if ($totalCategories > 3) {
                                    echo ' <small>+' . ($totalCategories - 3) . ' mais</small>';
                                }
                            } else {
                                echo '<small>Sem categorias</small>';
                            }
                            ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $ticket['status']; ?>">
                                <?php echo $ticket['status'] === 'active' ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                        <td class="action-buttons">                            <a href="editar_bilhete.php?id=<?php echo $ticket['id']; ?>&event_id=<?php echo $event_id; ?>" class="btn btn-edit">Editar</a>
                            <a href="associar_categorias_bilhete.php?id=<?php echo $ticket['id']; ?>&event_id=<?php echo $event_id; ?>" class="btn btn-primary">Categorias</a>
                            <button onclick="deleteTicket(<?php echo $ticket['id']; ?>)" class="btn btn-delete">Excluir</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nenhum bilhete cadastrado para este evento.</p>
    <?php endif; ?>

    <script>
        function deleteTicket(id) {
            if (confirm('Tem certeza que deseja excluir este bilhete?')) {
                window.location.href = `bilhetes.php?action=delete&id=${id}&event_id=<?php echo $event_id; ?>`;
            }
        }
    </script>
</body>
</html>
