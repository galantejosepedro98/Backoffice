<?php
include 'auth.php';
include 'conexao.php';

// Obter as roles disponíveis para o filtro
$stmt_roles = $pdo->query("SELECT id, name FROM roles WHERE id IN (6, 4, 8) ORDER BY name");
$available_roles = $stmt_roles->fetchAll();

// Obter os eventos disponíveis para o filtro
$stmt_eventos = $pdo->query("SELECT id, name FROM events ORDER BY start_at DESC");
$available_events = $stmt_eventos->fetchAll();

// Verificar se existe um filtro de role
$filtered_role = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;

// Verificar se existe um filtro de evento
$filtered_event = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Construir a query com base nos filtros
$where_clause = "WHERE u.role_id IN (6, 4, 8)";
$join_clause = "";
$params = [];

if ($filtered_role > 0 && in_array($filtered_role, array_column($available_roles, 'id'))) {
    $where_clause = "WHERE u.role_id = ?";
    $params[] = $filtered_role;
}

// Se um evento for selecionado, adicionar junções e condição para filtrar por evento
if ($filtered_event > 0) {
    $join_clause = "LEFT JOIN event_pos ep ON ep.pos_id = p.id";
    
    // Se já existe uma cláusula WHERE, adicionar AND, senão começar com WHERE
    if (strpos($where_clause, 'WHERE') !== false) {
        $where_clause .= " AND ep.event_id = ?";
    } else {
        $where_clause = "WHERE ep.event_id = ?";
    }
    
    // Adicionar mais uma condição para garantir que o usuário tem um PDV atribuído
    $where_clause .= " AND u.pos_id IS NOT NULL";
    
    $params[] = $filtered_event;
}

// Buscar usuários com base no filtro
$query = "
    SELECT u.id, CONCAT(u.name, ' ', IFNULL(u.l_name, '')) as full_name, u.email, 
           u.pos_id, p.name as pos_name,
           (SELECT sp.clear_password 
            FROM staff_passwords sp 
            WHERE sp.user_id = u.id 
            ORDER BY sp.created_at DESC 
            LIMIT 1) as current_password,
           r.name as role_name, u.role_id,
           e.id as event_id, e.name as event_name
    FROM users u
    LEFT JOIN pos p ON p.id = u.pos_id
    LEFT JOIN roles r ON r.id = u.role_id
    $join_clause
    LEFT JOIN events e ON " . ($filtered_event > 0 ? "e.id = ep.event_id" : "1=0") . "
    $where_clause
    GROUP BY u.id
    ORDER BY u.role_id, u.id
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Usuários - Painel Local</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            margin: 0;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 8px; 
            text-align: left; 
        }
        th { 
            background-color: #f5f5f5; 
        }
        tr:hover {
            background-color: #f9f9f9;
        }        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
    </style>
</head>
<body>    <?php include 'navbar.php'; ?>    <h1>Lista de Usuários</h1>      <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <div style="margin-bottom: 10px;">
            <a href="criar_user.php" class="btn btn-primary">+ Adicionar Usuário</a>
        </div>
        <div style="width: 100%;">
            <form method="GET" action="" style="display: flex; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div style="display: flex; align-items: center;">
                    <label for="role_filter" style="margin-right: 10px;">Filtrar por função:</label>
                    <select name="role_id" id="role_filter" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd; min-width: 200px;">
                        <option value="0">Todas as funções</option>
                        <?php foreach ($available_roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>" <?php echo $filtered_role == $role['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; align-items: center;">
                    <label for="event_filter" style="margin-right: 10px;">Filtrar por evento:</label>
                    <select name="event_id" id="event_filter" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd; min-width: 200px;">
                        <option value="0">Todos os eventos</option>
                        <?php foreach ($available_events as $event): ?>
                            <option value="<?php echo $event['id']; ?>" <?php echo $filtered_event == $event['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($event['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="btn btn-primary" style="background-color: #607D8B;">Aplicar Filtros</button>
                    <?php if ($filtered_role > 0 || $filtered_event > 0): ?>
                        <a href="users.php" class="btn btn-primary" style="background-color: #FF9800; margin-left: 5px;">Limpar Filtros</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>    <?php 
    $total_users = count($users);
    $role_name = '';
    $event_name = '';
    
    if ($filtered_role > 0) {
        foreach ($available_roles as $role) {
            if ($role['id'] == $filtered_role) {
                $role_name = $role['name'];
                break;
            }
        }
    }
    
    if ($filtered_event > 0) {
        foreach ($available_events as $event) {
            if ($event['id'] == $filtered_event) {
                $event_name = $event['name'];
                break;
            }
        }
    }
    ?>
    <div style="margin-bottom: 15px; background-color: #f8f9fa; padding: 10px; border-radius: 5px; border: 1px solid #dee2e6;">
        <p>
            <strong><?php echo $total_users; ?></strong> usuário(s) encontrado(s)
            <?php if ($role_name && $event_name): ?>
                para a função <strong><?php echo htmlspecialchars($role_name); ?></strong> 
                no evento <strong><?php echo htmlspecialchars($event_name); ?></strong>
            <?php elseif ($role_name): ?>
                para a função <strong><?php echo htmlspecialchars($role_name); ?></strong>
            <?php elseif ($event_name): ?>
                associados ao evento <strong><?php echo htmlspecialchars($event_name); ?></strong>
            <?php endif; ?>
        </p>
    </div>    <table><thead><tr>                <th width="5%">ID</th>
                <th width="15%">Nome Completo</th>
                <th width="15%">Email</th>
                <th width="10%">Função</th>
                <th width="10%">Ponto de Venda</th>
                <?php if ($filtered_event > 0): ?>
                    <th width="10%">Evento</th>
                <?php endif; ?>
                <th width="15%">Password</th>
                <th width="<?php echo $filtered_event > 0 ? '20%' : '30%'; ?>">Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>                <td><?php echo htmlspecialchars($user['id']); ?></td>                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                <td><?php echo $user['pos_name'] ? htmlspecialchars($user['pos_name']) : '-'; ?></td>
                <?php if ($filtered_event > 0): ?>
                    <td><?php echo $user['event_name'] ? htmlspecialchars($user['event_name']) : '-'; ?></td>
                <?php endif; ?>
                <td><?php echo $user['current_password'] ? htmlspecialchars($user['current_password']) : '-'; ?></td><td>
                    <a href="editar_user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary" style="background-color: #2196F3;">Editar</a>
                    <a href="reset_password.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">Reset Password</a>
                    <?php if ($user['pos_id']): ?>
                        <a href="reset_pos.php?id=<?php echo $user['id']; ?>" class="btn btn-primary" style="background-color: #f44336;">Reset Ponto de Venda</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
