<?php 
include 'auth.php';
include 'conexao.php';
$id = $_GET['id'] ?? 0;

$stmtCat = $pdo->prepare("SELECT * FROM extra_categories WHERE id = ?");
$stmtCat->execute([$id]);
$categoria = $stmtCat->fetch();

$stmtExtras = $pdo->prepare("SELECT * FROM extras WHERE extra_category_id = ?");
$stmtExtras->execute([$id]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Categoria: <?= htmlspecialchars($categoria['name']) ?></title>
</head>
<body>
    <h1>Categoria: <?= htmlspecialchars($categoria['name']) ?></h1>
    <a href="criar_extra.php?categoria_id=<?= $categoria['id'] ?>">Criar novo Extra nesta categoria</a>
    <h2>Extras</h2>
    <ul>
        <?php while ($extra = $stmtExtras->fetch()): ?>
            <li><?= htmlspecialchars($extra['name']) ?> (<?= htmlspecialchars($extra['price']) ?>€)</li>
        <?php endwhile; ?>
    </ul>
</body>
</html>
