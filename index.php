<?php
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/app/db.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/actions.php';

$action = $_GET['action'] ?? null;
if ($action) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || strpos($action, 'export') !== false) {
        handle_action($action);
    }
}

$page = $_GET['page'] ?? 'dashboard';
$user = current_user();
if (!$user && $page !== 'login') redirect('?page=login');
if ($user && $page === 'login') redirect('?page=dashboard');

$valid = ['login','dashboard','operadores','perfil','horas','habilidades','desempeno','velocidad','incidencias','formato','usuarios','admin'];
if (!in_array($page, $valid, true)) $page = 'dashboard';
if (in_array($page, ['usuarios', 'admin'], true) && !is_admin()) $page = 'dashboard';
if ($page === 'usuarios') redirect('?page=admin&tab=usuarios');

$AREA_ACTUAL = strtoupper($_GET['area'] ?? 'ARMG');
if (!isset($AREAS[$AREA_ACTUAL])) $AREA_ACTUAL = 'ARMG';

$isPrint = ($page === 'formato' && isset($_GET['print']));

$items = [
    'dashboard'   => ['speedometer2', 'Dashboard'],
    'operadores'  => ['people-fill', 'Operadores'],
    'desempeno'   => ['graph-up-arrow', 'Desempeño'],
    'horas'       => ['clock-history', 'Horas'],
    'habilidades' => ['stars', 'Habilidades'],
    'velocidad'   => ['stopwatch', 'Velocidad'],
    'incidencias' => ['exclamation-triangle-fill', 'Incidencias'],
];
// Nota: 'formato' ya no aparece en el menú; se conserva como página interna
// porque el botón "Imprimir" del módulo Desempeño la usa para generar el PDF.
if (is_admin()) $items['admin'] = ['shield-lock', 'Administrador'];
$pageTitle = $items[$page][1] ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Saira+Semi+Condensed:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>" rel="stylesheet">
</head>
<body class="<?= $isPrint ? 'print-mode' : '' ?>">
<div class="layout">
  <div class="content">
<?php if ($user && !$isPrint): ?>
    <header class="masthead no-print">
      <div class="brand">
        <img class="brand-logo" src="<?= BASE_URL ?>/assets/img/logo.svg" alt="COSCO SHIPPING">
        <div class="brand-sep"></div>
        <div class="brand-text">
          <b>Competencias Operativas</b>
          <small><?= h(APP_EMPRESA) ?></small>
        </div>
      </div>
      <div class="spacer"></div>
      <div class="user-chip">
        <div class="avatar"><?= h(strtoupper(mb_substr($user['nombre'], 0, 1))) ?></div>
        <div class="user-meta">
          <div style="font-weight:600"><?= h($user['nombre']) ?></div>
          <small class="muted"><?= h(ucfirst($user['rol'])) ?></small>
        </div>
        <form method="post" action="?action=logout" class="d-inline">
          <button class="btn btn-sm btn-outline-secondary" title="Cerrar sesión"><i class="bi bi-box-arrow-right"></i><span class="hide-sm"> Salir</span></button>
        </form>
      </div>
    </header>

    <nav class="topnav no-print" aria-label="Módulos">
      <div class="topnav-inner">
        <?php foreach ($items as $p => [$icon, $label]): ?>
          <?php if ($p === 'admin'): ?>
          <div class="dropdown tn-dropdown">
            <a class="tn-item <?= $page === 'admin' ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="tn-ico"><i class="bi bi-<?= $icon ?>"></i></span>
              <span class="tn-label"><?= $label ?> <i class="bi bi-chevron-down tn-chevron"></i></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-area dropdown-menu-admin">
              <li><h6 class="dropdown-header">Gestión administrativa</h6></li>
              <?php foreach (['usuarios' => ['person-gear', 'Usuarios', 'Gestiona accesos y roles'], 'registros' => ['trash3', 'Borrar registros', 'Eliminación controlada'], 'backup' => ['database-down', 'Backup', 'Exportar o restaurar Excel'], 'auditoria' => ['journal-text', 'Auditoría', 'Historial de cambios']] as $tab => [$tabIcon, $tabLabel, $tabDetail]): ?>
              <li><a class="dropdown-item <?= ($page === 'admin' && ($_GET['tab'] ?? 'usuarios') === $tab) ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?page=admin&tab=<?= $tab ?>">
                <i class="bi bi-<?= $tabIcon ?>"></i><span><b><?= $tabLabel ?></b><small><?= $tabDetail ?></small></span>
              </a></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php elseif (in_array($p, ['horas', 'habilidades', 'desempeno', 'velocidad'], true)): ?>
          <div class="dropdown tn-dropdown">
            <a class="tn-item <?= $page === $p ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="tn-ico"><i class="bi bi-<?= $icon ?>"></i></span>
              <span class="tn-label"><?= $label ?> <i class="bi bi-chevron-down tn-chevron"></i></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-area">
              <li><h6 class="dropdown-header">Elegir área</h6></li>
              <?php foreach ($AREAS as $a => $c): ?>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/index.php?page=<?= $p ?>&area=<?= $a ?>">
                <span class="fw-semibold"><?= h($c['operator_label'] ?? 'Operador ' . $a) ?></span>
                <small class="d-block text-muted"><?= h($c['nombre']) ?></small>
              </a></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php else: ?>
          <a class="tn-item <?= $page === $p ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?page=<?= $p ?>">
            <span class="tn-ico"><i class="bi bi-<?= $icon ?>"></i></span>
            <span class="tn-label"><?= $label ?></span>
          </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </nav>

    <div class="pagebar no-print"><h1><?= h($pageTitle) ?></h1></div>
<?php endif; ?>

<main class="<?= $isPrint ? '' : 'page' ?>">
<?php if (!$isPrint && ($f = get_flash())): ?>
  <div class="alert alert-<?= h($f['type']) ?> alert-floating alert-dismissible fade show no-print" role="alert">
    <div class="d-flex align-items-center gap-2">
      <?php if ($f['type'] === 'danger'): ?>
        <i class="bi bi-trash3-fill fs-5 text-white"></i>
      <?php else: ?>
        <i class="bi bi-check-circle-fill fs-5 text-white"></i>
      <?php endif; ?>
      <div class="text-white"><b><?= h($f['msg']) ?></b></div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close" style="padding: 1.1rem 1rem;"></button>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/views/' . $page . '.php'; ?>
</main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const alert = document.querySelector('.alert-floating.alert-success');
  if (alert && window.bootstrap) setTimeout(() => bootstrap.Alert.getOrCreateInstance(alert).close(), 4500);
});
</script>
</body>
</html>
