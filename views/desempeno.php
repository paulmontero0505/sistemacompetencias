<?php
$areaSel = strtoupper((string)($_GET['area'] ?? ''));
if ($areaSel === '' || !isset($AREAS[$areaSel])):
?>
<div class="d-flex align-items-center gap-2 mb-3">
  <h4 class="mb-0"><i class="bi bi-graph-up-arrow text-primary"></i> Desempeño</h4>
</div>
<p class="text-muted mb-4">Selecciona el área para ver el personal designado a esa grúa.</p>
<div class="row g-3">
  <?php
  $areaIcons = ['ARMG' => 'bi-building', 'QC' => 'bi-crop', 'PC' => 'bi-diagram-3', 'WL' => 'bi-truck-front'];
  foreach ($AREAS as $a => $c): ?>
  <div class="col-md-4">
    <a href="?page=desempeno&area=<?= $a ?>" class="card area-pick text-decoration-none h-100">
      <div class="card-body text-center py-4">
        <i class="bi <?= $areaIcons[$a] ?? 'bi-gear' ?> area-pick-ico"></i>
        <div class="fw-bold fs-5 mt-2"><?= h($c['operator_label'] ?? 'Operador ' . $a) ?></div>
        <div class="text-muted small"><?= h($c['nombre']) ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>
<?php
return;
endif;

$cfg = $AREAS[$AREA_ACTUAL];

// Personal designado a esta grúa según el módulo de Operadores (tipos_grua asignado explícitamente),
// con su estado de capacitación específico para ESTA grúa (independiente de las otras que tenga asignadas).
$st = db()->prepare("SELECT o.*, COALESCE(ge.estado_capacitacion, o.estado_capacitacion) AS estado_area
    FROM operators o
    LEFT JOIN operator_grua_estado ge ON ge.operator_id = o.id AND ge.area = ?
    WHERE o.activo = 1 AND FIND_IN_SET(?, o.tipos_grua)
    ORDER BY o.nombres");
$st->execute([$AREA_ACTUAL, $AREA_ACTUAL]);
$ops = $st->fetchAll();

$totalDesignados = count($ops);
$totalActivos = count(array_filter($ops, fn($o) => (int)$o['activo'] === 1));
$totalEntrenamiento = count(array_filter($ops, fn($o) => $o['estado_area'] === 'ENTRENAMIENTO'));
$totalReentrenamiento = count(array_filter($ops, fn($o) => $o['estado_area'] === 'REENTRENAMIENTO'));

// ── Promedio de habilidad por operador en ESTA grúa ──
// El promedio se calcula como el promedio de los ÚLTIMOS registros por lugar de instrucción.
$lastByOpCtx = []; // [operator_id][ctx] = score (último registro)
$qc = db()->prepare("SELECT s.operator_id op, COALESCE(NULLIF(TRIM(s.contexto),''),'(Sin lugar)') ctx, s.score
    FROM skill_records s
    JOIN (SELECT operator_id, COALESCE(NULLIF(TRIM(contexto),''),'(Sin lugar)') ctx,
                 MAX(CONCAT(fecha, LPAD(id,10,'0'))) mk
          FROM skill_records WHERE area=? GROUP BY operator_id, ctx) t
      ON t.operator_id = s.operator_id
     AND COALESCE(NULLIF(TRIM(s.contexto),''),'(Sin lugar)') = t.ctx
     AND CONCAT(s.fecha, LPAD(s.id,10,'0')) = t.mk
    WHERE s.area=?");
$qc->execute([$AREA_ACTUAL, $AREA_ACTUAL]);
foreach ($qc as $r) { $lastByOpCtx[$r['op']][$r['ctx']] = (float)$r['score']; }

$genByOp = []; // [operator_id] = prom (promedio de últimos por lugar)
foreach ($lastByOpCtx as $op => $byCtx) {
    $genByOp[$op] = round(array_sum($byCtx) / count($byCtx), 1);
}

// ── Horas realizadas por operador y tipo de preparación EN ESTA grúa ──
$horasByOp = []; // [operator_id][TIPO] = minutos ; [operator_id]['ALL'] = total
$qh = db()->prepare("SELECT operator_id op, UPPER(TRIM(tipo_preparacion)) tp, SUM(total_min) m
    FROM hours_records WHERE area=? GROUP BY op, tp");
$qh->execute([$AREA_ACTUAL]);
foreach ($qh as $r) {
    $op = $r['op']; $tp = $r['tp'] ?: 'OTROS'; $m = (int)$r['m'];
    $horasByOp[$op][$tp]  = ($horasByOp[$op][$tp] ?? 0) + $m;
    $horasByOp[$op]['ALL'] = ($horasByOp[$op]['ALL'] ?? 0) + $m;
}

// ── Última medición de velocidad por operador EN ESTA grúa (time record + eficiencia) ──
$speedByOp = []; // [operator_id] = ['seg'=>int, 'ef'=>float, 'fecha'=>string]
$qv = db()->prepare("SELECT s.operator_id op, s.total_seg, s.eficiencia, s.fecha
    FROM speed_records s
    JOIN (SELECT operator_id, MAX(CONCAT(fecha, LPAD(id,10,'0'))) mk
          FROM speed_records WHERE area=? GROUP BY operator_id) t
      ON t.operator_id = s.operator_id AND CONCAT(s.fecha, LPAD(s.id,10,'0')) = t.mk
    WHERE s.area=?");
$qv->execute([$AREA_ACTUAL, $AREA_ACTUAL]);
foreach ($qv as $r) { $speedByOp[$r['op']] = ['seg' => (int)$r['total_seg'], 'ef' => (float)$r['eficiencia'], 'fecha' => $r['fecha']]; }

// Mes/año de la última evaluación de habilidad por operador (para el enlace de impresión)
$lastEvalYm = [];
$qy = db()->prepare("SELECT s.operator_id op, MONTH(s.fecha) mes, YEAR(s.fecha) anio
    FROM skill_records s
    JOIN (SELECT operator_id, MAX(CONCAT(fecha, LPAD(id,10,'0'))) mk
          FROM skill_records WHERE area=? GROUP BY operator_id) t
      ON t.operator_id = s.operator_id AND CONCAT(s.fecha, LPAD(s.id,10,'0')) = t.mk
    WHERE s.area=?");
$qy->execute([$AREA_ACTUAL, $AREA_ACTUAL]);
foreach ($qy as $r) { $lastEvalYm[$r['op']] = ['mes' => (int)$r['mes'], 'anio' => (int)$r['anio']]; }

$colspanRoster = 11;

// Lugares de instrucción de esta grúa (para el filtro): config + los que aparezcan en datos
$ctxLabel = $cfg['skills_ctx_label'] ?? 'Lugar de instrucción';
$lugares  = $cfg['skills_contextos'] ?? [];
$extraCtx = [];
foreach ($lastByOpCtx as $byCtx) {
    foreach ($byCtx as $ctx => $_) {
        if (!in_array($ctx, $lugares, true) && !in_array($ctx, $extraCtx, true)) $extraCtx[] = $ctx;
    }
}
$colLugares = array_values(array_merge($lugares, $extraCtx));

// Niveles de habilidad (independiente del tipo de grúa): bajo <75 · inicial 75–85 · intermedio 86–95 · experto ≥96.
if (!function_exists('desempeno_habclass')) {
    function desempeno_habclass(float $p): string { return $p >= 96 ? 'EXPERTO' : ($p >= 86 ? 'INTERMEDIO' : ($p >= 75 ? 'INICIAL' : 'BAJO')); }
    function desempeno_habbadge(float $p): string {
        $cls = $p >= 96 ? 'success-subtle text-success border border-success-subtle' : ($p >= 86 ? 'primary-subtle text-primary border border-primary-subtle' : ($p >= 75 ? 'warning-subtle text-warning-emphasis border border-warning-subtle' : 'danger-subtle text-danger border border-danger-subtle'));
        return '<span class="badge bg-' . $cls . '">' . number_format($p, 1) . '%</span>';
    }
    // Velocidad (Score time) mantiene la clasificación estándar (80/50)
    function desempeno_scorebadge(float $prom): string {
        $st  = calc_status($prom);
        $cls = $st === 'OPTIMO' ? 'success-subtle text-success border border-success-subtle' : ($st === 'REGULAR' ? 'warning-subtle text-warning-emphasis border border-warning-subtle' : 'danger-subtle text-danger border border-danger-subtle');
        return '<span class="badge bg-' . $cls . '">' . number_format($prom, 1) . '%</span>';
    }
}

// Conteo inicial por nivel (vista "Todos", promedio general por operador).
$cntExperto = $cntIntermedio = $cntInicial = $cntBajo = 0;
$cargosDesignados = [];
$aptitudPorEstado = [
    'ENTRENAMIENTO' => ['aptos' => 0, 'no_aptos' => 0, 'sin_evaluacion' => 0],
    'REENTRENAMIENTO' => ['aptos' => 0, 'no_aptos' => 0, 'sin_evaluacion' => 0],
];
foreach ($ops as $o) {
    $cargo = trim((string)$o['cargo']) ?: 'Sin cargo';
    $cargosDesignados[$cargo] = ($cargosDesignados[$cargo] ?? 0) + 1;
    $estado = $o['estado_area'];
    $score = $genByOp[$o['id']] ?? null;
    if ($score === null) {
        if (isset($aptitudPorEstado[$estado])) $aptitudPorEstado[$estado]['sin_evaluacion']++;
        continue;
    }
    if (isset($aptitudPorEstado[$estado])) {
        $aptitudPorEstado[$estado][skill_es_apto($score, $AREA_ACTUAL) ? 'aptos' : 'no_aptos']++;
    }
    $c = desempeno_habclass($score);
    if ($c === 'EXPERTO') $cntExperto++; elseif ($c === 'INTERMEDIO') $cntIntermedio++; elseif ($c === 'INICIAL') $cntInicial++; else $cntBajo++;
}
ksort($cargosDesignados, SORT_NATURAL | SORT_FLAG_CASE);
?>
<section class="banner-head performance-hero mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
      <span class="banner-badge">DESEMPEÑO</span>
      <h3 class="mb-1 mt-2">Personal designado — <?= h($cfg['nombre']) ?></h3>
      <p class="mb-0 opacity-75">Operadores asignados a esta grúa según el módulo de Operadores. El reporte de desempeño se construirá a partir de Habilidades.</p>
    </div>
    <div class="d-flex flex-column align-items-end gap-2">
      <?php if ($user['rol'] !== 'visita'): ?>
      <button type="button" class="btn btn-light btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalBulkEstado">
        <i class="bi bi-people-fill"></i> Asignar en bloque
      </button>
      <?php endif; ?>
      <a class="btn btn-outline-light btn-sm fw-semibold" target="_blank" rel="noopener"
         href="?page=formato&farea=<?= h($AREA_ACTUAL) ?>&blank=1&print=1"
         title="Abrir plantilla vacía para imprimir">
        <i class="bi bi-file-earmark-plus"></i> Imprimir formato vacío
      </a>
    </div>
  </div>
</section>

<div class="performance-stats mb-3">
  <div class="stat-tile stat-tile-primary">
    <div class="stat-label">Total designados</div>
    <div class="stat-value text-primary"><?= $totalDesignados ?></div>
    <?php foreach ($cargosDesignados as $cargo => $cantidad): ?>
      <div class="stat-level-detail" title="<?= h($cargo) ?>"><span><?= h($cargo) ?></span><b><?= $cantidad ?></b></div>
    <?php endforeach; ?>
  </div>
  <div class="stat-tile stat-tile-training">
    <div class="stat-label">Entrenamiento</div>
    <div class="stat-value text-warning-emphasis"><?= $totalEntrenamiento ?></div>
    <div class="stat-level-detail"><span>Aptos</span><b id="js-training-aptos"><?= $aptitudPorEstado['ENTRENAMIENTO']['aptos'] ?></b></div>
    <div class="stat-level-detail"><span>No aptos</span><b id="js-training-no-aptos"><?= $aptitudPorEstado['ENTRENAMIENTO']['no_aptos'] ?></b></div>
    <?php if ($aptitudPorEstado['ENTRENAMIENTO']['sin_evaluacion']): ?><div class="stat-level-detail"><span>Sin evaluación</span><b id="js-training-pending"><?= $aptitudPorEstado['ENTRENAMIENTO']['sin_evaluacion'] ?></b></div><?php endif; ?>
  </div>
  <div class="stat-tile stat-tile-retraining">
    <div class="stat-label">Reentrenamiento</div>
    <div class="stat-value"><?= $totalReentrenamiento ?></div>
    <div class="stat-level-detail"><span>Aptos</span><b id="js-retraining-aptos"><?= $aptitudPorEstado['REENTRENAMIENTO']['aptos'] ?></b></div>
    <div class="stat-level-detail"><span>No aptos</span><b id="js-retraining-no-aptos"><?= $aptitudPorEstado['REENTRENAMIENTO']['no_aptos'] ?></b></div>
    <?php if ($aptitudPorEstado['REENTRENAMIENTO']['sin_evaluacion']): ?><div class="stat-level-detail"><span>Sin evaluación</span><b id="js-retraining-pending"><?= $aptitudPorEstado['REENTRENAMIENTO']['sin_evaluacion'] ?></b></div><?php endif; ?>
  </div>
  <div class="stat-tile stat-tile-optimal">
    <div class="stat-label">Aptos <small class="text-muted">(≥86%)</small></div>
    <div class="stat-value text-success" id="js-card-aptos"><?= $cntExperto + $cntIntermedio ?></div>
    <div class="stat-level-detail"><span>Nivel Experto (96–100%)</span><b id="js-card-experto"><?= $cntExperto ?></b></div>
    <div class="stat-level-detail"><span>Nivel Intermedio (86–95%)</span><b id="js-card-intermedio"><?= $cntIntermedio ?></b></div>
  </div>
  <div class="stat-tile stat-tile-low">
    <div class="stat-label">No aptos <small class="text-muted">(&lt;86%)</small></div>
    <div class="stat-value text-danger" id="js-card-no-aptos"><?= $cntInicial + $cntBajo ?></div>
    <div class="stat-level-detail"><span>Nivel Inicial (75–85%)</span><b id="js-card-inicial"><?= $cntInicial ?></b></div>
    <div class="stat-level-detail"><span>Nivel bajo (&lt;75%)</span><b id="js-card-bajo"><?= $cntBajo ?></b></div>
  </div>
</div>

<div class="card performance-roster">
  <div class="card-header performance-filters">
    <div class="performance-filter-grid">
      <div class="input-group input-group-sm performance-search">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input class="form-control" id="js-desemp-search" placeholder="Filtrar...">
      </div>
      <div class="performance-filter-field">
        <small class="text-muted fw-semibold text-uppercase text-nowrap" style="font-size:.7rem">Habilidad por <?= h(strtolower($ctxLabel)) ?>:</small>
        <select class="form-select form-select-sm" id="js-lugar-filter">
          <option value="all">Todos</option>
          <?php foreach ($colLugares as $i => $lg): ?>
            <option value="<?= $i ?>"><?= h($lg) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="performance-filter-field">
        <small class="text-muted fw-semibold text-uppercase text-nowrap" style="font-size:.7rem">Estado:</small>
        <select class="form-select form-select-sm" id="js-estado-filter">
          <option value="all">Todos</option>
          <option value="ENTRENAMIENTO">Entrenamiento</option>
          <option value="REENTRENAMIENTO">Reentrenamiento</option>
        </select>
      </div>
      <div class="performance-filter-field">
        <small class="text-muted fw-semibold text-uppercase text-nowrap" style="font-size:.7rem">Clasificación:</small>
        <select class="form-select form-select-sm" id="js-clase-filter">
          <option value="all">Todos</option>
          <option value="EXPERTO">Nivel Experto (96–100%)</option>
          <option value="INTERMEDIO">Nivel Intermedio (86–95%)</option>
          <option value="INICIAL">Nivel Inicial (75–85%)</option>
          <option value="BAJO">Nivel bajo (&lt;75%)</option>
        </select>
      </div>
      <div class="performance-filter-field">
        <small class="text-muted fw-semibold text-uppercase text-nowrap" style="font-size:.7rem">Horas por:</small>
        <select class="form-select form-select-sm" id="js-horas-filter">
          <option value="all">Todos</option>
          <?php foreach ($TIPOS_PREPARACION as $tp): ?>
            <option value="<?= h(strtolower($tp)) ?>"><?= h($tp) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
  <div class="table-responsive performance-table-wrap">
    <table class="table table-sm table-hover align-middle mb-0" id="js-desemp-table">
      <thead class="sticky-top"><tr>
        <th class="text-center">N°</th>
        <th>Código</th><th>DNI</th><th>Apellidos y nombres</th><th>Cargo</th>
        <th class="text-center">Prom. habilidad <span id="js-hab-th-tag" class="opacity-75 small fw-normal"></span></th>
        <th class="text-center">Total horas <span id="js-horas-th-tag" class="opacity-75 small fw-normal"></span></th>
        <th class="text-center" title="Tiempo total de la última medición de velocidad">Time record</th>
        <th class="text-center" title="Eficiencia (%) de la última medición de velocidad">Score time</th>
        <th>Estado en <?= h($AREA_ACTUAL) ?></th>
        <th class="text-center">Acciones</th>
      </tr></thead>
      <tbody>
      <?php foreach ($ops as $i => $o): ?>
        <tr data-estado="<?= h($o['estado_area']) ?>">
          <td class="text-center"><?= $i + 1 ?></td>
          <td><?= h($o['codigo']) ?></td>
          <td><?= h($o['dni']) ?></td>
          <td><a href="?page=perfil&id=<?= $o['id'] ?>" class="fw-semibold text-decoration-none"><?= h($o['nombres']) ?></a></td>
          <td class="small"><?= h($o['cargo']) ?></td>
          <?php $g = $genByOp[$o['id']] ?? null; ?>
          <td class="text-center js-hab-cell"
              data-hab-all="<?= $g !== null ? number_format($g, 1, '.', '') : '' ?>"
              <?php foreach ($colLugares as $ci => $lg): $lv = $lastByOpCtx[$o['id']][$lg] ?? null; ?>
                data-hab-<?= $ci ?>="<?= $lv !== null ? number_format($lv, 1, '.', '') : '' ?>"
              <?php endforeach; ?>>
            <?= $g !== null ? desempeno_habbadge($g) : '<span class="text-muted">—</span>' ?>
          </td>
          <?php
            $hb = $horasByOp[$o['id']] ?? [];
            $hData = [
              'all'           => fmt_minutes($hb['ALL'] ?? 0),
              'training'      => fmt_minutes($hb['TRAINING'] ?? 0),
              'difusion'      => fmt_minutes($hb['DIFUSION'] ?? 0),
              'certificacion' => fmt_minutes($hb['CERTIFICACION'] ?? 0),
            ];
          ?>
          <td class="text-center js-horas-cell"
              data-all="<?= h($hData['all']) ?>" data-training="<?= h($hData['training']) ?>"
              data-difusion="<?= h($hData['difusion']) ?>" data-certificacion="<?= h($hData['certificacion']) ?>">
            <span class="fw-semibold"><?= h($hData['all']) ?></span> <small class="text-muted">h</small>
          </td>
          <?php $sp = $speedByOp[$o['id']] ?? null; ?>
          <td class="text-center">
            <?php if ($sp): ?>
              <span class="fw-semibold"><?= h(fmt_seconds($sp['seg'])) ?></span>
              <div class="text-muted" style="font-size:.66rem;line-height:1.2;margin-top:2px"><?= h($sp['fecha']) ?></div>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td class="text-center">
            <?= $sp ? desempeno_scorebadge($sp['ef']) : '<span class="text-muted">—</span>' ?>
          </td>
          <td>
            <span class="badge bg-<?= $o['estado_area'] === 'REENTRENAMIENTO' ? 'success-subtle text-success border border-success-subtle' : 'warning-subtle text-warning-emphasis border border-warning-subtle' ?> px-2 py-2">
              <?= h($o['estado_area']) ?>
            </span>
          </td>
          <td class="text-center text-nowrap">
            <?php $ym = $lastEvalYm[$o['id']] ?? ['mes' => (int)date('n'), 'anio' => (int)date('Y')]; ?>
            <a class="btn btn-sm btn-outline-primary py-0 px-2" target="_blank" rel="noopener" data-print-format
               href="?page=formato&operator_id=<?= $o['id'] ?>&farea=<?= h($AREA_ACTUAL) ?>&mes=<?= $ym['mes'] ?>&anio=<?= $ym['anio'] ?>&snap=1&print=1&htipo=ALL"
               title="Imprimir reporte (PDF)"><i class="bi bi-printer"></i></a>
            <?php if (is_admin()): ?>
            <form method="post" action="?action=desempeno_unassign" class="d-inline"
                  onsubmit="return confirm('¿Desasignar a <?= h(addslashes($o['nombres'])) ?> de <?= h($AREA_ACTUAL) ?>? No se borran sus evaluaciones; solo deja de aparecer en esta grúa.')">
              <input type="hidden" name="area" value="<?= h($AREA_ACTUAL) ?>">
              <input type="hidden" name="operator_id" value="<?= $o['id'] ?>">
              <button class="btn btn-sm btn-outline-danger py-0 px-2" title="Desasignar de <?= h($AREA_ACTUAL) ?>"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$ops): ?>
        <tr><td colspan="<?= $colspanRoster ?>" class="text-center text-muted py-4">Sin personal designado a <?= $AREA_ACTUAL ?> todavía. Asígnalo desde el módulo de Operadores (Tipo(s) de grúa).</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Asignar en bloque -->
<div class="modal fade" id="modalBulkEstado" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content bulk-state-modal">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-people-fill"></i> Asignar estado en bloque — <?= h($cfg['nombre']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="?action=desempeno_estado_save">
        <div class="modal-body">
          <input type="hidden" name="area" value="<?= h($AREA_ACTUAL) ?>">
          <p class="bulk-state-intro">Selecciona operadores de <strong><?= h($cfg['nombre']) ?></strong> y asigna su estado de capacitación.</p>
          <div class="bulk-state-toolbar">
            <div class="input-group input-group-sm">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="search" class="form-control" id="js-bulk-operator-search" placeholder="Buscar por nombre, código o DNI..." autocomplete="off">
            </div>
            <div class="bulk-state-field">
              <label for="js-bulk-status">Estado *</label>
              <select name="estado_capacitacion" id="js-bulk-status" class="form-select form-select-sm" required>
                <option value="ENTRENAMIENTO">ENTRENAMIENTO</option>
                <option value="REENTRENAMIENTO">REENTRENAMIENTO</option>
              </select>
            </div>
          </div>
          <div class="bulk-state-summary">
            <label class="form-check mb-0">
              <input class="form-check-input" type="checkbox" id="js-bulk-select-all">
              <span class="form-check-label">Seleccionar visibles</span>
            </label>
            <span id="js-bulk-selected-count" aria-live="polite">Ningún operador seleccionado</span>
          </div>
          <div class="bulk-state-list" id="js-bulk-operator-list">
            <?php foreach ($ops as $o): ?>
              <label class="bulk-state-person" data-search="<?= h(mb_strtolower($o['nombres'] . ' ' . $o['codigo'] . ' ' . $o['dni'])) ?>">
                <input class="form-check-input js-bulk-operator" type="checkbox" name="operator_id[]" value="<?= $o['id'] ?>">
                <span class="bulk-state-person__icon"><i class="bi bi-person"></i></span>
                <span class="bulk-state-person__body">
                  <strong><?= h($o['nombres']) ?></strong>
                  <small><?= h($o['codigo']) ?> · DNI <?= h($o['dni']) ?> · <?= h($o['cargo']) ?></small>
                </span>
                <span class="bulk-state-person__current <?= $o['estado_area'] === 'REENTRENAMIENTO' ? 'is-retraining' : '' ?>"><?= h($o['estado_area']) ?></span>
              </label>
            <?php endforeach; ?>
            <p class="bulk-state-empty d-none mb-0" id="js-bulk-empty">No se encontraron operadores en esta grúa.</p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-cosco btn-sm" id="js-bulk-submit" disabled><i class="bi bi-save"></i> Aplicar a los seleccionados</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const table = document.getElementById('js-desemp-table');
  if (!table) return;
  const rows = Array.from(table.querySelectorAll('tbody tr')).filter(tr => tr.querySelector('.js-hab-cell'));
  const tableLabels = Array.from(table.querySelectorAll('thead th')).map(th => th.childNodes[0].textContent.trim());
  rows.forEach(row => row.querySelectorAll('td').forEach((cell, index) => cell.dataset.label = tableLabels[index] || 'Dato'));

  // Helper: marca el botón activo dentro de un grupo (ya no se usa para los selectores, pero lo dejamos por si acaso)

  // Badge de habilidad con los cuatro niveles de competencia.
  const habBadge = (v) => {
    if (v === undefined || v === '' || isNaN(parseFloat(v))) return '<span class="text-muted">—</span>';
    const n = parseFloat(v);
    const cls = n >= 96 ? 'success-subtle text-success border border-success-subtle' : (n >= 86 ? 'primary-subtle text-primary border border-primary-subtle' : (n >= 75 ? 'warning-subtle text-warning-emphasis border border-warning-subtle' : 'danger-subtle text-danger border border-danger-subtle'));
    return '<span class="badge bg-' + cls + '">' + n.toFixed(1) + '%</span>';
  };

  const lugarGrp  = document.getElementById('js-lugar-filter');
  const estadoGrp = document.getElementById('js-estado-filter');
  const claseGrp  = document.getElementById('js-clase-filter');
  const horasGrp  = document.getElementById('js-horas-filter');
  const search    = document.getElementById('js-desemp-search');
  const habTag    = document.getElementById('js-hab-th-tag');
  const cardA = document.getElementById('js-card-experto');
  const cardP = document.getElementById('js-card-intermedio');
  const cardBa = document.getElementById('js-card-inicial');
  const cardB = document.getElementById('js-card-bajo');
  const cardAptos = document.getElementById('js-card-aptos');
  const cardNoAptos = document.getElementById('js-card-no-aptos');
  const trainingAptos = document.getElementById('js-training-aptos');
  const trainingNoAptos = document.getElementById('js-training-no-aptos');
  const trainingPending = document.getElementById('js-training-pending');
  const retrainingAptos = document.getElementById('js-retraining-aptos');
  const retrainingNoAptos = document.getElementById('js-retraining-no-aptos');
  const retrainingPending = document.getElementById('js-retraining-pending');

  let lugarKey = 'all', estadoSel = 'all', claseSel = 'all';

  // Clasificación de habilidad según el valor mostrado.
  const claseDe = (val) => {
    if (val === undefined || val === '' || isNaN(parseFloat(val))) return null;
    const n = parseFloat(val);
    return n >= 96 ? 'EXPERTO' : (n >= 86 ? 'INTERMEDIO' : (n >= 75 ? 'INICIAL' : 'BAJO'));
  };

  const applyFilters = () => {
    const dsKey = lugarKey === 'all' ? 'habAll' : ('hab' + lugarKey);
    const txt = (search.value || '').toLowerCase().trim();
    let cA = 0, cP = 0, cBa = 0, cB = 0;
    const aptitudeByStatus = {
      ENTRENAMIENTO: { aptos: 0, noAptos: 0, pending: 0 },
      REENTRENAMIENTO: { aptos: 0, noAptos: 0, pending: 0 },
    };

    rows.forEach(tr => {
      // 1) Actualiza el promedio de habilidad según el lugar seleccionado
      const cell = tr.querySelector('.js-hab-cell');
      const val = cell.dataset[dsKey];
      cell.innerHTML = habBadge(val);
      const clase = claseDe(val);

      // 2) Visibilidad por estado + búsqueda + clasificación
      const okBase   = (estadoSel === 'all' || tr.dataset.estado === estadoSel) && (!txt || tr.textContent.toLowerCase().includes(txt));
      const okClase  = claseSel === 'all' || clase === claseSel;
      tr.style.display = (okBase && okClase) ? '' : 'none';

      // 3) Conteo de tarjetas: según estado+búsqueda (no según la clasificación elegida)
      if (okBase && clase) {
        if (clase === 'EXPERTO') cA++; else if (clase === 'INTERMEDIO') cP++; else if (clase === 'INICIAL') cBa++; else cB++;
      }
      if (okBase && aptitudeByStatus[tr.dataset.estado]) {
        if (!clase) aptitudeByStatus[tr.dataset.estado].pending++;
        else if (clase === 'EXPERTO' || clase === 'INTERMEDIO') aptitudeByStatus[tr.dataset.estado].aptos++;
        else aptitudeByStatus[tr.dataset.estado].noAptos++;
      }
    });

    if (cardA) cardA.textContent = cA;
    if (cardP) cardP.textContent = cP;
    if (cardBa) cardBa.textContent = cBa;
    if (cardB) cardB.textContent = cB;
    if (cardAptos) cardAptos.textContent = cA + cP;
    if (cardNoAptos) cardNoAptos.textContent = cBa + cB;
    if (trainingAptos) trainingAptos.textContent = aptitudeByStatus.ENTRENAMIENTO.aptos;
    if (trainingNoAptos) trainingNoAptos.textContent = aptitudeByStatus.ENTRENAMIENTO.noAptos;
    if (trainingPending) trainingPending.textContent = aptitudeByStatus.ENTRENAMIENTO.pending;
    if (retrainingAptos) retrainingAptos.textContent = aptitudeByStatus.REENTRENAMIENTO.aptos;
    if (retrainingNoAptos) retrainingNoAptos.textContent = aptitudeByStatus.REENTRENAMIENTO.noAptos;
    if (retrainingPending) retrainingPending.textContent = aptitudeByStatus.REENTRENAMIENTO.pending;
  };

  // Filtro por lugar de instrucción → cambia el promedio mostrado
  if (lugarGrp) lugarGrp.addEventListener('change', (e) => {
    lugarKey = e.target.value;
    const opt = e.target.options[e.target.selectedIndex];
    if (habTag) habTag.textContent = (lugarKey === 'all') ? '' : '(' + opt.textContent.trim() + ')';
    applyFilters();
  });

  // Filtro por estado (Entrenamiento / Reentrenamiento)
  if (estadoGrp) estadoGrp.addEventListener('change', (e) => {
    estadoSel = e.target.value;
    applyFilters();
  });

  // Filtro por clasificación.
  if (claseGrp) claseGrp.addEventListener('change', (e) => {
    claseSel = e.target.value;
    applyFilters();
  });

  // Modal de asignación en bloque: búsqueda y selección del listado de la grúa actual.
  const bulkModal = document.getElementById('modalBulkEstado');
  if (bulkModal) {
    const bulkSearch = document.getElementById('js-bulk-operator-search');
    const bulkAll = document.getElementById('js-bulk-select-all');
    const bulkPeople = Array.from(bulkModal.querySelectorAll('.js-bulk-operator'));
    const bulkCount = document.getElementById('js-bulk-selected-count');
    const bulkSubmit = document.getElementById('js-bulk-submit');
    const bulkEmpty = document.getElementById('js-bulk-empty');
    const syncBulk = () => {
      const selected = bulkPeople.filter(input => input.checked).length;
      const visible = bulkPeople.filter(input => !input.closest('.bulk-state-person').classList.contains('d-none'));
      bulkCount.textContent = selected ? selected + (selected === 1 ? ' operador seleccionado' : ' operadores seleccionados') : 'Ningún operador seleccionado';
      bulkSubmit.disabled = selected === 0;
      bulkAll.checked = visible.length > 0 && visible.every(input => input.checked);
      bulkAll.indeterminate = visible.some(input => input.checked) && !bulkAll.checked;
    };
    const filterBulk = () => {
      const query = (bulkSearch.value || '').trim().toLocaleLowerCase();
      let visible = 0;
      bulkPeople.forEach(input => {
        const person = input.closest('.bulk-state-person');
        const match = !query || person.dataset.search.includes(query);
        person.classList.toggle('d-none', !match);
        if (match) visible++;
      });
      bulkEmpty.classList.toggle('d-none', visible > 0);
      syncBulk();
    };
    bulkSearch.addEventListener('input', filterBulk);
    bulkAll.addEventListener('change', () => {
      bulkPeople.forEach(input => { if (!input.closest('.bulk-state-person').classList.contains('d-none')) input.checked = bulkAll.checked; });
      syncBulk();
    });
    bulkPeople.forEach(input => input.addEventListener('change', syncBulk));
    bulkModal.addEventListener('hidden.bs.modal', () => {
      bulkSearch.value = '';
      bulkPeople.forEach(input => { input.checked = false; input.closest('.bulk-state-person').classList.remove('d-none'); });
      bulkEmpty.classList.add('d-none');
      syncBulk();
    });
    syncBulk();
  }

  if (search) search.addEventListener('input', applyFilters);

  // Filtro interactivo de horas por tipo de preparación (independiente)
  if (horasGrp) {
    const hcells = document.querySelectorAll('.js-horas-cell');
    const htag = document.getElementById('js-horas-th-tag');
    horasGrp.addEventListener('change', (e) => {
      const t = e.target.value;
      const opt = e.target.options[e.target.selectedIndex];
      hcells.forEach(c => { const s = c.querySelector('span'); if (s) s.textContent = c.dataset[t] ?? '0:00'; });
      if (htag) htag.textContent = (t === 'all') ? '' : '(' + opt.textContent.trim() + ')';
      document.querySelectorAll('[data-print-format]').forEach(link => {
        const url = new URL(link.href);
        url.searchParams.set('htipo', t.toUpperCase());
        link.href = url.toString();
      });
    });
  }

  applyFilters();
});
</script>
