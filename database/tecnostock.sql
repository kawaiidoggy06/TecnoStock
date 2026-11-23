-- =====================================================================
--  TecnoStock - Sistema de inventario
--  Script de creación de la base de datos (MySQL / MariaDB - XAMPP)
-- =====================================================================

DROP DATABASE IF EXISTS tecnostock;
CREATE DATABASE tecnostock CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
USE tecnostock;

-- ---------------------------------------------------------------------
-- Tabla: usuario
-- Guarda a los encargados de inventario que pueden iniciar sesión.
-- La clave se guarda cifrada con password_hash() de PHP, nunca en texto plano.
-- ---------------------------------------------------------------------
CREATE TABLE usuario (
    id_usuario  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    correo      VARCHAR(120) NOT NULL,
    clave       VARCHAR(255) NOT NULL,
    estado      TINYINT(1)   NOT NULL DEFAULT 1,      -- 1 = activo, 0 = inactivo
    CONSTRAINT uq_usuario_correo UNIQUE (correo),
    CONSTRAINT ck_usuario_estado CHECK (estado IN (0, 1))
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- Tabla: categoria
-- Separada de producto para no repetir nombres (normalización, 3FN).
-- ---------------------------------------------------------------------
CREATE TABLE categoria (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(60)  NOT NULL,
    descripcion  VARCHAR(255) NULL,
    estado       TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uq_categoria_nombre UNIQUE (nombre),
    CONSTRAINT ck_categoria_estado CHECK (estado IN (0, 1))
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- Tabla: producto
-- Regla 1: el código no se repite              -> UNIQUE
-- Regla 2: precio y stocks no negativos        -> CHECK
-- Regla 4: el stock nunca queda bajo cero      -> CHECK stock_actual >= 0
-- Regla 5: no se borra, se desactiva           -> estado + FK RESTRICT en movimiento
-- ---------------------------------------------------------------------
CREATE TABLE producto (
    id_producto  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo       VARCHAR(20)   NOT NULL,
    nombre       VARCHAR(100)  NOT NULL,
    descripcion  VARCHAR(255)  NULL,
    precio       DECIMAL(10,0) NOT NULL,              -- pesos chilenos, sin decimales
    stock_actual INT           NOT NULL DEFAULT 0,
    stock_minimo INT           NOT NULL DEFAULT 0,
    estado       TINYINT(1)    NOT NULL DEFAULT 1,
    id_categoria INT UNSIGNED  NOT NULL,
    CONSTRAINT uq_producto_codigo   UNIQUE (codigo),
    CONSTRAINT ck_producto_precio   CHECK (precio >= 0),
    CONSTRAINT ck_producto_stock    CHECK (stock_actual >= 0),
    CONSTRAINT ck_producto_minimo   CHECK (stock_minimo >= 0),
    CONSTRAINT ck_producto_estado   CHECK (estado IN (0, 1)),
    CONSTRAINT fk_producto_categoria FOREIGN KEY (id_categoria)
        REFERENCES categoria (id_categoria)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- Tabla: movimiento
-- Regla 3: todo movimiento registra fecha, tipo, cantidad, producto y usuario.
-- Regla 2: la cantidad debe ser mayor que cero.
-- ON DELETE RESTRICT impide borrar físicamente un producto con movimientos.
-- ---------------------------------------------------------------------
CREATE TABLE movimiento (
    id_movimiento INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha_hora    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo          ENUM('ENTRADA', 'SALIDA') NOT NULL,
    cantidad      INT          NOT NULL,
    observacion   VARCHAR(255) NULL,
    id_producto   INT UNSIGNED NOT NULL,
    id_usuario    INT UNSIGNED NOT NULL,
    CONSTRAINT ck_movimiento_cantidad CHECK (cantidad > 0),
    CONSTRAINT fk_movimiento_producto FOREIGN KEY (id_producto)
        REFERENCES producto (id_producto)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_movimiento_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuario (id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB;

-- Índices para que la búsqueda por nombre y categoría sea rápida
CREATE INDEX idx_producto_nombre    ON producto (nombre);
CREATE INDEX idx_movimiento_fecha   ON movimiento (fecha_hora);

-- =====================================================================
--  DATOS DE PRUEBA
-- =====================================================================

-- Usuario de prueba -> correo: admin@tecnostock.cl | clave: admin123
INSERT INTO usuario (nombre, correo, clave) VALUES
('Encargado Inventario', 'admin@tecnostock.cl',
 '$2y$10$A1fVIloms3dGnVUOdLitw.o8YGmGHiXLcRkiMFLy9FO51EZCtyCAi');

INSERT INTO categoria (nombre, descripcion) VALUES
('Audio',        'Audífonos, parlantes y micrófonos'),
('Cargadores',   'Cargadores, cables y adaptadores'),
('Periféricos',  'Teclados, mouse y accesorios de escritorio'),
('Almacenamiento','Pendrives, tarjetas de memoria y discos');

INSERT INTO producto (codigo, nombre, descripcion, precio, stock_actual, stock_minimo, id_categoria) VALUES
('AUD-001', 'Audífonos Bluetooth',   'Audífonos inalámbricos con micrófono', 19990, 25, 5, 1),
('AUD-002', 'Parlante portátil',     'Parlante Bluetooth resistente al agua', 24990,  3, 5, 1),
('CAR-001', 'Cargador USB-C 20W',    'Carga rápida para celulares',           9990, 40, 10, 2),
('CAR-002', 'Cable USB-C 1m',        'Cable trenzado USB-C a USB-C',          4990,  8, 10, 2),
('PER-001', 'Mouse inalámbrico',     'Mouse 1600 DPI con receptor USB',       12990, 15, 5, 3),
('ALM-001', 'Pendrive 64GB',         'Pendrive USB 3.0',                      7990,  0, 4, 4);

INSERT INTO movimiento (tipo, cantidad, observacion, id_producto, id_usuario) VALUES
('ENTRADA', 25, 'Stock inicial',            1, 1),
('ENTRADA',  5, 'Stock inicial',            2, 1),
('SALIDA',   2, 'Venta en tienda',          2, 1),
('ENTRADA', 40, 'Stock inicial',            3, 1),
('ENTRADA',  8, 'Stock inicial',            4, 1),
('ENTRADA', 15, 'Stock inicial',            5, 1),
('ENTRADA',  4, 'Stock inicial',            6, 1),
('SALIDA',   4, 'Venta mayorista',          6, 1);
