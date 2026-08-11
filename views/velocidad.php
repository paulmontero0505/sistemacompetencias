<?php
$areaSel = strtoupper((string)($_GET['area'] ?? ''));
if ($areaSel === '' || !isset($AREAS[$areaSel])):
?>
<div class="d-flex align-items-center gap-2 mb-3">
  <h4 class="mb-0"><i class="bi bi-stopwatch text-primary"></i> Velocidad / Duración de maniobras</h4>
</div>
<p class="text-muted mb-4">Selecciona el área para ver y registrar mediciones de velocidad.</p>
<div class="row g-3">
  <?php
  $areaIcons = ['ARMG' => 'bi-building', 'QC' => 'bi-crop', 'PC' => 'bi-diagram-3', 'WL' => 'bi-truck-front'];
  foreach ($AREAS as $a => $c): ?>
  <div class="col-md-4">
    <a href="?page=velocidad&area=<?= $a ?>" class="card area-pick text-decoration-none h-100">
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
$ops = operadores_activos($AREA_ACTUAL);
$instructores = db()->query("SELECT nombre FROM users WHERE rol IN ('instructor','supervisor') AND activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$speed_lugares = lugares_con_personalizados($cfg['speed_lugares'], $AREA_ACTUAL);
$speed_contextos = contextos_con_personalizados($cfg['speed_contextos'], $AREA_ACTUAL);
$st = db()->prepare("SELECT s.*, o.nombres, o.cargo FROM speed_records s JOIN operators o ON o.id=s.operator_id
                     WHERE s.area=? ORDER BY s.fecha DESC, s.id DESC LIMIT 300");
$st->execute([$AREA_ACTUAL]);
$rows = $st->fetchAll();
$opsById = [];
foreach ($ops as $o) $opsById[(int)$o['id']] = $o;
foreach ($rows as $r) {
    $opId = (int)$r['operator_id'];
    if ($opId && !isset($opsById[$opId])) {
        $opsById[$opId] = ['id' => $opId, 'nombres' => $r['nombres']];
    }
}
$ops = array_values($opsById);

$totalRegistros = count($rows);
$eficienciaSuma = 0;
$totalOptimo = 0;
$operadoresSet = [];
foreach ($rows as $r) {
    $eficienciaSuma += (float)$r['eficiencia'];
    if ($r['status'] === 'OPTIMO') $totalOptimo++;
    $operadoresSet[$r['operator_id']] = true;
}
$eficienciaPromedio = $totalRegistros > 0 ? round($eficienciaSuma / $totalRegistros, 1) : 0;
$opsEvaluados = count($operadoresSet);
?>
<div class="banner-head mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
      <span class="banner-badge">VELOCIDAD · DURACIÓN</span>
      <h3 class="mb-1 mt-2">Velocidad / Duración de maniobras — <?= h($cfg['nombre']) ?></h3>
      <p class="mb-0 opacity-75">Registro de tiempos de ciclo y eficiencia en maniobras por operador.</p>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-light btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalNuevoVelocidad">
        <i class="bi bi-plus-circle"></i> Nueva medición
      </button>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="stat-tile">
    <div class="stat-label">Total mediciones</div>
    <div class="stat-value text-primary" id="js-stat-total"><?= $totalRegistros ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-tile">
    <div class="stat-label">Eficiencia promedio</div>
    <div class="stat-value text-success" id="js-stat-eficiencia"><?= $eficienciaPromedio ?>%</div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-tile">
    <div class="stat-label">Estado Óptimo</div>
    <div class="stat-value text-warning-emphasis" id="js-stat-optimo"><?= $totalOptimo ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-tile">
    <div class="stat-label">Operadores evaluados</div>
    <div class="stat-value" id="js-stat-ops"><?= $opsEvaluados ?></div></div></div>
</div>

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="bi bi-table"></i> Mediciones <?= $AREA_ACTUAL ?></span>
    <div class="input-group input-group-sm" style="max-width:280px">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input class="form-control" id="js-table-filter" placeholder="Buscar por operador o lugar...">
    </div>
  </div>
  <div class="table-responsive" style="max-height:65vh">
    <table class="table table-sm table-hover align-middle mb-0 js-filterable">
      <thead class="sticky-top">
        <tr>
          <th>Fecha</th>
          <th>Operador</th>
          <th>Tipo</th>
          <th>Lugar</th>
          <th>Total</th>
          <th>Estimado</th>
          <th>Eficiencia</th>
          <th>Status</th>
          <th>Observaciones</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): $fases = json_decode($r['fases'] ?? '[]', true) ?: []; ?>
        <tr data-status="<?= h($r['status']) ?>" data-eficiencia="<?= (float)$r['eficiencia'] ?>" data-op="<?= (int)$r['operator_id'] ?>" title="<?= h($r['observaciones']) ?>">
          <td class="text-nowrap"><?= h($r['fecha']) ?></td>
          <td class="small fw-semibold"><?= h($r['nombres']) ?></td>
          <td class="small"><?= h($r['tipo_capacitacion']) ?></td>
          <td class="small"><?= h($r['lugar']) ?></td>
          <td><b><?= fmt_seconds((int)$r['total_seg']) ?></b></td>
          <td><?= fmt_seconds((int)$r['estimado_seg']) ?></td>
          <td><b><?= number_format((float)$r['eficiencia'], 1) ?>%</b></td>
          <td><?= status_badge($r['status']) ?></td>
          <td class="small text-muted" style="max-width: 150px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?= h($r['observaciones']) ?>"><?= h($r['observaciones']) ?></td>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 me-1" title="Editar" onclick="editSpeed(this)"
              data-eval="<?= h(json_encode(array_merge($r, ['fases_arr' => $fases]))) ?>">
              <i class="bi bi-pencil"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary py-0" data-bs-toggle="modal" data-bs-target="#modalVerVelocidad" title="Ver"
              data-ver='<?= h(json_encode([
                  'Fecha' => $r['fecha'],
                  'Operador' => $r['nombres'],
                  'Cargo' => $r['cargo'] ?? '',
                  'Tipo' => $r['tipo_capacitacion'],
                  'Lugar / Zona' => $r['lugar'],
                  'Contexto' => $r['contexto'],
                  'Movimientos' => $r['movimientos'] ?? '—',
                  'Eficiencia' => number_format((float)$r['eficiencia'], 1) . '%',
                  'Estado' => $r['status'],
                  'Fases' => implode(', ', array_map(fn($f) => $f['f'] . ': ' . fmt_seconds((int)$f['s']), $fases)),
                  'Total' => fmt_seconds((int)$r['total_seg']),
                  'Estimado' => fmt_seconds((int)$r['estimado_seg']),
                  'Instructor' => $r['evaluador'],
                  'Observaciones' => $r['observaciones'],
              ])) ?>'><i class="bi bi-eye"></i></button>
            <form method="post" action="?action=speed_delete" class="d-inline" onsubmit="return confirm('¿Eliminar medición?')">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger py-0" title="Eliminar"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">Sin mediciones para <?= $AREA_ACTUAL ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Nueva medición -->
<div class="modal fade" id="modalNuevoVelocidad" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nueva medición — <?= h($cfg['nombre']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="?action=speed_save" id="speed-form">
        <div class="modal-body">
          <input type="hidden" name="area" value="<?= $AREA_ACTUAL ?>">
          <input type="hidden" name="id" value="">
          <div class="mb-2"><label class="form-label small">Operador *</label>
            <select name="operator_id" class="form-select form-select-sm js-op-select" required>
              <option value="">— Seleccionar —</option>
              <?php foreach ($ops as $o): ?><option value="<?= $o['id'] ?>"><?= h($o['nombres']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="row g-2 mb-2">
            <div class="col-12"><label class="form-label small">Fecha *</label><input type="date" name="fecha" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small">Lugar / zona</label>
              <select class="form-select form-select-sm js-lugar-select" name="lugar" data-field-name="lugar" data-area="<?= h($AREA_ACTUAL) ?>">
                <?php foreach ($speed_lugares as $l): ?><option value="<?= h($l) ?>"><?= h($l) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small"><?= h($cfg['speed_ctx_label']) ?></label>
              <select class="form-select form-select-sm js-context-select" name="contexto" data-field-name="contexto" data-area="<?= h($AREA_ACTUAL) ?>">
                <?php foreach ($speed_contextos as $c): ?><option value="<?= h($c) ?>"><?= h($c) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="input-group input-group-sm mb-2 d-none js-lugar-otro-wrap">
            <input type="text" class="form-control js-lugar-otro" placeholder="Escribe el lugar...">
            <button type="button" class="btn btn-outline-success js-lugar-otro-ok" title="Confirmar"><i class="bi bi-check-lg"></i></button>
            <button type="button" class="btn btn-outline-danger js-lugar-otro-cancel" title="Cancelar"><i class="bi bi-x-lg"></i></button>
          </div>
          <div class="input-group input-group-sm mb-2 d-none js-context-otro-wrap">
            <input type="text" class="form-control js-context-otro" placeholder="Escribe el tipo de maniobra...">
            <button type="button" class="btn btn-outline-success js-context-otro-ok" title="Confirmar"><i class="bi bi-check-lg"></i></button>
            <button type="button" class="btn btn-outline-danger js-context-otro-cancel" title="Cancelar"><i class="bi bi-x-lg"></i></button>
          </div>
          <label class="form-label small fw-semibold mt-1">Tiempos por fase (mm:ss)</label>
          <?php foreach ($cfg['speed_fases'] as $i => $fase): ?>
            <div class="input-group input-group-sm mb-1">
              <span class="input-group-text" style="min-width:62%; font-size:.75rem"><?= h($fase) ?></span>
              <input name="fase[<?= $i ?>]" class="form-control js-sec-input" placeholder="00:00">
            </div>
          <?php endforeach; ?>
          <div class="text-end small mb-2">Duración total: <b id="js-total-sec">0:00</b></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">Tiempo estimado (mm:ss) *</label><input name="estimado" class="form-control form-control-sm" placeholder="00:00" required></div>
            <div class="col-6"><label class="form-label small">N° movimientos</label><input type="number" name="movimientos" class="form-control form-control-sm" min="0"></div>
          </div>
          <div class="mb-2"><label class="form-label small">Instructor</label>
            <select name="evaluador" class="form-select form-select-sm js-op-select" required>
              <option value="">— Seleccionar —</option>
              <?php foreach ($instructores as $i): ?>
                <option value="<?= h($i) ?>" <?= $i === $user['nombre'] ? 'selected' : '' ?>><?= h($i) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-1"><label class="form-label small">Observaciones (errores, demoras, mejoras)</label><textarea name="observaciones" class="form-control form-control-sm" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-cosco btn-sm"><i class="bi bi-save"></i> Guardar medición</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Ver Registro -->
<div class="modal fade" id="modalVerVelocidad" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-eye"></i> Detalle de la medición</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <table class="table table-sm mb-0" id="js-ver-body"></table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
function editSpeed(btn) {
  const data = JSON.parse(btn.dataset.eval);
  const form = document.getElementById('speed-form');
  form.reset();
  document.querySelector('#modalNuevoVelocidad .modal-title').innerHTML = '<i class="bi bi-pencil"></i> Editar medición — <?= h($cfg["nombre"]) ?>';
  form.querySelector('[name="id"]').value = data.id;
  form.querySelector('[name="operator_id"]').value = data.operator_id;
  form.querySelector('[name="fecha"]').value = data.fecha;
  
  form.querySelector('[name="lugar"]').value = data.lugar || '';
  form.querySelector('[name="contexto"]').value = data.contexto || '';
  form.querySelector('[name="evaluador"]').value = data.evaluador || '';
  form.querySelector('[name="observaciones"]').value = data.observaciones || '';
  form.querySelectorAll('select.js-op-select').forEach(sel => {
    sel.dispatchEvent(new Event('change', {bubbles: true}));
  });
  
  const formatSecs = sec => {
    if (!sec) return '';
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
  };
  form.querySelector('[name="estimado"]').value = formatSecs(data.estimado_seg);
  form.querySelector('[name="movimientos"]').value = data.movimientos || '';
  
  const fases = data.fases_arr || [];
  fases.forEach((f, i) => {
    const input = form.querySelector(`[name="fase[${i}]"]`);
    if (input) input.value = formatSecs(f.s);
  });
  
  const firstInput = form.querySelector('.js-sec-input');
  if (firstInput) firstInput.dispatchEvent(new Event('input', {bubbles: true}));
  
  const modal = new bootstrap.Modal(document.getElementById('modalNuevoVelocidad'));
  modal.show();
}

document.addEventListener('DOMContentLoaded', () => {
  const modalEl = document.getElementById('modalNuevoVelocidad');
  if (modalEl) {
    modalEl.addEventListener('hidden.bs.modal', function () {
      const form = document.getElementById('speed-form');
      form.reset();
      form.querySelector('[name="id"]').value = '';
      document.querySelector('#modalNuevoVelocidad .modal-title').innerHTML = '<i class="bi bi-plus-circle"></i> Nueva medición — <?= h($cfg["nombre"]) ?>';
      const tot = form.querySelector('#js-total-sec');
      if (tot) tot.textContent = '0:00';
    });
  }

  const statTotal = document.getElementById('js-stat-total');
  const statEficiencia = document.getElementById('js-stat-eficiencia');
  const statOptimo = document.getElementById('js-stat-optimo');
  const statOps = document.getElementById('js-stat-ops');

  const recalcStats = () => {
    if (!statTotal) return;
    let total = 0, sumEf = 0, optimo = 0;
    const ops = new Set();
    document.querySelectorAll('table.js-filterable tbody tr').forEach(tr => {
      if (tr.style.display === 'none' || !('status' in tr.dataset)) return;
      total++;
      sumEf += parseFloat(tr.dataset.eficiencia) || 0;
      if (tr.dataset.status === 'OPTIMO') optimo++;
      if (tr.dataset.op) ops.add(tr.dataset.op);
    });
    statTotal.textContent = total;
    statEficiencia.textContent = total > 0 ? (sumEf / total).toFixed(1) + '%' : '0%';
    statOptimo.textContent = optimo;
    statOps.textContent = ops.size;
  };

  // Filtro de búsqueda por texto (aplicado en app.js): recalcula tras cada cambio
  window.onTableFilterChange = recalcStats;

  // Modal "Ver"
  const verModal = document.getElementById('modalVerVelocidad');
  if (verModal) {
    verModal.addEventListener('show.bs.modal', ev => {
      const data = JSON.parse(ev.relatedTarget.dataset.ver || '{}');
      const body = document.getElementById('js-ver-body');
      body.innerHTML = Object.entries(data).map(([k, v]) => {
        let val = v || '—';
        if (k === 'Estado') {
          const map = { 'OPTIMO': 'success', 'REGULAR': 'warning text-dark', 'BAJO': 'danger' };
          const cls = map[v] ?? 'secondary';
          val = `<span class="badge bg-${cls}">${v}</span>`;
        }
        return `<tr><th class="text-muted small" style="width:35%">${k}</th><td>${val}</td></tr>`;
      }).join('');
    });
  }
});
</script>
