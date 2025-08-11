<?php
include 'auth.php';
include 'conexao.php';

$error = null;
$success = null;
$duplicates = [];

// Buscar tipos de equipamento para o dropdown
$stmtTypes = $pdo->query("SELECT id, name FROM equipment_types ORDER BY name");
$types = $stmtTypes->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $type_id = $_POST['type_id'];
        $start_number = (int)$_POST['start_number'];
        $quantity = (int)$_POST['quantity'];
        
        // Começar transação
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO equipment (serial_number, type_id, created_at)
            VALUES (?, ?, NOW())
        ");
        
        $created_count = 0;
        for ($i = 0; $i < $quantity; $i++) {
            $number = $start_number + $i;
            $serial_number = (string)$number;
              // Verificar se já existe um equipamento com este número de série e tipo
            $check = $pdo->prepare("SELECT COUNT(*) FROM equipment WHERE serial_number = ? AND type_id = ?");
            $check->execute([$serial_number, $type_id]);
            if ($check->fetchColumn() == 0) {
                $stmt->execute([$serial_number, $type_id]);
                $created_count++;
            } else {
                $duplicates[] = $serial_number;
            }
        }
          $pdo->commit();        $success = "Foram criados $created_count equipamentos com sucesso!";
        if (!empty($duplicates)) {
            $success .= "<br>Nota: Os seguintes números já existiam para este tipo de equipamento: " . implode(", ", $duplicates);
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erro ao criar equipamentos: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Criar Equipamentos em Massa</title>
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
        input[type="number"],
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .preview {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .success {
            color: green;
            margin-bottom: 15px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-secondary {
            background-color: #666;
            color: white;
        }
        #preview-list {
            max-height: 200px;
            overflow-y: auto;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <h1>Criar Equipamentos em Massa</h1>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post" id="bulk-form">
        <div class="form-group">
            <label for="type_id">Tipo de Equipamento</label>
            <select id="type_id" name="type_id" required>
                <option value="">Selecione...</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?php echo $type['id']; ?>">
                        <?php echo htmlspecialchars($type['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="prefix">Prefixo do Número de Série</label>
            <input type="text" id="prefix" name="prefix" placeholder="Ex: EQ-">
        </div>

        <div class="form-group">
            <label for="start_number">Número Inicial</label>
            <input type="number" id="start_number" name="start_number" value="1" min="0" required>
        </div>

        <div class="form-group">
            <label for="pad_length">Quantidade de Dígitos (com zeros à esquerda)</label>
            <input type="number" id="pad_length" name="pad_length" value="2" min="1" max="10" required>
        </div>

        <div class="form-group">
            <label for="suffix">Sufixo do Número de Série</label>
            <input type="text" id="suffix" name="suffix">
        </div>

        <div class="form-group">
            <label for="quantity">Quantidade de Equipamentos</label>
            <input type="number" id="quantity" name="quantity" value="10" min="1" max="100" required>
        </div>

        <div class="preview">
            <h3>Prévia dos Números de Série:</h3>
            <div id="preview-list"></div>
        </div>

        <button type="submit" class="btn btn-primary">Criar Equipamentos</button>
        <a href="equipment.php" class="btn btn-secondary">Cancelar</a>
    </form>

    <script>
    function updatePreview() {
        const prefix = document.getElementById('prefix').value;
        const suffix = document.getElementById('suffix').value;
        const startNumber = parseInt(document.getElementById('start_number').value) || 0;
        const padLength = parseInt(document.getElementById('pad_length').value) || 2;
        const quantity = parseInt(document.getElementById('quantity').value) || 0;
        
        const previewList = document.getElementById('preview-list');
        let preview = '';
        
        for (let i = 0; i < Math.min(quantity, 100); i++) {
            const number = startNumber + i;
            const paddedNumber = number.toString().padStart(padLength, '0');
            preview += prefix + paddedNumber + suffix + '\n';
        }
        
        previewList.textContent = preview;
    }

    // Atualizar prévia quando qualquer campo mudar
    document.getElementById('prefix').addEventListener('input', updatePreview);
    document.getElementById('suffix').addEventListener('input', updatePreview);
    document.getElementById('start_number').addEventListener('input', updatePreview);
    document.getElementById('pad_length').addEventListener('input', updatePreview);
    document.getElementById('quantity').addEventListener('input', updatePreview);

    // Atualizar prévia inicial
    updatePreview();
    </script>
</body>
</html>
