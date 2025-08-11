<?php
include 'auth.php';
include 'conexao.php';

$error = null;
$return_to = isset($_GET['return_to']) ? $_GET['return_to'] : 'pos.php';
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $permission = '{"tickets":"0","extras":"1","scan":"0","report":"1"}';
    $payment_methods = '{"card":"1","qr":"1","cash":"0"}';
    $can_print = 1;

    if (empty($name)) {
        $error = "O nome do ponto de venda é obrigatório.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pos (name, permission, payment_methods, can_print, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
              $stmt->execute([$name, $permission, $payment_methods, $can_print]);
            
            // Redirecionar de volta para a página apropriada
            if ($return_to === 'event_pos_form' && $event_id) {
                header("Location: event_pos_form.php?event_id=" . $event_id);
            } else {
                header("Location: pos.php");
            }
            exit;
        } catch (PDOException $e) {
            $error = "Erro ao salvar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Criar Ponto de Venda</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            margin: 0;
        }
        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 100px;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-secondary {
            background-color: #666;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="form-container">
        <h1>Criar Novo Ponto de Venda</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nome:</label>
                <input type="text" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
            </div>            <div class="form-group">                <p>Por padrão, o ponto de venda será criado com:</p>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Permissões: Extras e Report</li>
                    <li>Métodos de pagamento: Cartão (✓) | QR Code (✓) | Dinheiro (✗)</li>
                    <li>Impressão habilitada</li>
                </ul>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Salvar</button>
                <?php if ($return_to === 'event_pos_form' && $event_id): ?>
                    <a href="event_pos_form.php?event_id=<?php echo $event_id; ?>" class="btn btn-secondary">Cancelar</a>
                <?php else: ?>
                    <a href="pos.php" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</body>
</html>
