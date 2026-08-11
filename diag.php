<?php
// diag.php — diagnóstico temporal. BORRAR del servidor después de usarlo.
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

require __DIR__ . '/config.php';

echo "== Config ==\n";
echo 'DB_HOST=' . DB_HOST . "\n";
echo 'DB_NAME=' . DB_NAME . "\n";
echo 'DB_USER=' . DB_USER . "\n";
echo 'PHP=' . PHP_VERSION . "\n\n";

try {
    echo "== Conexion ==\n";
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "OK conexion\n\n";

    echo "== Tablas ==\n";
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        echo " - $t\n";
    }
    echo "\n== Prueba login query ==\n";
    $st = $pdo->prepare('SELECT id,email,rol,activo FROM users LIMIT 3');
    $st->execute();
    print_r($st->fetchAll(PDO::FETCH_ASSOC));

    echo "\n== db() completo (migraciones) ==\n";
    require __DIR__ . '/app/db.php';
    db();
    echo "OK db() sin errores\n";
} catch (Throwable $e) {
    echo "ERROR:\n";
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
}
