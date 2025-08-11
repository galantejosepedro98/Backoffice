<?php
include 'auth.php';
include 'conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$error = null;
$card = [
    'phone_number' => '',
    'activation_number' => '',
    'pin' => '',
    'puk' => '',
    'provider' => '',
    'status' => 'active',
    'notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $card = [
            'phone_number' => $_POST['phone_number'],
            'activation_number' => $_POST['activation_number'],
            'pin' => $_POST['pin'],
            'puk' => $_POST['puk'],
            'provider' => $_POST['provider'],
            'status' => $_POST['status'],
            'notes' => $_POST['notes']
        ];

        if ($id) {
            $stmt = $pdo->prepare("
                UPDATE internet_cards                SET phone_number = ?, activation_number = ?, pin = ?, puk = ?, provider = ?, status = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $card['phone_number'],
                $card['activation_number'],
                $card['pin'],
                $card['puk'],
                $card['provider'],
                $card['status'],
                $card['notes'],
                $id
            ]);        } else {            $stmt = $pdo->prepare("                INSERT INTO internet_cards (phone_number, activation_number, pin, puk, provider, status, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $card['phone_number'],
                $card['activation_number'],
                $card['pin'],
                $card['puk'],
                $card['provider'],
                $card['status'],
                $card['notes']
            ]);
        }
          // Verificar se há parâmetros para retornar à página de gestão de eventos
        if (isset($_GET['event_id']) && isset($_GET['pos_id'])) {
            $redirect_url = "event_management.php?id=" . (int)$_GET['event_id'] . "&pos_id=" . (int)$_GET['pos_id'];
            
            // Manter os filtros
            if (isset($_GET['category'])) {
                $redirect_url .= "&category=" . urlencode($_GET['category']);
            }
            if (isset($_GET['search'])) {
                $redirect_url .= "&search=" . urlencode($_GET['search']);
            }
            
            header("Location: " . $redirect_url);        } else if (isset($_GET['return_url'])) {
            // Se tiver um URL de retorno específico
            $return_url = urldecode($_GET['return_url']);
            
            // Garantir que estamos retornando para a página de internet_cards.php
            if (strpos($return_url, 'internet_cards.php') !== false) {
                // O return_url já contém o fragmento para scroll (#card-id)
                header("Location: " . $return_url);
            } else {
                // Se não tiver o internet_cards.php no URL, redirecionar para a página principal
                header("Location: internet_cards.php" . (isset($id) ? '#card-' . $id : ''));
            }
        } else {
            // Verificar se há parâmetros para filtros na página de cartões
            $redirect_url = "internet_cards.php";
            
            // Aqui poderia adicionar outros parâmetros de filtro específicos da página internet_cards.php
            
            header("Location: " . $redirect_url);
        }
        exit;
    } catch (PDOException $e) {
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM internet_cards WHERE id = ?");
    $stmt->execute([$id]);
    $card = $stmt->fetch();
    if (!$card) {
        header("Location: internet_cards.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $id ? 'Editar' : 'Novo'; ?> Cartão de Internet</title>
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
        .error {
            color: red;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <h1><?php echo $id ? 'Editar' : 'Novo'; ?> Cartão de Internet</h1>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>    <form method="POST">

        <div class="form-group">
            <label for="phone_number">Número do Cartão</label>
            <input type="text" id="phone_number" name="phone_number" 
                   value="<?php echo htmlspecialchars($card['phone_number']); ?>"
                   placeholder="Número de telefone (opcional)">
        </div>

        <div class="form-group">
            <label for="activation_number">Número de Ativação</label>
            <input type="text" id="activation_number" name="activation_number" 
                   value="<?php echo htmlspecialchars($card['activation_number']); ?>" required>
        </div>

        <div class="form-group">
            <label for="pin">PIN</label>
            <input type="text" id="pin" name="pin" 
                   value="<?php echo htmlspecialchars($card['pin']); ?>"
                   placeholder="PIN do cartão (opcional)"
                   maxlength="8">
        </div>

        <div class="form-group">
            <label for="puk">PUK</label>
            <input type="text" id="puk" name="puk" 
                   value="<?php echo htmlspecialchars($card['puk']); ?>"
                   placeholder="PUK do cartão (opcional)"
                   maxlength="12">
        </div>

        <div class="form-group">
            <label for="provider">Operadora</label>
            <select id="provider" name="provider" required>
                <option value="">Selecione uma operadora</option>
                <?php
                $providers = ['MEO', 'VODAFONE', 'NOS', 'UZO', 'NOWO'];
                foreach ($providers as $provider) {
                    $selected = $card['provider'] === $provider ? 'selected' : '';
                    echo "<option value=\"$provider\" $selected>$provider</option>";
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" required>
                <option value="active" <?php echo $card['status'] === 'active' ? 'selected' : ''; ?>>Ativo</option>
                <option value="inactive" <?php echo $card['status'] === 'inactive' ? 'selected' : ''; ?>>Inativo</option>
                <option value="expired" <?php echo $card['status'] === 'expired' ? 'selected' : ''; ?>>Expirado</option>
            </select>
        </div>

        <div class="form-group">
            <label for="notes">Observações</label>
            <textarea id="notes" name="notes"><?php echo htmlspecialchars($card['notes']); ?></textarea>
        </div>        <div>
            <button type="submit" class="btn btn-primary">Salvar</button>
            <?php if (isset($_GET['event_id']) && isset($_GET['pos_id'])): ?>
                <?php
                $cancel_url = "event_management.php?id=" . (int)$_GET['event_id'] . "&pos_id=" . (int)$_GET['pos_id'];
                
                // Manter os filtros
                if (isset($_GET['category'])) {
                    $cancel_url .= "&category=" . urlencode($_GET['category']);
                }
                if (isset($_GET['search'])) {
                    $cancel_url .= "&search=" . urlencode($_GET['search']);
                }
                ?>
                <a href="<?php echo $cancel_url; ?>" class="btn btn-secondary">Cancelar</a>            <?php elseif (isset($_GET['return_url'])): ?>
                <?php                $return_url = urldecode($_GET['return_url']);
                // Garantir que estamos retornando para a página de internet_cards.php
                if (strpos($return_url, 'internet_cards.php') === false) {
                    $return_url = "internet_cards.php" . ($id ? "#card-" . $id : "");
                }
                ?>
                <a href="<?php echo htmlspecialchars($return_url); ?>" class="btn btn-secondary">Cancelar</a>
            <?php else: ?>
                <a href="internet_cards.php<?php echo $id ? '#card-' . $id : ''; ?>" class="btn btn-secondary">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</body>
</html>
