<?php
include 'auth.php';
include 'conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = null;
$message = null;

// Verificar se o usuário existe
$stmt = $pdo->prepare("
    SELECT id, email 
    FROM users 
    WHERE id = ?
");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: users.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Gerar nova senha de 4 dígitos
        $new_password = sprintf("%04d", rand(0, 9999));
        
        $pdo->beginTransaction();
        
        // Atualizar a senha no users
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        // Criar hash da nova senha
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt->execute([$hashed_password, $id]);
        
        // Salvar senha em texto claro
        $stmt = $pdo->prepare("
            INSERT INTO staff_passwords (user_id, clear_password, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$id, $new_password]);
        
        $pdo->commit();
        $message = "Nova senha gerada com sucesso! A senha é: " . $new_password;
        
        // Redirecionar após 2 segundos
        header("Refresh: 2; URL=users.php");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro ao atualizar senha: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Redefinir Senha - Painel Local</title>
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
        .error {
            color: red;
            padding: 10px;
            margin-bottom: 15px;
            background-color: #ffebee;
            border-radius: 4px;
        }
        .success {
            color: green;
            padding: 10px;
            margin-bottom: 15px;
            background-color: #e8f5e9;
            border-radius: 4px;
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
        .help-text {
            color: #666;
            margin-top: 5px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="form-container">
        <h1>Redefinir Senha</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <p class="help-text">Uma nova senha de 4 dígitos será gerada automaticamente.</p>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Gerar Nova Senha</button>
                <a href="users.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>
