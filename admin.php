<?php
// ======================================================================
// ARCHIVO: admin.php
// PEDIDOS REALIZADOS (solo administrador).
// ======================================================================
// Carga configuración y funciones.
require_once __DIR__ . '/conexion.php';
// Solo administradores; a un cliente le muestra 'Acceso denegado'.
requiere_admin();

// ----- Búsqueda de pedidos: producto, marca, categoría, cliente, DNI, email y fecha -----
// Filtros que llegan por la URL: $q = texto a buscar, $ff = fecha.
$q  = trim($_GET['q'] ?? '');
$ff = $_GET['fecha'] ?? '';
// Consulta que une 5 tablas: pedidos, productos, marcas, categorías y usuarios. 'WHERE 1 = 1'
// permite agregar filtros con AND.
$sql = 'SELECT pe.id, pe.cantidad, pe.precio_unitario, pe.creado_en,
               pr.nombre AS producto, pr.imagen, m.nombre AS marca, c.nombre AS categoria,
               u.nombre, u.apellido, u.dni, u.email
        FROM pedidos pe
        JOIN productos pr ON pr.id = pe.producto_id
        JOIN marcas m ON m.id = pr.marca_id
        JOIN categorias c ON c.id = pr.categoria_id
        JOIN usuarios u ON u.id = pe.usuario_id
        WHERE 1 = 1';
// Acá se acumulan los valores de los marcadores.
$par = [];
// Filtro de texto: se busca en producto, marca, categoría, nombre, apellido, DNI y email a la vez
// (OR).
if ($q !== '') {
    // Un marcador distinto (q1 a q7) por cada columna, aunque el valor sea el mismo.
    $sql .= ' AND (pr.nombre LIKE :q1 OR m.nombre LIKE :q2 OR c.nombre LIKE :q3 OR u.nombre LIKE :q4 OR u.apellido LIKE :q5 OR u.dni LIKE :q6 OR u.email LIKE :q7)';
    // Se rodea el texto de % (comodín 'cualquier cosa') y se escapan los comodines que escriba el
    // usuario con addcslashes.
    $like = '%' . addcslashes($q, '%_\\') . '%';
    // Se asigna el mismo valor $like a los 7 marcadores.
    foreach (['q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7'] as $k) $par[$k] = $like;
}
// Filtro de fecha: solo se acepta con formato AAAA-MM-DD (lo que manda el <input type=date>); si
// no, se descarta.
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ff)) { $sql .= ' AND DATE(pe.creado_en) = :f'; $par['f'] = $ff; } else { $ff = ''; }
// Se agrega el orden (más recientes primero) y un tope de 200 filas, y se ejecuta.
$st = $conexion->prepare($sql . ' ORDER BY pe.creado_en DESC, pe.id DESC LIMIT 200');
$st->execute($par);
$pedidos = $st->fetchAll();

// Suma de los totales (cantidad × precio) de los pedidos listados.
$totalGeneral = 0;
foreach ($pedidos as $t) $totalGeneral += $t['cantidad'] * $t['precio_unitario'];

// HTML.
cabecera('Pedidos realizados'); ?>
<?php flash_mostrar(); ?>

<section class="panel">
  <h2>Pedidos realizados (<?= count($pedidos) ?>)</h2>
  <?php /* Formulario de filtros por GET. */ ?>
  <form class="f" method="get" style="grid-template-columns:3fr 1fr auto auto;align-items:end">
    <div><label for="q">Buscar por producto, marca, categoría, cliente, DNI o email</label><input id="q" name="q" value="<?= e($q) ?>"></div>
    <div><label for="fecha">Fecha</label><input id="fecha" type="date" name="fecha" value="<?= e($ff) ?>"></div>
    <button type="submit">Filtrar</button>
    <?php if ($q !== '' || $ff !== ''): ?><a class="btn sec" href="admin.php">Limpiar</a><?php endif; ?>
  </form>
  <?php if ($pedidos): ?><p class="ayuda">Total de los pedidos listados: <strong><?= e(precio($totalGeneral)) ?></strong></p><?php endif; ?>

  <?php if (!$pedidos): ?><p style="margin-top:1rem">Todavía no hay pedidos con esos criterios.</p><?php endif; ?>
  <?php /* Por cada pedido se dibuja una tarjeta. */ ?>
  <?php foreach ($pedidos as $t):
    // Pedido 'Nuevo' = hecho en las últimas 24 horas (86400 segundos). strtotime convierte la fecha
    // a número de segundos.
    $nuevo = strtotime($t['creado_en']) > time() - 86400; ?>
  <div class="pedido">
    <p class="pedido-titulo">
      <strong><?= e($t['nombre'] . ' ' . $t['apellido']) ?></strong> realizó un pedido
      <?php if ($nuevo): ?><span class="etq pendiente">Nuevo</span><?php endif; ?>
    </p>
    <?php /* Cuatro bloques en columnas: Pedido, Cliente, Producto y Compra. */ ?>
    <div class="pedido-info">
      <div>
        <span class="rotulo">Pedido</span>
        N° <?= str_pad($t['id'], 6, '0', STR_PAD_LEFT) ?><br>
        <?= e(date('d/m/Y H:i', strtotime($t['creado_en']))) ?> hs<br>
        <small><a href="comprobante.php?id=<?= (int)$t['id'] ?>">Ver comprobante</a></small>
      </div>
      <div>
        <span class="rotulo">Cliente</span>
        <?= e($t['apellido'] . ', ' . $t['nombre']) ?><br>
        DNI <?= e($t['dni']) ?><br>
        <?= e($t['email']) ?>
      </div>
      <div>
        <span class="rotulo">Producto</span>
        <?php /* Foto chica del producto (o su emoji de categoría). */ ?>
        <div class="mini"><?= html_imagen(['imagen' => $t['imagen'], 'nombre' => $t['producto'], 'categoria' => $t['categoria']]) ?></div>
        <?= e($t['producto']) ?><br>
        <small><?= e($t['marca']) ?> · <?= e($t['categoria']) ?></small>
      </div>
      <div>
        <span class="rotulo">Compra</span>
        <?= (int)$t['cantidad'] ?> × <?= e(precio($t['precio_unitario'])) ?><br>
        Total: <strong><?= e(precio($t['cantidad'] * $t['precio_unitario'])) ?></strong>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</section>
<?php pie();
