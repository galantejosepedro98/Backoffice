<?php
include 'auth.php';
include 'conexao.php';

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$pos_id = isset($_GET['pos_id']) ? (int)$_GET['pos_id'] : 0;

// Validate context
if (!$event_id || !$pos_id) {
    die('Erro: Contexto inválido. ID do evento e PDV são obrigatórios.');
}

// Buscar todas as categorias de extras do evento
$stmtCategories = $pdo->prepare("
    SELECT DISTINCT ec.id, ec.name
    FROM extra_categories ec
    WHERE ec.event_id = ?
    ORDER BY ec.name
");
$stmtCategories->execute([$event_id]);
$categories = $stmtCategories->fetchAll();

// Buscar todos os extras e verificar quais já estão associados ao PDV atual
$sql = "
    SELECT 
        e.*,
        ec.name as category_name,
        CASE WHEN ep.pos_id IS NOT NULL THEN 1 ELSE 0 END as is_associated
    FROM extras e
    LEFT JOIN extra_categories ec ON e.extra_category_id = ec.id
    LEFT JOIN extra_pos ep ON ep.extra_id = e.id AND ep.pos_id = ?
    WHERE e.event_id = ?
";

$params = [$pos_id, $event_id];
if ($category_id) {
    $sql .= " AND e.extra_category_id = ?";
    $params[] = $category_id;
}

$sql .= " ORDER BY COALESCE(ec.name, 'Sem Categoria'), e.name";

$stmtExtras = $pdo->prepare($sql);
$stmtExtras->execute($params);
$extras = $stmtExtras->fetchAll();

// Agrupar extras por categoria
$groupedExtras = [];
foreach ($extras as $extra) {
    $categoryName = $extra['category_name'] ?? 'Sem Categoria';
    if (!isset($groupedExtras[$categoryName])) {
        $groupedExtras[$categoryName] = [];
    }
    $groupedExtras[$categoryName][] = $extra;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        .extras-sidebar {
            padding: 15px;
            background-color: #f5f5f5;
            border-right: 1px solid #ddd;
            height: 100%;
            overflow-y: auto;
        }
        .extras-group {
            margin-bottom: 20px;
        }
        .extras-group h3 {
            margin: 0 0 10px 0;
            padding: 5px 0;
            border-bottom: 2px solid #ddd;
            font-size: 1em;
        }
        .extra-item {
            margin: 5px 0;
            padding: 5px;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        .extra-item:hover {
            background-color: #e9ecef;
            border-color: #dee2e6;
        }
        .extra-item.selected {
            background-color: #007bff;
            color: white;
        }
        .extra-item.associated {
            background-color: #e8f5e9;
            border-left: 3px solid #4CAF50;
        }
        
        .extra-item.associated:hover {
            background-color: #c8e6c9;
        }
        
        .extra-item .status-badge {
            float: right;
            font-size: 0.8em;
            padding: 2px 6px;
            border-radius: 3px;
            background-color: #4CAF50;
            color: white;
        }
        
        .extra-price {
            color: #666;
            margin-left: 10px;
        }
        .category-filter {
            margin-bottom: 15px;
        }
        .search-box {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn-new-extra {
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 15px;
        }
        .btn-new-extra:hover {
            background-color: #218838;
        }
        
        .close-sidebar {
            position: absolute;
            right: 10px;
            top: 10px;
            font-size: 24px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px 10px;
            color: #666;
            z-index: 1000;
        }
        
        .close-sidebar:hover {
            color: #333;
            background-color: #f0f0f0;
            border-radius: 4px;
        }
        
        .sidebar-header {
            position: relative;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            margin-bottom: 15px;
        }
        
        .sidebar-header h2 {
            margin: 0;
            padding-right: 40px;
        }
        
        .extra-item:active {
            transform: scale(0.98);
            background-color: #e0e0e0;
        }
        
        .extra-item.associating {
            opacity: 0.7;
            pointer-events: none;
        }
        
        /* Animação quando um extra é associado */
        @keyframes successPulse {
            0% { background-color: #e8f5e9; }
            50% { background-color: #c8e6c9; }
            100% { background-color: #e8f5e9; }
        }
        
        .extra-item.just-associated {
            animation: successPulse 1s ease;
        }
    </style>
</head>
<body>
    <div class="extras-sidebar">
        <div class="sidebar-header">
            <h2>Adicionar Extras</h2>
            <button class="close-sidebar" onclick="window.parent.closeExtrasSidebar()">&times;</button>
        </div>
          <button class="btn-new-extra" onclick="window.parent.location.href='criar_extra.php?event_id=<?php echo $event_id; ?><?php echo $category_id ? '&category_id=' . $category_id : ''; ?><?php echo $pos_id ? '&pos_id=' . $pos_id : ''; ?>'">
            Criar Novo Extra
        </button>

        <input type="text" class="search-box" id="extraSearch" placeholder="Buscar extras...">
        
        <?php if (!empty($categories)): ?>
        <div class="category-filter">
            <select id="categoryFilter" style="width: 100%; padding: 8px;">
                <option value="">Todas as Categorias</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['name']); ?>"
                            <?php echo ($category_id == $cat['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
                <option value="Sem Categoria">Sem Categoria</option>
            </select>
        </div>
        <?php endif; ?>

        <?php foreach ($groupedExtras as $categoryName => $categoryExtras): ?>
        <div class="extras-group" data-category="<?php echo htmlspecialchars($categoryName); ?>">
            <h3><?php echo htmlspecialchars($categoryName); ?></h3>
            <?php foreach ($categoryExtras as $extra): ?>            <div class="extra-item<?php echo $extra['is_associated'] ? ' associated' : ''; ?>" 
                 data-id="<?php echo $extra['id']; ?>"
                 data-name="<?php echo htmlspecialchars($extra['name']); ?>"
                 data-price="<?php echo $extra['price']; ?>"
                 data-category="<?php echo htmlspecialchars($categoryName); ?>">
                <?php echo htmlspecialchars($extra['name']); ?>
                <span class="extra-price">€<?php echo number_format($extra['price'], 2); ?></span>
                <?php if ($extra['is_associated']): ?>
                    <span class="status-badge">Associado</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
        document.getElementById('extraSearch').addEventListener('input', function(e) {
            const searchText = e.target.value.toLowerCase();
            const groups = document.querySelectorAll('.extras-group');
            let hasResults = false;
            
            groups.forEach(group => {
                const extraItems = group.querySelectorAll('.extra-item');
                let groupHasVisibleItems = false;
                
                extraItems.forEach(item => {
                    const name = item.getAttribute('data-name').toLowerCase();
                    const category = item.getAttribute('data-category').toLowerCase();
                    const shouldShow = name.includes(searchText) || category.includes(searchText);
                    
                    item.style.display = shouldShow ? '' : 'none';
                    if (shouldShow) {
                        groupHasVisibleItems = true;
                        hasResults = true;
                    }
                });
                
                // Show/hide the entire category group
                group.style.display = groupHasVisibleItems ? '' : 'none';
            });
            
            // Show a message if no results are found
            const noResults = document.getElementById('noResults') || (() => {
                const div = document.createElement('div');
                div.id = 'noResults';
                div.style.textAlign = 'center';
                div.style.padding = '20px';
                div.style.color = '#666';
                document.querySelector('.extras-sidebar').appendChild(div);
                return div;
            })();
            
            noResults.textContent = !hasResults ? 'Nenhum extra encontrado' : '';
        });

        document.getElementById('categoryFilter').addEventListener('change', function(e) {
            const selectedCategory = e.target.value;
            const groups = document.querySelectorAll('.extras-group');
            let hasVisibleItems = false;
            
            groups.forEach(group => {
                const groupCategory = group.getAttribute('data-category');
                const shouldShowGroup = !selectedCategory || groupCategory === selectedCategory;
                
                group.style.display = shouldShowGroup ? '' : 'none';
                if (shouldShowGroup) {
                    hasVisibleItems = true;
                }
            });
            
            // Reset search when changing category
            document.getElementById('extraSearch').value = '';
            
            // Show/hide no results message
            const noResults = document.getElementById('noResults') || document.createElement('div');
            noResults.id = 'noResults';
            noResults.style.textAlign = 'center';
            noResults.style.padding = '20px';
            noResults.style.color = '#666';
            noResults.textContent = !hasVisibleItems ? 'Nenhum extra nesta categoria' : '';
            
            if (!hasVisibleItems && !noResults.parentNode) {
                document.querySelector('.extras-sidebar').appendChild(noResults);
            } else if (hasVisibleItems && noResults.parentNode) {
                noResults.remove();
            }
        });
        // Adicionar extra ao clicar
        document.querySelectorAll('.extra-item').forEach(item => {
            item.addEventListener('click', function(e) {
                const extraId = this.getAttribute('data-id');
                const isAssociated = this.classList.contains('associated');
                
                // Se já estiver associado, não faz nada
                if (isAssociated) {
                    return;
                }
                
                // Desabilitar o item temporariamente para evitar cliques duplos
                this.style.pointerEvents = 'none';
                this.style.opacity = '0.7';
                // Fazer a requisição para associar o extra
                fetch('assign_extra.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        extra_id: extraId,
                        pos_id: <?php echo $pos_id; ?>,
                        event_id: <?php echo $event_id; ?>
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => Promise.reject(err));
                    }
                    return response.json();
                })
                .then(data => {                    if (data.success) {
                        // Marcar como associado visualmente
                        this.classList.add('associated', 'just-associated');
                        if (!this.querySelector('.status-badge')) {
                            const statusBadge = document.createElement('span');
                            statusBadge.className = 'status-badge';
                            statusBadge.textContent = 'Associado';
                            this.appendChild(statusBadge);
                        }
                        
                        // Atualizar o conteúdo do PDV no parent
                        if (window.parent && window.parent.updatePDVContent) {
                            window.parent.updatePDVContent(<?php echo $pos_id; ?>);
                        } else {
                            console.error('Parent window or updatePDVContent function not found');
                        }

                        // Remove animation class after animation completes
                        setTimeout(() => {
                            this.classList.remove('just-associated');
                        }, 1000);
                    } else {
                        throw new Error(data.message || 'Erro desconhecido ao associar extra');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(error.message || 'Erro ao associar extra. Por favor, tente novamente.');
                })
                .finally(() => {
                    // Reabilitar o item
                    this.style.pointerEvents = '';
                    this.style.opacity = '';
                });
            });
        });
    </script>
</body>
</html>
