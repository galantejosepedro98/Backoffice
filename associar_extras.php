<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'auth.php';
include 'conexao.php';

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

if (!$event_id) {
    die('Erro: ID do evento é obrigatório.');
}

// Buscar informações do evento
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    die('Erro: Evento não encontrado.');
}

$message = '';
$error = '';

// Buscar todas as categorias de extras do evento
$stmt = $pdo->prepare("SELECT * FROM extra_categories WHERE event_id = ? ORDER BY `order`");
$stmt->execute([$event_id]);
$extra_categories = $stmt->fetchAll();

// Buscar categorias de PDVs usando a relação com pos_categories_assignments
$stmt = $pdo->prepare("
    SELECT DISTINCT pc.* 
    FROM pos_categories pc
    JOIN pos_categories_assignments pca ON pca.category_id = pc.id
    JOIN pos p ON p.id = pca.pos_id
    JOIN event_pos ep ON ep.pos_id = p.id
    WHERE ep.event_id = ?
");
$stmt->execute([$event_id]);
$pos_categories = $stmt->fetchAll();

// Função para criar planilha no Google Sheets
function createGoogleSheet($event_id, $extras, $pdvs) {
    $url = 'https://script.google.com/macros/s/AKfycbwbzDgWjfxVafANKrRCJTOAO044pSONewI9WnRYxgxOIEMcwQtDX5qZTq0hRymn8rsJIQ/exec';
    
    $data = [
        'event_id' => $event_id,
        'extras' => $extras,
        'pdvs' => $pdvs
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        throw new Exception('Erro ao criar planilha no Google Sheets');
    }
    
    return json_decode($result, true);
}

// Se foi solicitado download do Excel
if (isset($_GET['download']) && isset($_GET['extra_categories']) && isset($_GET['pos_category'])) {
    $extra_category_ids = $_GET['extra_categories'];
    $pos_category_id = (int)$_GET['pos_category'];
    
    // Buscar extras das categorias selecionadas
    $placeholders = str_repeat('?,', count($extra_category_ids) - 1) . '?';
    $params = array_merge($extra_category_ids, [$event_id]);
    
    $stmt = $pdo->prepare("
        SELECT e.*, ec.name as category_name 
        FROM extras e 
        JOIN extra_categories ec ON ec.id = e.extra_category_id
        WHERE e.extra_category_id IN ($placeholders) AND e.event_id = ?
        ORDER BY e.extra_category_id, e.name
    ");
    $stmt->execute($params);
    $extras = $stmt->fetchAll();
    
    if (empty($extras)) {
        $error = "Não há extras nas categorias selecionadas.";
    } else {
        // Buscar PDVs da categoria selecionada
        $stmt = $pdo->prepare("
            SELECT p.* 
            FROM pos p 
            JOIN pos_categories_assignments pca ON pca.pos_id = p.id
            JOIN event_pos ep ON ep.pos_id = p.id 
            WHERE pca.category_id = ? AND ep.event_id = ?
            ORDER BY p.name
        ");
        $stmt->execute([$pos_category_id, $event_id]);
        $pos_list = $stmt->fetchAll();
        
        // Preparar dados para o Google Sheets
        $extras_data = [];
        foreach ($extras as $extra) {
            // Verificar se o extra é válido
            if (!isset($extra['id'])) {
                continue;
            }
            
            // Buscar PDVs já associados a este extra
            $stmt = $pdo->prepare("
                SELECT p.id, p.name 
                FROM pos p 
                JOIN extra_pos ep ON ep.pos_id = p.id 
                WHERE ep.extra_id = ?
                ORDER BY p.name
            ");
            $stmt->execute([$extra['id']]);
            $associated_pdvs = $stmt->fetchAll();
            
            $pdv_str = implode(', ', array_map(function($pdv) {
                return $pdv['id'] . ' - ' . $pdv['name'];
            }, $associated_pdvs));
            
            $extras_data[] = [
                'id' => $extra['id'],
                'name' => $extra['name'],
                'price' => $extra['price'],
                'associated_pdvs' => $pdv_str
            ];
        }
        
        if (empty($extras_data)) {
            $error = "Não há dados para exportar na planilha.";
        } else {
            // Preparar lista de PDVs para o dropdown
            $pdv_options = array_map(function($pos) {
                return $pos['id'] . ' - ' . $pos['name'];
            }, $pos_list);
            
            try {
                // Criar planilha no Google Sheets
                $result = createGoogleSheet($event_id, $extras_data, $pdv_options);
                
                // Redirecionar para a URL da planilha
                header('Location: ' . $result['url']);
                exit;
            } catch (Exception $e) {
                $error = "Erro ao criar planilha: " . $e->getMessage();
            }
        }
    }
}

// Processar o arquivo enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if ($file_ext === 'xlsx' || $file_ext === 'xls') {
        require 'vendor/autoload.php';
        
        try {
            if ($file_ext === 'xlsx') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            } else {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
            }
            
            $spreadsheet = $reader->load($file_tmp);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Iniciar transação
            $pdo->beginTransaction();
            
            $total_associated = 0;
            $row_count = 0;
            $errors = [];
            
            // Percorrer as linhas do Excel (começando da linha 2, assumindo que a linha 1 é o cabeçalho)
            $processed_extras = []; // Para evitar duplicatas
            $real_row_count = 0;
            $extras_sem_pdvs = 0;  // Contador de extras sem PDVs
            
            foreach ($worksheet->getRowIterator(2) as $row) {
                $row_count++;
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                $row_data = [];
                foreach ($cellIterator as $cell) {
                    $row_data[] = $cell->getValue();
                }
                
                // Verificar se a linha tem dados - coluna A (ID do extra) e E (PDVs associados)
                // Índices no array: A=0, B=1, C=2, D=3, E=4
                if (empty($row_data[0]) || !isset($row_data[4])) {
                    continue;
                }
                
                $extra_id = (int)$row_data[0];
                $pos_ids_str = trim($row_data[4]); // Coluna E (índice 4) - PDVs associados
                
                if ($extra_id <= 0) {
                    continue;
                }
                
                // Pular extras sem PDVs associados
                if (empty($pos_ids_str)) {
                    error_log("Extra ID $extra_id: Coluna de PDVs vazia, pulando.");
                    $extras_sem_pdvs++;
                    continue;
                }
                
                // Registrar para debug
                error_log("Processando extra ID $extra_id com PDVs: " . $pos_ids_str);
                
                // Evitar processar o mesmo extra múltiplas vezes (linhas duplicadas)
                if (in_array($extra_id, $processed_extras)) {
                    continue;
                }
                
                // Verificar se o extra existe
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM extras WHERE id = ? AND event_id = ?");
                $check_stmt->execute([$extra_id, $event_id]);
                if ($check_stmt->fetchColumn() == 0) {
                    $errors[] = "O extra com ID $extra_id não existe neste evento.";
                    continue;
                }
                
                // Marcar este extra como processado
                $processed_extras[] = $extra_id;
                $real_row_count++;
                
                // Limpar associações existentes
                $stmt = $pdo->prepare("DELETE FROM extra_pos WHERE extra_id = ?");
                $stmt->execute([$extra_id]);
                
                // Formato esperado: "46 - Adega de Monção, 50 - Barcos Wines, ..."
                // Extrair apenas os IDs dos pontos de venda
                // Nova regex: procura por números no início de cada item separado por vírgula, antes de um traço
                $pos_items = explode(',', $pos_ids_str);
                $pos_ids = [];
                
                foreach ($pos_items as $item) {
                    // Para cada item, extrair o número antes do primeiro traço
                    if (preg_match('/^\s*(\d+)\s*-/', trim($item), $item_match)) {
                        $pos_ids[] = $item_match[1];
                        error_log("Extra $extra_id: Encontrado PDV ID " . $item_match[1] . " de: " . trim($item));
                    } else {
                        error_log("Extra $extra_id: Não foi possível extrair ID do PDV de: " . trim($item));
                    }
                }
                
                // Criar novas associações
                foreach ($pos_ids as $pos_id) {
                    $pos_id = (int)$pos_id; // Garantir que é um número
                    if ($pos_id > 0) {
                        // Verificar se o PDV pertence ao evento
                        $check_pos_stmt = $pdo->prepare("
                            SELECT COUNT(*) FROM event_pos WHERE pos_id = ? AND event_id = ?
                        ");
                        $check_pos_stmt->execute([$pos_id, $event_id]);
                        
                        if ($check_pos_stmt->fetchColumn() > 0) {
                            // PDV pertence ao evento, pode associar
                            $stmt = $pdo->prepare("
                                INSERT INTO extra_pos (extra_id, pos_id, created_at, updated_at) 
                                VALUES (?, ?, NOW(), NOW())
                            ");
                            $stmt->execute([$extra_id, $pos_id]);
                            $total_associated++;
                        } else {
                            // PDV não pertence ao evento, registrar erro
                            $errors[] = "PDV com ID {$pos_id} não pertence ao evento atual (extra ID: {$extra_id})";
                        }
                    }
                }
            }
            
            // Commit da transação
            $pdo->commit();
            
            $message = "Associações atualizadas com sucesso! {$total_associated} associações realizadas em {$real_row_count} extras.";
            
            if ($extras_sem_pdvs > 0) {
                $message .= " ({$extras_sem_pdvs} extras sem pontos de venda foram ignorados)";
            }
            
            // Gravar log para ajudar na depuração
            error_log("Associação de extras: {$total_associated} associações para {$real_row_count} extras no evento {$event_id}. Extras sem PDVs: {$extras_sem_pdvs}");
            
            // Se houver erros, exibir também
            if (!empty($errors)) {
                $error = "Atenção: Alguns registros não foram processados:<br>" . implode("<br>", $errors);
                error_log("Erros na associação de extras: " . implode(", ", $errors));
            }
            
        } catch (Exception $e) {
            // Rollback da transação em caso de erro
            $pdo->rollBack();
            $error = "Erro ao processar o arquivo: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, envie um arquivo Excel válido (.xlsx ou .xls)";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Associar Extras - <?php echo htmlspecialchars($event['name']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        form {
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        .btn {
            display: inline-block;
            font-weight: 400;
            text-align: center;
            vertical-align: middle;
            cursor: pointer;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            transition: color 0.15s, background-color 0.15s;
            text-decoration: none;
        }
        
        .btn-primary {
            color: #fff;
            background-color: #007bff;
            border: 1px solid #007bff;
        }
        
        .btn-primary:hover {
            background-color: #0069d9;
            border-color: #0062cc;
        }
        
        .btn-secondary {
            color: #fff;
            background-color: #6c757d;
            border: 1px solid #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        
        .file-input {
            display: block;
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .instructions {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #17a2b8;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <h1>Associar Extras - <?php echo htmlspecialchars($event['name']); ?></h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="instructions">
            <h3>Instruções:</h3>
            <ol>
                <li>Selecione as categorias de extras.</li>
                <li>Selecione as categorias dos pontos de venda.</li>
                <li>Clique em "Abrir Google Sheets".</li>
                <li>No Google Sheets, edite a coluna E "Pontos de Venda Associados" mantendo o formato "ID - Nome, ID - Nome".</li>
                <li>Guarde o arquivo e faça o upload para atualizar as associações.</li>
            </ol>
            <p style="margin-top: 10px;"><strong>Importante:</strong> Para a importação, apenas as colunas A (ID) e E (PDVs associados) são utilizadas.</p>
            
            <div style="background-color: #e9ecef; padding: 10px; border-radius: 4px; margin-top: 10px;">
                <p><strong>Formato obrigatório para coluna E:</strong></p>
                <ul>
                    <li>Cada ponto de venda deve começar com o ID numérico, seguido por hífen e o nome</li>
                    <li>Múltiplos pontos de venda devem ser separados por vírgulas</li>
                    <li>O ID do ponto de venda deve estar no início de cada item</li>
                    <li>Exemplo correto: <code>46 - Adega de Monção, 50 - Barcos Wines, 65 - Quinta das Pereirinhas</code></li>
                </ul>
                <p><strong>⚠️ Atenção:</strong> O sistema captura apenas os números que aparecem antes do primeiro hífen de cada item (separados por vírgulas).</p>
            </div>
        </div>
        
        <form id="downloadForm" action="" method="GET">
            <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
            <input type="hidden" name="download" value="1">
                
            <div class="form-group">
                <label for="extra_category">Categorias de Extras:</label>
                <select name="extra_categories[]" id="extra_category" multiple required size="5">
                    <?php foreach ($extra_categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">Pressione CTRL para selecionar múltiplas categorias</small>
            </div>
            
            <div class="form-group">
                <label for="pos_category">Categoria de Pontos de Venda:</label>
                <select name="pos_category" id="pos_category" required>
                    <option value="">Selecione uma categoria</option>
                    <?php foreach ($pos_categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Abrir Google Sheets</button>
        </form>
        
        <form action="" method="POST" enctype="multipart/form-data" style="margin-top: 30px;">
            <div class="form-group">
                <label for="excel_file">Selecione o arquivo Excel atualizado:</label>
                <input type="file" name="excel_file" id="excel_file" class="file-input" accept=".xlsx, .xls" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Importar Associações</button>
            <a href="event_management.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</body>
</html>
