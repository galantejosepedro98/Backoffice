<?php
include 'auth.php';
include 'conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$error = null;
$equipment = [
    'serial_number' => '',
    'type_id' => '',
    'notes' => ''
];

// Fetch equipment types for dropdown
$stmtTypes = $pdo->query("SELECT id, name FROM equipment_types ORDER BY name");
$types = $stmtTypes->fetchAll();

// Buscar o tipo do último equipamento criado para usar como valor inicial
$lastEquipmentType = null;
if (!$id) { // Só busca se for criação de novo equipamento
    $stmtLast = $pdo->query("
        SELECT type_id 
        FROM equipment 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $lastEquipment = $stmtLast->fetch();
    if ($lastEquipment) {
        $lastEquipmentType = $lastEquipment['type_id'];
    }
}

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $equipment = [
            'serial_number' => $_POST['serial_number'],
            'type_id' => $_POST['type_id'],
            'notes' => $_POST['notes']
        ];
        
        // Capturar o event_id do POST também
        $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;

        if ($id) {
            $stmt = $pdo->prepare("                UPDATE equipment 
                SET serial_number = ?, type_id = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $equipment['serial_number'],
                $equipment['type_id'],
                $equipment['notes'],
                $id
            ]);
        } else {
            $stmt = $pdo->prepare("                INSERT INTO equipment (serial_number, type_id, notes, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $equipment['serial_number'],
                $equipment['type_id'],
                $equipment['notes']
            ]);        }          // Redirecionamento inteligente para o local de origem
        $redirect_url = null;
        if (!empty($_SERVER['HTTP_REFERER'])) {
            // Se veio de event_management.php, adiciona âncora para o equipamento
            if (strpos($_SERVER['HTTP_REFERER'], 'event_management.php') !== false && $id) {
                $redirect_url = $_SERVER['HTTP_REFERER'] . '#equip-' . $id;
            } else {
                $redirect_url = $_SERVER['HTTP_REFERER'];
            }
        }
        if ($redirect_url) {
            // Se estiver em iframe/modal, força recarregar a página de origem
            echo "<script>if(window.top !== window.self){window.top.location.href='" . addslashes($redirect_url) . "';}else{window.location.href='" . addslashes($redirect_url) . "';}</script>";
            exit;
        } elseif ($event_id) {
            $pos_id = isset($_GET['pos_id']) ? '&pos_id=' . (int)$_GET['pos_id'] : '';
            $category_filter = isset($_GET['category']) ? '&category=' . urlencode($_GET['category']) : '';
            $search_term = isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';
            $url = "event_management.php?id=" . $event_id . $pos_id . $category_filter . $search_term;
            echo "<script>if(window.top !== window.self){window.top.location.href='" . addslashes($url) . "';}else{window.location.href='" . addslashes($url) . "';}</script>";
            exit;
        } else {
            echo "<script>if(window.top !== window.self){window.top.location.href='equipments.php';}else{window.location.href='equipments.php';}</script>";
            exit;
        }
    } catch (PDOException $e) {
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM equipment WHERE id = ?");
    $stmt->execute([$id]);
    $equipment = $stmt->fetch();
    if (!$equipment) {
        header("Location: equipment.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $id ? 'Editar' : 'Novo'; ?> Equipamento</title>
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
        .error {
            color: red;
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <h1><?php echo $id ? 'Editar' : 'Novo'; ?> Equipamento</h1>

    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>    <form method="post">
        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
        
        <?php if(isset($_GET['pos_id'])): ?>
            <input type="hidden" name="pos_id" value="<?php echo (int)$_GET['pos_id']; ?>">
        <?php endif; ?>
        
        <?php if(isset($_GET['category'])): ?>
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($_GET['category']); ?>">
        <?php endif; ?>
        
        <?php if(isset($_GET['search'])): ?>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label for="type_id">Tipo de Equipamento</label>            <select id="type_id" name="type_id" required>
                <option value="">Selecione...</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?php echo $type['id']; ?>" 
                        <?php echo ($id && $equipment['type_id'] == $type['id']) || (!$id && $lastEquipmentType == $type['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="serial_number">Número de Série</label>
            <input type="text" id="serial_number" name="serial_number" value="<?php echo htmlspecialchars($equipment['serial_number']); ?>" required>
        </div>

        <div class="form-group">
            <label for="notes">Observações</label>
            <textarea id="notes" name="notes"><?php echo htmlspecialchars($equipment['notes']); ?></textarea>
        </div>        <?php
        // Botão cancelar também volta para o referer com âncora se possível
        $cancel_url = null;
        if (!empty($_SERVER['HTTP_REFERER'])) {
            if (strpos($_SERVER['HTTP_REFERER'], 'event_management.php') !== false && $id) {
                $cancel_url = $_SERVER['HTTP_REFERER'] . '#equip-' . $id;
            } else {
                $cancel_url = $_SERVER['HTTP_REFERER'];
            }
        }
        ?>
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="#" class="btn btn-secondary" onclick="
            if(window.top !== window.self){ window.top.location.href='<?php echo addslashes($cancel_url ?: ($event_id ? 'event_management.php?id=' . $event_id : 'equipments.php')); ?>'; } else { window.location.href='<?php echo addslashes($cancel_url ?: ($event_id ? 'event_management.php?id=' . $event_id : 'equipments.php')); ?>'; } return false;">Cancelar</a>
    </form>
</body>
</html>
