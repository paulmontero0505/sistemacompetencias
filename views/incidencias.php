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
$totalEnProceso = 0;
$totalCerradas = 0;
foreach ($rows as $r) {
    if ($r['severidad'] === 'GRAVE') $totalGrave++;
    if ($r['severidad'] === 'MODERADA') $totalModerada++;
    if ($r['estado'] === 'EN PROCESO' || $r['estado'] === 'ABIERTA') $totalEnProceso++;
    if ($r['estado'] === 'CERRADO' || $r['estado'] === 'CERRADA') $totalCerradas++;
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
    <div class="card h-100 border shadow-sm position-relative overflow-hidden kpi-card" style="border-left: 5px solid #ffc107 !important; border-radius: 14px;">
      <div class="card-body p-3">
        <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">EN PROCESO</div>
        <div class="fs-2 fw-extrabold mt-1 text-warning-emphasis" id="js-stat-enproceso"><?= $totalEnProceso ?></div>
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
        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" data-estado="EN PROCESO" style="font-size: 0.75rem;">En Proceso</button>
        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" data-estado="CERRADO" style="font-size: 0.75rem;">Cerrados</button>
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
            <?php if ($r['estado'] === 'CERRADO' || $r['estado'] === 'CERRADA'): ?>
              <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 0.7rem;">CERRADO</span>
            <?php else: ?>
              <span class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle px-2 py-1" style="font-size: 0.7rem; color: #d97706 !important;">EN PROCESO</span>
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
                  'Estado' => ($r['estado'] === 'CERRADO' || $r['estado'] === 'CERRADA') ? 'CERRADO' : 'EN PROCESO',
                  'Descripción' => $r['descripcion'],
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
              <div class="form-check mt-2 mb-0">
                <input class="form-check-input" type="checkbox" name="sin_declaracion" id="incSinDeclaracion" value="1">
                <label class="form-check-label small fw-semibold text-muted" for="incSinDeclaracion">
                  <i class="bi bi-check2-circle text-success"></i> Marcar sin declaración <span class="text-muted fw-normal">(no será necesario subir el documento)</span>
                </label>
              </div>
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
  <div class="modal-dialog modal-dialog-centered modal-lg" style="max-width: 1000px;">
    <div class="modal-content inc-detail-modal border-0" style="border-radius: 20px; overflow: hidden;">
      <div class="inc-detail-accent" id="js-ver-accent"></div>
      <div class="modal-header border-bottom py-3 px-4">
        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" style="font-size: 1.05rem;">
          <span class="inc-detail-title-ico"><i class="bi bi-file-earmark-text-fill text-white"></i></span>
          Detalle de la Incidencia
          <span id="js-ver-estado-badge" class="ms-1"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div id="js-ver-body" class="inc-detail-grid"></div>
        <div id="js-ver-att" class="pt-4 mt-1"></div>
      </div>
      <div class="modal-footer border-top py-2 px-4">
        <button type="button" class="btn btn-secondary btn-sm px-4 fw-semibold" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const statTotal = document.getElementById('js-stat-total');
  const statGrave = document.getElementById('js-stat-grave');
  const statModerada = document.getElementById('js-stat-moderada');
  const statEnProceso = document.getElementById('js-stat-enproceso');

  const recalcStats = () => {
    let total = 0, grave = 0, moderada = 0, enProceso = 0;
    document.querySelectorAll('table.js-filterable tbody tr').forEach(tr => {
      if (tr.style.display === 'none' || !tr.dataset.severidad) return;
      total++;
      if (tr.dataset.severidad === 'GRAVE') grave++;
      if (tr.dataset.severidad === 'MODERADA') moderada++;
      const est = tr.dataset.estado;
      if (est === 'EN PROCESO' || est === 'ABIERTA') enProceso++;
    });
    if (statTotal) statTotal.textContent = total;
    if (statGrave) statGrave.textContent = grave;
    if (statModerada) statModerada.textContent = moderada;
    if (statEnProceso) statEnProceso.textContent = enProceso;
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
      let matchesEst = false;
      if (activeEst === 'TODAS') {
        matchesEst = true;
      } else if (activeEst === 'EN PROCESO') {
        matchesEst = (tr.dataset.estado === 'EN PROCESO' || tr.dataset.estado === 'ABIERTA');
      } else if (activeEst === 'CERRADO') {
        matchesEst = (tr.dataset.estado === 'CERRADO' || tr.dataset.estado === 'CERRADA');
      }
      
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
  const fieldIcons = {
    'Fecha': 'bi-calendar3', 'Operador': 'bi-person', 'Cargo': 'bi-briefcase', 'Área': 'bi-geo-alt',
    'Equipo': 'bi-tools', 'Tipo': 'bi-tag', 'Severidad': 'bi-exclamation-triangle', 'Estado': 'bi-flag',
    'Descripción': 'bi-card-text', 'Reportado por': 'bi-person-badge',
  };
  const verModal = document.getElementById('modalVerIncidencia');
  if (verModal) {
    verModal.addEventListener('show.bs.modal', ev => {
      const data = JSON.parse(ev.relatedTarget.dataset.ver || '{}');
      const isClosed = (data['Estado'] === 'CERRADO' || data['Estado'] === 'CERRADA');

      // Franja superior + badge de estado en el encabezado
      const accent = document.getElementById('js-ver-accent');
      if (accent) accent.className = 'inc-detail-accent ' + (isClosed ? 'is-closed' : 'is-open');
      const estadoBadge = document.getElementById('js-ver-estado-badge');
      if (estadoBadge) {
        estadoBadge.innerHTML = isClosed
          ? '<span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-1 fw-semibold" style="font-size:0.7rem;"><i class="bi bi-check-circle me-1"></i>CERRADO</span>'
          : '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-1 fw-semibold" style="font-size:0.7rem;"><i class="bi bi-clock me-1"></i>EN PROCESO</span>';
      }

      // Rejilla de campos en 2 columnas
      const body = document.getElementById('js-ver-body');
      body.innerHTML = Object.entries(data).map(([k, v]) => {
        let val = v || '—';
        const isDesc = (k === 'Descripción');
        if (k === 'Estado') {
          val = isClosed
            ? '<span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size:0.72rem;"><i class="bi bi-check-circle me-1"></i>CERRADO</span>'
            : '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1" style="font-size:0.72rem;"><i class="bi bi-clock me-1"></i>EN PROCESO</span>';
        } else if (k === 'Severidad') {
          const map = {
            'LEVE': 'bg-success-subtle text-success border border-success-subtle',
            'MODERADA': 'bg-warning-subtle text-warning border border-warning-subtle',
            'GRAVE': 'bg-danger-subtle text-danger border border-danger-subtle'
          };
          const cls = map[v] ?? 'bg-secondary-subtle text-secondary';
          let customStyle = (v === 'MODERADA') ? 'style="color:#fd7e14 !important;"' : '';
          val = `<span class="badge rounded-pill ${cls} px-2 py-1" ${customStyle} style="font-size:0.72rem;">${v}</span>`;
        }
        if (isDesc) {
          val = `<div class="inc-detail-desc">${v}</div>`;
        }
        return `<div class="inc-detail-item ${isDesc ? 'inc-detail-item--full' : ''}">
                  <span class="inc-detail-label"><i class="bi ${fieldIcons[k] || 'bi-dot'}"></i> ${k}</span>
                  <div class="inc-detail-value">${val}</div>
                </div>`;
      }).join('');

      // Adjuntos (Google Drive)
      let att = {};
      try { att = JSON.parse(ev.relatedTarget.dataset.verAtt || '{}'); } catch (e) {}
      const attBox = document.getElementById('js-ver-att');
      let ah = '';

      const hasFotos = att.fotos && att.fotos.length;
      const hasDecl = att.declaracion && att.declaracion.url;

      if (hasFotos || hasDecl) {
        ah += '<div class="inc-detail-section-title"><i class="bi bi-paperclip"></i> Adjuntos</div>';
        ah += '<div class="inc-att-row">';

        if (hasFotos) {
          ah += '<div class="inc-att-fotos">';
          ah += '<div class="inc-att-label"><i class="bi bi-images me-1"></i> Evidencia fotográfica</div>';
          ah += '<div class="d-flex flex-wrap gap-2">';
          att.fotos.forEach(f => {
            ah += `<a href="${f.url}" target="_blank" rel="noopener" class="inc-foto-thumb" title="Ver foto en tamaño completo">
                     <img src="${f.thumb || f.url}" alt="evidencia">
                   </a>`;
          });
          ah += '</div></div>';
        }

        if (hasDecl) {
          const docName = att.declaracion.name || 'Documento de Declaración';
          ah += `<div class="inc-decl-card">
                   <div class="inc-decl-ico"><i class="bi bi-file-earmark-pdf-fill"></i></div>
                   <div class="inc-decl-body">
                     <div class="inc-decl-name">${docName}</div>
                     <div class="inc-decl-sub">Documento oficial · Declaración</div>
                     <a href="${att.declaracion.url}" target="_blank" rel="noopener" class="inc-decl-btn">
                       <i class="bi bi-box-arrow-up-right"></i> Abrir documento
                     </a>
                   </div>
                 </div>`;
        }

        ah += '</div>';
      } else {
        ah = '<div class="inc-detail-empty"><i class="bi bi-info-circle me-1"></i> No hay fotos ni declaración adjunta a esta incidencia.</div>';
      }

      attBox.innerHTML = ah;
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

      // Pre-marcar "cerrar sin declaración" cuando se edita una incidencia cerrada sin documento.
      const sinDecl = incModal.querySelector('#incSinDeclaracion');
      if (sinDecl) {
        const estado = rt && rt.dataset.fillForm ? (JSON.parse(rt.dataset.fillForm).estado ?? '') : '';
        const tieneDecl = !!(att.declaracion && att.declaracion.id);
        sinDecl.checked = (estado === 'CERRADO' || estado === 'CERRADA') && !tieneDecl;
      }
    });
  }
});
</script>
