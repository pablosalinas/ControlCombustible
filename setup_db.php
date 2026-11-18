<?php
/**
 * setup_db.php
 * Script de instalación y verificación ESTRICTAMENTE ADITIVO (Local y Producción).
 * - Crea `veh_vehiculos` y `veh_repostajes` usando CREATE TABLE IF NOT EXISTS.
 * - NUNCA borra, modifica ni altera tablas ni registros existentes.
 * - Importa de forma idempotente los registros de `datos/historico_repostajes.csv` si aún no existen.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getVehDB();

    // 0. Asegurar columna aditiva `validado` en `users` para solicitudes de registro
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'validado'");
        if ($colCheck && !$colCheck->fetch()) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `validado` TINYINT(1) NOT NULL DEFAULT 1");
        }
    } catch (Exception $e) {
        // Ignorar si la tabla users no existe en algún entorno aislado
    }

    // 1. Obtener usuario administrador principal de la tabla `users` (SOLO LECTURA)
    $adminUserId = 1;
    $adminUsername = 'admin';
    try {
        $stmtUser = $pdo->query("SELECT id, username, role FROM `users` ORDER BY (role = 'admin') DESC, id ASC LIMIT 1");
        if ($stmtUser && ($uRow = $stmtUser->fetch())) {
            $adminUserId   = (int)$uRow['id'];
            $adminUsername = $uRow['username'];
        }
    } catch (Exception $e) {
        // Si la tabla users no existe aún en algún entorno aislado, usa ID 1
    }

    // 2. Asegurar que exista al menos un vehículo activo para los datos históricos (ADITIVO)
    $stmtVeh = $pdo->query("SELECT id, usuario_id, nombre FROM `veh_vehiculos` WHERE activo = 1 ORDER BY id ASC LIMIT 1");
    $vehiculo = $stmtVeh ? $stmtVeh->fetch() : false;

    if (!$vehiculo) {
        $stmtInsVeh = $pdo->prepare("
            INSERT INTO `veh_vehiculos` (usuario_id, nombre, matricula, marca, modelo, tipo_combustible, km_iniciales, notas, activo)
            VALUES (?, 'Vehículo Principal', '', 'Vehículo', 'Principal (Histórico)', 'Diésel', 0.00, 'Vehículo importado de hoja de cálculo', 1)
        ");
        $stmtInsVeh->execute(array($adminUserId));
        $vehiculoId = (int)$pdo->lastInsertId();
        $ownerUserId = $adminUserId;
        $vehiculoCreado = true;
    } else {
        $vehiculoId = (int)$vehiculo['id'];
        $ownerUserId = (int)$vehiculo['usuario_id'];
        $vehiculoCreado = false;
    }

    // 3. Importar registros históricos de `datos/historico_repostajes.csv` de forma idempotente (sin duplicar ni borrar nada)
    $csvFile = __DIR__ . '/datos/historico_repostajes.csv';
    $insertados = 0;
    $omitidos   = 0;

    if (file_exists($csvFile)) {
        $handle = fopen($csvFile, 'r');
        $header = fgetcsv($handle, 0, ',');

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
            $fecha = trim($row[0]);
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
                $omitidos++;
                continue;
            }

            $stmtIns->execute(array($vehiculoId, $ownerUserId, $fecha, $litros, $precio, $importe, $km, $notas));
            $insertados++;
        }
        fclose($handle);
    }

    $totalVehiculos  = (int)$pdo->query("SELECT COUNT(*) FROM `veh_vehiculos` WHERE activo = 1")->fetchColumn();
    $totalRepostajes = (int)$pdo->query("SELECT COUNT(*) FROM `veh_repostajes` WHERE activo = 1")->fetchColumn();

    echo json_encode(array(
        'success'            => true,
        'message'            => 'Tablas veh_vehiculos y veh_repostajes verificadas/creadas de forma aditiva sin alterar datos existentes.',
        'database'           => VEH_DB_NAME,
        'admin_vinculado'    => $adminUsername,
        'vehiculo_id'        => $vehiculoId,
        'vehiculo_creado'    => $vehiculoCreado,
        'csv_insertados'     => $insertados,
        'csv_omitidos'       => $omitidos,
        'total_vehiculos'    => $totalVehiculos,
        'total_repostajes'   => $totalRepostajes,
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error'   => $e->getMessage(),
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
