<?php
include 'auth.php';
include 'conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$pos_id = isset($_GET['pos_id']) ? (int)$_GET['pos_id'] : 0;
$erro = null;

// Buscar dados do extra
$stmt = $pdo->prepare("
    SELECT e.*, ec.name as category_name 
    FROM extras e
    LEFT JOIN extra_categories ec ON ec.id = e.extra_category_id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$extra = $stmt->fetch();

if (!$extra) {
    die('Extra não encontrado');
}

// Buscar pontos de venda associados a este extra
$stmtPos = $pdo->prepare("
    SELECT pos_id 
    FROM extra_pos 
    WHERE extra_id = ?
");
$stmtPos->execute([$id]);
$assigned_pos = $stmtPos->fetchAll(PDO::FETCH_COLUMN);

// Buscar todos os pontos de venda disponíveis
$stmtAllPos = $pdo->prepare("SELECT * FROM pos ORDER BY name");
$stmtAllPos->execute();
$all_pos = $stmtAllPos->fetchAll();

// Buscar zonas do evento
$stmtZones = $pdo->prepare("SELECT id, name FROM zones WHERE event_id = ?");
$stmtZones->execute([$event_id]);
$zones = $stmtZones->fetchAll();

// Buscar todas as categorias de extras do evento
$stmtCategories = $pdo->prepare("SELECT id, name FROM extra_categories WHERE event_id = ? ORDER BY name");
$stmtCategories->execute([$event_id]);
$categories = $stmtCategories->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? 0;
    $tax_type = $_POST['tax_type'] ?? '';
    $type = $_POST['type'] ?? '';
    $zone_id = $_POST['zone_id'] ?? 0;
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $selected_pos = isset($_POST['pos_id']) ? (array)$_POST['pos_id'] : [];
    
    if (empty($name)) {
        $erro = "O nome do extra é obrigatório";
    } else {
        try {
            $pdo->beginTransaction();

            // Atualizar informações do extra
            $stmt = $pdo->prepare("
                UPDATE extras 
                SET name = ?, 
                    display_name = ?,
                    price = ?,
                    tax_type = ?,
                    tax = ?,
                    type = ?,
                    zone_id = ?,
                    extra_category_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $name,
                $name,
                $price,
                $tax_type,
                $tax_type,
                $type,
                $zone_id,
                $category_id,
                $id
            ]);

            // Atualizar associações com PDVs
            // Primeiro remove todas as associações existentes
            $stmt = $pdo->prepare("DELETE FROM extra_pos WHERE extra_id = ?");
            $stmt->execute([$id]);

            // Depois adiciona as novas associações
            if (!empty($selected_pos)) {
                $stmt = $pdo->prepare("INSERT INTO extra_pos (extra_id, pos_id) VALUES (?, ?)");
                foreach ($selected_pos as $pos_id) {
                    $stmt->execute([$id, $pos_id]);
                }
            }

            $pdo->commit();            // Redirecionar de volta mantendo o PDV expandido e os filtros
            $redirect_url = "event_management.php?id=" . $event_id;
            
            if ($pos_id) {
                $redirect_url .= "&pos_id=" . $pos_id;
            }
            
            // Manter os parâmetros de filtro
            if (isset($_GET['category'])) {
                $redirect_url .= "&category=" . urlencode($_GET['category']);
            }
            if (isset($_GET['search'])) {
                $redirect_url .= "&search=" . urlencode($_GET['search']);
            }
            
            header("Location: " . $redirect_url);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = "Erro ao atualizar extra: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Editar Extra</title>
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
        #pos_search {
            margin-bottom: 10px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <p><a href="event_management.php?id=<?php echo $event_id; ?><?php echo $pos_id ? '&pos_id=' . $pos_id : ''; ?><?php echo isset($_GET['category']) ? '&category='.urlencode($_GET['category']) : ''; ?><?php echo isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>">&larr; Voltar</a></p>
    
    <h1>Editar Extra</h1>
    <?php if ($extra['category_name']): ?>
        <h2>Categoria: <?php echo htmlspecialchars($extra['category_name']); ?></h2>
    <?php endif; ?>

    <?php if (isset($erro)): ?>
        <div class="error"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <form method="post">
        <?php if (isset($_GET['category'])): ?>
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($_GET['category']); ?>">
        <?php endif; ?>
        
        <?php if (isset($_GET['search'])): ?>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="name">Nome do Extra:</label>
            <input type="text" id="name" name="name" required 
                   value="<?php echo htmlspecialchars($extra['name']); ?>">
        </div>

        <div class="form-group">
            <label for="price">Preço:</label>
            <input type="number" id="price" name="price" step="0.01" required
                   value="<?php echo htmlspecialchars($extra['price']); ?>">
        </div>

        <div class="form-group">
            <label for="tax_type">Tipo de Taxa:</label>
            <select id="tax_type" name="tax_type" required>
                <option value="">Selecione a taxa</option>
                <option value="6" <?php echo $extra['tax_type'] == '6' ? 'selected' : ''; ?>>6</option>
                <option value="13" <?php echo $extra['tax_type'] == '13' ? 'selected' : ''; ?>>13</option>
                <option value="23" <?php echo $extra['tax_type'] == '23' ? 'selected' : ''; ?>>23</option>
            </select>
        </div>

        <div class="form-group">
            <label for="type">Tipo:</label>
            <select id="type" name="type" required>
                <option value="">Selecione o tipo</option>
                <option value="product" <?php echo $extra['type'] == 'product' ? 'selected' : ''; ?>>Produto</option>
                <option value="service" <?php echo $extra['type'] == 'service' ? 'selected' : ''; ?>>Serviço</option>
            </select>
        </div>

        <div class="form-group">
            <label for="zone_id">Zona:</label>
            <select id="zone_id" name="zone_id" required>
                <option value="">Selecione a zona</option>
                <?php foreach ($zones as $zone): ?>
                    <option value="<?php echo $zone['id']; ?>" <?php echo $extra['zone_id'] == $zone['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($zone['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="category_id">Categoria:</label>
            <select id="category_id" name="category_id" required>
                <option value="">Selecione a categoria</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $extra['extra_category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="pos_search">Buscar Pontos de Venda:</label>
            <input type="text" id="pos_search" placeholder="Digite para buscar...">
            
            <label for="pos_id">Pontos de Venda:</label>
            <select id="pos_id" name="pos_id[]" multiple size="8">
                <?php foreach ($all_pos as $pos): ?>
                    <option value="<?php echo $pos['id']; ?>" 
                            <?php echo in_array($pos['id'], $assigned_pos) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pos['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="display: block; margin-top: 5px; color: #666;">
                Use Ctrl+Click para selecionar múltiplos pontos de venda
            </small>
        </div>

        <button type="submit">Salvar Alterações</button>
    </form>

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
</body>
</html>
