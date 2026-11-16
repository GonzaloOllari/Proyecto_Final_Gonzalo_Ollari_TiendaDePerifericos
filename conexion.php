<?php
// ======================================================================
// ARCHIVO: conexion.php
// ARCHIVO CENTRAL del sitio.
// ======================================================================
// ===== Configuración de la tienda  =====
// CONSTANTES de configuración (const = valor fijo que no cambia mientras corre el programa).
const TIENDA_NOMBRE    = 'OllariTech';
const PEDIDO_MAX_UNIDADES = 5;  // unidades máximas por pedido
const IMG_MAX_BYTES = 2097152;   // 2 MB por foto
const IMG_DIR = __DIR__ . '/uploads/productos/';
const IMG_URL = 'uploads/productos/';

// Zona horaria: hace que date() y las fechas de PHP salgan en hora argentina.
date_default_timezone_set('America/Argentina/Buenos_Aires');
// Configura la cookie de sesión. httponly = JavaScript no puede leerla (protege si hubiera un
// ataque XSS). samesite Lax = el navegador no la manda en pedidos que vienen desde otros sitios
// (ayuda contra CSRF).
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
// Inicia (o retoma) la sesión del visitante. $_SESSION es un arreglo que PHP recuerda entre
// páginas: acá se guarda quién está logueado (uid, nombre, rol).
session_start();
// CONEXIÓN A LA BASE DE DATOS con PDO.
try {
    $conexion = new PDO('mysql:host=localhost;dbname=tienda_perifericos;charset=utf8mb4', 'root', '', [
        // ERRMODE_EXCEPTION: si una consulta SQL falla, PHP lanza una excepción (PDOException) que
        // se puede atrapar con try/catch.
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // FETCH_ASSOC: cada fila llega como arreglo asociativo, o sea que se lee con
        // $fila['nombre'].
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
// Si no se puede conectar: el error real se guarda en el log del servidor (el visitante no lo ve) y
// se responde con un error 500 genérico. exit corta la ejecución.
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('No se pudo conectar con la base de datos.');
}

// ===== Seguridad: escape de HTML, CSRF, sesión y roles =====
// e(): escapa caracteres especiales de HTML (< > & comillas).
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
// csrf(): devuelve el token anti-CSRF de la sesión; si todavía no existe, lo crea con 16 bytes
// aleatorios convertidos a hexadecimal.
function csrf() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
// csrf_campo(): devuelve el campo oculto <input hidden> con el token.
function csrf_campo() { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
// csrf_ok(): comprueba que el token enviado por POST coincida con el de la sesión. hash_equals
// compara de forma segura (tiempo constante).
function csrf_ok() { return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']); }
// es_admin(): true si el usuario logueado tiene rol 'admin'.
function es_admin() { return ($_SESSION['rol'] ?? '') === 'admin'; }

// requiere_login(): se llama al principio de toda página privada.
function requiere_login() {
    global $conexion;
    // No hay usuario logueado: manda al login con el aviso ?requiere=1 y corta (exit es obligatorio
    // después de header Location).
    if (empty($_SESSION['uid'])) { header('Location: login.php?requiere=1'); exit; }
    // Se revalida contra la base: si la cuenta fue archivada o ya no existe, se cierra la sesión
    // Consulta PREPARADA: :i es un marcador y el valor se envía aparte en execute(), así nadie
    // puede inyectar SQL. fetch() trae una sola fila (o false si no hay).
    $st = $conexion->prepare('SELECT nombre, rol, archivado_en FROM usuarios WHERE id = :i');
    $st->execute(['i' => (int)$_SESSION['uid']]);
    $u = $st->fetch();
    // Si el usuario ya no existe o está archivado: se vacía y destruye la sesión y se manda al
    // login.
    if (!$u || $u['archivado_en'] !== null) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php?archivada=1');
        exit;
    }
    // Si todo está bien, se actualizan nombre y rol en la sesión con los datos frescos de la base.
    $_SESSION['nombre'] = $u['nombre'];
    $_SESSION['rol'] = $u['rol'];   // el rol siempre sale de la base, no de lo que quedó en la sesión
}

// requiere_admin(): igual que requiere_login() pero además exige rol admin.
function requiere_admin() {
    requiere_login();
    // 403 = 'Prohibido'.
    if (!es_admin()) {
        http_response_code(403);
        cabecera('Acceso denegado');
        echo '<section class="panel" style="max-width:560px;margin:0 auto"><h2>Acceso denegado</h2>'
           . '<div class="msg error">Esta sección es solo para administradores.</div>'
           . '<p><a class="btn" href="panel.php">Volver a mi panel</a></p></section>';
        pie();
        exit;
    }
}

// ===== Mensajes que sobreviven a una redirección =====
// MENSAJES FLASH: se guardan en la sesión para mostrarlos UNA vez después de una redirección (por
// ejemplo 'Producto agregado').
function flash($tipo, $txt) { $_SESSION['flash'] = ['tipo' => $tipo, 'txt' => $txt]; }
// flash_mostrar(): si hay un mensaje guardado lo imprime (verde si el tipo es 'ok', rojo si no) y
// lo borra con unset para que no se repita.
function flash_mostrar() {
    if (empty($_SESSION['flash'])) return;
    $f = $_SESSION['flash']; unset($_SESSION['flash']);
    echo '<div class="msg ' . ($f['tipo'] === 'ok' ? 'ok' : 'error') . '">' . e($f['txt']) . '</div>';
}

// Las acciones que modifican datos solo se aceptan por POST
// solo_post(): las acciones que modifican datos solo aceptan POST.
function solo_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método no permitido.'); }
}

// ===== Validación compartida de usuarios (registro de admin y edición) =====
// Devuelve '' si todo está bien, o el mensaje de error.
// validar_usuario(): reglas compartidas por alta y edición de usuarios.
function validar_usuario(array $d, bool $passObligatoria): string {
    $pass = $d['password'] ?? '';
    if ($d['nombre'] === '' || $d['apellido'] === '') return 'Completá nombre y apellido.';
    if (mb_strlen($d['nombre']) > 80 || mb_strlen($d['apellido']) > 80) return 'El nombre y el apellido pueden tener hasta 80 caracteres.';
    // Expresión regular: ^ inicio, \d{7,9} entre 7 y 9 dígitos, $ fin.
    if (!preg_match('/^\d{7,9}$/', $d['dni'])) return 'El DNI debe tener entre 7 y 9 números, sin puntos.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 254) return 'Ingresá un email válido.';
    if ($passObligatoria || $pass !== '') {
        if (strlen($pass) < 8) return 'La contraseña debe tener al menos 8 caracteres.';
        // Máximo 72: bcrypt (el algoritmo de password_hash) ignora lo que pase de 72 bytes.
        if (strlen($pass) > 72) return 'La contraseña no puede superar los 72 caracteres.';
    }
    return '';
}

// Devuelve un mensaje si el DNI o el email ya pertenecen a OTRO usuario ('' si están libres)
// usuario_duplicado(): busca si el DNI o el email ya los tiene OTRO usuario (id <> :i excluye al
// propio al editar).
function usuario_duplicado(PDO $c, string $dni, string $email, int $excluirId = 0): string {
    $st = $c->prepare('SELECT dni, email FROM usuarios WHERE (dni = :d OR email = :e) AND id <> :i');
    $st->execute(['d' => $dni, 'e' => $email, 'i' => $excluirId]);
    $dupDni = $dupMail = false;
    foreach ($st->fetchAll() as $f) {
        if ($f['dni'] === $dni) $dupDni = true;
        if (strcasecmp($f['email'], $email) === 0) $dupMail = true;
    }
    if ($dupDni && $dupMail) return 'Ya existe un usuario con ese DNI y con ese email.';
    if ($dupDni) return 'Ya existe un usuario con ese DNI.';
    if ($dupMail) return 'Ya existe un usuario con ese email.';
    return '';
}

// ===== Productos =====
// precio(): da formato de pesos.
function precio($n) { $n = (float)$n; return '$ ' . number_format($n, floor($n) == $n ? 0 : 2, ',', '.'); }

// icono_categoria(): devuelve un emoji según la categoría.
function icono_categoria($nombre) {
    $n = strtolower((string)$nombre);
    if (strpos($n, 'mouse') === 0) return '🖱️';
    if (strpos($n, 'teclado') === 0) return '⌨️';
    if (strpos($n, 'auricular') === 0) return '🎧';
    if (strpos($n, 'monitor') === 0) return '🖥️';
    return '📦';
}

// etiqueta_stock(): devuelve la píldora HTML según el stock: 0 = Sin stock, 1 a 3 = Últimas
// unidades, más = En stock.
function etiqueta_stock(int $stock) {
    if ($stock <= 0) return '<span class="etq cancelado">Sin stock</span>';
    if ($stock <= 3) return '<span class="etq pendiente">Últimas ' . $stock . ' unidades</span>';
    return '<span class="etq confirmado">En stock</span>';
}

// Búsqueda del catálogo por producto (nombre), marca y categoría. Todo con consultas preparadas.
// buscar_productos(): consulta única que usan el catálogo público y el panel admin. $f trae los
// filtros, $soloActivos oculta los productos ocultos, $limite pone tope a la cantidad de filas.
function buscar_productos(PDO $c, array $f, bool $soloActivos, int $limite = 200): array {
    // Se arma la consulta por partes.
    $sql = 'SELECT p.id, p.nombre, p.descripcion, p.precio, p.stock, p.activo, p.imagen, p.marca_id, p.categoria_id,
                   m.nombre AS marca, c.nombre AS categoria
            FROM productos p
            JOIN marcas m ON m.id = p.marca_id
            JOIN categorias c ON c.id = p.categoria_id
            WHERE 1 = 1';
    // $par acumula los valores de los marcadores (:q, :m, :c) para pasarlos a execute().
    $par = [];
    // Catálogo público: solo productos visibles (activo = 1).
    if ($soloActivos) $sql .= ' AND p.activo = 1';
    // Filtro por texto. addcslashes escapa % _ y \ para que el usuario no pueda usarlos como
    // comodines del LIKE.
    if (($f['q'] ?? '') !== '') {
        $sql .= ' AND p.nombre LIKE :q';
        $par['q'] = '%' . addcslashes($f['q'], '%_\\') . '%';
    }
    // Filtros por marca y categoría (ids numéricos ya convertidos con (int)).
    if (!empty($f['marca']))     { $sql .= ' AND p.marca_id = :m';     $par['m'] = (int)$f['marca']; }
    if (!empty($f['categoria'])) { $sql .= ' AND p.categoria_id = :c'; $par['c'] = (int)$f['categoria']; }
    // LISTA BLANCA de ordenamientos: lo que llega por la URL solo sirve como clave para elegir una
    // de estas opciones.
    $orden = ['nombre' => 'p.nombre ASC', 'precio_asc' => 'p.precio ASC', 'precio_desc' => 'p.precio DESC', 'recientes' => 'p.id DESC'];
    $sql .= ' ORDER BY ' . ($orden[$f['orden'] ?? 'nombre'] ?? $orden['nombre']) . ' LIMIT ' . (int)$limite;
    // Se prepara la consulta ya armada, se ejecuta con los parámetros y se devuelven todas las
    // filas.
    $st = $c->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

// Devuelve '' si los datos de un producto son válidos, o el mensaje de error
// validar_producto(): valida nombre, descripción, precio (hasta 8 enteros y 2 decimales, mayor a 0)
// y stock (entero >= 0).
function validar_producto(array $d): string {
    if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 120) return 'Ingresá el nombre del producto (hasta 120 caracteres).';
    if (mb_strlen($d['descripcion']) > 500) return 'La descripción puede tener hasta 500 caracteres.';
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $d['precio']) || (float)$d['precio'] <= 0) return 'Ingresá un precio válido mayor a 0 (por ejemplo 25000 o 25000.50).';
    if (!preg_match('/^\d{1,6}$/', $d['stock'])) return 'El stock debe ser un número entero de 0 en adelante.';
    return '';
}

// Busca una marca por nombre y, si no existe, la crea. Devuelve su id.
// obtener_marca_id(): busca la marca por nombre; si no existe la crea. lastInsertId() da el id de
// la fila recién insertada.
function obtener_marca_id(PDO $c, string $nombre): int {
    $st = $c->prepare('SELECT id FROM marcas WHERE nombre = :n');
    $st->execute(['n' => $nombre]);
    $id = $st->fetchColumn();
    if ($id) return (int)$id;
    $c->prepare('INSERT INTO marcas (nombre) VALUES (:n)')->execute(['n' => $nombre]);
    return (int)$c->lastInsertId();
}

// ===== Fotos de productos =====
// Procesa la foto subida en $_FILES[$campo]. Devuelve [nombre_del_archivo | null, mensaje_de_error].
// Si no se eligió ningún archivo devuelve [null, ''].
// subir_imagen(): maneja la subida segura de la foto.
function subir_imagen(string $campo): array {
    if (empty($_FILES[$campo]) || ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, ''];
    // $_FILES guarda los datos del archivo subido: error, size (bytes), tmp_name (ubicación
    // temporal).
    $a = $_FILES[$campo];
    // Revisa si el archivo supera el límite de PHP o el de 2 MB de la tienda.
    if ($a['error'] === UPLOAD_ERR_INI_SIZE || $a['error'] === UPLOAD_ERR_FORM_SIZE || $a['size'] > IMG_MAX_BYTES) return [null, 'La foto es demasiado pesada (máximo 2 MB).'];
    if ($a['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($a['tmp_name'])) return [null, 'No se pudo subir la foto. Intentá de nuevo.'];
    // El tipo se detecta mirando el contenido del archivo, no su nombre ni lo que dice el navegador
    $info = @getimagesize($a['tmp_name']);
    // Tipos permitidos y su extensión.
    $tipos = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($tipos[$info[2]])) return [null, 'El archivo tiene que ser una imagen JPG, PNG, GIF o WEBP.'];
    // Si la carpeta de fotos no existe la crea (0755 = permisos; true = crea también carpetas
    // intermedias).
    if (!is_dir(IMG_DIR) && !mkdir(IMG_DIR, 0755, true)) return [null, 'No se pudo guardar la foto en el servidor.'];
    // Nombre ALEATORIO de 24 caracteres hex + extensión elegida por el servidor.
    $nombre = bin2hex(random_bytes(12)) . '.' . $tipos[$info[2]];   // nombre propio: nadie elige la extensión
    // Mueve el archivo desde la carpeta temporal a uploads/productos/.
    if (!move_uploaded_file($a['tmp_name'], IMG_DIR . $nombre)) return [null, 'No se pudo guardar la foto en el servidor.'];
    return [$nombre, ''];
}

// nombre_imagen_valido(): true solo si el nombre tiene EXACTAMENTE el formato que genera la tienda
// (24 caracteres hex + extensión permitida).
function nombre_imagen_valido($n): bool { return is_string($n) && preg_match('/^[a-f0-9]{24}\.(jpg|png|gif|webp)$/', $n) === 1; }

// borrar_imagen(): borra el archivo de la foto del disco, pero solo si el nombre es válido y el
// archivo existe. @ silencia el aviso si falla.
function borrar_imagen($nombre): void {
    if (nombre_imagen_valido($nombre) && is_file(IMG_DIR . $nombre)) @unlink(IMG_DIR . $nombre);
}

// Foto del producto (<img>) o, si no tiene, el ícono de su categoría
// html_imagen(): devuelve la etiqueta <img> con la foto del producto o, si no tiene, el emoji de su
// categoría. loading=lazy: el navegador carga la foto solo cuando se acerca en pantalla.
function html_imagen(array $p): string {
    if (nombre_imagen_valido($p['imagen'] ?? null)) {
        return '<img src="' . IMG_URL . e($p['imagen']) . '" alt="' . e($p['nombre']) . '" loading="lazy">';
    }
    return icono_categoria($p['categoria'] ?? '');
}

// ===== Diseño común de las páginas =====
// tarjeta_producto(): dibuja UNA tarjeta del catálogo.
function tarjeta_producto(array $p) { ?>
<?php /* (int) en los ids y e() en los textos: todo lo que se imprime pasa por un filtro de seguridad. */ ?>
<article class="prod">
  <a class="prod-img" href="producto.php?id=<?= (int)$p['id'] ?>" aria-label="<?= e($p['nombre']) ?>"><?= html_imagen($p) ?></a>
  <div class="prod-datos">
    <span class="rotulo"><?= e($p['marca']) ?> · <?= e($p['categoria']) ?></span>
    <h3><a href="producto.php?id=<?= (int)$p['id'] ?>"><?= e($p['nombre']) ?></a></h3>
    <p class="precio"><?= e(precio($p['precio'])) ?></p>
    <div><?= etiqueta_stock((int)$p['stock']) ?></div>
  </div>
</article>
<?php }

// cabecera($titulo): imprime el <head> completo y el menú superior.
function cabecera($titulo) { ?>
<?php /* Estructura base HTML. */ ?>
<!DOCTYPE html>
<html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $titulo === TIENDA_NOMBRE ? e(TIENDA_NOMBRE) : e($titulo) . ' | ' . e(TIENDA_NOMBRE) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php /* Carga las tipografías Roboto desde Google Fonts. */ ?>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<?php /*
   Hoja de estilos. ?v= con la fecha de modificación (filemtime) obliga al navegador a recargarla
   cuando cambia el archivo.
*/ ?>
<link rel="stylesheet" href="css/estilos.css?v=<?= filemtime(__DIR__ . '/css/estilos.css') ?>">
</head><body>
<?php /* MENÚ SUPERIOR. */ ?>
<header class="top"><div class="wrap bar">
  <a class="marca" href="index.php"><?= e(TIENDA_NOMBRE) ?></a>
  <nav>
    <a href="index.php">Catálogo</a>
    <?php /*
       Con sesión iniciada: Mi panel + (admin: Pedidos, Productos, Usuarios | cliente: Mis pedidos)
       + Salir.
    */ ?>
    <?php if (!empty($_SESSION['uid'])): ?>
      <a href="panel.php">Mi panel</a>
      <?php /* Administrador: Pedidos, Productos y Usuarios. */ ?>
      <?php if (es_admin()): ?>
        <a href="admin.php">Pedidos</a><a href="admin_productos.php">Productos</a><a href="admin_usuarios.php">Usuarios</a>
      <?php /* Cliente: Mis pedidos. */ ?>
      <?php else: ?>
        <a href="pedidos.php">Mis pedidos</a>
      <?php endif; ?>
      <a href="cerrar_sesion.php">Salir (<?= e($_SESSION['nombre']) ?>)</a>
    <?php /* Sin sesión: Ingresar y Crear cuenta. */ ?>
    <?php else: ?>
      <a href="login.php">Ingresar</a><a class="btn chico" href="registro.php">Crear cuenta</a>
    <?php endif; ?>
  </nav>
</div></header>
<?php /* Acá empieza el contenido propio de cada página (lo cierra pie()). */ ?>
<main class="wrap">
<?php }

// pie(): cierra <main> e imprime el pie de página con los créditos y cierra el HTML.
function pie() { ?>
</main>
<footer class="pie">
  <div class="wrap">Página realizada por Gonzalo Ollari | 4A | Trabajo Práctico Final | 2026</div>
</footer>
</body></html>
<?php }
