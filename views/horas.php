<?php
$areaSel = strtoupper((string)($_GET['area'] ?? ''));
if ($areaSel === '' || !isset($AREAS[$areaSel])):
?>
<div class="d-flex align-items-center gap-2 mb-3">
  <h4 class="mb-0"><i class="bi bi-clock-history text-primary"></i> Horas de entrenamiento</h4>
</div>
<p class="text-muted mb-4">Selecciona el área para ver y registrar horas de entrenamiento.</p>
<div class="row g-3">
  <?php
  $areaIcons = ['ARMG' => 'bi-building', 'QC' => 'bi-crop', 'PC' => 'bi-diagram-3', 'WL' => 'bi-truck-front'];
  foreach ($AREAS as $a => $c): ?>
  <div class="col-md-4">
    <a href="?page=horas&area=<?= $a ?>" class="card area-pick text-decoration-none h-100">
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
$yearsStmt = db()->prepare("SELECT DISTINCT YEAR(fecha) AS anio FROM hours_records WHERE area=? ORDER BY anio DESC");
$yearsStmt->execute([$AREA_ACTUAL]);
$years = array_map('strval', $yearsStmt->fetchAll(PDO::FETCH_COLUMN));
$yearSel = (string)($_GET['anio'] ?? 'todos');
if ($yearSel !== 'todos' && !in_array($yearSel, $years, true)) $yearSel = 'todos';

$rowsSql = "SELECT h.*, o.nombres, o.dni, o.cargo FROM hours_records h JOIN operators o ON o.id=h.operator_id WHERE h.area=?";
$rowsParams = [$AREA_ACTUAL];
if ($yearSel !== 'todos') {
    $rowsSql .= " AND h.fecha BETWEEN ? AND ?";
    $rowsParams[] = $yearSel . '-01-01';
    $rowsParams[] = $yearSel . '-12-31';
}
$rowsSql .= " ORDER BY h.fecha DESC, h.id DESC";
$rows = db()->prepare($rowsSql);
$rows->execute($rowsParams);
$rows = $rows->fetchAll();

$actividades = actividades_con_personalizadas($TIPOS_ACTIVIDAD);
$lugares = lugares_con_personalizados($cfg['horas_lugares'], $AREA_ACTUAL);

$totalRegistros = count($rows);
$totalMin = array_sum(array_column($rows, 'total_min'));
$mesActual = date('Y-m');
$horasEsteMes = 0;
$operadoresSet = [];
foreach ($rows as $r) {
    if (substr($r['fecha'], 0, 7) === $mesActual) $horasEsteMes += (int)$r['total_min'];
    $operadoresSet[$r['operator_id']] = true;
}
?>
<div class="banner-head mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
      <span class="banner-badge">ENTRENAMIENTO · HORAS</span>
      <h3 class="mb-1 mt-2">Horas de entrenamiento — <?= h($cfg['nombre']) ?></h3>
      <p class="mb-0 opacity-75">Registro de horas de inducción e instrucción por operador.</p>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-light btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalNuevoHoras">
        <i class="bi bi-plus-circle"></i> Nuevo registro
      </button>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="stat-tile">
    <div class="stat-label">Total registros</div>
    <div class="stat-value text-primary" id="js-stat-total"><?= $totalRegistros ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-tile">
    <div class="stat-label">Horas totales</div>
    <div class="stat-value text-success" id="js-stat-horas"><?= fmt_minutes($totalMin) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-tile">
    <div class="stat-label">Horas este mes</div>
    <div class="stat-value text-warning-emphasis" id="js-stat-mes"><?= fmt_minutes($horasEsteMes) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-tile">
    <div class="stat-label">Operadores capacitados</div>
    <div class="stat-value" id="js-stat-ops"><?= count($operadoresSet) ?></div></div></div>
</div>

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="input-group input-group-sm" style="max-width:280px">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input class="form-control" id="js-table-filter" placeholder="Buscar por operador o detalle...">
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <form method="get" class="d-flex align-items-center gap-1">
        <input type="hidden" name="page" value="horas">
        <input type="hidden" name="area" value="<?= h($AREA_ACTUAL) ?>">
        <label class="small fw-semibold text-muted" for="js-year-filter">Año</label>
        <select class="form-select form-select-sm" id="js-year-filter" name="anio" onchange="this.form.submit()" aria-label="Filtrar registros por año">
          <option value="todos" <?= $yearSel === 'todos' ? 'selected' : '' ?>>Todos los años</option>
          <?php foreach ($years as $year): ?>
            <option value="<?= h($year) ?>" <?= $yearSel === $year ? 'selected' : '' ?>><?= h($year) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <div class="btn-group btn-group-sm" role="group" id="js-tipo-tabs" aria-label="Filtrar por tipo de preparación">
        <button type="button" class="btn btn-outline-primary active" data-tipo="TODOS">Todos</button>
        <?php foreach ($TIPOS_PREPARACION as $t): ?>
          <button type="button" class="btn btn-outline-primary" data-tipo="<?= h($t) ?>"><?= h($t) ?></button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="table-responsive" style="max-height:65vh">
    <table class="table table-sm table-hover align-middle mb-0 js-filterable">
      <thead class="sticky-top"><tr><th>Fecha</th><th>Operador</th><th>Tipo</th><th>Actividad</th><th>Lugar</th><th>Detalle</th><th>Total</th><th>Instructor</th><th class="text-end">Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $det = json_decode($r['detalle'] ?? '[]', true) ?: []; ?>
        <tr data-tipo="<?= h($r['tipo_preparacion']) ?>" data-min="<?= (int)$r['total_min'] ?>" data-op="<?= (int)$r['operator_id'] ?>" data-mes="<?= h(substr($r['fecha'], 0, 7)) ?>">
          <td class="text-nowrap"><?= h($r['fecha']) ?></td>
          <td class="small"><?= h($r['nombres']) ?></td>
          <td><span class="badge bg-primary"><?= h($r['tipo_preparacion']) ?></span></td>
          <td class="small"><?= h(mb_strimwidth($r['tipo_actividad'] ?? '', 0, 40, '…')) ?></td>
          <td class="small"><?= h($r['lugar']) ?></td>
          <td class="small"><?php foreach ($det as $k => $v) echo '<div>' . h($k) . ': <b>' . fmt_minutes((int)$v) . '</b></div>'; ?></td>
          <td><b><?= fmt_minutes((int)$r['total_min']) ?></b></td>
          <td class="small"><?= h($r['instructor']) ?></td>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-primary py-0" data-bs-toggle="modal" data-bs-target="#modalVerHoras" title="Ver"
              data-ver='<?= h(json_encode([
                  'Operador' => $r['nombres'], 'DNI' => $r['dni'], 'Cargo' => $r['cargo'], 'Fecha' => $r['fecha'],
                  'Tipo' => $r['tipo_preparacion'], 'Actividad' => $r['tipo_actividad'], 'Lugar' => $r['lugar'],
                  'Detalle' => implode(', ', array_map(fn($k, $v) => "$k: " . fmt_minutes((int)$v), array_keys($det), $det)),
                  'Total' => fmt_minutes((int)$r['total_min']), 'Instructor' => $r['instructor'], 'Observación' => $r['observacion'],
              ])) ?>'><i class="bi bi-eye"></i></button>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#modalEditarHoras" title="Editar"
              data-edit='<?= h(json_encode([
                  'id' => $r['id'], 'operador' => $r['nombres'], 'fecha' => $r['fecha'], 'tipo_preparacion' => $r['tipo_preparacion'],
                  'tipo_actividad' => $r['tipo_actividad'], 'lugar' => $r['lugar'], 'instructor' => $r['instructor'],
                  'observacion' => $r['observacion'], 'detalle' => $det,
              ])) ?>'><i class="bi bi-pencil"></i></button>
            <form method="post" action="?action=horas_delete" class="d-inline" onsubmit="return confirm('¿Eliminar registro?')">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger py-0" title="Eliminar"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">Sin registros para <?= $AREA_ACTUAL ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Nuevo registro -->
<div class="modal fade" id="modalNuevoHoras" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nuevo registro — <?= h($cfg['nombre']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="?action=horas_save">
        <div class="modal-body">
          <input type="hidden" name="area" value="<?= $AREA_ACTUAL ?>">
          <div class="mb-2"><label class="form-label small">Operador(es) *</label>
            <select name="operator_id[]" class="form-select form-select-sm js-op-select" multiple required>
              <?php foreach ($ops as $o): ?><option value="<?= $o['id'] ?>"><?= h($o['nombres']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">Fecha *</label><input type="date" name="fecha" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-6"><label class="form-label small">Tipo *</label>
              <select name="tipo_preparacion" class="form-select form-select-sm" required>
                <?php foreach ($TIPOS_PREPARACION as $t): ?><option value="<?= h($t) ?>"><?= h($t) ?></option><?php endforeach; ?>
              </select></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small">Lugar</label>
              <select class="form-select form-select-sm js-lugar-select" name="lugar" data-field-name="lugar" data-area="<?= h($AREA_ACTUAL) ?>">
                <?php foreach ($lugares as $l): ?><option value="<?= h($l) ?>"><?= h($l) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-6"><label class="form-label small">Actividad</label>
              <select class="form-select form-select-sm js-act-select" name="tipo_actividad" data-field-name="tipo_actividad">
                <option value="">— Seleccionar —</option>
                <?php foreach ($actividades as $t): ?><option value="<?= h($t) ?>"><?= h($t) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="input-group input-group-sm mb-2 d-none js-lugar-otro-wrap">
            <input type="text" class="form-control js-lugar-otro" placeholder="Escribe el lugar...">
            <button type="button" class="btn btn-outline-success js-lugar-otro-ok" title="Confirmar"><i class="bi bi-check-lg"></i></button>
            <button type="button" class="btn btn-outline-danger js-lugar-otro-cancel" title="Cancelar"><i class="bi bi-x-lg"></i></button>
          </div>
          <div class="input-group input-group-sm mb-2 d-none js-act-otro-wrap">
            <input type="text" class="form-control js-act-otro" placeholder="Escribe la actividad...">
            <button type="button" class="btn btn-outline-success js-act-otro-ok" title="Guardar actividad"><i class="bi bi-check-lg"></i></button>
            <button type="button" class="btn btn-outline-danger js-act-otro-cancel" title="Cancelar"><i class="bi bi-x-lg"></i></button>
          </div>
          <label class="form-label small fw-semibold mt-1">Horas por tipo (formato h:mm o minutos)</label>
          <?php foreach ($cfg['horas_campos'] as $i => $campo): ?>
            <div class="input-group input-group-sm mb-1">
              <span class="input-group-text" style="min-width:60%; font-size:.75rem"><?= h($campo) ?></span>
              <input name="horas[<?= $i ?>]" class="form-control js-min-input" placeholder="0:00">
            </div>
          <?php endforeach; ?>
          <div class="text-end small mb-2">Total: <b id="js-total-min">0:00 h</b></div>
          <div class="mb-2"><label class="form-label small">Instructor</label>
            <select name="instructor" class="form-select form-select-sm js-op-select">
              <option value="">— Seleccionar —</option>
              <?php foreach ($instructores as $i): ?>
                <option value="<?= h($i) ?>" <?= $i === $user['nombre'] ? 'selected' : '' ?>><?= h($i) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-1"><label class="form-label small">Observación</label><textarea name="observacion" class="form-control form-control-sm" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-cosco btn-sm"><i class="bi bi-save"></i> Guardar registro</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Ver registro -->
<div class="modal fade" id="modalVerHoras" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-eye"></i> Detalle del registro</h5>
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

<!-- Modal: Editar registro -->
<div class="modal fade" id="modalEditarHoras" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar registro — <?= h($cfg['nombre']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="?action=horas_update" id="form-editar-horas">
        <div class="modal-body">
          <input type="hidden" name="id">
          <div class="mb-2"><label class="form-label small">Operador</label>
            <input type="text" class="form-control form-control-sm" id="edit-operador" disabled></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">Fecha *</label><input type="date" name="fecha" class="form-control form-control-sm" required></div>
            <div class="col-6"><label class="form-label small">Tipo *</label>
              <select name="tipo_preparacion" class="form-select form-select-sm" required>
                <?php foreach ($TIPOS_PREPARACION as $t): ?><option value="<?= h($t) ?>"><?= h($t) ?></option><?php endforeach; ?>
              </select></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small">Lugar</label>
              <select class="form-select form-select-sm js-lugar-select" name="lugar" data-field-name="lugar" data-area="<?= h($AREA_ACTUAL) ?>">
                <?php foreach ($lugares as $l): ?><option value="<?= h($l) ?>"><?= h($l) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-6"><label class="form-label small">Actividad</label>
              <select class="form-select form-select-sm js-act-select" name="tipo_actividad" data-field-name="tipo_actividad">
                <option value="">— Seleccionar —</option>
                <?php foreach ($actividades as $t): ?><option value="<?= h($t) ?>"><?= h($t) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="input-group input-group-sm mb-2 d-none js-lugar-otro-wrap">
            <input type="text" class="form-control js-lugar-otro" placeholder="Escribe el lugar...">
            <button type="button" class="btn btn-outline-success js-lugar-otro-ok" title="Confirmar"><i class="bi bi-check-lg"></i></button>
            <button type="button" class="btn btn-outline-danger js-lugar-otro-cancel" title="Cancelar"><i class="bi bi-x-lg"></i></button>
          </div>
          <div class="input-group input-group-sm mb-2 d-none js-act-otro-wrap">
            <input type="text" class="form-control js-act-otro" placeholder="Escribe la actividad...">
            <button type="button" class="btn btn-outline-success js-act-otro-ok" title="Guardar actividad"><i class="bi bi-check-lg"></i></button>
            <button type="button" class="btn btn-outline-danger js-act-otro-cancel" title="Cancelar"><i class="bi bi-x-lg"></i></button>
          </div>
          <label class="form-label small fw-semibold mt-1">Horas por tipo (formato h:mm o minutos)</label>
          <?php foreach ($cfg['horas_campos'] as $i => $campo): ?>
            <div class="input-group input-group-sm mb-1">
              <span class="input-group-text" style="min-width:60%; font-size:.75rem"><?= h($campo) ?></span>
              <input name="horas[<?= $i ?>]" class="form-control js-min-input-edit" data-campo="<?= h($campo) ?>" placeholder="0:00">
            </div>
          <?php endforeach; ?>
          <div class="text-end small mb-2">Total: <b id="js-total-min-edit">0:00 h</b></div>
          <div class="mb-2"><label class="form-label small">Instructor</label>
            <select name="instructor" class="form-select form-select-sm js-op-select">
              <option value="">— Seleccionar —</option>
              <?php foreach ($instructores as $i): ?><option value="<?= h($i) ?>"><?= h($i) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-1"><label class="form-label small">Observación</label><textarea name="observacion" class="form-control form-control-sm" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-cosco btn-sm"><i class="bi bi-save"></i> Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Recalcula las tarjetas de estadísticas a partir de las filas visibles de la tabla
  const mesActual = new Date().toISOString().slice(0, 7);
  const statTotal = document.getElementById('js-stat-total');
  const statHoras = document.getElementById('js-stat-horas');
  const statMes = document.getElementById('js-stat-mes');
  const statOps = document.getElementById('js-stat-ops');
  const fmtMinStat = t => Math.floor(t / 60) + ':' + String(t % 60).padStart(2, '0');
  const recalcStats = () => {
    if (!statTotal) return;
    let total = 0, min = 0, minMes = 0;
    const ops = new Set();
    document.querySelectorAll('table.js-filterable tbody tr').forEach(tr => {
      if (tr.style.display === 'none' || !('min' in tr.dataset)) return;
      total++;
      const m = parseInt(tr.dataset.min, 10) || 0;
      min += m;
      if (tr.dataset.mes === mesActual) minMes += m;
      if (tr.dataset.op) ops.add(tr.dataset.op);
    });
    statTotal.textContent = total;
    statHoras.textContent = fmtMinStat(min);
    statMes.textContent = fmtMinStat(minMes);
    statOps.textContent = ops.size;
  };

  let tipoSeleccionado = 'TODOS';
  const tableRows = document.querySelectorAll('table.js-filterable tbody tr');
  const filterInput = document.getElementById('js-table-filter');
  const applyTableFilters = () => {
    const query = (filterInput?.value || '').toLowerCase();
    tableRows.forEach(tr => {
      if (!('min' in tr.dataset)) return;
      const matchesType = tipoSeleccionado === 'TODOS' || tr.dataset.tipo === tipoSeleccionado;
      const matchesQuery = !query || tr.textContent.toLowerCase().includes(query);
      tr.style.display = matchesType && matchesQuery ? '' : 'none';
    });
    recalcStats();
  };

  // Tabs por tipo de preparación, combinados con la búsqueda por texto
  const tabs = document.querySelectorAll('#js-tipo-tabs button');
  tabs.forEach(btn => {
    btn.addEventListener('click', () => {
      tabs.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      tipoSeleccionado = btn.dataset.tipo;
      applyTableFilters();
    });
  });

  // app.js delega aquí la búsqueda para no perder el filtro de tipo activo.
  window.applyTableFilters = applyTableFilters;

  // Modal "Ver"
  const verModal = document.getElementById('modalVerHoras');
  if (verModal) {
    verModal.addEventListener('show.bs.modal', ev => {
      const data = JSON.parse(ev.relatedTarget.dataset.ver || '{}');
      const body = document.getElementById('js-ver-body');
      body.innerHTML = Object.entries(data).map(([k, v]) =>
        `<tr><th class="text-muted small" style="width:35%">${k}</th><td>${v || '—'}</td></tr>`).join('');
    });
  }

  // Suma en vivo del modal "Editar"
  const editMinInputs = document.querySelectorAll('.js-min-input-edit');
  const editTotalOut = document.getElementById('js-total-min-edit');
  const parseMinLocal = v => {
    v = (v || '').trim();
    if (!v) return 0;
    if (v.includes(':')) { const p = v.split(':').map(Number); return (p[0] || 0) * 60 + (p[1] || 0); }
    return Math.round(parseFloat(v) || 0);
  };
  const recalcEditTotal = () => {
    let t = 0;
    editMinInputs.forEach(i => t += parseMinLocal(i.value));
    if (editTotalOut) editTotalOut.textContent = Math.floor(t / 60) + ':' + String(t % 60).padStart(2, '0') + ' h';
  };
  editMinInputs.forEach(i => i.addEventListener('input', recalcEditTotal));

  // Modal "Editar": precarga los datos del registro seleccionado
  const editModal = document.getElementById('modalEditarHoras');
  if (editModal) {
    editModal.addEventListener('show.bs.modal', ev => {
      const data = JSON.parse(ev.relatedTarget.dataset.edit || '{}');
      const form = document.getElementById('form-editar-horas');
      form.querySelector('[name=id]').value = data.id;
      document.getElementById('edit-operador').value = data.operador || '';
      form.querySelector('[name=fecha]').value = data.fecha;
      form.querySelector('[name=tipo_preparacion]').value = data.tipo_preparacion;
      form.querySelector('[name=instructor]').value = data.instructor || '';
      form.querySelector('[name=observacion]').value = data.observacion || '';

      const lugarSelect = form.querySelector('.js-lugar-select');
      const lugarOtro = form.querySelector('.js-lugar-otro');
      const lugarKnown = Array.from(lugarSelect.options).some(o => o.value === data.lugar);
      if (data.lugar && !lugarKnown) {
        lugarSelect.value = 'OTROS';
        lugarOtro.value = data.lugar;
      } else {
        lugarSelect.value = data.lugar || '';
      }
      lugarSelect.dispatchEvent(new Event('change'));

      const actSelect = form.querySelector('.js-act-select');
      const actOtro = form.querySelector('.js-act-otro');
      const known = Array.from(actSelect.options).some(o => o.value === data.tipo_actividad);
      if (data.tipo_actividad && !known) {
        actSelect.value = 'OTROS';
        actOtro.value = data.tipo_actividad;
      } else {
        actSelect.value = data.tipo_actividad || '';
      }
      actSelect.dispatchEvent(new Event('change'));

      editMinInputs.forEach(inp => {
        const min = (data.detalle && data.detalle[inp.dataset.campo]) || 0;
        inp.value = min ? Math.floor(min / 60) + ':' + String(min % 60).padStart(2, '0') : '';
      });
      recalcEditTotal();
    });
  }
});
</script>
