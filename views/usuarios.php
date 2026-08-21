<?php
$rows = db()->query("SELECT * FROM users ORDER BY nombre")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-person-gear text-primary"></i> Usuarios del sistema</h4>
  <button class="btn btn-sm btn-cosco" data-bs-toggle="modal" data-bs-target="#mUser"
          onclick="document.querySelector('#mUser form').reset(); document.querySelector('#mUser [name=id]').value='';">
    <i class="bi bi-plus-lg"></i> Nuevo usuario
  </button>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
  <thead><tr><th>Nombre</th><th>DNI</th><th>Correo</th><th>Rol</th><th>Activo</th><th>Creado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="fw-semibold"><?= h($r['nombre']) ?></td>
      <td><?= h($r['dni'] ?: '—') ?></td>
      <td><?= h($r['email']) ?></td>
      <td><span class="badge bg-<?= ['admin' => 'danger', 'supervisor' => 'warning text-dark', 'instructor' => 'primary', 'visita' => 'info text-dark'][$r['rol']] ?? 'secondary' ?>"><?= h($r['rol']) ?></span></td>
      <td><?= $r['activo'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>' ?></td>
      <td class="small text-muted"><?= h($r['created_at']) ?></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#mUser" data-target="#mUser"
          data-fill-form='<?= h(json_encode(['id'=>$r['id'],'nombre'=>$r['nombre'],'dni'=>$r['dni'],'email'=>$r['email'],'rol'=>$r['rol'],'activo'=>$r['activo']])) ?>'>
          <i class="bi bi-pencil"></i></button>
        <?php if ((int)$r['id'] !== (int)$user['id']): ?>
        <form method="post" action="?action=user_delete" class="d-inline" onsubmit="return confirm('¿Eliminar usuario?')">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div></div>

<div class="modal fade" id="mUser" data-bs-backdrop="static"><div class="modal-dialog"><div class="modal-content">
  <form method="post" action="?action=user_save">
    <div class="modal-header"><h5 class="modal-title">Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id">
      <div class="mb-2"><label class="form-label small">Nombre *</label><input class="form-control" name="nombre" required></div>
      <div class="mb-2"><label class="form-label small">DNI</label><input class="form-control" name="dni"></div>
      <div class="mb-2"><label class="form-label small">Correo *</label><input type="email" class="form-control" name="email" required></div>
      <div class="mb-2"><label class="form-label small">Contraseña <span class="text-muted">(en edición, dejar vacío para no cambiar)</span></label>
        <input type="password" class="form-control" name="password"></div>
      <div class="mb-2"><label class="form-label small">Rol</label>
        <select class="form-select" name="rol">
          <option value="instructor">Instructor</option>
          <option value="supervisor">Supervisor</option>
          <option value="admin">Administrador</option>
          <option value="visita">Visita</option>
        </select></div>
      <div class="form-check ms-1">
        <input class="form-check-input" type="checkbox" name="activo" id="usActivo" checked>
        <label class="form-check-label" for="usActivo">Activo</label>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-cosco">Guardar</button>
    </div>
  </form>
</div></div></div>
