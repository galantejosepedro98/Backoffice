<?php
include 'auth.php';
include 'conexao.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Validar parâmetros
if (!$product_id) {
    die('ID do bilhete não fornecido');
}

// Obter informações do bilhete
$stmtProduct = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmtProduct->execute([$product_id]);
$product = $stmtProduct->fetch();

if (!$product) {
    die('Bilhete não encontrado');
}

// Se o event_id não foi fornecido, obtenha do bilhete
if (!$event_id && isset($product['event_id'])) {
    $event_id = (int)$product['event_id'];
}

// Obter informações do evento
$stmtEvent = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmtEvent->execute([$event_id]);
$event = $stmtEvent->fetch();

if (!$event) {
    die('Evento não encontrado');
}

$message = '';
$error = '';

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Iniciar transação
    $pdo->beginTransaction();
    
    try {
        // Remover todas as associações existentes para este bilhete
        $stmtDelete = $pdo->prepare("DELETE FROM products_categories WHERE product_id = ?");
        $stmtDelete->execute([$product_id]);
        
        // Adicionar novas associações
        $selectedCategories = isset($_POST['categories']) ? $_POST['categories'] : [];
        
        if (!empty($selectedCategories)) {
            $stmtInsert = $pdo->prepare("INSERT INTO products_categories (product_id, product_category_id, created_at) VALUES (?, ?, NOW())");
            
            foreach ($selectedCategories as $category_id) {
                $stmtInsert->execute([$product_id, $category_id]);
            }
        }
        
        // Commit da transação
        $pdo->commit();
        $message = "Categorias atualizadas com sucesso!";
    } catch (PDOException $e) {
        // Rollback em caso de erro
        $pdo->rollBack();
        $error = "Erro ao atualizar categorias: " . $e->getMessage();
    }
}

// Obter todas as categorias para este evento
$stmtCategories = $pdo->prepare("SELECT * FROM product_categories WHERE event_id = ? ORDER BY name");
$stmtCategories->execute([$event_id]);
$categories = $stmtCategories->fetchAll();

// Obter as categorias já associadas a este bilhete
$stmtAssociated = $pdo->prepare("
    SELECT pc.product_category_id 
    FROM products_categories pc
    WHERE pc.product_id = ?
");
$stmtAssociated->execute([$product_id]);
$associatedCategories = array_map(function($item) {
    return $item['product_category_id'];
}, $stmtAssociated->fetchAll());
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Associar Categorias ao Bilhete - <?php echo htmlspecialchars($product['name']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        h1, h2 {
            color: #333;
        }
        
        .product-info {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 4px;
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
        
        .form-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .category-list {
            margin-top: 20px;
        }
        
        .category-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .category-item:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .category-item label {
            display: block;
            margin-left: 30px;
        }
        
        .category-item input[type="checkbox"] {
            float: left;
            margin-top: 3px;
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
        
        .btn-secondary {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .select-actions {
            margin-bottom: 10px;
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="navigation-links">
            <a href="bilhetes.php?event_id=<?php echo $event_id; ?>">&larr; Voltar para Bilhetes</a>
            <a href="bilhetes_categorias.php?event_id=<?php echo $event_id; ?>">Gerenciar Categorias de Bilhetes</a>
        </div>
        
        <h1>Associar Categorias ao Bilhete</h1>
        
        <div class="product-info">
            <h2><?php echo htmlspecialchars($product['name']); ?></h2>
            <p><strong>Preço:</strong> <?php echo number_format($product['price'] / 100, 2, '.', ''); ?>€</p>
            <p><strong>Evento:</strong> <?php echo htmlspecialchars($event['name']); ?></p>
            <?php if ($event['location']): ?>
                <p><strong>Local:</strong> <?php echo htmlspecialchars($event['location']); ?></p>
            <?php endif; ?>
            <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($event['start_at'])); ?></p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <h3>Selecione as categorias para este bilhete</h3>
            
            <?php if (empty($categories)): ?>
                <p>Não há categorias disponíveis para este evento. <a href="bilhetes_categorias.php?event_id=<?php echo $event_id; ?>">Clique aqui para criar categorias</a>.</p>
            <?php else: ?>
                <form method="post">
                    <div class="select-actions">
                        <button type="button" onclick="selectAllCategories()" class="btn btn-secondary">Selecionar Todas</button>
                        <button type="button" onclick="deselectAllCategories()" class="btn btn-secondary">Desmarcar Todas</button>
                    </div>
                    
                    <div class="category-list">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-item">
                                <input type="checkbox" id="category<?php echo $category['id']; ?>" 
                                       name="categories[]" value="<?php echo $category['id']; ?>"
                                       <?php echo in_array($category['id'], $associatedCategories) ? 'checked' : ''; ?>>
                                <label for="category<?php echo $category['id']; ?>">
                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                    <?php if (!empty($category['description'])): ?>
                                        <br><small><?php echo htmlspecialchars($category['description']); ?></small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">Salvar Associações</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function selectAllCategories() {
            var checkboxes = document.querySelectorAll('input[name="categories[]"]');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = true;
            }
        }
        
        function deselectAllCategories() {
            var checkboxes = document.querySelectorAll('input[name="categories[]"]');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = false;
            }
        }
    </script>
</body>
</html>
