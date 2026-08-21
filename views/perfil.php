<?php
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT * FROM operators WHERE id=?");
$st->execute([$id]);
$op = $st->fetch();
if (!$op) { echo '<div class="alert alert-warning">Operador no encontrado</div>'; return; }

$q = fn($sql) => db()->prepare($sql);

$horasTot = $q("SELECT area, SUM(total_min) t, COUNT(*) n FROM hours_records WHERE operator_id=? GROUP BY area");
$horasTot->execute([$id]); $horasTot = $horasTot->fetchAll();

$skillSerie = $q("SELECT fecha, area, score, status FROM skill_records WHERE operator_id=? ORDER BY fecha, id");
$skillSerie->execute([$id]); $skillSerie = $skillSerie->fetchAll();

$speedSerie = $q("SELECT fecha, area, total_seg, estimado_seg, eficiencia, status FROM speed_records WHERE operator_id=? ORDER BY fecha, id");
$speedSerie->execute([$id]); $speedSerie = $speedSerie->fetchAll();

$incs = $q("SELECT * FROM incidents WHERE operator_id=? ORDER BY fecha DESC");
$incs->execute([$id]); $incs = $incs->fetchAll();

$ultSkill = end($skillSerie) ?: null;
$totalMin = array_sum(array_column($horasTot, 't'));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-person-lines-fill text-primary"></i> <?= h($op['nombres']) ?></h4>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-primary" href="?page=formato&operator_id=<?= $op['id'] ?>"><i class="bi bi-printer"></i> Formato de evaluación</a>
    <a class="btn btn-sm btn-outline-secondary" href="?page=operadores"><i class="bi bi-arrow-left"></i> Volver</a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card kpi-card p-3">
    <div class="kpi-label">DNI / Código</div><div class="kpi-value fs-4"><?= h($op['dni']) ?> <span class="text-muted fs-6"><?= h($op['codigo']) ?></span></div>
    <div class="small text-muted"><?= h($op['cargo']) ?> · Ingreso: <?= h($op['fecha_ingreso'] ?: '—') ?></div></div></div>
  <div class="col-md-3"><div class="card kpi-card green p-3">
    <div class="kpi-label">Horas acumuladas</div><div class="kpi-value"><?= fmt_minutes((int)$totalMin) ?></div>
    <div class="small text-muted"><?php foreach ($horasTot as $ht) echo $ht['area'] . ': ' . fmt_minutes((int)$ht['t']) . ' '; ?></div></div></div>
  <div class="col-md-3"><div class="card kpi-card amber p-3">
    <div class="kpi-label">Última habilidad</div>
    <div class="kpi-value"><?= $ultSkill ? number_format((float)$ultSkill['score'], 1) . '%' : '—' ?></div>
    <div class="small"><?= $ultSkill ? status_badge($ultSkill['status']) . ' <span class="text-muted">' . h($ultSkill['fecha']) . '</span>' : 'Sin evaluaciones' ?></div></div></div>
  <div class="col-md-3"><div class="card kpi-card red p-3">
    <div class="kpi-label">Incidencias</div><div class="kpi-value"><?= count($incs) ?></div>
    <div class="small text-muted"><?= count(array_filter($incs, fn($i) => $i['estado'] !== 'CERRADA')) ?> abiertas</div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6"><div class="card h-100">
    <div class="card-header"><i class="bi bi-graph-up"></i> Evolución de habilidad (%)</div>
    <div class="card-body"><canvas id="chSkill" height="120"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card h-100">
    <div class="card-header"><i class="bi bi-stopwatch"></i> Eficiencia en maniobras (%)</div>
    <div class="card-body"><canvas id="chSpeed" height="120"></canvas></div></div></div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-exclamation-triangle"></i> Historial de incidencias</div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0 align-middle">
      <thead><tr><th>Fecha</th><th>Área</th><th>Equipo</th><th>Tipo</th><th>Severidad</th><th>Descripción</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach ($incs as $i): ?>
        <tr>
          <td class="text-nowrap"><?= h($i['fecha']) ?></td>
          <td><span class="badge bg-primary"><?= h($i['area']) ?></span></td>
          <td class="small"><?= h($i['equipo']) ?></td>
          <td class="small"><?= h($i['tipo']) ?></td>
          <td><?= status_badge($i['severidad']) ?></td>
          <td class="small"><?= h($i['descripcion']) ?></td>
          <td><?= status_badge($i['estado']) ?></td>
        </tr>
      <?php endforeach; if (!$incs): ?>
        <tr><td colspan="7" class="text-center text-muted py-3">Sin incidencias — buen historial ✔</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
  const sk = <?= json_encode(array_map(fn($r) => ['x' => $r['fecha'], 'y' => (float)$r['score'], 'a' => $r['area']], $skillSerie)) ?>;
  const sp = <?= json_encode(array_map(fn($r) => ['x' => $r['fecha'], 'y' => (float)$r['eficiencia'], 'a' => $r['area']], $speedSerie)) ?>;
  const byArea = (data) => {
    const colors = { ARMG: '#065F46', QC: '#34D399', PC: '#10B981' };
    const groups = {};
    data.forEach(p => (groups[p.a] = groups[p.a] || []).push(p));
    return Object.entries(groups).map(([a, pts]) => ({
      label: a, data: pts.map(p => ({ x: p.x, y: p.y })),
      borderColor: colors[a], backgroundColor: colors[a], tension: .25
    }));
  };
  const opts = { scales: { y: { beginAtZero: true, max: 110 }, x: { type: 'category' } } };
  new Chart(document.getElementById('chSkill'), { type: 'line', data: { datasets: byArea(sk) }, options: opts });
  new Chart(document.getElementById('chSpeed'), { type: 'line', data: { datasets: byArea(sp) }, options: opts });
});
</script>
