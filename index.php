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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Librerías para generación de Informes PDF en el móvil y gráficos -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
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
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.84rem;
        }
        th, td {
            padding: 0.65rem 0.6rem;
            text-align: right;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        th:first-child, td:first-child,
        th:nth-child(2), td:nth-child(2) {
            text-align: left;
        }
        th {
            background: #f8fafc;
            color: var(--muted);
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        tr:hover { background: #f8fafc; }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            font-size: 0.74rem;
            font-weight: 600;
        }
        .badge-teal { background: var(--primary-light); color: var(--primary-dark); }
        .badge-amber { background: #fef3c7; color: #b45309; }

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
            <div class="login-icon"><i class="fa-solid fa-gas-pump"></i></div>
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
            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 0.5rem; padding: 0.9rem;">
                <i class="fa-solid fa-right-to-bracket"></i> Iniciar Sesión
            </button>
            <a href="https://github.com/pablosalinas/ControlCombustible/releases/latest/download/ControlCombustible.apk" target="_blank" class="btn btn-outline btn-block" style="margin-top: 0.65rem; text-decoration: none; font-size: 0.85rem;">
                <i class="fa-brands fa-android" style="color:#16a34a; font-size:1.1rem;"></i> Descargar App Android (.APK)
            </a>
        </form>
    </div>
</div>

<!-- APLICACIÓN PRINCIPAL -->
<div id="app-screen" class="hidden">
    <header class="app-header">
        <div class="header-top">
            <div class="brand">
                <span class="brand-badge" id="brand-icon-box"><i class="fa-solid fa-gas-pump"></i></span>
                <div>
                    <div id="app-title-text">Control de Combustible</div>
                    <div style="font-size: 0.72rem; color: #94a3b8; font-weight: 500;" id="user-badge-text">Conectado</div>
                </div>
            </div>
            <div style="display: flex; gap: 0.45rem;">
                <button class="btn btn-outline" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="openVehiclesModal()">
                    <i class="fa-solid fa-car"></i> Vehículos
                </button>
                <button class="btn btn-accent" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;" onclick="exportPDFReport()">
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
                <div style="display:flex; gap:0.5rem; align-items:center;">
                    <input type="text" id="search-input" class="form-control" placeholder="Buscar fecha, km, nota..." style="max-width:210px; padding:0.45rem 0.75rem; font-size:0.84rem;" oninput="renderTable()">
                    <button class="btn btn-primary" style="padding:0.48rem 0.85rem; font-size:0.84rem;" onclick="openRepostajeModal()">
                        <i class="fa-solid fa-plus"></i> Nuevo
                    </button>
                </div>
            </div>
            <div class="table-responsive">
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
                    <select id="rep-vehiculo" class="form-control" required></select>
                </div>
                <div class="form-group">
                    <label id="label-fecha-form">Fecha *</label>
                    <input type="date" id="rep-fecha" class="form-control" required>
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Kilómetros (Odómetro) *</label>
                    <input type="number" step="0.01" id="rep-km" class="form-control" placeholder="Ej: 183200" required>
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
                    <label>Estación de Servicio</label>
                    <input type="text" id="rep-estacion" class="form-control" placeholder="Opcional">
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

<script>
let authCreds = null;
let currentData = { vehiculos: [], repostajes: [], resumen: {}, user: {} };
let chartInstance = null;

function fmtNum(val, dec = 2) {
    if (val === null || val === undefined || isNaN(val)) return '-';
    return Number(val).toLocaleString('es-ES', { minimumFractionDigits: dec, maximumFractionDigits: dec });
}

async function apiCall(action, payload = {}, method = 'POST') {
    const headers = {
        'Content-Type': 'application/json',
        'Authorization': 'Basic ' + btoa(authCreds.username + ':' + authCreds.password)
    };
    const url = 'api.php?action=' + encodeURIComponent(action);
    const res = await fetch(url, {
        method,
        headers,
        body: method === 'POST' ? JSON.stringify({ ...payload, username: authCreds.username, password: authCreds.password }) : undefined
    });
    return await res.json();
}

document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
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

    // Poblar selector de vehículos manteniendo selección
    sel.innerHTML = '<option value="0">📊 Todos los vehículos (Resultado Conjunto)</option>';
    const formVeh = document.getElementById('rep-vehiculo');
    formVeh.innerHTML = '';
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
        if (parseInt(v.id, 10) === vehiculoId) opt2.selected = true;
        formVeh.appendChild(opt2);
    });

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

function renderTable() {
    const q = (document.getElementById('search-input').value || '').toLowerCase().trim();
    const tbody = document.getElementById('repostajes-tbody');
    tbody.innerHTML = '';

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
            <td style="font-weight:700;">${fmtNum(r.importe_total, 2)} €</td>
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

function openRepostajeModal() {
    document.getElementById('rep-id').value = '0';
    document.getElementById('rep-fecha').value = new Date().toISOString().slice(0, 10);
    document.getElementById('rep-km').value = '';
    document.getElementById('rep-litros').value = '';
    document.getElementById('rep-precio').value = '';
    document.getElementById('rep-importe').value = '';
    document.getElementById('rep-estacion').value = '';
    document.getElementById('rep-notas').value = '';
    document.getElementById('repostaje-modal').classList.add('active');
}

function editRepostaje(r) {
    document.getElementById('rep-id').value = r.id;
    document.getElementById('rep-vehiculo').value = r.vehiculo_id;
    document.getElementById('rep-fecha').value = r.fecha;
    document.getElementById('rep-km').value = r.km_odometro;
    document.getElementById('rep-litros').value = r.litros;
    document.getElementById('rep-precio').value = r.precio_litro;
    document.getElementById('rep-importe').value = r.importe_total;
    document.getElementById('rep-estacion').value = r.estacion_servicio || '';
    document.getElementById('rep-notas').value = r.notas || '';
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

// GENERACIÓN DE INFORMES PDF EN EL MÓVIL
function exportPDFReport() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    const s = currentData.resumen || {};
    const selText = document.getElementById('vehicle-selector').selectedOptions[0].textContent;

    doc.setFontSize(16);
    doc.setTextColor(15, 118, 110);
    doc.text('Informe de Control de Combustible y Consumo', 14, 15);

    doc.setFontSize(10);
    doc.setTextColor(80, 80, 80);
    doc.text(`Ámbito: ${selText} | Fecha de emisión: ${new Date().toLocaleDateString('es-ES')}`, 14, 22);
    doc.text(
        `Total Litros: ${fmtNum(s.total_litros, 2)} L | Total Gasto: ${fmtNum(s.total_euros, 2)} € | Km Recorridos: ${fmtNum(s.total_km_recorridos, 0)} km | Consumo Medio: ${fmtNum(s.consumo_medio_l100, 2)} L/100km | Máx/Mín: ${fmtNum(s.consumo_maximo, 2)} / ${fmtNum(s.consumo_minimo, 2)} L/100`,
        14, 28
    );

    const rows = (currentData.repostajes || []).map(r => [
        r.fecha,
        r.vehiculo_nombre || '-',
        fmtNum(r.litros, 2),
        fmtNum(r.precio_litro, 3),
        fmtNum(r.importe_total, 2) + ' €',
        fmtNum(r.km_odometro, 0),
        r.dif_km > 0 ? fmtNum(r.dif_km, 0) : '-',
        r.litros_por_100 !== null ? fmtNum(r.litros_por_100, 2) : '-',
        r.km_por_litro !== null ? fmtNum(r.km_por_litro, 2) : '-',
        r.euros_por_km !== null ? fmtNum(r.euros_por_km, 4) : '-',
        fmtNum(r.total_litros_acum, 1),
        fmtNum(r.total_euros_acum, 2) + ' €',
        r.notas || ''
    ]);

    doc.autoTable({
        startY: 33,
        head: [['Fecha', 'Vehículo', 'Litros', '€/L', 'Importe', 'KM', 'DIF Km', 'L/100', 'Km/L', '€/Km', 'T.Litros', 'Total €', 'Notas']],
        body: rows,
        styles: { fontSize: 7.5, cellPadding: 1.8 },
        headStyles: { fillColor: [15, 118, 110] }
    });

    doc.save(`Informe_Combustible_${new Date().toISOString().slice(0,10)}.pdf`);
}

// Restaurar sesión si existe
window.addEventListener('DOMContentLoaded', async () => {
    const saved = sessionStorage.getItem('veh_auth');
    if (saved) {
        authCreds = JSON.parse(saved);
        const res = await apiCall('login');
        if (res.success) {
            document.getElementById('login-screen').classList.add('hidden');
            document.getElementById('app-screen').classList.remove('hidden');
            await loadDashboard();
        }
    }
});
</script>
</body>
</html>
