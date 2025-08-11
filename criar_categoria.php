<?php
include 'auth.php';
include 'conexao.php';

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Verificar se o evento existe
$stmtEvento = $pdo->prepare("SELECT name FROM events WHERE id = ?");
$stmtEvento->execute([$event_id]);
$evento = $stmtEvento->fetch();

if (!$evento) {
    die('Evento não encontrado');
}

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $order = $_POST['order'] ?? 0;
    
    if (empty($name)) {
        $erro = "O nome da categoria é obrigatório";
    } else {
        try {
            // Gerar o slug a partir do nome
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            
            $stmt = $pdo->prepare("INSERT INTO extra_categories (event_id, name, slug, `order`, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$event_id, $name, $slug, $order]);
            
            // Redirecionar de volta para a página de categorias
            header("Location: categorias.php?event_id=" . $event_id);
            exit;
        } catch (PDOException $e) {
            $erro = "Erro ao criar categoria: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Criar Nova Categoria - <?php echo htmlspecialchars($evento['name']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"],
        input[type="number"] { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box; 
        }
        button { 
            padding: 10px 20px; 
            background-color: #4CAF50; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
        }
        button:hover { background-color: #45a049; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
    <p><a href="categorias.php?event_id=<?php echo $event_id; ?>">&larr; Voltar para Categorias</a></p>
    
    <h1>Criar Nova Categoria</h1>
    <h2>Evento: <?php echo htmlspecialchars($evento['name']); ?></h2>

    <?php if (isset($erro)): ?>
        <div class="error"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="name">Nome da Categoria:</label>
            <input type="text" id="name" name="name" required 
                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="order">Ordem de Exibição:</label>
            <input type="number" id="order" name="order" min="0" 
                   value="<?php echo isset($_POST['order']) ? (int)$_POST['order'] : 0; ?>">
        </div>

        <button type="submit">Criar Categoria</button>
    </form>
</body>
</html>
