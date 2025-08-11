<?php
require_once 'auth.php';
require_once 'conexao.php';

header('Content-Type: application/json');

if (isset($_GET['action']) && $_GET['action'] === 'remove') {
    if (isset($_GET['pos_id']) && isset($_GET['user_id'])) {
        $pos_id = (int)$_GET['pos_id'];
        $user_id = (int)$_GET['user_id'];
        
        try {
            // Limpar o pos_id na tabela users
            $stmt = $pdo->prepare("UPDATE users SET pos_id = NULL WHERE id = ? AND pos_id = ?");
            $stmt->execute([$user_id, $pos_id]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    }
} else if (isset($_GET['action']) && $_GET['action'] === 'list') {
    // Listar todos os usuários disponíveis para associação
    $pos_id = isset($_GET['pos_id']) ? (int)$_GET['pos_id'] : 0;
    $event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
    
    if ($pos_id > 0) {
        try {
            // Buscar usuários não associados a nenhum PDV ainda (pos_id IS NULL)
            $stmt = $pdo->prepare("SELECT id, name, email, l_name FROM users WHERE pos_id IS NULL AND role_id IN (4, 6, 8) ORDER BY name");
            $stmt->execute();
            $users = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'users' => $users]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing pos_id parameter']);
    }
} else if (isset($_GET['action']) && $_GET['action'] === 'assign') {
    if (isset($_GET['pos_id']) && isset($_GET['user_id'])) {
        $pos_id = (int)$_GET['pos_id'];
        $user_id = (int)$_GET['user_id'];
        
        try {
            // Associar o usuário ao PDV
            $stmt = $pdo->prepare("UPDATE users SET pos_id = ? WHERE id = ? AND pos_id IS NULL");
            $stmt->execute([$pos_id, $user_id]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
