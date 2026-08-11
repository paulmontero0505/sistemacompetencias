<?php
$ops = operadores_activos();
$instructores = db()->query("SELECT nombre FROM users WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$rows = db()->query("SELECT i.*, o.nombres, o.cargo FROM incidents i JOIN operators o ON o.id=i.operator_id ORDER BY i.fecha DESC, i.id DESC LIMIT 400")->fetchAll();

// Cargar tipos personalizados de incidencia guardados por usuarios
$customTipos = db()->query("SELECT nombre FROM custom_contexts WHERE area='INCIDENT_TIPO' ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$todosLosTipos = array_merge($INCIDENT_TIPOS, $customTipos); // OTROS siempre al final
// Aseguramos que OTROS quede al final
$todosLosTipos = array_unique(array_merge(array_filter($todosLosTipos, fn($t) => $t !== 'OTROS'), ['OTROS']));

$totalRegistros = count($rows);
$totalGrave = 0;
$totalModerada = 0;
$totalAbiertas = 0;
foreach ($rows as $r) {
    if ($r['severidad'] === 'GRAVE') $totalGrave++;
    if ($r['severidad'] === 'MODERADA') $totalModerada++;
    if ($r['estado'] === 'ABIERTA') $totalAbiertas++;
}
?>

<!-- Cabecera Hero Banner -->
<div class="banner-head mb-3" style="background: linear-gradient(135deg, #0B3D73 0%, #1565C0 100%) !important;">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
      <span class="banner-badge bg-white-20 text-white mb-2">CONTROL DE CAMPO · INCIDENCIAS</span>
      <h3 class="mb-1 mt-2 text-white">Registro y bitácora de incidencias</h3>
      <p class="mb-0 text-white-80">Monitoreo y seguimiento de puntos a mejorar del personal con clasificación por nivel de impacto, turno operativo y evidencias adjuntas.</p>
    </div>
    <div>
      <button type="button" class="btn btn-light btn-cosco-light" data-bs-toggle="modal" data-bs-target="#mInc"
              onclick="document.querySelector('#mInc form').reset(); document.querySelector('#mInc [name=id]').value='';">
        <i class="bi bi-plus-circle"></i> Registrar incidencia
      </button>
    </div>
  </div>
</div>

<!-- Tarjetas KPI -->
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="card h-100 border shadow-sm position-relative overflow-hidden kpi-card" style="border-left: 5px solid #0B3D73 !important; border-radius: 14px;">
      <div class="card-body p-3">
        <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">TOTAL REGISTRADAS</div>
        <div class="fs-2 fw-extrabold mt-1 text-primary" id="js-stat-total"><?= $totalRegistros ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card h-100 border shadow-sm position-relative overflow-hidden kpi-card" style="border-left: 5px solid #dc3545 !important; border-radius: 14px;">
      <div class="card-body p-3">
        <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">IMPACTO CRÍTICO (GRAVE)</div>
        <div class="fs-2 fw-extrabold mt-1 text-danger" id="js-stat-grave"><?= $totalGrave ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card h-100 border shadow-sm position-relative overflow-hidden kpi-card" style="border-left: 5px solid #fd7e14 !important; border-radius: 14px;">
      <div class="card-body p-3">
        <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">IMPACTO ALTO (MODERADA)</div>
        <div class="fs-2 fw-extrabold mt-1 text-warning-emphasis" id="js-stat-moderada"><?= $totalModerada ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card h-100 border shadow-sm position-relative overflow-hidden kpi-card" style="border-left: 5px solid #0dcaf0 !important; border-radius: 14px;">
      <div class="card-body p-3">
        <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">ESTADO ABIERTO</div>
        <div class="fs-2 fw-extrabold mt-1 text-info-emphasis" id="js-stat-abiertas"><?= $totalAbiertas ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Toolbar de Búsqueda y Filtros -->
<div class="card p-3 mb-3 border shadow-sm" style="border-radius: 14px;">
  <div class="row g-2 align-items-center mb-2">
    <div class="col-12">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
        <input class="form-control" id="js-table-filter" placeholder="Buscar por colaborador, tipo, equipo, reportado por...">
      </div>
    </div>
  </div>
  <div class="d-flex flex-wrap align-items-center gap-3 pt-2 border-top small text-muted">
    <div class="d-flex align-items-center gap-2">
      <span class="fw-semibold text-uppercase" style="font-size: 0.72rem;">Nivel de Impacto:</span>
      <div class="btn-group btn-group-xs" role="group" id="js-severidad-filter">
        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 active" data-severidad="TODAS" style="font-size: 0.75rem;">Todos</button>
        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" data-severidad="LEVE" style="font-size: 0.75rem;">Leve</button>
        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" data-severidad="MODERADA" style="font-size: 0.75rem;">Moderada</button>
        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" data-severidad="GRAVE" style="font-size: 0.75rem;">Grave</button>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="fw-semibold text-uppercase" style="font-size: 0.72rem;">Estado:</span>
      <div class="btn-group btn-group-xs" role="group" id="js-estado-filter">
        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 active" data-estado="TODAS" style="font-size: 0.75rem;">Todos</button>
        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" data-estado="ABIERTA" style="font-size: 0.75rem;">Abiertas</button>
        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" data-estado="EN PROCESO" style="font-size: 0.75rem;">En Proceso</button>
        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" data-estado="CERRADA" style="font-size: 0.75rem;">Cerradas</button>
      </div>
    </div>
  </div>
</div>

<!-- Tabla Premium -->
<div class="card border shadow-sm mb-4" style="border-radius: 14px; overflow: hidden;">
  <div class="table-responsive" style="max-height: 65vh;">
    <table class="table table-hover align-middle mb-0 js-filterable">
      <thead class="bg-light sticky-top">
        <tr class="text-uppercase text-muted" style="font-size: 0.75rem; border-bottom: 2px solid #e2e8f0;">
          <th class="ps-3 py-3" style="width: 250px;">Colaborador</th>
          <th class="py-3">Incidencia</th>
          <th class="py-3" style="width: 120px;">Impacto</th>
          <th class="py-3" style="width: 150px;">Equipo / Área</th>
          <th class="py-3" style="width: 110px;">Fecha</th>
          <th class="py-3" style="width: 140px;">Coordinador</th>
          <th class="py-3" style="width: 120px;">Estado</th>
          <th class="pe-3 py-3 text-end" style="width: 130px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
        $rFotos = json_decode($r['fotos'] ?? '[]', true) ?: [];
        $rDecl  = json_decode($r['declaracion'] ?? 'null', true) ?: null;
        $attCount = count($rFotos) + ($rDecl ? 1 : 0);
        $parts = preg_split('/\s+/', trim($r['nombres']));
        $initials = '';
        if (count($parts) > 1) {
            $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        } else {
            $initials = strtoupper(substr($parts[0] ?? 'OP', 0, 2));
        }
      ?>
        <tr data-severidad="<?= h($r['severidad']) ?>" data-estado="<?= h($r['estado']) ?>" style="border-bottom: 1px solid #f1f5f9;">
          <td class="ps-3 py-2">
            <div class="d-flex align-items-center gap-2">
              <div class="avatar-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" 
                   style="width: 34px; height: 34px; border-radius: 50%; font-size: 0.78rem; background: linear-gradient(135deg, #10559A 0%, #0B3D73 100%);">
                <?= $initials ?>
              </div>
              <div>
                <div class="fw-semibold text-dark" style="font-size: 0.85rem; line-height: 1.2;"><?= h($r['nombres']) ?></div>
                <div class="text-muted" style="font-size: 0.72rem;"><?= h($r['cargo'] ?: 'Operador') ?></div>
              </div>
            </div>
          </td>
          <td class="py-2">
            <div>
              <div class="fw-semibold text-dark d-flex align-items-center gap-2" style="font-size: 0.85rem; line-height: 1.2;">
                <?= h($r['tipo']) ?>
                <?php if ($attCount): ?>
                  <span class="badge bg-light text-secondary border" style="font-size:.68rem;" title="<?= count($rFotos) ?> foto(s)<?= $rDecl ? ' + declaración' : '' ?>">
                    <i class="bi bi-paperclip"></i> <?= $attCount ?>
                  </span>
                <?php endif; ?>
              </div>
              <div class="text-muted text-truncate" style="font-size: 0.72rem; max-width: 280px;" title="<?= h($r['descripcion']) ?>">
                <?= h($r['descripcion']) ?>
              </div>
            </div>
          </td>
          <td class="py-2">
            <?php if ($r['severidad'] === 'LEVE'): ?>
              <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 0.7rem;">LEVE</span>
            <?php elseif ($r['severidad'] === 'MODERADA'): ?>
              <span class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle px-2 py-1" style="font-size: 0.7rem; color: #fd7e14 !important;">MODERADA</span>
            <?php else: ?>
              <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" style="font-size: 0.7rem;">GRAVE</span>
            <?php endif; ?>
          </td>
          <td class="py-2">
            <div>
              <div class="fw-semibold text-dark" style="font-size: 0.85rem; line-height: 1.2;"><?= h($r['equipo'] ?: '—') ?></div>
              <div class="text-muted" style="font-size: 0.72rem;"><?= h($r['area']) ?></div>
            </div>
          </td>
          <td class="py-2" style="font-size: 0.8rem; color: #475569;"><?= h($r['fecha']) ?></td>
          <td class="py-2" style="font-size: 0.8rem; color: #475569;"><?= h($r['reportado_por'] ?: '—') ?></td>
          <td class="py-2">
            <?php if ($r['estado'] === 'ABIERTA'): ?>
              <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" style="font-size: 0.7rem;">ABIERTA</span>
            <?php elseif ($r['estado'] === 'EN PROCESO'): ?>
              <span class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle px-2 py-1" style="font-size: 0.7rem; color: #fd7e14 !important;">EN PROCESO</span>
            <?php else: ?>
              <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 0.7rem;">CERRADA</span>
            <?php endif; ?>
          </td>
          <td class="pe-3 py-2 text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 me-1" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#modalVerIncidencia" title="Ver"
              data-ver='<?= h(json_encode([
                  'Fecha' => $r['fecha'],
                  'Operador' => $r['nombres'],
                  'Cargo' => $r['cargo'],
                  'Área' => $r['area'],
                  'Equipo' => $r['equipo'],
                  'Tipo' => $r['tipo'],
                  'Severidad' => $r['severidad'],
                  'Estado' => $r['estado'],
                  'Descripción' => $r['descripcion'],
                  'Acciones correctivas' => $r['acciones'],
                  'Reportado por' => $r['reportado_por'],
              ])) ?>'
              data-ver-att='<?= h(json_encode(['fotos' => $rFotos, 'declaracion' => $rDecl])) ?>'><i class="bi bi-eye"></i> Ver</button>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 me-1" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#mInc" data-target="#mInc" title="Editar"
              data-fill-form='<?= h(json_encode([
                  'id' => $r['id'],
                  'operator_id' => $r['operator_id'],
                  'area' => $r['area'],
                  'fecha' => $r['fecha'],
                  'equipo' => $r['equipo'],
                  'tipo' => $r['tipo'],
                  'severidad' => $r['severidad'],
                  'descripcion' => $r['descripcion'],
                  'acciones' => $r['acciones'],
                  'estado' => $r['estado'],
                  'reportado_por' => $r['reportado_por']
              ])) ?>'
              data-attachments='<?= h(json_encode(['fotos' => $rFotos, 'declaracion' => $rDecl])) ?>'><i class="bi bi-pencil"></i></button>
            <?php if (is_admin()): ?>
            <form method="post" action="?action=incident_delete" class="d-inline" onsubmit="return confirm('¿Eliminar incidencia?')">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size: 0.75rem;" title="Eliminar"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">Sin incidencias registradas</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Registro / Edición -->
<div class="modal fade" id="mInc" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog" style="max-width: 580px;">
    <div class="modal-content">
      <form method="post" action="?action=incident_save" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning"></i> Detalle de Incidencia</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-semibold">Operador *</label>
              <select name="operator_id" class="form-select form-select-sm js-op-select" required>
                <option value="">Buscar operador...</option>
                <?php foreach ($ops as $o): ?>
                  <option value="<?= $o['id'] ?>"><?= h($o['nombres']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Área *</label>
              <select name="area" class="form-select form-select-sm" required>
                <?php foreach (array_keys($AREAS) as $a): ?>
                  <option><?= $a ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Fecha *</label>
              <input type="date" name="fecha" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Equipo (ej. QC 105, ARMG 205) *</label>
              <input name="equipo" class="form-control form-control-sm" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Tipo de Incidencia *</label>
              <select name="tipo" class="form-select form-select-sm js-context-select" data-field-name="tipo" data-area="INCIDENT_TIPO" data-action="incident_tipo_add" required>
                <option value="">— Seleccionar —</option>
                <?php foreach ($todosLosTipos as $t): ?>
                  <option><?= h($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 js-context-otro-wrap d-none">
              <div class="input-group input-group-sm">
                <input type="text" class="form-control form-control-sm js-context-otro text-uppercase" placeholder="Nuevo tipo de incidencia..." maxlength="80">
                <button type="button" class="btn btn-outline-secondary btn-sm js-context-otro-ok" title="Confirmar">
                  <i class="bi bi-check-lg"></i>
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm js-context-otro-cancel" title="Cancelar">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Descripción de la Incidencia *</label>
              <textarea name="descripcion" class="form-control form-control-sm" rows="3" placeholder="Detalla lo sucedido..." required></textarea>
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Reportado por *</label>
              <select name="reportado_por" class="form-select form-select-sm js-op-select" required>
                <option value="">Buscar instructor...</option>
                <?php foreach ($instructores as $i): ?>
                  <option value="<?= h($i) ?>" <?= $i === $user['nombre'] ? 'selected' : '' ?>><?= h($i) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- ── Adjuntos: se guardan en Google Drive ── -->
            <div class="col-12"><hr class="my-2"></div>
            <div class="col-12">
              <label class="form-label small fw-semibold">
                <i class="bi bi-images text-primary"></i> Fotos de evidencia
                <span class="text-muted fw-normal">(máx. <?= DRIVE_MAX_FOTOS ?>, JPG/PNG — <?= DRIVE_MAX_FOTO_MB ?> MB c/u)</span>
              </label>
              <div id="incFotosActuales" class="inc-att-grid mb-2"></div>
              <input type="file" name="fotos[]" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp,image/gif" multiple>
              <div class="form-text">Se subirán a tu carpeta de Google Drive.</div>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">
                <i class="bi bi-file-earmark-text text-primary"></i> Declaración
                <span class="text-muted fw-normal">(PDF, DOC o DOCX — <?= DRIVE_MAX_DOC_MB ?> MB)</span>
              </label>
              <div id="incDeclActual" class="mb-2"></div>
              <input type="file" name="declaracion" class="form-control form-control-sm" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-cosco btn-sm"><i class="bi bi-save"></i> Guardar incidencia</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Ver Registro -->
<div class="modal fade" id="modalVerIncidencia" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog" style="max-width: 500px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-eye"></i> Detalle de la Incidencia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <table class="table table-striped table-sm mb-0" id="js-ver-body"></table>
        <div id="js-ver-att" class="p-3 border-top"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const statTotal = document.getElementById('js-stat-total');
  const statGrave = document.getElementById('js-stat-grave');
  const statModerada = document.getElementById('js-stat-moderada');
  const statAbiertas = document.getElementById('js-stat-abiertas');

  const recalcStats = () => {
    let total = 0, grave = 0, moderada = 0, abiertas = 0;
    document.querySelectorAll('table.js-filterable tbody tr').forEach(tr => {
      if (tr.style.display === 'none' || !tr.dataset.severidad) return;
      total++;
      if (tr.dataset.severidad === 'GRAVE') grave++;
      if (tr.dataset.severidad === 'MODERADA') moderada++;
      if (tr.dataset.estado === 'ABIERTA') abiertas++;
    });
    if (statTotal) statTotal.textContent = total;
    if (statGrave) statGrave.textContent = grave;
    if (statModerada) statModerada.textContent = moderada;
    if (statAbiertas) statAbiertas.textContent = abiertas;
  };

  // Filters by Severidad and Estado
  const sevButtons = document.querySelectorAll('#js-severidad-filter button');
  const estButtons = document.querySelectorAll('#js-estado-filter button');
  const searchInput = document.getElementById('js-table-filter');

  const applyFilters = () => {
    const activeSev = document.querySelector('#js-severidad-filter button.active').dataset.severidad;
    const activeEst = document.querySelector('#js-estado-filter button.active').dataset.estado;
    const searchText = searchInput ? searchInput.value.toLowerCase().trim() : '';

    let visibleCount = 0;
    document.querySelectorAll('table.js-filterable tbody tr').forEach(tr => {
      // Skip empty state row if it's there
      if (tr.id === 'js-empty-row') return;
      
      const matchesSev = (activeSev === 'TODAS' || tr.dataset.severidad === activeSev);
      const matchesEst = (activeEst === 'TODAS' || tr.dataset.estado === activeEst);
      
      const text = tr.textContent.toLowerCase();
      const matchesText = !searchText || text.includes(searchText);

      if (matchesSev && matchesEst && matchesText) {
        tr.style.display = '';
        visibleCount++;
      } else {
        tr.style.display = 'none';
      }
    });

    // Handle dynamic empty state display
    let emptyRow = document.getElementById('js-empty-row');
    if (visibleCount === 0) {
      if (!emptyRow) {
        emptyRow = document.createElement('tr');
        emptyRow.id = 'js-empty-row';
        emptyRow.innerHTML = '<td colspan="8" class="text-center text-muted py-4">Sin coincidencias registradas</td>';
        document.querySelector('table.js-filterable tbody').appendChild(emptyRow);
      } else {
        emptyRow.style.display = '';
      }
    } else if (emptyRow) {
      emptyRow.style.display = 'none';
    }

    recalcStats();
  };

  sevButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      sevButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      applyFilters();
    });
  });

  estButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      estButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      applyFilters();
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', applyFilters);
  }

  // Modal "Ver"
  const verModal = document.getElementById('modalVerIncidencia');
  if (verModal) {
    verModal.addEventListener('show.bs.modal', ev => {
      const data = JSON.parse(ev.relatedTarget.dataset.ver || '{}');
      const body = document.getElementById('js-ver-body');
      body.innerHTML = Object.entries(data).map(([k, v]) => {
        let val = v || '—';
        if (k === 'Estado' || k === 'Severidad') {
          const map = {
            'ABIERTA': 'bg-danger-subtle text-danger border border-danger-subtle',
            'EN PROCESO': 'bg-warning-subtle text-warning border border-warning-subtle',
            'CERRADA': 'bg-success-subtle text-success border border-success-subtle',
            'LEVE': 'bg-success-subtle text-success border border-success-subtle',
            'MODERADA': 'bg-warning-subtle text-warning border border-warning-subtle',
            'GRAVE': 'bg-danger-subtle text-danger border border-danger-subtle'
          };
          const cls = map[v] ?? 'bg-secondary-subtle text-secondary';
          let customStyle = '';
          if (v === 'MODERADA' || v === 'EN PROCESO') {
              customStyle = 'style="color:#fd7e14 !important;"';
          }
          val = `<span class="badge rounded-pill ${cls}" ${customStyle}>${v}</span>`;
        }
        return `<tr style="border-bottom:1px solid #f1f5f9;"><th class="text-muted small ps-3 py-2" style="width:40%; font-weight:600;">${k}</th><td class="pe-3 py-2">${val}</td></tr>`;
      }).join('');

      // Adjuntos (Google Drive)
      let att = {};
      try { att = JSON.parse(ev.relatedTarget.dataset.verAtt || '{}'); } catch (e) {}
      const attBox = document.getElementById('js-ver-att');
      let ah = '';
      if (att.fotos && att.fotos.length) {
        ah += '<div class="fw-semibold text-muted small text-uppercase mb-2">Evidencia fotográfica</div><div class="d-flex flex-wrap gap-2 mb-3">';
        att.fotos.forEach(f => {
          ah += `<a href="${f.url}" target="_blank" rel="noopener"><img src="${f.thumb || f.url}" style="width:92px;height:92px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0"></a>`;
        });
        ah += '</div>';
      }
      if (att.declaracion && att.declaracion.id) {
        ah += `<a href="${att.declaracion.url}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-text"></i> Ver declaración</a>`;
      }
      attBox.innerHTML = ah || '<div class="text-muted small">Sin archivos adjuntos.</div>';
    });
  }

  // Modal de registro/edición: pinta adjuntos existentes con opción de quitar
  const incModal = document.getElementById('mInc');
  if (incModal) {
    const fotosBox = document.getElementById('incFotosActuales');
    const declBox  = document.getElementById('incDeclActual');
    incModal.addEventListener('show.bs.modal', ev => {
      let att = { fotos: [], declaracion: null };
      const rt = ev.relatedTarget;
      if (rt && rt.dataset.attachments) { try { att = JSON.parse(rt.dataset.attachments); } catch (e) {} }
      fotosBox.innerHTML = (att.fotos || []).map(f =>
        `<div class="inc-att-thumb">
           <img src="${f.thumb || f.url}" alt="foto">
           <label class="inc-att-del" title="Quitar"><input type="checkbox" name="del_foto[]" value="${f.id}"><i class="bi bi-trash"></i></label>
         </div>`).join('');
      if (att.declaracion && att.declaracion.id) {
        declBox.innerHTML =
          `<div class="d-flex align-items-center gap-2 small border rounded p-2 bg-light">
             <a href="${att.declaracion.url}" target="_blank" rel="noopener" class="text-decoration-none text-truncate"><i class="bi bi-file-earmark-text"></i> ${att.declaracion.name}</a>
             <label class="text-danger ms-auto mb-0" title="Quitar"><input type="checkbox" name="del_declaracion" value="1"> quitar</label>
           </div>`;
      } else {
        declBox.innerHTML = '';
      }
      incModal.querySelectorAll('input[type=file]').forEach(i => i.value = '');
    });
  }
});
</script>
