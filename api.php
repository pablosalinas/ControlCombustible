<?php
/**
 * api.php
 * API REST para la aplicación Android (Flutter) y Web de Control de Combustible.
 * Conectada a la base de datos y sistema de usuarios y roles de Moratalla-Murcia-2026.
 * Compatible con PHP 5.6, 7.x y 8.x.
 *
 * REGLA ESTRICTA DE PROTECCIÓN DE DATOS:
 * - Prohibido ejecutar DELETE, DROP, TRUNCATE o alterar registros de otras tablas.
 * - La tabla `users` se consulta en modo SOLO LECTURA para autenticación y rol (`admin` / `editor`).
 * - Todas las bajas en `veh_vehiculos` y `veh_repostajes` usan borrado lógico (`activo = 0`).
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/FuelCalculator.php';
require_once __DIR__ . '/src/FuelMailer.php';

try {
    $pdo = getVehDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error'   => 'Error de conexión a la base de datos: ' . $e->getMessage()
    ));
    exit;
}

// Leer cuerpo JSON si se envía
$rawInput = file_get_contents('php://input');
$jsonBody = array();
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $jsonBody = $decoded;
    }
}

/**
 * Obtiene un valor de un array con fallback (compatible PHP 5.6+)
 */
function arr_get(array $arr, $key, $default = null) {
    return isset($arr[$key]) ? $arr[$key] : $default;
}

/**
 * Autenticación contra la tabla `users` de Moratalla-Murcia-2026 (SOLO LECTURA)
 * utilizando el sistema de roles (`admin` tiene visión global de todos los vehículos;
 * otros roles gestionan únicamente sus propios vehículos).
 */
function authenticateMoratallaUser(PDO $pdo, array $jsonBody) {
    $username = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
    $password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';

    if (!$username || !$password) {
        $authHeader = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if ($authHeader && preg_match('/Basic\s+(.*)$/i', $authHeader, $matches)) {
            $decoded = base64_decode($matches[1]);
            if (strpos($decoded, ':') !== false) {
                list($username, $password) = explode(':', $decoded, 2);
            }
        }
    }

    if (!$username || !$password) {
        $username = trim((string)arr_get($jsonBody, 'username', isset($_POST['username']) ? $_POST['username'] : ''));
        $password = (string)arr_get($jsonBody, 'password', isset($_POST['password']) ? $_POST['password'] : '');
    }

    if (!$username || !$password) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (!empty($_SESSION['admin_id'])) {
            $stmtSess = $pdo->prepare("SELECT id, username, email, role, acceso_sistema, es_superusuario FROM `users` WHERE id = ? LIMIT 1");
            $stmtSess->execute(array((int)$_SESSION['admin_id']));
            $uSess = $stmtSess->fetch();
            if ($uSess) {
                $roleSess = isset($uSess['role']) ? $uSess['role'] : 'editor';
                $esSuperSess = !empty($uSess['es_superusuario']) || ($roleSess === 'admin' && !isset($uSess['es_superusuario']));
                $accSess = isset($uSess['acceso_sistema']) && $uSess['acceso_sistema'] !== '' ? $uSess['acceso_sistema'] : 'ambos';
                if (!$esSuperSess && $accSess === 'web') {
                    http_response_code(403);
                    echo json_encode(array(
                        'success' => false,
                        'error'   => 'Tu usuario tiene asignado acceso exclusivo a moratalla-murcia.com.'
                    ));
                    exit;
                }
                return array(
                    'id'             => (int)$uSess['id'],
                    'username'       => $uSess['username'],
                    'email'          => isset($uSess['email']) ? $uSess['email'] : '',
                    'role'           => $roleSess,
                    'acceso_sistema' => $esSuperSess ? 'ambos' : $accSess,
                    'is_admin'       => (bool)$esSuperSess,
                );
            }
        }

        http_response_code(401);
        echo json_encode(array(
            'success' => false,
            'error'   => 'Credenciales requeridas (usuario de moratalla-murcia.com).'
        ));
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, email, password, role, acceso_sistema, es_superusuario FROM `users` WHERE username = ? LIMIT 1");
    $stmt->execute(array($username));
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(array(
            'success' => false,
            'error'   => 'Usuario o contraseña de moratalla-murcia.com incorrectos.'
        ));
        exit;
    }

    $role = isset($user['role']) ? $user['role'] : 'editor';
    $esSuper = !empty($user['es_superusuario']) || ($role === 'admin' && !isset($user['es_superusuario']));
    $accesoSistema = isset($user['acceso_sistema']) && $user['acceso_sistema'] !== '' ? $user['acceso_sistema'] : 'ambos';

    // Si no es superusuario y solo tiene asignado 'web', denegar acceso a la gestión de combustible
    if (!$esSuper && $accesoSistema === 'web') {
        http_response_code(403);
        echo json_encode(array(
            'success' => false,
            'error'   => 'Tu usuario tiene asignado acceso exclusivo a moratalla-murcia.com. Solicita al superusuario la activación para Gestión de Combustible.'
        ));
        exit;
    }

    return array(
        'id'              => (int)$user['id'],
        'username'        => $user['username'],
        'email'           => isset($user['email']) ? $user['email'] : '',
        'role'            => $role,
        'acceso_sistema'  => $esSuper ? 'ambos' : $accesoSistema,
        'is_admin'        => (bool)$esSuper,
    );
}

function computeNextTaskTargets($tipo, $ultimaFecha, $ultimoKm, $periodoDias, $periodoKm)
{
    $proximaFecha = null;
    $proximoKm    = null;

    if (($tipo === 'tiempo' || $tipo === 'ambos') && $periodoDias > 0) {
        $baseTs = !empty($ultimaFecha) ? strtotime($ultimaFecha) : time();
        if ($baseTs !== false) {
            $proximaFecha = date('Y-m-d', strtotime('+' . (int)$periodoDias . ' days', $baseTs));
        }
    }
    if (($tipo === 'kilometros' || $tipo === 'ambos') && $periodoKm > 0) {
        $baseKm = ($ultimoKm !== null && $ultimoKm !== '') ? (float)$ultimoKm : 0.0;
        $proximoKm = round($baseKm + (float)$periodoKm, 2);
    }

    return array($proximaFecha, $proximoKm);
}

function getVehiculoTareasEnriched($pdo, $userId, $isAdmin, $vehiculoId = 0, $estadoFilter = 'todas')
{
    $sql = "
        SELECT vt.*, v.nombre AS vehiculo_nombre, v.matricula AS vehiculo_matricula,
               COALESCE((SELECT MAX(r.km_odometro) FROM `veh_repostajes` r WHERE r.vehiculo_id = vt.vehiculo_id AND r.activo = 1), v.km_iniciales, 0) AS km_actual
        FROM `veh_vehiculo_tareas` vt
        INNER JOIN `veh_vehiculos` v ON v.id = vt.vehiculo_id AND v.activo = 1
        WHERE vt.activo = 1
    ";
    $params = array();
    if (!$isAdmin) {
        $sql .= " AND v.usuario_id = ?";
        $params[] = $userId;
    }
    if ($vehiculoId > 0) {
        $sql .= " AND vt.vehiculo_id = ?";
        $params[] = $vehiculoId;
    }
    if ($estadoFilter === 'pendiente' || $estadoFilter === 'finalizada') {
        $sql .= " AND vt.estado = ?";
        $params[] = $estadoFilter;
    } elseif ($estadoFilter === 'proximas') {
        $sql .= " AND vt.estado = 'pendiente'";
    }
    $sql .= " ORDER BY vt.estado ASC, COALESCE(vt.proxima_fecha, '9999-12-31') ASC, COALESCE(vt.proximo_km, 999999999) ASC, vt.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $todayTs = strtotime(date('Y-m-d'));
    $result = array();
    foreach ($rows as $row) {
        $kmActual = (float)$row['km_actual'];
        $proximoKm = ($row['proximo_km'] !== null && $row['proximo_km'] !== '') ? (float)$row['proximo_km'] : null;
        $proximaFecha = !empty($row['proxima_fecha']) ? $row['proxima_fecha'] : null;

        $kmRestantes = ($proximoKm !== null) ? round($proximoKm - $kmActual, 2) : null;
        $diasRestantes = ($proximaFecha !== null) ? (int)round((strtotime($proximaFecha) - $todayTs) / 86400) : null;

        $tipo = $row['tipo_periodicidad'];
        $avisarTiempoSinKm = !empty($row['avisar_por_tiempo_si_no_km']);
        $enAlerta = false;
        $vencida = false;
        $motivos = array();

        if ($row['estado'] === 'pendiente') {
            $alertaKm = ($kmRestantes !== null && $kmRestantes <= 1000);
            $vencidaKm = ($kmRestantes !== null && $kmRestantes <= 0);
            $alertaTiempo = ($diasRestantes !== null && $diasRestantes <= 15);
            $vencidaTiempo = ($diasRestantes !== null && $diasRestantes <= 0);

            if ($tipo === 'kilometros') {
                if ($alertaKm) {
                    $enAlerta = true;
                    $vencida = $vencidaKm;
                    $motivos[] = $vencidaKm
                        ? ('Vencida por km (' . number_format(abs($kmRestantes), 0, ',', '.') . ' km excedidos)')
                        : ('Faltan ' . number_format($kmRestantes, 0, ',', '.') . ' km');
                }
            } elseif ($tipo === 'tiempo') {
                if ($alertaTiempo) {
                    $enAlerta = true;
                    $vencida = $vencidaTiempo;
                    $motivos[] = $vencidaTiempo
                        ? ('Vencida por tiempo (' . abs($diasRestantes) . ' días)')
                        : ('Faltan ' . $diasRestantes . ' días');
                }
            } else {
                // 'ambos'
                if ($alertaKm) {
                    $enAlerta = true;
                    if ($vencidaKm) $vencida = true;
                    $motivos[] = $vencidaKm
                        ? ('Vencida por km (' . number_format(abs($kmRestantes), 0, ',', '.') . ' km excedidos)')
                        : ('Faltan ' . number_format($kmRestantes, 0, ',', '.') . ' km');
                }
                if ($alertaTiempo && ($avisarTiempoSinKm || $alertaKm)) {
                    $enAlerta = true;
                    if ($vencidaTiempo) $vencida = true;
                    $suffix = (!$alertaKm && $avisarTiempoSinKm) ? ' (aviso por tiempo sin llegar al km)' : '';
                    $motivos[] = $vencidaTiempo
                        ? ('Vencida por tiempo (' . abs($diasRestantes) . ' días)' . $suffix)
                        : ('Faltan ' . $diasRestantes . ' días' . $suffix);
                }
            }
        }

        if ($estadoFilter === 'proximas' && !$enAlerta) {
            continue;
        }

        $row['km_actual']      = $kmActual;
        $row['km_restantes']   = $kmRestantes;
        $row['dias_restantes'] = $diasRestantes;
        $row['en_alerta']      = $enAlerta;
        $row['vencida']        = $vencida;
        $row['motivo_alerta']  = implode(' · ', $motivos);
        $result[] = $row;
    }
    return $result;
}

$user    = authenticateMoratallaUser($pdo, $jsonBody);
$userId  = (int)$user['id'];
$isAdmin = (bool)$user['is_admin'];
$action  = isset($_GET['action']) ? $_GET['action'] : arr_get($jsonBody, 'action', isset($_POST['action']) ? $_POST['action'] : 'dashboard');

try {
    switch ($action) {
        case 'login':
            echo json_encode(array(
                'success' => true,
                'message' => 'Autenticado correctamente con moratalla-murcia.com',
                'user'    => $user,
            ));
            break;

        case 'list_vehiculos':
            if ($isAdmin) {
                $stmt = $pdo->query("
                    SELECT v.id, v.usuario_id, u.username AS propietario, v.nombre, v.matricula, v.marca, v.modelo, v.tipo_combustible, v.km_iniciales, v.notas, v.created_at
                    FROM `veh_vehiculos` v
                    LEFT JOIN `users` u ON u.id = v.usuario_id
                    WHERE v.activo = 1
                    ORDER BY v.id ASC
                ");
            } else {
                $stmt = $pdo->prepare("
                    SELECT v.id, v.usuario_id, u.username AS propietario, v.nombre, v.matricula, v.marca, v.modelo, v.tipo_combustible, v.km_iniciales, v.notas, v.created_at
                    FROM `veh_vehiculos` v
                    LEFT JOIN `users` u ON u.id = v.usuario_id
                    WHERE v.usuario_id = ? AND v.activo = 1
                    ORDER BY v.id ASC
                ");
                $stmt->execute(array($userId));
            }
            $vehiculos = $stmt->fetchAll();

            echo json_encode(array(
                'success'   => true,
                'vehiculos' => $vehiculos,
            ));
            break;

        case 'save_vehiculo':
            $id              = (int)arr_get($jsonBody, 'id', isset($_POST['id']) ? $_POST['id'] : 0);
            $nombre          = trim((string)arr_get($jsonBody, 'nombre', isset($_POST['nombre']) ? $_POST['nombre'] : ''));
            $matricula       = trim((string)arr_get($jsonBody, 'matricula', isset($_POST['matricula']) ? $_POST['matricula'] : ''));
            $marca           = trim((string)arr_get($jsonBody, 'marca', isset($_POST['marca']) ? $_POST['marca'] : ''));
            $modelo          = trim((string)arr_get($jsonBody, 'modelo', isset($_POST['modelo']) ? $_POST['modelo'] : ''));
            $tipoCombustible = trim((string)arr_get($jsonBody, 'tipo_combustible', isset($_POST['tipo_combustible']) ? $_POST['tipo_combustible'] : 'Diésel'));
            $kmIniciales     = (float)arr_get($jsonBody, 'km_iniciales', isset($_POST['km_iniciales']) ? $_POST['km_iniciales'] : 0);
            $notas           = trim((string)arr_get($jsonBody, 'notas', isset($_POST['notas']) ? $_POST['notas'] : ''));

            if ($nombre === '') {
                http_response_code(400);
                echo json_encode(array('success' => false, 'error' => 'El nombre del vehículo es obligatorio.'));
                exit;
            }

            if ($id > 0) {
                if ($isAdmin) {
                    $stmt = $pdo->prepare("
                        UPDATE `veh_vehiculos`
                        SET nombre = ?, matricula = ?, marca = ?, modelo = ?, tipo_combustible = ?, km_iniciales = ?, notas = ?
                        WHERE id = ? AND activo = 1
                    ");
                    $stmt->execute(array($nombre, $matricula, $marca, $modelo, $tipoCombustible, $kmIniciales, $notas, $id));
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE `veh_vehiculos`
                        SET nombre = ?, matricula = ?, marca = ?, modelo = ?, tipo_combustible = ?, km_iniciales = ?, notas = ?
                        WHERE id = ? AND usuario_id = ? AND activo = 1
                    ");
                    $stmt->execute(array($nombre, $matricula, $marca, $modelo, $tipoCombustible, $kmIniciales, $notas, $id, $userId));
                }
                $savedId = $id;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO `veh_vehiculos` (usuario_id, nombre, matricula, marca, modelo, tipo_combustible, km_iniciales, notas, activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute(array($userId, $nombre, $matricula, $marca, $modelo, $tipoCombustible, $kmIniciales, $notas));
                $savedId = (int)$pdo->lastInsertId();
            }

            echo json_encode(array(
                'success'     => true,
                'message'     => $id > 0 ? 'Vehículo actualizado correctamente.' : 'Vehículo dado de alta correctamente.',
                'vehiculo_id' => $savedId,
            ));
            break;

        case 'deactivate_vehiculo':
            // Baja lógica estricta (activo = 0), jamás DELETE
            $id = (int)arr_get($jsonBody, 'id', isset($_POST['id']) ? $_POST['id'] : 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(array('success' => false, 'error' => 'ID de vehículo inválido.'));
                exit;
            }
            if ($isAdmin) {
                $stmt = $pdo->prepare("UPDATE `veh_vehiculos` SET activo = 0 WHERE id = ?");
                $stmt->execute(array($id));
            } else {
                $stmt = $pdo->prepare("UPDATE `veh_vehiculos` SET activo = 0 WHERE id = ? AND usuario_id = ?");
                $stmt->execute(array($id, $userId));
            }

            echo json_encode(array(
                'success' => true,
                'message' => 'Vehículo dado de baja lógicamente (activo = 0).',
            ));
            break;

        case 'dashboard':
        case 'list_repostajes':
            $vehiculoId = isset($_GET['vehiculo_id']) ? (int)$_GET['vehiculo_id'] : (int)arr_get($jsonBody, 'vehiculo_id', 0);
            $fechaDesde = trim((string)(isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : arr_get($jsonBody, 'fecha_desde', '')));
            $fechaHasta = trim((string)(isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : arr_get($jsonBody, 'fecha_hasta', '')));

            // Obtener vehículos activos (todos si el usuario tiene rol 'admin', o solo los suyos)
            if ($isAdmin) {
                $stmtVeh = $pdo->query("
                    SELECT v.id, v.usuario_id, u.username AS propietario, v.nombre, v.matricula, v.marca, v.modelo, v.tipo_combustible, v.km_iniciales, v.notas
                    FROM `veh_vehiculos` v
                    LEFT JOIN `users` u ON u.id = v.usuario_id
                    WHERE v.activo = 1
                    ORDER BY v.id ASC
                ");
            } else {
                $stmtVeh = $pdo->prepare("
                    SELECT v.id, v.usuario_id, u.username AS propietario, v.nombre, v.matricula, v.marca, v.modelo, v.tipo_combustible, v.km_iniciales, v.notas
                    FROM `veh_vehiculos` v
                    LEFT JOIN `users` u ON u.id = v.usuario_id
                    WHERE v.usuario_id = ? AND v.activo = 1
                    ORDER BY v.id ASC
                ");
                $stmtVeh->execute(array($userId));
            }
            $vehiculos = $stmtVeh->fetchAll();

            // Obtener repostajes activos ordenados cronológicamente
            $sqlRep = "
                SELECT r.*, v.nombre AS vehiculo_nombre, v.matricula AS vehiculo_matricula
                FROM `veh_repostajes` r
                INNER JOIN `veh_vehiculos` v ON v.id = r.vehiculo_id AND v.activo = 1
                WHERE r.activo = 1
            ";
            $params = array();

            if (!$isAdmin) {
                $sqlRep .= " AND r.usuario_id = ?";
                $params[] = $userId;
            }

            if ($vehiculoId > 0) {
                $sqlRep .= " AND r.vehiculo_id = ?";
                $params[] = $vehiculoId;
            }

            $sqlRep .= " ORDER BY r.vehiculo_id ASC, r.km_odometro ASC, r.fecha ASC, r.id ASC";
            $stmtRep = $pdo->prepare($sqlRep);
            $stmtRep->execute($params);
            $allRows = $stmtRep->fetchAll();

            // Agrupar por vehículo para referenciar siempre al repostaje anterior del mismo vehículo
            $rowsByVehicle = array();
            foreach ($allRows as $row) {
                $vId = (int)$row['vehiculo_id'];
                if (!isset($rowsByVehicle[$vId])) {
                    $rowsByVehicle[$vId] = array();
                }
                $rowsByVehicle[$vId][] = $row;
            }

            if ($vehiculoId > 0) {
                $vehicleRows = isset($rowsByVehicle[$vehiculoId]) ? $rowsByVehicle[$vehiculoId] : array();
                $calc = FuelCalculator::calculateVehicleSeries($vehicleRows);
                $repostajesCalculados = array_reverse($calc['repostajes']);
                $resumen = $calc['resumen'];
                $resumenPorVehiculo = array($vehiculoId => $resumen);
            } else {
                $consolidated = FuelCalculator::calculateConsolidatedFleet($rowsByVehicle);
                $repostajesCalculados = $consolidated['repostajes'];
                $resumen = $consolidated['resumen_consolidado'];
                $resumenPorVehiculo = $consolidated['resumen_por_vehiculo'];
            }

            if ($fechaDesde !== '' || $fechaHasta !== '') {
                $repostajesCalculados = array_values(array_filter($repostajesCalculados, function ($r) use ($fechaDesde, $fechaHasta) {
                    if ($fechaDesde !== '' && strcmp($r['fecha'], $fechaDesde) < 0) return false;
                    if ($fechaHasta !== '' && strcmp($r['fecha'], $fechaHasta) > 0) return false;
                    return true;
                }));
            }

            // Obtener estaciones de servicio activas (comunes para todos los usuarios)
            $stmtEst = $pdo->query("
                SELECT id, usuario_id, nombre, marca, localidad, direccion, notas
                FROM `veh_estaciones`
                WHERE activo = 1
                ORDER BY nombre ASC
            ");
            $estaciones = $stmtEst->fetchAll();

            // Obtener último odómetro registrado por cada vehículo del usuario
            if ($isAdmin) {
                $stmtLastKm = $pdo->query("
                    SELECT vehiculo_id, MAX(km_odometro) AS ultimo_km
                    FROM `veh_repostajes`
                    WHERE activo = 1
                    GROUP BY vehiculo_id
                ");
            } else {
                $stmtLastKm = $pdo->prepare("
                    SELECT vehiculo_id, MAX(km_odometro) AS ultimo_km
                    FROM `veh_repostajes`
                    WHERE usuario_id = ? AND activo = 1
                    GROUP BY vehiculo_id
                ");
                $stmtLastKm->execute(array($userId));
            }
            $ultimoKmPorVehiculo = array();
            while ($lk = $stmtLastKm->fetch()) {
                $ultimoKmPorVehiculo[(int)$lk['vehiculo_id']] = (float)$lk['ultimo_km'];
            }

            $tareasMaestro = $pdo->query("
                SELECT id, usuario_id, nombre, descripcion, tipo_periodicidad, periodo_dias, periodo_km, avisar_por_tiempo_si_no_km
                FROM `veh_tareas_maestro`
                WHERE activo = 1
                ORDER BY nombre ASC
            ")->fetchAll();

            $vehiculoTareas = getVehiculoTareasEnriched($pdo, $userId, $isAdmin, $vehiculoId, 'todas');

            echo json_encode(array(
                'success'                  => true,
                'user'                     => $user,
                'vehiculos'                => $vehiculos,
                'estaciones'               => $estaciones,
                'tareas_maestro'           => $tareasMaestro,
                'vehiculo_tareas'          => $vehiculoTareas,
                'ultimo_km_por_vehiculo'   => $ultimoKmPorVehiculo,
                'vehiculo_seleccionado'    => $vehiculoId,
                'resumen'                  => $resumen,
                'resumen_por_vehiculo'     => $resumenPorVehiculo,
                'repostajes'               => $repostajesCalculados,
            ));
            break;

        case 'save_repostaje':
            $id               = (int)arr_get($jsonBody, 'id', isset($_POST['id']) ? $_POST['id'] : 0);
            $vehiculoId       = (int)arr_get($jsonBody, 'vehiculo_id', isset($_POST['vehiculo_id']) ? $_POST['vehiculo_id'] : 0);
            $fecha            = trim((string)arr_get($jsonBody, 'fecha', isset($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d')));
            $litros           = (float)str_replace(',', '.', (string)arr_get($jsonBody, 'litros', isset($_POST['litros']) ? $_POST['litros'] : 0));
            $precioLitro      = (float)str_replace(',', '.', (string)arr_get($jsonBody, 'precio_litro', isset($_POST['precio_litro']) ? $_POST['precio_litro'] : 0));
            $importeTotal     = (float)str_replace(',', '.', (string)arr_get($jsonBody, 'importe_total', isset($_POST['importe_total']) ? $_POST['importe_total'] : 0));
            $kmOdometro       = (float)str_replace(',', '.', (string)arr_get($jsonBody, 'km_odometro', isset($_POST['km_odometro']) ? $_POST['km_odometro'] : 0));
            $estacionServicio = trim((string)arr_get($jsonBody, 'estacion_servicio', isset($_POST['estacion_servicio']) ? $_POST['estacion_servicio'] : ''));
            $notas            = trim((string)arr_get($jsonBody, 'notas', isset($_POST['notas']) ? $_POST['notas'] : ''));

            // Autocalcular el tercer valor si se proporcionan dos
            if ($importeTotal <= 0 && $litros > 0 && $precioLitro > 0) {
                $importeTotal = round($litros * $precioLitro, 2);
            } elseif ($litros <= 0 && $importeTotal > 0 && $precioLitro > 0) {
                $litros = round($importeTotal / $precioLitro, 3);
            } elseif ($precioLitro <= 0 && $importeTotal > 0 && $litros > 0) {
                $precioLitro = round($importeTotal / $litros, 4);
            }

            if ($vehiculoId <= 0 || $litros <= 0 || $precioLitro <= 0) {
                http_response_code(400);
                echo json_encode(array(
                    'success' => false,
                    'error'   => 'Vehículo, litros y precio por litro son obligatorios.'
                ));
                exit;
            }

            if ($isAdmin) {
                $stmtCheck = $pdo->prepare("SELECT id, usuario_id, nombre FROM `veh_vehiculos` WHERE id = ? AND activo = 1");
                $stmtCheck->execute(array($vehiculoId));
            } else {
                $stmtCheck = $pdo->prepare("SELECT id, usuario_id, nombre FROM `veh_vehiculos` WHERE id = ? AND usuario_id = ? AND activo = 1");
                $stmtCheck->execute(array($vehiculoId, $userId));
            }
            $vehRow = $stmtCheck->fetch();
            if (!$vehRow) {
                http_response_code(403);
                echo json_encode(array('success' => false, 'error' => 'Vehículo no válido o sin permisos.'));
                exit;
            }
            $ownerUserId = (int)$vehRow['usuario_id'];

            if ($id > 0) {
                if ($isAdmin) {
                    $stmt = $pdo->prepare("
                        UPDATE `veh_repostajes`
                        SET vehiculo_id = ?, usuario_id = ?, fecha = ?, litros = ?, precio_litro = ?, importe_total = ?, km_odometro = ?, estacion_servicio = ?, notas = ?
                        WHERE id = ? AND activo = 1
                    ");
                    $stmt->execute(array($vehiculoId, $ownerUserId, $fecha, $litros, $precioLitro, $importeTotal, $kmOdometro, $estacionServicio, $notas, $id));
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE `veh_repostajes`
                        SET vehiculo_id = ?, fecha = ?, litros = ?, precio_litro = ?, importe_total = ?, km_odometro = ?, estacion_servicio = ?, notas = ?
                        WHERE id = ? AND usuario_id = ? AND activo = 1
                    ");
                    $stmt->execute(array($vehiculoId, $fecha, $litros, $precioLitro, $importeTotal, $kmOdometro, $estacionServicio, $notas, $id, $userId));
                }
                $savedId = $id;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO `veh_repostajes`
                    (vehiculo_id, usuario_id, fecha, litros, precio_litro, importe_total, km_odometro, estacion_servicio, notas, activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute(array($vehiculoId, $ownerUserId, $fecha, $litros, $precioLitro, $importeTotal, $kmOdometro, $estacionServicio, $notas));
                $savedId = (int)$pdo->lastInsertId();
            }

            $emailSent = false;
            $emailError = null;
            $alertTasks = array();
            if ($id === 0) {
                // Al registrar un nuevo repostaje, comprobar tareas en alerta (<1000 km o <=15 días) y enviar correo
                try {
                    $allProximas = getVehiculoTareasEnriched($pdo, $userId, $isAdmin, 0, 'proximas');
                    $alertTasks = $allProximas;
                    $repMailData = array(
                        'fecha'             => $fecha,
                        'km_odometro'       => $kmOdometro,
                        'litros'            => $litros,
                        'precio_litro'      => $precioLitro,
                        'importe_total'     => $importeTotal,
                        'estacion_servicio' => $estacionServicio,
                    );
                    $emailSent = FuelMailer::sendRefuelNotification($pdo, $user, $vehRow['nombre'], $repMailData, $alertTasks, $emailError);
                    if ($emailSent && !empty($alertTasks)) {
                        $stmtMark = $pdo->prepare("UPDATE `veh_vehiculo_tareas` SET aviso_enviado = 1 WHERE id = ?");
                        foreach ($alertTasks as $at) {
                            $stmtMark->execute(array((int)$at['id']));
                        }
                    }
                } catch (Exception $mailEx) {
                    $emailError = $mailEx->getMessage();
                }
            }

            echo json_encode(array(
                'success'       => true,
                'message'       => $id > 0 ? 'Repostaje actualizado correctamente.' : 'Repostaje registrado correctamente.',
                'repostaje_id'  => $savedId,
                'email_sent'    => $emailSent,
                'email_error'   => $emailError,
                'alertas_count' => count($alertTasks),
            ));
            break;

        case 'deactivate_repostaje':
            // Baja lógica estricta (activo = 0), jamás DELETE
            $id = (int)arr_get($jsonBody, 'id', isset($_POST['id']) ? $_POST['id'] : 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(array('success' => false, 'error' => 'ID de repostaje inválido.'));
                exit;
            }
            if ($isAdmin) {
                $stmt = $pdo->prepare("UPDATE `veh_repostajes` SET activo = 0 WHERE id = ?");
                $stmt->execute(array($id));
            } else {
                $stmt = $pdo->prepare("UPDATE `veh_repostajes` SET activo = 0 WHERE id = ? AND usuario_id = ?");
                $stmt->execute(array($id, $userId));
            }

            echo json_encode(array(
                'success' => true,
                'message' => 'Repostaje dado de baja lógicamente (activo = 0).',
            ));
            break;

        case 'list_estaciones':
            $stmt = $pdo->query("
                SELECT id, usuario_id, nombre, marca, localidad, direccion, notas, created_at
                FROM `veh_estaciones`
                WHERE activo = 1
                ORDER BY nombre ASC
            ");
            echo json_encode(array(
                'success'    => true,
                'estaciones' => $stmt->fetchAll(),
            ));
            break;

        case 'save_estacion':
            $id        = (int)arr_get($jsonBody, 'id', isset($_POST['id']) ? $_POST['id'] : 0);
            $nombre    = trim((string)arr_get($jsonBody, 'nombre', isset($_POST['nombre']) ? $_POST['nombre'] : ''));
            $marca     = trim((string)arr_get($jsonBody, 'marca', isset($_POST['marca']) ? $_POST['marca'] : ''));
            $localidad = trim((string)arr_get($jsonBody, 'localidad', isset($_POST['localidad']) ? $_POST['localidad'] : ''));
            $direccion = trim((string)arr_get($jsonBody, 'direccion', isset($_POST['direccion']) ? $_POST['direccion'] : ''));
            $notas     = trim((string)arr_get($jsonBody, 'notas', isset($_POST['notas']) ? $_POST['notas'] : ''));

            if ($nombre === '') {
                http_response_code(400);
                echo json_encode(array('success' => false, 'error' => 'El nombre de la estación de servicio es obligatorio.'));
                exit;
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE `veh_estaciones`
                    SET nombre = ?, marca = ?, localidad = ?, direccion = ?, notas = ?
                    WHERE id = ? AND activo = 1
                ");
                $stmt->execute(array($nombre, $marca, $localidad, $direccion, $notas, $id));
                $savedId = $id;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO `veh_estaciones` (usuario_id, nombre, marca, localidad, direccion, notas, activo)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute(array($userId, $nombre, $marca, $localidad, $direccion, $notas));
                $savedId = (int)$pdo->lastInsertId();
            }

            echo json_encode(array(
                'success'     => true,
                'message'     => $id > 0 ? 'Estación actualizada correctamente.' : 'Estación dada de alta correctamente.',
                'estacion_id' => $savedId,
            ));
            break;

        case 'deactivate_estacion':
            $id = (int)arr_get($jsonBody, 'id', isset($_POST['id']) ? $_POST['id'] : 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(array('success' => false, 'error' => 'ID de estación inválido.'));
                exit;
            }
            $stmt = $pdo->prepare("UPDATE `veh_estaciones` SET activo = 0 WHERE id = ?");
            $stmt->execute(array($id));
            echo json_encode(array(
                'success' => true,
                'message' => 'Estación dada de baja lógicamente (activo = 0).',
            ));
            break;

        case 'import_csv':
            $vehiculoId = (int)arr_get($jsonBody, 'vehiculo_id', isset($_POST['vehiculo_id']) ? $_POST['vehiculo_id'] : 0);

            if ($vehiculoId <= 0) {
                if ($isAdmin) {
                    $stmtV = $pdo->query("SELECT id, usuario_id FROM `veh_vehiculos` WHERE activo = 1 ORDER BY id ASC LIMIT 1");
                } else {
                    $stmtV = $pdo->prepare("SELECT id, usuario_id FROM `veh_vehiculos` WHERE usuario_id = ? AND activo = 1 ORDER BY id ASC LIMIT 1");
                    $stmtV->execute(array($userId));
                }
                $vRow = $stmtV->fetch();
                if ($vRow) {
                    $vehiculoId  = (int)$vRow['id'];
                    $ownerUserId = (int)$vRow['usuario_id'];
                } else {
                    $stmtCreate = $pdo->prepare("
                        INSERT INTO `veh_vehiculos` (usuario_id, nombre, matricula, marca, modelo, tipo_combustible, km_iniciales, notas, activo)
                        VALUES (?, 'Vehículo Principal (Histórico)', '', 'Principal', 'Histórico 2015-2025', 'Diésel', 0, 'Importado de hoja de cálculo', 1)
                    ");
                    $stmtCreate->execute(array($userId));
                    $vehiculoId  = (int)$pdo->lastInsertId();
                    $ownerUserId = $userId;
                }
            } else {
                $stmtV = $pdo->prepare("SELECT usuario_id FROM `veh_vehiculos` WHERE id = ?");
                $stmtV->execute(array($vehiculoId));
                $vRow = $stmtV->fetch();
                $ownerUserId = $vRow ? (int)$vRow['usuario_id'] : $userId;
            }

            $csvFile = __DIR__ . '/datos/historico_repostajes.csv';
            if (!file_exists($csvFile)) {
                http_response_code(404);
                echo json_encode(array('success' => false, 'error' => 'No se encontró datos/historico_repostajes.csv'));
                exit;
            }

            $handle = fopen($csvFile, 'r');
            $header = fgetcsv($handle, 0, ',');
            $inserted = 0;
            $skipped  = 0;

            $stmtExists = $pdo->prepare("
                SELECT id FROM `veh_repostajes`
                WHERE vehiculo_id = ? AND fecha = ? AND ABS(km_odometro - ?) < 0.01 AND ABS(litros - ?) < 0.01 AND activo = 1
                LIMIT 1
            ");
            $stmtIns = $pdo->prepare("
                INSERT INTO `veh_repostajes`
                (vehiculo_id, usuario_id, fecha, litros, precio_litro, importe_total, km_odometro, notas, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");

            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                if (count($row) < 5) continue;
                $fecha   = trim($row[0]);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) continue;

                $litros  = (float)$row[1];
                $precio  = (float)$row[2];
                $importe = (float)$row[3];
                $km      = (float)$row[4];
                $notaCol = isset($row[16]) ? trim($row[16]) : '';
                $notas   = (!is_numeric($notaCol) && $notaCol !== '' && strpos($notaCol, 'PRECIO') === false && strpos($notaCol, 'MINIMO') === false)
                    ? $notaCol
                    : null;

                $stmtExists->execute(array($vehiculoId, $fecha, $km, $litros));
                if ($stmtExists->fetch()) {
                    $skipped++;
                    continue;
                }

                $stmtIns->execute(array($vehiculoId, $ownerUserId, $fecha, $litros, $precio, $importe, $km, $notas));
                $inserted++;
            }
            fclose($handle);

            echo json_encode(array(
                'success'     => true,
                'message'     => "Importación completada: $inserted registros nuevos añadidos ($skipped ya existían).",
                'vehiculo_id' => $vehiculoId,
                'insertados'  => $inserted,
                'omitidos'    => $skipped,
            ));
            break;

        case 'import_custom_rows':
            $vehiculoId = (int)arr_get($jsonBody, 'vehiculo_id', isset($_POST['vehiculo_id']) ? $_POST['vehiculo_id'] : 0);
            $rows       = arr_get($jsonBody, 'rows', array());

            if ($vehiculoId <= 0) {
                http_response_code(400);
                echo json_encode(array('success' => false, 'error' => 'Debes seleccionar el vehículo destino para la importación.'));
                exit;
            }
            if (!is_array($rows) || empty($rows)) {
                http_response_code(400);
                echo json_encode(array('success' => false, 'error' => 'No se recibieron filas válidas para importar.'));
                exit;
            }

            // Verificar que el vehículo existe y el usuario tiene permiso
            if ($isAdmin) {
                $stmtV = $pdo->prepare("SELECT id, usuario_id FROM `veh_vehiculos` WHERE id = ? AND activo = 1");
                $stmtV->execute(array($vehiculoId));
            } else {
                $stmtV = $pdo->prepare("SELECT id, usuario_id FROM `veh_vehiculos` WHERE id = ? AND usuario_id = ? AND activo = 1");
                $stmtV->execute(array($vehiculoId, $userId));
            }
            $vRow = $stmtV->fetch();
            if (!$vRow) {
                http_response_code(403);
                echo json_encode(array('success' => false, 'error' => 'Vehículo no encontrado o sin permisos.'));
                exit;
            }
            $ownerUserId = (int)$vRow['usuario_id'];

            $stmtExists = $pdo->prepare("
                SELECT id FROM `veh_repostajes`
                WHERE vehiculo_id = ? AND fecha = ? AND ABS(km_odometro - ?) < 0.01 AND ABS(litros - ?) < 0.01 AND activo = 1
                LIMIT 1
            ");
            $stmtIns = $pdo->prepare("
                INSERT INTO `veh_repostajes`
                (vehiculo_id, usuario_id, fecha, litros, precio_litro, importe_total, km_odometro, estacion_servicio, notas, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");

            $inserted = 0;
            $skipped  = 0;
            $invalid  = 0;

            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $rawFecha = trim((string)arr_get($r, 'fecha', ''));
                if ($rawFecha === '') {
                    $invalid++;
                    continue;
                }

                // Normalizar fecha (YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY o serial Excel)
                $fechaNorm = null;
                if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/', $rawFecha, $m)) {
                    $fechaNorm = sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
                } elseif (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $rawFecha, $m)) {
                    $yr = (int)$m[3];
                    if ($yr < 100) $yr += 2000;
                    $fechaNorm = sprintf('%04d-%02d-%02d', $yr, (int)$m[2], (int)$m[1]);
                } elseif (is_numeric($rawFecha) && (float)$rawFecha > 25000 && (float)$rawFecha < 65000) {
                    $unixTimestamp = ((int)$rawFecha - 25569) * 86400;
                    $fechaNorm = gmdate('Y-m-d', $unixTimestamp);
                }

                if (!$fechaNorm) {
                    $invalid++;
                    continue;
                }

                $cleanNum = function($val) {
                    if ($val === null || $val === '') return 0.0;
                    $s = preg_replace('/[^0-9,\.\-]/', '', (string)$val);
                    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
                        if (strrpos($s, ',') > strrpos($s, '.')) {
                            $s = str_replace('.', '', $s);
                            $s = str_replace(',', '.', $s);
                        } else {
                            $s = str_replace(',', '', $s);
                        }
                    } else {
                        $s = str_replace(',', '.', $s);
                    }
                    return (float)$s;
                };

                $litros  = round($cleanNum(arr_get($r, 'litros', 0)), 3);
                $precio  = round($cleanNum(arr_get($r, 'precio_litro', 0)), 4);
                $importe = round($cleanNum(arr_get($r, 'importe_total', 0)), 2);
                $km      = round($cleanNum(arr_get($r, 'km_odometro', 0)), 2);
                $estacion = trim((string)arr_get($r, 'estacion_servicio', ''));
                $notas    = trim((string)arr_get($r, 'notas', ''));

                if ($importe <= 0 && $litros > 0 && $precio > 0) {
                    $importe = round($litros * $precio, 2);
                } elseif ($litros <= 0 && $importe > 0 && $precio > 0) {
                    $litros = round($importe / $precio, 3);
                } elseif ($precio <= 0 && $importe > 0 && $litros > 0) {
                    $precio = round($importe / $litros, 4);
                }

                if ($litros <= 0 && $importe <= 0) {
                    $invalid++;
                    continue;
                }

                $stmtExists->execute(array($vehiculoId, $fechaNorm, $km, $litros));
                if ($stmtExists->fetch()) {
                    $skipped++;
                    continue;
                }

                $stmtIns->execute(array(
                    $vehiculoId,
                    $ownerUserId,
                    $fechaNorm,
                    $litros,
                    $precio,
                    $importe,
                    $km,
                    $estacion !== '' ? $estacion : null,
                    $notas !== '' ? $notas : null
                ));
                $inserted++;
            }

            echo json_encode(array(
                'success'     => true,
                'message'     => "Importación finalizada: $inserted registros nuevos añadidos, $skipped duplicados omitidos" . ($invalid > 0 ? ", $invalid filas ignoradas." : "."),
                'vehiculo_id' => $vehiculoId,
                'insertados'  => $inserted,
                'omitidos'    => $skipped,
                'ignorados'   => $invalid,
            ));
            break;

        case 'list_tareas_maestro':
            $rows = $pdo->query("
                SELECT id, usuario_id, nombre, descripcion, tipo_periodicidad, periodo_dias, periodo_km, avisar_por_tiempo_si_no_km
                FROM `veh_tareas_maestro`
                WHERE activo = 1
                ORDER BY nombre ASC
            ")->fetchAll();
            echo json_encode(array('success' => true, 'tareas_maestro' => $rows));
            break;

        case 'save_tarea_maestro':
            $id          = (int)arr_get($jsonBody, 'id', isset($_POST['id']) ? $_POST['id'] : 0);
            $nombre      = trim((string)arr_get($jsonBody, 'nombre', isset($_POST['nombre']) ? $_POST['nombre'] : ''));
            $descripcion = trim((string)arr_get($jsonBody, 'descripcion', isset($_POST['descripcion']) ? $_POST['descripcion'] : ''));
            $tipo        = trim((string)arr_get($jsonBody, 'tipo_periodicidad', isset($_POST['tipo_periodicidad']) ? $_POST['tipo_periodicidad'] : 'ambos'));
            if (!in_array($tipo, array('tiempo', 'kilometros', 'ambos'), true)) {
                $tipo = 'ambos';
            }
            $rawDias = arr_get($jsonBody, 'periodo_dias', isset($_POST['periodo_dias']) ? $_POST['periodo_dias'] : '');
            $rawKm   = arr_get($jsonBody, 'periodo_km', isset($_POST['periodo_km']) ? $_POST['periodo_km'] : '');
            $periodoDias = ($rawDias !== '' && $rawDias !== null && (int)$rawDias > 0) ? (int)$rawDias : null;
            $periodoKm   = ($rawKm !== '' && $rawKm !== null && (float)str_replace(',', '.', (string)$rawKm) > 0)
                ? round((float)str_replace(',', '.', (string)$rawKm), 2)
                : null;
            $avisarTiempo = (int)arr_get($jsonBody, 'avisar_por_tiempo_si_no_km', isset($_POST['avisar_por_tiempo_si_no_km']) ? $_POST['avisar_por_tiempo_si_no_km'] : 1) ? 1 : 0;

            if ($nombre === '') {
                http_response_code(400);
                echo json_encode(array('success' => false, 'error' => 'El nombre de la tarea es obligatorio.'));
                exit;
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE `veh_tareas_maestro`
                    SET nombre = ?, descripcion = ?, tipo_periodicidad = ?, periodo_dias = ?, periodo_km = ?, avisar_por_tiempo_si_no_km = ?
                    WHERE id = ? AND activo = 1
                ");
                $stmt->execute(array($nombre, $descripcion !== '' ? $descripcion : null, $tipo, $periodoDias, $periodoKm, $avisarTiempo, $id));
                $savedId = $id;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO `veh_tareas_maestro`
                    (usuario_id, nombre, descripcion, tipo_periodicidad, periodo_dias, periodo_km, avisar_por_tiempo_si_no_km, activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute(array($userId, $nombre, $descripcion !== '' ? $descripcion : null, $tipo, $periodoDias, $periodoKm, $avisarTiempo));
                $savedId = (int)$pdo->lastInsertId();
            }

            echo json_encode(array(
                'success' => true,
                'message' => $id > 0 ? 'Tarea maestra actualizada.' : 'Tarea maestra creada.',
                'id'      => $savedId,
            ));
            break;

        case 'deactivate_tarea_maestro':
            $id = (int)arr_get($jsonBody, 'id', isset($_POST['id']) ? $_POST['id'] : 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(array('success' => false, 'error' => 'ID inválido.'));
                exit;
            }
            $stmt = $pdo->prepare("UPDATE `veh_tareas_maestro` SET activo = 0 WHERE id = ?");
            $stmt->execute(array($id));
            echo json_encode(array('success' => true, 'message' => 'Tarea maestra dada de baja.'));
            break;

        case 'list_vehiculo_tareas':
            $vehiculoId   = (int)arr_get($jsonBody, 'vehiculo_id', isset($_GET['vehiculo_id']) ? $_GET['vehiculo_id'] : 0);
            $estadoFilter = trim((string)arr_get($jsonBody, 'estado', isset($_GET['estado']) ? $_GET['estado'] : 'todas'));
            $list = getVehiculoTareasEnriched($pdo, $userId, $isAdmin, $vehiculoId, $estadoFilter);
            echo json_encode(array('success' => true, 'vehiculo_tareas' => $list));
            break;

        case 'save_vehiculo_tarea':
            $id             = (int)arr_get($jsonBody, 'id', isset($_POST['id']) ? $_POST['id'] : 0);
            $vehiculoId     = (int)arr_get($jsonBody, 'vehiculo_id', isset($_POST['vehiculo_id']) ? $_POST['vehiculo_id'] : 0);
            $tareaMaestroId = (int)arr_get($jsonBody, 'tarea_maestro_id', isset($_POST['tarea_maestro_id']) ? $_POST['tarea_maestro_id'] : 0);
            $descripcion    = trim((string)arr_get($jsonBody, 'descripcion', isset($_POST['descripcion']) ? $_POST['descripcion'] : ''));
            $tipo           = trim((string)arr_get($jsonBody, 'tipo_periodicidad', isset($_POST['tipo_periodicidad']) ? $_POST['tipo_periodicidad'] : 'ambos'));
            if (!in_array($tipo, array('tiempo', 'kilometros', 'ambos'), true)) {
                $tipo = 'ambos';
            }
            $rawDias = arr_get($jsonBody, 'periodo_dias', isset($_POST['periodo_dias']) ? $_POST['periodo_dias'] : '');
            $rawKm   = arr_get($jsonBody, 'periodo_km', isset($_POST['periodo_km']) ? $_POST['periodo_km'] : '');
            $periodoDias = ($rawDias !== '' && $rawDias !== null && (int)$rawDias > 0) ? (int)$rawDias : null;
            $periodoKm   = ($rawKm !== '' && $rawKm !== null && (float)str_replace(',', '.', (string)$rawKm) > 0)
                ? round((float)str_replace(',', '.', (string)$rawKm), 2)
                : null;
            $avisarTiempo = (int)arr_get($jsonBody, 'avisar_por_tiempo_si_no_km', isset($_POST['avisar_por_tiempo_si_no_km']) ? $_POST['avisar_por_tiempo_si_no_km'] : 1) ? 1 : 0;
            $ultimaFecha  = trim((string)arr_get($jsonBody, 'ultima_fecha', isset($_POST['ultima_fecha']) ? $_POST['ultima_fecha'] : date('Y-m-d')));
            $rawUltKm     = arr_get($jsonBody, 'ultimo_km', isset($_POST['ultimo_km']) ? $_POST['ultimo_km'] : '');
            $ultimoKm     = ($rawUltKm !== '' && $rawUltKm !== null) ? round((float)str_replace(',', '.', (string)$rawUltKm), 2) : null;
            $notas        = trim((string)arr_get($jsonBody, 'notas', isset($_POST['notas']) ? $_POST['notas'] : ''));

            if ($vehiculoId <= 0 || $descripcion === '') {
                http_response_code(400);
                echo json_encode(array('success' => false, 'error' => 'Vehículo y descripción de la tarea son obligatorios.'));
                exit;
            }

            if ($isAdmin) {
                $stmtV = $pdo->prepare("SELECT id, usuario_id FROM `veh_vehiculos` WHERE id = ? AND activo = 1");
                $stmtV->execute(array($vehiculoId));
            } else {
                $stmtV = $pdo->prepare("SELECT id, usuario_id FROM `veh_vehiculos` WHERE id = ? AND usuario_id = ? AND activo = 1");
                $stmtV->execute(array($vehiculoId, $userId));
            }
            $vehRow = $stmtV->fetch();
            if (!$vehRow) {
                http_response_code(403);
                echo json_encode(array('success' => false, 'error' => 'Vehículo no válido o sin permisos.'));
                exit;
            }

            list($proximaFecha, $proximoKm) = computeNextTaskTargets($tipo, $ultimaFecha, $ultimoKm, $periodoDias, $periodoKm);
            $ownerUserId = (int)$vehRow['usuario_id'];

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE `veh_vehiculo_tareas`
                    SET vehiculo_id = ?, tarea_maestro_id = ?, descripcion = ?, tipo_periodicidad = ?, periodo_dias = ?, periodo_km = ?,
                        avisar_por_tiempo_si_no_km = ?, ultima_fecha = ?, ultimo_km = ?, proxima_fecha = ?, proximo_km = ?, notas = ?, aviso_enviado = 0
                    WHERE id = ? AND activo = 1
                ");
                $stmt->execute(array(
                    $vehiculoId, $tareaMaestroId > 0 ? $tareaMaestroId : null, $descripcion, $tipo, $periodoDias, $periodoKm,
                    $avisarTiempo, $ultimaFecha !== '' ? $ultimaFecha : null, $ultimoKm, $proximaFecha, $proximoKm,
                    $notas !== '' ? $notas : null, $id
                ));
                $savedId = $id;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO `veh_vehiculo_tareas`
                    (vehiculo_id, tarea_maestro_id, usuario_id, descripcion, tipo_periodicidad, periodo_dias, periodo_km,
                     avisar_por_tiempo_si_no_km, ultima_fecha, ultimo_km, proxima_fecha, proximo_km, estado, notas, activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, 1)
                ");
                $stmt->execute(array(
                    $vehiculoId, $tareaMaestroId > 0 ? $tareaMaestroId : null, $ownerUserId, $descripcion, $tipo, $periodoDias, $periodoKm,
                    $avisarTiempo, $ultimaFecha !== '' ? $ultimaFecha : null, $ultimoKm, $proximaFecha, $proximoKm,
                    $notas !== '' ? $notas : null
                ));
                $savedId = (int)$pdo->lastInsertId();
            }

            echo json_encode(array(
                'success' => true,
                'message' => $id > 0 ? 'Tarea de vehículo actualizada.' : 'Tarea asignada al vehículo.',
                'id'      => $savedId,
            ));
            break;

        case 'complete_vehiculo_tarea':
            $id           = (int)arr_get($jsonBody, 'id', isset($_POST['id']) ? $_POST['id'] : 0);
            $fechaFin     = trim((string)arr_get($jsonBody, 'fecha_finalizacion', isset($_POST['fecha_finalizacion']) ? $_POST['fecha_finalizacion'] : date('Y-m-d')));
            $rawKmFin     = arr_get($jsonBody, 'km_finalizacion', isset($_POST['km_finalizacion']) ? $_POST['km_finalizacion'] : '');
            $kmFin        = ($rawKmFin !== '' && $rawKmFin !== null) ? round((float)str_replace(',', '.', (string)$rawKmFin), 2) : null;
            $notasFin     = trim((string)arr_get($jsonBody, 'notas', isset($_POST['notas']) ? $_POST['notas'] : ''));
            $crearSig     = (int)arr_get($jsonBody, 'crear_siguiente', isset($_POST['crear_siguiente']) ? $_POST['crear_siguiente'] : 1);

            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(array('success' => false, 'error' => 'ID de tarea inválido.'));
                exit;
            }

            $stmtGet = $pdo->prepare("
                SELECT vt.*, v.usuario_id AS veh_owner
                FROM `veh_vehiculo_tareas` vt
                INNER JOIN `veh_vehiculos` v ON v.id = vt.vehiculo_id
                WHERE vt.id = ? AND vt.activo = 1
            ");
            $stmtGet->execute(array($id));
            $task = $stmtGet->fetch();
            if (!$task || (!$isAdmin && (int)$task['veh_owner'] !== $userId)) {
                http_response_code(403);
                echo json_encode(array('success' => false, 'error' => 'Tarea no encontrada o sin permisos.'));
                exit;
            }

            $stmtUpd = $pdo->prepare("
                UPDATE `veh_vehiculo_tareas`
                SET estado = 'finalizada', fecha_finalizacion = ?, km_finalizacion = ?,
                    notas = COALESCE(NULLIF(?, ''), notas)
                WHERE id = ?
            ");
            $stmtUpd->execute(array($fechaFin, $kmFin, $notasFin, $id));

            $nextCreated = false;
            if ($crearSig && (($task['periodo_dias'] !== null && (int)$task['periodo_dias'] > 0) || ($task['periodo_km'] !== null && (float)$task['periodo_km'] > 0))) {
                list($nextFecha, $nextKm) = computeNextTaskTargets(
                    $task['tipo_periodicidad'],
                    $fechaFin,
                    $kmFin,
                    $task['periodo_dias'],
                    $task['periodo_km']
                );
                $stmtIns = $pdo->prepare("
                    INSERT INTO `veh_vehiculo_tareas`
                    (vehiculo_id, tarea_maestro_id, usuario_id, descripcion, tipo_periodicidad, periodo_dias, periodo_km,
                     avisar_por_tiempo_si_no_km, ultima_fecha, ultimo_km, proxima_fecha, proximo_km, estado, notas, activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, 1)
                ");
                $stmtIns->execute(array(
                    (int)$task['vehiculo_id'],
                    $task['tarea_maestro_id'] ? (int)$task['tarea_maestro_id'] : null,
                    (int)$task['usuario_id'],
                    $task['descripcion'],
                    $task['tipo_periodicidad'],
                    $task['periodo_dias'],
                    $task['periodo_km'],
                    (int)$task['avisar_por_tiempo_si_no_km'],
                    $fechaFin,
                    $kmFin,
                    $nextFecha,
                    $nextKm,
                    $task['notas']
                ));
                $nextCreated = true;
            }

            echo json_encode(array(
                'success'      => true,
                'message'      => $nextCreated ? 'Tarea finalizada y programada la siguiente repetición.' : 'Tarea marcada como finalizada.',
                'next_created' => $nextCreated,
            ));
            break;

        case 'deactivate_vehiculo_tarea':
            $id = (int)arr_get($jsonBody, 'id', isset($_POST['id']) ? $_POST['id'] : 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(array('success' => false, 'error' => 'ID inválido.'));
                exit;
            }
            if ($isAdmin) {
                $stmt = $pdo->prepare("UPDATE `veh_vehiculo_tareas` SET activo = 0 WHERE id = ?");
                $stmt->execute(array($id));
            } else {
                $stmt = $pdo->prepare("
                    UPDATE `veh_vehiculo_tareas` vt
                    INNER JOIN `veh_vehiculos` v ON v.id = vt.vehiculo_id
                    SET vt.activo = 0
                    WHERE vt.id = ? AND v.usuario_id = ?
                ");
                $stmt->execute(array($id, $userId));
            }
            echo json_encode(array('success' => true, 'message' => 'Tarea dada de baja.'));
            break;

        case 'send_task_alerts_email':
            $vehiculoId = (int)arr_get($jsonBody, 'vehiculo_id', isset($_POST['vehiculo_id']) ? $_POST['vehiculo_id'] : 0);
            $alertTasks = getVehiculoTareasEnriched($pdo, $userId, $isAdmin, $vehiculoId, 'proximas');
            if (empty($alertTasks)) {
                echo json_encode(array(
                    'success'       => true,
                    'email_sent'    => false,
                    'alertas_count' => 0,
                    'message'       => 'No hay tareas pendientes a menos de 1.000 km o 15 días.',
                ));
                break;
            }
            $mailErr = null;
            $sent = FuelMailer::sendTaskAlertsEmail($pdo, $user, $alertTasks, $mailErr);
            if ($sent) {
                $stmtMark = $pdo->prepare("UPDATE `veh_vehiculo_tareas` SET aviso_enviado = 1 WHERE id = ?");
                foreach ($alertTasks as $at) {
                    $stmtMark->execute(array((int)$at['id']));
                }
            }
            echo json_encode(array(
                'success'       => $sent,
                'email_sent'    => $sent,
                'alertas_count' => count($alertTasks),
                'message'       => $sent
                    ? ('Correo enviado con ' . count($alertTasks) . ' aviso(s) de mantenimiento.')
                    : ('No se pudo enviar el correo: ' . ($mailErr ? $mailErr : 'Error desconocido')),
                'error'         => $sent ? null : $mailErr,
            ));
            break;

        case 'update_user_email':
            $newEmail = trim((string)arr_get($jsonBody, 'email', isset($_POST['email']) ? $_POST['email'] : ''));
            if ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(array('success' => false, 'error' => 'El correo electrónico no es válido.'));
                exit;
            }
            $stmtUpdEmail = $pdo->prepare("UPDATE `users` SET email = ? WHERE id = ?");
            $stmtUpdEmail->execute(array($newEmail !== '' ? $newEmail : null, $userId));
            echo json_encode(array(
                'success' => true,
                'email'   => $newEmail,
                'message' => 'Correo electrónico actualizado correctamente.',
            ));
            break;

        case 'send_report_email':
            $reportTitle = trim((string)arr_get($jsonBody, 'title', isset($_POST['title']) ? $_POST['title'] : 'Informe Control de Combustible'));
            $summaryText = trim((string)arr_get($jsonBody, 'summary', isset($_POST['summary']) ? $_POST['summary'] : ''));
            $filename    = trim((string)arr_get($jsonBody, 'filename', isset($_POST['filename']) ? $_POST['filename'] : 'Informe.pdf'));
            $pdfBase64   = (string)arr_get($jsonBody, 'pdf_base64', isset($_POST['pdf_base64']) ? $_POST['pdf_base64'] : '');

            $mailErr = null;
            $sent = FuelMailer::sendReportPdfEmail($pdo, $user, $reportTitle, $summaryText, $pdfBase64, $filename, $mailErr);
            echo json_encode(array(
                'success' => $sent,
                'message' => $sent ? 'Informe PDF enviado correctamente por correo electrónico.' : ('No se pudo enviar el informe: ' . ($mailErr ? $mailErr : 'Error desconocido')),
                'error'   => $sent ? null : $mailErr,
            ));
            break;

        default:
            http_response_code(400);
            echo json_encode(array(
                'success' => false,
                'error'   => "Acción no reconocida: $action"
            ));
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error'   => 'Error interno en el servidor: ' . $e->getMessage()
    ));
}
