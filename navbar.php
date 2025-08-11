<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Não mostrar a navbar nas páginas de autenticação
$auth_pages = ['login.php', 'logout.php', 'reset_password.php'];
if (in_array($current_page, $auth_pages)) {
    return;
}
?>
<style>
.navbar {
    background-color: #333;
    padding: 15px 20px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.navbar-left {
    display: flex;
    align-items: center;
}

.navbar-right {
    display: flex;
    align-items: center;
}

.navbar a {
    color: white;
    text-decoration: none;
    padding: 10px 15px;
    margin-right: 10px;
    border-radius: 4px;
}

.navbar a:hover {
    background-color: #555;
}

.navbar a.active {
    background-color: #4CAF50;
}

.user-info {
    color: white;
    margin-right: 15px;
    font-size: 14px;
}

.sidebar {
    position: fixed;
    right: -300px;
    top: 0;
    width: 300px;
    height: 100%;
    background-color: white;
    box-shadow: -2px 0 5px rgba(0,0,0,0.1);
    transition: right 0.3s ease;
    z-index: 1000;
    padding: 20px;
    overflow-y: auto;
}

.sidebar.active {
    right: 0;
}

.sidebar-toggle {
    position: fixed;
    right: 20px;
    bottom: 20px;
    background-color: #4CAF50;
    color: white;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    z-index: 1001;
}

.sidebar-toggle:hover {
    background-color: #45a049;
}

.sidebar-header {
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.sidebar-close {
    position: absolute;
    right: 20px;
    top: 20px;
    cursor: pointer;
    font-size: 20px;
}
</style>

<div class="navbar">
    <a href="eventos.php" <?php echo $current_page == 'eventos.php' ? 'class="active"' : ''; ?>>
        Eventos
    </a>    <a href="pos_category_assign.php" <?php echo $current_page == 'pos_category_assign.php' ? 'class="active"' : ''; ?>>
        Categorias   </a>
    <a href="equipments.php" <?php echo $current_page == 'equipments.php' ? 'class="active"' : ''; ?>>
        Equipamentos
    </a>
    <a href="internet_cards.php" <?php echo $current_page == 'internet_cards.php' ? 'class="active"' : ''; ?>>
        Internet
    </a>
    <a href="users.php" <?php echo $current_page == 'users.php' ? 'class="active"' : ''; ?>>
        Users
    </a>
    <div style="float: right;">
        <span style="color: white; margin-right: 15px;">
            <?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>
        </span>
        <a href="logout.php" style="margin-right: 0;">
            Sair
        </a>
    </div>
</div>
