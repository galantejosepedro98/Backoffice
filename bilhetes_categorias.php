<?php
include 'auth.php';
include 'conexao.php';

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Validar event_id
if (!$event_id) {
    die('ID do evento não fornecido');
}

// Obter informações do evento
$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtEvent->execute([$event_id]);
$event = $stmtEvent->fetch();

if (!$event) {
    die('Evento não encontrado');
}

// Processar operações CRUD
$message = '';
$error = '';

// Excluir categoria
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $category_id = (int)$_GET['id'];
    
    try {
        // Primeiro remover todas as associações na tabela products_categories
        $stmtDeleteAssociations = $pdo->prepare("DELETE FROM products_categories WHERE product_category_id = ?");
        $stmtDeleteAssociations->execute([$category_id]);
        
        // Agora excluir a categoria
        $stmtDelete = $pdo->prepare("DELETE FROM product_categories WHERE id = ? AND event_id = ?");
        $stmtDelete->execute([$category_id, $event_id]);
        
        if ($stmtDelete->rowCount() > 0) {
            $message = "Categoria excluída com sucesso!";
        } else {
            $error = "Erro ao excluir categoria. Verifique se a categoria existe e pertence a este evento.";
        }
    } catch (PDOException $e) {
        $error = "Erro ao excluir categoria: " . $e->getMessage();
    }
}

// Criar nova categoria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($name)) {
        $error = "O nome da categoria é obrigatório";
    } else {
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO product_categories (name, description, event_id, status, created_at, updated_at)
                VALUES (?, ?, ?, 'active', NOW(), NOW())
            ");
            $stmtInsert->execute([$name, $description, $event_id]);
            
            $message = "Categoria criada com sucesso!";
        } catch (PDOException $e) {
            $error = "Erro ao criar categoria: " . $e->getMessage();
        }
    }
}

// Editar categoria existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $category_id = (int)$_POST['category_id'];
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name)) {
        $error = "O nome da categoria é obrigatório";
    } else {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE product_categories
                SET name = ?, description = ?, status = ?, updated_at = NOW()
                WHERE id = ? AND event_id = ?
            ");
            $stmtUpdate->execute([$name, $description, $status, $category_id, $event_id]);
            
            if ($stmtUpdate->rowCount() > 0) {
                $message = "Categoria atualizada com sucesso!";
            } else {
                $error = "Nenhuma alteração feita ou categoria não encontrada.";
            }
        } catch (PDOException $e) {
            $error = "Erro ao atualizar categoria: " . $e->getMessage();
        }
    }
}

// Obter todas as categorias de produto para este evento
$stmtCategories = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(pc.product_id) FROM products_categories pc WHERE pc.product_category_id = c.id) AS product_count
    FROM product_categories c
    WHERE c.event_id = ?
    ORDER BY c.name
");
$stmtCategories->execute([$event_id]);
$categories = $stmtCategories->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Categorias de Bilhetes - <?php echo htmlspecialchars($event['name']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .event-info {
            margin-bottom: 20px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        
        .actions-container {
            margin: 20px 0;
        }
        
        .category-form {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        
        .category-form h2 {
            margin-top: 0;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input[type="text"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
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
        
        .btn-success {
            color: #fff;
            background-color: #28a745;
            border-color: #28a745;
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
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .edit-form-container {
            display: none;
        }
        
        .navigation-links {
            margin-bottom: 20px;
        }
        
        .navigation-links a {
            color: #007bff;
            text-decoration: none;
            margin-right: 15px;
        }
        
        .navigation-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="navigation-links">
            <a href="event_management.php?id=<?php echo $event_id; ?>">&larr; Voltar para Gestão do Evento</a>
            <a href="bilhetes.php?event_id=<?php echo $event_id; ?>">&larr; Voltar para Bilhetes</a>
        </div>
        
        <div class="event-info">
            <h1>Categorias de Bilhetes - <?php echo htmlspecialchars($event['name']); ?></h1>
            <?php if ($event['location']): ?>
                <p>Local: <?php echo htmlspecialchars($event['location']); ?></p>
            <?php endif; ?>
            <p>Data: <?php echo date('d/m/Y', strtotime($event['start_at'])); ?></p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="category-form">
            <h2>Nova Categoria de Bilhete</h2>
            <form method="post">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="name">Nome da Categoria:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Descrição (opcional):</label>
                    <textarea id="description" name="description"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Criar Categoria</button>
            </form>
        </div>
        
        <h2>Categorias Existentes</h2>
        
        <?php if (!empty($categories)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Descrição</th>
                        <th>Status</th>
                        <th>Bilhetes</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo $category['id']; ?></td>
                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                            <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $category['status']; ?>">
                                    <?php echo $category['status'] === 'active' ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td><?php echo $category['product_count']; ?></td>
                            <td class="action-buttons">
                                <button class="btn btn-edit" onclick="showEditForm(<?php echo $category['id']; ?>, '<?php echo addslashes(htmlspecialchars($category['name'])); ?>', '<?php echo addslashes(htmlspecialchars($category['description'] ?? '')); ?>', '<?php echo $category['status']; ?>')">Editar</button>
                                <button class="btn btn-delete" onclick="confirmDelete(<?php echo $category['id']; ?>, '<?php echo addslashes(htmlspecialchars($category['name'])); ?>')">Excluir</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nenhuma categoria cadastrada para este evento.</p>
        <?php endif; ?>
        
        <!-- Formulário de edição oculto -->
        <div id="edit-form-container" class="edit-form-container category-form">
            <h2>Editar Categoria</h2>
            <form method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" id="edit-category-id" name="category_id" value="">
                
                <div class="form-group">
                    <label for="edit-name">Nome da Categoria:</label>
                    <input type="text" id="edit-name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit-description">Descrição (opcional):</label>
                    <textarea id="edit-description" name="description"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit-status">Status:</label>
                    <select id="edit-status" name="status">
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-success">Atualizar Categoria</button>
                <button type="button" class="btn btn-secondary" onclick="hideEditForm()">Cancelar</button>
            </form>
        </div>
        
        <script>
            function showEditForm(id, name, description, status) {
                document.getElementById('edit-category-id').value = id;
                document.getElementById('edit-name').value = name;
                document.getElementById('edit-description').value = description;
                document.getElementById('edit-status').value = status;
                
                document.getElementById('edit-form-container').style.display = 'block';
                document.getElementById('edit-form-container').scrollIntoView({ behavior: 'smooth' });
            }
            
            function hideEditForm() {
                document.getElementById('edit-form-container').style.display = 'none';
            }
            
            function confirmDelete(id, name) {
                if (confirm('Tem certeza que deseja excluir a categoria "' + name + '"?\nIsso também removerá todas as associações com bilhetes.')) {
                    window.location.href = 'bilhetes_categorias.php?event_id=<?php echo $event_id; ?>&action=delete&id=' + id;
                }
            }
        </script>
    </div>
</body>
</html>
