<?php
include 'auth.php';
include 'conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$erro = null;

// Se for edição, buscar dados do tipo
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM equipment_types WHERE id = ?");
    $stmt->execute([$id]);
    $tipo = $stmt->fetch();
    if (!$tipo) {
        die('Tipo de equipamento não encontrado');
    }
}

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($name)) {
        $erro = "O nome é obrigatório";
    } else {
        try {
            if ($id > 0) {
                // Atualizar
                $stmt = $pdo->prepare("
                    UPDATE equipment_types 
                    SET name = ?, description = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description, $id]);
            } else {
                // Inserir novo
                $stmt = $pdo->prepare("
                    INSERT INTO equipment_types (name, description, created_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$name, $description]);
            }
            
            header("Location: equipment_types.php");
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
    <title><?php echo $id ? 'Editar' : 'Novo'; ?> Tipo de Equipamento</title>
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
        input[type="text"],
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 100px;
            resize: vertical;
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

    <h1><?php echo $id ? 'Editar' : 'Novo'; ?> Tipo de Equipamento</h1>

    <?php if ($erro): ?>
        <div class="error"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="name">Nome:</label>
            <input type="text" id="name" name="name" required 
                   value="<?php echo isset($tipo['name']) ? htmlspecialchars($tipo['name']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="description">Descrição:</label>
            <textarea id="description" name="description"><?php 
                echo isset($tipo['description']) ? htmlspecialchars($tipo['description']) : ''; 
            ?></textarea>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="equipment_types.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</body>
</html>
