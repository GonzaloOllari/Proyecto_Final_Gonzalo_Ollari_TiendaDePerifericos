<?php
// ======================================================================
// ARCHIVO: producto.php
// DETALLE DE UN PRODUCTO + COMPRA.
// ======================================================================
// Carga la configuración, la base de datos y las funciones comunes.
require_once __DIR__ . '/conexion.php';

// El id llega por GET al entrar a la página y por POST al comprar. (int) lo convierte a número para
// que no se pueda mandar texto.
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
// Trae el producto con el nombre de su marca y su categoría (JOIN une las tablas por sus ids).
$st = $conexion->prepare('SELECT p.id, p.nombre, p.descripcion, p.precio, p.stock, p.activo, p.imagen, m.nombre AS marca, c.nombre AS categoria
                          FROM productos p JOIN marcas m ON m.id = p.marca_id JOIN categorias c ON c.id = p.categoria_id
                          WHERE p.id = :i');
$st->execute(['i' => $id]);
$p = $st->fetch();
// Si el producto no existe, o está oculto y quien mira no es admin: error 404 y se corta.
if (!$p || (!$p['activo'] && !es_admin())) {
    http_response_code(404);
    cabecera('Producto no encontrado');
    echo '<section class="panel" style="max-width:560px;margin:0 auto"><h2>Producto no encontrado</h2><div class="msg error">Ese producto no existe o ya no está disponible.</div><p><a class="btn" href="index.php">Volver al catálogo</a></p></section>';
    pie();
    exit;
}

// Acá se guardará el mensaje de error si algo falla al comprar.
$error = '';
// COMPRA: solo entra acá cuando se envió el formulario 'Comprar'.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Para comprar hay que estar logueado.
    requiere_login();
    // Cantidad pedida, convertida a número.
    $cant = (int)($_POST['cantidad'] ?? 0);
    // Cadena de validaciones: se corta en el primer problema. 1) token CSRF 2) los admins no
    // compran 3) cantidad entre 1 y el máximo.
    if (!csrf_ok()) {
        $error = 'La sesión expiró. Recargá la página.';
    } elseif (es_admin()) {
        $error = 'Las cuentas de administrador no compran. Usá una cuenta de cliente.';
    } elseif ($cant < 1 || $cant > PEDIDO_MAX_UNIDADES) {
        $error = 'Elegí una cantidad entre 1 y ' . PEDIDO_MAX_UNIDADES . '.';
    } else {
        try {
            // TRANSACCIÓN: agrupa varias consultas para que se hagan TODAS o NINGUNA.
            $conexion->beginTransaction();
            // UPDATE con condición 'stock >= cantidad': el descuento y la comprobación ocurren en
            // una sola consulta, así dos compradores no se llevan la misma última unidad.
            // Descuenta el stock solo si alcanza (evita que dos pedidos se lleven la misma unidad)
            $up = $conexion->prepare('UPDATE productos SET stock = stock - :c1 WHERE id = :i AND activo = 1 AND stock >= :c2');
            $up->execute(['c1' => $cant, 'i' => $id, 'c2' => $cant]);
            // rowCount() = cantidad de filas modificadas.
            if ($up->rowCount() !== 1) {
                $conexion->rollBack();
                $error = 'No hay stock suficiente para esa cantidad. Probá con menos unidades.';
            } else {
                // Lee el precio actual para guardarlo en el pedido (precio_unitario).
                $pr = $conexion->prepare('SELECT precio FROM productos WHERE id = :i');
                $pr->execute(['i' => $id]);
                $conexion->prepare('INSERT INTO pedidos (usuario_id, producto_id, cantidad, precio_unitario) VALUES (:u, :p, :c, :pr)')
                    ->execute(['u' => (int)$_SESSION['uid'], 'p' => $id, 'c' => $cant, 'pr' => $pr->fetchColumn()]);
                // commit() CONFIRMA la transacción: recién acá los cambios quedan guardados de
                // verdad.
                $conexion->commit();
                // Se deja un mensaje flash y se redirige a 'Mis pedidos'.
                flash('ok', '¡Compra realizada! Tu pedido quedó registrado.');
                header('Location: pedidos.php');
                exit;
            }
        // Error inesperado de base de datos: se deshace la transacción, se registra en el log y se
        // muestra un mensaje genérico.
        } catch (PDOException $ex) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            error_log($ex->getMessage());
            $error = 'No se pudo realizar la compra. Intentá de nuevo.';
        }
    }
    // refresca el stock mostrado
    $st->execute(['i' => $id]);
    $p = $st->fetch();
}
// Máximo que se puede elegir en el desplegable: el menor entre el stock y el límite por pedido
// (nunca negativo).
$maxCant = max(0, min((int)$p['stock'], PEDIDO_MAX_UNIDADES));

// Cabecera y menú.
cabecera($p['nombre']); ?>
<p class="ayuda"><a href="index.php">← Volver al catálogo</a></p>
<?php /* Dos columnas: foto a la izquierda y datos a la derecha (en celular se apilan). */ ?>
<section class="panel detalle">
  <div class="prod-img grande"><?= html_imagen($p) ?></div>
  <div>
    <span class="rotulo"><?= e($p['marca']) ?> · <?= e($p['categoria']) ?></span>
    <h2><?= e($p['nombre']) ?></h2>
    <p><?= e($p['descripcion']) ?></p>
    <p class="precio"><?= e(precio($p['precio'])) ?></p>
    <p><?= etiqueta_stock((int)$p['stock']) ?><?= $p['activo'] ? '' : ' <span class="etq cancelado">Oculto (solo lo ves vos como admin)</span>' ?></p>

    <?php /* Muestra el error de la compra, si lo hay. */ ?>
    <?php if ($error): ?><div class="msg error"><?= e($error) ?></div><?php endif; ?>

    <?php /*
       Qué se muestra según el caso: sin stock, visitante sin sesión, administrador (no compra) o
       cliente (formulario de compra).
    */ ?>
    <?php if ($maxCant < 1 || !$p['activo']): ?>
      <p class="aviso">Por ahora no tenemos unidades disponibles. Volvé a mirar el catálogo más adelante.</p>
    <?php elseif (empty($_SESSION['uid'])): ?>
      <p class="aviso">Para comprar tenés que <a href="login.php">ingresar</a> o <a href="registro.php">crear una cuenta</a>.</p>
    <?php elseif (es_admin()): ?>
      <p class="aviso">Estás con una cuenta de administrador. Podés editar este producto desde <a href="editar_producto.php?id=<?= (int)$p['id'] ?>">Productos</a>.</p>
    <?php else: ?>
      <?php /* Formulario de compra: token CSRF + id del producto oculto + cantidad. */ ?>
      <form class="f" method="post">
        <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <div><label for="cantidad">Cantidad</label>
          <?php /* El for genera las opciones del 1 hasta $maxCant. */ ?>
          <select id="cantidad" name="cantidad"><?php for ($i = 1; $i <= $maxCant; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></div>
        <button type="submit">Comprar</button>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php /* Cierra la página. */ ?>
<?php pie();
