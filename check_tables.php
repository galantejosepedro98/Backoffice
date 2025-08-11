<?php
include 'auth.php';
include 'conexao.php';

try {
    $tables = [
        'events',
        'pos',
        'event_pos',
        'event_pos_equipment',
        'users',
        'staff_passwords',
        'equipment',
        'equipment_types',
        'internet_cards'
    ];
    
    foreach ($tables as $table) {
        echo "=== STRUCTURE OF $table ===\n";
        $stmt = $pdo->prepare("DESCRIBE $table");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "{$column['Field']} - {$column['Type']}";
            if ($column['Key']) echo " - Key: {$column['Key']}";
            if ($column['Extra']) echo " - {$column['Extra']}";
            echo "\n";
        }
        echo "\n";
    }
    
    echo "=== CHECKING RELATIONSHIPS ===\n";
    // Check relationship between event_pos_equipment and internet_cards
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as count 
        FROM information_schema.columns
        WHERE table_name = 'event_pos_equipment' 
        AND column_name = 'internet_card_id'
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result['count'] > 0) {
        echo "event_pos_equipment has internet_card_id column\n";
    } else {
        echo "event_pos_equipment does NOT have internet_card_id column\n";
    }
    
    // Check if equipment_internet_cards table exists
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as count 
        FROM information_schema.tables
        WHERE table_name = 'equipment_internet_cards'
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result['count'] > 0) {
        echo "equipment_internet_cards table exists\n";
        
        // Check structure
        $stmt = $pdo->prepare("DESCRIBE equipment_internet_cards");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "equipment_internet_cards columns:\n";
        foreach ($columns as $column) {
            echo "{$column['Field']} - {$column['Type']}\n";
        }
    } else {
        echo "equipment_internet_cards table does NOT exist\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
