<?php
include 'auth.php';
include 'conexao.php';

// Handle delete action
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // Verificar se a categoria está em uso
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM pos_categories_assignments WHERE category_id = ?");
        $stmtCheck->execute([$id]);
        if ($stmtCheck->fetchColumn() > 0) {
            $erro = "Não é possível excluir esta categoria pois existem PDVs associados a ela.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM pos_categories WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: pos_categories.php");
            exit;
        }
    } catch (PDOException $e) {
        $erro = "Erro ao excluir: " . $e->getMessage();
    }
}

// Fetch all categories with count of associated PDVs
$stmt = $pdo->query("
    SELECT pc.*, COUNT(pca.pos_id) as pos_count 
    FROM pos_categories pc
    LEFT JOIN pos_categories_assignments pca ON pc.id = pca.category_id
    GROUP BY pc.id
    ORDER BY pc.name
");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Categorias de PDV</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            margin: 0;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px 8px; 
            text-align: left; 
        }
        th { 
            background-color: #f5f5f5; 
        }
        tr:hover {
            background-color: #f9f9f9;
        }
        .btn {
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            margin: 2px;
            cursor: pointer;
        }
        .btn-create {
            background-color: #4CAF50;
            color: white;
        }
        .btn-edit {
            background-color: #2196F3;
            color: white;
        }
        .btn-delete {
            background-color: #f44336;
            color: white;
        }
        .count-badge {
            background-color: #2196F3;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.8em;
            margin-left: 5px;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <h1>Categorias de PDV</h1>

    <?php if (isset($erro)): ?>
        <div class="error"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <div style="margin: 20px 0;">
        <a href="pos_category_form.php" class="btn btn-create">+ Nova Categoria</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Qtd. PDVs</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </td>
                    <td><?php echo $category['pos_count']; ?></td>                    <td>
                        <a href="pos_category_assign.php?category_id=<?php echo $category['id']; ?>" 
                           class="btn btn-create">Associar PDVs</a>
                        <a href="pos_category_form.php?id=<?php echo $category['id']; ?>" 
                           class="btn btn-edit">Editar</a>
                        <?php if ($category['pos_count'] == 0): ?>
                            <a href="pos_categories.php?delete=<?php echo $category['id']; ?>" 
                               class="btn btn-delete"
                               onclick="return confirm('Tem certeza que deseja excluir esta categoria?')">
                                Excluir
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
