<?php
include 'auth.php';
include 'conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$erro = null;

// Se for edição, buscar dados da categoria
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM pos_categories WHERE id = ?");
    $stmt->execute([$id]);
    $categoria = $stmt->fetch();
    if (!$categoria) {
        die('Categoria não encontrada');
    }
}

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    
    if (empty($name)) {
        $erro = "O nome é obrigatório";
    } else {
        try {
            if ($id > 0) {
                // Atualizar
                $stmt = $pdo->prepare("UPDATE pos_categories SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
            } else {
                // Inserir novo
                $stmt = $pdo->prepare("INSERT INTO pos_categories (name) VALUES (?)");
                $stmt->execute([$name]);
            }
            
            header("Location: pos_categories.php");
            exit;
        } catch (PDOException $e) {
            $erro = "Erro ao salvar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $id ? 'Editar' : 'Nova'; ?> Categoria de PDV</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            margin: 0;
        }
        .form-group { 
            margin-bottom: 15px; 
        }
        label { 
            display: block; 
            margin-bottom: 5px; 
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <h1><?php echo $id ? 'Editar' : 'Nova'; ?> Categoria de PDV</h1>

    <?php if ($erro): ?>
        <div class="error"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="name">Nome</label>
            <input type="text" id="name" name="name" 
                   value="<?php echo $id ? htmlspecialchars($categoria['name']) : ''; ?>" 
                   required>
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="pos_categories.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</body>
</html>
