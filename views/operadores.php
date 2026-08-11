<?php
$ops = db()->query("SELECT o.* FROM operators o ORDER BY o.nombres")->fetchAll();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-people-fill text-primary"></i> Operadores</h4>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <select class="form-select form-select-sm" id="js-cargo-filter" style="width:230px">
      <option value="">Todos los cargos</option>
      <?php foreach ($CARGOS as $c): ?><option value="<?= h($c) ?>"><?= h($c) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select form-select-sm" id="js-grua-filter" style="width:170px">
      <option value="">Todas las grúas</option>
      <?php foreach ($AREAS as $ak => $ac): ?><option value="<?= h($ak) ?>"><?= h($ac['nombre']) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select form-select-sm" id="js-estado-filter" style="width:220px">
      <option value="">Todos los estados</option>
      <option value="ENTRENAMIENTO">ENTRENAMIENTO (Evaluación)</option>
      <option value="REENTRENAMIENTO">REENTRENAMIENTO (Certificado)</option>
    </select>
    <input class="form-control form-control-sm" id="js-ops-search" placeholder="Buscar..." style="width:230px">
    <div class="form-check form-switch mb-0">
      <input class="form-check-input" type="checkbox" id="js-show-cesados">
      <label class="form-check-label small" for="js-show-cesados">Mostrar inactivos</label>
    </div>
    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#mGruasBulk">
      <i class="bi bi-diagram-3"></i> Asignar grúas en bloque
    </button>
    <button class="btn btn-sm btn-cosco" data-bs-toggle="modal" data-bs-target="#mOperador"
            onclick="document.querySelector('#mOperador form').reset(); document.querySelector('#mOperador [name=id]').value=''; document.querySelectorAll('#mOperador [name=\'tipos_grua[]\']').forEach(c=>c.checked=false);">
      <i class="bi bi-plus-lg"></i> Nuevo operador
    </button>
  </div>
</div>

<div class="row g-2 mb-3">
  <div class="col-6 col-lg">
    <div class="card h-100"><div class="card-body py-2 text-center">
      <div class="small text-muted">Activos</div>
      <div class="fs-4 fw-bold text-primary" id="js-card-activos">0</div>
    </div></div>
  </div>
  <div class="col-6 col-lg">
    <div class="card h-100"><div class="card-body py-2 text-center">
      <div class="small text-muted">Inactivos</div>
      <div class="fs-4 fw-bold text-danger" id="js-card-cesados">0</div>
    </div></div>
  </div>
  <div class="col-6 col-lg">
    <div class="card h-100"><div class="card-body py-2 text-center">
      <div class="small text-muted">ARMG</div>
      <div class="fs-4 fw-bold" id="js-card-armg">0</div>
    </div></div>
  </div>
  <div class="col-6 col-lg">
    <div class="card h-100"><div class="card-body py-2 text-center">
      <div class="small text-muted">QC</div>
      <div class="fs-4 fw-bold" id="js-card-qc">0</div>
    </div></div>
  </div>
  <div class="col-6 col-lg">
    <div class="card h-100"><div class="card-body py-2 text-center">
      <div class="small text-muted">PC</div>
      <div class="fs-4 fw-bold" id="js-card-pc">0</div>
    </div></div>
  </div>
  <div class="col-6 col-lg">
    <div class="card h-100"><div class="card-body py-2 text-center">
      <div class="small text-muted">Wheel Loaders</div>
      <div class="fs-4 fw-bold" id="js-card-wl">0</div>
    </div></div>
  </div>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-hover align-middle mb-0 js-filterable">
  <thead><tr>
    <th class="text-center">N°</th>
    <th>Código</th><th>DNI</th><th>Apellidos y nombres</th><th>Cargo</th><th>Grúas</th><th>Ingreso</th>
    <th class="text-center">Laboral</th><th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($ops as $o): $gruas = $o['tipos_grua'] ? explode(',', $o['tipos_grua']) : []; ?>
    <tr data-estado="<?= h($o['estado_capacitacion']) ?>" data-cargo="<?= h($o['cargo']) ?>" data-activo="<?= (int)$o['activo'] ?>" data-gruas="<?= h($o['tipos_grua'] ?? '') ?>">
      <td class="text-center js-row-num"></td>
      <td><?= h($o['codigo']) ?></td>
      <td><?= h($o['dni']) ?></td>
      <td><a href="?page=perfil&id=<?= $o['id'] ?>" class="fw-semibold text-decoration-none"><?= h($o['nombres']) ?></a></td>
      <td class="small"><?= h($o['cargo']) ?></td>
      <td class="small text-nowrap">
        <?php if ($gruas): foreach ($gruas as $g): ?>
          <span class="badge bg-secondary"><?= h($g) ?></span>
        <?php endforeach; else: ?>
          <span class="text-muted">—</span>
        <?php endif; ?>
      </td>
      <td class="small"><?= h($o['fecha_ingreso']) ?></td>
      <td class="text-center"><?= $o['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>' ?></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-primary js-edit-op" data-bs-toggle="modal" data-bs-target="#mOperador"
          data-fill-form='<?= h(json_encode(['id'=>$o['id'],'codigo'=>$o['codigo'],'dni'=>$o['dni'],'nombres'=>$o['nombres'],'cargo'=>$o['cargo'],'lugar'=>$o['lugar'],'fecha_ingreso'=>$o['fecha_ingreso'],'estado_laboral'=>$o['activo']?'ACTIVO':'CESADO'])) ?>'
          data-gruas='<?= h(json_encode($gruas)) ?>'
          data-target="#mOperador"><i class="bi bi-pencil"></i></button>
        <?php if (is_admin()): ?>
        <form method="post" action="?action=operador_delete" class="d-inline" onsubmit="return confirm('¿Eliminar operador y todos sus registros?')">
          <input type="hidden" name="id" value="<?= $o['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; if (!$ops): ?>
    <tr><td colspan="9" class="text-center text-muted py-4">Sin operadores. Registra el primero o ejecuta el importador de Excel.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div></div>

<div class="modal fade" id="mOperador" data-bs-backdrop="static"><div class="modal-dialog"><div class="modal-content">
  <form method="post" action="?action=operador_save">
    <div class="modal-header"><h5 class="modal-title">Operador</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id">
      <div class="row g-2">
        <div class="col-4"><label class="form-label small">Código</label><input class="form-control" name="codigo"></div>
        <div class="col-8"><label class="form-label small">DNI *</label><input class="form-control" name="dni" required></div>
        <div class="col-12"><label class="form-label small">Apellidos y nombres *</label><input class="form-control" name="nombres" required></div>
        <div class="col-12"><label class="form-label small">Cargo *</label>
          <select class="form-select" name="cargo" required>
            <?php foreach ($CARGOS as $c): ?><option><?= h($c) ?></option><?php endforeach; ?>
          </select></div>
        <div class="col-6"><label class="form-label small">Lugar</label><input class="form-control" name="lugar" value="CHANCAY"></div>
        <div class="col-6"><label class="form-label small">Fecha de ingreso</label><input type="date" class="form-control" name="fecha_ingreso"></div>
        <div class="col-12"><label class="form-label small">Estado laboral *</label>
          <select class="form-select" name="estado_laboral" required>
            <option value="ACTIVO">Activo</option>
            <option value="CESADO">Inactivo</option>
          </select></div>
        <div class="col-12">
          <label class="form-label small">Tipo(s) de grúa que puede operar</label>
          <div class="d-flex flex-wrap gap-3">
            <?php foreach ($AREAS as $ak => $ac): ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="tipos_grua[]" value="<?= h($ak) ?>" id="opGrua<?= h($ak) ?>">
                <label class="form-check-label small" for="opGrua<?= h($ak) ?>"><?= h($ac['nombre']) ?></label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-cosco">Guardar</button>
    </div>
  </form>
</div></div></div>

<div class="modal fade" id="mGruasBulk" data-bs-backdrop="static"><div class="modal-dialog"><div class="modal-content">
  <form method="post" action="?action=operador_gruas_bulk_save">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-diagram-3"></i> Asignar grúas en bloque</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-2"><label class="form-label small">Operador(es) *</label>
        <select name="operator_id[]" class="form-select form-select-sm js-op-select" multiple required>
          <?php foreach ($ops as $o): ?><option value="<?= $o['id'] ?>"><?= h($o['nombres']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="mb-2">
        <label class="form-label small">Tipo(s) de grúa *</label>
        <div class="d-flex flex-wrap gap-3">
          <?php foreach ($AREAS as $ak => $ac): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="tipos_grua[]" value="<?= h($ak) ?>" id="bulkGrua<?= h($ak) ?>">
              <label class="form-check-label small" for="bulkGrua<?= h($ak) ?>"><?= h($ac['nombre']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="mb-1">
        <label class="form-label small">Modo</label>
        <select name="modo" class="form-select form-select-sm">
          <option value="reemplazar">Reemplazar grúas asignadas</option>
          <option value="agregar">Agregar a las que ya tienen</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-cosco">Aplicar a los seleccionados</button>
    </div>
  </form>
</div></div></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const estadoFilter = document.getElementById('js-estado-filter');
  const cargoFilter = document.getElementById('js-cargo-filter');
  const gruaFilter = document.getElementById('js-grua-filter');
  const showCesados = document.getElementById('js-show-cesados');
  const textFilter = document.getElementById('js-ops-search');
  const rows = () => document.querySelectorAll('table.js-filterable tbody tr[data-estado]');

  const applyFilters = () => {
    const estado = estadoFilter.value;
    const cargo = cargoFilter.value;
    const grua = gruaFilter.value;
    const q = (textFilter.value || '').toLowerCase();
    let activos = 0, cesados = 0, armg = 0, qc = 0, pc = 0, wl = 0, n = 0;

    rows().forEach(tr => {
      const gruasRow = (tr.dataset.gruas || '').split(',').filter(Boolean);
      const matchesEstado = !estado || tr.dataset.estado === estado;
      const matchesCargo = !cargo || tr.dataset.cargo === cargo;
      const matchesGrua = !grua || gruasRow.includes(grua);
      const matchesText = !q || tr.textContent.toLowerCase().includes(q);
      const matchesBase = matchesEstado && matchesCargo && matchesGrua && matchesText;
      const matchesLaboral = showCesados.checked || tr.dataset.activo === '1';
      const visible = matchesBase && matchesLaboral;
      tr.style.display = visible ? '' : 'none';

      // Cesados se cuenta según los demás filtros, sin depender del switch "Mostrar cesados"
      if (matchesBase && tr.dataset.activo !== '1') cesados++;

      const numCell = tr.querySelector('.js-row-num');
      if (visible) {
        n++;
        if (numCell) numCell.textContent = n;
        if (tr.dataset.activo === '1') activos++;
        if (gruasRow.includes('ARMG')) armg++;
        if (gruasRow.includes('QC')) qc++;
        if (gruasRow.includes('PC')) pc++;
        if (gruasRow.includes('WL')) wl++;
      } else if (numCell) {
        numCell.textContent = '';
      }
    });

    document.getElementById('js-card-activos').textContent = activos;
    document.getElementById('js-card-cesados').textContent = cesados;
    document.getElementById('js-card-armg').textContent = armg;
    document.getElementById('js-card-qc').textContent = qc;
    document.getElementById('js-card-pc').textContent = pc;
    document.getElementById('js-card-wl').textContent = wl;

    if (window.onTableFilterChange) window.onTableFilterChange();
  };

  estadoFilter.addEventListener('change', applyFilters);
  cargoFilter.addEventListener('change', applyFilters);
  gruaFilter.addEventListener('change', applyFilters);
  showCesados.addEventListener('change', applyFilters);
  textFilter.addEventListener('input', applyFilters);
  applyFilters();

  // Al editar: marcar los checkboxes de tipo(s) de grúa según el operador (data-fill-form
  // ya rellena los campos escalares; esto complementa con el array de grúas).
  document.querySelectorAll('.js-edit-op').forEach(btn => {
    btn.addEventListener('click', () => {
      const gruas = JSON.parse(btn.dataset.gruas || '[]');
      document.querySelectorAll('#mOperador [name="tipos_grua[]"]').forEach(cb => {
        cb.checked = gruas.includes(cb.value);
      });
    });
  });
});
</script>
