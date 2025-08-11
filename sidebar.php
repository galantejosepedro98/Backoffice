<?php
$current_page = basename($_SERVER['PHP_SELF']);
$auth_pages = ['login.php', 'logout.php', 'reset_password.php'];

if (in_array($current_page, $auth_pages)) {
    return;
}
?>

<div class="global-sidebar">
    <div class="sidebar-header">
        <h3>Menu</h3>
        <button class="sidebar-close" onclick="toggleSidebar()">&times;</button>
    </div>
    
    <div class="sidebar-content">
        <nav class="sidebar-nav">
            <a href="eventos.php" class="sidebar-item <?php echo $current_page == 'eventos.php' ? 'active' : ''; ?>">
                Eventos
            </a>
            <a href="pos.php" class="sidebar-item <?php echo $current_page == 'pos.php' ? 'active' : ''; ?>">
                Pontos de Venda
            </a>
            <a href="users.php" class="sidebar-item <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                Users
            </a>
            <a href="equipment.php" class="sidebar-item <?php echo $current_page == 'equipment.php' ? 'active' : ''; ?>">
                Equipamentos
            </a>
        </nav>
    </div>
</div>

<div class="sidebar-toggle" onclick="toggleSidebar()">
    <span>☰</span>
</div>

<style>
.global-sidebar {
    position: fixed;
    left: -280px;
    top: 0;
    width: 280px;
    height: 100%;
    background-color: #2c3e50;
    transition: left 0.3s ease;
    z-index: 1000;
    color: white;
    box-shadow: 2px 0 5px rgba(0,0,0,0.1);
}

.global-sidebar.active {
    left: 0;
}

.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.sidebar-header h3 {
    margin: 0;
    font-size: 1.2em;
}

.sidebar-close {
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
}

.sidebar-content {
    padding: 20px 0;
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
}

.sidebar-item {
    padding: 12px 20px;
    color: white;
    text-decoration: none;
    transition: background-color 0.2s;
}

.sidebar-item:hover {
    background-color: rgba(255,255,255,0.1);
}

.sidebar-item.active {
    background-color: #3498db;
}

.sidebar-toggle {
    position: fixed;
    left: 20px;
    top: 20px;
    background-color: #3498db;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    z-index: 999;
    transition: background-color 0.2s;
}

.sidebar-toggle:hover {
    background-color: #2980b9;
}

@media (max-width: 768px) {
    .global-sidebar {
        width: 100%;
        left: -100%;
    }
    
    .sidebar-toggle {
        width: 35px;
        height: 35px;
        left: 10px;
        top: 10px;
    }
}
</style>

<script>
function toggleSidebar() {
    const sidebar = document.querySelector('.global-sidebar');
    sidebar.classList.toggle('active');
}

// Close sidebar when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.global-sidebar');
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    
    if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target) && sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
    }
});
</script>
