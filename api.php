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
        http_response_code(401);
        echo json_encode(array(
            'success' => false,
            'error'   => 'Credenciales requeridas (usuario de moratalla-murcia.com).'
        ));
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, password, role FROM `users` WHERE username = ? LIMIT 1");
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

    return array(
        'id'           => (int)$user['id'],
        'username'     => $user['username'],
        'role'         => $role,
        'is_admin'     => ($role === 'admin'),
    );
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

            echo json_encode(array(
                'success'               => true,
                'user'                  => $user,
                'vehiculos'             => $vehiculos,
                'vehiculo_seleccionado' => $vehiculoId,
                'resumen'               => $resumen,
                'resumen_por_vehiculo'  => $resumenPorVehiculo,
                'repostajes'            => $repostajesCalculados,
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
                $stmtCheck = $pdo->prepare("SELECT id, usuario_id FROM `veh_vehiculos` WHERE id = ? AND activo = 1");
                $stmtCheck->execute(array($vehiculoId));
            } else {
                $stmtCheck = $pdo->prepare("SELECT id, usuario_id FROM `veh_vehiculos` WHERE id = ? AND usuario_id = ? AND activo = 1");
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
                        SET vehiculo_id = ?, fecha = ?, litros = ?, precio_litro = ?, importe_total = ?, km_odometro = ?, estacion_servicio = ?, notas = ?
                        WHERE id = ? AND activo = 1
                    ");
                    $stmt->execute(array($vehiculoId, $fecha, $litros, $precioLitro, $importeTotal, $kmOdometro, $estacionServicio, $notas, $id));
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

            echo json_encode(array(
                'success'      => true,
                'message'      => $id > 0 ? 'Repostaje actualizado correctamente.' : 'Repostaje registrado correctamente.',
                'repostaje_id' => $savedId,
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
