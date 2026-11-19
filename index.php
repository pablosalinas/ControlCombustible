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
            font-size: 0.78rem;
        }
        th, td {
            padding: 0.45rem 0.36rem;
            text-align: right;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        td.wrap-col {
            white-space: normal;
            min-width: 115px;
            max-width: 210px;
            line-height: 1.28;
            word-break: break-word;
        }
        th.col-actions, td.col-actions {
            position: sticky;
            right: 0;
            z-index: 5;
            text-align: right;
            white-space: nowrap;
            box-shadow: -3px 0 6px rgba(15, 23, 42, 0.06);
        }
        thead th.col-actions {
            background: #0f172a;
            z-index: 12;
        }
        tbody tr:nth-child(odd) td.col-actions { background: #fff; }
        tbody tr:nth-child(even) td.col-actions { background: #f8fafc; }
        tbody tr:hover td.col-actions { background: #f0fdfa; }
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
            grid-template-columns: repeat(3, 1fr);
            gap: 0.55rem;
            margin-bottom: 0.75rem;
            background: #f8fafc;
            padding: 0.7rem;
            border-radius: 12px;
            border: 1px dashed #cbd5e1;
        }
        .photo-preview-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            margin-bottom: 0.85rem;
        }
        .photo-thumb-card {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            background: #f1f5f9;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.35rem 0.55rem;
            font-size: 0.78rem;
        }
        .photo-thumb-card img {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            border: 1px solid #cbd5e1;
        }
        .detected-station-box {
            margin-top: 0.45rem;
            padding: 0.55rem 0.7rem;
            border-radius: 8px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            font-size: 0.78rem;
            color: #065f46;
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
        <div id="login-success" class="hidden" style="background:#dcfce7; color:#166534; padding:0.75rem; border-radius:10px; font-size:0.85rem; margin-bottom:1rem; text-align:center;"></div>
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
            <button type="button" class="btn btn-outline btn-block" style="margin-top: 0.55rem;" onclick="toggleAuthMode(true)">
                <i class="fa-solid fa-user-plus"></i> Solicitar Registro de Usuario
            </button>
            <a href="https://github.com/pablosalinas/ControlCombustible/releases/latest/download/ControlCombustible.apk" target="_blank" class="btn btn-outline btn-block" style="margin-top: 0.65rem; text-decoration: none; font-size: 0.85rem;">
                <i class="fa-brands fa-android" style="color:#16a34a; font-size:1.1rem;"></i> Descargar App Android (.APK)
            </a>
            <div style="margin-top: 0.45rem; font-size: 0.75rem; color: #64748b; text-align: center; line-height: 1.35;">
                Si Android rechaza actualizar desde una versión antigua, desinstala la app previa una única vez e instala el nuevo APK (desde V1.261118 ya se actualiza directo).
            </div>
            <div style="text-align: center; margin-top: 1rem;">
                <a href="https://www.moratalla-murcia.com" target="_blank" style="color: var(--primary); text-decoration: none; font-size: 0.84rem; font-weight: 600;">
                    <i class="fa-solid fa-globe"></i> Ir a moratalla-murcia.com
                </a>
            </div>
        </form>
        <form id="register-form" class="hidden">
            <div class="form-group">
                <label>Nuevo Usuario *</label>
                <input type="text" id="reg-user" class="form-control" required minlength="3" maxlength="50" placeholder="Ej: juan.perez" autocomplete="username">
            </div>
            <div class="form-group">
                <label>Correo Electrónico (para comunicaciones) *</label>
                <input type="email" id="reg-email" class="form-control" required placeholder="tu@correo.com" autocomplete="email">
            </div>
            <div class="form-group">
                <label>Contraseña (mínimo 6 caracteres) *</label>
                <input type="password" id="reg-pass" class="form-control" required minlength="6" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label>Repetir Contraseña *</label>
                <input type="password" id="reg-pass2" class="form-control" required minlength="6" autocomplete="new-password">
            </div>
            <p style="font-size:0.78rem; color:#64748b; margin-bottom:0.85rem;">
                <i class="fa-solid fa-user-shield"></i> Tu solicitud quedará pendiente de validación por parte del superusuario.
            </p>
            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.9rem;">
                <i class="fa-solid fa-paper-plane"></i> Enviar Solicitud de Registro
            </button>
            <button type="button" class="btn btn-outline btn-block" style="margin-top: 0.55rem;" onclick="toggleAuthMode(false)">
                <i class="fa-solid fa-arrow-left"></i> Volver a Iniciar Sesión
            </button>
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
                <button class="btn btn-outline" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="document.getElementById('maintenance-section').scrollIntoView({behavior:'smooth'})">
                    <i class="fa-solid fa-wrench" style="color:var(--primary)"></i> Tareas <span id="tasks-alert-badge" class="hidden" style="background:#dc2626; color:#fff; border-radius:999px; padding:0.08rem 0.42rem; font-size:0.7rem; margin-left:2px;">0</span>
                </button>
                <button class="btn btn-outline" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="openTareasMaestroModal()">
                    <i class="fa-solid fa-list-check" style="color:var(--primary)"></i> Maestro Tareas
                </button>
                <button class="btn btn-outline hidden" id="btn-pending-users" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="openPendingUsersModal()" title="Validar solicitudes de registro">
                    <i class="fa-solid fa-user-check" style="color:#d97706"></i> Solicitudes
                </button>
                <button class="btn btn-outline" id="btn-inactive-records" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="openInactiveRecordsModal()" title="Papelera / Reactivar vehículos y repostajes inactivos">
                    <i class="fa-solid fa-trash-arrow-up" style="color:#dc2626"></i> Papelera
                </button>
                <button class="btn btn-outline" id="btn-parques-moviles" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="openParquesModal()" title="Gestionar Parques Móviles compartidos">
                    <i class="fa-solid fa-people-group" style="color:#0f766e"></i> Parques Móviles
                </button>
                <button class="btn btn-outline" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="openUserEmailModal()" title="Configurar correo para avisos">
                    <i class="fa-solid fa-envelope" style="color:#0284c7"></i> Correo
                </button>
                <button class="btn btn-outline" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="openImportModal()">
                    <i class="fa-solid fa-file-import" style="color:var(--primary)"></i> Importar Excel/CSV
                </button>
                <button class="btn btn-outline" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="openEstacionesModal()">
                    <i class="fa-solid fa-shop" style="color:var(--accent)"></i> Estaciones
                </button>
                <button class="btn btn-outline" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="openProveedoresModal()">
                    <i class="fa-solid fa-screwdriver-wrench" style="color:#0284c7"></i> Talleres/Seguros/ITV
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

        <!-- MANTENIMIENTO Y TAREAS PERIÓDICAS DE VEHÍCULOS -->
        <div class="card" id="maintenance-section">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-wrench" style="color:var(--primary)"></i>
                    Mantenimiento y Controles Periódicos
                </div>
                <div style="display:flex; gap:0.45rem; align-items:center; flex-wrap:wrap;">
                    <select id="tasks-filter-estado" class="form-control" style="width:auto; padding:0.42rem 0.65rem; font-size:0.82rem;" onchange="renderTareasTable()">
                        <option value="todas">📋 Todas las tareas</option>
                        <option value="pendiente">⏳ Pendientes</option>
                        <option value="proximas">⚠️ Próximas / En alerta (&lt;1000 km o ≤15d)</option>
                        <option value="finalizada">✅ Finalizadas</option>
                    </select>
                    <input type="text" id="tasks-search-input" class="form-control" placeholder="Buscar tarea o vehículo..." style="max-width:190px; padding:0.42rem 0.7rem; font-size:0.82rem;" oninput="renderTareasTable()">
                    <button class="btn btn-outline" style="padding:0.42rem 0.7rem; font-size:0.8rem;" onclick="openTareasMaestroModal()" title="SCRUB Maestro de Tareas">
                        <i class="fa-solid fa-list-check"></i> Maestro
                    </button>
                    <button class="btn btn-outline" style="padding:0.42rem 0.7rem; font-size:0.8rem;" onclick="openProveedoresModal()" title="SCRUB Talleres, Aseguradoras e ITVs">
                        <i class="fa-solid fa-screwdriver-wrench" style="color:#0284c7"></i> Proveedores
                    </button>
                    <button class="btn btn-outline" style="padding:0.42rem 0.7rem; font-size:0.8rem; border-color:#d97706; color:#b45309;" onclick="sendTaskAlertsEmailNow()" title="Enviar avisos por email">
                        <i class="fa-solid fa-paper-plane"></i> Enviar Avisos Email
                    </button>
                    <button class="btn btn-accent" style="padding:0.42rem 0.7rem; font-size:0.8rem;" onclick="exportTareasPDF(false)">
                        <i class="fa-solid fa-file-pdf"></i> Informe Tareas
                    </button>
                    <button class="btn btn-outline" style="padding:0.42rem 0.7rem; font-size:0.8rem; border-color:#0284c7; color:#0369a1;" onclick="exportTareasPDF(true)" title="Enviar Informe de Tareas PDF por correo">
                        <i class="fa-solid fa-envelope"></i> PDF Tareas Email
                    </button>
                    <button class="btn btn-primary" style="padding:0.44rem 0.8rem; font-size:0.82rem;" onclick="openVehiculoTareaModal()">
                        <i class="fa-solid fa-plus"></i> Asignar Tarea
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Vehículo</th>
                            <th>Tarea / Control</th>
                            <th>Periodicidad</th>
                            <th>Última / Próx. Fecha</th>
                            <th>Último / Próx. Km</th>
                            <th>Proveedor / Coste</th>
                            <th>Estado / Margen</th>
                            <th style="text-align:right" class="col-actions">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tareas-tbody"></tbody>
                </table>
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
                            <th class="col-actions">Acciones</th>
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

        <!-- Barra de Escaneo OCR (Factura/Ticket, Surtidor y Cuentakilómetros) -->
        <div class="ocr-bar">
            <label class="btn btn-outline" style="font-size:0.78rem; cursor:pointer; justify-content:center; text-align:center;">
                <i class="fa-solid fa-receipt" style="color:var(--primary)"></i> Foto Factura / Ticket
                <input type="file" accept="image/*" capture="environment" class="hidden" onchange="scanFuelPhoto(this, 'factura')">
            </label>
            <label class="btn btn-outline" style="font-size:0.78rem; cursor:pointer; justify-content:center; text-align:center;">
                <i class="fa-solid fa-gas-pump" style="color:#0284c7"></i> Foto Surtidor
                <input type="file" accept="image/*" capture="environment" class="hidden" onchange="scanFuelPhoto(this, 'surtidor')">
            </label>
            <label class="btn btn-outline" style="font-size:0.78rem; cursor:pointer; justify-content:center; text-align:center;">
                <i class="fa-solid fa-gauge-high" style="color:var(--accent)"></i> Foto Odómetro
                <input type="file" accept="image/*" capture="environment" class="hidden" onchange="scanOdometerOCR(this)">
            </label>
        </div>
        <div id="ocr-status" class="hidden" style="font-size:0.8rem; padding:0.55rem 0.75rem; border-radius:8px; background:#e0f2fe; color:#0369a1; margin-bottom:0.75rem;"></div>
        <div id="rep-photos-preview" class="photo-preview-row hidden"></div>

        <form id="repostaje-form">
            <input type="hidden" id="rep-id" value="0">
            <input type="hidden" id="rep-foto-ticket" value="">
            <input type="hidden" id="rep-foto-odometro" value="">
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
                    <div id="detected-station-banner" class="detected-station-box hidden"></div>
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

<!-- MODAL LIGHTBOX PARA FOTOS DE REPOSTAJE -->
<div class="modal-backdrop" id="photo-lightbox-modal" onclick="if(event.target===this) closePhotoLightbox()">
    <div class="modal-content" style="max-width:720px; padding:1rem;">
        <div class="card-header" style="margin-bottom:0.65rem;">
            <div class="card-title" id="lightbox-title"><i class="fa-solid fa-image" style="color:var(--primary)"></i> Foto adjunta</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closePhotoLightbox()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="text-align:center; background:#0f172a; border-radius:10px; padding:0.5rem;">
            <img id="lightbox-img" src="" alt="Foto repostaje" style="max-width:100%; max-height:75vh; border-radius:6px; object-fit:contain;">
        </div>
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
            <div class="form-group">
                <label>Parque Móvil / Flota (opcional)</label>
                <select id="veh-parque" class="form-control">
                    <option value="">Personal (solo mi usuario)</option>
                </select>
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
        <div style="display:flex; justify-content:flex-end; gap:0.6rem; flex-wrap:wrap;">
            <button type="button" class="btn btn-outline" onclick="closePdfOptionsModal()">Cancelar</button>
            <button type="button" class="btn btn-outline" style="border-color:#0284c7; color:#0369a1;" onclick="exportPDFReport(true)">
                <i class="fa-solid fa-envelope"></i> Enviar por Email
            </button>
            <button type="button" class="btn btn-accent" onclick="exportPDFReport(false)">
                <i class="fa-solid fa-file-pdf"></i> Generar PDF
            </button>
        </div>
    </div>
</div>

<!-- MODAL MAESTRO DE TAREAS Y CONTROLES PERIÓDICOS (SCRUB GLOBAL) -->
<div class="modal-backdrop" id="tareas-maestro-modal">
    <div class="modal-content" style="max-width:650px;">
        <div class="card-header" style="margin-bottom:0.8rem;">
            <div class="card-title"><i class="fa-solid fa-list-check" style="color:var(--primary)"></i> Maestro de Tareas Periódicas (Común para todos)</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closeTareasMaestroModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="tm-list" style="max-height:220px; overflow-y:auto; margin-bottom:1rem; border:1px solid var(--border); border-radius:10px; padding:0.5rem;"></div>
        <form id="tm-form" style="border-top:1px solid var(--border); padding-top:0.85rem;">
            <input type="hidden" id="tm-id" value="0">
            <div class="grid-2">
                <div class="form-group">
                    <label>Nombre de la Tarea *</label>
                    <input type="text" id="tm-nombre" class="form-control" required placeholder="Ej: Cambio de aceite">
                </div>
                <div class="form-group">
                    <label>Tipo de Periodicidad *</label>
                    <select id="tm-tipo" class="form-control" onchange="toggleTmPeriodoFields()">
                        <option value="ambos">Tiempo y Kilómetros (Ambos)</option>
                        <option value="kilometros">Solo Kilómetros</option>
                        <option value="tiempo">Solo Tiempo (Días)</option>
                        <option value="unica">Única / Reparación (No periódica)</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <input type="text" id="tm-descripcion" class="form-control" placeholder="Detalle del control o mantenimiento">
            </div>
            <div class="grid-2">
                <div class="form-group" id="tm-dias-wrap">
                    <label>Periodicidad en Tiempo (días)</label>
                    <input type="number" id="tm-dias" class="form-control" placeholder="Ej: 365">
                </div>
                <div class="form-group" id="tm-km-wrap">
                    <label>Periodicidad en Kilómetros</label>
                    <input type="number" step="1" id="tm-km" class="form-control" placeholder="Ej: 15000">
                </div>
            </div>
            <div class="form-group">
                <label style="display:flex; align-items:center; gap:0.5rem; font-weight:600; cursor:pointer;">
                    <input type="checkbox" id="tm-avisar-tiempo" checked>
                    Avisar por tiempo aunque no haya llegado al kilometraje indicado
                </label>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                <button type="button" class="btn btn-outline" onclick="resetTmForm()">Limpiar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar en Maestro</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL ASIGNAR / EDITAR TAREA DE VEHÍCULO -->
<div class="modal-backdrop" id="vehiculo-tarea-modal">
    <div class="modal-content" style="max-width:560px;">
        <div class="card-header" style="margin-bottom:0.8rem;">
            <div class="card-title" id="vt-modal-title"><i class="fa-solid fa-wrench" style="color:var(--primary)"></i> Asignar Tarea al Vehículo</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closeVehiculoTareaModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="vt-form">
            <input type="hidden" id="vt-id" value="0">
            <div class="grid-2">
                <div class="form-group">
                    <label>Vehículo *</label>
                    <select id="vt-vehiculo" class="form-control" required onchange="onVtVehiculoChange()"></select>
                </div>
                <div class="form-group">
                    <label>Plantilla del Maestro (Opcional)</label>
                    <select id="vt-maestro" class="form-control" onchange="onVtMaestroSelect()">
                        <option value="0">-- Personalizada --</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Descripción de la Tarea *</label>
                <input type="text" id="vt-descripcion" class="form-control" required placeholder="Ej: Cambio de aceite y filtro">
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Periodicidad *</label>
                    <select id="vt-tipo" class="form-control" onchange="toggleVtPeriodoFields()">
                        <option value="ambos">Tiempo y Kilómetros (Ambos)</option>
                        <option value="kilometros">Solo Kilómetros</option>
                        <option value="tiempo">Solo Tiempo (Días)</option>
                        <option value="unica">Única / Reparación (No periódica)</option>
                    </select>
                </div>
                <div class="form-group" id="vt-dias-wrap">
                    <label>Cada (días)</label>
                    <input type="number" id="vt-dias" class="form-control" placeholder="Ej: 365">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group" id="vt-km-wrap">
                    <label>Cada (km)</label>
                    <input type="number" step="1" id="vt-km" class="form-control" placeholder="Ej: 15000">
                </div>
                <div class="form-group">
                    <label>Fecha última realización / inicio</label>
                    <input type="date" id="vt-ultima-fecha" class="form-control">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Km última realización / inicio</label>
                    <input type="number" step="1" id="vt-ultimo-km" class="form-control" placeholder="Ej: 180000">
                </div>
                <div class="form-group">
                    <label>Coste / Precio (€)</label>
                    <input type="number" step="0.01" id="vt-coste" class="form-control" placeholder="Opcional, ej: 120.50">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Taller / Aseguradora / ITV</label>
                    <div style="display:flex; gap:0.35rem;">
                        <select id="vt-proveedor" class="form-control">
                            <option value="0">-- Sin especificar --</option>
                        </select>
                        <button type="button" class="btn btn-outline" style="padding:0.45rem 0.65rem; flex-shrink:0;" onclick="openQuickProveedorModal('vt-proveedor')" title="Crear Taller, Aseguradora o ITV nuevo">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Garantía (meses)</label>
                    <input type="number" min="0" step="1" id="vt-garantia" class="form-control" placeholder="Opcional, ej: 12 o 24">
                </div>
            </div>
            <div class="form-group">
                <label>Notas</label>
                <input type="text" id="vt-notas" class="form-control" placeholder="Opcional">
            </div>
            <div class="form-group" id="vt-avisar-wrap">
                <label style="display:flex; align-items:center; gap:0.5rem; font-weight:600; cursor:pointer;">
                    <input type="checkbox" id="vt-avisar-tiempo" checked>
                    Avisar si llega al tiempo límite aunque no haya alcanzado los km
                </label>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:0.6rem;">
                <button type="button" class="btn btn-outline" onclick="closeVehiculoTareaModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar Tarea</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL FINALIZAR TAREA -->
<div class="modal-backdrop" id="complete-tarea-modal">
    <div class="modal-content" style="max-width:440px;">
        <div class="card-header" style="margin-bottom:0.8rem;">
            <div class="card-title"><i class="fa-solid fa-circle-check" style="color:#16a34a"></i> Finalizar Tarea Periódica</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closeCompleteTareaModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="complete-vt-form">
            <input type="hidden" id="comp-vt-id" value="0">
            <input type="hidden" id="comp-is-renewable" value="0">
            <p id="comp-vt-desc" style="font-weight:700; color:var(--primary-dark); margin-bottom:0.8rem;"></p>
            <div class="grid-2">
                <div class="form-group">
                    <label>Fecha de realización *</label>
                    <input type="date" id="comp-fecha" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Km al realizarla</label>
                    <input type="number" step="1" id="comp-km" class="form-control">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Taller / Aseguradora / ITV</label>
                    <div style="display:flex; gap:0.35rem;">
                        <select id="comp-proveedor" class="form-control">
                            <option value="0">-- Sin especificar --</option>
                        </select>
                        <button type="button" class="btn btn-outline" style="padding:0.45rem 0.65rem; flex-shrink:0;" onclick="openQuickProveedorModal('comp-proveedor')" title="Crear Taller, Aseguradora o ITV nuevo">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Coste / Precio (€)</label>
                    <input type="number" step="0.01" id="comp-coste" class="form-control" placeholder="Ej: 125.50">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Garantía (meses)</label>
                    <input type="number" min="0" step="1" id="comp-garantia" class="form-control" placeholder="Opcional, ej: 12">
                </div>
                <div class="form-group">
                    <label>Notas de ejecución</label>
                    <input type="text" id="comp-notas" class="form-control" placeholder="Observaciones...">
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:0.6rem;">
                <button type="button" class="btn btn-outline" onclick="closeCompleteTareaModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Confirmar Finalización</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CRUD TALLERES, ASEGURADORAS E ITVS -->
<div class="modal-backdrop" id="proveedores-modal">
    <div class="modal-content" style="max-width:650px;">
        <div class="card-header" style="margin-bottom:0.6rem;">
            <div class="card-title"><i class="fa-solid fa-screwdriver-wrench" style="color:#0284c7"></i> Talleres, Aseguradoras e ITVs</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closeProveedoresModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="display:flex; gap:0.4rem; margin-bottom:0.85rem; flex-wrap:wrap;">
            <button type="button" class="btn btn-primary" id="prov-tab-taller" style="padding:0.4rem 0.85rem; font-size:0.82rem;" onclick="switchProveedorTab('taller')">
                <i class="fa-solid fa-wrench"></i> Talleres
            </button>
            <button type="button" class="btn btn-outline" id="prov-tab-aseguradora" style="padding:0.4rem 0.85rem; font-size:0.82rem;" onclick="switchProveedorTab('aseguradora')">
                <i class="fa-solid fa-shield-halved"></i> Aseguradoras
            </button>
            <button type="button" class="btn btn-outline" id="prov-tab-itv" style="padding:0.4rem 0.85rem; font-size:0.82rem;" onclick="switchProveedorTab('itv')">
                <i class="fa-solid fa-clipboard-check"></i> ITVs
            </button>
        </div>
        <div id="proveedores-list" style="max-height:220px; overflow-y:auto; margin-bottom:0.9rem; border:1px solid var(--border); border-radius:10px;"></div>
        <h4 style="font-size:0.9rem; margin-bottom:0.6rem;" id="prov-form-title">Añadir Taller</h4>
        <form id="proveedor-form">
            <input type="hidden" id="prov-id" value="0">
            <input type="hidden" id="prov-tipo" value="taller">
            <div class="grid-2">
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" id="prov-nombre" class="form-control" required placeholder="Ej: Talleres Moratalla / Mapfre / ITV Caravaca">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" id="prov-telefono" class="form-control" placeholder="Ej: 968 000 000">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Localidad</label>
                    <input type="text" id="prov-localidad" class="form-control" placeholder="Ej: Moratalla">
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" id="prov-direccion" class="form-control" placeholder="Opcional">
                </div>
            </div>
            <div class="form-group">
                <label>Notas</label>
                <input type="text" id="prov-notas" class="form-control" placeholder="Horario, persona de contacto, nº póliza...">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                <button type="button" class="btn btn-outline" onclick="resetProveedorForm()">Limpiar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CREACIÓN RÁPIDA DE PROVEEDOR DESDE TAREA -->
<div class="modal-backdrop" id="quick-proveedor-modal" style="z-index:1100;">
    <div class="modal-content" style="max-width:460px;">
        <div class="card-header" style="margin-bottom:0.7rem;">
            <div class="card-title"><i class="fa-solid fa-plus-circle" style="color:var(--primary)"></i> Nuevo Taller / Aseguradora / ITV</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closeQuickProveedorModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="quick-proveedor-form">
            <input type="hidden" id="qprov-target-select" value="vt-proveedor">
            <div class="grid-2">
                <div class="form-group">
                    <label>Tipo *</label>
                    <select id="qprov-tipo" class="form-control" required>
                        <option value="taller">Taller</option>
                        <option value="aseguradora">Aseguradora</option>
                        <option value="itv">ITV</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" id="qprov-nombre" class="form-control" required placeholder="Nombre...">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" id="qprov-telefono" class="form-control" placeholder="Opcional">
                </div>
                <div class="form-group">
                    <label>Localidad</label>
                    <input type="text" id="qprov-localidad" class="form-control" placeholder="Opcional">
                </div>
            </div>
            <div class="form-group">
                <label>Dirección</label>
                <input type="text" id="qprov-direccion" class="form-control" placeholder="Opcional">
            </div>
            <div class="form-group">
                <label>Notas</label>
                <input type="text" id="qprov-notas" class="form-control" placeholder="Opcional">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                <button type="button" class="btn btn-outline" onclick="closeQuickProveedorModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Crear y Seleccionar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL COMPARATIVA DE COSTES DE TAREAS RELACIONADAS -->
<div class="modal-backdrop" id="comparativa-tarea-modal">
    <div class="modal-content" style="max-width:680px;">
        <div class="card-header" style="margin-bottom:0.7rem;">
            <div class="card-title"><i class="fa-solid fa-chart-line" style="color:var(--primary)"></i> Comparativa de Costes (Tareas Relacionadas)</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closeComparativaTareaModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <p id="comp-tarea-subtitle" style="font-weight:700; color:var(--primary-dark); margin-bottom:0.75rem;"></p>
        <div id="comp-tarea-summary" style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-bottom:0.9rem;"></div>
        <div class="table-responsive" style="max-height:320px; overflow-y:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Km</th>
                        <th>Proveedor</th>
                        <th style="text-align:right">Coste (€)</th>
                        <th style="text-align:right">Dif. vs Anterior</th>
                        <th>Estado / Notas</th>
                    </tr>
                </thead>
                <tbody id="comp-tarea-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL SOLICITUDES DE REGISTRO PENDIENTES (SUPERUSUARIO) -->
<div class="modal-backdrop" id="pending-users-modal">
    <div class="modal-content" style="max-width:560px;">
        <div class="card-header" style="margin-bottom:0.8rem;">
            <div class="card-title"><i class="fa-solid fa-user-check" style="color:#d97706"></i> Solicitudes de Registro Pendientes</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closePendingUsersModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="pending-users-list" style="display:flex; flex-direction:column; gap:0.65rem; max-height:60vh; overflow-y:auto;"></div>
    </div>
</div>

<!-- MODAL PARQUES MÓVILES COMPARTIDOS -->
<div class="modal-backdrop" id="parques-modal">
    <div class="modal-content" style="max-width:680px;">
        <div class="card-header" style="margin-bottom:0.8rem;">
            <div class="card-title"><i class="fa-solid fa-people-group" style="color:var(--primary)"></i> Parques Móviles (Flotas Compartidas)</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closeParquesModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="parques-list" style="display:flex; flex-direction:column; gap:0.75rem; max-height:48vh; overflow-y:auto; margin-bottom:1rem;"></div>
        <div id="parque-admin-section" class="hidden">
            <hr style="border:none; border-top:1px solid var(--border); margin:0.9rem 0;">
            <h4 style="font-size:0.92rem; margin-bottom:0.65rem;" id="parque-form-title">Crear / Editar Parque Móvil</h4>
            <form id="parque-form">
                <input type="hidden" id="parque-id" value="0">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Nombre del Parque Móvil *</label>
                        <input type="text" id="parque-nombre" class="form-control" placeholder="Ej: Flota Ayuntamiento / Empresa" required>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <input type="text" id="parque-descripcion" class="form-control" placeholder="Opcional">
                    </div>
                </div>
                <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                    <button type="button" class="btn btn-outline" onclick="resetParqueForm()">Limpiar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Guardar Parque Móvil</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL PAPELERA / REACTIVAR O BORRAR DEFINITIVAMENTE -->
<div class="modal-backdrop" id="inactive-records-modal">
    <div class="modal-content" style="max-width:780px;">
        <div class="card-header" style="margin-bottom:0.6rem;">
            <div class="card-title"><i class="fa-solid fa-trash-arrow-up" style="color:#dc2626"></i> Papelera (Registros con activo = 0)</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closeInactiveRecordsModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="font-size:0.77rem; color:var(--muted); margin-bottom:0.55rem;">
            <i class="fa-solid fa-circle-info"></i> Selecciona con la casilla, <strong>Ctrl + clic</strong> para elegir uno a uno, o <strong>Shift + clic</strong> para seleccionar un bloque completo.
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem; flex-wrap:wrap; margin-bottom:0.75rem; padding:0.5rem 0.65rem; background:#f8fafc; border:1px solid var(--border); border-radius:10px;">
            <label style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.82rem; font-weight:600; cursor:pointer;">
                <input type="checkbox" id="inactive-select-all" onchange="toggleAllInactiveSelection(this.checked)">
                Seleccionar todo (<span id="inactive-selected-count">0</span>)
            </label>
            <div style="display:flex; gap:0.45rem; flex-wrap:wrap;">
                <button type="button" class="btn btn-primary" style="padding:0.36rem 0.68rem; font-size:0.78rem;" onclick="reactivateSelectedInactive()">
                    <i class="fa-solid fa-rotate-left"></i> Recuperar seleccionados
                </button>
                <button type="button" class="btn btn-danger" style="padding:0.36rem 0.68rem; font-size:0.78rem;" onclick="purgeSelectedInactive()">
                    <i class="fa-solid fa-trash-can"></i> Borrar definitivamente
                </button>
                <button type="button" class="btn btn-outline" style="padding:0.36rem 0.68rem; font-size:0.78rem;" onclick="reactivateRecord('all_repostajes', 0)">
                    <i class="fa-solid fa-rotate"></i> Recuperar todos los repostajes
                </button>
            </div>
        </div>
        <div id="inactive-records-list" style="display:flex; flex-direction:column; gap:0.45rem; max-height:55vh; overflow-y:auto; user-select:none;"></div>
    </div>
</div>

<!-- MODAL CORREO DE NOTIFICACIONES -->
<div class="modal-backdrop" id="user-email-modal">
    <div class="modal-content" style="max-width:420px;">
        <div class="card-header" style="margin-bottom:0.8rem;">
            <div class="card-title"><i class="fa-solid fa-envelope" style="color:#0284c7"></i> Correo para Avisos</div>
            <button class="btn btn-outline" style="padding:0.35rem 0.65rem;" onclick="closeUserEmailModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="user-email-form">
            <div class="form-group">
                <label>Correo electrónico del usuario</label>
                <input type="email" id="user-email-input" class="form-control" placeholder="tu@correo.com">
                <small style="color:var(--muted);">Se usará para enviarte confirmación de repostajes y avisos de tareas (&lt;1000 km o ≤15 días) desde moratalla-murcia.com.</small>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:0.6rem;">
                <button type="button" class="btn btn-outline" onclick="closeUserEmailModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar Correo</button>
            </div>
        </form>
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
function fmtDate(val) {
    if (!val) return '-';
    const m = String(val).trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${m[3]}-${m[2]}-${m[1]}` : String(val);
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

function toggleAuthMode(showRegister) {
    document.getElementById('login-error').classList.add('hidden');
    document.getElementById('login-success').classList.add('hidden');
    if (showRegister) {
        document.getElementById('login-form').classList.add('hidden');
        document.getElementById('register-form').classList.remove('hidden');
    } else {
        document.getElementById('register-form').classList.add('hidden');
        document.getElementById('login-form').classList.remove('hidden');
    }
}

document.getElementById('register-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const err = document.getElementById('login-error');
    const okBox = document.getElementById('login-success');
    err.classList.add('hidden');
    okBox.classList.add('hidden');

    const username = document.getElementById('reg-user').value.trim();
    const email    = document.getElementById('reg-email').value.trim();
    const password = document.getElementById('reg-pass').value;
    const pass2    = document.getElementById('reg-pass2').value;

    if (password !== pass2) {
        err.textContent = 'Las contraseñas no coinciden.';
        err.classList.remove('hidden');
        return;
    }

    try {
        const resp = await fetch('api.php?action=register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'register', username, email, password })
        });
        const res = await resp.json();
        if (res.success) {
            document.getElementById('register-form').reset();
            toggleAuthMode(false);
            okBox.textContent = res.message || 'Solicitud enviada. Pendiente de validación por el superusuario.';
            okBox.classList.remove('hidden');
        } else {
            err.textContent = res.error || 'No se pudo completar la solicitud.';
            err.classList.remove('hidden');
        }
    } catch (ex) {
        err.textContent = 'Error de conexión con el servidor: ' + ex.message;
        err.classList.remove('hidden');
    }
});

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
        document.getElementById('btn-pending-users').classList.remove('hidden');
    } else {
        document.getElementById('app-title-text').textContent = 'Control de Combustible';
        document.getElementById('label-fecha-form').textContent = 'Fecha *';
        document.getElementById('brand-icon-box').style.border = 'none';
        document.getElementById('btn-pending-users').classList.add('hidden');
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
    renderTareasTable();
    updateTasksAlertBadge();
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
        return (r.fecha && (r.fecha.toLowerCase().includes(q) || fmtDate(r.fecha).toLowerCase().includes(q))) ||
               (String(r.km_odometro).includes(q)) ||
               (r.notas && r.notas.toLowerCase().includes(q)) ||
               (r.vehiculo_nombre && r.vehiculo_nombre.toLowerCase().includes(q));
    });

    filtered.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="font-weight:600;">${fmtDate(r.fecha)}</td>
            <td><span class="badge badge-teal">${r.vehiculo_nombre || 'Vehículo'}</span></td>
            <td>${fmtNum(r.litros, 2)}</td>
            <td>${fmtNum(r.precio_litro, 3)} €${r.es_pesetas ? `<br><small style="color:var(--muted);">${fmtNum(r.precio_litro_pesetas, 1)} pts</small>` : ''}</td>
            <td style="font-weight:700; color:var(--primary-dark);">${fmtNum(r.importe_total, 2)} €${r.es_pesetas ? `<br><small style="color:var(--muted); font-weight:500;">${fmtNum(r.importe_total_pesetas, 0)} pts</small>` : ''}</td>
            <td style="font-weight:600;">${fmtNum(r.km_odometro, 0)}</td>
            <td>${r.dif_km > 0 ? '+' + fmtNum(r.dif_km, 0) : '-'}</td>
            <td>${r.litros_por_100 !== null ? `<span class="badge badge-amber">${fmtNum(r.litros_por_100, 2)}</span>` : '-'}</td>
            <td>${r.km_por_litro !== null ? fmtNum(r.km_por_litro, 2) : '-'}</td>
            <td>${r.euros_por_km !== null ? fmtNum(r.euros_por_km, 4) + ' €' : '-'}</td>
            <td>${fmtNum(r.total_litros_acum, 1)}</td>
            <td>${fmtNum(r.total_euros_acum, 2)} €</td>
            <td>${r.coste_medio_100_acum !== null ? fmtNum(r.coste_medio_100_acum, 2) : '-'}</td>
            <td class="wrap-col" style="text-align:left; max-width:130px;">${r.notas || ''}</td>
            <td class="col-actions">
                ${r.foto_ticket_path ? `<button class="btn btn-outline" style="padding:0.25rem 0.45rem; font-size:0.75rem; color:var(--primary);" onclick="openPhotoLightbox('${r.foto_ticket_path}', 'Factura / Surtidor (${r.fecha})')" title="Ver foto factura/surtidor"><i class="fa-solid fa-receipt"></i></button>` : ''}
                ${r.foto_odometro_path ? `<button class="btn btn-outline" style="padding:0.25rem 0.45rem; font-size:0.75rem; color:var(--accent);" onclick="openPhotoLightbox('${r.foto_odometro_path}', 'Odómetro (${r.fecha})')" title="Ver foto odómetro"><i class="fa-solid fa-gauge-high"></i></button>` : ''}
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
                    <strong style="font-size:0.92rem;">${fmtDate(r.fecha)}</strong>
                    <span class="badge badge-teal" style="margin-left:0.35rem;">${r.vehiculo_nombre || 'Vehículo'}</span>
                </div>
                <div style="font-weight:800; font-size:1.05rem; color:var(--primary-dark);">${fmtNum(r.importe_total, 2)} €${r.es_pesetas ? ` <small style="font-weight:500; color:var(--muted);">(${fmtNum(r.importe_total_pesetas, 0)} pts)</small>` : ''}</div>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.82rem; color:var(--muted);">
                <span><strong>${fmtNum(r.km_odometro, 0)} km</strong> (${r.dif_km > 0 ? '+' + fmtNum(r.dif_km, 0) + ' km' : 'Inicio'})</span>
                <span>${fmtNum(r.litros, 2)} L × ${fmtNum(r.precio_litro, 3)} €/L${r.es_pesetas ? ` (${fmtNum(r.precio_litro_pesetas, 1)} pts)` : ''}</span>
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
            <div style="display:flex; justify-content:flex-end; gap:0.4rem; margin-top:0.55rem; flex-wrap:wrap;">
                ${r.foto_ticket_path ? `<button class="btn btn-outline" style="padding:0.3rem 0.55rem; font-size:0.76rem; color:var(--primary);" onclick="openPhotoLightbox('${r.foto_ticket_path}', 'Factura / Surtidor (${r.fecha})')"><i class="fa-solid fa-receipt"></i> Ticket</button>` : ''}
                ${r.foto_odometro_path ? `<button class="btn btn-outline" style="padding:0.3rem 0.55rem; font-size:0.76rem; color:var(--accent);" onclick="openPhotoLightbox('${r.foto_odometro_path}', 'Odómetro (${r.fecha})')"><i class="fa-solid fa-gauge-high"></i> Odómetro</button>` : ''}
                <button class="btn btn-outline" style="padding:0.3rem 0.65rem; font-size:0.76rem;" onclick='editRepostaje(${JSON.stringify(r)})'><i class="fa-solid fa-pen"></i> Editar</button>
                <button class="btn btn-danger" style="padding:0.3rem 0.65rem; font-size:0.76rem;" onclick="deleteRepostaje(${r.id})"><i class="fa-solid fa-trash"></i> Baja</button>
            </div>
        `;
        cardsContainer.appendChild(card);
    });
}

function renderChart() {
    const ctx = document.getElementById('evolutionChart').getContext('2d');
    const hasDateFilter = Boolean(document.getElementById('filter-from')?.value || document.getElementById('filter-to')?.value);
    const allChrono = [...(currentData.repostajes || [])].reverse();
    const chronological = hasDateFilter ? allChrono : allChrono.slice(-50);
    const labels = chronological.map(r => fmtDate(r.fecha));
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
    document.getElementById('rep-foto-ticket').value = '';
    document.getElementById('rep-foto-odometro').value = '';
    document.getElementById('ocr-status').classList.add('hidden');
    document.getElementById('detected-station-banner').classList.add('hidden');
    lastDetectedStation = null;
    renderRepostajePhotosPreview();
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
    document.getElementById('rep-precio').value = (r.precio_litro_raw !== undefined && r.precio_litro_raw !== null) ? r.precio_litro_raw : r.precio_litro;
    document.getElementById('rep-importe').value = (r.importe_total_raw !== undefined && r.importe_total_raw !== null) ? r.importe_total_raw : r.importe_total;
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
    document.getElementById('rep-foto-ticket').value = r.foto_ticket_path || '';
    document.getElementById('rep-foto-odometro').value = r.foto_odometro_path || '';
    document.getElementById('ocr-status').classList.add('hidden');
    document.getElementById('detected-station-banner').classList.add('hidden');
    lastDetectedStation = null;
    renderRepostajePhotosPreview();
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
        notas: document.getElementById('rep-notas').value,
        foto_ticket_path: document.getElementById('rep-foto-ticket').value,
        foto_odometro_path: document.getElementById('rep-foto-odometro').value
    };
    const res = await apiCall('save_repostaje', payload);
    if (res.success) {
        closeRepostajeModal();
        await loadDashboard();
        if (res.email_sent) {
            const extra = res.alertas_count > 0 ? ` (incluye ${res.alertas_count} aviso(s) de mantenimiento)` : '';
            alert(`Repostaje guardado y correo enviado${extra}.`);
        }
    } else {
        alert(res.error || 'Error guardando repostaje');
    }
});

async function deleteRepostaje(id) {
    if (!confirm('¿Dar de baja este repostaje? (Se marcará como inactivo sin destruir el registro en la base de datos)')) return;
    const res = await apiCall('deactivate_repostaje', { id });
    if (res.success) await loadDashboard();
}

// GESTIÓN DE FOTOS Y ESCANEO OCR INTELIGENTE (FACTURA/TICKET, SURTIDOR, ODÓMETRO)
let lastDetectedStation = null;

function openPhotoLightbox(path, title) {
    if (!path) return;
    document.getElementById('lightbox-title').innerHTML = `<i class="fa-solid fa-image" style="color:var(--primary)"></i> ${title || 'Foto adjunta'}`;
    document.getElementById('lightbox-img').src = path;
    document.getElementById('photo-lightbox-modal').classList.add('active');
}

function closePhotoLightbox() {
    document.getElementById('photo-lightbox-modal').classList.remove('active');
    document.getElementById('lightbox-img').src = '';
}

function renderRepostajePhotosPreview() {
    const container = document.getElementById('rep-photos-preview');
    const ticketPath = document.getElementById('rep-foto-ticket').value;
    const odoPath = document.getElementById('rep-foto-odometro').value;
    container.innerHTML = '';

    if (!ticketPath && !odoPath) {
        container.classList.add('hidden');
        return;
    }
    container.classList.remove('hidden');

    if (ticketPath) {
        const card = document.createElement('div');
        card.className = 'photo-thumb-card';
        card.innerHTML = `
            <img src="${ticketPath}" alt="Factura/Surtidor" onclick="openPhotoLightbox('${ticketPath}', 'Factura / Surtidor')">
            <div>
                <div style="font-weight:700;">Factura / Surtidor</div>
                <div style="display:flex; gap:0.4rem; margin-top:0.2rem;">
                    <a href="javascript:void(0)" onclick="openPhotoLightbox('${ticketPath}', 'Factura / Surtidor')" style="color:var(--primary); font-weight:600;">Ver</a>
                    <a href="javascript:void(0)" onclick="removeRepostajePhoto('ticket')" style="color:var(--danger);">Quitar</a>
                </div>
            </div>
        `;
        container.appendChild(card);
    }

    if (odoPath) {
        const card = document.createElement('div');
        card.className = 'photo-thumb-card';
        card.innerHTML = `
            <img src="${odoPath}" alt="Odómetro" onclick="openPhotoLightbox('${odoPath}', 'Odómetro')">
            <div>
                <div style="font-weight:700;">Odómetro</div>
                <div style="display:flex; gap:0.4rem; margin-top:0.2rem;">
                    <a href="javascript:void(0)" onclick="openPhotoLightbox('${odoPath}', 'Odómetro')" style="color:var(--primary); font-weight:600;">Ver</a>
                    <a href="javascript:void(0)" onclick="removeRepostajePhoto('odometro')" style="color:var(--danger);">Quitar</a>
                </div>
            </div>
        `;
        container.appendChild(card);
    }
}

function removeRepostajePhoto(tipo) {
    if (tipo === 'odometro') {
        document.getElementById('rep-foto-odometro').value = '';
    } else {
        document.getElementById('rep-foto-ticket').value = '';
    }
    renderRepostajePhotosPreview();
}

function getAuthHeadersForFormData() {
    const headers = {};
    if (typeof authCreds !== 'undefined' && authCreds && authCreds.username && authCreds.password) {
        headers['Authorization'] = 'Basic ' + btoa(authCreds.username + ':' + authCreds.password);
    }
    return headers;
}

async function uploadRepostajePhoto(file, tipo) {
    const fd = new FormData();
    fd.append('tipo', tipo);
    fd.append('foto', file);
    if (typeof authCreds !== 'undefined' && authCreds && authCreds.username && authCreds.password) {
        fd.append('username', authCreds.username);
        fd.append('password', authCreds.password);
    }
    const res = await fetch(`api.php?action=upload_repostaje_foto`, {
        method: 'POST',
        headers: getAuthHeadersForFormData(),
        credentials: 'same-origin',
        body: fd
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Error subiendo foto');
    return data.path;
}

async function tryGeminiExtract(file, tipo) {
    try {
        const fd = new FormData();
        fd.append('tipo', tipo);
        fd.append('foto', file);
        if (typeof authCreds !== 'undefined' && authCreds && authCreds.username && authCreds.password) {
            fd.append('username', authCreds.username);
            fd.append('password', authCreds.password);
        }
        const res = await fetch(`api.php?action=extract_repostaje_ocr`, {
            method: 'POST',
            headers: getAuthHeadersForFormData(),
            credentials: 'same-origin',
            body: fd
        });
        const json = await res.json();
        if (json && json.success && json.data) {
            return json.data;
        }
    } catch (err) {
        console.warn('Gemini OCR fallback:', err);
    }
    return null;
}

function normalizeStr(s) {
    return (s || '')
        .toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function parseLocalizedNumber(raw) {
    if (!raw) return NaN;
    let s = String(raw).trim().replace(/[€\s]/g, '');
    if (s.includes(',') && s.includes('.')) {
        if (s.lastIndexOf(',') > s.lastIndexOf('.')) {
            s = s.replace(/\./g, '').replace(',', '.');
        } else {
            s = s.replace(/,/g, '');
        }
    } else {
        s = s.replace(',', '.');
    }
    return parseFloat(s);
}

function findBestFuelTriplet(candidates, hintImporte) {
    let best = null;
    let bestScore = -Infinity;
    const prices = candidates.filter(n => n >= 0.75 && n <= 3.00);
    const others = candidates.filter(n => n > 2.50 && n <= 500.0);

    for (const p of prices) {
        for (const l of others) {
            for (const imp of others) {
                if (l === imp) continue;
                const prod = l * p;
                const diff = Math.abs(prod - imp);
                const tol = Math.max(0.12, imp * 0.015);
                if (diff <= tol && (p < 1.05 ? imp > l * 0.5 : imp > l)) {
                    let score = 100 - diff * 100;
                    if (hintImporte && Math.abs(imp - hintImporte) < 0.05) score += 80;
                    if (String(p).includes('.') && p.toFixed(3) === String(p)) score += 10;
                    if (score > bestScore) {
                        bestScore = score;
                        best = { litros: l, precio_litro: p, importe_total: imp };
                    }
                }
            }
        }
    }
    return best;
}

function extractStationFromText(text) {
    const lines = text.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
    // Limitar búsqueda al bloque del emisor (antes de CLIENTE solo si CLIENTE está en su propia línea, o antes de ARTICULO/PRODUCTO)
    let stopIdx = lines.findIndex(l => /^CLIENTE\b/i.test(l) || /\b(ARTICULO|PRODUCTO|CANTIDAD)\b/i.test(l));
    const headerLines = stopIdx > 0 ? lines.slice(0, stopIdx) : lines.slice(0, 18);

    let nombre = '';
    const brandRe = /\b(E\.?[ \t]*S\.?[ \t]+[A-ZÁÉÍÓÚÑ0-9\.\,\- \t]{3,45}|ESTACI[ÓO]N[ \t]+DE[ \t]+SERVICIO[ \t]+[A-ZÁÉÍÓÚÑ0-9\.\,\- \t]{3,45}|REPSOL|CEPSA|BP|GALP|SHELL|PLENOIL|BALLENOIL|PETROPRIX|MOEVE|AVIA|ALCAMPO|CARREFOUR|BONAREA|Q8|TAMOIL)/i;
    for (const l of headerLines) {
        const m = l.match(brandRe);
        if (m) {
            nombre = m[1].replace(/\s+/g, ' ').trim();
            // Si la línea tiene columnas (Emisor | Cliente), cortar si aparece el nombre del cliente
            nombre = nombre.replace(/\s+Inv\.?Des\.?.*$/i, '').trim();
            break;
        }
    }
    if (!nombre) {
        const candidateLine = headerLines.find(l => l.length >= 4 && !/^(FACTURA|NUM\.|FECHA|EMISOR|PAG|ALBARAN)/i.test(l) && !/^\d+$/.test(l));
        if (candidateLine) nombre = candidateLine;
    }

    const headerBlock = headerLines.join('\n');
    const cifMatch = headerBlock.match(/\b([A-Z]\-?\d{8}|[A-Z]\d{7}[A-Z])\b/);
    const cif = cifMatch ? cifMatch[1].replace('-', '') : '';

    const dirLine = headerLines.find(l => /\b(CTRA|CARRETERA|AVDA|AVENIDA|C\/|CALLE|POLIGONO|POL\.|KM\.?)\b/i.test(l)) || '';
    const direccion = dirLine.replace(/\s+\d{5}\s+.*$/, '').trim();

    let localidad = '';
    for (const l of headerLines) {
        const cpMatch = l.match(/\b(\d{5})[ \t]+([A-ZÁÉÍÓÚÑ]+(?:[ \t]+[A-ZÁÉÍÓÚÑ]+)?)/i);
        if (cpMatch) {
            localidad = `${cpMatch[1]} ${cpMatch[2].trim()}`;
            break;
        }
    }
    if (!localidad) {
        const locLine = headerLines.find(l => /\b(TOTANA|MURCIA|LORCA|CARAVACA|MORATALLA)\b/i.test(l));
        if (locLine) localidad = locLine.trim();
    }

    if (!nombre && !cif && !direccion) return null;
    return {
        nombre: nombre || ('Estación ' + cif),
        marca: nombre,
        cif,
        direccion,
        localidad
    };
}

function parseFuelInvoiceText(text) {
    const lines = text.split(/\r?\n/).map(l => l.trim()).filter(Boolean);

    // 1. Fecha (priorizar línea FECHA, evitar VENCIMIENTO)
    let fecha = null;
    const fechaLine = lines.find(l => /\bFECHA\b/i.test(l) && !/VENCIMIENTO/i.test(l));
    const dateRegex = /(\d{2})[\/\-\.](\d{2})[\/\-\.](20\d{2})/;
    const mLine = fechaLine ? fechaLine.match(dateRegex) : null;
    const mAny = text.match(dateRegex);
    const dm = mLine || mAny;
    if (dm) {
        fecha = `${dm[3]}-${dm[2]}-${dm[1]}`;
    }

    // 2. Importe Total por palabra clave (evitando BASE, IVA, ESTATALES)
    let hintImporte = null;
    for (const l of lines) {
        if (/\b(TOTAL\s*EUROS|TOTAL\s*FACTURA|TOTAL\s*PAGAR|IMPORTE\s*TOTAL|TOTAL\s*COBRADO|IMPORTE\s*PAGADO)\b/i.test(l)) {
            const nums = [...l.matchAll(/(\d{1,3}(?:[,\.]\d{2}))/g)].map(m => parseLocalizedNumber(m[1])).filter(n => !isNaN(n) && n > 0);
            if (nums.length > 0) {
                hintImporte = nums[nums.length - 1];
                break;
            }
        }
    }

    // 3. Recoger números candidatos excluyendo líneas fiscales/ruido
    const ignoredLineRe = /\b(BASE|IMPONIBLE|IVA|CUOTA|ESTATALES|AUTONOMIC|IMPUESTO|EUX1000|TURNO|TELF|TELEFONO|N\.?I\.?F|C\.?I\.?F|KM\.?\s*\d|REGISTRO|TOMO|FOLIO|HOJA)\b/i;
    const candidateNums = [];
    for (const l of lines) {
        if (ignoredLineRe.test(l)) continue;
        const matches = [...l.matchAll(/(\d{1,3}[,\.]\d{2,3})/g)];
        for (const m of matches) {
            const val = parseLocalizedNumber(m[1]);
            if (!isNaN(val) && val > 0) candidateNums.push(val);
        }
    }

    let triplet = findBestFuelTriplet(candidateNums, hintImporte);

    // Fallback: intentar con todos los decimales si no hubo match estricto
    if (!triplet) {
        const allNums = [...text.matchAll(/(\d{1,3}[,\.]\d{2,3})/g)]
            .map(m => parseLocalizedNumber(m[1]))
            .filter(n => !isNaN(n) && n > 0);
        triplet = findBestFuelTriplet(allNums, hintImporte);
    }

    // 4. Estación de servicio
    const estacion = extractStationFromText(text);

    return {
        fecha,
        litros: triplet ? triplet.litros : null,
        precio_litro: triplet ? triplet.precio_litro : null,
        importe_total: triplet ? triplet.importe_total : hintImporte,
        estacion
    };
}

function parsePumpDisplayText(text) {
    const rawNums = [...text.matchAll(/(\d{1,4}[,\.]\d{1,3})/g)]
        .map(m => parseLocalizedNumber(m[1]))
        .filter(n => !isNaN(n) && n > 0 && n < 1000);

    // En surtidores el precio/L puede venir en €/L (1.699) o en céntimos/L (169.9 o 212.9)
    const expanded = [...rawNums];
    for (const n of rawNums) {
        if (n >= 75 && n <= 300) {
            expanded.push(parseFloat((n / 100).toFixed(4)));
        }
    }

    const triplet = findBestFuelTriplet(expanded, null);
    if (triplet) return triplet;

    // Si solo hay 2 números (Importe y Litros), inferir precio/L en rango [0.75, 3.00]
    const sorted = [...rawNums].sort((a, b) => b - a);
    for (let i = 0; i < sorted.length; i++) {
        for (let j = i + 1; j < sorted.length; j++) {
            const imp = sorted[i];
            const lit = sorted[j];
            if (lit > 1 && imp > lit) {
                const ratio = imp / lit;
                if (ratio >= 0.75 && ratio <= 3.00) {
                    return {
                        importe_total: imp,
                        litros: lit,
                        precio_litro: parseFloat(ratio.toFixed(3))
                    };
                }
            }
        }
    }
    return { litros: null, precio_litro: null, importe_total: null };
}

function parseOdometerText(text) {
    const clean = text.replace(/(\d)\s+(\d)/g, '$1$2');
    const matches = [...clean.matchAll(/\b(\d{4,7})\b/g)]
        .map(m => m[1])
        .filter(s => !/^202\d$/.test(s)); // evitar confundir años 2020-2029 si hay otro número
    if (matches.length === 0) return null;
    matches.sort((a, b) => b.length - a.length);
    return parseFloat(matches[0]);
}

function matchOrOfferStation(stationInfo) {
    const banner = document.getElementById('detected-station-banner');
    banner.classList.add('hidden');
    lastDetectedStation = null;

    if (!stationInfo || !stationInfo.nombre) return;

    const normTarget = normalizeStr(stationInfo.nombre);
    const estaciones = currentData.estaciones || [];
    let matched = null;

    for (const est of estaciones) {
        const normEst = normalizeStr(est.nombre);
        if (!normEst) continue;
        if (normEst === normTarget || normEst.includes(normTarget) || normTarget.includes(normEst)) {
            matched = est;
            break;
        }
        // Coincidencia por palabras clave (>=2 palabras significativas)
        const wordsT = normTarget.split(' ').filter(w => w.length >= 3 && !['estacion', 'servicio', 'slu'].includes(w));
        const wordsE = normEst.split(' ').filter(w => w.length >= 3);
        const common = wordsT.filter(w => wordsE.includes(w));
        if (common.length >= 2) {
            matched = est;
            break;
        }
    }

    const estSel = document.getElementById('rep-estacion');
    if (matched) {
        estSel.value = matched.nombre;
        return;
    }

    // No existe todavía en veh_estaciones: ofrecer crear y asociar en 1 clic
    lastDetectedStation = stationInfo;
    const details = [stationInfo.localidad, stationInfo.direccion, stationInfo.cif ? `CIF: ${stationInfo.cif}` : ''].filter(Boolean).join(' · ');
    banner.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem; flex-wrap:wrap;">
            <div>
                <strong><i class="fa-solid fa-shop"></i> Estación detectada:</strong> ${stationInfo.nombre}
                ${details ? `<div style="font-size:0.74rem; opacity:0.85;">${details}</div>` : ''}
            </div>
            <button type="button" class="btn btn-primary" style="padding:0.3rem 0.65rem; font-size:0.75rem;" onclick="createAndSelectDetectedStation()">
                <i class="fa-solid fa-plus"></i> Crear y asociar
            </button>
        </div>
    `;
    banner.classList.remove('hidden');
}

async function createAndSelectDetectedStation() {
    if (!lastDetectedStation || !lastDetectedStation.nombre) return;
    const s = lastDetectedStation;
    const notas = [s.cif ? `CIF: ${s.cif}` : '', s.telefono ? `Tel: ${s.telefono}` : ''].filter(Boolean).join(' · ');
    const res = await apiCall('save_estacion', {
        id: 0,
        nombre: s.nombre,
        marca: s.marca || s.nombre,
        localidad: s.localidad || '',
        direccion: s.direccion || '',
        notas
    });
    if (res.success) {
        const estSel = document.getElementById('rep-estacion');
        const opt = document.createElement('option');
        opt.value = s.nombre;
        opt.textContent = s.nombre + (s.localidad ? ` (${s.localidad})` : '');
        estSel.appendChild(opt);
        estSel.value = s.nombre;
        currentData.estaciones = currentData.estaciones || [];
        currentData.estaciones.push({ id: res.estacion_id, nombre: s.nombre, localidad: s.localidad || '', direccion: s.direccion || '' });
        document.getElementById('detected-station-banner').classList.add('hidden');
    } else {
        alert(res.error || 'No se pudo crear la estación.');
    }
}

async function scanFuelPhoto(input, modo) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const status = document.getElementById('ocr-status');
    status.classList.remove('hidden');
    status.textContent = modo === 'surtidor'
        ? '⛽ Subiendo foto del surtidor y extrayendo datos...'
        : '🧾 Subiendo factura/ticket y extrayendo datos...';

    try {
        // 1. Subir y guardar la foto asociada al repostaje
        const savedPath = await uploadRepostajePhoto(file, 'ticket');
        document.getElementById('rep-foto-ticket').value = savedPath;
        renderRepostajePhotosPreview();

        // 2. Intentar extracción con Gemini Vision (si está configurado) o Tesseract inteligente
        let extracted = await tryGeminiExtract(file, modo);
        let engineLabel = 'IA Vision';

        if (!extracted) {
            engineLabel = 'OCR';
            const lang = modo === 'surtidor' ? 'eng' : 'spa';
            const result = await Tesseract.recognize(file, lang);
            const text = result.data.text || '';
            extracted = (modo === 'surtidor') ? parsePumpDisplayText(text) : parseFuelInvoiceText(text);
        }

        if (extracted.fecha) {
            document.getElementById('rep-fecha').value = extracted.fecha;
        }
        if (extracted.precio_litro) {
            document.getElementById('rep-precio').value = Number(extracted.precio_litro).toFixed(3);
        }
        if (extracted.litros) {
            document.getElementById('rep-litros').value = Number(extracted.litros).toFixed(2);
        }
        if (extracted.importe_total) {
            document.getElementById('rep-importe').value = Number(extracted.importe_total).toFixed(2);
        }
        if (extracted.estacion) {
            matchOrOfferStation(extracted.estacion);
        }

        const gotAny = extracted.litros || extracted.precio_litro || extracted.importe_total;
        status.textContent = gotAny
            ? `✅ Foto guardada y datos extraídos (${engineLabel}). Revisa y confirma los valores.`
            : `📸 Foto guardada. No se pudieron leer todos los números automáticamente; complétalos manualmente.`;
    } catch (e) {
        status.textContent = '⚠️ Error procesando la foto: ' + e.message;
    } finally {
        input.value = '';
    }
}

async function scanOdometerOCR(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const status = document.getElementById('ocr-status');
    status.classList.remove('hidden');
    status.textContent = '🔍 Subiendo foto del odómetro y leyendo kilómetros...';

    try {
        const savedPath = await uploadRepostajePhoto(file, 'odometro');
        document.getElementById('rep-foto-odometro').value = savedPath;
        renderRepostajePhotosPreview();

        let km = null;
        const gem = await tryGeminiExtract(file, 'odometro');
        if (gem && gem.km_odometro) {
            km = gem.km_odometro;
        } else {
            const result = await Tesseract.recognize(file, 'eng');
            km = parseOdometerText(result.data.text || '');
        }

        if (km) {
            document.getElementById('rep-km').value = km;
            updateParcialKmPreview();
            status.textContent = `✅ Foto guardada y kilometraje detectado: ${km} km`;
        } else {
            status.textContent = '📸 Foto del odómetro guardada. No se detectaron dígitos claros; introdúcelos manualmente.';
        }
    } catch (e) {
        status.textContent = '⚠️ Error al procesar imagen del odómetro: ' + e.message;
    } finally {
        input.value = '';
    }
}

// GESTIÓN DE VEHÍCULOS
function populateVehicleParqueSelect(selectedId = '') {
    const sel = document.getElementById('veh-parque');
    if (!sel) return;
    sel.innerHTML = '<option value="">Personal (solo mi usuario)</option>';
    (currentData.parques || []).forEach(pq => {
        const opt = document.createElement('option');
        opt.value = String(pq.id);
        opt.textContent = `Parque Móvil: ${pq.nombre}`;
        if (String(selectedId || '') === String(pq.id)) opt.selected = true;
        sel.appendChild(opt);
    });
}
function openVehiclesModal() {
    populateVehicleParqueSelect();
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
        const parqueBadge = v.parque_nombre
            ? `<span class="badge" style="background:#e0f2fe; color:#0369a1; margin-left:4px;"><i class="fa-solid fa-people-group"></i> ${v.parque_nombre}</span>`
            : `<span class="badge" style="background:#f1f5f9; color:#475569; margin-left:4px;">Personal</span>`;
        div.innerHTML = `
            <div>
                <div style="font-weight:700;">${v.nombre} ${v.matricula ? `<span class="badge badge-teal">${v.matricula}</span>` : ''}${parqueBadge}</div>
                <div style="font-size:0.78rem; color:var(--muted);">${v.tipo_combustible || 'Diésel'} · ${v.modelo || ''}${v.propietario ? ` · Propietario: ${v.propietario}` : ''}</div>
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
    populateVehicleParqueSelect(v.parque_id || '');
}
function resetVehicleForm() {
    document.getElementById('veh-id').value = '0';
    document.getElementById('veh-nombre').value = '';
    document.getElementById('veh-matricula').value = '';
    document.getElementById('veh-modelo').value = '';
    populateVehicleParqueSelect('');
}
document.getElementById('vehicle-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await apiCall('save_vehiculo', {
        id: document.getElementById('veh-id').value,
        parque_id: document.getElementById('veh-parque').value || null,
        nombre: document.getElementById('veh-nombre').value,
        matricula: document.getElementById('veh-matricula').value,
        modelo: document.getElementById('veh-modelo').value,
        tipo_combustible: document.getElementById('veh-combustible').value
    });
    if (res.success) {
        resetVehicleForm();
        await loadDashboard();
        populateVehicleParqueSelect();
        renderVehiclesList();
    } else if (res.error) {
        alert(res.error);
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

function parseImportNum(val) {
    if (val === null || val === undefined || val === '') return 0;
    if (typeof val === 'number') return isFinite(val) ? val : 0;
    let s = String(val).replace(/[^0-9,.\-]/g, '');
    if (!s) return 0;
    if (s.includes(',') && s.includes('.')) {
        if (s.lastIndexOf(',') > s.lastIndexOf('.')) {
            s = s.replace(/\./g, '').replace(',', '.');
        } else {
            s = s.replace(/,/g, '');
        }
    } else {
        s = s.replace(',', '.');
    }
    const n = parseFloat(s);
    return isFinite(n) ? n : 0;
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
        const lNum = cLitros >= 0 ? parseImportNum(r[cLitros]) : 0;
        const pNum = cPrecio >= 0 ? parseImportNum(r[cPrecio]) : 0;
        let iNum = cImporte >= 0 ? parseImportNum(r[cImporte]) : 0;
        if (iNum <= 0 && lNum > 0 && pNum > 0) {
            iNum = Math.round(lNum * pNum * 100) / 100;
        }
        mapped.push({
            fecha: fVal,
            litros: lNum > 0 ? lNum.toFixed(3) : '',
            precio_litro: pNum > 0 ? pNum.toFixed(4) : '',
            importe_total: iNum > 0 ? iNum.toFixed(2) : '0.00',
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
        const lVal = parseImportNum(m.litros);
        const pVal = parseImportNum(m.precio_litro);
        const iVal = parseImportNum(m.importe_total);
        tr.innerHTML = `
            <td>${m.fecha}</td>
            <td>${lVal > 0 ? fmtNum(lVal, 2) : '-'}</td>
            <td>${pVal > 0 ? fmtNum(pVal, 3) : '-'}</td>
            <td style="font-weight:700;">${fmtNum(iVal, 2)} €</td>
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

async function sendJsPdfByEmail(doc, title, summary, filename) {
    const pdfBase64 = doc.output('datauristring');
    const res = await apiCall('send_report_email', {
        title,
        summary,
        filename,
        pdf_base64: pdfBase64
    });
    alert(res.message || res.error || 'Operación completada');
}

async function exportPDFReport(sendByEmail = false) {
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
        fmtDate(r.fecha),
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
        alternateRowStyles: { fillColor: [248, 250, 252] }
    });

    const totalPages = doc.internal.getNumberOfPages();
    const nowFooterStr = new Date().toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    for (let p = 1; p <= totalPages; p++) {
        doc.setPage(p);
        doc.setFontSize(7.5);
        doc.setTextColor(120, 120, 120);
        doc.text(
            `Página ${p} de ${totalPages} · generado y enviado por www.moratalla-murcia.com (${nowFooterStr})`,
            105,
            291,
            { align: 'center' }
        );
    }

    closePdfOptionsModal();
    const fileName = `Informe_Combustible_A4_${new Date().toISOString().slice(0,10)}.pdf`;
    if (sendByEmail) {
        const summary = `Ámbito: ${selText}${rangeLabel}\nRepostajes: ${filteredList.length}\nConsumo medio: ${fmtNum(cMed, 2)} L/100km\nTotal: ${fmtNum(totalLitros, 2)} L (${fmtNum(totalEuros, 2)} €)`;
        await sendJsPdfByEmail(doc, `Informe de Combustible - ${selText}`, summary, fileName);
    } else {
        doc.save(fileName);
    }
}

// ============================================================================
// MAESTRO DE TAREAS, TAREAS POR VEHÍCULO, INFORMES PDF Y AVISOS POR CORREO
// ============================================================================
function updateTasksAlertBadge() {
    const badge = document.getElementById('tasks-alert-badge');
    if (!badge) return;
    const count = (currentData.vehiculo_tareas || []).filter(t => t.en_alerta).length;
    if (count > 0) {
        badge.textContent = String(count);
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

function getFilteredVehiculoTareas() {
    const estado = document.getElementById('tasks-filter-estado').value || 'todas';
    const q = (document.getElementById('tasks-search-input').value || '').toLowerCase().trim();
    return (currentData.vehiculo_tareas || []).filter(t => {
        if (estado === 'pendiente' && t.estado !== 'pendiente') return false;
        if (estado === 'finalizada' && t.estado !== 'finalizada') return false;
        if (estado === 'proximas' && !(t.estado === 'pendiente' && t.en_alerta)) return false;
        if (q) {
            const text = `${t.vehiculo_nombre || ''} ${t.descripcion || ''} ${t.notas || ''} ${t.motivo_alerta || ''}`.toLowerCase();
            if (!text.includes(q)) return false;
        }
        return true;
    });
}

function renderTareasTable() {
    const tbody = document.getElementById('tareas-tbody');
    if (!tbody) return;
    const list = getFilteredVehiculoTareas();
    tbody.innerHTML = '';
    if (list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:1.5rem; color:var(--muted);">No hay tareas que coincidan con el filtro.</td></tr>';
        return;
    }
    const provTypeLabels = { taller: 'Taller', aseguradora: 'Aseguradora', itv: 'ITV' };
    list.forEach(t => {
        const tr = document.createElement('tr');
        const perParts = [];
        if (t.tipo_periodicidad !== 'unica') {
            if (t.periodo_dias) perParts.push(`${t.periodo_dias} d`);
            if (t.periodo_km) perParts.push(`${fmtNum(t.periodo_km, 0)} km`);
        }
        const perText = t.tipo_periodicidad === 'unica' ? 'Única / Reparación' : (perParts.join(' / ') || 'Única');
        const garantiaHtml = t.garantia_meses
            ? `<br><small style="color:#0369a1; font-weight:600;">🛡️ Garantía: ${t.garantia_meses} meses${t.garantia_hasta ? ` (hasta ${t.garantia_hasta})` : ''}</small>`
            : '';

        let badgeHtml = '';
        if (t.estado === 'finalizada') {
            badgeHtml = `<span style="background:#e2e8f0; color:#334155; padding:0.2rem 0.5rem; border-radius:6px; font-size:0.75rem; font-weight:700;">✅ Finalizada (${t.fecha_finalizacion || ''})</span>`;
        } else if (t.tipo_periodicidad === 'unica') {
            badgeHtml = `<span style="background:#e0f2fe; color:#0369a1; padding:0.2rem 0.5rem; border-radius:6px; font-size:0.75rem; font-weight:700;">🔧 Reparación / Única</span>`;
        } else if (t.vencida) {
            badgeHtml = `<span style="background:#fee2e2; color:#b91c1c; padding:0.2rem 0.5rem; border-radius:6px; font-size:0.75rem; font-weight:700;">🚨 Vencida: ${t.motivo_alerta}</span>`;
        } else if (t.en_alerta) {
            badgeHtml = `<span style="background:#fef3c7; color:#b45309; padding:0.2rem 0.5rem; border-radius:6px; font-size:0.75rem; font-weight:700;">⚠️ Próxima: ${t.motivo_alerta}</span>`;
        } else {
            const rem = [];
            if (t.dias_restantes !== null) rem.push(`${t.dias_restantes} días`);
            if (t.km_restantes !== null) rem.push(`${fmtNum(t.km_restantes, 0)} km`);
            badgeHtml = `<span style="background:#dcfce7; color:#15803d; padding:0.2rem 0.5rem; border-radius:6px; font-size:0.75rem; font-weight:700;">✓ En plazo (${rem.join(' / ') || 'OK'})</span>`;
        }

        let provCostHtml = '';
        if (t.proveedor_nombre) {
            const pLbl = provTypeLabels[t.proveedor_tipo] || 'Proveedor';
            provCostHtml += `<span style="background:#e0f2fe; color:#0369a1; padding:0.12rem 0.4rem; border-radius:5px; font-size:0.72rem; font-weight:700;">${pLbl}</span> <strong>${t.proveedor_nombre}</strong>`;
        }
        if (t.coste !== null && t.coste !== undefined && t.coste !== '') {
            const costeNum = Number(t.coste);
            provCostHtml += `${provCostHtml ? '<br>' : ''}<strong>${fmtNum(costeNum, 2)} €</strong>`;
            if (t.diferencia_coste !== null && t.diferencia_coste !== undefined) {
                const diff = Number(t.diferencia_coste);
                const sign = diff > 0 ? '+' : '';
                const color = diff > 0 ? '#dc2626' : (diff < 0 ? '#16a34a' : 'var(--muted)');
                const pctStr = (t.porcentaje_coste !== null && t.porcentaje_coste !== undefined) ? ` (${sign}${fmtNum(t.porcentaje_coste, 1)}%)` : '';
                provCostHtml += ` <small style="color:${color}; font-weight:700;">${sign}${fmtNum(diff, 2)} €${pctStr}</small>`;
            }
        }
        if (!provCostHtml) provCostHtml = '<span style="color:var(--muted);">-</span>';

        const compareBtn = (Number(t.relacionadas_count || 0) > 1 || (t.coste !== null && t.coste !== undefined))
            ? `<button class="btn btn-outline" style="padding:0.25rem 0.5rem; font-size:0.75rem; color:#0284c7; border-color:#bae6fd;" onclick='openComparativaTareaModal(${JSON.stringify(t)})' title="Comparar costes de tareas relacionadas"><i class="fa-solid fa-chart-line"></i></button> `
            : '';

        const actionsHtml = t.estado === 'pendiente'
            ? `${compareBtn}<button class="btn btn-outline" style="padding:0.25rem 0.5rem; font-size:0.75rem; color:#15803d; border-color:#86efac;" onclick='openCompleteTareaModal(${JSON.stringify(t)})' title="Finalizar tarea"><i class="fa-solid fa-check"></i></button>
               <button class="btn btn-outline" style="padding:0.25rem 0.5rem; font-size:0.75rem;" onclick='editVehiculoTarea(${JSON.stringify(t)})' title="Editar"><i class="fa-solid fa-pen"></i></button>
               <button class="btn btn-outline" style="padding:0.25rem 0.5rem; font-size:0.75rem; color:var(--danger);" onclick="deactivateVehiculoTarea(${t.id})" title="Baja"><i class="fa-solid fa-trash"></i></button>`
            : `${compareBtn}<button class="btn btn-outline" style="padding:0.25rem 0.5rem; font-size:0.75rem; color:#d97706; border-color:#fcd34d;" onclick="reopenVehiculoTarea(${t.id})" title="Deshacer finalización (volver a pendiente)"><i class="fa-solid fa-rotate-left"></i></button>
               <button class="btn btn-outline" style="padding:0.25rem 0.5rem; font-size:0.75rem; color:var(--danger);" onclick="deactivateVehiculoTarea(${t.id})" title="Baja"><i class="fa-solid fa-trash"></i></button>`;

        tr.innerHTML = `
            <td><strong>${t.vehiculo_nombre || ''}</strong></td>
            <td class="wrap-col"><strong>${t.descripcion}</strong>${garantiaHtml}${t.notas ? `<br><small style="color:var(--muted)">${t.notas}</small>` : ''}</td>
            <td>${perText}${t.tipo_periodicidad === 'ambos' && t.avisar_por_tiempo_si_no_km ? '<br><small style="color:var(--primary)">Aviso por tiempo sin km</small>' : ''}</td>
            <td>${t.ultima_fecha || '-'}<br><strong>Próx: ${t.proxima_fecha || '-'}</strong></td>
            <td>${t.ultimo_km !== null ? fmtNum(t.ultimo_km, 0) + ' km' : '-'}<br><strong>Próx: ${t.proximo_km !== null ? fmtNum(t.proximo_km, 0) + ' km' : '-'}</strong></td>
            <td class="wrap-col">${provCostHtml}</td>
            <td class="wrap-col">${badgeHtml}</td>
            <td class="col-actions">${actionsHtml}</td>
        `;
        tbody.appendChild(tr);
    });
}

function openTareasMaestroModal() {
    renderTmList();
    resetTmForm();
    document.getElementById('tareas-maestro-modal').classList.add('active');
}
function closeTareasMaestroModal() {
    document.getElementById('tareas-maestro-modal').classList.remove('active');
}
function toggleTmPeriodoFields() {
    const tipo = document.getElementById('tm-tipo').value;
    document.getElementById('tm-dias-wrap').style.display = (tipo === 'kilometros' || tipo === 'unica') ? 'none' : '';
    document.getElementById('tm-km-wrap').style.display = (tipo === 'tiempo' || tipo === 'unica') ? 'none' : '';
}
function resetTmForm() {
    document.getElementById('tm-id').value = '0';
    document.getElementById('tm-nombre').value = '';
    document.getElementById('tm-descripcion').value = '';
    document.getElementById('tm-tipo').value = 'ambos';
    document.getElementById('tm-dias').value = '';
    document.getElementById('tm-km').value = '';
    document.getElementById('tm-avisar-tiempo').checked = true;
    toggleTmPeriodoFields();
}
function renderTmList() {
    const el = document.getElementById('tm-list');
    const list = currentData.tareas_maestro || [];
    if (list.length === 0) {
        el.innerHTML = '<div style="padding:0.75rem; color:var(--muted); text-align:center;">No hay tareas en el maestro.</div>';
        return;
    }
    el.innerHTML = list.map(t => `
        <div style="display:flex; justify-content:space-between; align-items:center; padding:0.45rem 0.6rem; border-bottom:1px solid var(--border); gap:0.5rem;">
            <div>
                <strong>${t.nombre}</strong>
                <small style="color:var(--muted); display:block;">${t.tipo_periodicidad} ${t.periodo_dias ? '· ' + t.periodo_dias + ' días' : ''} ${t.periodo_km ? '· ' + fmtNum(t.periodo_km, 0) + ' km' : ''}</small>
            </div>
            <div style="display:flex; gap:0.3rem;">
                <button type="button" class="btn btn-outline" style="padding:0.2rem 0.5rem; font-size:0.75rem;" onclick='editTm(${JSON.stringify(t)})'><i class="fa-solid fa-pen"></i></button>
                <button type="button" class="btn btn-outline" style="padding:0.2rem 0.5rem; font-size:0.75rem; color:var(--danger);" onclick="deleteTm(${t.id})"><i class="fa-solid fa-trash"></i></button>
            </div>
        </div>
    `).join('');
}
function editTm(t) {
    document.getElementById('tm-id').value = t.id;
    document.getElementById('tm-nombre').value = t.nombre || '';
    document.getElementById('tm-descripcion').value = t.descripcion || '';
    document.getElementById('tm-tipo').value = t.tipo_periodicidad || 'ambos';
    document.getElementById('tm-dias').value = t.periodo_dias || '';
    document.getElementById('tm-km').value = t.periodo_km || '';
    document.getElementById('tm-avisar-tiempo').checked = Boolean(Number(t.avisar_por_tiempo_si_no_km));
    toggleTmPeriodoFields();
}
document.getElementById('tm-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await apiCall('save_tarea_maestro', {
        id: document.getElementById('tm-id').value,
        nombre: document.getElementById('tm-nombre').value,
        descripcion: document.getElementById('tm-descripcion').value,
        tipo_periodicidad: document.getElementById('tm-tipo').value,
        periodo_dias: document.getElementById('tm-dias').value,
        periodo_km: document.getElementById('tm-km').value,
        avisar_por_tiempo_si_no_km: document.getElementById('tm-avisar-tiempo').checked ? 1 : 0
    });
    if (res.success) {
        await loadDashboard();
        renderTmList();
        resetTmForm();
    } else {
        alert(res.error || 'Error guardando tarea maestra');
    }
});
async function deleteTm(id) {
    if (!confirm('¿Dar de baja esta tarea del maestro?')) return;
    const res = await apiCall('deactivate_tarea_maestro', { id });
    if (res.success) {
        await loadDashboard();
        renderTmList();
    }
}

function toggleVtPeriodoFields() {
    const tipo = document.getElementById('vt-tipo').value;
    document.getElementById('vt-dias-wrap').style.display = (tipo === 'kilometros' || tipo === 'unica') ? 'none' : '';
    document.getElementById('vt-km-wrap').style.display = (tipo === 'tiempo' || tipo === 'unica') ? 'none' : '';
    const avisarWrap = document.getElementById('vt-avisar-wrap');
    if (avisarWrap) avisarWrap.style.display = (tipo === 'unica') ? 'none' : '';
    if (tipo === 'unica') {
        document.getElementById('vt-dias').value = '';
        document.getElementById('vt-km').value = '';
    }
}
function populateProveedorSelect(selectId, selectedId = '0') {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '<option value="0">-- Sin especificar --</option>';
    const groups = [
        { tipo: 'taller', label: 'Talleres' },
        { tipo: 'aseguradora', label: 'Aseguradoras' },
        { tipo: 'itv', label: 'ITVs' }
    ];
    const provs = currentData.proveedores || [];
    groups.forEach(g => {
        const items = provs.filter(p => p.tipo === g.tipo);
        if (items.length > 0) {
            const og = document.createElement('optgroup');
            og.label = g.label;
            items.forEach(p => {
                const o = document.createElement('option');
                o.value = p.id;
                o.textContent = p.nombre + (p.localidad ? ` (${p.localidad})` : '');
                og.appendChild(o);
            });
            sel.appendChild(og);
        }
    });
    sel.value = String(selectedId || '0');
}
function populateVtSelectors() {
    const vSel = document.getElementById('vt-vehiculo');
    vSel.innerHTML = '';
    (currentData.vehiculos || []).forEach(v => {
        const o = document.createElement('option');
        o.value = v.id;
        o.textContent = v.nombre + (v.matricula ? ` (${v.matricula})` : '');
        vSel.appendChild(o);
    });
    const mSel = document.getElementById('vt-maestro');
    mSel.innerHTML = '<option value="0">-- Personalizada --</option>';
    (currentData.tareas_maestro || []).forEach(tm => {
        const o = document.createElement('option');
        o.value = tm.id;
        o.textContent = tm.nombre;
        mSel.appendChild(o);
    });
    populateProveedorSelect('vt-proveedor', '0');
}
function onVtMaestroSelect() {
    const tmId = parseInt(document.getElementById('vt-maestro').value || '0', 10);
    if (!tmId) return;
    const tm = (currentData.tareas_maestro || []).find(x => Number(x.id) === tmId);
    if (!tm) return;
    document.getElementById('vt-descripcion').value = tm.nombre + (tm.descripcion ? ` - ${tm.descripcion}` : '');
    document.getElementById('vt-tipo').value = tm.tipo_periodicidad || 'ambos';
    document.getElementById('vt-dias').value = tm.periodo_dias || '';
    document.getElementById('vt-km').value = tm.periodo_km || '';
    document.getElementById('vt-avisar-tiempo').checked = Boolean(Number(tm.avisar_por_tiempo_si_no_km));
    toggleVtPeriodoFields();
}
function onVtVehiculoChange() {
    const vid = parseInt(document.getElementById('vt-vehiculo').value || '0', 10);
    const lastKmMap = currentData.ultimo_km_por_vehiculo || {};
    if (document.getElementById('vt-id').value === '0' && lastKmMap[vid] !== undefined) {
        document.getElementById('vt-ultimo-km').value = Math.round(lastKmMap[vid]);
    }
}
function openVehiculoTareaModal() {
    populateVtSelectors();
    document.getElementById('vt-id').value = '0';
    document.getElementById('vt-modal-title').innerHTML = '<i class="fa-solid fa-wrench" style="color:var(--primary)"></i> Asignar Tarea al Vehículo';
    const curVeh = document.getElementById('vehicle-selector').value;
    if (curVeh && curVeh !== '0') document.getElementById('vt-vehiculo').value = curVeh;
    document.getElementById('vt-maestro').value = '0';
    document.getElementById('vt-descripcion').value = '';
    document.getElementById('vt-tipo').value = 'ambos';
    document.getElementById('vt-dias').value = '365';
    document.getElementById('vt-km').value = '15000';
    document.getElementById('vt-ultima-fecha').value = new Date().toISOString().slice(0, 10);
    document.getElementById('vt-proveedor').value = '0';
    document.getElementById('vt-coste').value = '';
    document.getElementById('vt-garantia').value = '';
    document.getElementById('vt-notas').value = '';
    document.getElementById('vt-avisar-tiempo').checked = true;
    onVtVehiculoChange();
    toggleVtPeriodoFields();
    document.getElementById('vehiculo-tarea-modal').classList.add('active');
}
function editVehiculoTarea(t) {
    populateVtSelectors();
    document.getElementById('vt-id').value = t.id;
    document.getElementById('vt-modal-title').innerHTML = '<i class="fa-solid fa-pen" style="color:var(--accent)"></i> Editar Tarea del Vehículo';
    document.getElementById('vt-vehiculo').value = t.vehiculo_id;
    document.getElementById('vt-maestro').value = t.tarea_maestro_id || '0';
    document.getElementById('vt-proveedor').value = t.proveedor_id || '0';
    document.getElementById('vt-descripcion').value = t.descripcion || '';
    document.getElementById('vt-tipo').value = t.tipo_periodicidad || 'ambos';
    document.getElementById('vt-dias').value = t.periodo_dias || '';
    document.getElementById('vt-km').value = t.periodo_km || '';
    document.getElementById('vt-ultima-fecha').value = t.ultima_fecha || '';
    document.getElementById('vt-ultimo-km').value = t.ultimo_km !== null ? t.ultimo_km : '';
    document.getElementById('vt-coste').value = (t.coste !== null && t.coste !== undefined) ? t.coste : '';
    document.getElementById('vt-garantia').value = (t.garantia_meses !== null && t.garantia_meses !== undefined) ? t.garantia_meses : '';
    document.getElementById('vt-notas').value = t.notas || '';
    document.getElementById('vt-avisar-tiempo').checked = Boolean(Number(t.avisar_por_tiempo_si_no_km));
    toggleVtPeriodoFields();
    document.getElementById('vehiculo-tarea-modal').classList.add('active');
}
function closeVehiculoTareaModal() {
    document.getElementById('vehiculo-tarea-modal').classList.remove('active');
}
document.getElementById('vt-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await apiCall('save_vehiculo_tarea', {
        id: document.getElementById('vt-id').value,
        vehiculo_id: document.getElementById('vt-vehiculo').value,
        tarea_maestro_id: document.getElementById('vt-maestro').value,
        proveedor_id: document.getElementById('vt-proveedor').value,
        descripcion: document.getElementById('vt-descripcion').value,
        tipo_periodicidad: document.getElementById('vt-tipo').value,
        periodo_dias: document.getElementById('vt-dias').value,
        periodo_km: document.getElementById('vt-km').value,
        ultima_fecha: document.getElementById('vt-ultima-fecha').value,
        ultimo_km: document.getElementById('vt-ultimo-km').value,
        coste: document.getElementById('vt-coste').value,
        garantia_meses: document.getElementById('vt-garantia').value,
        notas: document.getElementById('vt-notas').value,
        avisar_por_tiempo_si_no_km: document.getElementById('vt-avisar-tiempo').checked ? 1 : 0
    });
    if (res.success) {
        closeVehiculoTareaModal();
        await loadDashboard();
    } else {
        alert(res.error || 'Error guardando tarea');
    }
});

let completingPrevTaskKm = null;

function openCompleteTareaModal(t) {
    const isRenewable = (t.tipo_periodicidad !== 'unica') && ((t.periodo_dias && Number(t.periodo_dias) > 0) || (t.periodo_km && Number(t.periodo_km) > 0));
    completingPrevTaskKm = (t.ultimo_km !== null && t.ultimo_km !== undefined && Number(t.ultimo_km) > 0)
        ? Math.round(Number(t.ultimo_km))
        : ((t.km_actual && Number(t.km_actual) > 0) ? Math.round(Number(t.km_actual)) : null);
    document.getElementById('comp-vt-id').value = t.id;
    document.getElementById('comp-is-renewable').value = isRenewable ? '1' : '0';
    document.getElementById('comp-vt-desc').textContent = `${t.vehiculo_nombre}: ${t.descripcion}`;
    document.getElementById('comp-fecha').value = new Date().toISOString().slice(0, 10);
    const defaultKm = (t.km_actual && Number(t.km_actual) > 0) ? Math.round(Number(t.km_actual)) : (completingPrevTaskKm || '');
    document.getElementById('comp-km').value = defaultKm;
    populateProveedorSelect('comp-proveedor', t.proveedor_id || '0');
    document.getElementById('comp-coste').value = (t.coste !== null && t.coste !== undefined) ? t.coste : '';
    document.getElementById('comp-garantia').value = (t.garantia_meses !== null && t.garantia_meses !== undefined) ? t.garantia_meses : '';
    document.getElementById('comp-notas').value = '';
    document.getElementById('complete-tarea-modal').classList.add('active');
}
function closeCompleteTareaModal() {
    document.getElementById('complete-tarea-modal').classList.remove('active');
}
document.getElementById('complete-vt-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const isRenewable = document.getElementById('comp-is-renewable').value === '1';
    let crearSig = 0;
    if (isRenewable) {
        crearSig = confirm('Esta tarea es periódica. ¿Deseas registrar también una tarea para el siguiente periodo?') ? 1 : 0;
    }
    let kmVal = document.getElementById('comp-km').value.trim();
    if (crearSig === 1 && (!kmVal || Number(kmVal) <= 0)) {
        if (completingPrevTaskKm !== null && completingPrevTaskKm > 0) {
            const proceed = confirm(
                `⚠️ No has indicado un nuevo kilometraje al renovar la tarea.\n\nSe utilizará por defecto el kilometraje de la tarea anterior (${completingPrevTaskKm} km).\n\n¿Deseas continuar con ${completingPrevTaskKm} km? (Pulsa Cancelar para introducir otro kilometraje)`
            );
            if (!proceed) return;
            kmVal = String(completingPrevTaskKm);
            document.getElementById('comp-km').value = kmVal;
        } else {
            const proceedEmpty = confirm('⚠️ No has indicado un nuevo kilometraje y la tarea anterior tampoco tenía kilómetros registrados. ¿Deseas continuar igualmente?');
            if (!proceedEmpty) return;
        }
    }
    const res = await apiCall('complete_vehiculo_tarea', {
        id: document.getElementById('comp-vt-id').value,
        fecha_finalizacion: document.getElementById('comp-fecha').value,
        km_finalizacion: kmVal,
        proveedor_id: document.getElementById('comp-proveedor').value,
        coste: document.getElementById('comp-coste').value,
        garantia_meses: document.getElementById('comp-garantia').value,
        notas: document.getElementById('comp-notas').value,
        crear_siguiente: crearSig
    });
    if (res.success) {
        closeCompleteTareaModal();
        await loadDashboard();
    } else {
        alert(res.error || 'Error finalizando tarea');
    }
});

async function reopenVehiculoTarea(id) {
    if (!confirm('¿Deshacer la finalización y volver a dejar esta tarea como pendiente?')) return;
    const res = await apiCall('reopen_vehiculo_tarea', { id });
    if (res.success) {
        await loadDashboard();
    } else {
        alert(res.error || 'Error al reabrir la tarea');
    }
}

async function deactivateVehiculoTarea(id) {
    if (!confirm('¿Dar de baja esta tarea?')) return;
    const res = await apiCall('deactivate_vehiculo_tarea', { id });
    if (res.success) await loadDashboard();
}

// CRUD TALLERES, ASEGURADORAS E ITVS
let currentProveedorTab = 'taller';
function openProveedoresModal(tab = 'taller') {
    switchProveedorTab(tab);
    document.getElementById('proveedores-modal').classList.add('active');
}
function closeProveedoresModal() {
    document.getElementById('proveedores-modal').classList.remove('active');
}
function switchProveedorTab(tipo) {
    currentProveedorTab = tipo;
    ['taller', 'aseguradora', 'itv'].forEach(t => {
        const btn = document.getElementById(`prov-tab-${t}`);
        if (btn) {
            btn.className = (t === tipo) ? 'btn btn-primary' : 'btn btn-outline';
        }
    });
    resetProveedorForm();
    renderProveedoresList();
}
function resetProveedorForm() {
    const labels = { taller: 'Taller', aseguradora: 'Aseguradora', itv: 'ITV' };
    document.getElementById('prov-id').value = '0';
    document.getElementById('prov-tipo').value = currentProveedorTab;
    document.getElementById('prov-form-title').textContent = `Añadir ${labels[currentProveedorTab] || 'Proveedor'}`;
    document.getElementById('prov-nombre').value = '';
    document.getElementById('prov-telefono').value = '';
    document.getElementById('prov-localidad').value = '';
    document.getElementById('prov-direccion').value = '';
    document.getElementById('prov-notas').value = '';
}
function renderProveedoresList() {
    const el = document.getElementById('proveedores-list');
    const list = (currentData.proveedores || []).filter(p => p.tipo === currentProveedorTab);
    if (list.length === 0) {
        el.innerHTML = '<div style="padding:0.9rem; color:var(--muted); text-align:center; font-size:0.85rem;">No hay registros todavía en esta categoría.</div>';
        return;
    }
    el.innerHTML = list.map(p => `
        <div style="display:flex; justify-content:space-between; align-items:center; padding:0.5rem 0.7rem; border-bottom:1px solid var(--border); gap:0.5rem;">
            <div>
                <strong>${p.nombre}</strong>
                <small style="color:var(--muted); display:block;">
                    ${[p.telefono ? 'Tel: ' + p.telefono : '', p.localidad || '', p.direccion || ''].filter(Boolean).join(' · ') || 'Sin datos adicionales'}
                </small>
                ${p.notas ? `<small style="color:var(--muted); font-style:italic;">${p.notas}</small>` : ''}
            </div>
            <div style="display:flex; gap:0.3rem;">
                <button type="button" class="btn btn-outline" style="padding:0.2rem 0.5rem; font-size:0.75rem;" onclick='editProveedor(${JSON.stringify(p)})'><i class="fa-solid fa-pen"></i></button>
                <button type="button" class="btn btn-outline" style="padding:0.2rem 0.5rem; font-size:0.75rem; color:var(--danger);" onclick="deleteProveedor(${p.id})"><i class="fa-solid fa-trash"></i></button>
            </div>
        </div>
    `).join('');
}
function editProveedor(p) {
    const labels = { taller: 'Taller', aseguradora: 'Aseguradora', itv: 'ITV' };
    document.getElementById('prov-id').value = p.id;
    document.getElementById('prov-tipo').value = p.tipo;
    document.getElementById('prov-form-title').textContent = `Editar ${labels[p.tipo] || 'Proveedor'}`;
    document.getElementById('prov-nombre').value = p.nombre || '';
    document.getElementById('prov-telefono').value = p.telefono || '';
    document.getElementById('prov-localidad').value = p.localidad || '';
    document.getElementById('prov-direccion').value = p.direccion || '';
    document.getElementById('prov-notas').value = p.notas || '';
}
document.getElementById('proveedor-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await apiCall('save_proveedor', {
        id: document.getElementById('prov-id').value,
        tipo: document.getElementById('prov-tipo').value,
        nombre: document.getElementById('prov-nombre').value,
        telefono: document.getElementById('prov-telefono').value,
        localidad: document.getElementById('prov-localidad').value,
        direccion: document.getElementById('prov-direccion').value,
        notas: document.getElementById('prov-notas').value
    });
    if (res.success) {
        await loadDashboard();
        renderProveedoresList();
        resetProveedorForm();
    } else {
        alert(res.error || 'Error guardando registro');
    }
});
async function deleteProveedor(id) {
    if (!confirm('¿Dar de baja este registro?')) return;
    const res = await apiCall('deactivate_proveedor', { id });
    if (res.success) {
        await loadDashboard();
        renderProveedoresList();
    }
}

// CREACIÓN RÁPIDA DESDE DESPLEGABLE DE TAREAS
function openQuickProveedorModal(targetSelectId) {
    document.getElementById('qprov-target-select').value = targetSelectId;
    document.getElementById('qprov-tipo').value = 'taller';
    document.getElementById('qprov-nombre').value = '';
    document.getElementById('qprov-telefono').value = '';
    document.getElementById('qprov-localidad').value = '';
    document.getElementById('qprov-direccion').value = '';
    document.getElementById('qprov-notas').value = '';
    document.getElementById('quick-proveedor-modal').classList.add('active');
}
function closeQuickProveedorModal() {
    document.getElementById('quick-proveedor-modal').classList.remove('active');
}
document.getElementById('quick-proveedor-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const targetId = document.getElementById('qprov-target-select').value;
    const res = await apiCall('save_proveedor', {
        id: 0,
        tipo: document.getElementById('qprov-tipo').value,
        nombre: document.getElementById('qprov-nombre').value,
        telefono: document.getElementById('qprov-telefono').value,
        localidad: document.getElementById('qprov-localidad').value,
        direccion: document.getElementById('qprov-direccion').value,
        notas: document.getElementById('qprov-notas').value
    });
    if (res.success && res.proveedor) {
        if (!Array.isArray(currentData.proveedores)) currentData.proveedores = [];
        currentData.proveedores.push(res.proveedor);
        populateProveedorSelect('vt-proveedor', targetId === 'vt-proveedor' ? res.id : document.getElementById('vt-proveedor').value);
        populateProveedorSelect('comp-proveedor', targetId === 'comp-proveedor' ? res.id : document.getElementById('comp-proveedor').value);
        closeQuickProveedorModal();
    } else {
        alert(res.error || 'Error creando proveedor');
    }
});

// COMPARATIVA DE COSTES DE TAREAS RELACIONADAS
function openComparativaTareaModal(t) {
    const normDesc = (s) => (s || '').trim().toLowerCase();
    const relKeyOf = (x) => `${x.vehiculo_id}:${x.tarea_maestro_id ? 'm:' + x.tarea_maestro_id : 'd:' + normDesc(x.descripcion)}`;
    const targetKey = relKeyOf(t);

    const related = (currentData.vehiculo_tareas || [])
        .filter(x => relKeyOf(x) === targetKey)
        .sort((a, b) => {
            const fa = a.fecha_finalizacion || a.ultima_fecha || '';
            const fb = b.fecha_finalizacion || b.ultima_fecha || '';
            if (fa !== fb) return fa.localeCompare(fb);
            return Number(a.id) - Number(b.id);
        });

    document.getElementById('comp-tarea-subtitle').textContent = `${t.vehiculo_nombre || ''} · ${t.descripcion}`;

    const costs = related.map(x => x.coste).filter(c => c !== null && c !== undefined && c !== '').map(Number);
    const sumEl = document.getElementById('comp-tarea-summary');
    if (costs.length > 0) {
        const total = costs.reduce((acc, v) => acc + v, 0);
        const avg = total / costs.length;
        const min = Math.min(...costs);
        const max = Math.max(...costs);
        sumEl.innerHTML = `
            <div style="background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:0.45rem 0.75rem; font-size:0.8rem;">Registros con coste: <strong>${costs.length}</strong></div>
            <div style="background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:0.45rem 0.75rem; font-size:0.8rem;">Total acumulado: <strong>${fmtNum(total, 2)} €</strong></div>
            <div style="background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:0.45rem 0.75rem; font-size:0.8rem;">Media: <strong>${fmtNum(avg, 2)} €</strong></div>
            <div style="background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:0.45rem 0.75rem; font-size:0.8rem;">Mín / Máx: <strong>${fmtNum(min, 2)} €</strong> / <strong>${fmtNum(max, 2)} €</strong></div>
        `;
    } else {
        sumEl.innerHTML = '<div style="font-size:0.82rem; color:var(--muted);">Aún no hay costes registrados en estas tareas.</div>';
    }

    const tbody = document.getElementById('comp-tarea-tbody');
    tbody.innerHTML = '';
    let prevCost = null;
    related.forEach(r => {
        const fecha = r.fecha_finalizacion || r.ultima_fecha || '-';
        const km = r.km_finalizacion !== null ? r.km_finalizacion : r.ultimo_km;
        const kmTxt = (km !== null && km !== undefined) ? `${fmtNum(km, 0)} km` : '-';
        const provTxt = r.proveedor_nombre || '-';
        const hasCost = (r.coste !== null && r.coste !== undefined && r.coste !== '');
        const costNum = hasCost ? Number(r.coste) : null;
        let diffHtml = '-';
        if (costNum !== null && prevCost !== null) {
            const diff = costNum - prevCost;
            const sign = diff > 0 ? '+' : '';
            const color = diff > 0 ? '#dc2626' : (diff < 0 ? '#16a34a' : 'var(--muted)');
            const pct = prevCost > 0 ? ` (${sign}${fmtNum((diff / prevCost) * 100, 1)}%)` : '';
            diffHtml = `<span style="color:${color}; font-weight:700;">${sign}${fmtNum(diff, 2)} €${pct}</span>`;
        }
        if (costNum !== null) prevCost = costNum;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${fecha}</td>
            <td>${kmTxt}</td>
            <td>${provTxt}</td>
            <td style="text-align:right; font-weight:700;">${costNum !== null ? fmtNum(costNum, 2) + ' €' : '-'}</td>
            <td style="text-align:right;">${diffHtml}</td>
            <td><small>${r.estado === 'finalizada' ? '✅ Finalizada' : '⏳ Pendiente'}${r.notas ? ' · ' + r.notas : ''}</small></td>
        `;
        tbody.appendChild(tr);
    });
    document.getElementById('comparativa-tarea-modal').classList.add('active');
}
function closeComparativaTareaModal() {
    document.getElementById('comparativa-tarea-modal').classList.remove('active');
}

async function sendTaskAlertsEmailNow() {
    const vehiculoId = document.getElementById('vehicle-selector').value || 0;
    const res = await apiCall('send_task_alerts_email', { vehiculo_id: vehiculoId });
    alert(res.message || res.error || 'Operación completada');
}

function openUserEmailModal() {
    document.getElementById('user-email-input').value = (currentData.user && currentData.user.email) ? currentData.user.email : '';
    document.getElementById('user-email-modal').classList.add('active');
}
function closeUserEmailModal() {
    document.getElementById('user-email-modal').classList.remove('active');
}
document.getElementById('user-email-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await apiCall('update_user_email', { email: document.getElementById('user-email-input').value });
    if (res.success) {
        closeUserEmailModal();
        await loadDashboard();
        alert(res.message);
    } else {
        alert(res.error || 'Error guardando correo');
    }
});

async function openPendingUsersModal() {
    document.getElementById('pending-users-modal').classList.add('active');
    const box = document.getElementById('pending-users-list');
    box.innerHTML = '<div style="color:#64748b; font-size:0.85rem;">Cargando solicitudes...</div>';
    const res = await apiCall('list_pending_users');
    if (!res.success) {
        box.innerHTML = `<div style="color:#b91c1c; font-size:0.85rem;">${res.error || 'Error cargando solicitudes'}</div>`;
        return;
    }
    const list = res.pending_users || [];
    if (list.length === 0) {
        box.innerHTML = '<div style="color:#64748b; font-size:0.85rem; padding:0.75rem 0;">No hay solicitudes pendientes de validación.</div>';
        return;
    }
    box.innerHTML = list.map(u => `
        <div style="border:1px solid var(--border); border-radius:10px; padding:0.75rem; display:flex; justify-content:space-between; align-items:center; gap:0.65rem; flex-wrap:wrap;">
            <div>
                <div style="font-weight:700; font-size:0.9rem;">${u.username}</div>
                <div style="font-size:0.8rem; color:#64748b;">${u.email || 'Sin correo'}</div>
            </div>
            <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                <button class="btn btn-primary" style="padding:0.35rem 0.65rem; font-size:0.78rem;" onclick="approvePendingUser(${u.id}, 'ambos')">Validar (Ambos)</button>
                <button class="btn btn-outline" style="padding:0.35rem 0.65rem; font-size:0.78rem;" onclick="approvePendingUser(${u.id}, 'combustible')">Solo Combustible</button>
                <button class="btn btn-danger" style="padding:0.35rem 0.65rem; font-size:0.78rem;" onclick="rejectPendingUser(${u.id})">Rechazar</button>
            </div>
        </div>
    `).join('');
}
function closePendingUsersModal() {
    document.getElementById('pending-users-modal').classList.remove('active');
}
async function approvePendingUser(id, acceso) {
    const res = await apiCall('approve_user', { id, acceso_sistema: acceso });
    alert(res.message || res.error || 'Operación completada');
    await openPendingUsersModal();
}
async function rejectPendingUser(id) {
    if (!confirm('¿Rechazar esta solicitud de registro?')) return;
    const res = await apiCall('reject_user', { id });
    alert(res.message || res.error || 'Operación completada');
    await openPendingUsersModal();
}

// PARQUES MÓVILES COMPARTIDOS
let cachedAllUsers = [];
async function openParquesModal() {
    document.getElementById('parques-modal').classList.add('active');
    const isAdmin = !!(currentData.user && currentData.user.is_admin);
    const adminSec = document.getElementById('parque-admin-section');
    if (isAdmin) adminSec.classList.remove('hidden'); else adminSec.classList.add('hidden');

    const box = document.getElementById('parques-list');
    box.innerHTML = '<div style="color:#64748b; font-size:0.85rem;">Cargando parques móviles...</div>';
    const res = await apiCall('list_parques');
    if (!res.success) {
        box.innerHTML = `<div style="color:#b91c1c; font-size:0.85rem;">${res.error || 'Error cargando parques'}</div>`;
        return;
    }
    cachedAllUsers = res.users || [];
    const allVehs = res.all_vehiculos || [];
    const currentUserId = parseInt((currentData.user && currentData.user.id) || '0', 10);
    const list = res.parques || [];
    if (list.length === 0) {
        box.innerHTML = '<div style="color:#64748b; font-size:0.85rem; padding:0.5rem 0;">No hay parques móviles creados o asignados todavía.</div>';
        return;
    }

    box.innerHTML = list.map(pq => {
        const miembros = (pq.miembros || []).map(m => `
            <span class="badge" style="background:#f1f5f9; color:#0f172a; display:inline-flex; align-items:center; gap:0.35rem; margin:2px;">
                <i class="fa-solid fa-user" style="color:var(--primary);"></i> ${m.username}
                ${isAdmin ? `<button type="button" onclick="removeParqueUser(${pq.id}, ${m.usuario_id})" style="border:none; background:none; color:#dc2626; cursor:pointer; font-weight:700;" title="Quitar usuario">×</button>` : ''}
            </span>
        `).join('') || '<span style="font-size:0.78rem; color:#94a3b8;">Sin usuarios asignados</span>';

        const vehiculosChips = (pq.vehiculos || []).map(v => {
            const canRemove = isAdmin || parseInt(v.usuario_id, 10) === currentUserId;
            return `
                <span class="badge" style="background:#e0f2fe; color:#0369a1; display:inline-flex; align-items:center; gap:0.35rem; margin:2px;">
                    <i class="fa-solid fa-car"></i> ${v.nombre}${v.matricula ? ` (${v.matricula})` : ''}
                    ${canRemove ? `<button type="button" onclick="removeParqueVehiculo(${pq.id}, ${v.id})" style="border:none; background:none; color:#dc2626; cursor:pointer; font-weight:700;" title="Quitar vehículo del parque">×</button>` : ''}
                </span>
            `;
        }).join('') || '<span style="font-size:0.78rem; color:#94a3b8;">Sin vehículos asignados</span>';

        const vehOpts = allVehs
            .filter(v => parseInt(v.parque_id || '0', 10) !== parseInt(pq.id, 10) && (isAdmin || parseInt(v.usuario_id, 10) === currentUserId))
            .map(v => `<option value="${v.id}">${v.nombre}${v.matricula ? ` (${v.matricula})` : ''}${isAdmin && v.propietario ? ` · ${v.propietario}` : ''}</option>`)
            .join('');

        const assignVehRow = vehOpts ? `
            <div style="display:flex; gap:0.4rem; margin-top:0.45rem; align-items:center; flex-wrap:wrap;">
                <select id="assign-veh-parque-${pq.id}" class="form-control" style="flex:1; min-width:170px; padding:0.35rem 0.5rem; font-size:0.8rem;">
                    <option value="">-- Añadir vehículo al parque --</option>
                    ${vehOpts}
                </select>
                <button type="button" class="btn btn-outline" style="padding:0.35rem 0.65rem; font-size:0.78rem;" onclick="assignParqueVehiculo(${pq.id})">
                    <i class="fa-solid fa-car"></i> Añadir vehículo
                </button>
            </div>
        ` : '';

        const userOpts = cachedAllUsers
            .filter(u => !(pq.miembros || []).some(m => parseInt(m.usuario_id, 10) === parseInt(u.id, 10)))
            .map(u => `<option value="${u.id}">${u.username} (${u.role})</option>`)
            .join('');

        const assignRow = isAdmin ? `
            <div style="display:flex; gap:0.4rem; margin-top:0.55rem; align-items:center; flex-wrap:wrap;">
                <select id="assign-user-parque-${pq.id}" class="form-control" style="flex:1; min-width:170px; padding:0.35rem 0.5rem; font-size:0.8rem;">
                    <option value="">-- Añadir usuario al parque --</option>
                    ${userOpts}
                </select>
                <button type="button" class="btn btn-primary" style="padding:0.35rem 0.65rem; font-size:0.78rem;" onclick="assignParqueUser(${pq.id})">
                    <i class="fa-solid fa-user-plus"></i> Añadir
                </button>
                <button type="button" class="btn btn-outline" style="padding:0.35rem 0.6rem; font-size:0.78rem;" onclick='editParque(${JSON.stringify({id: pq.id, nombre: pq.nombre, descripcion: pq.descripcion || ""})})'>
                    <i class="fa-solid fa-pen"></i>
                </button>
                <button type="button" class="btn btn-danger" style="padding:0.35rem 0.6rem; font-size:0.78rem;" onclick="deactivateParque(${pq.id})">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        ` : '';

        return `
            <div style="border:1px solid var(--border); border-radius:10px; padding:0.75rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem;">
                    <div style="font-weight:700; font-size:0.95rem;"><i class="fa-solid fa-people-group" style="color:var(--primary)"></i> ${pq.nombre}</div>
                    <span class="badge badge-teal">${pq.num_vehiculos || 0} vehículos</span>
                </div>
                ${pq.descripcion ? `<div style="font-size:0.8rem; color:var(--muted); margin-top:0.2rem;">${pq.descripcion}</div>` : ''}
                <div style="margin-top:0.45rem;"><span style="font-size:0.74rem; color:var(--muted); font-weight:600;">Usuarios:</span> ${miembros}</div>
                <div style="margin-top:0.35rem;"><span style="font-size:0.74rem; color:var(--muted); font-weight:600;">Vehículos:</span> ${vehiculosChips}</div>
                ${assignVehRow}
                ${assignRow}
            </div>
        `;
    }).join('');
}
function closeParquesModal() {
    document.getElementById('parques-modal').classList.remove('active');
}
function editParque(pq) {
    document.getElementById('parque-id').value = pq.id;
    document.getElementById('parque-nombre').value = pq.nombre;
    document.getElementById('parque-descripcion').value = pq.descripcion || '';
}
function resetParqueForm() {
    document.getElementById('parque-id').value = '0';
    document.getElementById('parque-nombre').value = '';
    document.getElementById('parque-descripcion').value = '';
}
document.getElementById('parque-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await apiCall('save_parque', {
        id: document.getElementById('parque-id').value,
        nombre: document.getElementById('parque-nombre').value,
        descripcion: document.getElementById('parque-descripcion').value
    });
    if (res.success) {
        resetParqueForm();
        await loadDashboard();
        await openParquesModal();
    } else {
        alert(res.error || 'Error guardando parque móvil');
    }
});
async function assignParqueUser(parqueId) {
    const sel = document.getElementById(`assign-user-parque-${parqueId}`);
    const usuarioId = sel ? parseInt(sel.value || '0', 10) : 0;
    if (!usuarioId) return;
    const res = await apiCall('assign_parque_user', { parque_id: parqueId, usuario_id: usuarioId });
    if (res.success) {
        await loadDashboard();
        await openParquesModal();
    } else {
        alert(res.error || 'Error asignando usuario');
    }
}
async function removeParqueUser(parqueId, usuarioId) {
    if (!confirm('¿Retirar este usuario del parque móvil?')) return;
    const res = await apiCall('remove_parque_user', { parque_id: parqueId, usuario_id: usuarioId });
    if (res.success) {
        await loadDashboard();
        await openParquesModal();
    } else {
        alert(res.error || 'Error retirando usuario');
    }
}
async function assignParqueVehiculo(parqueId) {
    const sel = document.getElementById(`assign-veh-parque-${parqueId}`);
    const vehiculoId = sel ? parseInt(sel.value || '0', 10) : 0;
    if (!vehiculoId) return;
    const res = await apiCall('assign_parque_vehiculo', { parque_id: parqueId, vehiculo_id: vehiculoId });
    if (res.success) {
        await loadDashboard();
        await openParquesModal();
    } else {
        alert(res.error || 'Error añadiendo vehículo');
    }
}
async function removeParqueVehiculo(parqueId, vehiculoId) {
    if (!confirm('¿Retirar este vehículo del parque móvil? Pasará a ser personal de su propietario.')) return;
    const res = await apiCall('remove_parque_vehiculo', { parque_id: parqueId, vehiculo_id: vehiculoId });
    if (res.success) {
        await loadDashboard();
        await openParquesModal();
    } else {
        alert(res.error || 'Error retirando vehículo');
    }
}
async function deactivateParque(id) {
    if (!confirm('¿Dar de baja lógica este parque móvil (activo = 0)?')) return;
    const res = await apiCall('deactivate_parque', { id });
    if (res.success) {
        await loadDashboard();
        await openParquesModal();
    } else {
        alert(res.error || 'Error dando de baja el parque');
    }
}

// PAPELERA / REACTIVAR O BORRAR DEFINITIVAMENTE REGISTROS INACTIVOS
let inactiveItemsOrder = [];
let selectedInactiveKeys = new Set();
let lastInactiveAnchorIdx = null;

function syncInactiveSelectionUI() {
    const cntEl = document.getElementById('inactive-selected-count');
    if (cntEl) cntEl.textContent = String(selectedInactiveKeys.size);
    const allChk = document.getElementById('inactive-select-all');
    if (allChk) {
        allChk.checked = inactiveItemsOrder.length > 0 && selectedInactiveKeys.size === inactiveItemsOrder.length;
    }
    inactiveItemsOrder.forEach((key, idx) => {
        const rowEl = document.getElementById(`inactive-row-${idx}`);
        const chkEl = document.getElementById(`inactive-chk-${idx}`);
        const isSel = selectedInactiveKeys.has(key);
        if (chkEl) chkEl.checked = isSel;
        if (rowEl) {
            rowEl.style.background = isSel ? '#f0fdfa' : '#ffffff';
            rowEl.style.borderColor = isSel ? '#0f766e' : 'var(--border)';
        }
    });
}

function toggleAllInactiveSelection(checked) {
    if (checked) {
        inactiveItemsOrder.forEach(k => selectedInactiveKeys.add(k));
    } else {
        selectedInactiveKeys.clear();
    }
    syncInactiveSelectionUI();
}

function handleInactiveRowClick(ev, idx, key) {
    if (ev.target && ev.target.closest('button')) return;
    const isCheckbox = ev.target && ev.target.tagName === 'INPUT';
    if (ev.shiftKey && lastInactiveAnchorIdx !== null) {
        const start = Math.min(lastInactiveAnchorIdx, idx);
        const end = Math.max(lastInactiveAnchorIdx, idx);
        if (!ev.ctrlKey && !ev.metaKey) selectedInactiveKeys.clear();
        for (let i = start; i <= end; i++) {
            selectedInactiveKeys.add(inactiveItemsOrder[i]);
        }
    } else if (ev.ctrlKey || ev.metaKey || isCheckbox) {
        if (selectedInactiveKeys.has(key)) selectedInactiveKeys.delete(key);
        else selectedInactiveKeys.add(key);
        lastInactiveAnchorIdx = idx;
    } else {
        if (selectedInactiveKeys.size === 1 && selectedInactiveKeys.has(key)) {
            selectedInactiveKeys.clear();
        } else {
            selectedInactiveKeys.clear();
            selectedInactiveKeys.add(key);
        }
        lastInactiveAnchorIdx = idx;
    }
    syncInactiveSelectionUI();
}

async function openInactiveRecordsModal() {
    document.getElementById('inactive-records-modal').classList.add('active');
    const box = document.getElementById('inactive-records-list');
    box.innerHTML = '<div style="color:#64748b; font-size:0.85rem;">Cargando registros inactivos...</div>';
    inactiveItemsOrder = [];
    selectedInactiveKeys = new Set();
    lastInactiveAnchorIdx = null;
    syncInactiveSelectionUI();

    const res = await apiCall('list_inactive_records');
    if (!res.success) {
        box.innerHTML = `<div style="color:#b91c1c; font-size:0.85rem;">${res.error || 'Error cargando registros'}</div>`;
        return;
    }
    const vehs = res.vehiculos || [];
    const reps = res.repostajes || [];
    if (vehs.length === 0 && reps.length === 0) {
        box.innerHTML = '<div style="color:#64748b; font-size:0.85rem; padding:0.75rem 0;">No hay vehículos ni repostajes inactivos (activo = 0).</div>';
        return;
    }
    let html = '';
    let idx = 0;
    if (vehs.length > 0) {
        html += '<div style="font-weight:700; font-size:0.85rem; color:var(--primary-dark);">Vehículos inactivos</div>';
        html += vehs.map(v => {
            const key = `vehiculo:${v.id}`;
            const curIdx = idx++;
            inactiveItemsOrder.push(key);
            return `
                <div id="inactive-row-${curIdx}" onclick="handleInactiveRowClick(event, ${curIdx}, '${key}')" style="border:1px solid var(--border); border-radius:8px; padding:0.5rem 0.7rem; display:flex; justify-content:space-between; align-items:center; gap:0.5rem; cursor:pointer;">
                    <div style="display:flex; align-items:center; gap:0.55rem; font-size:0.83rem;">
                        <input type="checkbox" id="inactive-chk-${curIdx}">
                        <span><b>#${v.id} ${v.nombre}</b> (${v.matricula || 'Sin matrícula'}) · Usuario: ${v.propietario || v.usuario_id}</span>
                    </div>
                    <div style="display:flex; gap:0.35rem;">
                        <button type="button" class="btn btn-primary" style="padding:0.26rem 0.55rem; font-size:0.75rem;" onclick="reactivateRecord('vehiculo', ${v.id})">Recuperar</button>
                        <button type="button" class="btn btn-danger" style="padding:0.26rem 0.55rem; font-size:0.75rem;" onclick="purgeRecord('vehiculo', ${v.id})" title="Borrado físico definitivo"><i class="fa-solid fa-trash-can"></i></button>
                    </div>
                </div>
            `;
        }).join('');
    }
    if (reps.length > 0) {
        html += `<div style="font-weight:700; font-size:0.85rem; color:var(--primary-dark); margin-top:0.4rem;">Repostajes inactivos (${reps.length})</div>`;
        html += reps.map(r => {
            const key = `repostaje:${r.id}`;
            const curIdx = idx++;
            inactiveItemsOrder.push(key);
            return `
                <div id="inactive-row-${curIdx}" onclick="handleInactiveRowClick(event, ${curIdx}, '${key}')" style="border:1px solid var(--border); border-radius:8px; padding:0.5rem 0.7rem; display:flex; justify-content:space-between; align-items:center; gap:0.5rem; cursor:pointer;">
                    <div style="display:flex; align-items:center; gap:0.55rem; font-size:0.83rem;">
                        <input type="checkbox" id="inactive-chk-${curIdx}">
                        <span><b>#${r.id}</b> · ${fmtDate(r.fecha)} · ${r.vehiculo_nombre || ('Veh #' + r.vehiculo_id)} · ${r.litros} L (${r.importe_total} €) · ${r.propietario || r.usuario_id}</span>
                    </div>
                    <div style="display:flex; gap:0.35rem;">
                        <button type="button" class="btn btn-primary" style="padding:0.26rem 0.55rem; font-size:0.75rem;" onclick="reactivateRecord('repostaje', ${r.id})">Recuperar</button>
                        <button type="button" class="btn btn-danger" style="padding:0.26rem 0.55rem; font-size:0.75rem;" onclick="purgeRecord('repostaje', ${r.id})" title="Borrado físico definitivo"><i class="fa-solid fa-trash-can"></i></button>
                    </div>
                </div>
            `;
        }).join('');
    }
    box.innerHTML = html;
    syncInactiveSelectionUI();
}
function closeInactiveRecordsModal() {
    document.getElementById('inactive-records-modal').classList.remove('active');
}
function getSelectedInactiveBatch() {
    const vehiculo_ids = [];
    const repostaje_ids = [];
    selectedInactiveKeys.forEach(k => {
        const [tipo, idStr] = k.split(':');
        const id = parseInt(idStr, 10);
        if (tipo === 'vehiculo' && id > 0) vehiculo_ids.push(id);
        if (tipo === 'repostaje' && id > 0) repostaje_ids.push(id);
    });
    return { vehiculo_ids, repostaje_ids };
}
async function reactivateRecord(tipo, id) {
    const res = await apiCall('reactivate_records', { tipo, id });
    alert(res.message || res.error || 'Operación completada');
    await loadDashboard();
    await openInactiveRecordsModal();
}
async function reactivateSelectedInactive() {
    const batch = getSelectedInactiveBatch();
    const total = batch.vehiculo_ids.length + batch.repostaje_ids.length;
    if (total === 0) {
        alert('Selecciona primero uno o varios registros.');
        return;
    }
    const res = await apiCall('reactivate_records', { tipo: 'batch', ...batch });
    alert(res.message || res.error || 'Operación completada');
    await loadDashboard();
    await openInactiveRecordsModal();
}
async function purgeRecord(tipo, id) {
    if (!confirm('¿Borrar DEFINITIVAMENTE este registro de la base de datos? Esta acción NO se puede deshacer.')) return;
    const res = await apiCall('purge_inactive_records', { tipo, id });
    alert(res.message || res.error || 'Operación completada');
    await loadDashboard();
    await openInactiveRecordsModal();
}
async function purgeSelectedInactive() {
    const batch = getSelectedInactiveBatch();
    const total = batch.vehiculo_ids.length + batch.repostaje_ids.length;
    if (total === 0) {
        alert('Selecciona primero uno o varios registros.');
        return;
    }
    if (!confirm(`¿Borrar DEFINITIVAMENTE los ${total} registros seleccionados de la base de datos? Esta acción NO se puede deshacer.`)) return;
    const res = await apiCall('purge_inactive_records', { tipo: 'batch', ...batch });
    alert(res.message || res.error || 'Operación completada');
    await loadDashboard();
    await openInactiveRecordsModal();
}

function pdfCleanText(str) {
    return String(str || '')
        .replace(/≤/g, '<=')
        .replace(/≥/g, '>=')
        .replace(/[^\x20-\x7E\xA0-\xFF]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

async function exportTareasPDF(sendByEmail = false) {
    const list = getFilteredVehiculoTareas();
    if (list.length === 0) {
        alert('No hay tareas que coincidan con el filtro seleccionado.');
        return;
    }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const estadoSel = document.getElementById('tasks-filter-estado');
    const estadoLabel = pdfCleanText(estadoSel.options[estadoSel.selectedIndex].textContent);
    const vehSel = document.getElementById('vehicle-selector');
    const vehName = pdfCleanText(vehSel.options[vehSel.selectedIndex]?.textContent || 'Todos');

    doc.setFillColor(15, 118, 110);
    doc.rect(0, 0, 210, 26, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(14);
    doc.text('INFORME DE MANTENIMIENTO Y TAREAS PERIODICAS', 12, 11);
    doc.setFontSize(9);
    doc.text(pdfCleanText(`Ambito: ${vehName} · Filtro: ${estadoLabel}`), 12, 18);
    doc.text(pdfCleanText(`Generado: ${new Date().toLocaleDateString('es-ES')} · ${list.length} tareas`), 12, 23);

    const body = list.map(t => {
        const estadoTxt = t.estado === 'finalizada'
            ? `Finalizada (${t.fecha_finalizacion || '-'})`
            : (t.en_alerta ? `AVISO: ${t.motivo_alerta}` : 'En plazo');
        const provCost = [
            t.proveedor_nombre || '',
            (t.coste !== null && t.coste !== undefined && t.coste !== '') ? `${fmtNum(t.coste, 2)} EUR` : ''
        ].filter(Boolean).join(' · ') || '-';
        return [
            pdfCleanText(t.vehiculo_nombre || ''),
            pdfCleanText(t.descripcion || ''),
            t.proxima_fecha || '-',
            t.proximo_km !== null ? `${fmtNum(t.proximo_km, 0)} km` : '-',
            pdfCleanText(provCost),
            pdfCleanText(estadoTxt)
        ];
    });

    doc.autoTable({
        startY: 32,
        margin: { left: 10, right: 10 },
        head: [['Vehículo', 'Tarea / Control', 'Próx. Fecha', 'Próx. Km', 'Proveedor / Coste', 'Estado / Margen']],
        body,
        theme: 'grid',
        styles: { fontSize: 7.5, cellPadding: 2 },
        headStyles: { fillColor: [15, 23, 42], textColor: 255 }
    });

    const totalPagesT = doc.internal.getNumberOfPages();
    const nowFooterT = new Date().toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    for (let p = 1; p <= totalPagesT; p++) {
        doc.setPage(p);
        doc.setFontSize(7.5);
        doc.setTextColor(120, 120, 120);
        doc.text(
            `Página ${p} de ${totalPagesT} · generado y enviado por www.moratalla-murcia.com (${nowFooterT})`,
            105,
            291,
            { align: 'center' }
        );
    }
    const fileName = `Informe_Tareas_Mantenimiento_${new Date().toISOString().slice(0,10)}.pdf`;
    if (sendByEmail) {
        const summary = `Ámbito: ${vehName}\nFiltro: ${estadoLabel}\nTotal tareas incluidas: ${list.length}`;
        await sendJsPdfByEmail(doc, `Informe de Mantenimiento - ${vehName}`, summary, fileName);
    } else {
        doc.save(fileName);
    }
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
