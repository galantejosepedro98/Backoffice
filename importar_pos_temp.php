<?php
include 'conexao.php';

$error = null;
$message = null;
$return_to = isset($_GET['return_to']) ? $_GET['return_to'] : 'pos.php';
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    try {
        $file = $_FILES['file'];
        
        // Verificar se é um arquivo Excel
        $fileType = pathinfo($file['name'], PATHINFO_EXTENSION);
        if ($fileType != 'xlsx' && $fileType != 'xls') {
            throw new Exception('Por favor, envie um arquivo Excel (.xlsx ou .xls)');
        }

        // Configurações padrão para todos os PDVs
        $permission = '{"tickets":"0","extras":"1","scan":"0","report":"1"}';
        $payment_methods = '{"card":"1","qr":"1","cash":"0"}';
        $can_print = 1;

        require 'vendor/autoload.php';

        // Carregar o arquivo Excel
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Começar transação
        $pdo->beginTransaction();
        
        $created_pos = 0;
        $created_users = 0;

        $stmt = $pdo->prepare("
            INSERT INTO pos (name, permission, payment_methods, can_print, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");

        // Preparar statement para criação de usuários somente se o checkbox estiver marcado
        $stmtUser = null;
        $stmtPassword = null;
        if (isset($_POST['create_users']) && $_POST['create_users'] == '1') {
            $stmtUser = $pdo->prepare("
                INSERT INTO users (role_id, name, l_name, email, password, pos_id, created_at, updated_at)
                VALUES (6, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmtPassword = $pdo->prepare("
                INSERT INTO staff_passwords (user_id, clear_password, created_at)
                VALUES (?, ?, NOW())
            ");
        }

        // Se temos um event_id, preparar statement para associação
        $stmtEventPos = null;
        if ($event_id) {
            $stmtEventPos = $pdo->prepare("
                INSERT INTO event_pos (event_id, pos_id, status, created_at)
                VALUES (?, ?, 'active', NOW())
            ");
        }

        // Pular a primeira linha se for cabeçalho
        $firstRow = true;
        $highestRow = $worksheet->getHighestRow();
        
        for ($row = 1; $row <= $highestRow; $row++) {
            if ($firstRow) {
                $firstRow = false;
                continue;
            }

            $pos_name = trim($worksheet->getCell('A' . $row)->getValue());
            
            // Se não tem nome do PDV, pular linha
            if (empty($pos_name)) continue;

            // Criar PDV
            $stmt->execute([
                $pos_name,
                $permission,
                $payment_methods,
                $can_print
            ]);
            $pos_id = $pdo->lastInsertId();
            $created_pos++;

            // Se temos um event_id, associar o PDV ao evento
            if ($event_id && $stmtEventPos) {
                $stmtEventPos->execute([$event_id, $pos_id]);
            }

            // Criar usuário automaticamente apenas se o checkbox estiver marcado
            if (isset($_POST['create_users']) && $_POST['create_users'] == '1' && $stmtUser && $stmtPassword) {
                // Preparar statement para usuários
                $stmtUser = $pdo->prepare("
                    INSERT INTO users (role_id, name, l_name, email, password, pos_id, uniqid, created_at, updated_at)
                    VALUES (6, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");

                // Separar palavras do nome do PDV
                $words = preg_split('/\s+/', $pos_name);
                
                // Definir nome e sobrenome
                if (count($words) == 1) {
                    $user_name = $words[0];
                    $l_name = null;
                } else {
                    $l_name = array_pop($words); // Última palavra
                    $user_name = implode(' ', $words); // Todas as palavras exceto a última
                }

                // Função para remover acentos e caracteres especiais
                function cleanString($str) {
                    // Remove acentos
                    $str = preg_replace('/[áàãâä]/ui', 'a', $str);
                    $str = preg_replace('/[éèêë]/ui', 'e', $str);
                    $str = preg_replace('/[íìîï]/ui', 'i', $str);
                    $str = preg_replace('/[óòõôö]/ui', 'o', $str);
                    $str = preg_replace('/[úùûü]/ui', 'u', $str);
                    $str = preg_replace('/[ýÿ]/ui', 'y', $str);
                    $str = str_replace('ç', 'c', $str);
                    $str = str_replace('ñ', 'n', $str);
                    
                    // Remove qualquer caractere que não seja letra ou número
                    $str = preg_replace('/[^a-z0-9]/i', '', $str);
                    
                    return strtolower($str);
                }

                // Gerar email
                if (count($words) <= 1) {
                    $email = cleanString($pos_name) . "@essencia.com";
                } else {
                    $first_word = cleanString($words[0]);
                    $last_word = cleanString($l_name);
                    $email = $first_word . "." . $last_word . "@essencia.com";
                }

                if (!empty($user_name)) {
                    // Gerar senha de 4 dígitos
                    $generated_password = sprintf("%04d", rand(0, 9999));
                    $hashed_password = password_hash($generated_password, PASSWORD_DEFAULT);
                    
                    // Gerar UUID único
                    $uniqid = uniqid(rand(), true);

                    // Criar usuário
                    $stmtUser->execute([
                        $user_name,
                        $l_name,
                        $email,
                        $hashed_password,
                        $pos_id,
                        $uniqid
                    ]);
                    $user_id = $pdo->lastInsertId();

                    // Salvar senha em texto claro
                    $stmtPassword->execute([
                        $user_id,
                        $generated_password
                    ]);

                    $created_users++;
                }
            }
        }

        $pdo->commit();

        $message = "Importação concluída com sucesso! $created_pos pontos de venda foram criados.";
        $message .= " $created_users usuários foram criados.";
        
        if ($event_id) {
            $message .= " Todos PDVs foram associados ao evento.";
            // Redirecionar após 2 segundos para a página de gestão do evento
            header("Refresh: 2; URL=event_management.php?id=" . $event_id);
        } else {
            // Se não veio de um evento, redirecionar para a lista de PDVs
            header("Refresh: 2; URL=pos.php");
        }
        
    } catch (Exception $e) {
        if (isset($pdo)) $pdo->rollBack();
        $error = "Erro na importação: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Importar Pontos de Venda</title>
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
        .instructions {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
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
        .sample {
            background-color: #fff;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="form-container">
        <h1>Importar Pontos de Venda</h1>

        <div style="margin-bottom: 20px;">
            <a href="template importar.xlsx" download class="btn btn-secondary">
                <i class="fas fa-download"></i> Download Template Excel
            </a>
        </div>
    
        <div class="instructions">
            <h3>Instruções:</h3>
            <ol>
                <li><strong>Formato do arquivo Excel:</strong>
                    <ul>
                        <li>O arquivo deve ter apenas UMA coluna: Nome do PDV</li>
                        <li>A primeira linha será ignorada (pode ser usada como cabeçalho)</li>
                        <li>Cada linha abaixo deve conter apenas o nome do PDV</li>
                    </ul>
                </li>
                <li>Se você marcar a opção "Criar usuário automaticamente para cada PDV":
                    <ul>
                        <li>Se o PDV tiver apenas uma palavra (ex: "Bar1"), será usado como nome do usuário</li>
                        <li>Se tiver múltiplas palavras (ex: "Bar Central Lisboa"), "Bar Central" será o nome e "Lisboa" o sobrenome</li>                        <li>O email será gerado automaticamente no formato: primeironome.ultimonome@essencia.com</li>
                        <li>Exemplo: Para "Bar Central Lisboa" → bar.lisboa@essencia.com</li>
                        <li>Para PDV com uma palavra: Para "Bar1" → bar1@essencia.com</li>
                        <li>Obs: Acentos e caracteres especiais serão removidos do email</li>
                    </ul>
                </li>
                <li>Cada PDV será criado com as seguintes configurações padrão:
                    <ul>
                        <li>Permissões: Extras e Report</li>
                        <li>Métodos de pagamento: Cartão (✓) | QR Code (✓) | Dinheiro (✗)</li>
                        <li>Impressão habilitada</li>
                    </ul>
                </li>
                <li>Se optar por criar usuários automaticamente:
                    <ul>
                        <li>Um usuário será criado com role_id = 6 (Staff)</li>
                        <li>Uma senha de 4 dígitos será gerada automaticamente</li>
                        <li>O usuário será automaticamente associado ao PDV</li>
                    </ul>
                </li>
            </ol>
            <div class="sample">
                <strong>Exemplo do arquivo Excel:</strong><br>
                <table style="border-collapse: collapse; width: 200px; margin: 10px 0;">
                    <tr style="background-color: #f0f0f0;">
                        <th style="border: 1px solid #ddd; padding: 8px;">Nome do PDV</th>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 8px;">Bar Central Lisboa</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 8px;">Bar1</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 8px;">Restaurante Principal Centro</td>
                    </tr>
                </table>
                <small>* A primeira linha (cabeçalho) será ignorada</small>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">            <div class="form-group">
                <label for="file">Selecione o arquivo:</label>
                <input type="file" id="file" name="file" accept=".xlsx,.xls" required>
            </div>

            <div class="form-group">
                <input type="checkbox" id="create_users" name="create_users" value="1">
                <label for="create_users">Criar usuário automaticamente para cada PDV</label>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Importar</button>
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
