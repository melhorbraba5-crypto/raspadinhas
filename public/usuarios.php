<?php
// Este arquivo é incluído por index.php.
// index.php já incluiu auth_check.php e database.php,
// então $pdo e $_SESSION['admin_user'] já estão disponíveis.

// Definir o fuso horário para São Paulo (GMT-3)
date_default_timezone_set('America/Sao_Paulo');

$admin_user = $_SESSION['admin_user'] ?? 'Admin';
$page = 'usuarios'; // Define a página atual para o menu de navegação lateral

// --- Lógica de Busca e Paginação ---
$search_term = $_GET['q'] ?? ''; // Pega o termo de busca da URL, se existir

// A consulta e paginação são condicionais. Se houver um termo de busca, a lógica muda.
if (!empty($search_term)) {
    // Se há um termo de busca, ignora a paginação e busca em todo o banco de dados
    $search_param = '%' . $search_term . '%'; // Adiciona curingas para buscar qualquer ocorrência
    $sql = "SELECT id, name, email, saldo, created_at FROM users WHERE name LIKE :search OR email LIKE :search ORDER BY created_at DESC";
    $search_stmt = $pdo->prepare($sql);
    $search_stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    $search_stmt->execute();
    $users = $search_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Variáveis de paginação são zeradas ou ajustadas para não serem exibidas
    $total_users = count($users);
    $users_per_page = 0; // Desabilita a contagem
    $total_pages = 0;
    $current_page = 1;
} else {
    // Se não há termo de busca, usa a lógica de paginação normal
    $users_per_page = 30; // Define o número de usuários por página
    $current_page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
    $offset = ($current_page - 1) * $users_per_page;

    // Contar o número total de usuários para a paginação
    $total_users_stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = $total_users_stmt->fetchColumn();
    $total_pages = ceil($total_users / $users_per_page);

    // Lógica PHP para buscar os usuários paginados
    $recent_users_stmt = $pdo->prepare("SELECT id, name, email, saldo, created_at FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $recent_users_stmt->bindParam(':limit', $users_per_page, PDO::PARAM_INT);
    $recent_users_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $recent_users_stmt->execute();
    $users = $recent_users_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$has_results = !empty($users);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Usuários</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Estilos base (mantidos para consistência se não houver um common.css) */
        :root {
            --bg-dark: #0D1117; --bg-light: #161B22; --border-color: #30363D;
            --text-primary: #c9d1d9; --text-secondary: #8b949e;
            --accent-blue: #58A6FF; --accent-green: #3FB950;
            --card-bg: #1e2a3a;
            --card-border: #30363D;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; margin: 0; background-color: var(--bg-dark); color: var(--text-primary); }
        .dashboard-grid { display: grid; grid-template-columns: 280px 1fr; gap: 2rem; padding: 2rem; min-height: 100vh; }
        .sidebar { background-color: var(--bg-light); border: 1px solid var(--border-color); border-radius: 10px; padding: 1.5rem; display: flex; flex-direction: column; }
        .admin-profile { text-align: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem; }
        .admin-profile .icon { font-size: 4rem; color: var(--accent-blue); }
        .admin-profile h3 { margin: 0.5rem 0 0.2rem 0; }
        .admin-profile p { margin: 0; font-size: 0.9rem; color: var(--text-secondary); }
        .sidebar-nav { list-style: none; padding: 0; margin: 1.5rem 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; color: var(--text-secondary); text-decoration: none; padding: 0.75rem 1rem; border-radius: 6px; font-weight: 500; transition: all 0.2s; }
        .sidebar-nav a:hover { background-color: #2a3038; color: var(--text-primary); }
        .sidebar-nav a.active { background-color: var(--accent-blue); color: #fff; font-weight: 600; }
        .logout-btn { margin-top: auto; }
        .logout-btn a { display: flex; align-items: center; gap: 0.75rem; color: var(--text-secondary); text-decoration: none; padding: 0.75rem 1rem; border-radius: 6px; font-weight: 500; border: 1px solid var(--text-secondary); }
        .logout-btn a:hover { background-color: #2a3038; color: var(--text-primary); }
        .main-content { overflow-y: auto; }
        h1, h2 { font-weight: 600; }
        .page-header { border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 2rem; }

        /* Estilos Específicos para a Página de Usuários */
        .search-container {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
        }
        #search-form {
            display: flex;
            flex-grow: 1;
            gap: 1rem;
        }
        #search-input {
            flex-grow: 1;
            padding: 12px;
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        #search-input::placeholder {
            color: var(--text-secondary);
        }
        #search-input:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.2);
        }
        .search-btn {
            padding: 12px 20px;
            background-color: var(--accent-blue);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.2s, transform 0.1s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        .search-btn:hover {
            background-color: #4a90e2;
            transform: translateY(-1px);
        }
        .add-user-btn {
            padding: 12px 20px;
            background-color: var(--accent-green);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.2s, transform 0.1s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        .add-user-btn:hover {
            background-color: #359f44;
            transform: translateY(-1px);
        }
        .add-user-btn:active {
            transform: translateY(0);
        }

        .user-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .user-card {
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.2rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            transition: transform 0.2s, border-color 0.2s, box-shadow 0.2s;
            text-decoration: none;
            color: inherit;
        }
        .user-card:hover {
            transform: translateY(-3px);
            border-color: var(--accent-blue);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        .user-card-content {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 0.5rem;
        }
        .user-card-content .user-icon {
            font-size: 1.5rem;
            color: var(--accent-blue);
        }
        .user-card-details {
            display: flex;
            flex-direction: column;
            text-align: left;
            overflow-x: hidden;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .user-card-details h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--accent-blue);
            font-weight: 600;
        }
        .user-card-details p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .user-card-date {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            align-self: flex-start;
        }

        .user-card-balance, .user-card-actions {
            display: none;
        }

        /* Placeholder para mensagem de nenhum usuário encontrado */
        .no-users-found {
            text-align: center;
            color: var(--text-secondary);
            padding: 2rem;
            border: 1px dashed var(--border-color);
            border-radius: 8px;
            margin-top: 2rem;
        }
        .no-users-found i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--accent-blue);
        }

        /* Estilos para a paginação */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        .pagination a {
            text-decoration: none;
            color: var(--text-secondary);
            padding: 0.6rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            transition: all 0.2s;
            font-weight: 500;
        }
        .pagination a:hover {
            background-color: #2a3038;
            color: var(--text-primary);
        }
        .pagination a.active {
            background-color: var(--accent-blue);
            color: #fff;
            border-color: var(--accent-blue);
        }
        .pagination a.disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        .pagination-info {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* ---------------------------------------------------- */
        /* RESPONSIVIDADE */
        /* ---------------------------------------------------- */
        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1.5rem;
            }
            .sidebar {
                order: 2;
            }
            .main-content {
                order: 1;
            }
            .user-cards-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 1rem;
            }
            .search-container {
                flex-direction: column;
                gap: 0.75rem;
            }
            .add-user-btn, .search-btn {
                width: 100%;
                justify-content: center;
            }
            #search-form {
                flex-direction: column;
                gap: 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .sidebar-nav a, .logout-btn a {
                padding: 0.65rem 0.8rem;
                font-size: 0.9rem;
            }
            h1 {
                font-size: 1.5rem;
            }
            .page-header {
                margin-bottom: 1rem;
            }
            .user-card {
                padding: 1rem;
            }
            .user-card-details h3 {
                font-size: 1rem;
            }
            .user-card-details p {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .dashboard-grid {
                padding: 0.5rem;
                gap: 0.5rem;
            }
            .sidebar, .user-card, #search-input, .add-user-btn, .search-btn {
                padding: 0.75rem;
            }
            .admin-profile h3 {
                font-size: 1.2rem;
            }
            .admin-profile p {
                font-size: 0.8rem;
            }
            .pagination a {
                padding: 0.5rem 0.8rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-grid">
        <aside class="sidebar">
            <div class="admin-profile">
                <i class="bi bi-person-circle icon"></i>
                <h3><?= htmlspecialchars($admin_user) ?></h3>
                <p>Administrador</p>
            </div>
            <ul class="sidebar-nav">
                <li><a href="index.php?page=dashboard" class="<?= ($page === 'dashboard') ? 'active' : '' ?>"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
                <li><a href="index.php?page=usuarios" class="<?= ($page === 'usuarios') ? 'active' : '' ?>"><i class="bi bi-people-fill"></i> Usuários</a></li>
                <li><a href="index.php?page=financeiro" class="<?= ($page === 'financeiro') ? 'active' : '' ?>"><i class="bi bi-wallet2"></i> Financeiro</a></li>
                <li><a href="index.php?page=relatorios" class="<?= ($page === 'relatorios') ? 'active' : '' ?>"><i class="bi bi-file-earmark-bar-graph-fill"></i> Relatórios</a></li>
                <li><a href="index.php?page=contasdemo" class="<?= ($page === 'contasdemo') ? 'active' : '' ?>"><i class="bi bi-joystick"></i> Contas Demo</a></li>
                <li><a href="index.php?page=gerenciarjogos" class="<?= ($page === 'gerenciarjogos') ? 'active' : '' ?>"><i class="bi bi-gear-fill"></i> Gerenciar Jogos</a></li>
                <li><a href="index.php?page=gerenciarpagamentos" class="<?= ($page === 'gerenciarpagamentos') ? 'active' : '' ?>"><i class="bi bi-credit-card-fill"></i> Métodos de Pagamento</a></li>
            </ul>
            <div class="logout-btn">
                <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Sair da Conta</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>Gerenciamento de Usuários</h1>
            </div>

            <div class="search-container">
                <form id="search-form" action="index.php" method="GET">
                    <input type="hidden" name="page" value="usuarios">
                    <input type="text" name="q" id="search-input" placeholder="Buscar por nome ou email..." value="<?= htmlspecialchars($search_term) ?>">
                    <button type="submit" class="search-btn"><i class="bi bi-search"></i> Buscar</button>
                    <?php if (!empty($search_term)): ?>
                        <a href="index.php?page=usuarios" class="search-btn" style="background-color: var(--text-secondary);"><i class="bi bi-x-circle"></i> Limpar</a>
                    <?php endif; ?>
                </form>
                <a href="add_user.php" class="add-user-btn">
                    <i class="bi bi-plus-circle"></i> Adicionar Novo Usuário
                </a>
            </div>

            <div class="user-cards-grid">
                <?php if ($has_results): ?>
                    <?php foreach ($users as $user):
                        $registered_date = date('d/m/Y', strtotime($user['created_at']));
                    ?>
                        <a href="user_details.php?id=<?= $user['id'] ?>" class="user-card">
                            <div class="user-card-content">
                                <i class="bi bi-person-circle user-icon"></i>
                                <div class="user-card-details">
                                    <h3><?= htmlspecialchars($user['name']) ?></h3>
                                    <p><?= htmlspecialchars($user['email']) ?></p>
                                </div>
                            </div>
                            <span class="user-card-date">Membro desde: <?= $registered_date ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-users-found">
                        <i class="bi bi-people-fill"></i>
                        <p>Nenhum usuário encontrado <?= !empty($search_term) ? "para o termo de busca \"**" . htmlspecialchars($search_term) . "**\"." : "." ?></p>
                        <p><?= !empty($search_term) ? "Tente um termo diferente ou " : "" ?>Comece adicionando novos usuários!</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($search_term) && $total_pages > 1): ?>
                <div class="pagination">
                    <a href="index.php?page=usuarios&p=<?= max(1, $current_page - 1) ?>" class="<?= ($current_page <= 1) ? 'disabled' : '' ?>"><i class="bi bi-arrow-left"></i> Anterior</a>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="index.php?page=usuarios&p=<?= $i ?>" class="<?= ($i == $current_page) ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <a href="index.php?page=usuarios&p=<?= min($total_pages, $current_page + 1) ?>" class="<?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">Próximo <i class="bi bi-arrow-right"></i></a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>