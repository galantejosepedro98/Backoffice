<?php
include 'auth.php';
include 'conexao.php';

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

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

$message = '';
$error = '';

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'associate') {
    $selected_products = isset($_POST['products']) ? $_POST['products'] : [];
    $selected_categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    
    if (empty($selected_products)) {
        $error = "Por favor, selecione pelo menos um bilhete.";
    } elseif (empty($selected_categories)) {
        $error = "Por favor, selecione pelo menos uma categoria.";
    } else {
        // Iniciar transação
        $pdo->beginTransaction();
        
        try {
            $total_associations = 0;
            
            // Opção selecionada: append ou replace
            $mode = isset($_POST['mode']) ? $_POST['mode'] : 'append';
            
            foreach ($selected_products as $product_id) {
                if ($mode === 'replace') {
                    // Se for modo substituir, remover todas as associações existentes primeiro
                    $stmtDelete = $pdo->prepare("DELETE FROM products_categories WHERE product_id = ?");
                    $stmtDelete->execute([$product_id]);
                }
                
                // Adicionar novas associações
                foreach ($selected_categories as $category_id) {
                    // Verificar se a associação já existe para evitar duplicatas (importante para o modo append)
                    $stmtCheck = $pdo->prepare("
                        SELECT COUNT(*) FROM products_categories 
                        WHERE product_id = ? AND product_category_id = ?
                    ");
                    $stmtCheck->execute([$product_id, $category_id]);
                    $exists = $stmtCheck->fetchColumn() > 0;
                    
                    if (!$exists) {
                        $stmtInsert = $pdo->prepare("
                            INSERT INTO products_categories (product_id, product_category_id, created_at) 
                            VALUES (?, ?, NOW())
                        ");
                        $stmtInsert->execute([$product_id, $category_id]);
                        $total_associations++;
                    }
                }
            }
            
            // Commit da transação
            $pdo->commit();
            $message = "Associações realizadas com sucesso! Total de {$total_associations} novas associações criadas.";
        } catch (PDOException $e) {
            // Rollback em caso de erro
            $pdo->rollBack();
            $error = "Erro ao processar associações: " . $e->getMessage();
        }
    }
}

// Obter todas as categorias para este evento
$stmtCategories = $pdo->prepare("SELECT * FROM product_categories WHERE event_id = ? ORDER BY name");
$stmtCategories->execute([$event_id]);
$categories = $stmtCategories->fetchAll();

// Construir a query para obter os bilhetes com base no filtro
$productQuery = "
    SELECT p.* FROM products p
    WHERE p.event_id = ? 
";

$params = [$event_id];

// Adicionar filtro de categoria se houver
if ($category_filter) {
    $productQuery .= "
        AND p.id IN (
            SELECT pc.product_id FROM products_categories pc 
            WHERE pc.product_category_id = ?
        )
    ";
    $params[] = $category_filter;
}

$productQuery .= " ORDER BY p.name";
$stmtProducts = $pdo->prepare($productQuery);
$stmtProducts->execute($params);
$products = $stmtProducts->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Associar Categorias em Massa - <?php echo htmlspecialchars($event['name']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1, h2, h3 {
            color: #333;
        }
        
        .header-info {
            margin-bottom: 20px;
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
        
        .form-section {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-title {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .product-list, .category-list {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .product-item, .category-item {
            flex: 0 0 33.333333%;
            padding: 10px;
            box-sizing: border-box;
        }
        
        @media (max-width: 992px) {
            .product-item, .category-item {
                flex: 0 0 50%;
            }
        }
        
        @media (max-width: 768px) {
            .product-item, .category-item {
                flex: 0 0 100%;
            }
        }
        
        .checkbox-item {
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .checkbox-item:hover {
            background-color: #f0f0f0;
        }
        
        .checkbox-item input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .filter-box {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        
        .select-actions {
            margin-bottom: 15px;
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
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group .radio-group {
            margin-top: 5px;
        }
        
        .form-group .radio-group label {
            display: inline;
            font-weight: normal;
            margin-right: 15px;
            cursor: pointer;
        }
        
        .search-box {
            padding: 8px;
            margin-bottom: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            width: 100%;
            box-sizing: border-box;
        }
        
        .mode-selection {
            margin-top: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="navigation-links">
            <a href="bilhetes.php?event_id=<?php echo $event_id; ?>">&larr; Voltar para Bilhetes</a>
            <a href="bilhetes_categorias.php?event_id=<?php echo $event_id; ?>">Gerenciar Categorias</a>
        </div>
        
        <div class="header-info">
            <h1>Associar Categorias em Massa</h1>
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
        
        <?php if (empty($categories)): ?>
            <div class="alert alert-danger">
                Não há categorias disponíveis para este evento. 
                <a href="bilhetes_categorias.php?event_id=<?php echo $event_id; ?>">Clique aqui para criar categorias</a>.
            </div>
        <?php elseif (empty($products)): ?>
            <div class="alert alert-danger">
                Não há bilhetes disponíveis para este evento.
                <a href="bilhetes.php?event_id=<?php echo $event_id; ?>">Clique aqui para voltar à lista de bilhetes</a>.
            </div>
        <?php else: ?>
            <!-- Filtro de categorias -->
            <div class="filter-box">
                <h3>Filtrar Bilhetes por Categoria</h3>
                <form method="get" action="">
                    <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                    
                    <div class="form-group">
                        <label for="category">Categoria:</label>
                        <select id="category" name="category" onchange="this.form.submit()">
                            <option value="0">Todos os bilhetes</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="associate">
                
                <div class="form-section">
                    <h2 class="form-title">1. Selecione os Bilhetes</h2>
                    
                    <div class="select-actions">
                        <button type="button" class="btn btn-secondary" onclick="selectAllProducts()">Selecionar Todos</button>
                        <button type="button" class="btn btn-secondary" onclick="deselectAllProducts()">Desmarcar Todos</button>
                        <input type="text" id="productSearch" placeholder="Buscar bilhetes..." class="search-box">
                    </div>
                    
                    <div class="product-list">
                        <?php foreach ($products as $product): ?>
                            <div class="product-item">
                                <div class="checkbox-item product-checkbox-item">
                                    <input type="checkbox" id="product<?php echo $product['id']; ?>" name="products[]" value="<?php echo $product['id']; ?>">
                                    <label for="product<?php echo $product['id']; ?>">
                                        <?php echo htmlspecialchars($product['name']); ?> - 
                                        €<?php echo number_format($product['price'] / 100, 2, '.', ''); ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2 class="form-title">2. Selecione as Categorias</h2>
                    
                    <div class="select-actions">
                        <button type="button" class="btn btn-secondary" onclick="selectAllCategories()">Selecionar Todas</button>
                        <button type="button" class="btn btn-secondary" onclick="deselectAllCategories()">Desmarcar Todas</button>
                        <input type="text" id="categorySearch" placeholder="Buscar categorias..." class="search-box">
                    </div>
                    
                    <div class="category-list">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-item">
                                <div class="checkbox-item category-checkbox-item">
                                    <input type="checkbox" id="category<?php echo $category['id']; ?>" name="categories[]" value="<?php echo $category['id']; ?>">
                                    <label for="category<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                        <?php if (!empty($category['description'])): ?>
                                            <br>
                                            <small><?php echo htmlspecialchars(substr($category['description'], 0, 50)) . (strlen($category['description']) > 50 ? '...' : ''); ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mode-selection">
                    <h3>3. Escolha o Modo de Associação</h3>
                    <div class="form-group">
                        <div class="radio-group">
                            <input type="radio" id="mode-append" name="mode" value="append" checked>
                            <label for="mode-append">Adicionar às categorias existentes</label>
                            
                            <input type="radio" id="mode-replace" name="mode" value="replace">
                            <label for="mode-replace">Substituir categorias existentes</label>
                        </div>
                    </div>
                    <p><small><strong>Adicionar:</strong> Mantém as categorias existentes e adiciona as novas selecionadas<br>
                    <strong>Substituir:</strong> Remove todas as categorias existentes e adiciona apenas as selecionadas</small></p>
                </div>
                
                <button type="submit" class="btn btn-primary" style="margin-top: 20px; padding: 10px 20px;">Associar Categorias</button>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        function selectAllProducts() {
            var checkboxes = document.querySelectorAll('input[name="products[]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = true;
            });
        }
        
        function deselectAllProducts() {
            var checkboxes = document.querySelectorAll('input[name="products[]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = false;
            });
        }
        
        function selectAllCategories() {
            var checkboxes = document.querySelectorAll('input[name="categories[]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = true;
            });
        }
        
        function deselectAllCategories() {
            var checkboxes = document.querySelectorAll('input[name="categories[]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = false;
            });
        }
        
        // Função de busca para bilhetes
        document.getElementById('productSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const productItems = document.querySelectorAll('.product-checkbox-item');
            
            productItems.forEach(function(item) {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    item.parentElement.style.display = 'block';
                } else {
                    item.parentElement.style.display = 'none';
                }
            });
        });
        
        // Função de busca para categorias
        document.getElementById('categorySearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const categoryItems = document.querySelectorAll('.category-checkbox-item');
            
            categoryItems.forEach(function(item) {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    item.parentElement.style.display = 'block';
                } else {
                    item.parentElement.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
