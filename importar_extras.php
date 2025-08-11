<?php
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

// Processar o arquivo enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    // Verificar se foi enviado um arquivo Excel
    $file = $_FILES['excel_file'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Verificar extensão do arquivo
    if ($file_ext === 'xlsx' || $file_ext === 'xls') {
        // Carregar o PhpSpreadsheet
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
            
            $total_imported = 0;
            $row_count = 0;
            $errors = [];
            
            // Percorrer as linhas do Excel (começando da linha 2, assumindo que a linha 1 é o cabeçalho)
            foreach ($worksheet->getRowIterator(2) as $row) {
                $row_count++;
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                $row_data = [];
                foreach ($cellIterator as $cell) {
                    $row_data[] = $cell->getValue();
                }                // Verificar se a linha tem dados suficientes
                if (empty($row_data[2]) || empty($row_data[3])) {
                    continue; // Pular linhas vazias ou sem nome do extra/preço
                }                  // Nova estrutura do arquivo:
                // 0: ID Stand (para match de PDV)
                // 1: ID Categoria
                // 2: Nome do Extra
                // 3: Preço
                // 4: Percentagem IVA
                // 5: ID da Zona
                // 6: Tipo (product/service)

                $pos_id = trim($row_data[0] ?? '');
                $category_id_from_excel = trim($row_data[1] ?? '');
                $name = trim($row_data[2] ?? '');
                $display_name = $name; // Mesmo nome para exibição
                $price = (float)str_replace(',', '.', $row_data[3] ?? 0);
                $iva_percentage = trim($row_data[4] ?? '');
                $zone_id = !empty($row_data[5]) ? (int)trim($row_data[5]) : null;
                $item_type = strtolower(trim($row_data[6] ?? 'product')); // Tipo: product ou service
                
                // Usar a própria percentagem para tax e tax_type (sem conversão para NOR, INT, RED, ISE)
                $tax_type = $iva_percentage;
                $tax = $iva_percentage;
                
                // Garantir que o tipo seja válido
                if ($item_type != 'product' && $item_type != 'service') {
                    $item_type = 'product'; // Valor padrão se inválido
                }
                
                $toconline_item_code = ''; // Será atualizado após inserção
                $toconline_item_id = ''; // Não disponível no arquivo
                $category_name = 'Importados'; // Categoria padrão para itens importados// Validar dados obrigatórios
                if (empty($name) || $price <= 0) {
                    $errors[] = "Linha {$row_count}: Nome ou preço inválidos";
                    continue;
                }
                
                // Verificar se a categoria existe com o ID fornecido
                $category_id = null;
                if (!empty($category_id_from_excel)) {
                    // Verificar se a categoria existe para este evento
                    $stmt = $pdo->prepare("SELECT id FROM extra_categories WHERE id = ? AND event_id = ?");
                    $stmt->execute([$category_id_from_excel, $event_id]);
                    $category = $stmt->fetch();
                    
                    if ($category) {
                        $category_id = $category['id'];
                    } else {
                        $errors[] = "Linha {$row_count}: Categoria ID {$category_id_from_excel} não encontrada neste evento";
                    }
                }
                
                // Verificar se o extra já existe com o mesmo nome neste evento
                $stmt = $pdo->prepare("SELECT id FROM extras WHERE name = ? AND event_id = ?");
                $stmt->execute([$name, $event_id]);
                $existingExtra = $stmt->fetch();
                
                $extra_id = null;
                  if ($existingExtra) {
                    // Atualizar extra existente
                    $stmt = $pdo->prepare("
                        UPDATE extras 
                        SET 
                            display_name = ?,
                            price = ?,
                            tax_type = ?,
                            tax = ?,
                            type = ?,
                            toconline_item_code = ?,
                            toconline_item_id = ?,
                            extra_category_id = ?,
                            zone_id = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $display_name,
                        $price,
                        $tax_type,
                        $tax,
                        $item_type,
                        $toconline_item_code,
                        $toconline_item_id,
                        $category_id,
                        $zone_id,
                        $existingExtra['id']
                    ]);
                    
                    $extra_id = $existingExtra['id'];
                } else {
                    // Inserir novo extra
                    $stmt = $pdo->prepare("
                        INSERT INTO extras 
                        (name, display_name, price, tax_type, tax, type, toconline_item_code, toconline_item_id, extra_category_id, event_id, zone_id, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $name,
                        $display_name,
                        $price,
                        $tax_type,
                        $tax,
                        $item_type,
                        $toconline_item_code,
                        $toconline_item_id,
                        $category_id,
                        $event_id,
                        $zone_id
                    ]);
                    
                    $extra_id = $pdo->lastInsertId();
                    
                    // Definir automaticamente o campo 'toconline_item_code' como 'EXTRA_' seguido do ID do extra criado
                    $toconlineItemCode = 'EXTRA_' . $extra_id;
                    $stmtUpdate = $pdo->prepare("UPDATE extras SET toconline_item_code = ? WHERE id = ?");
                    $stmtUpdate->execute([$toconlineItemCode, $extra_id]);

                    // --- INTEGRACAO VENDUS: criar produto/serviço ---
                    require_once __DIR__ . '/vendus/api.php';
                    $vendus = $vendus ?? new VendusAPI();
                    $tax_map = [
                        '23' => 'NOR',
                        '13' => 'INT',
                        '6'  => 'RED',
                    ];
                    $vendus_tax_id = isset($tax_map[$tax_type]) ? $tax_map[$tax_type] : 'NOR';
                    $vendus_type_id = ($item_type === 'service') ? 'S' : 'P';
                    $params = [
                        'reference'    => $toconlineItemCode,
                        'title'        => $name,
                        'gross_price'  => $price,
                        'unit_id'      => 261335896, // Uni
                        'type_id'      => $vendus_type_id,
                        'tax_id'       => $vendus_tax_id,
                        'status'       => 'on',
                    ];
                    $vendusResult = $vendus->createProduct($params);
                    $vendusData = json_decode($vendusResult['response'], true);
                    $vendus_id = isset($vendusData['id']) ? $vendusData['id'] : null;
                    // Atualizar o extra com o toconline_item_id
                    $stmtUpdate = $pdo->prepare("UPDATE extras SET toconline_item_id = ? WHERE id = ?");
                    $stmtUpdate->execute([$vendus_id, $extra_id]);
                    // --- FIM INTEGRACAO VENDUS ---
                }
                
                // Associar extra ao PDV se o ID do Stand for fornecido
                if (!empty($pos_id)) {
                    // Verificar se o PDV existe
                    $stmt = $pdo->prepare("
                        SELECT p.id 
                        FROM pos p 
                        JOIN event_pos ep ON ep.pos_id = p.id 
                        WHERE p.id = ? AND ep.event_id = ?
                    ");
                    $stmt->execute([$pos_id, $event_id]);
                    $existing_pos = $stmt->fetch();
                    
                    if ($existing_pos) {
                        // Verificar se o extra já está associado ao PDV
                        $stmt = $pdo->prepare("
                            SELECT * FROM extra_pos 
                            WHERE extra_id = ? AND pos_id = ?
                        ");
                        $stmt->execute([$extra_id, $pos_id]);
                        $existing_association = $stmt->fetch();
                        
                        if (!$existing_association) {
                            // Associar o extra ao PDV
                            $stmt = $pdo->prepare("
                                INSERT INTO extra_pos (extra_id, pos_id) 
                                VALUES (?, ?)
                            ");
                            $stmt->execute([$extra_id, $pos_id]);
                        }
                    } else {
                        $errors[] = "Linha {$row_count}: PDV ID {$pos_id} não encontrado neste evento";
                    }
                }
                
                $total_imported++;
            }
            
            // Commit da transação
            $pdo->commit();
            
            $message = "Importação concluída com sucesso! {$total_imported} extras importados.";
            
            if (count($errors) > 0) {
                $error = "Alguns registros não puderam ser importados:<br>" . implode("<br>", $errors);
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
    <title>Importar Extras - <?php echo htmlspecialchars($event['name']); ?></title>
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
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        table, th, td {
            border: 1px solid #ddd;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        tr:nth-child(even) {
            background-color: #f8f8f8;
        }
        
        .instructions {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #17a2b8;
        }
        
        .download-template {
            margin-top: 10px;
            margin-bottom: 20px;
        }
        
        .file-input {
            display: block;
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <h1>Importar Extras - <?php echo htmlspecialchars($event['name']); ?></h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>          <div class="instructions">
            <h3>Instruções:</h3>
            <ol>
                <li>Prepare seu arquivo Excel com a estrutura correta</li>
                <li>Certifique-se que as colunas necessárias estejam preenchidas: Nome do Extra, Preço e Percentagem IVA</li>
                <li>As colunas adicionais são: ID do Posto de Venda, ID da Categoria, ID da Zona e Tipo (product/service)</li>
                <li>Envie o arquivo para importação</li>
            </ol>
            <p>Campos obrigatórios: Nome do Extra (coluna C) e Preço (coluna D).</p>
            <p>Se o ID do Posto de Venda (coluna A) for fornecido, o extra será automaticamente associado ao PDV correspondente.</p>
            <p>Se o ID da Categoria (coluna B) for fornecido, o extra será associado à categoria correspondente.</p>
            <p>Se o ID da Zona (coluna F) for fornecido, o extra será associado à zona correspondente.</p>
            <p>Se o Tipo (coluna G) for especificado, pode ser "product" ou "service".</p>
            <p>O sistema verificará se o extra já existe pelo nome. Se existir, atualizará os dados.</p>
        </div>
        
        <div class="download-template">
            <a href="templates/template_importarExtras.xlsx" class="btn btn-primary" download>
                <i class="fas fa-download"></i> Ver Exemplo de Arquivo
            </a>
        </div>
        
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="excel_file">Selecione o arquivo Excel:</label>
                <input type="file" name="excel_file" id="excel_file" class="file-input" accept=".xlsx, .xls" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Importar Extras</button>
            <a href="event_management.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">Voltar</a>
        </form>          <div style="margin-top: 30px;">
            <h3>Colunas relevantes do arquivo</h3>            <table>
                <thead>
                    <tr>
                        <th>Coluna</th>
                        <th>Descrição</th>
                        <th>Exemplo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>A</td>
                        <td>ID do Posto de Venda (para associar ao PDV)</td>
                        <td>123</td>
                    </tr>
                    <tr>
                        <td>B</td>
                        <td>ID da Categoria</td>
                        <td>5</td>
                    </tr>
                    <tr>
                        <td>C</td>
                        <td>Nome do Extra</td>
                        <td>Refrigerante Lata</td>
                    </tr>
                    <tr>
                        <td>D</td>
                        <td>Preço</td>
                        <td>2.50</td>
                    </tr>
                    <tr>
                        <td>E</td>
                        <td>Percentagem IVA</td>
                        <td>23</td>
                    </tr>
                    <tr>
                        <td>F</td>
                        <td>ID da Zona</td>
                        <td>1</td>
                    </tr>
                    <tr>
                        <td>G</td>
                        <td>Tipo (product ou service)</td>
                        <td>product</td>
                    </tr>
                </tbody>
            </table>
              <h4 style="margin-top: 20px;">Observações:</h4>
            <ul>
                <li>A percentagem de IVA é usada diretamente nos campos tax e tax_type</li>
                <li>O tipo pode ser "product" ou "service" - se não for especificado, será "product" por padrão</li>
                <li>Para usar o recurso de categoria, certifique-se de que o ID da categoria já existe no sistema</li>
                <li>Para usar uma zona, certifique-se de que o ID da zona existe no sistema</li>
                <li>O campo toconline_item_code será automaticamente preenchido como "EXTRA_ID" (onde ID é o ID do extra criado)</li>
                <li>Se o ID do Posto de Venda for válido, o extra será automaticamente associado ao PDV correspondente</li>
            </ul>
        </div>
    </div>
</body>
</html>
