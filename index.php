<?php
/**
 * index.php
 * Interfaz Web / PWA Móvil de Control de Gastos y Consumo de Vehículos.
 * Conectada a la API REST (`api.php`) y base de datos de Moratalla-Murcia-2026.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Control de Combustible y Gastos - Vehículos</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16x16.png">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link rel="manifest" href="site.webmanifest">
    <meta name="theme-color" content="#0f172a">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Librerías para generación de Informes PDF en el móvil y gráficos -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <style>
        :root {
            --primary: #0f766e;
            --primary-dark: #115e59;
            --primary-light: #ccfbf1;
            --accent: #f59e0b;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --danger: #dc2626;
            --success: #16a34a;
            --radius: 14px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding-bottom: 85px;
        }
        /* Login Screen */
        #login-screen {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            background: linear-gradient(135deg, #0f172a 0%, #0f766e 100%);
        }
        .login-card {
            background: var(--card);
            width: 100%;
            max-width: 410px;
            padding: 2.2rem 1.8rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.28);
        }
        .login-header {
            text-align: center;
            margin-bottom: 1.6rem;
        }
        .login-icon {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: var(--primary-light);
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 0.8rem;
        }
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 0.35rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 0.9rem;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-control:focus { border-color: var(--primary); }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.1rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.92rem;
            cursor: pointer;
            transition: all 0.18s;
            font-family: inherit;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-accent { background: var(--accent); color: #fff; }
        .btn-outline { background: #fff; border: 1.5px solid var(--border); color: var(--text); }
        .btn-danger { background: #fee2e2; color: var(--danger); }
        .btn-block { width: 100%; }

        /* App Header */
        .app-header {
            background: #0f172a;
            color: #fff;
            padding: 0.9rem 1.2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
            gap: 0.75rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-weight: 800;
            font-size: 1.05rem;
        }
        .brand-badge {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .vehicle-bar {
            max-width: 1200px;
            margin: 0.75rem auto 0;
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .vehicle-select {
            flex: 1;
            min-width: 200px;
            padding: 0.55rem 0.8rem;
            border-radius: 9px;
            border: 1px solid #334155;
            background: #1e293b;
            color: #fff;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .container {
            max-width: 1200px;
            margin: 1.2rem auto;
            padding: 0 1rem;
        }

        /* KPI Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(165px, 1fr));
            gap: 0.85rem;
            margin-bottom: 1.25rem;
        }
        .kpi-card {
            background: var(--card);
            padding: 1rem;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: 0 2px 6px rgba(15,23,42,0.03);
        }
        .kpi-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--muted);
            letter-spacing: 0.4px;
            margin-bottom: 0.35rem;
        }
        .kpi-value {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text);
        }
        .kpi-sub {
            font-size: 0.76rem;
            color: var(--muted);
            margin-top: 0.25rem;
        }

        /* Section Cards */
        .card {
            background: var(--card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 1.2rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 2px 6px rgba(15,23,42,0.03);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .card-title {
            font-size: 1.02rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Table & Scrub List */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 10px;
            border: 1px solid var(--border);
            max-height: 68vh;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.81rem;
        }
        th, td {
            padding: 0.55rem 0.5rem;
            text-align: right;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        th:first-child, td:first-child,
        th:nth-child(2), td:nth-child(2) {
            text-align: left;
        }
        thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #0f172a;
            color: #f8fafc;
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #f0fdfa; }
        .badge {
            display: inline-block;
            padding: 0.18rem 0.48rem;
            border-radius: 6px;
            font-size: 0.73rem;
            font-weight: 700;
        }
        .badge-teal { background: var(--primary-light); color: var(--primary-dark); }
        .badge-amber { background: #fef3c7; color: #b45309; }

        /* Vista Tarjetas Responsive para Móvil */
        .mobile-cards-container {
            display: none;
            flex-direction: column;
            gap: 0.65rem;
        }
        .rep-mobile-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.85rem;
            background: #fff;
            box-shadow: 0 1px 4px rgba(15,23,42,0.04);
        }
        .rep-mobile-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.45rem;
        }
        .rep-mobile-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.4rem;
            background: #f8fafc;
            padding: 0.55rem;
            border-radius: 8px;
            font-size: 0.76rem;
            margin-top: 0.45rem;
        }
        .rep-mobile-grid div span {
            display: block;
            color: var(--muted);
            font-size: 0.68rem;
        }
        .rep-mobile-grid div strong {
            color: var(--text);
            font-size: 0.8rem;
        }

        /* Modals */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,0.65);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            z-index: 200;
            overflow-y: auto;
        }
        .modal-backdrop.active { display: flex; }
        .modal-content {
            background: var(--card);
            width: 100%;
            max-width: 540px;
            border-radius: 18px;
            padding: 1.4rem;
            max-height: 92vh;
            overflow-y: auto;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        .ocr-bar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem;
            margin-bottom: 1rem;
            background: #f8fafc;
            padding: 0.75rem;
            border-radius: 12px;
            border: 1px dashed #cbd5e1;
        }
        .fab {
            position: fixed;
            right: 1.25rem;
            bottom: 1.25rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 999px;
            padding: 0.95rem 1.35rem;
            font-size: 0.98rem;
            font-weight: 700;
            box-shadow: 0 10px 25px rgba(15,118,110,0.4);
            display: flex;
            align-items: center;
            gap: 0.55rem;
            cursor: pointer;
            z-index: 150;
        }
        .hidden { display: none !important; }
        @media (max-width: 768px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 0.6rem; }
            .kpi-card { padding: 0.8rem; }
            .kpi-value { font-size: 1.15rem; }
            .header-top { flex-wrap: wrap; }
            .vehicle-bar input[type="date"] { flex: 1; min-width: 130px; }
            .table-responsive.auto-hide-mobile { display: none; }
            .mobile-cards-container { display: flex; }
        }
        @media (max-width: 600px) {
            .grid-2 { grid-template-columns: 1fr; }
            .ocr-bar { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- PANTALLA DE LOGIN -->
<div id="login-screen">
    <div class="login-card">
        <div class="login-header">
            <img src="assets/icons/icon-192.png" alt="Logo Control de Combustible" style="width: 84px; height: 84px; border-radius: 18px; margin-bottom: 0.8rem; box-shadow: 0 6px 18px rgba(15,118,110,0.28);">
            <h1 style="font-size: 1.35rem; font-weight: 800;">Control de Combustible</h1>
            <p style="font-size: 0.85rem; color: var(--muted); margin-top: 0.3rem;">Acceso con tu cuenta de moratalla-murcia.com</p>
        </div>
        <div id="login-error" class="hidden" style="background:#fee2e2; color:#b91c1c; padding:0.75rem; border-radius:10px; font-size:0.85rem; margin-bottom:1rem; text-align:center;"></div>
        <form id="login-form">
            <div class="form-group">
                <label>Usuario (moratalla-murcia.com)</label>
                <input type="text" id="login-user" class="form-control" required autocomplete="username">
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" id="login-pass" class="form-control" required autocomplete="current-password">
            </div>
            <!-- Verificación Anti-Robots: Suma sencilla de 2 dígitos de una cifra -->
            <div style="background:#f8fafc; padding:0.75rem 0.9rem; border-radius:10px; border:1px dashed #cbd5e1; margin-bottom:1rem;">
                <label style="display:block; font-size:0.78rem; font-weight:700; color:var(--primary); margin-bottom:0.45rem;">
                    <i class="fa-solid fa-shield-halved"></i> Verificación de Seguridad (Suma) *
                </label>
                <div style="display:flex; align-items:center; justify-content:space-between; gap:0.65rem;">
                    <div id="captcha-question" style="background:#fff; border:1px solid var(--border); border-radius:8px; padding:0.5rem 0.85rem; font-weight:800; font-size:1rem; color:var(--primary-dark); user-select:none;">
                        3 + 4 = ?
                    </div>
                    <input type="number" id="login-captcha" class="form-control" required placeholder="Resultado" style="max-width:130px; text-align:center; font-weight:700;" autocomplete="off">
                    <button type="button" class="btn btn-outline" style="padding:0.5rem 0.65rem;" onclick="generateCaptcha()" title="Nueva suma">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 0.5rem; padding: 0.9rem;">
                <i class="fa-solid fa-right-to-bracket"></i> Iniciar Sesión
            </button>
            <a href="https://github.com/pablosalinas/ControlCombustible/releases/latest/download/ControlCombustible.apk" target="_blank" class="btn btn-outline btn-block" style="margin-top: 0.65rem; text-decoration: none; font-size: 0.85rem;">
                <i class="fa-brands fa-android" style="color:#16a34a; font-size:1.1rem;"></i> Descargar App Android (.APK)
            </a>
            <div style="text-align: center; margin-top: 1rem;">
                <a href="https://www.moratalla-murcia.com" target="_blank" style="color: var(--primary); text-decoration: none; font-size: 0.84rem; font-weight: 600;">
                    <i class="fa-solid fa-globe"></i> Ir a moratalla-murcia.com
                </a>
            </div>
        </form>
    </div>
</div>

<!-- APLICACIÓN PRINCIPAL -->
<div id="app-screen" class="hidden">
    <header class="app-header">
        <div class="header-top">
            <div class="brand">
                <span class="brand-badge" id="brand-icon-box" style="overflow:hidden; padding:0; background:transparent;">
                    <img src="assets/icons/icon-192.png" alt="Icono" style="width:36px; height:36px; border-radius:10px; display:block;">
                </span>
                <div>
                    <div id="app-title-text">Control de Combustible</div>
                    <div style="font-size: 0.72rem; color: #94a3b8; font-weight: 500;" id="user-badge-text">Conectado</div>
                </div>
            </div>
            <div style="display: flex; gap: 0.45rem; flex-wrap: wrap;">
                <button class="btn btn-outline" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="openImportModal()">
                    <i class="fa-solid fa-file-import" style="color:var(--primary)"></i> Importar Excel/CSV
                </button>
                <button class="btn btn-outline" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="openEstacionesModal()">
                    <i class="fa-solid fa-shop" style="color:var(--accent)"></i> Estaciones
                </button>
                <button class="btn btn-outline" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="openVehiclesModal()">
                    <i class="fa-solid fa-car"></i> Vehículos
                </button>
                <button class="btn btn-accent" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="openPdfOptionsModal()">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
                <button class="btn btn-danger" style="padding: 0.45rem 0.7rem; font-size: 0.8rem;" onclick="logout()" title="Cerrar sesión">
                    <i class="fa-solid fa-power-off"></i>
                </button>
            </div>
        </div>
        <div class="vehicle-bar">
            <select id="vehicle-selector" class="vehicle-select" onchange="loadDashboard()">
                <option value="0">📊 Todos los vehículos (Resultado Conjunto)</option>
            </select>
            <input type="date" id="filter-from" class="form-control" style="width: auto; padding: 0.45rem 0.6rem; font-size: 0.82rem; background:#1e293b; color:#fff; border-color:#334155;" onchange="loadDashboard()" title="Desde fecha">
            <input type="date" id="filter-to" class="form-control" style="width: auto; padding: 0.45rem 0.6rem; font-size: 0.82rem; background:#1e293b; color:#fff; border-color:#334155;" onchange="loadDashboard()" title="Hasta fecha">
        </div>
    </header>

    <main class="container">
        <!-- INDICADORES GLOBALES (HOJA DE CÁLCULO) -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Consumo Medio</div>
                <div class="kpi-value" id="kpi-consumo-medio">0,00 <small style="font-size:0.75rem">L/100</small></div>
                <div class="kpi-sub" id="kpi-coste-100">Coste: 0,00 €/100km</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Consumo Máx / Mín</div>
                <div class="kpi-value" style="font-size:1.1rem;" id="kpi-consumo-max-min">0,00 / 0,00</div>
                <div class="kpi-sub" id="kpi-consumo-dif">Diferencia: 0,00 L/100</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Precio Máx / Mín</div>
                <div class="kpi-value" style="font-size:1.1rem;" id="kpi-precio-max-min">0,000 / 0,000 €</div>
                <div class="kpi-sub" id="kpi-precio-dif">Diferencia: 0,000 €/L</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Combustible</div>
                <div class="kpi-value" id="kpi-total-litros">0,00 L</div>
                <div class="kpi-sub" id="kpi-num-repostajes">0 repostajes</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Gasto Acumulado</div>
                <div class="kpi-value" id="kpi-total-euros">0,00 €</div>
                <div class="kpi-sub" id="kpi-euros-km">0,000 €/km</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Km Recorridos</div>
                <div class="kpi-value" id="kpi-total-km">0 km</div>
                <div class="kpi-sub" id="kpi-odometro-actual">Odómetro: 0 km</div>
            </div>
        </div>

        <!-- GRÁFICO DE EVOLUCIÓN -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-chart-line" style="color:var(--primary)"></i> Evolución de Consumo (L/100km) y Precio (€/L)</div>
            </div>
            <div style="height: 230px; position: relative;">
                <canvas id="evolutionChart"></canvas>
            </div>
        </div>

        <!-- TABLA SCRUB / ABM DE REPOSTAJES -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-table-list" style="color:var(--primary)"></i>
                    Histórico de Repostajes y Cálculos Encadenados
                </div>
                <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                    <input type="text" id="search-input" class="form-control" placeholder="Buscar fecha, km, nota..." style="max-width:200px; padding:0.45rem 0.75rem; font-size:0.84rem;" oninput="renderTable()">
                    <button class="btn btn-outline" id="btn-toggle-view" style="padding:0.45rem 0.75rem; font-size:0.8rem;" onclick="toggleViewMode()" title="Alternar vista Tarjetas / Tabla">
                        <i class="fa-solid fa-grip"></i> Vista
                    </button>
                    <button class="btn btn-primary" style="padding:0.48rem 0.85rem; font-size:0.84rem;" onclick="openRepostajeModal()">
                        <i class="fa-solid fa-plus"></i> Nuevo
                    </button>
                </div>
            </div>
            <div class="table-responsive auto-hide-mobile" id="repostajes-table-wrap">
                <table id="repostajes-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Vehículo</th>
                            <th>Litros</th>
                            <th>Precio</th>
                            <th>Importe</th>
                            <th>KM.</th>
                            <th>DIF. Km</th>
                            <th>LTS.x.100</th>
                            <th>KM.x.LTS</th>
                            <th>€ x 1KM.</th>
                            <th>T.Litros</th>
                            <th>Total €</th>
                            <th>Media €</th>
                            <th>Notas</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="repostajes-tbody"></tbody>
                </table>
            </div>
            <!-- Vista Tarjetas Responsive para Móvil o Pantalla Compacta -->
            <div class="mobile-cards-container" id="repostajes-mobile-cards"></div>
        </div>
    </main>

    <button class="fab" onclick="openRepostajeModal()">
        <i class="fa-solid fa-gas-pump"></i> Nuevo Repostaje
    </button>
</div>

<!-- MODAL ALTA / MODIFICACIÓN DE REPOSTAJE CON OCR -->
<div class="modal-backdrop" id="repostaje-modal">
    <div class="modal-content">
        <div class="card-header" style="margin-bottom:0.8rem;">
            <div class="card-title" id="rep-modal-title"><i class="fa-solid fa-gas-pump" style="color:var(--primary)"></i> Registrar Repostaje</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closeRepostajeModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- Barra de Escaneo OCR (Ticket y Cuentakilómetros) -->
        <div class="ocr-bar">
            <label class="btn btn-outline" style="font-size:0.8rem; cursor:pointer;">
                <i class="fa-solid fa-receipt" style="color:var(--primary)"></i> Escanear Ticket (OCR)
                <input type="file" accept="image/*" capture="environment" class="hidden" onchange="scanTicketOCR(this)">
            </label>
            <label class="btn btn-outline" style="font-size:0.8rem; cursor:pointer;">
                <i class="fa-solid fa-gauge-high" style="color:var(--accent)"></i> Foto Odómetro (OCR)
                <input type="file" accept="image/*" capture="environment" class="hidden" onchange="scanOdometerOCR(this)">
            </label>
        </div>
        <div id="ocr-status" class="hidden" style="font-size:0.8rem; padding:0.55rem 0.75rem; border-radius:8px; background:#e0f2fe; color:#0369a1; margin-bottom:0.85rem;"></div>

        <form id="repostaje-form">
            <input type="hidden" id="rep-id" value="0">
            <div class="grid-2">
                <div class="form-group">
                    <label>Vehículo *</label>
                    <select id="rep-vehiculo" class="form-control" required onchange="updateParcialKmPreview()"></select>
                </div>
                <div class="form-group">
                    <label id="label-fecha-form">Fecha *</label>
                    <input type="date" id="rep-fecha" class="form-control" required>
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Kilómetros (Odómetro) *</label>
                    <input type="number" step="0.01" id="rep-km" class="form-control" placeholder="Ej: 183200" required oninput="updateParcialKmPreview()">
                    <div id="km-parcial-preview" style="font-size:0.76rem; margin-top:0.3rem; font-weight:600; color:var(--primary-dark);"></div>
                </div>
                <div class="form-group">
                    <label>Litros Repostados *</label>
                    <input type="number" step="0.001" id="rep-litros" class="form-control" placeholder="Ej: 52.12" required oninput="autoCalcForm('litros')">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Precio / Litro (€) *</label>
                    <input type="number" step="0.0001" id="rep-precio" class="form-control" placeholder="Ej: 1.439" required oninput="autoCalcForm('precio')">
                </div>
                <div class="form-group">
                    <label>Importe Total (€) *</label>
                    <input type="number" step="0.01" id="rep-importe" class="form-control" placeholder="Ej: 75.00" required oninput="autoCalcForm('importe')">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Estación de Servicio (opcional)</label>
                    <div style="display:flex; gap:0.35rem;">
                        <select id="rep-estacion" class="form-control">
                            <option value="">-- Sin especificar (Opcional) --</option>
                        </select>
                        <button type="button" class="btn btn-outline" style="padding:0.45rem 0.65rem;" onclick="openEstacionesModal()" title="Gestionar Estaciones">
                            <i class="fa-solid fa-gear"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Notas / Mantenimiento</label>
                    <input type="text" id="rep-notas" class="form-control" placeholder="Ej: Revisión o incidencia">
                </div>
            </div>
            <div style="display:flex; gap:0.65rem; justify-content:flex-end; margin-top:0.8rem;">
                <button type="button" class="btn btn-outline" onclick="closeRepostajeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar Repostaje</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL GESTIÓN DE VEHÍCULOS -->
<div class="modal-backdrop" id="vehicles-modal">
    <div class="modal-content">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-car" style="color:var(--primary)"></i> Mis Vehículos</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closeVehiclesModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="vehicles-list" style="margin-bottom:1.2rem;"></div>
        <hr style="border:none; border-top:1px solid var(--border); margin:1rem 0;">
        <h4 style="font-size:0.92rem; margin-bottom:0.75rem;" id="veh-form-title">Añadir / Editar Vehículo</h4>
        <form id="vehicle-form">
            <input type="hidden" id="veh-id" value="0">
            <div class="grid-2">
                <div class="form-group">
                    <label>Nombre / Alias *</label>
                    <input type="text" id="veh-nombre" class="form-control" placeholder="Ej: Peugeot 308" required>
                </div>
                <div class="form-group">
                    <label>Matrícula</label>
                    <input type="text" id="veh-matricula" class="form-control" placeholder="Ej: 1234 HJK">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Marca y Modelo</label>
                    <input type="text" id="veh-modelo" class="form-control" placeholder="Ej: TDI 115CV">
                </div>
                <div class="form-group">
                    <label>Combustible</label>
                    <select id="veh-combustible" class="form-control">
                        <option value="Diésel">Diésel</option>
                        <option value="Gasolina 95">Gasolina 95</option>
                        <option value="Gasolina 98">Gasolina 98</option>
                        <option value="Híbrido">Híbrido</option>
                        <option value="GLP">GLP</option>
                    </select>
                </div>
            </div>
            <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="resetVehicleForm()">Limpiar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Guardar Vehículo</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL GESTIÓN (SCRUB) DE ESTACIONES DE SERVICIO -->
<div class="modal-backdrop" id="estaciones-modal">
    <div class="modal-content">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-shop" style="color:var(--accent)"></i> Estaciones de Servicio</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closeEstacionesModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="estaciones-list" style="margin-bottom:1.1rem; max-height:220px; overflow-y:auto;"></div>
        <hr style="border:none; border-top:1px solid var(--border); margin:0.9rem 0;">
        <h4 style="font-size:0.92rem; margin-bottom:0.75rem;" id="est-form-title">Añadir / Editar Estación de Servicio</h4>
        <form id="estacion-form">
            <input type="hidden" id="est-id" value="0">
            <div class="grid-2">
                <div class="form-group">
                    <label>Nombre Estación *</label>
                    <input type="text" id="est-nombre" class="form-control" placeholder="Ej: Repsol Moratalla" required>
                </div>
                <div class="form-group">
                    <label>Marca / Bandera</label>
                    <input type="text" id="est-marca" class="form-control" placeholder="Ej: Repsol, Cepsa, Ballenoil">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Localidad</label>
                    <input type="text" id="est-localidad" class="form-control" placeholder="Ej: Moratalla">
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" id="est-direccion" class="form-control" placeholder="Opcional">
                </div>
            </div>
            <div class="form-group">
                <label>Notas</label>
                <input type="text" id="est-notas" class="form-control" placeholder="Ej: Descuento tarjeta, horario 24h...">
            </div>
            <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="resetEstacionForm()">Limpiar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Guardar Estación</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CONFIGURACIÓN DE INFORME PDF (FILTRO + ORDEN POR FECHA) -->
<div class="modal-backdrop" id="pdf-options-modal">
    <div class="modal-content" style="max-width: 460px;">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-file-pdf" style="color:var(--accent)"></i> Configurar Informe PDF (A4 Vertical)</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closePdfOptionsModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label>Desde Fecha</label>
                <input type="date" id="pdf-from" class="form-control" onchange="updatePdfPreviewCount()">
            </div>
            <div class="form-group">
                <label>Hasta Fecha</label>
                <input type="date" id="pdf-to" class="form-control" onchange="updatePdfPreviewCount()">
            </div>
        </div>
        <div class="form-group">
            <label>Filtro de texto (vehículo, estación, km, nota...)</label>
            <input type="text" id="pdf-search" class="form-control" placeholder="Opcional (toma el buscador activo)" oninput="updatePdfPreviewCount()">
        </div>
        <div class="form-group">
            <label>Ordenación por Fecha *</label>
            <select id="pdf-sort-order" class="form-control">
                <option value="desc">⬇️ Descendente (Más recientes primero)</option>
                <option value="asc">⬆️ Ascendente (Más antiguos primero)</option>
            </select>
        </div>
        <div id="pdf-count-info" style="font-size:0.82rem; font-weight:600; color:var(--primary-dark); background:#f0fdfa; padding:0.55rem 0.75rem; border-radius:8px; margin-bottom:1rem;"></div>
        <div style="display:flex; justify-content:flex-end; gap:0.6rem;">
            <button type="button" class="btn btn-outline" onclick="closePdfOptionsModal()">Cancelar</button>
            <button type="button" class="btn btn-accent" onclick="exportPDFReport()">
                <i class="fa-solid fa-file-pdf"></i> Generar PDF
            </button>
        </div>
    </div>
</div>

<!-- MODAL DE IMPORTACIÓN EXCEL / CSV CONFIGURABLE POR VEHÍCULO -->
<div class="modal-backdrop" id="import-modal">
    <div class="modal-content" style="max-width: 720px;">
        <div class="card-header" style="margin-bottom:0.8rem;">
            <div class="card-title"><i class="fa-solid fa-file-excel" style="color:#16a34a;"></i> Importar Excel (.xlsx / .xls) o CSV por Vehículo</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closeImportModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- Guía de Estructura de Columnas y Plantilla -->
        <div style="background:#f8fafc; border:1px solid var(--border); border-radius:12px; padding:0.9rem; margin-bottom:1rem; font-size:0.81rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.45rem;">
                <strong style="color:var(--primary-dark);"><i class="fa-solid fa-circle-info"></i> ¿Cómo deben estructurarse las columnas?</strong>
                <button type="button" class="btn btn-outline" style="padding:0.25rem 0.6rem; font-size:0.75rem;" onclick="downloadTemplateCSV()">
                    <i class="fa-solid fa-download"></i> Descargar Plantilla CSV
                </button>
            </div>
            <p style="color:var(--muted); margin-bottom:0.4rem;">
                Por defecto se reconoce automáticamente la estructura original de tu hoja:
                <code>Col A: FECHA</code> · <code>Col B: LITROS</code> · <code>Col C: PRECIO</code> · <code>Col D: IMPORTE</code> · <code>Col E: KM.</code> · <code>Notas (opcional)</code>.
            </p>
            <p style="color:var(--muted);">
                <strong>¿Tienes otro Excel/CSV distinto?</strong> Al elegir el archivo se mostrarán todas sus columnas para que asignes libremente cuál corresponde a cada dato. Los campos calculados (consumos, diferencias, acumulados) los genera el sistema automáticamente.
            </p>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>1. Vehículo destino de los datos *</label>
                <select id="import-vehiculo" class="form-control" required></select>
            </div>
            <div class="form-group">
                <label>2. Seleccionar archivo Excel (.xlsx, .xls) o .CSV *</label>
                <input type="file" id="import-file" accept=".xlsx,.xls,.csv,.txt" class="form-control" onchange="handleImportFile(this)">
            </div>
        </div>

        <div style="margin-bottom:0.85rem;">
            <label style="font-size:0.82rem; display:inline-flex; align-items:center; gap:0.45rem; cursor:pointer; font-weight:600;">
                <input type="checkbox" id="import-has-header" checked onchange="reparseImportSheet()">
                La primera fila del archivo contiene los títulos/encabezados de columna
            </label>
        </div>

        <!-- Configurador de Mapeo de Columnas -->
        <div id="import-mapping-section" class="hidden" style="border-top:1px solid var(--border); padding-top:0.9rem;">
            <h4 style="font-size:0.88rem; margin-bottom:0.65rem; color:var(--text);">3. Configurar correspondencia de columnas de tu archivo:</h4>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:0.65rem; margin-bottom:0.9rem;">
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:var(--muted);">Columna FECHA *</label>
                    <select id="map-fecha" class="form-control" style="padding:0.45rem;" onchange="renderImportPreview()"></select>
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:var(--muted);">Columna LITROS *</label>
                    <select id="map-litros" class="form-control" style="padding:0.45rem;" onchange="renderImportPreview()"></select>
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:var(--muted);">Columna PRECIO (€/L)</label>
                    <select id="map-precio" class="form-control" style="padding:0.45rem;" onchange="renderImportPreview()"></select>
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:var(--muted);">Columna IMPORTE (€)</label>
                    <select id="map-importe" class="form-control" style="padding:0.45rem;" onchange="renderImportPreview()"></select>
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:var(--muted);">Columna KM. (Odómetro) *</label>
                    <select id="map-km" class="form-control" style="padding:0.45rem;" onchange="renderImportPreview()"></select>
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:var(--muted);">Columna NOTAS (Opcional)</label>
                    <select id="map-notas" class="form-control" style="padding:0.45rem;" onchange="renderImportPreview()"></select>
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:var(--muted);">Estación (Opcional)</label>
                    <select id="map-estacion" class="form-control" style="padding:0.45rem;" onchange="renderImportPreview()"></select>
                </div>
            </div>

            <div id="import-preview-info" style="font-size:0.8rem; font-weight:600; color:var(--primary-dark); margin-bottom:0.45rem;"></div>
            <div class="table-responsive" style="max-height:190px; margin-bottom:1rem;">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Litros</th>
                            <th>Precio €/L</th>
                            <th>Importe €</th>
                            <th>KM.</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody id="import-preview-tbody"></tbody>
                </table>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.6rem;">
                <button type="button" class="btn btn-outline" onclick="closeImportModal()">Cancelar</button>
                <button type="button" id="btn-run-import" class="btn btn-primary" onclick="executeCustomImport()">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Confirmar e Importar al Vehículo
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let authCreds = null;
let currentData = { vehiculos: [], repostajes: [], resumen: {}, user: {} };
let chartInstance = null;
let captchaExpected = 7;
let rawImportedRows = [];

function generateCaptcha() {
    const a = Math.floor(Math.random() * 9) + 1;
    const b = Math.floor(Math.random() * 9) + 1;
    captchaExpected = a + b;
    const qEl = document.getElementById('captcha-question');
    if (qEl) qEl.textContent = `${a} + ${b} = ?`;
    const inEl = document.getElementById('login-captcha');
    if (inEl) inEl.value = '';
}
generateCaptcha();

function fmtNum(val, dec = 2) {
    if (val === null || val === undefined || isNaN(val)) return '-';
    return Number(val).toLocaleString('es-ES', { minimumFractionDigits: dec, maximumFractionDigits: dec });
}

async function apiCall(action, payload = {}, method = 'POST') {
    const headers = { 'Content-Type': 'application/json' };
    if (authCreds && authCreds.username && authCreds.password) {
        headers['Authorization'] = 'Basic ' + btoa(authCreds.username + ':' + authCreds.password);
    }
    const url = 'api.php?action=' + encodeURIComponent(action);
    const bodyObj = (authCreds && authCreds.username)
        ? { ...payload, username: authCreds.username, password: authCreds.password }
        : { ...payload };
    const res = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: method === 'POST' ? JSON.stringify(bodyObj) : undefined
    });
    return await res.json();
}

document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const userCaptcha = parseInt(document.getElementById('login-captcha').value, 10);
    if (isNaN(userCaptcha) || userCaptcha !== captchaExpected) {
        const err = document.getElementById('login-error');
        err.textContent = 'La suma de verificación anti-robots no es correcta. Inténtalo de nuevo.';
        err.classList.remove('hidden');
        generateCaptcha();
        return;
    }

    const username = document.getElementById('login-user').value.trim();
    const password = document.getElementById('login-pass').value;
    authCreds = { username, password };
    try {
        const res = await apiCall('login');
        if (res.success) {
            sessionStorage.setItem('veh_auth', JSON.stringify(authCreds));
            document.getElementById('login-screen').classList.add('hidden');
            document.getElementById('app-screen').classList.remove('hidden');
            await loadDashboard();
        } else {
            const err = document.getElementById('login-error');
            err.textContent = res.error || 'Error de autenticación';
            err.classList.remove('hidden');
            generateCaptcha();
        }
    } catch (ex) {
        alert('Error conectando con el servidor: ' + ex.message);
    }
});

function logout() {
    sessionStorage.removeItem('veh_auth');
    authCreds = null;
    document.getElementById('app-screen').classList.add('hidden');
    document.getElementById('login-screen').classList.remove('hidden');
}

async function loadDashboard() {
    const sel = document.getElementById('vehicle-selector');
    const vehiculoId = sel ? parseInt(sel.value || '0', 10) : 0;
    const fechaDesde = document.getElementById('filter-from').value;
    const fechaHasta = document.getElementById('filter-to').value;

    const res = await apiCall('dashboard', { vehiculo_id: vehiculoId, fecha_desde: fechaDesde, fecha_hasta: fechaHasta });
    if (!res.success) return;

    currentData = res;
    document.getElementById('user-badge-text').textContent =
        res.user.username + (res.user.is_admin ? ' · Acceso Global (Todos los vehículos)' : '');

    // Marca visual discreta cuando está activo el alcance global de administrador
    if (res.user.is_admin) {
        document.getElementById('app-title-text').textContent = 'Control de Combustible.';
        document.getElementById('label-fecha-form').textContent = 'Fecha. *';
        document.getElementById('brand-icon-box').style.border = '1.5px solid #f59e0b';
    } else {
        document.getElementById('app-title-text').textContent = 'Control de Combustible';
        document.getElementById('label-fecha-form').textContent = 'Fecha *';
        document.getElementById('brand-icon-box').style.border = 'none';
    }

    // Poblar selector de vehículos manteniendo selección en el dashboard
    sel.innerHTML = '<option value="0">📊 Todos los vehículos (Resultado Conjunto)</option>';
    const formVeh = document.getElementById('rep-vehiculo');
    formVeh.innerHTML = '';
    if (res.vehiculos.length > 1) {
        const emptyOpt = document.createElement('option');
        emptyOpt.value = '';
        emptyOpt.textContent = '-- Selecciona un vehículo --';
        formVeh.appendChild(emptyOpt);
    }
    res.vehiculos.forEach(v => {
        const mat = v.matricula ? ` (${v.matricula})` : '';
        const opt = document.createElement('option');
        opt.value = v.id;
        opt.textContent = `🚗 ${v.nombre}${mat}`;
        if (parseInt(v.id, 10) === vehiculoId) opt.selected = true;
        sel.appendChild(opt);

        const opt2 = document.createElement('option');
        opt2.value = v.id;
        opt2.textContent = `${v.nombre}${mat}`;
        formVeh.appendChild(opt2);
    });

    // Poblar desplegable de estaciones de servicio
    const estSelEl = document.getElementById('rep-estacion');
    if (estSelEl) {
        const prevEst = estSelEl.value;
        estSelEl.innerHTML = '<option value="">-- Sin especificar (Opcional) --</option>';
        (res.estaciones || []).forEach(est => {
            const o = document.createElement('option');
            o.value = est.nombre;
            o.textContent = est.nombre + (est.localidad ? ` (${est.localidad})` : '');
            estSelEl.appendChild(o);
        });
        if (prevEst) estSelEl.value = prevEst;
    }

    // Actualizar KPIs
    const s = res.resumen || {};
    document.getElementById('kpi-consumo-medio').innerHTML = `${fmtNum(s.consumo_medio_l100, 2)} <small style="font-size:0.75rem">L/100</small>`;
    document.getElementById('kpi-coste-100').textContent = `Coste medio: ${fmtNum(s.coste_medio_100km, 2)} €/100km`;
    document.getElementById('kpi-consumo-max-min').textContent = `${fmtNum(s.consumo_maximo, 2)} / ${fmtNum(s.consumo_minimo, 2)}`;
    document.getElementById('kpi-consumo-dif').textContent = `Diferencia: ${fmtNum(s.diferencia_consumo, 2)} L/100`;
    document.getElementById('kpi-precio-max-min').textContent = `${fmtNum(s.precio_maximo, 3)} / ${fmtNum(s.precio_minimo, 3)} €`;
    document.getElementById('kpi-precio-dif').textContent = `Diferencia: ${fmtNum(s.diferencia_precio, 3)} €/L`;
    document.getElementById('kpi-total-litros').textContent = `${fmtNum(s.total_litros, 2)} L`;
    document.getElementById('kpi-num-repostajes').textContent = `${s.num_repostajes || 0} repostajes registrados`;
    document.getElementById('kpi-total-euros').textContent = `${fmtNum(s.total_euros, 2)} €`;
    document.getElementById('kpi-euros-km').textContent = `${fmtNum(s.euros_por_km_medio, 4)} €/km`;
    document.getElementById('kpi-total-km').textContent = `${fmtNum(s.total_km_recorridos, 0)} km`;
    document.getElementById('kpi-odometro-actual').textContent = s.km_actual ? `Odómetro: ${fmtNum(s.km_actual, 0)} km` : 'Flota combinada';

    renderTable();
    renderChart();
}

let forceCardsView = false;
function toggleViewMode() {
    forceCardsView = !forceCardsView;
    const tableWrap = document.getElementById('repostajes-table-wrap');
    const cardsWrap = document.getElementById('repostajes-mobile-cards');
    if (forceCardsView) {
        tableWrap.style.display = 'none';
        cardsWrap.style.display = 'flex';
    } else {
        tableWrap.style.display = '';
        cardsWrap.style.display = '';
    }
}

function renderTable() {
    const q = (document.getElementById('search-input').value || '').toLowerCase().trim();
    const tbody = document.getElementById('repostajes-tbody');
    const cardsContainer = document.getElementById('repostajes-mobile-cards');
    tbody.innerHTML = '';
    cardsContainer.innerHTML = '';

    const filtered = (currentData.repostajes || []).filter(r => {
        if (!q) return true;
        return (r.fecha && r.fecha.toLowerCase().includes(q)) ||
               (String(r.km_odometro).includes(q)) ||
               (r.notas && r.notas.toLowerCase().includes(q)) ||
               (r.vehiculo_nombre && r.vehiculo_nombre.toLowerCase().includes(q));
    });

    filtered.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="font-weight:600;">${r.fecha}</td>
            <td><span class="badge badge-teal">${r.vehiculo_nombre || 'Vehículo'}</span></td>
            <td>${fmtNum(r.litros, 2)}</td>
            <td>${fmtNum(r.precio_litro, 3)} €</td>
            <td style="font-weight:700; color:var(--primary-dark);">${fmtNum(r.importe_total, 2)} €</td>
            <td style="font-weight:600;">${fmtNum(r.km_odometro, 0)}</td>
            <td>${r.dif_km > 0 ? '+' + fmtNum(r.dif_km, 0) : '-'}</td>
            <td>${r.litros_por_100 !== null ? `<span class="badge badge-amber">${fmtNum(r.litros_por_100, 2)}</span>` : '-'}</td>
            <td>${r.km_por_litro !== null ? fmtNum(r.km_por_litro, 2) : '-'}</td>
            <td>${r.euros_por_km !== null ? fmtNum(r.euros_por_km, 4) + ' €' : '-'}</td>
            <td>${fmtNum(r.total_litros_acum, 1)}</td>
            <td>${fmtNum(r.total_euros_acum, 2)} €</td>
            <td>${r.coste_medio_100_acum !== null ? fmtNum(r.coste_medio_100_acum, 2) : '-'}</td>
            <td style="text-align:left; max-width:160px; overflow:hidden; text-overflow:ellipsis;">${r.notas || ''}</td>
            <td>
                <button class="btn btn-outline" style="padding:0.25rem 0.5rem; font-size:0.75rem;" onclick='editRepostaje(${JSON.stringify(r)})' title="Modificar"><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-danger" style="padding:0.25rem 0.5rem; font-size:0.75rem;" onclick="deleteRepostaje(${r.id})" title="Baja lógica"><i class="fa-solid fa-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);

        const card = document.createElement('div');
        card.className = 'rep-mobile-card';
        card.innerHTML = `
            <div class="rep-mobile-top">
                <div>
                    <strong style="font-size:0.92rem;">${r.fecha}</strong>
                    <span class="badge badge-teal" style="margin-left:0.35rem;">${r.vehiculo_nombre || 'Vehículo'}</span>
                </div>
                <div style="font-weight:800; font-size:1.05rem; color:var(--primary-dark);">${fmtNum(r.importe_total, 2)} €</div>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.82rem; color:var(--muted);">
                <span><strong>${fmtNum(r.km_odometro, 0)} km</strong> (${r.dif_km > 0 ? '+' + fmtNum(r.dif_km, 0) + ' km' : 'Inicio'})</span>
                <span>${fmtNum(r.litros, 2)} L × ${fmtNum(r.precio_litro, 3)} €/L</span>
            </div>
            <div class="rep-mobile-grid">
                <div><span>Consumo L/100</span><strong>${r.litros_por_100 !== null ? fmtNum(r.litros_por_100, 2) : '-'}</strong></div>
                <div><span>Km / Litro</span><strong>${r.km_por_litro !== null ? fmtNum(r.km_por_litro, 2) : '-'}</strong></div>
                <div><span>Coste / Km</span><strong>${r.euros_por_km !== null ? fmtNum(r.euros_por_km, 4) + ' €' : '-'}</strong></div>
                <div><span>Acum. Litros</span><strong>${fmtNum(r.total_litros_acum, 1)} L</strong></div>
                <div><span>Acum. Gasto</span><strong>${fmtNum(r.total_euros_acum, 2)} €</strong></div>
                <div><span>Media €/100</span><strong>${r.coste_medio_100_acum !== null ? fmtNum(r.coste_medio_100_acum, 2) + ' €' : '-'}</strong></div>
            </div>
            ${r.notas ? `<div style="margin-top:0.45rem; font-size:0.78rem; color:#92400e; background:#fef3c7; padding:0.3rem 0.55rem; border-radius:6px;"><i class="fa-solid fa-screwdriver-wrench"></i> ${r.notas}</div>` : ''}
            <div style="display:flex; justify-content:flex-end; gap:0.4rem; margin-top:0.55rem;">
                <button class="btn btn-outline" style="padding:0.3rem 0.65rem; font-size:0.76rem;" onclick='editRepostaje(${JSON.stringify(r)})'><i class="fa-solid fa-pen"></i> Editar</button>
                <button class="btn btn-danger" style="padding:0.3rem 0.65rem; font-size:0.76rem;" onclick="deleteRepostaje(${r.id})"><i class="fa-solid fa-trash"></i> Baja</button>
            </div>
        `;
        cardsContainer.appendChild(card);
    });
}

function renderChart() {
    const ctx = document.getElementById('evolutionChart').getContext('2d');
    const chronological = [...(currentData.repostajes || [])].reverse().slice(-40);
    const labels = chronological.map(r => r.fecha);
    const dataConsumo = chronological.map(r => r.litros_por_100);
    const dataPrecio = chronological.map(r => r.precio_litro);

    if (chartInstance) chartInstance.destroy();
    chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Consumo (L/100km)',
                    data: dataConsumo,
                    borderColor: '#0f766e',
                    backgroundColor: 'rgba(15,118,110,0.1)',
                    tension: 0.3,
                    yAxisID: 'y'
                },
                {
                    label: 'Precio (€/L)',
                    data: dataPrecio,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.1)',
                    tension: 0.3,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { type: 'linear', position: 'left', title: { display: true, text: 'L/100km' } },
                y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: '€/L' } }
            }
        }
    });
}

function autoCalcForm(changedField) {
    const l = parseFloat(document.getElementById('rep-litros').value);
    const p = parseFloat(document.getElementById('rep-precio').value);
    const i = parseFloat(document.getElementById('rep-importe').value);

    if (changedField === 'litros' || changedField === 'precio') {
        if (l > 0 && p > 0) {
            document.getElementById('rep-importe').value = (l * p).toFixed(2);
        }
    } else if (changedField === 'importe') {
        if (i > 0 && p > 0) {
            document.getElementById('rep-litros').value = (i / p).toFixed(2);
        }
    }
}

let editingPrevKm = null;

function getPreviousKmForVehicle(vehId, editingId = 0) {
    if (!vehId) return null;
    if (editingId > 0) {
        const list = (currentData.repostajes || [])
            .filter(r => parseInt(r.vehiculo_id, 10) === parseInt(vehId, 10) && parseInt(r.id, 10) !== parseInt(editingId, 10));
        const currentKm = parseFloat(document.getElementById('rep-km').value || '0');
        const prevs = list.filter(r => parseFloat(r.km_odometro) < currentKm);
        if (prevs.length > 0) {
            return Math.max(...prevs.map(r => parseFloat(r.km_odometro)));
        }
        return null;
    }
    const map = currentData.ultimo_km_por_vehiculo || {};
    if (map[vehId] !== undefined) return parseFloat(map[vehId]);
    const vehReps = (currentData.repostajes || []).filter(r => parseInt(r.vehiculo_id, 10) === parseInt(vehId, 10));
    if (vehReps.length > 0) return Math.max(...vehReps.map(r => parseFloat(r.km_odometro)));
    return null;
}

function updateParcialKmPreview() {
    const previewEl = document.getElementById('km-parcial-preview');
    if (!previewEl) return;
    const vehId = parseInt(document.getElementById('rep-vehiculo').value || '0', 10);
    const editId = parseInt(document.getElementById('rep-id').value || '0', 10);
    const kmVal = parseFloat(document.getElementById('rep-km').value);

    if (!vehId) {
        previewEl.textContent = 'Selecciona un vehículo para ver su lectura anterior.';
        previewEl.style.color = 'var(--muted)';
        return;
    }
    const prevKm = (editId > 0 && editingPrevKm !== null) ? editingPrevKm : getPreviousKmForVehicle(vehId, editId);
    if (prevKm === null || isNaN(prevKm)) {
        previewEl.textContent = 'Primer repostaje registrado para este vehículo.';
        previewEl.style.color = 'var(--muted)';
        return;
    }
    if (isNaN(kmVal) || kmVal <= 0) {
        previewEl.textContent = `Lectura anterior: ${fmtNum(prevKm, 0)} km`;
        previewEl.style.color = 'var(--muted)';
        return;
    }
    const parcial = kmVal - prevKm;
    if (parcial < 0) {
        previewEl.textContent = `⚠️ Menor que la lectura anterior (${fmtNum(prevKm, 0)} km): ${fmtNum(parcial, 0)} km`;
        previewEl.style.color = 'var(--danger)';
    } else {
        previewEl.textContent = `✓ Lectura anterior: ${fmtNum(prevKm, 0)} km → Parcial calculado: +${fmtNum(parcial, 1)} km`;
        previewEl.style.color = 'var(--primary-dark)';
    }
}

function openRepostajeModal() {
    editingPrevKm = null;
    document.getElementById('rep-id').value = '0';
    document.getElementById('rep-modal-title').innerHTML = '<i class="fa-solid fa-gas-pump" style="color:var(--primary)"></i> Registrar Repostaje';
    const vehSel = document.getElementById('rep-vehiculo');
    const vehs = currentData.vehiculos || [];
    if (vehs.length === 1) {
        vehSel.value = String(vehs[0].id);
    } else {
        // Si hay más de un vehículo, no poner ninguno por defecto
        vehSel.value = '';
    }
    document.getElementById('rep-fecha').value = new Date().toISOString().slice(0, 10);
    document.getElementById('rep-km').value = '';
    document.getElementById('rep-litros').value = '';
    document.getElementById('rep-precio').value = '';
    document.getElementById('rep-importe').value = '';
    document.getElementById('rep-estacion').value = '';
    document.getElementById('rep-notas').value = '';
    updateParcialKmPreview();
    document.getElementById('repostaje-modal').classList.add('active');
}

function editRepostaje(r) {
    editingPrevKm = r.dif_km > 0 ? (parseFloat(r.km_odometro) - parseFloat(r.dif_km)) : null;
    document.getElementById('rep-id').value = r.id;
    document.getElementById('rep-modal-title').innerHTML = '<i class="fa-solid fa-pen" style="color:var(--accent)"></i> Modificar / Reasignar Repostaje';
    document.getElementById('rep-vehiculo').value = r.vehiculo_id;
    document.getElementById('rep-fecha').value = r.fecha;
    document.getElementById('rep-km').value = r.km_odometro;
    document.getElementById('rep-litros').value = r.litros;
    document.getElementById('rep-precio').value = r.precio_litro;
    document.getElementById('rep-importe').value = r.importe_total;
    const estSel = document.getElementById('rep-estacion');
    const estVal = r.estacion_servicio || '';
    if (estVal && !Array.from(estSel.options).some(o => o.value === estVal)) {
        const tmpOpt = document.createElement('option');
        tmpOpt.value = estVal;
        tmpOpt.textContent = estVal;
        estSel.appendChild(tmpOpt);
    }
    estSel.value = estVal;
    document.getElementById('rep-notas').value = r.notas || '';
    updateParcialKmPreview();
    document.getElementById('repostaje-modal').classList.add('active');
}

function closeRepostajeModal() {
    document.getElementById('repostaje-modal').classList.remove('active');
}

document.getElementById('repostaje-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        id: document.getElementById('rep-id').value,
        vehiculo_id: document.getElementById('rep-vehiculo').value,
        fecha: document.getElementById('rep-fecha').value,
        km_odometro: document.getElementById('rep-km').value,
        litros: document.getElementById('rep-litros').value,
        precio_litro: document.getElementById('rep-precio').value,
        importe_total: document.getElementById('rep-importe').value,
        estacion_servicio: document.getElementById('rep-estacion').value,
        notas: document.getElementById('rep-notas').value
    };
    const res = await apiCall('save_repostaje', payload);
    if (res.success) {
        closeRepostajeModal();
        await loadDashboard();
    } else {
        alert(res.error || 'Error guardando repostaje');
    }
});

async function deleteRepostaje(id) {
    if (!confirm('¿Dar de baja este repostaje? (Se marcará como inactivo sin destruir el registro en la base de datos)')) return;
    const res = await apiCall('deactivate_repostaje', { id });
    if (res.success) await loadDashboard();
}

// ESCANEO OCR DE TICKET Y CUENTAKILÓMETROS
async function scanTicketOCR(input) {
    if (!input.files || !input.files[0]) return;
    const status = document.getElementById('ocr-status');
    status.classList.remove('hidden');
    status.textContent = '🔍 Escaneando ticket de combustible con OCR...';

    try {
        const result = await Tesseract.recognize(input.files[0], 'spa');
        const text = result.data.text;

        // Extraer Fecha (DD/MM/YYYY o YYYY-MM-DD)
        const dateMatch = text.match(/(\d{2})[\/\-](\d{2})[\/\-](20\d{2})/);
        if (dateMatch) {
            document.getElementById('rep-fecha').value = `${dateMatch[3]}-${dateMatch[2]}-${dateMatch[1]}`;
        }

        // Extraer números decimales para Precio/L (1.xxx), Litros e Importe
        const decimals = [...text.matchAll(/(\d{1,3}[,\.]\d{2,3})/g)].map(m => parseFloat(m[1].replace(',', '.')));
        const unitPrice = decimals.find(n => n >= 0.85 && n <= 2.60);
        if (unitPrice) document.getElementById('rep-precio').value = unitPrice.toFixed(3);

        const largerNums = decimals.filter(n => n > 3.0).sort((a, b) => b - a);
        if (largerNums.length >= 2) {
            const importe = largerNums[0];
            const litros = largerNums[1];
            document.getElementById('rep-importe').value = importe.toFixed(2);
            document.getElementById('rep-litros').value = litros.toFixed(2);
        }
        status.textContent = '✅ Datos extraídos del ticket. Revisa y confirma los valores.';
    } catch (e) {
        status.textContent = '⚠️ No se pudo leer el ticket automáticamente: ' + e.message;
    }
}

async function scanOdometerOCR(input) {
    if (!input.files || !input.files[0]) return;
    const status = document.getElementById('ocr-status');
    status.classList.remove('hidden');
    status.textContent = '🔍 Leyendo kilómetros del cuentakilómetros...';

    try {
        const result = await Tesseract.recognize(input.files[0], 'eng');
        const digits = result.data.text.replace(/\s+/g, '').match(/\d{4,7}/g);
        if (digits && digits.length > 0) {
            const bestKm = digits.sort((a, b) => b.length - a.length)[0];
            document.getElementById('rep-km').value = bestKm;
            updateParcialKmPreview();
            status.textContent = `✅ Kilometraje detectado: ${bestKm} km`;
        } else {
            status.textContent = '⚠️ No se detectaron dígitos claros en el odómetro.';
        }
    } catch (e) {
        status.textContent = '⚠️ Error al procesar imagen del odómetro.';
    }
}

// GESTIÓN DE VEHÍCULOS
function openVehiclesModal() {
    renderVehiclesList();
    document.getElementById('vehicles-modal').classList.add('active');
}
function closeVehiclesModal() {
    document.getElementById('vehicles-modal').classList.remove('active');
}
function renderVehiclesList() {
    const container = document.getElementById('vehicles-list');
    container.innerHTML = '';
    (currentData.vehiculos || []).forEach(v => {
        const div = document.createElement('div');
        div.style = 'display:flex; justify-content:space-between; align-items:center; padding:0.65rem; border:1px solid var(--border); border-radius:10px; margin-bottom:0.5rem;';
        div.innerHTML = `
            <div>
                <div style="font-weight:700;">${v.nombre} ${v.matricula ? `<span class="badge badge-teal">${v.matricula}</span>` : ''}</div>
                <div style="font-size:0.78rem; color:var(--muted);">${v.tipo_combustible || 'Diésel'} · ${v.modelo || ''}</div>
            </div>
            <div style="display:flex; gap:0.4rem;">
                <button class="btn btn-outline" style="padding:0.3rem 0.6rem; font-size:0.78rem;" onclick='editVehicle(${JSON.stringify(v)})'><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-danger" style="padding:0.3rem 0.6rem; font-size:0.78rem;" onclick="deactivateVehicle(${v.id})"><i class="fa-solid fa-trash"></i></button>
            </div>
        `;
        container.appendChild(div);
    });
}
function editVehicle(v) {
    document.getElementById('veh-id').value = v.id;
    document.getElementById('veh-nombre').value = v.nombre;
    document.getElementById('veh-matricula').value = v.matricula || '';
    document.getElementById('veh-modelo').value = v.modelo || '';
    document.getElementById('veh-combustible').value = v.tipo_combustible || 'Diésel';
}
function resetVehicleForm() {
    document.getElementById('veh-id').value = '0';
    document.getElementById('veh-nombre').value = '';
    document.getElementById('veh-matricula').value = '';
    document.getElementById('veh-modelo').value = '';
}
document.getElementById('vehicle-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await apiCall('save_vehiculo', {
        id: document.getElementById('veh-id').value,
        nombre: document.getElementById('veh-nombre').value,
        matricula: document.getElementById('veh-matricula').value,
        modelo: document.getElementById('veh-modelo').value,
        tipo_combustible: document.getElementById('veh-combustible').value
    });
    if (res.success) {
        resetVehicleForm();
        await loadDashboard();
        renderVehiclesList();
    }
});
async function deactivateVehicle(id) {
    if (!confirm('¿Dar de baja lógica este vehículo? Sus datos permanecerán protegidos en la base de datos (activo = 0).')) return;
    const res = await apiCall('deactivate_vehiculo', { id });
    if (res.success) {
        await loadDashboard();
        renderVehiclesList();
    }
}

// GESTIÓN (SCRUB) DE ESTACIONES DE SERVICIO
function openEstacionesModal() {
    renderEstacionesList();
    document.getElementById('estaciones-modal').classList.add('active');
}
function closeEstacionesModal() {
    document.getElementById('estaciones-modal').classList.remove('active');
}
function renderEstacionesList() {
    const container = document.getElementById('estaciones-list');
    container.innerHTML = '';
    const list = currentData.estaciones || [];
    if (list.length === 0) {
        container.innerHTML = '<div style="font-size:0.82rem; color:var(--muted); text-align:center; padding:0.75rem;">No hay estaciones de servicio registradas todavía.</div>';
        return;
    }
    list.forEach(est => {
        const div = document.createElement('div');
        div.style = 'display:flex; justify-content:space-between; align-items:center; padding:0.6rem; border:1px solid var(--border); border-radius:10px; margin-bottom:0.45rem;';
        div.innerHTML = `
            <div>
                <div style="font-weight:700;">${est.nombre} ${est.marca ? `<span class="badge badge-amber">${est.marca}</span>` : ''}</div>
                <div style="font-size:0.76rem; color:var(--muted);">${est.localidad || ''} ${est.direccion ? '· ' + est.direccion : ''}</div>
            </div>
            <div style="display:flex; gap:0.35rem;">
                <button class="btn btn-outline" style="padding:0.28rem 0.55rem; font-size:0.76rem;" onclick='editEstacion(${JSON.stringify(est)})'><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-danger" style="padding:0.28rem 0.55rem; font-size:0.76rem;" onclick="deactivateEstacion(${est.id})"><i class="fa-solid fa-trash"></i></button>
            </div>
        `;
        container.appendChild(div);
    });
}
function editEstacion(est) {
    document.getElementById('est-id').value = est.id;
    document.getElementById('est-nombre').value = est.nombre;
    document.getElementById('est-marca').value = est.marca || '';
    document.getElementById('est-localidad').value = est.localidad || '';
    document.getElementById('est-direccion').value = est.direccion || '';
    document.getElementById('est-notas').value = est.notas || '';
}
function resetEstacionForm() {
    document.getElementById('est-id').value = '0';
    document.getElementById('est-nombre').value = '';
    document.getElementById('est-marca').value = '';
    document.getElementById('est-localidad').value = '';
    document.getElementById('est-direccion').value = '';
    document.getElementById('est-notas').value = '';
}
document.getElementById('estacion-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await apiCall('save_estacion', {
        id: document.getElementById('est-id').value,
        nombre: document.getElementById('est-nombre').value,
        marca: document.getElementById('est-marca').value,
        localidad: document.getElementById('est-localidad').value,
        direccion: document.getElementById('est-direccion').value,
        notas: document.getElementById('est-notas').value
    });
    if (res.success) {
        resetEstacionForm();
        await loadDashboard();
        renderEstacionesList();
    } else {
        alert(res.error || 'Error guardando estación');
    }
});
async function deactivateEstacion(id) {
    if (!confirm('¿Dar de baja lógica esta estación de servicio? (activo = 0)')) return;
    const res = await apiCall('deactivate_estacion', { id });
    if (res.success) {
        await loadDashboard();
        renderEstacionesList();
    }
}

// IMPORTADOR CONFIGURABLE DE EXCEL (.XLSX / .XLS) Y CSV POR VEHÍCULO
function openImportModal() {
    const sel = document.getElementById('import-vehiculo');
    sel.innerHTML = '';
    const activeVehId = parseInt(document.getElementById('vehicle-selector').value || '0', 10);
    (currentData.vehiculos || []).forEach(v => {
        const opt = document.createElement('option');
        opt.value = v.id;
        opt.textContent = `${v.nombre}${v.matricula ? ' (' + v.matricula + ')' : ''}`;
        if (parseInt(v.id, 10) === activeVehId) opt.selected = true;
        sel.appendChild(opt);
    });
    document.getElementById('import-file').value = '';
    document.getElementById('import-mapping-section').classList.add('hidden');
    document.getElementById('import-modal').classList.add('active');
}

function closeImportModal() {
    document.getElementById('import-modal').classList.remove('active');
}

function downloadTemplateCSV() {
    const csvContent = "FECHA,LITROS,PRECIO,IMPORTE,KM.,NOTAS\n2025-03-01,48.50,1.429,69.31,183200,Repostaje normal\n2025-03-15,51.20,1.439,73.68,184120,Cambio filtro aire\n";
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'Plantilla_Repostajes_Combustible.csv';
    link.click();
}

function handleImportFile(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = function (e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: 'array', raw: false, dateNF: 'yyyy-mm-dd' });
        const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
        rawImportedRows = XLSX.utils.sheet_to_json(firstSheet, { header: 1, defval: '' });
        reparseImportSheet();
    };
    reader.readAsArrayBuffer(file);
}

function colLetter(idx) {
    let s = '';
    let n = idx;
    while (n >= 0) {
        s = String.fromCharCode((n % 26) + 65) + s;
        n = Math.floor(n / 26) - 1;
    }
    return s;
}

function reparseImportSheet() {
    if (!rawImportedRows || rawImportedRows.length === 0) return;
    const hasHeader = document.getElementById('import-has-header').checked;
    const firstRow = rawImportedRows[0] || [];
    const maxCols = Math.max(...rawImportedRows.slice(0, 10).map(r => r.length), firstRow.length);

    const mapIds = ['map-fecha', 'map-litros', 'map-precio', 'map-importe', 'map-km', 'map-notas', 'map-estacion'];
    mapIds.forEach((id, fieldIdx) => {
        const sel = document.getElementById(id);
        sel.innerHTML = (fieldIdx >= 5) ? '<option value="-1">-- Ninguna --</option>' : '';
        for (let c = 0; c < maxCols; c++) {
            const title = hasHeader && firstRow[c] !== undefined && String(firstRow[c]).trim() !== ''
                ? `Col ${colLetter(c)}: ${String(firstRow[c]).trim()}`
                : `Columna ${colLetter(c)} (Ej: ${rawImportedRows[hasHeader ? 1 : 0]?.[c] ?? ''})`;
            const opt = document.createElement('option');
            opt.value = c;
            opt.textContent = title;
            sel.appendChild(opt);
        }
    });

    // Autodetectar por nombres de cabecera o por posición estándar (A=0, B=1, C=2, D=3, E=4, Q=16)
    let defFecha = 0, defLitros = 1, defPrecio = 2, defImporte = 3, defKm = 4, defNotas = (maxCols > 16 ? 16 : (maxCols > 5 ? 5 : -1)), defEst = -1;
    if (hasHeader) {
        firstRow.forEach((h, idx) => {
            const u = String(h || '').toUpperCase().trim();
            if (u.includes('FECHA') || u === 'DATE') defFecha = idx;
            else if (u === 'LITROS' || u === 'LTS' || u === 'VOLUMEN') defLitros = idx;
            else if (u.includes('PRECIO') || u === '€/L' || u === 'EUR/L') defPrecio = idx;
            else if (u.includes('IMPORTE') || u === 'TOTAL' || u === 'EUROS') defImporte = idx;
            else if (u === 'KM.' || u === 'KM' || u.includes('KILOMETR') || u.includes('ODOMETR')) defKm = idx;
            else if (u.includes('NOTA') || u.includes('INCIDENCIA') || u.includes('OBSERV')) defNotas = idx;
            else if (u.includes('ESTACION') || u.includes('GASOLINERA')) defEst = idx;
        });
    }

    document.getElementById('map-fecha').value = String(defFecha);
    document.getElementById('map-litros').value = String(defLitros);
    document.getElementById('map-precio').value = String(defPrecio);
    document.getElementById('map-importe').value = String(defImporte);
    document.getElementById('map-km').value = String(defKm);
    document.getElementById('map-notas').value = String(defNotas < maxCols ? defNotas : -1);
    document.getElementById('map-estacion').value = String(defEst);

    document.getElementById('import-mapping-section').classList.remove('hidden');
    renderImportPreview();
}

function getMappedImportRows() {
    const hasHeader = document.getElementById('import-has-header').checked;
    const cFecha = parseInt(document.getElementById('map-fecha').value, 10);
    const cLitros = parseInt(document.getElementById('map-litros').value, 10);
    const cPrecio = parseInt(document.getElementById('map-precio').value, 10);
    const cImporte = parseInt(document.getElementById('map-importe').value, 10);
    const cKm = parseInt(document.getElementById('map-km').value, 10);
    const cNotas = parseInt(document.getElementById('map-notas').value, 10);
    const cEst = parseInt(document.getElementById('map-estacion').value, 10);

    const startRow = hasHeader ? 1 : 0;
    const mapped = [];
    for (let i = startRow; i < rawImportedRows.length; i++) {
        const r = rawImportedRows[i];
        if (!r || r.length === 0) continue;
        const fVal = cFecha >= 0 ? String(r[cFecha] ?? '').trim() : '';
        if (!fVal) continue;
        const rawNote = cNotas >= 0 ? String(r[cNotas] ?? '').trim() : '';
        const cleanNote = (rawNote && isNaN(Number(rawNote)) && !rawNote.includes('PRECIO') && !rawNote.includes('MINIMO')) ? rawNote : '';
        mapped.push({
            fecha: fVal,
            litros: cLitros >= 0 ? r[cLitros] : '',
            precio_litro: cPrecio >= 0 ? r[cPrecio] : '',
            importe_total: cImporte >= 0 ? r[cImporte] : '',
            km_odometro: cKm >= 0 ? r[cKm] : '',
            notas: cleanNote,
            estacion_servicio: cEst >= 0 ? String(r[cEst] ?? '').trim() : ''
        });
    }
    return mapped;
}

function renderImportPreview() {
    const mapped = getMappedImportRows();
    document.getElementById('import-preview-info').textContent =
        `Se han detectado ${mapped.length} filas de datos listas para importar. Vista previa de las primeras 6 filas:`;
    const tbody = document.getElementById('import-preview-tbody');
    tbody.innerHTML = '';
    mapped.slice(0, 6).forEach(m => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${m.fecha}</td>
            <td>${m.litros}</td>
            <td>${m.precio_litro}</td>
            <td>${m.importe_total}</td>
            <td>${m.km_odometro}</td>
            <td>${m.notas || ''}</td>
        `;
        tbody.appendChild(tr);
    });
}

async function executeCustomImport() {
    const vehiculoId = parseInt(document.getElementById('import-vehiculo').value || '0', 10);
    if (!vehiculoId) {
        alert('Selecciona primero el vehículo al que quieres importar los datos.');
        return;
    }
    const rows = getMappedImportRows();
    if (rows.length === 0) {
        alert('No hay filas válidas para importar.');
        return;
    }
    const btn = document.getElementById('btn-run-import');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importando...';
    try {
        const res = await apiCall('import_custom_rows', { vehiculo_id: vehiculoId, rows });
        if (res.success) {
            alert(res.message);
            closeImportModal();
            document.getElementById('vehicle-selector').value = String(vehiculoId);
            await loadDashboard();
        } else {
            alert(res.error || 'Error en la importación');
        }
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Confirmar e Importar al Vehículo';
    }
}

// GENERACIÓN DE INFORMES PDF OPTIMIZADO EN A4 VERTICAL CON FILTRO Y ORDEN POR FECHA
function openPdfOptionsModal() {
    document.getElementById('pdf-from').value = document.getElementById('filter-from').value || '';
    document.getElementById('pdf-to').value = document.getElementById('filter-to').value || '';
    document.getElementById('pdf-search').value = document.getElementById('search-input').value || '';
    updatePdfPreviewCount();
    document.getElementById('pdf-options-modal').classList.add('active');
}

function closePdfOptionsModal() {
    document.getElementById('pdf-options-modal').classList.remove('active');
}

function getFilteredSortedRowsForPdf() {
    const from = document.getElementById('pdf-from').value || '';
    const to   = document.getElementById('pdf-to').value || '';
    const q    = (document.getElementById('pdf-search').value || '').toLowerCase().trim();
    const order = document.getElementById('pdf-sort-order').value || 'desc';

    const list = (currentData.repostajes || []).filter(r => {
        if (from && r.fecha < from) return false;
        if (to && r.fecha > to) return false;
        if (q) {
            const match = (r.fecha && r.fecha.toLowerCase().includes(q)) ||
                          String(r.km_odometro).includes(q) ||
                          (r.notas && r.notas.toLowerCase().includes(q)) ||
                          (r.estacion_servicio && r.estacion_servicio.toLowerCase().includes(q)) ||
                          (r.vehiculo_nombre && r.vehiculo_nombre.toLowerCase().includes(q));
            if (!match) return false;
        }
        return true;
    });

    list.sort((a, b) => {
        const cmp = (a.fecha || '').localeCompare(b.fecha || '');
        if (cmp !== 0) return order === 'asc' ? cmp : -cmp;
        const kmCmp = (parseFloat(a.km_odometro) || 0) - (parseFloat(b.km_odometro) || 0);
        return order === 'asc' ? kmCmp : -kmCmp;
    });
    return list;
}

function updatePdfPreviewCount() {
    const list = getFilteredSortedRowsForPdf();
    document.getElementById('pdf-count-info').textContent =
        `Se incluirán ${list.length} repostaje(s) según los filtros seleccionados.`;
}

function exportPDFReport() {
    const filteredList = getFilteredSortedRowsForPdf();
    if (filteredList.length === 0) {
        alert('No hay registros que coincidan con el filtro seleccionado.');
        return;
    }
    const order = document.getElementById('pdf-sort-order').value || 'desc';
    const from  = document.getElementById('pdf-from').value || '';
    const to    = document.getElementById('pdf-to').value || '';

    // Calcular resumen sobre el conjunto filtrado
    let totalLitros = 0, totalEuros = 0, totalKm = 0;
    const consumos = [], precios = [];
    filteredList.forEach(r => {
        totalLitros += parseFloat(r.litros) || 0;
        totalEuros  += parseFloat(r.importe_total) || 0;
        if ((parseFloat(r.dif_km) || 0) > 0) totalKm += parseFloat(r.dif_km);
        if (r.litros_por_100 !== null && !isNaN(r.litros_por_100)) consumos.push(parseFloat(r.litros_por_100));
        if ((parseFloat(r.precio_litro) || 0) > 0) precios.push(parseFloat(r.precio_litro));
    });
    const cMax = consumos.length ? Math.max(...consumos) : 0;
    const cMin = consumos.length ? Math.min(...consumos) : 0;
    const cMed = consumos.length ? (consumos.reduce((a, b) => a + b, 0) / consumos.length) : 0;
    const pMax = precios.length ? Math.max(...precios) : 0;
    const pMin = precios.length ? Math.min(...precios) : 0;
    const coste100 = totalKm > 0 ? ((totalEuros / totalKm) * 100) : 0;

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const selText = document.getElementById('vehicle-selector').selectedOptions[0].textContent.replace(/[^\w\sáéíóúÁÉÍÓÚñÑ()\-]/g, '').trim();
    const orderLabel = order === 'asc' ? 'Ascendente (fecha)' : 'Descendente (fecha)';
    const rangeLabel = (from || to) ? ` | Periodo: ${from || '...'} a ${to || '...'}` : '';

    // Cabecera compacta A4 Vertical
    doc.setFontSize(13);
    doc.setTextColor(15, 118, 110);
    doc.text('INFORME DE CONTROL DE COMBUSTIBLE Y CONSUMO', 8, 12);

    doc.setFontSize(8.2);
    doc.setTextColor(70, 70, 70);
    doc.text(`Ámbito: ${selText}${rangeLabel} | Orden: ${orderLabel} | Emisión: ${new Date().toLocaleDateString('es-ES')}`, 8, 17);

    // Caja resumen de indicadores en A4 Vertical (194 mm ancho útil)
    doc.setDrawColor(203, 213, 225);
    doc.setFillColor(248, 250, 252);
    doc.roundedRect(8, 20, 194, 13, 2, 2, 'FD');

    doc.setFontSize(7.5);
    doc.setTextColor(15, 23, 42);
    doc.text(
        `Consumo Medio: ${fmtNum(cMed, 2)} L/100km  |  Máx / Mín: ${fmtNum(cMax, 2)} / ${fmtNum(cMin, 2)} L/100 (Dif: ${fmtNum(cMax - cMin, 2)})  |  Precio Máx / Mín: ${fmtNum(pMax, 3)} / ${fmtNum(pMin, 3)} €/L`,
        11, 25.5
    );
    doc.text(
        `Total Litros: ${fmtNum(totalLitros, 2)} L (${filteredList.length} rep.)  |  Gasto: ${fmtNum(totalEuros, 2)} €  |  Km Recorridos: ${fmtNum(totalKm, 0)} km  |  Coste Medio: ${fmtNum(coste100, 2)} €/100km`,
        11, 30.5
    );

    const rows = filteredList.map(r => [
        r.fecha,
        fmtNum(r.litros, 2),
        fmtNum(r.precio_litro, 3),
        fmtNum(r.importe_total, 2) + ' €',
        fmtNum(r.km_odometro, 0),
        r.dif_km > 0 ? '+' + fmtNum(r.dif_km, 0) : '-',
        r.litros_por_100 !== null ? fmtNum(r.litros_por_100, 2) : '-',
        r.km_por_litro !== null ? fmtNum(r.km_por_litro, 2) : '-',
        r.euros_por_km !== null ? fmtNum(r.euros_por_km, 3) : '-',
        fmtNum(r.total_litros_acum, 1),
        fmtNum(r.total_euros_acum, 2) + ' €',
        (r.notas || '').substring(0, 22)
    ]);

    doc.autoTable({
        startY: 36,
        margin: { top: 12, right: 8, bottom: 12, left: 8 },
        head: [['Fecha', 'Litros', '€/L', 'Importe', 'KM.', 'DIF.', 'L/100', 'Km/L', '€/Km', 'T.Litros', 'Total €', 'Notas']],
        body: rows,
        styles: {
            fontSize: 6.8,
            cellPadding: 1.3,
            valign: 'middle',
            overflow: 'linebreak'
        },
        headStyles: {
            fillColor: [15, 118, 110],
            textColor: 255,
            fontStyle: 'bold',
            halign: 'right'
        },
        columnStyles: {
            0: { halign: 'left', cellWidth: 18 },
            1: { halign: 'right', cellWidth: 13 },
            2: { halign: 'right', cellWidth: 12 },
            3: { halign: 'right', cellWidth: 16, fontStyle: 'bold' },
            4: { halign: 'right', cellWidth: 16 },
            5: { halign: 'right', cellWidth: 13 },
            6: { halign: 'right', cellWidth: 13, fontStyle: 'bold' },
            7: { halign: 'right', cellWidth: 13 },
            8: { halign: 'right', cellWidth: 13 },
            9: { halign: 'right', cellWidth: 16 },
            10: { halign: 'right', cellWidth: 18 },
            11: { halign: 'left', cellWidth: 'auto' }
        },
        alternateRowStyles: { fillColor: [248, 250, 252] },
        didDrawPage: function (data) {
            const pageCount = doc.internal.getNumberOfPages();
            doc.setFontSize(7.5);
            doc.setTextColor(120, 120, 120);
            doc.text(
                `Página ${data.pageNumber} de ${pageCount} - Control de Combustible (A4 Vertical)`,
                105,
                291,
                { align: 'center' }
            );
        }
    });

    closePdfOptionsModal();
    doc.save(`Informe_Combustible_A4_${new Date().toISOString().slice(0,10)}.pdf`);
}

// Restaurar sesión (por sessionStorage o por sesión activa de moratalla-murcia.com/admin)
window.addEventListener('DOMContentLoaded', async () => {
    const saved = sessionStorage.getItem('veh_auth');
    if (saved) {
        authCreds = JSON.parse(saved);
    }
    try {
        const res = await apiCall('login');
        if (res && res.success) {
            document.getElementById('login-screen').classList.add('hidden');
            document.getElementById('app-screen').classList.remove('hidden');
            await loadDashboard();
        }
    } catch (e) {}
});
</script>
</body>
</html>
