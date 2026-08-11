<?php
if (!is_admin()) { redirect('?page=dashboard'); }

$adminTab = $_GET['tab'] ?? 'usuarios';
if (!in_array($adminTab, ['usuarios', 'registros', 'backup', 'auditoria'], true)) $adminTab = 'usuarios';
?>
<section class="banner-head admin-hero mb-3">
  <span class="banner-badge">ACCESO RESTRINGIDO</span>
  <h3 class="mb-1 mt-2"><i class="bi bi-shield-lock"></i> Administrador</h3>
  <p class="mb-0 opacity-75">Gestión de usuarios, eliminación controlada y trazabilidad de los cambios del sistema.</p>
</section>

<?php if ($adminTab === 'usuarios'): ?>
  <?php require __DIR__ . '/usuarios.php'; ?>

<?php elseif ($adminTab === 'registros'):
  $recordTypes = [
      'horas' => ['Horas', 'hours_records', 'h', "CONCAT(h.tipo_preparacion, ' · ', COALESCE(h.tipo_actividad, ''))"],
      'habilidades' => ['Habilidades', 'skill_records', 's', "CONCAT(s.tipo_capacitacion, ' · ', COALESCE(s.contexto, ''))"],
      'velocidad' => ['Velocidad', 'speed_records', 's', "CONCAT(s.tipo_capacitacion, ' · ', COALESCE(s.contexto, ''))"],
      'desempeno' => ['Desempeño', 'performance_records', 'p', "COALESCE(p.evaluador, '')"],
      'incidencias' => ['Incidencias', 'incidents', 'i', "CONCAT(i.tipo, ' · ', COALESCE(i.estado, ''))"],
      'operadores' => ['Operadores', 'operators', 'o', "CONCAT(COALESCE(o.codigo, ''), ' · ', o.cargo)"],
  ];
  $recordType = $_GET['tipo'] ?? 'horas';
  if (!isset($recordTypes[$recordType])) $recordType = 'horas';
  $recordArea = strtoupper($_GET['admin_area'] ?? 'ARMG');
  if (!isset($AREAS[$recordArea])) $recordArea = 'ARMG';
  [$recordLabel, $recordTable, $recordAlias, $recordDetail] = $recordTypes[$recordType];
  if ($recordType === 'operadores') {
    $sql = "SELECT o.id, DATE(o.created_at) fecha, o.tipos_grua area, o.nombres, {$recordDetail} detalle, o.created_at FROM operators o";
  } else {
    $sql = "SELECT {$recordAlias}.id, {$recordAlias}.fecha, {$recordAlias}.area, o.nombres, {$recordDetail} detalle, {$recordAlias}.created_at
            FROM {$recordTable} {$recordAlias} JOIN operators o ON o.id={$recordAlias}.operator_id";
  }
  $params = [];
  if ($recordArea) {
    $sql .= $recordType === 'operadores' ? ' WHERE FIND_IN_SET(?, o.tipos_grua)' : " WHERE {$recordAlias}.area=?";
    $params[] = $recordArea;
  }
  $sql .= $recordType === 'operadores' ? ' ORDER BY o.created_at DESC, o.id DESC LIMIT 300' : " ORDER BY {$recordAlias}.fecha DESC, {$recordAlias}.id DESC LIMIT 300";
  $st = db()->prepare($sql); $st->execute($params); $adminRecords = $st->fetchAll();
?>
  <div class="admin-section-head mb-3">
    <div><h4><i class="bi bi-trash3 text-danger"></i> Borrar registros</h4><p>Elimina registros específicos. Esta acción queda registrada en Auditoría.</p></div>
  </div>
  <div class="admin-danger-zone mb-3">
    <div><h5><i class="bi bi-exclamation-triangle"></i> Eliminación masiva por filtro</h5><p>Elige la grúa, el tipo de registro y el periodo. Para operadores se usa su fecha de creación; al eliminarlos también se eliminan sus registros relacionados.</p></div>
    <form method="post" action="?action=admin_records_mass_delete" class="js-admin-delete-form" data-confirm="¿Confirmas la eliminación masiva? Esta acción no se puede deshacer." data-progress="Eliminando registros por filtro…">
      <div class="admin-mass-grid">
        <div><label>Tipo</label><select name="registro_tipo" id="js-admin-record-type" class="form-select form-select-sm">
          <?php foreach ($recordTypes as $key => [$label]): ?><option value="<?= $key ?>" <?= $key === $recordType ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
        </select></div>
        <div><label>Grúa *</label><select name="area" id="js-admin-record-area" class="form-select form-select-sm" required><?php foreach ($AREAS as $key => $cfg): ?><option value="<?= $key ?>" <?= $key === $recordArea ? 'selected' : '' ?>><?= $key ?> — <?= h($cfg['nombre']) ?></option><?php endforeach; ?></select></div>
        <div><label>Periodo</label><select name="periodo" id="js-admin-period" class="form-select form-select-sm"><option value="all">Todos</option><option value="week">Semana</option><option value="month">Mes</option><option value="range">Rango de fechas</option></select></div>
        <div class="js-admin-period-field d-none" data-period="week"><label>Semana</label><input type="week" name="periodo_semana" class="form-control form-control-sm"></div>
        <div class="js-admin-period-field d-none" data-period="month"><label>Mes</label><input type="month" name="periodo_mes" class="form-control form-control-sm"></div>
        <div class="js-admin-period-field d-none" data-period="range"><label>Desde</label><input type="date" name="fecha_desde" class="form-control form-control-sm"></div>
        <div class="js-admin-period-field d-none" data-period="range"><label>Hasta</label><input type="date" name="fecha_hasta" class="form-control form-control-sm"></div>
        <div class="admin-mass-action"><button class="btn btn-danger btn-sm"><i class="bi bi-trash3"></i> Eliminar por filtro</button></div>
      </div>
    </form>
  </div>
  <form method="post" action="?action=admin_records_delete" class="js-admin-delete-form" data-confirm="¿Eliminar los registros seleccionados? Esta acción no se puede deshacer." data-progress="Eliminando registros seleccionados…">
    <input type="hidden" name="registro_tipo" value="<?= h($recordType) ?>">
    <div class="card admin-records-card"><div class="card-header d-flex justify-content-between align-items-center">
      <div><span><?= h($recordLabel) ?> <small class="text-muted fw-normal">(máximo 300 registros recientes)</small></span><small class="d-block text-muted mt-1">Grúa: <?= h($recordArea) ?> · <span id="js-admin-results"><?= count($adminRecords) ?></span> registros</small></div>
      <div class="admin-records-tools"><div class="input-group input-group-sm"><span class="input-group-text"><i class="bi bi-search"></i></span><input id="js-admin-search" type="search" class="form-control" placeholder="Buscar para eliminar..." aria-label="Buscar en registros"></div>
      <button class="btn btn-sm btn-outline-danger" id="js-admin-delete" disabled><i class="bi bi-trash3"></i> Eliminar seleccionados</button>
      </div>
    </div><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
      <thead><tr><th><input class="form-check-input" type="checkbox" id="js-admin-all"></th><th>Fecha</th><th>Área</th><th>Operador</th><th>Detalle</th><th>Registrado</th></tr></thead>
      <tbody id="js-admin-record-list"><?php foreach ($adminRecords as $record): ?><tr>
        <td><input class="form-check-input js-admin-record" type="checkbox" name="ids[]" value="<?= $record['id'] ?>"></td>
        <td><?= h($record['fecha']) ?></td><td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= h($record['area'] ?: '—') ?></span></td>
        <td><?= h($record['nombres']) ?></td><td class="small"><?= h($record['detalle']) ?></td><td class="small text-muted"><?= h($record['created_at']) ?></td>
      </tr><?php endforeach; if (!$adminRecords): ?><tr><td colspan="6" class="text-center text-muted py-4">No hay registros para los filtros seleccionados.</td></tr><?php endif; ?></tbody>
    </table></div></div>
  </form>
  <div id="js-admin-delete-progress" class="admin-delete-progress" hidden role="status" aria-live="assertive" aria-atomic="true">
    <div class="admin-delete-progress__message"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span><b id="js-admin-delete-progress-text">Eliminando registros…</b><small>No cierres ni recargues esta página hasta que termine.</small></span></div>
  </div>

<?php elseif ($adminTab === 'backup'): ?>
  <div class="admin-section-head mb-3"><div><h4><i class="bi bi-database-down text-primary"></i> Backup y restauración</h4><p>Exporta todas las tablas operativas en un archivo Excel o restaura un backup generado por este sistema.</p></div></div>
  <div class="admin-backup-grid">
    <section class="admin-backup-card">
      <span class="admin-backup-icon"><i class="bi bi-file-earmark-arrow-down"></i></span>
      <h5>Exportar backup</h5><p>Descarga un archivo XLSX con usuarios, operadores, registros, configuraciones y auditoría.</p>
      <a class="btn btn-cosco btn-sm" href="?action=admin_backup_export"><i class="bi bi-download"></i> Exportar Excel</a>
    </section>
    <section class="admin-backup-card is-restore">
      <span class="admin-backup-icon"><i class="bi bi-file-earmark-arrow-up"></i></span>
      <h5>Importar backup</h5><p>Restaura o actualiza los datos desde un XLSX exportado por este sistema.</p>
      <form method="post" action="?action=admin_backup_import" enctype="multipart/form-data">
        <input class="form-control form-control-sm mb-2" type="file" name="backup_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
        <button class="btn btn-outline-primary btn-sm"><i class="bi bi-upload"></i> Importar Excel</button>
      </form>
    </section>
  </div>

<?php else:
  $auditRows = db()->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 500")->fetchAll();
?>
  <div class="admin-section-head mb-3">
    <div><h4><i class="bi bi-journal-text text-primary"></i> Auditoría</h4><p>Historial de cambios y eliminaciones realizados dentro del sistema.</p></div>
  </div>
  <div class="card admin-audit-card"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
    <thead><tr><th>Fecha</th><th>Usuario</th><th>Módulo</th><th>Acción</th><th>Registro</th><th>Detalle</th></tr></thead>
    <tbody><?php foreach ($auditRows as $log): $detail = json_decode($log['detalle'] ?? '', true); ?><tr>
      <td class="text-nowrap small"><?= h($log['created_at']) ?></td><td><?= h($log['usuario'] ?: 'Sistema') ?></td>
      <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= h($log['modulo']) ?></span></td>
      <td><?= h(str_replace('_', ' ', $log['accion'])) ?></td><td><?= $log['registro_id'] ? '#' . (int)$log['registro_id'] : '—' ?></td>
      <td class="small text-muted"><?= h($detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : '—') ?></td>
    </tr><?php endforeach; if (!$auditRows): ?><tr><td colspan="6" class="text-center text-muted py-4">Aún no hay cambios registrados.</td></tr><?php endif; ?></tbody>
  </table></div></div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const all = document.getElementById('js-admin-all');
  const records = Array.from(document.querySelectorAll('.js-admin-record'));
  const remove = document.getElementById('js-admin-delete');
  const search = document.getElementById('js-admin-search');
  const results = document.getElementById('js-admin-results');
  const visibleRecords = () => records.filter(item => !item.closest('tr').classList.contains('d-none'));
  const sync = () => {
    if (!all || !remove) return;
    const visible = visibleRecords();
    const checked = visible.filter(item => item.checked);
    remove.disabled = checked.length === 0;
    all.checked = visible.length > 0 && checked.length === visible.length;
    all.indeterminate = checked.length > 0 && checked.length < visible.length;
  };
  if (all && remove) {
    all.addEventListener('change', () => { visibleRecords().forEach(item => { item.checked = all.checked; }); sync(); });
    records.forEach(item => item.addEventListener('change', sync));
    sync();
  }
  if (search) search.addEventListener('input', () => {
    const term = search.value.trim().toLocaleLowerCase(); let visible = 0;
    records.forEach(item => {
      const row = item.closest('tr'); const matches = !term || row.textContent.toLocaleLowerCase().includes(term);
      row.classList.toggle('d-none', !matches); if (matches) visible++;
    });
    if (results) results.textContent = visible; sync();
  });

  const type = document.getElementById('js-admin-record-type');
  const area = document.getElementById('js-admin-record-area');
  const applyRecordFilter = () => {
    if (!type || !area) return;
    window.location.href = '?page=admin&tab=registros&tipo=' + encodeURIComponent(type.value) + '&admin_area=' + encodeURIComponent(area.value);
  };
  if (type) type.addEventListener('change', applyRecordFilter);
  if (area) area.addEventListener('change', applyRecordFilter);

  const progress = document.getElementById('js-admin-delete-progress');
  const progressText = document.getElementById('js-admin-delete-progress-text');
  document.querySelectorAll('.js-admin-delete-form').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirm || '¿Confirmas esta eliminación?')) { event.preventDefault(); return; }
    if (progress) {
      progressText.textContent = form.dataset.progress || 'Eliminando registros…';
      progress.hidden = false;
      document.body.classList.add('admin-is-processing');
    }
    form.querySelectorAll('button').forEach(button => { button.disabled = true; });
  }));

  const period = document.getElementById('js-admin-period');
  if (period) {
    const syncPeriod = () => document.querySelectorAll('.js-admin-period-field').forEach(field => field.classList.toggle('d-none', field.dataset.period !== period.value));
    period.addEventListener('change', syncPeriod); syncPeriod();
  }
});
</script>
