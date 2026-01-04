<?php
// public/contasdemo.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

// A sessão já deve ter sido iniciada pelo index.php, então o PHP Notice não ocorrerá.
$page = 'contasdemo';

$admin_user = $_SESSION['admin_user'] ?? 'Admin';

// Verifica se há alguma mensagem de feedback na sessão
$feedback = $_SESSION['form_feedback'] ?? null;
unset($_SESSION['form_feedback']);

// Lógica para paginação
$users_per_page = 10;
$current_page = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT) ?? 1;
if ($current_page <= 0) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $users_per_page;

// Lógica para buscar as contas demo
try {
    global $pdo;

    // Consulta para o total de contas demo
    $total_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_demo = TRUE");
    $total_stmt->execute();
    $total_users = $total_stmt->fetchColumn();
    $total_pages = ceil($total_users / $users_per_page);

    // Consulta para buscar os usuários da página atual
    $demo_users_stmt = $pdo->prepare("SELECT id, name, email, saldo, created_at, demo_win_rate, is_blocked FROM users WHERE is_demo = TRUE ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $demo_users_stmt->bindValue(1, $users_per_page, PDO::PARAM_INT);
    $demo_users_stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $demo_users_stmt->execute();
    $demo_users = $demo_users_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $feedback = [
        'type' => 'error',
        'message' => 'Erro ao buscar contas de demonstração: ' . $e->getMessage()
    ];
    $demo_users = [];
    $total_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Gerenciar Contas Demo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS GERAL DA DASHBOARD PARA SEGUIR O PADRÃO */
        :root {
            --bg-dark: #0D1117;
            --bg-light: #161B22;
            --border-color: #30363D;
            --text-primary: #c9d1d9;
            --text-secondary: #8b949e;
            --accent-blue: #58A6FF;
            --accent-green: #3FB950;
            --accent-red: #E24C4C;
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-dark); color: var(--text-primary); margin:0; }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            padding: 2rem;
            min-height: 100vh;
        }
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

        /* CSS ESPECÍFICO DESTA PÁGINA (CONTAS DEMO) */
        .main-content { overflow-y: auto; }
        .page-header { border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 2rem; }

        .card { background-color: var(--bg-light); padding: 2rem; border-radius: 10px; border: 1px solid var(--border-color); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-secondary); }
        .form-group input { width: 100%; padding: 0.75rem; background-color: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: var(--accent-blue); }
        .submit-btn { width: 100%; padding: 0.8rem; background-color: var(--accent-blue); color: #fff; border: none; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background-color 0.2s; }
        .submit-btn:hover { background-color: #4a90e2; }

        .feedback-box { padding: 1rem; margin-bottom: 1.5rem; border-radius: 6px; border: 1px solid transparent; }
        .feedback-box.success { background-color: rgba(63, 185, 80, 0.1); border-color: var(--accent-green); color: var(--accent-green); }
        .feedback-box.error { background-color: rgba(226, 76, 76, 0.1); border-color: var(--accent-red); color: var(--accent-red); }
        .feedback-box p { margin: 0.2rem 0; }
        .feedback-box small { opacity: 0.8; }

        .table-container { overflow-x: auto; }
        .table-container table { width: 100%; border-collapse: collapse; background-color: var(--bg-light); }
        .table-container th, .table-container td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        .table-container th { background-color: #1e2a3a; font-weight: 600; }
        .table-container tbody tr:hover { background-color: #2a3038; }
        .table-container td form { display: flex; align-items: center; gap: 0.5rem; }
        .table-container td input[type="number"] { width: 80px; text-align: center; }
        .table-container td button { padding: 0.4rem 0.8rem; font-size: 0.9rem; }

        /* Estilos de paginação */
        .pagination { display: flex; justify-content: center; padding: 1rem 0; list-style: none; }
        .pagination .page-item a { color: var(--text-primary); text-decoration: none; padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; margin: 0 0.25rem; transition: background-color 0.2s; }
        .pagination .page-item a:hover { background-color: #2a3038; }
        .pagination .page-item a.active { background-color: var(--accent-blue); color: #fff; border-color: var(--accent-blue); }

        /* Novo CSS para o layout de duas colunas */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .create-account-card {
            max-width: 450px;
            max-height: 499px;
        }

        /* ---------------------------------------------------- */
        /* RESPONSIVIDADE - AJUSTES MEDIA QUERIES */
        /* ---------------------------------------------------- */

        /* Para telas de tablets e desktops menores (até 992px) */
        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
                padding: 1.5rem;
                gap: 1.5rem;
            }
            .sidebar {
                order: 2; /* Move a sidebar para o final */
            }
            .main-content {
                order: 1; /* Move o conteúdo principal para o topo */
            }
            .content-grid {
                grid-template-columns: 1fr; /* Muda para uma única coluna */
                gap: 1.5rem;
            }
            .form-container, .card {
                padding: 1.5rem;
            }
        }

        /* Para telas de celulares grandes e tablets pequenos (até 768px) */
        @media (max-width: 768px) {
            .dashboard-grid {
                padding: 1rem;
                gap: 1rem;
            }
            .sidebar-nav a, .logout-btn a {
                padding: 0.65rem 0.8rem;
                font-size: 0.9rem;
            }
            h1 {
                font-size: 1.5rem;
            }
            .page-header {
                margin-bottom: 1.5rem;
            }
            .card {
                padding: 1.2rem;
            }
            .table-container th, .table-container td {
                padding: 0.8rem;
                font-size: 0.9rem;
            }
            .table-container td form {
                flex-direction: column;
                gap: 0.25rem;
                align-items: flex-start;
            }
            .table-container td input[type="number"] {
                width: 60px;
            }
            .table-container td button {
                width: 100%;
            }
        }

        /* Para telas de celulares pequenos (até 480px) */
        @media (max-width: 480px) {
            .dashboard-grid {
                padding: 0.5rem;
                gap: 0.5rem;
            }
            .sidebar, .card {
                padding: 1rem;
            }
            h1 {
                font-size: 1.3rem;
            }
            .page-header {
                padding-bottom: 0.5rem;
                margin-bottom: 1rem;
            }
            .table-container th, .table-container td {
                padding: 0.6rem;
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
                <h1>Gerenciamento de Contas Demo</h1>
                <p class="text-secondary">Crie novas contas de demonstração e gerencie as existentes.</p>
            </div>

            <?php if ($feedback): ?>
                <div class="feedback-box <?= htmlspecialchars($feedback['type']) ?>">
                    <strong><?= htmlspecialchars($feedback['message']) ?></strong>
                    <?php if (isset($feedback['account_details'])): ?>
                        <hr style="border-color: rgba(255,255,255,0.1); margin: 0.8rem 0;">
                        <p><strong>Email:</strong> <?= htmlspecialchars($feedback['account_details']['email']) ?></p>
                        <p><strong>Senha:</strong> <?= htmlspecialchars($feedback['account_details']['password']) ?></p>
                        <p><small>Anote a senha, ela não será exibida novamente.</small></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="content-grid">
                <div class="card create-account-card">
                    <h2>Criar Nova Conta</h2>
                    <form id="demo-account-form" method="POST" action="create_demo_account.php">
                        <div class="form-group">
                            <label for="name">Nome do Usuário</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="win_rate">Porcentagem de Ganho (%)</label>
                            <input type="number" id="win_rate" name="win_rate" step="0.1" min="0" max="100" required>
                        </div>
                        <button type="submit" class="submit-btn">Gerar Conta Demo</button>
                    </form>
                </div>

                <div class="card">
                    <h2>Contas Demo Existentes</h2>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Saldo</th>
                                    <th>Criado em</th>
                                    <th>Taxa de Ganho (%)</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($demo_users)): ?>
                                    <?php foreach ($demo_users as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['name']) ?></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td>R$ <?= number_format($user['saldo'], 2, ',', '.') ?></td>
                                            <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                            <td>
                                                <form method="POST" action="manage_demo_account.php">
                                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                                    <input type="hidden" name="action" value="update_win_rate">
                                                    <input type="number" name="new_win_rate" value="<?= htmlspecialchars($user['demo_win_rate']) ?>" step="0.1" min="0" max="100" style="width: 80px;">
                                                    %
                                                    <button type="submit" class="submit-btn" style="width: auto; padding: 0.4rem 0.8rem;">Atualizar</button>
                                                </form>
                                            </td>
                                            <td>
                                                <span class="status-indicator" style="color: <?= $user['is_blocked'] ? 'var(--accent-red)' : 'var(--accent-green)' ?>;">
                                                    <?= $user['is_blocked'] ? 'Bloqueado' : 'Ativo' ?>
                                                </span>
                                            </td>
                                            <td style="display: flex; flex-direction: column; gap: 0.5rem;">
                                                <form method="POST" action="manage_demo_account.php">
                                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                                    <input type="hidden" name="action" value="change_password">
                                                    <div style="display:flex; gap: 0.5rem; align-items: center;">
                                                      <input type="text" name="new_password" placeholder="Nova Senha" required style="width: 100px;">
                                                      <button type="submit" class="submit-btn" style="width: auto; padding: 0.4rem 0.8rem; background-color: var(--accent-blue);">
                                                          <i class="bi bi-key-fill"></i>
                                                      </button>
                                                    </div>
                                                </form>

                                                <form method="POST" action="manage_demo_account.php">
                                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                                    <input type="hidden" name="action" value="toggle_block">
                                                    <input type="hidden" name="is_blocked" value="<?= $user['is_blocked'] ? 'false' : 'true' ?>">
                                                    <button type="submit" class="submit-btn" style="width: auto; padding: 0.4rem 0.8rem; background-color: <?= $user['is_blocked'] ? 'var(--accent-green)' : 'var(--accent-red)' ?>;">
                                                        <i class="bi bi-person-fill-lock"></i> <?= $user['is_blocked'] ? 'Desbloquear' : 'Bloquear' ?>
                                                    </button>
                                                </form>

                                                <form method="POST" action="manage_demo_account.php" onsubmit="return confirm('Tem certeza que deseja excluir esta conta demo? Esta ação é irreversível.');">
                                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                                    <input type="hidden" name="action" value="delete_account">
                                                    <button type="submit" class="submit-btn" style="width: auto; background-color: var(--accent-red); padding: 0.4rem 0.8rem;">
                                                        <i class="bi bi-trash-fill"></i> Excluir
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: var(--text-secondary);">Nenhuma conta de demonstração encontrada.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Navegação de página">
                            <ul class="pagination">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item">
                                        <a class="page-link <?= ($i == $current_page) ? 'active' : '' ?>" href="?page=contasdemo&p=<?= $i ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>

            <div style="height: 2rem;"></div>

        </main>
    </div>
</body>
</html>