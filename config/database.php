<?php
/**
 * config/database.php
 * Conexión compartida con la base de datos de Moratalla-Murcia-2026.
 * Compatible con PHP 5.6, 7.x y 8.x tanto en Local (XAMPP) como en Producción (IONOS).
 *
 * POLÍTICA DE PROTECCIÓN DE DATOS:
 * - Prohibido ejecutar DELETE, DROP, TRUNCATE o alterar registros existentes de moratalla-murcia.com.
 * - Las tablas nuevas (`veh_vehiculos`, `veh_repostajes`) y las columnas de permiso en `users`
 *   (`acceso_sistema`, `es_superusuario`) se crean de forma ESTRICTAMENTE ADITIVA.
 * - Todas las bajas utilizan borrado lógico (`activo = 0`).
 */

if (!function_exists('veh_load_env')) {
    function veh_load_env($file) {
        if (!file_exists($file)) {
            return false;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if (preg_match('/^"(.*)"$/', $value, $m) || preg_match('/^\'(.*)\'$/', $value, $m)) {
                    $value = $m[1];
                }
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
                putenv("$name=$value");
            }
        }
        return true;
    }
}

// 1. Intentar cargar .env propio del proyecto
$localEnvLoaded = veh_load_env(dirname(__DIR__) . '/.env');
// 2. Si no existe .env propio y estamos dentro de la estructura de Moratalla-Murcia-2026, cargar su .env
if (!$localEnvLoaded) {
    $localEnvLoaded = veh_load_env(dirname(dirname(__DIR__)) . '/.env');
}
if (!$localEnvLoaded) {
    veh_load_env(dirname(dirname(__DIR__)) . '/moratalla/.env');
}

if (!function_exists('veh_env')) {
    function veh_env($key, $default = null) {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
        $val = getenv($key);
        return ($val !== false && $val !== null && $val !== '') ? $val : $default;
    }
}

$currentHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$isProdHost = (strpos($currentHost, 'moratalla-murcia.com') !== false);
$environment = veh_env('APP_ENV', $isProdHost ? 'production' : 'local');

if ($environment === 'production') {
    define('VEH_DB_HOST', veh_env('DB_HOST'));
    define('VEH_DB_PORT', veh_env('DB_PORT', '3306'));
    define('VEH_DB_USER', veh_env('DB_USER'));
    define('VEH_DB_PASS', veh_env('DB_PASS'));
    define('VEH_DB_NAME', veh_env('DB_NAME'));
} else {
    // Valores de XAMPP local idénticos a Moratalla-Murcia-2026/config.php
    define('VEH_DB_HOST', veh_env('DB_HOST', 'localhost'));
    define('VEH_DB_PORT', veh_env('DB_PORT', '3306'));
    define('VEH_DB_USER', veh_env('DB_USER', 'root'));
    define('VEH_DB_PASS', veh_env('DB_PASS', ''));
    define('VEH_DB_NAME', veh_env('DB_NAME', 'moratalla_web_2026'));
}

/**
 * Obtiene la instancia PDO conectada a la BD de Moratalla-Murcia-2026
 * y asegura de forma estrictamente aditiva las tablas `veh_vehiculos`, `veh_repostajes`
 * y las columnas de control de acceso en `users`.
 */
function getVehDB() {
    static $pdo = null;
    if ($pdo === null) {
        $host = VEH_DB_HOST;
        $port = VEH_DB_PORT;
        $user = VEH_DB_USER;
        $pass = VEH_DB_PASS;
        $dbname = VEH_DB_NAME;

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'",
        ));

        ensureAdditiveSchema($pdo);
    }
    return $pdo;
}

/**
 * Crea de forma estrictamente aditiva las tablas `veh_vehiculos`, `veh_repostajes`
 * y añade las columnas `acceso_sistema` y `es_superusuario` a `users` si aún no existen.
 */
function ensureAdditiveSchema(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `veh_vehiculos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `usuario_id` INT NOT NULL,
            `nombre` VARCHAR(120) NOT NULL,
            `matricula` VARCHAR(30) DEFAULT NULL,
            `marca` VARCHAR(80) DEFAULT NULL,
            `modelo` VARCHAR(80) DEFAULT NULL,
            `tipo_combustible` VARCHAR(40) DEFAULT 'Diésel',
            `km_iniciales` DECIMAL(12, 2) DEFAULT 0.00,
            `notas` TEXT DEFAULT NULL,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_veh_usuario_activo` (`usuario_id`, `activo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `veh_repostajes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `vehiculo_id` INT NOT NULL,
            `usuario_id` INT NOT NULL,
            `fecha` DATE NOT NULL,
            `litros` DECIMAL(10, 3) NOT NULL DEFAULT 0.000,
            `precio_litro` DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
            `importe_total` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            `km_odometro` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
            `estacion_servicio` VARCHAR(150) DEFAULT NULL,
            `notas` VARCHAR(255) DEFAULT NULL,
            `foto_ticket_path` VARCHAR(255) DEFAULT NULL,
            `foto_odometro_path` VARCHAR(255) DEFAULT NULL,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_rep_vehiculo_fecha` (`vehiculo_id`, `activo`, `fecha`, `km_odometro`),
            INDEX `idx_rep_usuario_activo` (`usuario_id`, `activo`),
            CONSTRAINT `fk_rep_vehiculo` FOREIGN KEY (`vehiculo_id`) REFERENCES `veh_vehiculos`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `veh_estaciones` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `usuario_id` INT NOT NULL,
            `nombre` VARCHAR(150) NOT NULL,
            `marca` VARCHAR(80) DEFAULT NULL,
            `localidad` VARCHAR(120) DEFAULT NULL,
            `direccion` VARCHAR(200) DEFAULT NULL,
            `notas` VARCHAR(255) DEFAULT NULL,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_est_usuario_activo` (`usuario_id`, `activo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Añadir de forma estrictamente aditiva las columnas de permiso en `users` si no existen
    try {
        $colAcceso = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'acceso_sistema'")->fetch();
        if (!$colAcceso) {
            // Por defecto 'ambos' para no bloquear a los administradores actuales
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `acceso_sistema` VARCHAR(30) NOT NULL DEFAULT 'ambos'");
        }

        $colSuper = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'es_superusuario'")->fetch();
        if (!$colSuper) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `es_superusuario` TINYINT(1) NOT NULL DEFAULT 0");
            // Activar marca de superusuario en las cuentas administradoras iniciales existentes
            $pdo->exec("UPDATE `users` SET `es_superusuario` = 1 WHERE `role` = 'admin'");
        }

        $colEmail = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'email'")->fetch();
        if (!$colEmail) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `email` VARCHAR(190) DEFAULT NULL");
        }
    } catch (Exception $e) {
        // Ignorar si en algún entorno de prueba aún no existe la tabla users
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `veh_tareas_maestro` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `usuario_id` INT NOT NULL,
            `nombre` VARCHAR(150) NOT NULL,
            `descripcion` TEXT NULL,
            `tipo_periodicidad` ENUM('tiempo','kilometros','ambos') NOT NULL DEFAULT 'ambos',
            `periodo_dias` INT NULL DEFAULT NULL,
            `periodo_km` DECIMAL(12, 2) NULL DEFAULT NULL,
            `avisar_por_tiempo_si_no_km` TINYINT(1) NOT NULL DEFAULT 1,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_tm_activo` (`activo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `veh_vehiculo_tareas` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `vehiculo_id` INT NOT NULL,
            `tarea_maestro_id` INT NULL DEFAULT NULL,
            `usuario_id` INT NOT NULL,
            `descripcion` VARCHAR(200) NOT NULL,
            `tipo_periodicidad` ENUM('tiempo','kilometros','ambos') NOT NULL DEFAULT 'ambos',
            `periodo_dias` INT NULL DEFAULT NULL,
            `periodo_km` DECIMAL(12, 2) NULL DEFAULT NULL,
            `avisar_por_tiempo_si_no_km` TINYINT(1) NOT NULL DEFAULT 1,
            `ultima_fecha` DATE NULL DEFAULT NULL,
            `ultimo_km` DECIMAL(12, 2) NULL DEFAULT NULL,
            `proxima_fecha` DATE NULL DEFAULT NULL,
            `proximo_km` DECIMAL(12, 2) NULL DEFAULT NULL,
            `estado` ENUM('pendiente','finalizada') NOT NULL DEFAULT 'pendiente',
            `fecha_finalizacion` DATE NULL DEFAULT NULL,
            `km_finalizacion` DECIMAL(12, 2) NULL DEFAULT NULL,
            `aviso_enviado` TINYINT(1) NOT NULL DEFAULT 0,
            `notas` TEXT NULL,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_vt_vehiculo` (`vehiculo_id`, `estado`, `activo`),
            CONSTRAINT `fk_vt_vehiculo` FOREIGN KEY (`vehiculo_id`) REFERENCES `veh_vehiculos`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `veh_proveedores` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `usuario_id` INT NOT NULL,
            `tipo` ENUM('taller','aseguradora','itv') NOT NULL DEFAULT 'taller',
            `nombre` VARCHAR(150) NOT NULL,
            `telefono` VARCHAR(40) DEFAULT NULL,
            `localidad` VARCHAR(120) DEFAULT NULL,
            `direccion` VARCHAR(200) DEFAULT NULL,
            `notas` VARCHAR(255) DEFAULT NULL,
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_prov_tipo_activo` (`tipo`, `activo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Columnas aditivas en veh_vehiculo_tareas para proveedor (taller/aseguradora/itv), coste, garantía y tipo 'unica'
    try {
        $colProv = $pdo->query("SHOW COLUMNS FROM `veh_vehiculo_tareas` LIKE 'proveedor_id'")->fetch();
        if (!$colProv) {
            $pdo->exec("ALTER TABLE `veh_vehiculo_tareas` ADD COLUMN `proveedor_id` INT NULL DEFAULT NULL AFTER `tarea_maestro_id`");
        }
        $colCoste = $pdo->query("SHOW COLUMNS FROM `veh_vehiculo_tareas` LIKE 'coste'")->fetch();
        if (!$colCoste) {
            $pdo->exec("ALTER TABLE `veh_vehiculo_tareas` ADD COLUMN `coste` DECIMAL(10, 2) NULL DEFAULT NULL AFTER `notas`");
        }
        $colGarantia = $pdo->query("SHOW COLUMNS FROM `veh_vehiculo_tareas` LIKE 'garantia_meses'")->fetch();
        if (!$colGarantia) {
            $pdo->exec("ALTER TABLE `veh_vehiculo_tareas` ADD COLUMN `garantia_meses` INT NULL DEFAULT NULL AFTER `coste`");
        }
        $colTipoVt = $pdo->query("SHOW COLUMNS FROM `veh_vehiculo_tareas` LIKE 'tipo_periodicidad'")->fetch();
        if ($colTipoVt && isset($colTipoVt['Type']) && strpos($colTipoVt['Type'], "'unica'") === false) {
            $pdo->exec("ALTER TABLE `veh_vehiculo_tareas` MODIFY COLUMN `tipo_periodicidad` ENUM('tiempo','kilometros','ambos','unica') NOT NULL DEFAULT 'ambos'");
        }
        $colTipoTm = $pdo->query("SHOW COLUMNS FROM `veh_tareas_maestro` LIKE 'tipo_periodicidad'")->fetch();
        if ($colTipoTm && isset($colTipoTm['Type']) && strpos($colTipoTm['Type'], "'unica'") === false) {
            $pdo->exec("ALTER TABLE `veh_tareas_maestro` MODIFY COLUMN `tipo_periodicidad` ENUM('tiempo','kilometros','ambos','unica') NOT NULL DEFAULT 'ambos'");
        }
    } catch (Exception $e) {}

    // Semilla inicial aditiva de tareas comunes si el maestro está vacío
    try {
        $cntTm = (int)$pdo->query("SELECT COUNT(*) FROM `veh_tareas_maestro`")->fetchColumn();
        if ($cntTm === 0) {
            $seedTasks = array(
                array('Cambio de aceite', 'Sustitución de aceite motor y filtro de aceite', 'ambos', 365, 15000, 1),
                array('Cambio filtro de aire', 'Revisión o sustitución del filtro de aire del motor', 'ambos', 365, 20000, 1),
                array('Cambio de ruedas', 'Revisión de desgaste, rotación o sustitución de neumáticos', 'kilometros', null, 40000, 1),
                array('Revisión ITV', 'Inspección Técnica de Vehículos periódica', 'tiempo', 365, null, 1),
                array('Seguro del vehículo', 'Renovación de póliza de seguro obligatorio', 'tiempo', 365, null, 1),
            );
            $stmtSeed = $pdo->prepare("
                INSERT INTO `veh_tareas_maestro`
                (usuario_id, nombre, descripcion, tipo_periodicidad, periodo_dias, periodo_km, avisar_por_tiempo_si_no_km, activo)
                VALUES (1, ?, ?, ?, ?, ?, ?, 1)
            ");
            foreach ($seedTasks as $st) {
                $stmtSeed->execute($st);
            }
        }
    } catch (Exception $e) {}
}
