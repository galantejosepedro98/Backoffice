<?php
include 'auth.php';
include 'conexao.php';

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$erro = null;

// Buscar informações da categoria
$stmt = $pdo->prepare("SELECT * FROM pos_categories WHERE id = ?");
$stmt->execute([$category_id]);
$category = $stmt->fetch();

if (!$category) {
    header("Location: pos_categories.php");
    exit;
}

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Remover todas as associações existentes desta categoria
        $stmt = $pdo->prepare("DELETE FROM pos_categories_assignments WHERE category_id = ?");
        $stmt->execute([$category_id]);

        // Adicionar novas associações
        if (isset($_POST['pos']) && is_array($_POST['pos'])) {
            $stmt = $pdo->prepare("
                INSERT INTO pos_categories_assignments (category_id, pos_id) 
                VALUES (?, ?)
            ");
            
            foreach ($_POST['pos'] as $pos_id) {
                $stmt->execute([$category_id, $pos_id]);
            }
        }

        $pdo->commit();
        header("Location: pos_categories.php");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        $erro = "Erro ao salvar associações: " . $e->getMessage();
    }
}

// Buscar todos os PDVs
$stmt = $pdo->query("SELECT * FROM pos ORDER BY name");
$all_pos = $stmt->fetchAll();

// Buscar PDVs já associados
$stmt = $pdo->prepare("
    SELECT pos_id 
    FROM pos_categories_assignments 
    WHERE category_id = ?
");
$stmt->execute([$category_id]);
$assigned_pos = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Associar PDVs à Categoria</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            margin: 0;
        }
        .category-info {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .pos-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .pos-item {
            background: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
        }
        .pos-item label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        .pos-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        .search-box {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-secondary {
            background-color: #ccc;
            color: black;
            margin-left: 10px;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="category-info">
        <h1>Associar PDVs à Categoria: <?php echo htmlspecialchars($category['name']); ?></h1>
        <?php if ($erro): ?>
            <div class="error"><?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>
    </div>

    <input type="text" id="searchBox" class="search-box" 
           placeholder="Pesquisar PDVs..." 
           onkeyup="filterPOS(this.value)">

    <form method="POST">
        <div class="pos-list">
            <?php foreach ($all_pos as $pos): ?>
                <div class="pos-item" data-name="<?php echo htmlspecialchars(strtolower($pos['name'])); ?>">
                    <label>
                        <input type="checkbox" 
                               name="pos[]" 
                               value="<?php echo $pos['id']; ?>"
                               <?php echo in_array($pos['id'], $assigned_pos) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($pos['name']); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="pos_categories.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>

    <script>
        function filterPOS(query) {
            query = query.toLowerCase();
            const items = document.querySelectorAll('.pos-item');
            
            items.forEach(item => {
                const name = item.dataset.name;
                if (name.includes(query)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
