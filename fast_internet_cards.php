<?php
include 'auth.php';
include 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {    
    $barcode = $_POST['barcode'];
    // Pega apenas os últimos 12 dígitos do código de barras
    $activation_number = substr($barcode, -12);
    
    try {
        // Primeiro verifica se o número já existe
        $check = $pdo->prepare("SELECT id FROM internet_cards WHERE activation_number = ?");
        $check->execute([$activation_number]);
        $existing = $check->fetch();
        
        if ($existing) {
            $error = "Este cartão já foi lido anteriormente! ID do cartão: " . $existing['id'];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO internet_cards (activation_number, provider, status, created_at)
                VALUES (?, ?, 'active', NOW())
            ");            $stmt->execute([
                $activation_number,
                $_POST['provider']
            ]);
            
            $message = "Cartão adicionado com sucesso! Código de ativação: " . $activation_number;
        }
    } catch (PDOException $e) {
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ativação Rápida de Cartões</title>
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
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
        .success {
            color: green;
            padding: 10px;
            margin-bottom: 15px;
            background-color: #e8f5e9;
            border-radius: 4px;
        }
        .error {
            color: red;
            padding: 10px;
            margin-bottom: 15px;
            background-color: #ffebee;
            border-radius: 4px;
        }
        #barcode {
            font-size: 20px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .counter {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }
        .instructions {
            background-color: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <h1>Ativação Rápida de Cartões de Internet</h1>

    <div class="instructions">
        <h3>Instruções:</h3>
        <ol>
            <li>Selecione a operadora dos cartões</li>
            <li>Posicione o cursor no campo "Código de Barras"</li>
            <li>Use o leitor para escanear o código</li>            <li>O sistema automaticamente guardará apenas os últimos 12 dígitos</li>
            <li>Pressione Enter ou clique em "Adicionar" para salvar</li>
        </ol>
    </div>

    <?php if (isset($message)): ?>
        <div class="success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php
    // Conta quantos cartões foram adicionados hoje
    $stmt = $pdo->query("SELECT COUNT(*) FROM internet_cards WHERE DATE(created_at) = CURDATE()");
    $cartoesHoje = $stmt->fetchColumn();
    ?>
    <div class="counter">
        Cartões adicionados hoje: <?php echo $cartoesHoje; ?>
    </div>

    <form method="POST" id="cardForm">
        <div class="form-group">
            <label for="provider">Operadora</label>
            <select id="provider" name="provider" required>                <option value="">Selecione uma operadora</option>
                <?php
                $providers = ['MEO', 'VODAFONE', 'NOS', 'UZO', 'NOWO'];
                foreach ($providers as $provider) {
                    $selected = ($provider === 'NOS') ? 'selected' : '';
                    echo "<option value=\"$provider\" $selected>$provider</option>";
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label for="barcode">Código de Barras</label>
            <input type="text" id="barcode" name="barcode" 
                   autofocus required 
                   placeholder="Use o leitor de código de barras aqui">
        </div>
    </form>    <script>
        document.getElementById('cardForm').addEventListener('submit', function(e) {
            if (!document.getElementById('barcode').value) {
                e.preventDefault();
            }
        });

        document.getElementById('barcode').addEventListener('keypress', function(e) {
            // Verifica se a tecla pressionada é Enter
            if (e.key === 'Enter') {
                if (this.value.length > 0) {
                    document.getElementById('cardForm').submit();
                }
            }
        });

        // Auto-focus no campo de código de barras após adicionar um cartão
        if (document.querySelector('.success') || document.querySelector('.error')) {
            setTimeout(() => {
                const barcode = document.getElementById('barcode');
                barcode.value = ''; // Limpa o campo
                barcode.focus();
            }, 100);
        }
    </script>
</body>
</html>
