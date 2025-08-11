<?php
// Arquivo para funcionalidade do sidebar de equipamentos
if (!isset($id)) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
}
?>
<div id="equipmentSidebar" class="sidebar">
    <div class="sidebar-header">
        <h2>Adicionar Equipamento</h2>
        <button class="close-sidebar" onclick="closeEquipmentSidebar()">&times;</button>
    </div>
    <button class="btn-new-equipment" onclick="window.location.href='equipment_form.php'">
        Criar Novo Equipamento
    </button>
    <div class="sidebar-filters">
        <input 
            type="text" 
            id="equipmentSearch" 
            class="form-control" 
            placeholder="Pesquisar por número de série..."
            style="margin-bottom: 10px;"
        >
        <select 
            id="equipmentTypeFilter" 
            class="form-select"
            style="margin-bottom: 20px;"
        >
            <option value="">Todos os tipos</option>
        </select>
    </div>

    <div id="equipmentList" class="equipment-list">
        <!-- Lista de equipamentos será carregada aqui via JavaScript -->
    </div>
</div>

<style>
.sidebar-filters {
    padding: 15px;
    border-bottom: 1px solid #ddd;
}
.equipment-item {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.equipment-item:hover {
    background-color: #f5f5f5;
}
.equipment-type {
    color: #666;
    font-size: 0.9em;
}
.btn-new-equipment {
    width: calc(100% - 30px);
    padding: 10px;
    background-color: #28a745;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    margin: 0 15px 15px;
}
.btn-new-equipment:hover {
    background-color: #218838;
}
</style>

<script>
let currentPosId = null;
let equipmentData = [];

function openEquipmentSidebar(event, posId) {
    event.stopPropagation();    currentPosId = posId;
    const sidebar = document.getElementById('equipmentSidebar');
    const equipmentList = document.getElementById('equipmentList');
    
    // Limpar filtros
    document.getElementById('equipmentSearch').value = '';
    document.getElementById('equipmentTypeFilter').value = '';
    
    // Adicionar os event listeners
    document.getElementById('equipmentSearch').addEventListener('input', filterEquipment);
    document.getElementById('equipmentTypeFilter').addEventListener('change', filterEquipment);
    
    sidebar.classList.add('active');
    equipmentList.innerHTML = '<div class="loading">Carregando equipamentos...</div>';
      fetch('get_available_equipment.php')
        .then(response => response.json())
        .then(data => {
            equipmentData = data;
            // Preencher o select de tipos de equipamento
            const typeFilter = document.getElementById('equipmentTypeFilter');
            const types = [...new Set(data.map(e => e.type_name))].sort();
            types.forEach(type => {
                if (type) {
                    const option = document.createElement('option');
                    option.value = type;
                    option.textContent = type;
                    typeFilter.appendChild(option);
                }
            });
            renderEquipmentList(data);
        })
        .catch(error => {
            console.error('Erro:', error);
            equipmentList.innerHTML = '<div class="error">Erro ao carregar equipamentos</div>';
        });
}

function filterEquipment() {
    const searchTerm = document.getElementById('equipmentSearch').value.toLowerCase();
    const selectedType = document.getElementById('equipmentTypeFilter').value;
    
    console.log('Filtrando:', { searchTerm, selectedType }); // Debug
    
    const filteredEquipment = equipmentData.filter(equipment => {
        const serialNumber = (equipment.serial_number || '').toLowerCase();
        const typeName = (equipment.type_name || '').toLowerCase();
        
        const matchesSearch = serialNumber.includes(searchTerm);
        const matchesType = !selectedType || equipment.type_name === selectedType;
        
        console.log('Equipment:', equipment.serial_number, 'Type:', equipment.type_name, 'Matches:', matchesSearch && matchesType); // Debug
        
        return matchesSearch && matchesType;
    });
    
    console.log('Equipamentos filtrados:', filteredEquipment.length); // Debug
    renderFilteredList(filteredEquipment);
}

function renderEquipmentList(equipment) {
    equipmentData = equipment;
    const equipmentList = document.getElementById('equipmentList');
    
    // Reset and reattach event listeners
    const searchInput = document.getElementById('equipmentSearch');
    const typeFilter = document.getElementById('equipmentTypeFilter');
    
    searchInput.removeEventListener('input', filterEquipment);
    typeFilter.removeEventListener('change', filterEquipment);
    
    searchInput.addEventListener('input', filterEquipment);
    typeFilter.addEventListener('change', filterEquipment);
    
    // Initial render
    renderFilteredList(equipment);
}

function renderFilteredList(equipment) {
    const equipmentList = document.getElementById('equipmentList');
    equipmentList.innerHTML = '';
    
    if (!equipment || equipment.length === 0) {
        equipmentList.innerHTML = '<div class="no-equipment">Nenhum equipamento disponível</div>';
        return;
    }
    
    equipment.forEach(equipment => {
        const item = document.createElement('div');
        item.className = 'equipment-item';                
        const serialNumber = equipment.serial_number || '(Sem número de série)';
        item.innerHTML = `
            <div>
                <strong>${serialNumber}</strong>
                <div class="equipment-type">${equipment.type_name || ''}</div>
            </div>
        `;
        item.onclick = () => assignEquipment(equipment.id, currentPosId);
        equipmentList.appendChild(item);
    });
}

function closeEquipmentSidebar() {
    const sidebar = document.getElementById('equipmentSidebar');
    sidebar.classList.remove('active');
    currentPosId = null;
}

function updatePosEquipment(posId) {
    const eventId = <?php echo $id; ?>;
    
    // Find the content container
    const posContent = document.querySelector(`.pos-equipment-content[data-pos-id="${posId}"]`);
    if (!posContent) return;
    
    // Show loading state
    posContent.innerHTML = '<div class="loading">Atualizando equipamentos...</div>';
    
    // Ensure PDV is expanded
    const posContentDiv = posContent.closest('.pos-content');
    if (!posContentDiv.classList.contains('active')) {
        posContentDiv.classList.add('active');
    }
    
    fetch(`get_pos_equipment.php?pos_id=${posId}&event_id=${eventId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                posContent.innerHTML = data.html;
            } else {
                throw new Error(data.message || 'Erro ao atualizar equipamentos');
            }
        })
        .catch(error => {
            console.error('Erro ao atualizar lista de equipamentos:', error);
            posContent.innerHTML = '<div class="error">Erro ao atualizar lista de equipamentos</div>';
            showNotification('Erro ao atualizar lista de equipamentos', false);
        });
}

function showNotification(message, isSuccess = true) {
    const notification = document.createElement('div');
    notification.className = `notification ${isSuccess ? 'success' : 'error'}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 500);
    }, 3000);
}

function assignEquipment(equipmentId, posId) {
    const item = event.currentTarget;
    item.style.pointerEvents = 'none';
    item.style.opacity = '0.5';
    item.classList.add('disabled');

    fetch('assign_equipment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `equipment_id=${equipmentId}&pos_id=${posId}&event_id=${<?php echo $id; ?>}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove o equipamento da lista
            equipmentData = equipmentData.filter(e => e.id !== equipmentId);
            renderEquipmentList(equipmentData);
            
        // Atualiza a lista de equipamentos do PDV
            updatePDVContent(posId);
            
            // Mostra mensagem de sucesso
            showNotification(data.message || 'Equipamento associado com sucesso!', true);
          } else {
            showNotification(data.message || 'Erro ao associar equipamento', false);
            item.style.pointerEvents = '';
            item.style.opacity = '';
            item.classList.remove('disabled');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro ao associar equipamento', false);
        item.style.pointerEvents = '';
        item.style.opacity = '';
        item.classList.remove('disabled');
    });
}
</script>

<style>
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 25px;
    border-radius: 4px;
    color: white;
    z-index: 1000;
    animation: slide-in 0.5s ease;
}

.notification.success {
    background-color: #4CAF50;
}

.notification.error {
    background-color: #f44336;
}

.notification.fade-out {
    animation: fade-out 0.5s ease;
}

@keyframes slide-in {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}

@keyframes fade-out {
    from { opacity: 1; }
    to { opacity: 0; }
}

.equipment-item {
    display: flex;
    flex-direction: column;
    padding: 10px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    transition: background-color 0.2s;
}

.equipment-item:hover {
    background-color: #f5f5f5;
}

.equipment-item.disabled {
    opacity: 0.5;
    pointer-events: none;
}

.loading, .no-equipment, .error {
    padding: 20px;
    text-align: center;
    color: #666;
}

.error {
    color: #dc3545;
}

.equipment-item {
    padding: 10px;
    border: 1px solid #ddd;
    margin-bottom: 5px;
    cursor: pointer;
    border-radius: 4px;
}

.equipment-item strong {
    display: block;
    margin-bottom: 5px;
}

.equipment-item:hover {
    background-color: #f5f5f5;
}

.equipment-list {
    padding: 10px;
    max-height: calc(100vh - 100px);
    overflow-y: auto;
}

.loading {
    text-align: center;
    padding: 20px;
    color: #666;
}

.loading::after {
    content: "...";
    animation: dots 1s steps(5, end) infinite;
}

@keyframes dots {
    0%, 20% { content: "."; }
    40% { content: ".."; }
    60% { content: "..."; }
    80% { content: "...."; }
    100% { content: "....."; }
}

.error {
    color: #dc3545;
    padding: 10px;
    border: 1px solid #dc3545;
    border-radius: 4px;
    margin: 10px 0;
    text-align: center;
}

.disabled {
    opacity: 0.5;
    pointer-events: none;
    cursor: not-allowed;
}
</style>
