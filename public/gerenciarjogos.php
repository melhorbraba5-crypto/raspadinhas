<?php
// public/gerenciarjogos.php
require_once __DIR__ . '/../config/database.php';
// session_start(); // Remova ou comente essa linha se já estiver sendo iniciada em outro lugar
date_default_timezone_set('America/Sao_Paulo');

$admin_user = $_SESSION['admin_user'] ?? 'Admin';
$page = 'gerenciarjogos';

$feedback = $_SESSION['form_feedback'] ?? null;
unset($_SESSION['form_feedback']);

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Gerenciar Jogos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* [ ... seu CSS completo, sem alterações ... ] */
        :root {
            --bg-dark: #0D1117;
            --bg-light: #161B22;
            --border-color: #30363D;
            --text-primary: #c9d1d9;
            --text-secondary: #8b949e;
            --accent-blue: #58A6FF;
            --accent-green: #3FB950;
            --accent-red: #E24C4C;
            --accent-yellow: #FFC107;
            --card-user-bg: #22364c;
            --card-finance-bg: #2a3a22;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            background-color: var(--bg-dark);
            color: var(--text-primary);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            padding: 2rem;
            min-height: 100vh;
        }

        .sidebar {
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
        }

        .admin-profile {
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1.5rem;
        }

        .admin-profile .icon {
            font-size: 4rem;
            color: var(--accent-blue);
        }

        .admin-profile h3 {
            margin: 0.5rem 0 0.2rem 0;
        }

        .admin-profile p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .sidebar-nav a:hover {
            background-color: #2a3038;
            color: var(--text-primary);
        }

        .sidebar-nav a.active {
            background-color: var(--accent-blue);
            color: #fff;
            font-weight: 600;
        }

        .logout-btn {
            margin-top: auto;
        }

        .logout-btn a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            border: 1px solid var(--text-secondary);
        }

        .logout-btn a:hover {
            background-color: #2a3038;
            color: var(--text-primary);
        }

        .main-content {
            overflow-y: auto;
        }

        h1,
        h2 {
            font-weight: 600;
        }

        .page-header {
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }

        .card {
            background-color: var(--bg-light);
            padding: 2rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        /* Tabela de Gerenciamento */
        .table-container { overflow-x: auto; }
        .table-container table { width: 100%; border-collapse: collapse; }
        .table-container th, .table-container td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .table-container th { background-color: #1e2a3a; font-weight: 600; }
        .table-container tbody tr:hover { background-color: #2a3038; }
        .table-container td form { display: flex; align-items: center; gap: 0.5rem; }
        .table-container td input[type="number"] { width: 100px; text-align: center; padding: 0.4rem; background-color: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); }
        .table-container td button { padding: 0.4rem 0.8rem; font-size: 0.9rem; background-color: var(--accent-blue); color: #fff; border: none; border-radius: 6px; cursor: pointer; white-space: nowrap; }
        .table-container td button.red { background-color: var(--accent-red); }
        .table-container td button.green { background-color: var(--accent-green); }
        .table-container td button:hover { opacity: 0.8; }
        .status-badge { padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .status-badge.active { background-color: rgba(63, 185, 80, 0.2); color: var(--accent-green); }
        .status-badge.inactive { background-color: rgba(226, 76, 76, 0.2); color: var(--accent-red); }

        /* Layout de colunas para o card de bônus */
        .bonus-controls-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .bonus-controls-grid .form-group {
            margin-bottom: 0;
        }

        /* Estilos do formulário */
        .form-container {
            background-color: var(--bg-light);
            padding: 2rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            background-color: var(--bg-dark);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 1rem;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--accent-blue);
        }
        .submit-btn {
            width: 100%;
            padding: 0.8rem;
            background-color: var(--accent-blue);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .submit-btn:hover {
            background-color: #4a90e2;
        }

        /* Estilos de feedback */
        .feedback-box {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
            border: 1px solid transparent;
        }

        .feedback-box.success {
            background-color: rgba(63, 185, 80, 0.1);
            border-color: var(--accent-green);
            color: var(--accent-green);
        }

        .feedback-box.error {
            background-color: rgba(226, 76, 76, 0.1);
            border-color: var(--accent-red);
            color: var(--accent-red);
        }

        /* Media Queries */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
                padding: 1rem;
            }
            .sidebar { order: 2; }
            .main-content { order: 1; }
            h1 { font-size: 1.5rem; }
            .page-header { margin-bottom: 1rem; }
            .card { padding: 1.5rem; }
            .table-container th, .table-container td { padding: 0.8rem; font-size: 0.9rem; }
            .bonus-controls-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 480px) {
            .dashboard-grid { padding: 0.5rem; gap: 0.5rem; }
            .sidebar { padding: 0.75rem; }
            .admin-profile h3 { font-size: 1.2rem; }
            .admin-profile p { font-size: 0.8rem; }
            .card { padding: 1rem; }
            h1 { font-size: 1.3rem; }
            .page-header { padding-bottom: 0.5rem; margin-bottom: 1rem; }
            .table-container th, .table-container td { padding: 0.6rem; font-size: 0.8rem; }
            .table-container td form { flex-direction: column; align-items: flex-start; gap: 0.25rem; }
            .table-container td input[type="number"] { width: 60px; }
            .table-container td button { width: 100%; }
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
                <h1>Gerenciamento de Jogos e Bônus</h1>
                <p class="text-secondary">Controle o sistema de bônus para cada jogo.</p>
            </div>

            <?php if ($feedback): ?>
                <div class="feedback-box <?= htmlspecialchars($feedback['type']) ?>">
                    <strong><?= htmlspecialchars($feedback['message']) ?></strong>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2>Configurações de Jogos</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Jogo</th>
                                <th>Custo da Aposta (R$)</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="games-table-body">
                            </tbody>
                    </table>
                </div>
            </div>

            <div class="card" style="margin-top: 2rem;">
                <h2>Sistema de Bônus</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Jogo</th>
                                <th>Faturamento (R$)</th>
                                <th>Bônus Pago (R$)</th>
                                <th>Meta de Faturamento (R$)</th>
                                <th>Ajustar Meta</th>
                                <th>Bônus a Pagar (R$)</th>
                                <th>Ajustar Bônus</th>
                                <th>Status</th>
                                <th>Ativar/Desativar</th>
                                <th>Zerar Faturamento</th>
                                <th>Zerar Bônus Pago</th>
                            </tr>
                        </thead>
                        <tbody id="bonus-table-body">
                            </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const gamesTableBody = document.getElementById('games-table-body');
            const bonusTableBody = document.getElementById('bonus-table-body');

            const fetchAndRenderData = async () => {
                const activeElement = document.activeElement;
                if (activeElement && activeElement.tagName === 'INPUT' && activeElement.closest('#bonus-table-body')) {
                    return;
                }

                try {
                    const response = await fetch('/get-game-and-bonus-data.php');
                    const result = await response.json();

                    if (result.success) {

                        // <-- ALTERAÇÃO: Adicionada lógica de ordenação
                        // 1. Ordena a lista de jogos pelo custo da aposta (bet_cost)
                        result.data.games.sort((a, b) => parseFloat(a.bet_cost) - parseFloat(b.bet_cost));

                        // 2. Para ordenar a tabela de bônus na mesma ordem, criamos um mapa de custos
                        const costMap = new Map();
                        result.data.games.forEach(game => {
                            costMap.set(game.name, parseFloat(game.bet_cost));
                        });

                        // 3. Ordenamos a lista do sistema de bônus usando o mapa de custos
                        result.data.bonus_systems.sort((a, b) => {
                            const costA = costMap.get(a.game_name) || 0;
                            const costB = costMap.get(b.game_name) || 0;
                            return costA - costB;
                        });
                        // Fim da alteração

                        renderGamesTable(result.data.games);
                        renderBonusTable(result.data.bonus_systems);
                    } else {
                        console.error("Erro na API:", result.error);
                        gamesTableBody.innerHTML = `<tr><td colspan="3" style="text-align: center;">${result.error}</td></tr>`;
                        bonusTableBody.innerHTML = `<tr><td colspan="11" style="text-align: center;">${result.error}</td></tr>`;
                    }
                } catch (error) {
                    console.error("Erro ao buscar dados:", error);
                    gamesTableBody.innerHTML = `<tr><td colspan="3" style="text-align: center;">Erro de conexão.</td></tr>`;
                    bonusTableBody.innerHTML = `<tr><td colspan="11" style="text-align: center;">Erro de conexão.</td></tr>`;
                }
            };

            const renderGamesTable = (games) => {
                let html = '';
                if (games && games.length > 0) {
                    games.forEach(game => {
                        html += `
                            <tr>
                                <td>${game.name}</td>
                                <td>R$ ${parseFloat(game.bet_cost).toFixed(2).replace('.', ',')}</td>
                                <td></td>
                            </tr>
                        `;
                    });
                } else {
                    html = `<tr><td colspan="3" style="text-align: center;">Nenhum jogo encontrado.</td></tr>`;
                }
                gamesTableBody.innerHTML = html;
            };

            const renderBonusTable = (bonus_systems) => {
                let html = '';
                if (bonus_systems && bonus_systems.length > 0) {
                    bonus_systems.forEach(bonus => {
                        const statusClass = bonus.is_bonus_active ? 'status-badge active' : 'status-badge inactive';
                        const statusText = bonus.is_bonus_active ? 'Ativo' : 'Inativo';

                        html += `
                            <tr>
                                <td>${bonus.game_name}</td>
                                <td>R$ ${parseFloat(bonus.current_faturamento).toFixed(2).replace('.', ',')}</td>
                                <td>R$ ${parseFloat(bonus.current_bonus_paid).toFixed(2).replace('.', ',')}</td>
                                <td>R$ ${parseFloat(bonus.faturamento_meta).toFixed(2).replace('.', ',')}</td>
                                <td>
                                    <form method="POST" action="update_bonus_settings.php">
                                        <input type="hidden" name="game_name" value="${bonus.game_name}">
                                        <input type="number" name="faturamento_meta" value="${parseFloat(bonus.faturamento_meta).toFixed(2)}" step="0.01" min="0">
                                        <button type="submit">Atualizar</button>
                                    </form>
                                </td>
                                <td>R$ ${parseFloat(bonus.bonus_amount).toFixed(2).replace('.', ',')}</td>
                                <td>
                                    <form method="POST" action="update_bonus_settings.php">
                                        <input type="hidden" name="game_name" value="${bonus.game_name}">
                                        <input type="number" name="bonus_amount" value="${parseFloat(bonus.bonus_amount).toFixed(2)}" step="0.01" min="0">
                                        <button type="submit">Atualizar</button>
                                    </form>
                                </td>
                                <td>
                                    <span class="${statusClass}">${statusText}</span>
                                </td>
                                <td>
                                    <form method="POST" action="toggle_bonus_status.php">
                                        <input type="hidden" name="game_name" value="${bonus.game_name}">
                                        <button type="submit" class="${bonus.is_bonus_active ? 'red' : 'green'}">
                                            ${bonus.is_bonus_active ? 'Desativar' : 'Ativar'}
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="reset_faturamento.php" onsubmit="return confirm('Tem certeza que deseja zerar o faturamento deste jogo? Esta ação não pode ser desfeita.');">
                                        <input type="hidden" name="game_name" value="${bonus.game_name}">
                                        <button type="submit" class="red">Zerar</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="reset_bonus.php" onsubmit="return confirm('Tem certeza que deseja zerar o bônus pago deste jogo? Esta ação não pode ser desfeita.');">
                                        <input type="hidden" name="game_name" value="${bonus.game_name}">
                                        <button type="submit" class="red">Zerar</button>
                                    </form>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = `<tr><td colspan="11" style="text-align: center;">Nenhum sistema de bônus configurado.</td></tr>`;
                }
                bonusTableBody.innerHTML = html;
            };

            fetchAndRenderData();

            setInterval(fetchAndRenderData, 3000);
        });
    </script>
</body>
</html>