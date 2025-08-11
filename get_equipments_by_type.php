<?php
include 'auth.php';
include 'conexao.php';

// Verificar se o type_id foi fornecido
if (!isset($_GET['type_id']) || !is_numeric($_GET['type_id'])) {
    echo '<p style="color: red;">ID de tipo inválido</p>';
    exit;
}

$typeId = (int)$_GET['type_id'];

try {
    // Primeiro, busca informações do tipo para mostrar no título
    $stmtType = $pdo->prepare("SELECT name FROM equipment_types WHERE id = ?");
    $stmtType->execute([$typeId]);
    $typeInfo = $stmtType->fetch();
    
    if (!$typeInfo) {
        echo '<p style="color: red;">Tipo de equipamento não encontrado</p>';
        exit;
    }
    
    // Buscar todos os equipamentos deste tipo
    $stmt = $pdo->prepare("
        SELECT e.*, 
               CASE 
                   WHEN (SELECT COUNT(*) FROM pos_equipment pe WHERE pe.equipment_id = e.id) > 0 THEN 'Em uso'
                   ELSE 'Disponível'
               END as status
        FROM equipment e
        WHERE e.type_id = ?
        ORDER BY e.serial_number
    ");
    $stmt->execute([$typeId]);
    $equipments = $stmt->fetchAll();
    
    // Contar quantos estão em uso e quantos estão disponíveis
    $inUseCount = 0;
    $availableCount = 0;
    
    foreach ($equipments as $equipment) {
        if ($equipment['status'] === 'Em uso') {
            $inUseCount++;
        } else {
            $availableCount++;
        }
    }
    
    // Exibir os resultados em uma tabela HTML
    ?>
    <div>
        <h3><?php echo htmlspecialchars($typeInfo['name']); ?> - Equipamentos</h3>
        <p>
            Total: <strong><?php echo count($equipments); ?></strong> | 
            Em uso: <strong><?php echo $inUseCount; ?></strong> | 
            Disponíveis: <strong><?php echo $availableCount; ?></strong>
        </p>
        
        <table>
            <thead>
                <tr>
                    <th>Número de Série</th>
                    <th>Status</th>
                    <th>Observações</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($equipments) === 0): ?>
                    <tr>
                        <td colspan="4" style="text-align: center;">Nenhum equipamento encontrado</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($equipments as $equipment): ?>
                        <tr class="equipment-item">
                            <td><?php echo htmlspecialchars($equipment['serial_number']); ?></td>
                            <td>
                                <span style="color: <?php echo $equipment['status'] === 'Em uso' ? '#f44336' : '#4CAF50'; ?>">
                                    <?php echo htmlspecialchars($equipment['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($equipment['notes'] ?: '-'); ?></td>
                            <td>
                                <a href="equipment_form.php?id=<?php echo $equipment['id']; ?>" 
                                   class="btn btn-edit">Editar</a>
                                
                                <?php if ($equipment['status'] !== 'Em uso'): ?>
                                    <a href="equipment.php?delete=<?php echo $equipment['id']; ?>" 
                                       class="btn btn-delete"
                                       onclick="return confirm('Tem certeza que deseja excluir este equipamento?')">
                                        Excluir
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($equipment['status'] === 'Em uso'): ?>
                                    <a href="equipment.php?type=<?php echo $typeId; ?>#equipment-<?php echo $equipment['id']; ?>" 
                                       class="btn" style="background-color: #607D8B; color: white;">
                                        Ver Detalhes
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    
} catch (PDOException $e) {
    echo '<p style="color: red;">Erro ao buscar equipamentos: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
