<?php
include 'auth.php';
include 'conexao.php';

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$pos_id = isset($_GET['pos_id']) ? (int)$_GET['pos_id'] : 0;

// Obter o event_id diretamente da URL ou do contexto da página
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Buscar todos os pontos de venda
$stmtPos = $pdo->prepare("SELECT * FROM pos ORDER BY name");
$stmtPos->execute();
$pontos_venda = $stmtPos->fetchAll();

// Verificar se o evento existe e obter seu nome
$stmtEvento = $pdo->prepare("SELECT id, name FROM events WHERE id = ?");
$stmtEvento->execute([$event_id]);
$evento = $stmtEvento->fetch();
if (!$evento) {
    die('Erro: O evento não existe ou é inválido.');
}

if ($category_id) {
    // Se tiver categoria_id, verificar se a categoria existe e pegar informações do evento
    $stmtCategoria = $pdo->prepare("
        SELECT c.*, e.name as event_name 
        FROM extra_categories c
        JOIN events e ON e.id = c.event_id
        WHERE c.id = ?
    ");
    $stmtCategoria->execute([$category_id]);
    $categoria = $stmtCategoria->fetch();

    if (!$categoria) {
        die('Categoria não encontrada');
    }

    // Verificar se o event_id da categoria é válido
    if (!isset($categoria['event_id']) || empty($categoria['event_id'])) {
        die('Erro: O event_id associado à categoria é inválido ou não existe.');
    }

    // Verificar se o event_id existe na tabela events
    $stmtEventCheck = $pdo->prepare("SELECT id FROM events WHERE id = ?");
    $stmtEventCheck->execute([$categoria['event_id']]);
    if (!$stmtEventCheck->fetch()) {
        die('Erro: O event_id associado à categoria não existe na tabela events.');
    }

    // Verificar se o extra_category_id está associado a um event_id válido
    $stmtCategoriaEvento = $pdo->prepare("SELECT e.id FROM extra_categories c JOIN events e ON e.id = c.event_id WHERE c.id = ?");
    $stmtCategoriaEvento->execute([$category_id]);
    $categoriaEvento = $stmtCategoriaEvento->fetch();
    if (!$categoriaEvento) {
        die('Erro: A categoria não está associada a um evento válido.');
    }
}

// Buscar os valores mais comuns de tax_type, type e zone desta categoria ou ponto de venda
if ($category_id) {
    $stmtCommonValues = $pdo->prepare("
        SELECT 
            (SELECT tax_type 
             FROM extras 
             WHERE extra_category_id = ? 
             GROUP BY tax_type 
             ORDER BY COUNT(*) DESC 
             LIMIT 1) as most_common_tax_type,
            (SELECT type 
             FROM extras 
             WHERE extra_category_id = ? 
             GROUP BY type 
             ORDER BY COUNT(*) DESC 
             LIMIT 1) as most_common_type,
            (SELECT zone_id 
             FROM extras 
             WHERE extra_category_id = ? 
             GROUP BY zone_id 
             ORDER BY COUNT(*) DESC 
             LIMIT 1) as most_common_zone
    ");
    $stmtCommonValues->execute([$category_id, $category_id, $category_id]);
} else if ($pos_id) {
    $stmtCommonValues = $pdo->prepare("
        SELECT 
            (SELECT tax_type 
             FROM extras e
             INNER JOIN extra_pos ep ON ep.extra_id = e.id
             WHERE ep.pos_id = ?
             GROUP BY tax_type 
             ORDER BY COUNT(*) DESC 
             LIMIT 1) as most_common_tax_type,
            (SELECT type 
             FROM extras e
             INNER JOIN extra_pos ep ON ep.extra_id = e.id
             WHERE ep.pos_id = ?
             GROUP BY type 
             ORDER BY COUNT(*) DESC 
             LIMIT 1) as most_common_type,
            (SELECT zone_id 
             FROM extras e
             INNER JOIN extra_pos ep ON ep.extra_id = e.id
             WHERE ep.pos_id = ?
             GROUP BY zone_id 
             ORDER BY COUNT(*) DESC 
             LIMIT 1) as most_common_zone
    ");
    $stmtCommonValues->execute([$pos_id, $pos_id, $pos_id]);
}
$commonValues = $stmtCommonValues->fetch();

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? 0;
    $tax_type = $_POST['tax_type'] ?? '';
    $type = $_POST['type'] ?? '';
    $event_id = (int)($_POST['event_id'] ?? 0);
    $zone_id = (int)($_POST['zone_id'] ?? 0);
    
    // Validar event_id
    if ($event_id <= 0) {
        $erro = "ID do evento inválido ou não foi fornecido";
    }
    
    if (empty($name)) {
        $erro = "O nome do extra é obrigatório";
    } else {
        try {
            // Verifica se $category_id é 0 e ajusta a consulta SQL adequadamente
                if ($category_id > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO extras (
                            extra_category_id, event_id, name, display_name, price, tax_type, tax, type, created_at, updated_at, zone_id
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?
                        )
                    ");
                    $stmt->execute([
                        $category_id,
                        $event_id,
                        $name,
                        $name, // Usando o mesmo valor para display_name
                        $price,
                        $tax_type,
                        $tax_type, // Usando o mesmo valor de tax_type para tax
                        $type,
                        $zone_id
                    ]);
                } else {
                    // Se não houver categoria, permite a criação sem categoria (NULL)
                    $stmt = $pdo->prepare("
                        INSERT INTO extras (
                            event_id, name, display_name, price, tax_type, tax, type, created_at, updated_at, zone_id
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?
                        )
                    ");
                    $stmt->execute([
                        $event_id,
                        $name,
                        $name, // Usando o mesmo valor para display_name
                        $price,
                        $tax_type,
                        $tax_type, // Usando o mesmo valor de tax_type para tax
                        $type,
                        $zone_id
                    ]);
                }
            
            // Pegar o ID do extra recém inserido
            $extra_id = $pdo->lastInsertId();

            // --- INTEGRACAO VENDUS: criar produto/serviço ---
            require_once __DIR__ . '/vendus/api.php';
            $vendus = new VendusAPI();
            // Buscar unidade padrão (exemplo: primeira unidade disponível)
            $unitsResult = $vendus->getUnits();
            $units = json_decode($unitsResult['response'], true);
            $unit_id = isset($units[0]['id']) ? $units[0]['id'] : null;
            // Montar payload mínimo para produto Vendus
            $params = [
                'reference'    => 'EXTRA_' . $extra_id,
                'title'        => $name,
                'gross_price'  => $price,
                'unit_id'      => 261335896,
                'type_id'      => ($type === 'service' ? 'S' : 'P'),
                'tax_id'       => $tax_type, // Ajusta conforme o mapeamento correto do teu Vendus
                'status'       => 'on',
            ];
            $vendusResult = $vendus->createProduct($params);
            // Guardar o id do produto Vendus na coluna toconline_item_id
            $vendusData = json_decode($vendusResult['response'], true);
            $vendus_id = isset($vendusData['id']) ? $vendusData['id'] : null;
            // --- FIM INTEGRACAO VENDUS ---
            
            // Definir automaticamente o campo 'toconline_item_code' como 'EXTRA_' seguido do ID do extra criado
            $toconlineItemCode = 'EXTRA_' . $extra_id;

            // Mapear tax_type para tax_id do Vendus
            $tax_map = [
                '23' => 'NOR', // Taxa Normal
                '13' => 'INT', // Taxa Intermédia
                '6'  => 'RED', // Taxa Reduzida
            ];
            $vendus_tax_id = isset($tax_map[$tax_type]) ? $tax_map[$tax_type] : 'NOR';
            // Mapear type para type_id do Vendus
            $vendus_type_id = ($type === 'service') ? 'S' : 'P';

            // Montar payload para produto Vendus
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
            // Atualizar o extra com o toconline_item_code e o id do Vendus
            $stmtUpdate = $pdo->prepare("UPDATE extras SET toconline_item_code = ?, toconline_item_id = ? WHERE id = ?");
            $stmtUpdate->execute([$toconlineItemCode, $vendus_id, $extra_id]);
            
            // Se houver pontos de venda selecionados, criar as associações na tabela extra_pos
            $selectedPosIds = isset($_POST['pos_id']) ? (array)$_POST['pos_id'] : [];
            if (!empty($selectedPosIds)) {
                $stmtExtraPos = $pdo->prepare("INSERT INTO extra_pos (extra_id, pos_id) VALUES (?, ?)");
                foreach ($selectedPosIds as $selectedPosId) {
                    $stmtExtraPos->execute([$extra_id, $selectedPosId]);
                }
                
                // Redirecionar de volta para a página de pontos de venda
                // Redirecionar de volta mantendo o PDV expandido
                if ($pos_id) {
                    header("Location: event_management.php?id=" . $event_id . "&pos_id=" . $pos_id);
                } else if ($category_id > 0) {
                    header("Location: categorias.php?event_id=" . $categoria['event_id'] . "#extras-" . $category_id);
                } else {
                    header("Location: event_management.php?id=" . $event_id);
                }
                exit;
            }
            
            // Se não houver pos_id, redirecionar de volta para a página de categorias
            if ($category_id > 0) {
                header("Location: categorias.php?event_id=" . $categoria['event_id'] . "#extras-" . $category_id);
            } else {
                header("Location: event_management.php?id=" . $event_id);
            }
            exit;
        } catch (PDOException $e) {
            // Verifica se é um erro de chave estrangeira
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                $erro = "Erro de restrição de chave estrangeira. Verifique se o ID da categoria e o ID do evento existem nas respectivas tabelas.";
                
                // Log detalhado para debug
                error_log("Erro ao criar extra - Foreign key constraint: " . $e->getMessage());
                error_log("Categoria ID: " . $category_id . ", Evento ID: " . $event_id);
            } else {
                $erro = "Erro ao criar extra: " . $e->getMessage();
            }
        }
    }
}

// Atualizar a consulta para buscar apenas as zonas associadas ao event_id atual
$stmtZones = $pdo->prepare("SELECT id, name FROM zones WHERE event_id = ?");
$stmtZones->execute([$event_id]);
$zones = $stmtZones->fetchAll();

// Garantir que a zona mais usada seja pré-selecionada no dropdown
$mostCommonZone = $commonValues['most_common_zone'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Criar Novo Extra - <?php echo htmlspecialchars($categoria['name']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"],
        input[type="number"],
        select { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box;
        }
        select[multiple] {
            height: 200px;
            overflow-y: auto;
        }
        select[multiple] option {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        select[multiple] option:hover {
            background-color: #f5f5f5;
        }
        #pos_search {
            margin-bottom: 10px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
        }
        button { 
            padding: 10px 20px; 
            background-color: #4CAF50; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
        }
        button:hover { background-color: #45a049; }
        .error { color: red; margin-bottom: 15px; }
        .note { color: #666; font-size: 0.9em; margin-top: 5px; font-style: italic; }
    </style>
</head>
<body>
    <?php if ($category_id): ?>
        <p><a href="categorias.php?event_id=<?php echo $categoria['event_id']; ?>">&larr; Voltar para Categorias</a></p>
    <?php else: ?>
        <p><a href="pos.php">&larr; Voltar para Pontos de Venda</a></p>
    <?php endif; ?>
    
    <h1>Criar Novo Extra</h1>
    <?php if ($category_id): ?>
        <h2>Categoria: <?php echo htmlspecialchars($categoria['name']); ?></h2>
    <?php endif; ?>
    <h3>Evento: <?php echo htmlspecialchars($evento['name']); ?></h3>

    <?php if (isset($erro)): ?>
        <div class="error"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="name">Nome do Extra:</label>
            <input type="text" id="name" name="name" required 
                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="price">Preço:</label>
            <input type="number" id="price" name="price" step="0.01" required
                   value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="tax_type">Tipo de Taxa:</label>
            <select id="tax_type" name="tax_type" required>
                <option value="">Selecione a taxa</option>
                <option value="6" <?php echo (isset($_POST['tax_type']) ? ($_POST['tax_type'] == '6' ? 'selected' : '') : ($commonValues['most_common_tax_type'] == '6' ? 'selected' : '')); ?>>6</option>
                <option value="13" <?php echo (isset($_POST['tax_type']) ? ($_POST['tax_type'] == '13' ? 'selected' : '') : ($commonValues['most_common_tax_type'] == '13' ? 'selected' : '')); ?>>13</option>
                <option value="23" <?php echo (isset($_POST['tax_type']) ? ($_POST['tax_type'] == '23' ? 'selected' : '') : ($commonValues['most_common_tax_type'] == '23' ? 'selected' : '')); ?>>23</option>
            </select>
        </div>

        <div class="form-group">
            <label for="type">Tipo:</label>
            <select id="type" name="type" required>
                <option value="">Selecione o tipo</option>
                <option value="product" <?php echo (isset($_POST['type']) ? ($_POST['type'] == 'product' ? 'selected' : '') : ($commonValues['most_common_type'] == 'product' ? 'selected' : '')); ?>>Produto</option>
                <option value="service" <?php echo (isset($_POST['type']) ? ($_POST['type'] == 'service' ? 'selected' : '') : ($commonValues['most_common_type'] == 'service' ? 'selected' : '')); ?>>Serviço</option>
            </select>
        </div>

        <div class="form-group">
            <label for="event_id">ID do Evento:</label>
            <input type="number" id="event_id" name="event_id" required
                   value="<?php echo isset($_POST['event_id']) ? htmlspecialchars($_POST['event_id']) : $event_id; ?>">
            <?php if ($category_id == 0): ?>
            <p class="note">Criando extra sem associação a uma categoria</p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="zone_id">Zona:</label>
            <select id="zone_id" name="zone_id" required>
                <option value="">Selecione a zona</option>
                <?php foreach ($zones as $zone): ?>
                    <option value="<?php echo $zone['id']; ?>" <?php echo (isset($_POST['zone_id']) ? ($_POST['zone_id'] == $zone['id'] ? 'selected' : '') : ($mostCommonZone == $zone['id'] ? 'selected' : '')); ?>>
                        <?php echo htmlspecialchars($zone['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!empty($pontos_venda)): ?>
        <div class="form-group">
            <label for="pos_search">Buscar Pontos de Venda:</label>
            <input type="text" id="pos_search" placeholder="Digite para buscar..."
                   style="margin-bottom: 10px;">
            
            <label for="pos_id">Pontos de Venda:</label>
            <select id="pos_id" name="pos_id[]" multiple size="8" 
                    <?php echo $pos_id ? 'required' : ''; ?>
                    style="height: 200px;">
                <?php foreach ($pontos_venda as $pos): ?>
                    <option value="<?php echo $pos['id']; ?>" 
                            <?php echo $pos_id == $pos['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pos['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="display: block; margin-top: 5px; color: #666;">
                Use Ctrl+Click para selecionar múltiplos pontos de venda
            </small>
        </div>

        <script>
        document.getElementById('pos_search').addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            const select = document.getElementById('pos_id');
            const options = select.options;

            for (let i = 0; i < options.length; i++) {
                const text = options[i].text.toLowerCase();
                const option = options[i];
                if (text.includes(search)) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
        });
        </script>
        <?php endif; ?>

        <button type="submit">Criar Extra</button>
    </form>
</body>
</html>
