-- ======================================================================
-- ARCHIVO: actualizar_bd.sql
-- SCRIPT DE ACTUALIZACIÓN.
-- ======================================================================
-- Solo si ya tenías importada la versión anterior y no querés borrar la base.
-- (Si preferís empezar de cero, borrá la base y volvé a importar tienda_perifericos.sql)
-- Selecciona la base sobre la que se va a trabajar.
USE tienda_perifericos;
-- Agrega la columna 'imagen' (nombre de la foto) a productos, después de 'activo'.
ALTER TABLE productos ADD COLUMN imagen VARCHAR(40) NULL DEFAULT NULL AFTER activo;
-- Elimina la columna 'estado' de pedidos: la versión actual ya no maneja estados de pedido.
ALTER TABLE pedidos DROP COLUMN estado;
