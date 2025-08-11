<?php
include 'auth.php';
include 'conexao.php';

$error = null;
$role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;

// Buscar todas as roles permitidas
$stmt = $pdo->query("SELECT id, name FROM roles WHERE id IN (6, 4, 8) ORDER BY id");
$roles = $stmt->fetchAll();

// Se não foi especificado um role_id ou não é válido, não definir um padrão
if (!$role_id || !in_array($role_id, array_column($roles, 'id'))) {
    $role_id = null;
}

// Gerar senha de 4 dígitos
$generated_password = sprintf("%04d", rand(0, 9999));

// Buscar todos os PDVs disponíveis
$stmt = $pdo->query("SELECT id, name FROM pos ORDER BY name");
$pdvs = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {    $name = $_POST['name'] ?? '';
    $l_name = $_POST['l_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role_id = !empty($_POST['role_id']) ? (int)$_POST['role_id'] : null;
    $pos_id = !empty($_POST['pos_id']) ? $_POST['pos_id'] : null;
    
    // Verificar se o role_id é válido
    if (!in_array($role_id, [4, 6, 8])) {
        $error = "Função de usuário inválida.";
    }

    if (empty($name) || empty($email)) {
        $error = "Os campos Nome e Email são obrigatórios.";
    } else {
        try {
            // Verificar se o email já existe
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception("Este email já está em uso.");
            }            // Criar o usuário
            $pdo->beginTransaction();

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (role_id, name, l_name, email, password, pos_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                // Hash da senha gerada
                $hashed_password = password_hash($generated_password, PASSWORD_DEFAULT);
                
                $stmt->execute([$role_id, $name, $l_name, $email, $hashed_password, $pos_id]);
                
                // Pegar o ID do usuário criado
                $user_id = $pdo->lastInsertId();
                
                // Salvar a senha em texto claro na nova tabela
                $stmt = $pdo->prepare("
                    INSERT INTO staff_passwords (user_id, clear_password, created_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$user_id, $generated_password]);

                $pdo->commit();
                $_SESSION['success_message'] = "Usuário criado com sucesso! A senha é: " . $generated_password;
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            
            // Redirecionar para a lista de usuários
            header("Location: users.php");
            exit;
        } catch (Exception $e) {
            $error = "Erro ao criar usuário: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Criar Usuário - Painel Local</title>
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
        input[type="password"],
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
        <h1>Criar Novo Usuário</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="name">Nome *</label>
                <input type="text" id="name" name="name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="l_name">Sobrenome</label>
                <input type="text" id="l_name" name="l_name" value="<?php echo isset($_POST['l_name']) ? htmlspecialchars($_POST['l_name']) : ''; ?>">
            </div>            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="role_id">Função *</label>
                <select id="role_id" name="role_id" required>
                    <option value="">Selecione uma função...</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>"
                            <?php echo (isset($_POST['role_id']) && $_POST['role_id'] == $role['id']) || $role_id == $role['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Senha</label>
                <p class="help-text">Uma senha de 4 dígitos será gerada automaticamente quando você criar o usuário.</p>
            </div>

            <div class="form-group">
                <label for="pos_id">Ponto de Venda</label>
                <select id="pos_id" name="pos_id">
                    <option value="">Selecione um PDV...</option>
                    <?php foreach ($pdvs as $pdv): ?>
                        <option value="<?php echo $pdv['id']; ?>"
                            <?php echo (isset($_POST['pos_id']) && $_POST['pos_id'] == $pdv['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pdv['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Criar Usuário</button>
                <a href="users.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>
