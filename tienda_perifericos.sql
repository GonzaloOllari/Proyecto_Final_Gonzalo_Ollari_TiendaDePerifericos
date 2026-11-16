-- ======================================================================
-- ARCHIVO: tienda_perifericos.sql
-- BASE DE DATOS COMPLETA.
-- ======================================================================
-- Base de datos de OllariTech (tienda de periféricos)
-- Crea la base si no existe. utf8mb4 soporta tildes, ñ y emojis.
CREATE DATABASE IF NOT EXISTS tienda_perifericos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tienda_perifericos;

-- TABLA USUARIOS: clientes y administradores.
CREATE TABLE IF NOT EXISTS usuarios (
  -- id: número automático (AUTO_INCREMENT) que identifica cada fila; PRIMARY KEY = clave primaria.
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  apellido VARCHAR(80) NOT NULL,
  -- UNIQUE: no puede haber dos usuarios con el mismo DNI o el mismo email.
  dni VARCHAR(20) NOT NULL UNIQUE,
  email VARCHAR(254) NOT NULL UNIQUE,
  -- Se guarda el HASH de la contraseña, nunca la contraseña real.
  password_hash VARCHAR(255) NOT NULL,
  -- ENUM: el campo solo admite uno de esos valores.
  rol ENUM('usuario','admin') NOT NULL DEFAULT 'usuario',
  -- Fecha de alta (se completa sola con la fecha y hora actual).
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- NULL = cuenta activa.
  archivado_en TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB;

-- TABLA CATEGORÍAS: Mouse, Teclados, Auriculares y Monitores.
CREATE TABLE IF NOT EXISTS categorias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- TABLA MARCAS: Logitech, Razer, etc.
CREATE TABLE IF NOT EXISTS marcas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- TABLA PRODUCTOS.
CREATE TABLE IF NOT EXISTS productos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  descripcion VARCHAR(500) NOT NULL DEFAULT '',
  marca_id INT UNSIGNED NOT NULL,
  categoria_id INT UNSIGNED NOT NULL,
  -- DECIMAL(10,2): hasta 10 dígitos con 2 decimales (ideal para dinero; no usa FLOAT para evitar
  -- errores de redondeo).
  precio DECIMAL(10,2) NOT NULL,
  -- UNSIGNED = no admite negativos: el stock nunca baja de 0.
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  -- 1 = visible en el catálogo, 0 = oculto.
  activo TINYINT(1) NOT NULL DEFAULT 1,
  -- Solo se guarda el NOMBRE del archivo de la foto; el archivo está en uploads/productos/.
  imagen VARCHAR(40) NULL DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- CLAVES FORÁNEAS: marca_id y categoria_id deben existir en sus tablas.
  FOREIGN KEY (marca_id) REFERENCES marcas(id),
  FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB;

-- TABLA PEDIDOS: cada compra = una fila (1 cliente, 1 producto, cantidad).
CREATE TABLE IF NOT EXISTS pedidos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad SMALLINT UNSIGNED NOT NULL,
  -- Precio al momento de comprar (copia del precio del producto), para que cambios futuros no
  -- alteren pedidos viejos.
  precio_unitario DECIMAL(10,2) NOT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- ON DELETE CASCADE: si se borra el usuario, se borran sus pedidos.
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

-- DATOS INICIALES: las 4 categorías.
INSERT INTO categorias (id, nombre) VALUES (1,'Mouse'), (2,'Teclados'), (3,'Auriculares'), (4,'Monitores');

-- Las 8 marcas iniciales.
INSERT INTO marcas (id, nombre) VALUES
 (1,'Logitech'), (2,'Redragon'), (3,'HyperX'), (4,'Razer'), (5,'Corsair'), (6,'Samsung'), (7,'LG'), (8,'AOC');

-- Cada fila: nombre, descripción, marca_id, categoria_id, precio, stock (los números de marca y
-- categoría son los ids de arriba).
-- Productos de ejemplo (precios en pesos, inventados para el trabajo práctico)
INSERT INTO productos (nombre, descripcion, marca_id, categoria_id, precio, stock) VALUES
 ('Logitech G203 Lightsync',      'Mouse gamer con cable e iluminación RGB.', 1, 1, 42000, 15),
 ('Logitech M170 Inalámbrico',    'Mouse inalámbrico compacto para uso diario.', 1, 1, 17500, 30),
 ('Redragon Cobra M711',          'Mouse gamer con cable, iluminación RGB y botones programables.', 2, 1, 28000, 12),
 ('Razer DeathAdder Essential',   'Mouse gamer ergonómico con cable.', 4, 1, 39000, 8),
 ('HyperX Pulsefire Core',        'Mouse gamer liviano con cable e iluminación RGB.', 3, 1, 36000, 10),
 ('Redragon Kumara K552',         'Teclado mecánico compacto con iluminación.', 2, 2, 55000, 10),
 ('Logitech K120',                'Teclado de oficina con cable, resistente a salpicaduras.', 1, 2, 16000, 25),
 ('HyperX Alloy Origins Core',    'Teclado mecánico sin teclado numérico y con iluminación RGB.', 3, 2, 135000, 5),
 ('Razer Cynosa Lite',            'Teclado gamer de membrana con iluminación RGB.', 4, 2, 52000, 7),
 ('Corsair K55 Core',             'Teclado gamer de membrana con iluminación RGB.', 5, 2, 68000, 6),
 ('HyperX Cloud Stinger',         'Auriculares gamer con micrófono, livianos y cómodos.', 3, 3, 58000, 9),
 ('Redragon Zeus H510',           'Auriculares gamer con micrófono y sonido envolvente virtual.', 2, 3, 62000, 8),
 ('Logitech G435 Inalámbricos',   'Auriculares inalámbricos livianos para juegos y música.', 1, 3, 85000, 6),
 ('Razer Kraken X',               'Auriculares gamer livianos con micrófono.', 4, 3, 64000, 7),
 ('Corsair HS55 Stereo',          'Auriculares estéreo con micrófono y almohadillas de espuma.', 5, 3, 72000, 4),
 ('Samsung Odyssey G3 24"',       'Monitor gamer de 24 pulgadas y 144 Hz.', 6, 4, 245000, 6),
 ('LG 24MP400 24"',               'Monitor IPS de 24 pulgadas para uso diario.', 7, 4, 175000, 9),
 ('AOC 24G2 24"',                 'Monitor gamer IPS de 24 pulgadas y 144 Hz.', 8, 4, 235000, 5),
 ('Samsung T350 22"',             'Monitor de 22 pulgadas para estudio y oficina.', 6, 4, 140000, 12),
 ('AOC 22B2H 22"',                'Monitor de 22 pulgadas con entradas HDMI y VGA.', 8, 4, 128000, 3);

-- El hash de abajo corresponde a la contraseña Admin1234 (generado con password_hash de PHP /
-- bcrypt).
-- Admin inicial: admin@tienda.com / Admin1234  (cambiá la clave después de entrar)
INSERT INTO usuarios (nombre, apellido, dni, email, password_hash, rol) VALUES
 ('Administrador','Tienda','00000000','admin@tienda.com','$2y$12$O4/P7.lgU.7.Kz5uivfCYudmQ/tzioJ.zCk7kEjb4JxZQEHXPVMo2','admin');
