-- ============================================================================
-- MIGRACIÓN ESTRICTAMENTE ADITIVA - GESTIÓN DE ADITIVOS EN EL COMBUSTIBLE
-- PROHIBIDO: DROP, DELETE, TRUNCATE o alterar tablas existentes.
-- Todas las bajas se realizan mediante borrado lógico (activo = 0).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `veh_aditivos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NOT NULL,
    `nombre` VARCHAR(150) NOT NULL,
    `marca` VARCHAR(100) DEFAULT NULL,
    `componentes` TEXT DEFAULT NULL,
    `descripcion` TEXT DEFAULT NULL,
    `caracteristicas` TEXT DEFAULT NULL,
    `fuente_info` VARCHAR(255) DEFAULT NULL,
    `unidad` VARCHAR(10) NOT NULL DEFAULT 'L' COMMENT 'Unidad SI: L o kg',
    `dosis_por_litro` DECIMAL(12, 6) NOT NULL DEFAULT 0.000000 COMMENT 'Dosis por litro de combustible (L/L o kg/L)',
    `precio_envase` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Precio del envase en EUR',
    `cantidad_envase` DECIMAL(12, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Cantidad del envase en la misma unidad (L o kg)',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_adit_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `veh_repostaje_aditivos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `repostaje_id` INT NOT NULL,
    `aditivo_id` INT NOT NULL,
    `dosis_por_litro` DECIMAL(12, 6) NOT NULL DEFAULT 0.000000,
    `unidad` VARCHAR(10) NOT NULL DEFAULT 'L',
    `cantidad_recomendada` DECIMAL(12, 6) NOT NULL DEFAULT 0.000000,
    `cantidad_real` DECIMAL(12, 6) NOT NULL DEFAULT 0.000000,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ra_repostaje_activo` (`repostaje_id`, `activo`),
    INDEX `idx_ra_aditivo_activo` (`aditivo_id`, `activo`),
    CONSTRAINT `fk_ra_repostaje` FOREIGN KEY (`repostaje_id`) REFERENCES `veh_repostajes`(`id`),
    CONSTRAINT `fk_ra_aditivo` FOREIGN KEY (`aditivo_id`) REFERENCES `veh_aditivos`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
