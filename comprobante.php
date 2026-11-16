<?php
// ======================================================================
// ARCHIVO: comprobante.php
// COMPROBANTE IMPRIMIBLE de un pedido (comprobante.php?id=NUMERO).
// ======================================================================
// Carga configuración y funciones.
require_once __DIR__ . '/conexion.php';
// Página privada.
requiere_login();
// Trae el pedido junto con producto, marca y datos del cliente (nombre, apellido, DNI) uniendo 4
// tablas.
$st = $conexion->prepare('SELECT pe.id, pe.usuario_id, pe.cantidad, pe.precio_unitario, pe.creado_en,
                                 pr.nombre AS producto, m.nombre AS marca, u.nombre, u.apellido, u.dni
                          FROM pedidos pe JOIN productos pr ON pr.id = pe.producto_id JOIN marcas m ON m.id = pr.marca_id
                          JOIN usuarios u ON u.id = pe.usuario_id WHERE pe.id = :id');
// El id sale de la URL y se convierte con (int) antes de usarlo.
$st->execute(['id' => (int)($_GET['id'] ?? 0)]);
// $t = el pedido (o false si no existe).
$t = $st->fetch();
// CONTROL DE ACCESO: si no existe, o no es del usuario logueado y tampoco es admin, se muestra 404.
if (!$t || ((int)$t['usuario_id'] !== (int)$_SESSION['uid'] && !es_admin())) {
    http_response_code(404);
    cabecera('Comprobante no encontrado');
    echo '<section class="panel" style="max-width:560px;margin:0 auto"><h2>Comprobante no encontrado</h2><div class="msg error">No existe ese comprobante o no corresponde a tu cuenta.</div><p><a class="btn" href="panel.php">Volver a mi panel</a></p></section>';
    pie();
    exit;
}
// HTML.
cabecera('Comprobante de pedido'); ?>
<?php /* Caja del comprobante (estilos en .comprobante del CSS). */ ?>
<div class="comprobante">
  <h2><?= e(TIENDA_NOMBRE) ?></h2>
  <p>Comprobante de pedido N° <?= str_pad($t['id'], 6, '0', STR_PAD_LEFT) ?></p>
  <dl>
    <dt>Cliente</dt><dd><?= e($t['apellido'] . ', ' . $t['nombre']) ?> (DNI <?= e($t['dni']) ?>)</dd>
    <dt>Producto</dt><dd><?= e($t['producto']) ?> · <?= e($t['marca']) ?></dd>
    <dt>Cantidad</dt><dd><?= (int)$t['cantidad'] ?> × <?= e(precio($t['precio_unitario'])) ?></dd>
    <dt>Total</dt><dd><strong><?= e(precio($t['cantidad'] * $t['precio_unitario'])) ?></strong></dd>
    <dt>Fecha del pedido</dt><dd><?= e(date('d/m/Y H:i', strtotime($t['creado_en']))) ?></dd>
  </dl>
  <?php /*
     La clase no-print oculta este botón al imprimir (ver @media print en estilos.css).
     window.print() abre el diálogo de impresión / guardar como PDF.
  */ ?>
  <p class="no-print"><button onclick="window.print()">Imprimir o guardar en PDF</button></p>
</div>
<?php pie();
