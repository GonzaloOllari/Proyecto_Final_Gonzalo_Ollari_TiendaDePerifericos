<?php
// ======================================================================
// ARCHIVO: pedidos.php
// 'MIS PEDIDOS' del cliente.
// ======================================================================
// Carga configuración y funciones.
require_once __DIR__ . '/conexion.php';
// Página privada.
requiere_login();

// Trae los pedidos (pe) uniendo producto (pr) y marca (m).
$st = $conexion->prepare('SELECT pe.id, pe.cantidad, pe.precio_unitario, pe.creado_en, pr.nombre AS producto, m.nombre AS marca
                          FROM pedidos pe JOIN productos pr ON pr.id = pe.producto_id JOIN marcas m ON m.id = pr.marca_id
                          WHERE pe.usuario_id = :u ORDER BY pe.id DESC');
$st->execute(['u' => (int)$_SESSION['uid']]);
// $mios = arreglo con los pedidos del usuario.
$mios = $st->fetchAll();

// HTML.
cabecera('Mis pedidos'); ?>
<?php /* Muestra el mensaje '¡Compra realizada!' que dejó producto.php. */ ?>
<?php flash_mostrar(); ?>
<section class="panel">
  <h2>Mis pedidos</h2>
  <?php /* Sin pedidos: mensaje con enlace al catálogo. */ ?>
  <?php if (!$mios): ?>
    <p>Todavía no compraste nada. <a href="index.php">Mirá el catálogo</a>.</p>
  <?php else: ?>
  <div class="tabla"><table>
    <tr><th>Pedido</th><th>Producto</th><th>Total</th><th></th></tr>
    <?php /* Una fila de la tabla por cada pedido. */ ?>
    <?php foreach ($mios as $t): ?>
    <tr>
      <?php /* str_pad rellena con ceros a la izquierda hasta 6 dígitos (el pedido 15 se ve N° 000015). */ ?>
      <td>N° <?= str_pad($t['id'], 6, '0', STR_PAD_LEFT) ?><br><small><?= e(date('d/m/Y H:i', strtotime($t['creado_en']))) ?></small></td>
      <td><?= e($t['producto']) ?><br><small><?= e($t['marca']) ?> · <?= (int)$t['cantidad'] ?> unidad<?= (int)$t['cantidad'] === 1 ? '' : 'es' ?></small></td>
      <?php /* Total = cantidad × precio unitario guardado en el pedido. */ ?>
      <td><?= e(precio($t['cantidad'] * $t['precio_unitario'])) ?></td>
      <?php /* Enlace al comprobante imprimible de ese pedido. */ ?>
      <td><a href="comprobante.php?id=<?= (int)$t['id'] ?>">Comprobante</a></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>
<?php pie();
