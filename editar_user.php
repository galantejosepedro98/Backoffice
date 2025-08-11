<?php
include 'auth.php';
include 'conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = null;
$message = null;

// Verificar se o usuário existe
$stmt = $pdo->prepare("
    SELECT id, name, l_name, email, role_id, pos_id 
    FROM users 
    WHERE id = ?
");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: users.php");
    exit;
}

// Obter todos os pontos de venda para o select
$stmt_pos = $pdo->query("SELECT id, name FROM pos ORDER BY name");
$pontos_venda = $stmt_pos->fetchAll();

// Obter todas as roles para o select
$stmt_roles = $pdo->query("SELECT id, name FROM roles ORDER BY id");
$roles = $stmt_roles->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $l_name = trim($_POST['l_name']);
    $email = trim($_POST['email']);
    $role_id = (int)$_POST['role_id'];
    $pos_id = !empty($_POST['pos_id']) ? (int)$_POST['pos_id'] : null;

    $errors = [];

    // Validações
    if (empty($name)) {
        $errors[] = "Nome é obrigatório";
    }
    if (empty($email)) {
        $errors[] = "Email é obrigatório";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email inválido";
    }

    // Verificar se email já existe (exceto para o próprio usuário)
    $stmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE email = ? AND id != ?
    ");
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
        $errors[] = "Email já está em uso por outro usuário";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Atualizar usuário
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = ?, l_name = ?, email = ?, role_id = ?, pos_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$name, $l_name, $email, $role_id, $pos_id, $id]);
            
            $pdo->commit();
            $message = "Usuário atualizado com sucesso!";
            
            // Redirecionar após 2 segundos
            header("Refresh: 2; URL=users.php");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erro ao atualizar usuário: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Editar Usuário - Painel Local</title>
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
        input[type="email"],
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="form-container">
        <h1>Editar Usuário</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="name">Nome:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="l_name">Sobrenome:</label>
                <input type="text" id="l_name" name="l_name" value="<?php echo htmlspecialchars($user['l_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="role_id">Função:</label>
                <select id="role_id" name="role_id" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>" <?php echo $user['role_id'] == $role['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="pos_id">Ponto de Venda:</label>
                <select id="pos_id" name="pos_id">
                    <option value="">-- Nenhum --</option>
                    <?php foreach ($pontos_venda as $pos): ?>
                        <option value="<?php echo $pos['id']; ?>" <?php echo $user['pos_id'] == $pos['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pos['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="users.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>
