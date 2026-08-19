<?php
require_once __DIR__ . '/sync_sheets.php';

// Manejadores de acciones POST (?action=...)
function handle_action(string $action): void {
    global $AREAS;
    $u = current_user();

    if ($action === 'login') {
        try {
            $st = db()->prepare("SELECT * FROM users WHERE email=? AND activo=1");
            $st->execute([trim($_POST['email'] ?? '')]);
            $user = $st->fetch();
        } catch (Throwable $e) {
            error_log('Error de base de datos al iniciar sesión: ' . $e->getMessage());
            flash('No fue posible acceder a la base de datos. Revisa la configuración de MySQL en cPanel.', 'danger');
            redirect('?page=login');
        }
        if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
            $_SESSION['user'] = ['id' => $user['id'], 'nombre' => $user['nombre'], 'email' => $user['email'], 'rol' => $user['rol']];
            redirect('?page=dashboard');
        }
        flash('Credenciales incorrectas', 'danger');
        redirect('?page=login');
    }

    if ($action === 'logout') {
        session_destroy();
        redirect('?page=login');
    }

    if (!$u) redirect('?page=login');

    // Bitácora general de cambios realizados en módulos operativos.
    $auditActions = [
        'operador_save' => 'Operadores', 'operador_delete' => 'Operadores', 'operador_gruas_bulk_save' => 'Operadores',
        'horas_save' => 'Horas', 'horas_delete' => 'Horas', 'skill_save' => 'Habilidades', 'skill_delete' => 'Habilidades',
        'performance_save' => 'Desempeño', 'performance_delete' => 'Desempeño', 'desempeno_estado_save' => 'Desempeño',
        'speed_save' => 'Velocidad', 'speed_delete' => 'Velocidad', 'incident_save' => 'Incidencias', 'incident_delete' => 'Incidencias',
        'user_save' => 'Usuarios', 'user_delete' => 'Usuarios',
    ];
    if (isset($auditActions[$action])) {
        audit_log($action, $auditActions[$action], (int)($_POST['id'] ?? 0) ?: null, [
            'area' => $_POST['area'] ?? null,
            'operadores' => count((array)($_POST['operator_id'] ?? [])),
        ]);
    }

    if ($action === 'activity_add') {
        header('Content-Type: application/json');
        $nombre = mb_strtoupper(trim($_POST['nombre'] ?? ''));
        if ($nombre === '' || $nombre === 'OTRO') {
            echo json_encode(['ok' => false, 'error' => 'Nombre inválido']);
            exit;
        }
        try {
            db()->prepare("INSERT IGNORE INTO custom_activities (nombre) VALUES (?)")->execute([$nombre]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'error' => 'Error al guardar']);
            exit;
        }
        echo json_encode(['ok' => true, 'nombre' => $nombre]);
        exit;
    }

    if ($action === 'lugar_add') {
        header('Content-Type: application/json');
        $nombre = mb_strtoupper(trim($_POST['nombre'] ?? ''));
        $area = $_POST['area'] ?? '';
        if ($nombre === '' || $nombre === 'OTROS' || !isset($AREAS[$area])) {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
            exit;
        }
        try {
            db()->prepare("INSERT IGNORE INTO custom_lugares (area, nombre) VALUES (?,?)")->execute([$area, $nombre]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'error' => 'Error al guardar']);
            exit;
        }
        echo json_encode(['ok' => true, 'nombre' => $nombre, 'area' => $area]);
        exit;
    }

    if ($action === 'context_add') {
        header('Content-Type: application/json');
        $nombre = mb_strtoupper(trim($_POST['nombre'] ?? ''));
        $area = $_POST['area'] ?? '';
        if ($nombre === '' || $nombre === 'OTROS' || !isset($AREAS[$area])) {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
            exit;
        }
        try {
            db()->prepare("INSERT IGNORE INTO custom_contexts (area, nombre) VALUES (?,?)")->execute([$area, $nombre]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'error' => 'Error al guardar']);
            exit;
        }
        echo json_encode(['ok' => true, 'nombre' => $nombre, 'area' => $area]);
        exit;
    }

    // Agregar nuevo tipo de incidencia personalizado (OTROS en incidencias)
    if ($action === 'incident_tipo_add') {
        header('Content-Type: application/json');
        $nombre = mb_strtoupper(trim($_POST['nombre'] ?? ''));
        if ($nombre === '' || $nombre === 'OTROS') {
            echo json_encode(['ok' => false, 'error' => 'Nombre inválido']);
            exit;
        }
        try {
            db()->prepare("INSERT IGNORE INTO custom_contexts (area, nombre) VALUES ('INCIDENT_TIPO', ?)")->execute([$nombre]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'error' => 'Error al guardar']);
            exit;
        }
        echo json_encode(['ok' => true, 'nombre' => $nombre, 'area' => 'INCIDENT_TIPO']);
        exit;
    }

    // Acciones que registran datos de un operador: validar que exista(n)
    if (in_array($action, ['horas_save', 'skill_save', 'speed_save', 'incident_save', 'performance_save', 'desempeno_estado_save', 'operador_gruas_bulk_save'], true)) {
        $opIds = $_POST['operator_id'] ?? [];
        if (!is_array($opIds)) $opIds = [$opIds];
        $opIds = array_values(array_unique(array_filter(array_map('intval', $opIds))));
        $valid = 0;
        if ($opIds) {
            $in = implode(',', array_fill(0, count($opIds), '?'));
            $st = db()->prepare("SELECT COUNT(*) FROM operators WHERE id IN ($in)");
            $st->execute($opIds);
            $valid = (int)$st->fetchColumn();
        }
        if (!$opIds || $valid !== count($opIds)) {
            flash('Debes seleccionar al menos un operador válido', 'danger');
            $redirPage = ['horas_save' => 'horas', 'skill_save' => 'habilidades', 'speed_save' => 'velocidad', 'incident_save' => 'incidencias', 'performance_save' => 'desempeno', 'desempeno_estado_save' => 'desempeno', 'operador_gruas_bulk_save' => 'operadores'][$action];
            redirect('?page=' . $redirPage . ($redirPage === 'operadores' ? '' : '&area=' . ($_POST['area'] ?? 'ARMG')));
        }
    }

    switch ($action) {
        case 'admin_records_mass_delete': {
            if (!is_admin()) redirect('?page=dashboard');
            $type = $_POST['registro_tipo'] ?? '';
            $area = strtoupper($_POST['area'] ?? '');
            $period = $_POST['periodo'] ?? 'all';
            $types = ['horas' => ['hours_records', 'horas'], 'habilidades' => ['skill_records', 'habilidades'], 'velocidad' => ['speed_records', 'velocidad'], 'desempeno' => ['performance_records', null], 'incidencias' => ['incidents', 'incidencias'], 'operadores' => ['operators', null]];
            if (!isset($types[$type]) || !isset($AREAS[$area])) { flash('Selecciona un tipo de registro y una grúa válida.', 'danger'); redirect('?page=admin&tab=registros'); }
            [$table, $sheet] = $types[$type]; $where = []; $params = [];
            $dateColumn = $type === 'operadores' ? 'DATE(created_at)' : 'fecha';
            if ($type === 'operadores') { $where[] = 'FIND_IN_SET(?, tipos_grua)'; $params[] = $area; }
            else { $where[] = 'area=?'; $params[] = $area; }
            if ($period === 'week' && preg_match('/^\d{4}-W\d{2}$/', $_POST['periodo_semana'] ?? '')) {
                $start = new DateTime($_POST['periodo_semana'] . '-1'); $end = clone $start; $end->modify('+6 days'); $where[] = "$dateColumn BETWEEN ? AND ?"; $params[] = $start->format('Y-m-d'); $params[] = $end->format('Y-m-d');
            } elseif ($period === 'month' && preg_match('/^\d{4}-\d{2}$/', $_POST['periodo_mes'] ?? '')) {
                $where[] = "DATE_FORMAT($dateColumn, '%Y-%m')=?"; $params[] = $_POST['periodo_mes'];
            } elseif ($period === 'range' && !empty($_POST['fecha_desde']) && !empty($_POST['fecha_hasta'])) {
                $where[] = "$dateColumn BETWEEN ? AND ?"; $params[] = $_POST['fecha_desde']; $params[] = $_POST['fecha_hasta'];
            } elseif ($period !== 'all') { flash('Completa el periodo que deseas eliminar.', 'danger'); redirect('?page=admin&tab=registros'); }
            $condition = implode(' AND ', $where); $ids = db()->prepare("SELECT id FROM `$table` WHERE $condition"); $ids->execute($params); $ids = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));
            if (!$ids) { flash('No se encontraron registros para eliminar con esos filtros.', 'warning'); redirect('?page=admin&tab=registros'); }
            db()->prepare("DELETE FROM `$table` WHERE $condition")->execute($params);
            if ($sheet) sheets_delete_many($sheet, $ids);
            audit_log('eliminación_por_filtro', 'Registros: ' . $type, null, ['area' => $area, 'periodo' => $period, 'cantidad' => count($ids)]);
            flash(count($ids) . ' registro(s) eliminado(s) de ' . $area . '.'); redirect('?page=admin&tab=registros');
        }
        case 'admin_backup_export': {
            if (!is_admin()) redirect('?page=dashboard');
            $sheets = [];
            foreach (backup_tables() as $table) $sheets[$table] = db()->query("SELECT * FROM `$table`")->fetchAll();
            $file = backup_export_xlsx($sheets); audit_log('exportación_backup', 'Backup', null, ['tablas' => count($sheets)]);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="backup_competencias_' . date('Ymd_His') . '.xlsx"');
            header('Content-Length: ' . filesize($file)); readfile($file); @unlink($file); exit;
        }
        case 'admin_backup_import': {
            if (!is_admin()) redirect('?page=dashboard');
            $upload = $_FILES['backup_file'] ?? null;
            if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'xlsx' || (int)$upload['size'] > 50 * 1024 * 1024) { flash('Selecciona un archivo XLSX de respaldo válido (máximo 50 MB).', 'danger'); redirect('?page=admin&tab=backup'); }
            try {
                $backup = backup_import_xlsx($upload['tmp_name']); $allowed = array_flip(backup_tables()); $pdo = db(); $pdo->beginTransaction(); $restored = 0;
                foreach ($backup as $table => $rows) {
                    if (!isset($allowed[$table]) || !$rows) continue;
                    $columns = array_keys($rows[0]); $validCols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_COLUMN);
                    $columns = array_values(array_intersect($columns, $validCols)); if (!$columns) continue;
                    $insert = '`' . implode('`,`', $columns) . '`'; $marks = implode(',', array_fill(0, count($columns), '?'));
                    $updates = implode(',', array_map(fn($c) => "`$c`=VALUES(`$c`)", array_filter($columns, fn($c) => $c !== 'id')));
                    $sql = "INSERT INTO `$table` ($insert) VALUES ($marks)" . ($updates ? " ON DUPLICATE KEY UPDATE $updates" : ''); $st = $pdo->prepare($sql);
                    foreach ($rows as $row) { $st->execute(array_map(fn($c) => $row[$c] === '' ? null : $row[$c], $columns)); $restored++; }
                }
                $pdo->commit(); audit_log('importación_backup', 'Backup', null, ['filas' => $restored]); flash('Backup importado: ' . $restored . ' fila(s) restaurada(s).');
            } catch (Throwable $e) { if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); flash('No se pudo importar el backup: ' . $e->getMessage(), 'danger'); }
            redirect('?page=admin&tab=backup');
        }
        case 'admin_records_delete': {
            if (!is_admin()) redirect('?page=dashboard');
            $types = [
                'horas' => ['table' => 'hours_records', 'sheet' => 'horas'],
                'habilidades' => ['table' => 'skill_records', 'sheet' => 'habilidades'],
                'velocidad' => ['table' => 'speed_records', 'sheet' => 'velocidad'],
                'desempeno' => ['table' => 'performance_records', 'sheet' => null],
                'incidencias' => ['table' => 'incidents', 'sheet' => 'incidencias'],
                'operadores' => ['table' => 'operators', 'sheet' => null],
            ];
            $type = $_POST['registro_tipo'] ?? '';
            $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
            if (!isset($types[$type]) || !$ids) {
                flash('Selecciona al menos un registro para eliminar.', 'danger');
                redirect('?page=admin&tab=registros');
            }
            $cfg = $types[$type];
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            db()->prepare("DELETE FROM `{$cfg['table']}` WHERE id IN ($placeholders)")->execute($ids);
            if ($cfg['sheet']) sheets_delete_many($cfg['sheet'], $ids);
            audit_log('eliminación_masiva', 'Registros: ' . $type, null, ['cantidad' => count($ids), 'ids' => $ids]);
            flash(count($ids) . ' registro(s) eliminado(s).');
            redirect('?page=admin&tab=registros&tipo=' . urlencode($type));
        }
        // ---- Operadores ----
        case 'operador_save': {
            $id = (int)($_POST['id'] ?? 0);
            $activo = ($_POST['estado_laboral'] ?? 'ACTIVO') === 'CESADO' ? 0 : 1;
            $gruas = array_values(array_intersect((array)($_POST['tipos_grua'] ?? []), ['ARMG', 'QC', 'PC', 'WL']));
            if (!$gruas) {
                // Si no se marcó ninguna grúa manualmente, asignarla automáticamente según el cargo
                $mapaCargoGrua = [
                    'OPERADOR DE GRUA DE PATIO ARMG' => 'ARMG',
                    'OPERADOR DE GRUAS STS'          => 'QC',
                    'OPERADOR DE PORTAL CRANE'       => 'PC',
                    'OPERADOR DE CARGADOR FRONTAL'   => 'WL',
                ];
                if (isset($mapaCargoGrua[$_POST['cargo'] ?? ''])) $gruas = [$mapaCargoGrua[$_POST['cargo']]];
            }
            // El estado de capacitación (Entrenamiento/Reentrenamiento) ya no se edita aquí:
            // se gestiona por grúa desde el módulo de Desempeño.
            $data = [
                trim($_POST['codigo'] ?? ''), trim($_POST['dni'] ?? ''), mb_strtoupper(trim($_POST['nombres'] ?? '')),
                $_POST['cargo'] ?? '', trim($_POST['lugar'] ?? 'CHANCAY'),
                ($_POST['fecha_ingreso'] ?? '') ?: null, $activo, $gruas ? implode(',', $gruas) : null,
            ];
            try {
                if ($id) {
                    $data[] = $id;
                    db()->prepare("UPDATE operators SET codigo=?, dni=?, nombres=?, cargo=?, lugar=?, fecha_ingreso=?, activo=?, tipos_grua=? WHERE id=?")->execute($data);
                    flash('Operador actualizado');
                } else {
                    db()->prepare("INSERT INTO operators (codigo, dni, nombres, cargo, lugar, fecha_ingreso, activo, tipos_grua) VALUES (?,?,?,?,?,?,?,?)")->execute($data);
                    flash('Operador registrado');
                }
            } catch (PDOException $e) {
                flash($e->getCode() === '23000' ? 'Ya existe un operador con ese DNI' : 'Error al guardar', 'danger');
            }
            redirect('?page=operadores');
        }
        case 'operador_delete': {
            if (is_admin()) {
                db()->prepare("DELETE FROM operators WHERE id=?")->execute([(int)$_POST['id']]);
                flash('ELIMINADO', 'danger');
            }
            redirect('?page=operadores');
        }
        case 'operador_gruas_bulk_save': {
            $gruas = array_values(array_intersect((array)($_POST['tipos_grua'] ?? []), ['ARMG', 'QC', 'PC', 'WL']));
            $modo = ($_POST['modo'] ?? '') === 'agregar' ? 'agregar' : 'reemplazar';
            if (!$gruas) {
                flash('Selecciona al menos un tipo de grúa', 'danger');
                redirect('?page=operadores');
            }
            if ($modo === 'reemplazar') {
                $nuevo = implode(',', $gruas);
                $stmt = db()->prepare("UPDATE operators SET tipos_grua=? WHERE id=?");
                foreach ($opIds as $opId) $stmt->execute([$nuevo, $opId]);
            } else {
                $sel = db()->prepare("SELECT tipos_grua FROM operators WHERE id=?");
                $upd = db()->prepare("UPDATE operators SET tipos_grua=? WHERE id=?");
                foreach ($opIds as $opId) {
                    $sel->execute([$opId]);
                    $actuales = array_filter(explode(',', (string)$sel->fetchColumn()));
                    $union = array_values(array_unique(array_merge($actuales, $gruas)));
                    $upd->execute([implode(',', $union), $opId]);
                }
            }
            flash(count($opIds) . ' operador(es) actualizado(s) (' . ($modo === 'agregar' ? 'grúas agregadas' : 'grúas reemplazadas') . ')');
            redirect('?page=operadores');
        }

        // ---- Horas ----
        case 'horas_save': {
            $area = $_POST['area'];
            $cfg = $AREAS[$area];
            $detalle = [];
            $total = 0;
            foreach ($cfg['horas_campos'] as $i => $campo) {
                $min = parse_minutes($_POST['horas'][$i] ?? '');
                if ($min > 0) { $detalle[$campo] = $min; $total += $min; }
            }
            $stmt = db()->prepare("INSERT INTO hours_records (operator_id, area, fecha, tipo_preparacion, tipo_actividad, lugar, instructor, observacion, detalle, total_min, created_by)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            foreach ($opIds as $opId) {
                $stmt->execute([$opId, $area, $_POST['fecha'], $_POST['tipo_preparacion'],
                          trim($_POST['tipo_actividad'] ?? ''), $_POST['lugar'] ?? null, trim($_POST['instructor'] ?? $u['nombre']),
                          trim($_POST['observacion'] ?? ''), json_encode($detalle, JSON_UNESCAPED_UNICODE), $total, $u['id']]);
                // Sync Google Sheets
                $newId = (int)db()->lastInsertId();
                $sr = db()->prepare("SELECT h.*, o.nombres, o.cargo FROM hours_records h JOIN operators o ON o.id=h.operator_id WHERE h.id=?");
                $sr->execute([$newId]); $sr = $sr->fetch();
                if ($sr) sheets_upsert('horas', sheets_row_horas(array_merge($sr, ['total_minutos'=>$sr['total_min'], 'total_segundos'=>$sr['total_min']*60, 'contexto'=>$sr['lugar'], 'actividad'=>$sr['tipo_actividad'], 'evaluador'=>$sr['instructor'], 'tipo_capacitacion'=>$sr['tipo_preparacion']])));
            }
            flash(count($opIds) . ' registro(s) de horas guardado(s) (' . fmt_minutes($total) . ' h c/u)');
            redirect('?page=horas&area=' . $area);
        }
        case 'horas_update': {
            $id = (int)($_POST['id'] ?? 0);
            $st = db()->prepare("SELECT area FROM hours_records WHERE id=?"); $st->execute([$id]);
            $area = $st->fetchColumn();
            if (!$area) { flash('Registro no encontrado', 'danger'); redirect('?page=horas'); }
            $cfg = $AREAS[$area];
            $detalle = [];
            $total = 0;
            foreach ($cfg['horas_campos'] as $i => $campo) {
                $min = parse_minutes($_POST['horas'][$i] ?? '');
                if ($min > 0) { $detalle[$campo] = $min; $total += $min; }
            }
            db()->prepare("UPDATE hours_records SET fecha=?, tipo_preparacion=?, tipo_actividad=?, lugar=?, instructor=?, observacion=?, detalle=?, total_min=? WHERE id=?")
               ->execute([$_POST['fecha'], $_POST['tipo_preparacion'], trim($_POST['tipo_actividad'] ?? ''), $_POST['lugar'] ?? null,
                          trim($_POST['instructor'] ?? $u['nombre']), trim($_POST['observacion'] ?? ''), json_encode($detalle, JSON_UNESCAPED_UNICODE), $total, $id]);
            flash('Registro actualizado (' . fmt_minutes($total) . ' h)');
            redirect('?page=horas&area=' . $area);
        }
        case 'horas_delete': {
            $st = db()->prepare("SELECT area FROM hours_records WHERE id=?"); $st->execute([(int)$_POST['id']]);
            $area = $st->fetchColumn() ?: 'ARMG';
            db()->prepare("DELETE FROM hours_records WHERE id=?")->execute([(int)$_POST['id']]);
            sheets_delete('horas', (int)$_POST['id']);
            flash('ELIMINADO', 'danger');
            redirect('?page=horas&area=' . $area);
        }

        // ---- Habilidades ----
        case 'skill_save': {
            $id = (int)($_POST['id'] ?? 0);
            $area = $_POST['area'];
            $cfg = $AREAS[$area];
            
            $tipo_st = db()->prepare("SELECT estado_capacitacion FROM operator_grua_estado WHERE operator_id=? AND area=?");
            $tipo_st->execute([(int)$_POST['operator_id'], $area]);
            $tipo_capacitacion = $tipo_st->fetchColumn() ?: 'ENTRENAMIENTO';
            
            $items = [];
            $score = 0.0;
            if ($cfg['skills_tipo'] === 'escala') {
                $sum = 0;
                $total_items = 0;
                foreach ($cfg['skills_grupos'] as $g => $gcfg) {
                    $total_items += count($gcfg['items']);
                    foreach ($gcfg['items'] as $k => $item) {
                        $v = (int)($_POST['item'][$g][$k] ?? 0);
                        if ($v >= 1 && $v <= 5) {
                            $items[] = ['g' => $g, 'i' => $item, 'v' => $v];
                            $sum += $v;
                        }
                    }
                }
                $max_points = $total_items * 5;
                $score = $max_points ? round($sum / $max_points * 100, 2) : 0;
            } elseif ($cfg['skills_tipo'] === 'trinivel') {
                $sum = 0;
                $totalItems = 0;
                foreach ($cfg['skills_grupos'] as $g => $gcfg) {
                    foreach ($gcfg['items'] as $k => $item) {
                        $v = (int)($_POST['item'][$g][$k] ?? -1);
                        if ($v >= 0 && $v <= 2) {
                            $items[] = ['g' => $g, 'i' => $item, 'v' => $v];
                            $sum += $v;
                            $totalItems++;
                        }
                    }
                }
                $score = $totalItems ? round($sum / ($totalItems * 2) * 100, 2) : 0;
            } else { // check ponderado
                foreach ($cfg['skills_grupos'] as $g => $gcfg) {
                    $ok = 0; $tot = count($gcfg['items']);
                    foreach ($gcfg['items'] as $k => $item) {
                        $v = isset($_POST['item'][$g][$k]) ? 1 : 0;
                        $items[] = ['g' => $g, 'i' => $item, 'v' => $v];
                        $ok += $v;
                    }
                    $score += ($tot ? $ok / $tot : 0) * $gcfg['peso'];
                }
                $score = round($score, 2);
            }
            $status = calc_skill_status($score, $area);
            $apto = skill_es_apto($score, $area) ? 1 : 0;
            if ($id) {
                db()->prepare("UPDATE skill_records SET operator_id=?, area=?, fecha=?, tipo_capacitacion=?, contexto=?, evaluador=?, items=?, score=?, status=?, apto=?, comentarios=? WHERE id=?")
                   ->execute([(int)$_POST['operator_id'], $area, $_POST['fecha'], $tipo_capacitacion,
                              $_POST['contexto'] ?? null, trim($_POST['evaluador'] ?? $u['nombre']),
                              json_encode($items, JSON_UNESCAPED_UNICODE), $score, $status,
                              $apto, trim($_POST['comentarios'] ?? ''), $id]);
                $lastId = $id;
                flash("Evaluación actualizada: {$score}% ({$status})");
            } else {
                db()->prepare("INSERT INTO skill_records (operator_id, area, fecha, tipo_capacitacion, contexto, evaluador, items, score, status, apto, comentarios, created_by)
                               VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([(int)$_POST['operator_id'], $area, $_POST['fecha'], $tipo_capacitacion,
                              $_POST['contexto'] ?? null, trim($_POST['evaluador'] ?? $u['nombre']),
                              json_encode($items, JSON_UNESCAPED_UNICODE), $score, $status,
                              $apto, trim($_POST['comentarios'] ?? ''), $u['id']]);
                $lastId = (int)db()->lastInsertId();
                flash("Evaluación guardada: {$score}% ({$status})");
            }
            // Sync Google Sheets
            $sr = db()->prepare("SELECT s.*, o.nombres, o.cargo FROM skill_records s JOIN operators o ON o.id=s.operator_id WHERE s.id=?");
            $sr->execute([$lastId]); $sr = $sr->fetch();
            if ($sr) sheets_upsert('habilidades', sheets_row_skill($sr));
            redirect('?page=habilidades&area=' . $area);
        }
        case 'skill_delete': {
            $st = db()->prepare("SELECT area FROM skill_records WHERE id=?"); $st->execute([(int)$_POST['id']]);
            $area = $st->fetchColumn() ?: 'ARMG';
            db()->prepare("DELETE FROM skill_records WHERE id=?")->execute([(int)$_POST['id']]);
            sheets_delete('habilidades', (int)$_POST['id']);
            flash('ELIMINADO', 'danger');
            redirect('?page=habilidades&area=' . $area);
        }
        case 'skill_export': {
            $area = $_GET['area'] ?? 'ARMG';
            if (!isset($AREAS[$area])) $area = 'ARMG';
            
            $st = db()->prepare("SELECT s.*, o.nombres, o.cargo FROM skill_records s JOIN operators o ON o.id=s.operator_id
                                 WHERE s.area=? ORDER BY s.fecha DESC, s.id DESC");
            $st->execute([$area]);
            $rows = $st->fetchAll();
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="habilidades_' . strtolower($area) . '_' . date('Ymd_His') . '.csv"');
            
            echo "\xEF\xBB\xBF"; // UTF-8 BOM
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Fecha', 'Operador', 'Cargo', 'Tipo de capacitación', 'Lugar / Contexto', 'Score (%)', 'Status', 'Apto', 'Instructor']);
            
            foreach ($rows as $r) {
                fputcsv($output, [
                    $r['fecha'],
                    $r['nombres'],
                    $r['cargo'],
                    $r['tipo_capacitacion'],
                    $r['contexto'],
                    number_format((float)$r['score'], 1),
                    calc_skill_status((float)$r['score'], $area),
                    skill_es_apto((float)$r['score'], $area) ? 'SÍ' : 'NO',
                    $r['evaluador']
                ]);
            }
            fclose($output);
            exit;
        }

        // ---- Desempeño ----
        case 'performance_save': {
            global $DESEMPENO_CRITERIOS;
            $area = $_POST['area'];
            $items = [];
            $sum = 0; $n = 0;
            foreach ($DESEMPENO_CRITERIOS as $k => $item) {
                $v = (int)($_POST['item'][$k] ?? 0);
                if ($v >= 1 && $v <= 5) { $items[] = ['i' => $item, 'v' => $v]; $sum += $v; $n++; }
            }
            $score = $n ? round($sum / ($n * 5) * 100, 2) : 0;
            $status = calc_status($score);
            $stmt = db()->prepare("INSERT INTO performance_records (operator_id, area, fecha, evaluador, items, score, status, comentarios, created_by)
                           VALUES (?,?,?,?,?,?,?,?,?)");
            foreach ($opIds as $opId) {
                $stmt->execute([$opId, $area, $_POST['fecha'], trim($_POST['evaluador'] ?? $u['nombre']),
                          json_encode($items, JSON_UNESCAPED_UNICODE), $score, $status, trim($_POST['comentarios'] ?? ''), $u['id']]);
            }
            flash(count($opIds) . " evaluación(es) de desempeño guardada(s): {$score}% ({$status})");
            redirect('?page=desempeno&area=' . $area);
        }
        case 'performance_delete': {
            $st = db()->prepare("SELECT area FROM performance_records WHERE id=?"); $st->execute([(int)$_POST['id']]);
            $area = $st->fetchColumn() ?: 'ARMG';
            db()->prepare("DELETE FROM performance_records WHERE id=?")->execute([(int)$_POST['id']]);
            flash('ELIMINADO', 'danger');
            redirect('?page=desempeno&area=' . $area);
        }
        case 'desempeno_estado_save': {
            $area = $_POST['area'] ?? '';
            if (!isset($AREAS[$area])) redirect('?page=desempeno');
            $estado = ($_POST['estado_capacitacion'] ?? '') === 'REENTRENAMIENTO' ? 'REENTRENAMIENTO' : 'ENTRENAMIENTO';
            $stmt = db()->prepare("INSERT INTO operator_grua_estado (operator_id, area, estado_capacitacion) VALUES (?,?,?)
                           ON DUPLICATE KEY UPDATE estado_capacitacion = VALUES(estado_capacitacion)");
            foreach ($opIds as $opId) {
                $stmt->execute([$opId, $area, $estado]);
            }
            flash(count($opIds) . " operador(es) marcado(s) como {$estado} en {$area}");
            redirect('?page=desempeno&area=' . $area);
        }
        case 'desempeno_unassign': {
            // Quita al operador del personal designado a ESTA grúa (no borra datos históricos)
            $area = $_POST['area'] ?? '';
            $opId = (int)($_POST['operator_id'] ?? 0);
            if (!isset($AREAS[$area]) || !$opId) redirect('?page=desempeno&area=' . $area);
            $sel = db()->prepare("SELECT tipos_grua FROM operators WHERE id=?");
            $sel->execute([$opId]);
            $actuales = array_filter(array_map('trim', explode(',', (string)$sel->fetchColumn())));
            $nuevo = array_values(array_diff($actuales, [$area]));
            db()->prepare("UPDATE operators SET tipos_grua=? WHERE id=?")->execute([implode(',', $nuevo), $opId]);
            // Limpia el estado de capacitación específico de esa grúa
            db()->prepare("DELETE FROM operator_grua_estado WHERE operator_id=? AND area=?")->execute([$opId, $area]);
            flash("Operador desasignado de {$area}", 'danger');
            redirect('?page=desempeno&area=' . $area);
        }

        // ---- Velocidad ----
        case 'speed_save': {
            $id = (int)($_POST['id'] ?? 0);
            $area = $_POST['area'];
            $cfg = $AREAS[$area];
            
            $tipo_st = db()->prepare("SELECT estado_capacitacion FROM operator_grua_estado WHERE operator_id=? AND area=?");
            $tipo_st->execute([(int)$_POST['operator_id'], $area]);
            $tipo_capacitacion = $tipo_st->fetchColumn() ?: 'ENTRENAMIENTO';
            
            $fases = [];
            $total = 0;
            foreach ($cfg['speed_fases'] as $i => $fase) {
                $s = parse_seconds($_POST['fase'][$i] ?? '');
                $fases[] = ['f' => $fase, 's' => $s];
                $total += $s;
            }
            $estimado = parse_seconds($_POST['estimado'] ?? '');
            $ef = ($total > 0 && $estimado > 0) ? round(min(150, $estimado / $total * 100), 2) : 0;
            $status = calc_status($ef >= 100 ? 100 : $ef);
            if ($id) {
                db()->prepare("UPDATE speed_records SET operator_id=?, area=?, fecha=?, tipo_capacitacion=?, lugar=?, contexto=?, evaluador=?, fases=?, total_seg=?, estimado_seg=?, eficiencia=?, status=?, movimientos=?, cumple=?, observaciones=? WHERE id=?")
                   ->execute([(int)$_POST['operator_id'], $area, $_POST['fecha'], $tipo_capacitacion,
                              $_POST['lugar'] ?? null, $_POST['contexto'] ?? null, trim($_POST['evaluador'] ?? $u['nombre']),
                              json_encode($fases, JSON_UNESCAPED_UNICODE), $total, $estimado, $ef, $status,
                              ($_POST['movimientos'] ?? '') !== '' ? (int)$_POST['movimientos'] : null,
                              $ef >= UMBRAL_OPTIMO ? 1 : 0, trim($_POST['observaciones'] ?? ''), $id]);
                $lastId = $id;
                flash('Registro de velocidad actualizado: ' . fmt_seconds($total) . " (eficiencia {$ef}%, {$status})");
            } else {
                db()->prepare("INSERT INTO speed_records (operator_id, area, fecha, tipo_capacitacion, lugar, contexto, evaluador, fases, total_seg, estimado_seg, eficiencia, status, movimientos, cumple, observaciones, created_by)
                               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([(int)$_POST['operator_id'], $area, $_POST['fecha'], $tipo_capacitacion,
                              $_POST['lugar'] ?? null, $_POST['contexto'] ?? null, trim($_POST['evaluador'] ?? $u['nombre']),
                              json_encode($fases, JSON_UNESCAPED_UNICODE), $total, $estimado, $ef, $status,
                              ($_POST['movimientos'] ?? '') !== '' ? (int)$_POST['movimientos'] : null,
                              $ef >= UMBRAL_OPTIMO ? 1 : 0, trim($_POST['observaciones'] ?? ''), $u['id']]);
                $lastId = (int)db()->lastInsertId();
                flash('Registro de velocidad guardado: ' . fmt_seconds($total) . " (eficiencia {$ef}%, {$status})");
            }
            // Sync Google Sheets
            $sr = db()->prepare("SELECT s.*, o.nombres, o.cargo FROM speed_records s JOIN operators o ON o.id=s.operator_id WHERE s.id=?");
            $sr->execute([$lastId]); $sr = $sr->fetch();
            if ($sr) sheets_upsert('velocidad', sheets_row_speed(array_merge((array)$sr, ['apto'=>$sr['cumple']])));
            redirect('?page=velocidad&area=' . $area);
        }
        case 'speed_delete': {
            $st = db()->prepare("SELECT area FROM speed_records WHERE id=?"); $st->execute([(int)$_POST['id']]);
            $area = $st->fetchColumn() ?: 'ARMG';
            db()->prepare("DELETE FROM speed_records WHERE id=?")->execute([(int)$_POST['id']]);
            sheets_delete('velocidad', (int)$_POST['id']);
            flash('ELIMINADO', 'danger');
            redirect('?page=velocidad&area=' . $area);
        }

        // ---- Incidencias ----
        case 'incident_save': {
            $id = (int)($_POST['id'] ?? 0);
            $severidad = trim($_POST['severidad'] ?? 'LEVE');   // fallback si se edita un registro antiguo
            $acciones  = trim($_POST['acciones']  ?? '');
            // Estado previo (solo para edición): se respeta un cierre manual sin declaración.
            $prevEstado = 'EN PROCESO';
            if ($id) {
                $pe = db()->query("SELECT estado FROM incidents WHERE id=" . (int)$id)->fetch();
                if ($pe) $prevEstado = $pe['estado'];
            }
            // Por defecto cuando se genera la incidencia, el estado es EN PROCESO
            $estado    = 'EN PROCESO';
            $data = [(int)$_POST['operator_id'], $_POST['area'], $_POST['fecha'], trim($_POST['equipo'] ?? ''),
                     $_POST['tipo'], $severidad, trim($_POST['descripcion'] ?? ''),
                     $acciones, $estado, trim($_POST['reportado_por'] ?? $u['nombre'])];
            if ($id) {
                $data[] = $id;
                db()->prepare("UPDATE incidents SET operator_id=?, area=?, fecha=?, equipo=?, tipo=?, severidad=?, descripcion=?, acciones=?, estado=?, reportado_por=? WHERE id=?")->execute($data);
                flash('Incidencia actualizada');
            } else {
                $data[] = $u['id'];
                db()->prepare("INSERT INTO incidents (operator_id, area, fecha, equipo, tipo, severidad, descripcion, acciones, estado, reportado_por, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute($data);
                $id = (int)db()->lastInsertId();
                flash('Incidencia registrada');
            }

            // ---- Adjuntos en Google Drive: fotos (máx.) + declaración ----
            set_time_limit(120);
            $maxFotos = defined('DRIVE_MAX_FOTOS') ? DRIVE_MAX_FOTOS : 3;
            $fotoMax  = (defined('DRIVE_MAX_FOTO_MB') ? DRIVE_MAX_FOTO_MB : 8) * 1024 * 1024;
            $docMax   = (defined('DRIVE_MAX_DOC_MB') ? DRIVE_MAX_DOC_MB : 15) * 1024 * 1024;
            $fotoExt  = ['jpg','jpeg','png','webp','gif'];
            $docExt   = ['pdf','doc','docx'];
            $warn = [];

            // Estado actual (para edición): conservar lo ya subido
            $curAtt = db()->prepare("SELECT fotos, declaracion FROM incidents WHERE id=?");
            $curAtt->execute([$id]); $curAtt = $curAtt->fetch();
            $fotos = json_decode($curAtt['fotos'] ?? '[]', true) ?: [];
            $decl  = json_decode($curAtt['declaracion'] ?? 'null', true) ?: null;

            // Quitar fotos / declaración marcadas para eliminar
            $delFotos = (array)($_POST['del_foto'] ?? []);
            if ($delFotos) $fotos = array_values(array_filter($fotos, fn($f) => !in_array($f['id'] ?? '', $delFotos, true)));
            if (!empty($_POST['del_declaracion'])) $decl = null;

            // Recolectar nuevas fotos válidas
            $batch = [];
            if (!empty($_FILES['fotos']) && is_array($_FILES['fotos']['name'])) {
                $n = count($_FILES['fotos']['name']);
                for ($k = 0; $k < $n; $k++) {
                    $err = $_FILES['fotos']['error'][$k] ?? UPLOAD_ERR_NO_FILE;
                    if ($err === UPLOAD_ERR_NO_FILE) continue;
                    if (count($fotos) + count($batch) >= $maxFotos) { $warn[] = "Solo se permiten $maxFotos fotos."; break; }
                    $fname = $_FILES['fotos']['name'][$k];
                    $ext   = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                    if ($err !== UPLOAD_ERR_OK)                    { $warn[] = "No se pudo subir «$fname»."; continue; }
                    if (!in_array($ext, $fotoExt, true))          { $warn[] = "«$fname»: formato de imagen no permitido."; continue; }
                    if ($_FILES['fotos']['size'][$k] > $fotoMax)  { $warn[] = "«$fname» supera el tamaño máximo."; continue; }
                    $data = @file_get_contents($_FILES['fotos']['tmp_name'][$k]);
                    if ($data === false) continue;
                    $mime = function_exists('mime_content_type') ? (mime_content_type($_FILES['fotos']['tmp_name'][$k]) ?: 'image/jpeg') : 'image/jpeg';
                    $batch[] = ['name' => $fname, 'mime' => $mime, 'data' => $data, 'kind' => 'foto'];
                }
            }
            // Declaración (PDF / DOC / DOCX)
            if (!empty($_FILES['declaracion']['name']) && ($_FILES['declaracion']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $dname = $_FILES['declaracion']['name'];
                $dext  = strtolower(pathinfo($dname, PATHINFO_EXTENSION));
                if (($_FILES['declaracion']['error'] ?? 1) !== UPLOAD_ERR_OK) { $warn[] = "No se pudo subir la declaración."; }
                elseif (!in_array($dext, $docExt, true))                     { $warn[] = "La declaración debe ser PDF, DOC o DOCX."; }
                elseif ($_FILES['declaracion']['size'] > $docMax)            { $warn[] = "La declaración supera el tamaño máximo."; }
                else {
                    $data = @file_get_contents($_FILES['declaracion']['tmp_name']);
                    if ($data !== false) {
                        $mime = function_exists('mime_content_type') ? (mime_content_type($_FILES['declaracion']['tmp_name']) ?: 'application/octet-stream') : 'application/octet-stream';
                        $batch[] = ['name' => $dname, 'mime' => $mime, 'data' => $data, 'kind' => 'declaracion'];
                    }
                }
            }

            // Subir todo en una sola llamada al Apps Script
            if ($batch) {
                $uploaded = drive_upload($batch);
                if (!$uploaded && defined('SHEETS_WEBHOOK_URL') && SHEETS_WEBHOOK_URL) {
                    $warn[] = "No se pudieron subir los archivos a Google Drive (verifica el Apps Script).";
                }
                foreach ($uploaded as $uf) {
                    if (($uf['kind'] ?? '') === 'declaracion') {
                        $decl = ['id' => $uf['id'], 'url' => $uf['url'], 'name' => $uf['name']];
                    } elseif (count($fotos) < $maxFotos) {
                        $fotos[] = ['id' => $uf['id'], 'url' => $uf['url'], 'thumb' => $uf['thumb'] ?? '', 'name' => $uf['name']];
                    }
                }
            }

            // Determinar estado final:
            //  - CERRADO si hay declaración adjunta
            //  - CERRADO si ya estaba cerrado manualmente (sin declaración) y no se quitó la declaración
            //  - EN PROCESO en cualquier otro caso
            $nuevaDecl = $decl && !empty($decl['id']) && !empty($decl['url']);
            $quitaDecl = !empty($_POST['del_declaracion']);
            if ($nuevaDecl) {
                $finalEstado = 'CERRADO';
            } elseif (!$quitaDecl && $prevEstado === 'CERRADO') {
                $finalEstado = 'CERRADO';
            } else {
                $finalEstado = 'EN PROCESO';
            }

            // Persistir referencias y estado final
            db()->prepare("UPDATE incidents SET fotos=?, declaracion=?, estado=? WHERE id=?")->execute([
                json_encode(array_values($fotos), JSON_UNESCAPED_UNICODE),
                $decl ? json_encode($decl, JSON_UNESCAPED_UNICODE) : null,
                $finalEstado,
                $id,
            ]);
            if ($warn) flash(implode(' ', $warn), 'danger');

            // Sync Google Sheets
            $sr = db()->prepare("SELECT i.*, o.nombres, o.cargo FROM incidents i JOIN operators o ON o.id=i.operator_id WHERE i.id=?");
            $sr->execute([$id]); $sr = $sr->fetch();
            if ($sr) sheets_upsert('incidencias', sheets_row_incidencia($sr));
            redirect('?page=incidencias');
        }
        case 'incident_delete': {
            if (is_admin()) {
                db()->prepare("DELETE FROM incidents WHERE id=?")->execute([(int)$_POST['id']]);
                sheets_delete('incidencias', (int)$_POST['id']);
                flash('ELIMINADO', 'danger');
            }
            redirect('?page=incidencias');
        }
        case 'incident_close': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                db()->prepare("UPDATE incidents SET estado='CERRADO' WHERE id=?")->execute([$id]);
                $sr = db()->prepare("SELECT i.*, o.nombres, o.cargo FROM incidents i JOIN operators o ON o.id=i.operator_id WHERE i.id=?");
                $sr->execute([$id]); $sr = $sr->fetch();
                if ($sr) sheets_upsert('incidencias', sheets_row_incidencia($sr));
                flash('Incidencia marcada como CERRADO');
            }
            redirect('?page=incidencias');
        }

        // ---- Sincronización masiva con Google Sheets ----
        case 'sheets_resync_all': {
            if (!is_admin()) redirect('?page=dashboard');
            set_time_limit(120);
            // Operadores
            $ops = db()->query("SELECT * FROM operators WHERE activo=1 ORDER BY nombres")->fetchAll();
            sheets_resync('operadores', array_map('sheets_row_operador', $ops));
            // Habilidades
            $skills = db()->query("SELECT s.*, o.nombres, o.cargo FROM skill_records s JOIN operators o ON o.id=s.operator_id ORDER BY s.fecha DESC")->fetchAll();
            sheets_resync('habilidades', array_map('sheets_row_skill', $skills));
            // Horas
            $horas = db()->query("SELECT h.*, o.nombres, o.cargo FROM hours_records h JOIN operators o ON o.id=h.operator_id ORDER BY h.fecha DESC")->fetchAll();
            sheets_resync('horas', array_map(fn($r) => sheets_row_horas(array_merge($r, ['total_minutos'=>$r['total_min'], 'total_segundos'=>$r['total_min']*60, 'contexto'=>$r['lugar'], 'actividad'=>$r['tipo_actividad'], 'evaluador'=>$r['instructor'], 'tipo_capacitacion'=>$r['tipo_preparacion']])), $horas));
            // Velocidad
            $speeds = db()->query("SELECT s.*, o.nombres, o.cargo FROM speed_records s JOIN operators o ON o.id=s.operator_id ORDER BY s.fecha DESC")->fetchAll();
            sheets_resync('velocidad', array_map(fn($r) => sheets_row_speed(array_merge((array)$r, ['apto'=>$r['cumple']])), $speeds));
            // Incidencias
            $incs = db()->query("SELECT i.*, o.nombres, o.cargo FROM incidents i JOIN operators o ON o.id=i.operator_id ORDER BY i.fecha DESC")->fetchAll();
            sheets_resync('incidencias', array_map('sheets_row_incidencia', $incs));
            flash('Datos sincronizados con Google Sheets (' . count($ops) . ' operadores, ' . count($skills) . ' habilidades, ' . count($horas) . ' horas, ' . count($speeds) . ' velocidad, ' . count($incs) . ' incidencias)');
            redirect('?page=dashboard');
        }

        // ---- Usuarios ----
        case 'user_save': {
            if (!is_admin()) redirect('?page=dashboard');
            $id = (int)($_POST['id'] ?? 0);
            $pass = $_POST['password'] ?? '';
            $dni = trim($_POST['dni'] ?? '') ?: null;
            if ($id) {
                if ($pass !== '') {
                    db()->prepare("UPDATE users SET nombre=?, dni=?, email=?, rol=?, activo=?, password_hash=? WHERE id=?")
                       ->execute([trim($_POST['nombre']), $dni, trim($_POST['email']), $_POST['rol'], isset($_POST['activo']) ? 1 : 0, password_hash($pass, PASSWORD_DEFAULT), $id]);
                } else {
                    db()->prepare("UPDATE users SET nombre=?, dni=?, email=?, rol=?, activo=? WHERE id=?")
                       ->execute([trim($_POST['nombre']), $dni, trim($_POST['email']), $_POST['rol'], isset($_POST['activo']) ? 1 : 0, $id]);
                }
                if ($id === (int)$u['id']) {
                    $_SESSION['user'] = ['id' => $id, 'nombre' => trim($_POST['nombre']), 'email' => trim($_POST['email']), 'rol' => $_POST['rol']];
                }
                flash('Usuario actualizado');
            } else {
                db()->prepare("INSERT INTO users (nombre, dni, email, password_hash, rol) VALUES (?,?,?,?,?)")
                   ->execute([trim($_POST['nombre']), $dni, trim($_POST['email']), password_hash($pass ?: 'cambiar123', PASSWORD_DEFAULT), $_POST['rol']]);
                flash('Usuario creado');
            }
            redirect('?page=admin&tab=usuarios');
        }
        case 'user_delete': {
            if (is_admin() && (int)$_POST['id'] !== (int)$u['id']) {
                db()->prepare("DELETE FROM users WHERE id=?")->execute([(int)$_POST['id']]);
                flash('ELIMINADO', 'danger');
            }
            redirect('?page=admin&tab=usuarios');
        }
    }
    redirect('?page=dashboard');
}
