<?php
/**
 * config/database.php
 * Conexión compartida con la base de datos de Moratalla-Murcia-2026.
 * Compatible con PHP 5.6, 7.x y 8.x tanto en Local (XAMPP) como en Producción (IONOS).
 *
 * POLÍTICA DE PROTECCIÓN DE DATOS:
 * - Prohibido ejecutar DELETE, DROP, TRUNCATE o alterar tablas existentes de moratalla-murcia.com.
 * - La tabla `users` de Moratalla-Murcia-2026 se consulta en modo ESTRICTAMENTE SOLO LECTURA para autenticación.
 * - Las tablas nuevas (`veh_vehiculos`, `veh_repostajes`) se crean de forma aditiva (CREATE TABLE IF NOT EXISTS)
 *   y utilizan borrado lógico (`activo = 0`).
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
    veh_load_env(dirname(dirname(__DIR__)) . '/.env');
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
 * y asegura de forma estrictamente aditiva las tablas `veh_vehiculos` y `veh_repostajes`.
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

        // Asegurar creación aditiva de las tablas de vehículos sin tocar ninguna otra tabla
        ensureAdditiveSchema($pdo);
    }
    return $pdo;
}

/**
 * Crea únicamente las tablas nuevas si no existen (CREATE TABLE IF NOT EXISTS).
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
}
