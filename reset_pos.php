<?php
include 'auth.php';
include 'conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = null;
$message = null;

// Verificar se o usuário existe e tem role_id = 6
$stmt = $pdo->prepare("
    SELECT id, email, name, l_name, pos_id 
    FROM users 
    WHERE id = ? AND role_id = 6
");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: users.php");
    exit;
}

// Obter informações do ponto de venda atual
$pos_name = '';
if ($user['pos_id']) {
    $stmtPos = $pdo->prepare("SELECT name FROM pos WHERE id = ?");
    $stmtPos->execute([$user['pos_id']]);
    $pos = $stmtPos->fetch();
    if ($pos) {
        $pos_name = $pos['name'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Atualizar para remover a associação com o ponto de venda
        $stmt = $pdo->prepare("
            UPDATE users 
            SET pos_id = NULL, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$id]);
        
        $pdo->commit();
        $message = "Ponto de venda resetado com sucesso para o usuário " . htmlspecialchars($user['name'] . ' ' . $user['l_name']) . "!";
        
        // Redirecionar após 2 segundos
        header("Refresh: 2; URL=users.php");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro ao resetar ponto de venda: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Resetar Ponto de Venda - Painel Local</title>
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
            background-color: #f1f1f1;
            color: black;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="form-container">
        <h1>Resetar Ponto de Venda</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php else: ?>
            <p>Você está prestes a remover a associação do usuário <strong><?php echo htmlspecialchars($user['name'] . ' ' . $user['l_name']); ?></strong> com o ponto de venda atual<?php echo $pos_name ? ' <strong>' . htmlspecialchars($pos_name) . '</strong>' : ''; ?>.</p>
            
            <?php if (!$user['pos_id']): ?>
                <div class="error">Este usuário não está associado a nenhum ponto de venda atualmente.</div>
                <a href="users.php" class="btn btn-secondary">Voltar para Lista de Usuários</a>
            <?php else: ?>
                <p>Após esta operação, o usuário não estará mais associado a nenhum ponto de venda e precisará ser associado novamente para poder acessar o sistema.</p>
                
                <form method="post">
                    <div class="form-group">
                        <p>Tem certeza que deseja continuar?</p>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Sim, Resetar Ponto de Venda</button>
                        <a href="users.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
