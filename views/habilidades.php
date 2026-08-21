<?php
$areaSel = strtoupper((string)($_GET['area'] ?? ''));
if ($areaSel === '' || !isset($AREAS[$areaSel])):
?>
<div class="d-flex align-items-center gap-2 mb-3">
  <h4 class="mb-0"><i class="bi bi-stars text-primary"></i> Evaluación de habilidades</h4>
</div>
<p class="text-muted mb-4">Selecciona el área para ver y registrar evaluaciones de habilidades.</p>
<div class="row g-3">
  <?php
  $areaIcons = ['ARMG' => 'bi-building', 'QC' => 'bi-crop', 'PC' => 'bi-diagram-3', 'WL' => 'bi-truck-front'];
  foreach ($AREAS as $a => $c): ?>
  <div class="col-md-4">
    <a href="?page=habilidades&area=<?= $a ?>" class="card area-pick text-decoration-none h-100">
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
$st = db()->prepare("SELECT s.*, o.nombres FROM skill_records s JOIN operators o ON o.id=s.operator_id
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
$esEscala = $cfg['skills_tipo'] === 'escala';
$esTresNiveles = $cfg['skills_tipo'] === 'trinivel';
$skillPrefix = SKILL_STATUS_PREFIX[$AREA_ACTUAL] ?? 'C';

foreach ($rows as &$r) {
    $r['status_calculado'] = calc_skill_status((float)$r['score'], $AREA_ACTUAL);
    $r['apto_calculado'] = skill_es_apto((float)$r['score'], $AREA_ACTUAL);
}
unset($r);

$totalEvals = count($rows);
$promedioScore = $totalEvals ? array_sum(array_column($rows, 'score')) / $totalEvals : 0;
$totalAptos = count(array_filter($rows, fn($r) => $r['apto_calculado']));
$operadoresSet = [];
foreach ($rows as $r) $operadoresSet[$r['operator_id']] = true;
?>
<div class="banner-head mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
      <span class="banner-badge">EVALUACIÓN · HABILIDADES</span>
      <h3 class="mb-1 mt-2">Evaluación de habilidades — <?= h($cfg['nombre']) ?></h3>
      <p class="mb-0 opacity-75">Registro y seguimiento de evaluaciones de habilidades por operador.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="?action=skill_export&area=<?= h($AREA_ACTUAL) ?>" class="btn btn-outline-light btn-sm fw-semibold">
        <i class="bi bi-file-earmark-excel"></i> Exportar Excel
      </a>
      <?php if ($user['rol'] !== 'visita'): ?>
      <button type="button" class="btn btn-light btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalNuevaEval">
        <i class="bi bi-plus-circle"></i> Nueva evaluación
      </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="stat-tile">
    <div class="stat-label">Total evaluaciones</div>
    <div class="stat-value text-primary" id="js-stat-total"><?= $totalEvals ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-tile">
    <div class="stat-label">Score promedio</div>
    <div class="stat-value text-success" id="js-stat-score"><?= number_format($promedioScore, 1) ?>%</div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-tile">
    <div class="stat-label">Aptos (≥75%)</div>
    <div class="stat-value text-success" id="js-stat-aptos"><?= $totalAptos ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-tile">
    <div class="stat-label">Operadores evaluados</div>
    <div class="stat-value" id="js-stat-ops"><?= count($operadoresSet) ?></div></div></div>
</div>

<section class="skill-level-legend mb-3" aria-labelledby="skill-levels-title">
  <div class="skill-level-legend__intro">
    <span class="skill-level-legend__icon"><i class="bi bi-info-circle"></i></span>
    <div>
      <h4 id="skill-levels-title">Niveles de competencia <?= h($AREA_ACTUAL) ?></h4>
      <p>La aptitud se obtiene desde <strong>75%</strong> (<?= h($skillPrefix) ?>1, <?= h($skillPrefix) ?>2 o <?= h($skillPrefix) ?>3).</p>
    </div>
  </div>
  <div class="skill-level-legend__levels">
    <div class="skill-level skill-level--c0"><span class="skill-level__code"><?= h($skillPrefix) ?>0</span><div><strong>Nivel bajo</strong><small>Desempeño por debajo de lo esperado · &lt;75%</small></div></div>
    <div class="skill-level skill-level--c1"><span class="skill-level__code"><?= h($skillPrefix) ?>1</span><div><strong>Nivel básico</strong><small>Desempeño esperado · 75–85.9% · Apto</small></div></div>
    <div class="skill-level skill-level--c2"><span class="skill-level__code"><?= h($skillPrefix) ?>2</span><div><strong>Nivel promedio</strong><small>Desempeño superior al esperado · 86–95.9% · Apto</small></div></div>
    <div class="skill-level skill-level--c3"><span class="skill-level__code"><?= h($skillPrefix) ?>3</span><div><strong>Nivel avanzado</strong><small>Alta competencia y dominio operativo · ≥96% · Apto</small></div></div>
  </div>
</section>

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="input-group input-group-sm" style="max-width:220px">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input class="form-control" id="js-table-filter" placeholder="Filtrar...">
    </div>
    <div class="btn-group btn-group-sm" role="group" id="js-tipo-tabs">
      <button type="button" class="btn btn-outline-primary active" data-tipo="TODOS">Todos</button>
      <button type="button" class="btn btn-outline-primary" data-tipo="ENTRENAMIENTO">Entrenamiento</button>
      <button type="button" class="btn btn-outline-primary" data-tipo="REENTRENAMIENTO">Reentrenamiento</button>
    </div>
  </div>
  <div class="table-responsive" style="max-height:65vh">
    <table class="table table-sm table-hover align-middle mb-0 js-filterable">
      <thead class="sticky-top"><tr><th>Fecha</th><th>Operador</th><th>Tipo</th><th><?= mb_strtoupper($cfg['skills_ctx_label']) ?></th><th>Score</th><th>Status</th><th>Apto</th><th>Instructor</th><th>Comentarios</th><th class="text-end">Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr title="<?= h($r['comentarios']) ?>" data-tipo="<?= h($r['tipo_capacitacion']) ?>" data-score="<?= (float)$r['score'] ?>" data-apto="<?= $r['apto_calculado'] ? '1' : '0' ?>" data-op="<?= (int)$r['operator_id'] ?>">
          <td class="text-nowrap"><?= h($r['fecha']) ?></td>
          <td class="small"><?= h($r['nombres']) ?></td>
          <td class="small"><?= h($r['tipo_capacitacion']) ?></td>
          <td class="small"><?= h(mb_strimwidth($r['contexto'] ?? '', 0, 28, '…')) ?></td>
          <td><b><?= number_format((float)$r['score'], 1) ?>%</b></td>
          <td><?= status_badge($r['status_calculado']) ?></td>
          <td><?= $r['apto_calculado'] ? '<span class="check-ok">SÍ</span>' : '<span class="check-bad">NO</span>' ?></td>
          <td class="small"><?= h($r['evaluador']) ?></td>
          <td class="small text-muted" style="max-width: 150px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?= h($r['comentarios']) ?>"><?= h($r['comentarios']) ?></td>
          <td class="text-end text-nowrap">
            <?php if ($user['rol'] !== 'visita'): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 me-1" title="Editar" onclick="editSkill(this)" data-eval="<?= h(json_encode($r)) ?>">
              <i class="bi bi-pencil"></i>
            </button>
            <form method="post" action="?action=skill_delete" class="d-inline" onsubmit="return confirm('¿Eliminar evaluación?')">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger py-0" title="Eliminar"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">Sin evaluaciones para <?= $AREA_ACTUAL ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Nueva evaluación -->
<div class="modal fade" id="modalNuevaEval" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog" style="max-width: 580px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nueva evaluación — <?= h($cfg['nombre']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="?action=skill_save" id="skill-form" data-tipo="<?= $cfg['skills_tipo'] ?>">
        <div class="modal-body">
          <input type="hidden" name="area" value="<?= $AREA_ACTUAL ?>">
          <input type="hidden" name="id" value="">
          <div class="mb-2"><label class="form-label small">Operador *</label>
            <select name="operator_id" class="form-select form-select-sm js-op-select" required>
              <option value="">— Seleccionar —</option>
              <?php foreach ($ops as $o): ?><option value="<?= $o['id'] ?>"><?= h($o['nombres']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small">Fecha *</label><input type="date" name="fecha" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-6"><label class="form-label small"><?= h($cfg['skills_ctx_label']) ?></label>
              <select name="contexto" class="form-select form-select-sm">
                <option value="">— Seleccionar —</option>
                <?php foreach ($cfg['skills_contextos'] as $c): ?><option value="<?= h($c) ?>"><?= h($c) ?></option><?php endforeach; ?>
              </select></div>
          </div>

          <?php if ($esEscala): ?>
            <div class="small text-muted mb-1">Escala: 1 Deficiente · 2 Necesita mejorar · 3 Aceptable · 4 Bueno · 5 Excelente</div>
            <table class="table table-sm skill-grid mb-2">
            <?php foreach ($cfg['skills_grupos'] as $g => $gcfg): foreach ($gcfg['items'] as $k => $item): ?>
              <tr>
                <td class="small"><?= h($item) ?></td>
                <td class="text-end text-nowrap">
                  <div class="btn-group rate-group" role="group">
                  <?php for ($v = 1; $v <= 5; $v++): $rid = "r{$k}_{$v}"; ?>
                    <input type="radio" class="btn-check" name="item[<?= h($g) ?>][<?= $k ?>]" id="<?= $rid ?>" value="<?= $v ?>" data-g="<?= h($g) ?>" data-i="<?= h($item) ?>">
                    <label class="btn btn-sm btn-outline-primary" for="<?= $rid ?>"><?= $v ?></label>
                  <?php endfor; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; endforeach; ?>
            </table>
          <?php elseif ($esTresNiveles): ?>
            <div class="small text-muted mb-1">Marca una opción por actividad: 0 debajo de lo esperado · 1 en lo esperado · 2 sobre lo esperado.</div>
            <table class="table table-sm skill-grid mb-2">
            <?php foreach ($cfg['skills_grupos'] as $g => $gcfg): foreach ($gcfg['items'] as $k => $item): ?>
              <tr>
                <td class="small"><?= h($item) ?></td>
                <td class="text-end text-nowrap">
                  <div class="btn-group rate-group" role="group" aria-label="Calificación de <?= h($item) ?>">
                  <?php foreach ([0 => 'Debajo', 1 => 'Esperado', 2 => 'Sobre'] as $v => $label): $rid = "w{$k}_{$v}"; ?>
                    <input type="radio" class="btn-check" name="item[<?= h($g) ?>][<?= $k ?>]" id="<?= $rid ?>" value="<?= $v ?>" data-g="<?= h($g) ?>" data-i="<?= h($item) ?>">
                    <label class="btn btn-sm btn-outline-primary" for="<?= $rid ?>" title="<?= $label ?>"><?= $v ?></label>
                  <?php endforeach; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; endforeach; ?>
            </table>
          <?php else: ?>
            <?php foreach ($cfg['skills_grupos'] as $g => $gcfg): ?>
              <div class="border rounded p-2 mb-2" data-grupo data-peso="<?= $gcfg['peso'] ?>">
                <div class="fw-semibold small text-primary mb-1"><?= h($g) ?> (<?= $gcfg['peso'] ?>%)</div>
                <?php foreach ($gcfg['items'] as $k => $item): $cid = 'c' . md5($g . $k); ?>
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="form-check form-switch mb-0">
                      <input class="form-check-input js-skill-switch" type="checkbox" name="item[<?= h($g) ?>][<?= $k ?>]" id="<?= $cid ?>" data-g="<?= h($g) ?>" data-i="<?= h($item) ?>">
                      <label class="form-check-label small" for="<?= $cid ?>"><?= h($item) ?></label>
                    </div>
                    <span class="badge rounded-pill js-switch-status bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.7rem; min-width: 85px; text-align: center;">Desaprobado</span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <div class="alert alert-light border d-flex justify-content-between py-2 mb-2">
            <span class="small fw-semibold">Puntaje calculado:</span> <b id="js-skill-score">—</b>
          </div>
          <div class="mb-2"><label class="form-label small">Instructor</label>
            <select name="evaluador" class="form-select form-select-sm js-op-select" required>
              <option value="">— Seleccionar —</option>
              <?php foreach ($instructores as $i): ?>
                <option value="<?= h($i) ?>" <?= $i === $user['nombre'] ? 'selected' : '' ?>><?= h($i) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-1"><label class="form-label small">Comentarios / puntos de mejora</label><textarea name="comentarios" class="form-control form-control-sm" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-cosco btn-sm"><i class="bi bi-save"></i> Guardar evaluación</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editSkill(btn) {
  const data = JSON.parse(btn.dataset.eval);
  const form = document.getElementById('skill-form');
  form.reset();
  document.querySelector('#modalNuevaEval .modal-title').innerHTML = '<i class="bi bi-pencil"></i> Editar evaluación — <?= h($cfg["nombre"]) ?>';
  form.querySelector('[name="id"]').value = data.id;
  form.querySelector('[name="operator_id"]').value = data.operator_id;
  form.querySelector('[name="fecha"]').value = data.fecha;
  form.querySelector('[name="contexto"]').value = data.contexto || '';
  form.querySelector('[name="evaluador"]').value = data.evaluador || '';
  form.querySelector('[name="comentarios"]').value = data.comentarios || '';
  form.querySelectorAll('select.js-op-select').forEach(sel => {
    sel.dispatchEvent(new Event('change', {bubbles: true}));
  });
  
  const items = JSON.parse(data.items || '[]');
  const isEscala = form.dataset.tipo === 'escala';
  items.forEach(it => {
    if (isEscala) {
      const radio = form.querySelector(`input[type="radio"][data-g="${it.g}"][data-i="${it.i}"][value="${it.v}"]`);
      if (radio) {
        radio.checked = true;
        radio.dispatchEvent(new Event('change', {bubbles: true}));
      }
    } else if (form.dataset.tipo === 'trinivel') {
      const radio = form.querySelector(`input[type="radio"][data-g="${it.g}"][data-i="${it.i}"][value="${it.v}"]`);
      if (radio) {
        radio.checked = true;
        radio.dispatchEvent(new Event('change', {bubbles: true}));
      }
    } else {
      const chk = form.querySelector(`input[type="checkbox"][data-g="${it.g}"][data-i="${it.i}"]`);
      if (chk) {
        chk.checked = (it.v == 1);
        chk.dispatchEvent(new Event('change', {bubbles: true}));
      }
    }
  });
  
  const modal = new bootstrap.Modal(document.getElementById('modalNuevaEval'));
  modal.show();
}

document.addEventListener('DOMContentLoaded', () => {
  const modalEl = document.getElementById('modalNuevaEval');
  if (modalEl) {
    modalEl.addEventListener('hidden.bs.modal', function () {
      const form = document.getElementById('skill-form');
      form.reset();
      form.querySelector('[name="id"]').value = '';
      document.querySelector('#modalNuevaEval .modal-title').innerHTML = '<i class="bi bi-plus-circle"></i> Nueva evaluación — <?= h($cfg["nombre"]) ?>';
      form.querySelectorAll('input[type="checkbox"]').forEach(chk => chk.dispatchEvent(new Event('change', {bubbles: true})));
      form.querySelectorAll('input[type="radio"]').forEach(rad => rad.dispatchEvent(new Event('change', {bubbles: true})));
    });
  }

  // Recalcula las tarjetas de estadísticas a partir de las filas visibles de la tabla
  const statTotal = document.getElementById('js-stat-total');
  const statScore = document.getElementById('js-stat-score');
  const statAptos = document.getElementById('js-stat-aptos');
  const statOps = document.getElementById('js-stat-ops');
  const recalcStats = () => {
    if (!statTotal) return;
    let total = 0, sumScore = 0, aptos = 0;
    const ops = new Set();
    document.querySelectorAll('table.js-filterable tbody tr').forEach(tr => {
      if (tr.style.display === 'none' || !('tipo' in tr.dataset)) return;
      total++;
      sumScore += parseFloat(tr.dataset.score) || 0;
      if (tr.dataset.apto === '1') aptos++;
      if (tr.dataset.op) ops.add(tr.dataset.op);
    });
    statTotal.textContent = total;
    statScore.textContent = (total ? (sumScore / total) : 0).toFixed(1) + '%';
    statAptos.textContent = aptos;
    statOps.textContent = ops.size;
  };

  // Tabs por tipo de capacitación
  const tabs = document.querySelectorAll('#js-tipo-tabs button');
  tabs.forEach(btn => {
    btn.addEventListener('click', () => {
      tabs.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const tipo = btn.dataset.tipo;
      document.querySelectorAll('table.js-filterable tbody tr').forEach(tr => {
        tr.style.display = (tipo === 'TODOS' || tr.dataset.tipo === tipo) ? '' : 'none';
      });
      recalcStats();
    });
  });

  // Filtro de búsqueda por texto (aplicado en app.js): recalcula tras cada cambio
  window.onTableFilterChange = recalcStats;
});
</script>
