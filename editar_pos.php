<?php
include 'auth.php';
include 'conexao.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID do PDV não fornecido']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $name = $_POST['name'] ?? '';
    $permissions = $_POST['permissions'] ?? [];
    $payment_methods = $_POST['payment_methods'] ?? [];

    // Transform arrays to JSON strings for DB
    $permissions_json = json_encode($permissions);
    $payment_methods_json = json_encode($payment_methods);

    try {
        $stmt = $pdo->prepare("
            UPDATE pos 
            SET name = ?,
                permission = ?,
                payment_methods = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$name, $permissions_json, $payment_methods_json, $id]);

        echo json_encode([
            'success' => true,
            'message' => 'PDV atualizado com sucesso',
            'pos' => [
                'id' => $id,
                'name' => $name,
                'permissions' => $permissions,
                'payment_methods' => $payment_methods
            ]
        ]);
        exit;
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao atualizar PDV: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Get PDV data
$stmt = $pdo->prepare("SELECT * FROM pos WHERE id = ?");
$stmt->execute([$id]);
$pos = $stmt->fetch();

if (!$pos) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'PDV não encontrado']);
    exit;
}

// If it's not a POST request and not expecting JSON, return the form HTML
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    // Prepare permissions and payment_methods for form
    $permissions = json_decode($pos['permission'] ?? '{}', true);
    $payment_methods = json_decode($pos['payment_methods'] ?? '{}', true);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Editar PDV</title>
    <style>
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
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
            resize: vertical;
        }
        .buttons {
            text-align: right;
            margin-top: 20px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <form id="editPosForm">
        <div class="form-group">
            <label for="name">Nome do PDV</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($pos['name']); ?>" required>
        </div>

        <div class="form-group">
            <label>Permissões</label>
            <label><input type="checkbox" name="permissions[tickets]" value="1" <?php echo !empty($permissions['tickets']) ? 'checked' : ''; ?>> Tickets</label>
            <label><input type="checkbox" name="permissions[extras]" value="1" <?php echo !empty($permissions['extras']) ? 'checked' : ''; ?>> Extras</label>
            <label><input type="checkbox" name="permissions[scan]" value="1" <?php echo !empty($permissions['scan']) ? 'checked' : ''; ?>> Scan</label>
            <label><input type="checkbox" name="permissions[report]" value="1" <?php echo !empty($permissions['report']) ? 'checked' : ''; ?>> Relatório</label>
        </div>

        <div class="form-group">
            <label>Métodos de Pagamento</label>
            <label><input type="checkbox" name="payment_methods[card]" value="1" <?php echo !empty($payment_methods['card']) ? 'checked' : ''; ?>> Cartão</label>
            <label><input type="checkbox" name="payment_methods[qr]" value="1" <?php echo !empty($payment_methods['qr']) ? 'checked' : ''; ?>> QR Code</label>
            <label><input type="checkbox" name="payment_methods[cash]" value="1" <?php echo !empty($payment_methods['cash']) ? 'checked' : ''; ?>> Dinheiro</label>
        </div>

        <div class="buttons">
            <button type="button" class="btn btn-secondary" onclick="window.parent.closeEditPosDialog()">Cancelar</button>
            <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
    </form>

    <script>
        document.getElementById('editPosForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('id', <?php echo $id; ?>);

            fetch('editar_pos.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.parent.onPosUpdated(data.pos);
                    window.parent.closeEditPosDialog();
                } else {
                    alert(data.message || 'Erro ao atualizar PDV');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erro ao atualizar PDV');
            });
        });
    </script>
</body>
</html>
<?php
}
?>
