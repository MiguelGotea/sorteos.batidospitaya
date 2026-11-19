-- =============================================================================
-- TABLAS: sorteos.batidospitaya.com
-- Sorteo: Concierto Martin Garrix 2026
-- Ejecutar manualmente en la base de datos de producción
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Tabla 1: sorteos_sesiones_qr
-- Registra cada escaneada del QR (una sesión = una factura posible)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sorteos_sesiones_qr` (
    `id`                    INT AUTO_INCREMENT PRIMARY KEY,
    `token`                 VARCHAR(64)     NOT NULL UNIQUE,
    `ip_origen`             VARCHAR(45)     DEFAULT NULL,
    `geo_lat`               DECIMAL(10,8)   DEFAULT NULL,
    `geo_lng`               DECIMAL(11,8)   DEFAULT NULL,
    `distancia_metros`      INT             DEFAULT NULL,
    `geo_precision_metros`  INT             DEFAULT NULL,
    `sucursal_id`           INT             DEFAULT NULL,
    `sucursal_nombre`       VARCHAR(100)    DEFAULT NULL,
    `creado_en`             DATETIME        NOT NULL,
    `expira_en`             DATETIME        NOT NULL,
    `usado`                 TINYINT(1)      NOT NULL DEFAULT 0,
    INDEX `idx_token`       (`token`),
    INDEX `idx_expira`      (`expira_en`),
    INDEX `idx_sucursal`    (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabla 2: sorteos_registros
-- Registra cada ticket enviado por un participante
-- nombre_sorteo permite reutilizar esta tabla en sorteos futuros
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sorteos_registros` (
    `id`                    INT AUTO_INCREMENT PRIMARY KEY,
    `nombre_sorteo`         VARCHAR(50)     NOT NULL DEFAULT 'concierto_martingarrix',
    `token_sesion`          VARCHAR(64)     NOT NULL,

    -- Datos del participante
    `nombre_completo`       VARCHAR(150)    NOT NULL,
    `numero_contacto`       VARCHAR(20)     NOT NULL,
    `numero_cedula`         VARCHAR(20)     DEFAULT NULL,
    `correo_electronico`    VARCHAR(150)    DEFAULT NULL,

    -- Datos del ticket
    `codigo_participacion`  VARCHAR(30)     NOT NULL,
    `puntos_sorteo`         INT             NOT NULL DEFAULT 0,
    `monto_ticket`          DECIMAL(10,2)   DEFAULT NULL,
    `es_club`               TINYINT(1)      NOT NULL DEFAULT 0,
    `foto_ticket`           VARCHAR(255)    DEFAULT NULL,

    -- Estado de validación automática (sin mencionar IA de cara al usuario)
    `validado_imagen`       TINYINT(1)      NOT NULL DEFAULT 0,

    -- Datos extraídos para triangulación diferida con VentasGlobalesAccessCSV
    `ia_codigo_pedido`      VARCHAR(30)     DEFAULT NULL,
    `ia_fecha`              DATE            DEFAULT NULL,
    `ia_hora`               TIME            DEFAULT NULL,
    `ia_sucursal_nombre`    VARCHAR(100)    DEFAULT NULL,
    `ia_monto_total`        DECIMAL(10,2)   DEFAULT NULL,

    -- Validación diferida contra sistema
    `validado_sistema`      TINYINT(1)      DEFAULT NULL,
    `validado_sistema_en`   DATETIME        DEFAULT NULL,
    `validado_sistema_por`  INT             DEFAULT NULL,

    -- Sucursal detectada por geolocalización
    `sucursal_id`           INT             DEFAULT NULL,
    `sucursal_nombre`       VARCHAR(100)    DEFAULT NULL,

    `fecha_registro`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- ── Restricción de duplicados: mismo código + misma sucursal + mismo sorteo ──
    -- Las sucursales tienen correlativos propios (pueden repetirse entre sucursales)
    -- pero NO dentro de la misma sucursal para el mismo sorteo
    UNIQUE KEY `uk_codigo_sucursal_sorteo` (`codigo_participacion`, `sucursal_id`, `nombre_sorteo`),

    INDEX `idx_token_sesion`        (`token_sesion`),
    INDEX `idx_codigo_participacion`(`codigo_participacion`),
    INDEX `idx_nombre_sorteo`       (`nombre_sorteo`),
    INDEX `idx_validado_sistema`    (`validado_sistema`),
    INDEX `idx_fecha_registro`      (`fecha_registro`),
    INDEX `idx_sucursal`            (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Si la tabla YA existe y necesitas agregar la restricción:
-- ALTER TABLE `sorteos_registros`
--   ADD UNIQUE KEY `uk_codigo_sucursal_sorteo`
--       (`codigo_participacion`, `sucursal_id`, `nombre_sorteo`);
-- =============================================================================
