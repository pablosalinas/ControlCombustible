-- ============================================================================
-- MIGRACIÓN ESTRICTAMENTE ADITIVA - CONTROL DE COMBUSTIBLE Y GASTOS DE VEHÍCULOS
-- Base de datos compartida con moratalla-murcia.com (Moratalla-Murcia-2026)
-- PROHIBIDO: DROP, DELETE, TRUNCATE o alterar tablas existentes.
-- Todas las bajas se realizan mediante borrado lógico (activo = 0).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `veh_vehiculos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NOT NULL COMMENT 'ID del usuario en la tabla users de moratalla-murcia.com',
    `nombre` VARCHAR(120) NOT NULL COMMENT 'Alias o nombre identificativo del vehículo',
    `matricula` VARCHAR(30) DEFAULT NULL,
    `marca` VARCHAR(80) DEFAULT NULL,
    `modelo` VARCHAR(80) DEFAULT NULL,
    `tipo_combustible` VARCHAR(40) DEFAULT 'Diésel' COMMENT 'Diésel, Gasolina 95, Gasolina 98, GLP, Híbrido, Eléctrico',
    `km_iniciales` DECIMAL(12, 2) DEFAULT 0.00,
    `notas` TEXT DEFAULT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Activo, 0 = Baja lógica (Soft Delete)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_veh_usuario_activo` (`usuario_id`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `veh_repostajes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `vehiculo_id` INT NOT NULL,
    `usuario_id` INT NOT NULL COMMENT 'Redundancia de seguridad para consultas consolidadas por usuario',
    `fecha` DATE NOT NULL,
    `litros` DECIMAL(10, 3) NOT NULL DEFAULT 0.000,
    `precio_litro` DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
    `importe_total` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `km_odometro` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `estacion_servicio` VARCHAR(150) DEFAULT NULL,
    `notas` VARCHAR(255) DEFAULT NULL COMMENT 'Incidencias o mantenimientos asociados (ej. AJUSTE MOTOR EMISIONES NO2)',
    `foto_ticket_path` VARCHAR(255) DEFAULT NULL,
    `foto_odometro_path` VARCHAR(255) DEFAULT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Activo, 0 = Baja lógica (Soft Delete)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_rep_vehiculo_fecha` (`vehiculo_id`, `activo`, `fecha`, `km_odometro`),
    INDEX `idx_rep_usuario_activo` (`usuario_id`, `activo`),
    CONSTRAINT `fk_rep_vehiculo` FOREIGN KEY (`vehiculo_id`) REFERENCES `veh_vehiculos`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `veh_estaciones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NOT NULL,
    `nombre` VARCHAR(150) NOT NULL,
    `marca` VARCHAR(80) DEFAULT NULL,
    `localidad` VARCHAR(120) DEFAULT NULL,
    `direccion` VARCHAR(200) DEFAULT NULL,
    `notas` VARCHAR(255) DEFAULT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Activo, 0 = Baja lógica (Soft Delete)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_est_usuario_activo` (`usuario_id`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
