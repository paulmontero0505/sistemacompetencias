<?php
/**
 * sync_sheets.php — Helper para enviar datos al Google Apps Script Web App.
 *
 * Uso:
 *   sheets_upsert('habilidades', $row);
 *   sheets_delete('incidencias', $id);
 *   sheets_resync('operadores', $allRows);
 */

/**
 * Envía un upsert (insertar o actualizar) de una fila al Web App.
 */
function sheets_upsert(string $module, array $row): void {
    _sheets_send($module, 'upsert', [$row]);
}

/**
 * Notifica al Web App que se eliminó una fila (por id).
 */
function sheets_delete(string $module, int $id): void {
    _sheets_send($module, 'delete', [['id' => $id]]);
}

/**
 * Elimina varias filas en una sola llamada al Web App.
 * Evita que una eliminación masiva haga cientos de solicitudes HTTP y agote
 * el tiempo de ejecución del servidor.
 */
function sheets_delete_many(string $module, array $ids): void {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return;
    try {
        _sheets_send($module, 'delete', array_map(fn($id) => ['id' => $id], $ids));
    } catch (Throwable $e) {
        error_log('No se pudo sincronizar la eliminación masiva con Google Sheets: ' . $e->getMessage());
    }
}

/**
 * Envía TODOS los registros de un módulo para reemplazar la pestaña completa.
 */
function sheets_resync(string $module, array $rows): void {
    _sheets_send($module, 'resync', $rows);
}

/**
 * Hace el POST asíncrono (fire-and-forget) al webhook de Google Sheets.
 * Si SHEETS_WEBHOOK_URL está vacío, no hace nada.
 */
function _sheets_send(string $module, string $action, array $rows): void {
    $url = defined('SHEETS_WEBHOOK_URL') ? SHEETS_WEBHOOK_URL : '';
    if (!$url) return;   // Sync deshabilitado

    $payload = json_encode([
        'module' => $module,
        'action' => $action,
        'rows'   => $rows,
    ], JSON_UNESCAPED_UNICODE);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            // Timeout muy corto — fire-and-forget (no bloquea la respuesta PHP)
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}

/**
 * Sube uno o más archivos a Google Drive a través del Apps Script Web App.
 *
 * $files: lista de ['name'=>, 'mime'=>, 'data'=>(contenido binario), 'kind'=>'foto'|'declaracion']
 * Devuelve la lista de archivos creados: [['id','url','thumb','name','kind'], ...]
 * En caso de error o webhook deshabilitado devuelve [].
 */
function drive_upload(array $files): array {
    $url = defined('SHEETS_WEBHOOK_URL') ? SHEETS_WEBHOOK_URL : '';
    $folder = defined('DRIVE_FOLDER_ID') ? DRIVE_FOLDER_ID : '';
    if (!$url || !$folder || !$files || !function_exists('curl_init')) return [];

    $payloadFiles = [];
    foreach ($files as $f) {
        if (empty($f['data'])) continue;
        $payloadFiles[] = [
            'name' => $f['name'] ?? 'archivo',
            'mime' => $f['mime'] ?? 'application/octet-stream',
            'kind' => $f['kind'] ?? '',
            'dataBase64' => base64_encode($f['data']),
        ];
    }
    if (!$payloadFiles) return [];

    $payload = json_encode([
        'module'   => 'drive',
        'action'   => 'upload',
        'folderId' => $folder,
        'files'    => $payloadFiles,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,   // Apps Script responde con redirect 302
        CURLOPT_TIMEOUT        => 60,     // subir archivos puede tardar
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) return [];

    $json = json_decode($resp, true);
    if (!is_array($json) || empty($json['ok']) || empty($json['files'])) return [];
    return $json['files'];
}

/**
 * Construye el array de datos para la pestaña Habilidades (skill_records).
 */
function sheets_row_skill(array $r): array {
    return [
        'id'               => $r['id'],
        'fecha'            => $r['fecha'],
        'nombres'          => $r['nombres'] ?? '',
        'cargo'            => $r['cargo'] ?? '',
        'area'             => $r['area'],
        'tipo_capacitacion'=> $r['tipo_capacitacion'] ?? '',
        'contexto'         => $r['contexto'],
        'score'            => $r['score'],
        'status'           => calc_skill_status((float)$r['score'], $r['area']),
        'apto'             => skill_es_apto((float)$r['score'], $r['area']) ? 1 : 0,
        'evaluador'        => $r['evaluador'],
    ];
}

/**
 * Construye el array de datos para la pestaña Velocidad (speed_records).
 */
function sheets_row_speed(array $r): array {
    $fases = json_decode($r['fases'] ?? '[]', true) ?: [];
    return [
        'id'               => $r['id'],
        'fecha'            => $r['fecha'],
        'nombres'          => $r['nombres'] ?? '',
        'cargo'            => $r['cargo'] ?? '',
        'area'             => $r['area'],
        'tipo_capacitacion'=> $r['tipo_capacitacion'] ?? '',
        'lugar'            => $r['lugar'],
        'contexto'         => $r['contexto'],
        'f1'               => $fases[0] ?? 0,
        'f2'               => $fases[1] ?? 0,
        'f3'               => $fases[2] ?? 0,
        'f4'               => $fases[3] ?? 0,
        'f5'               => $fases[4] ?? 0,
        'total_seg'        => $r['total_seg'] ?? 0,
        'status'           => $r['status'],
        'apto'             => $r['apto'],
    ];
}

/**
 * Construye el array de datos para la pestaña Horas (horas_records).
 */
function sheets_row_horas(array $r): array {
    return [
        'id'               => $r['id'],
        'fecha'            => $r['fecha'],
        'nombres'          => $r['nombres'] ?? '',
        'cargo'            => $r['cargo'] ?? '',
        'area'             => $r['area'],
        'tipo_capacitacion'=> $r['tipo_capacitacion'] ?? '',
        'contexto'         => $r['contexto'],
        'actividad'        => $r['actividad'] ?? '',
        'evaluador'        => $r['evaluador'],
        'total_minutos'    => $r['total_minutos'] ?? 0,
        'total_segundos'   => $r['total_segundos'] ?? 0,
    ];
}

/**
 * Construye el array de datos para la pestaña Incidencias (incidents).
 */
function sheets_row_incidencia(array $r): array {
    return [
        'id'           => $r['id'],
        'fecha'        => $r['fecha'],
        'nombres'      => $r['nombres'] ?? '',
        'cargo'        => $r['cargo'] ?? '',
        'area'         => $r['area'],
        'equipo'       => $r['equipo'],
        'tipo'         => $r['tipo'],
        'descripcion'  => $r['descripcion'],
        'severidad'    => $r['severidad'],
        'estado'       => $r['estado'],
        'reportado_por'=> $r['reportado_por'],
    ];
}

/**
 * Construye el array de datos para la pestaña Operadores.
 */
function sheets_row_operador(array $r): array {
    return [
        'id'            => $r['id'],
        'codigo'        => $r['codigo'] ?? '',
        'dni'           => $r['dni'] ?? '',
        'nombres'       => $r['nombres'],
        'cargo'         => $r['cargo'],
        'lugar'         => $r['lugar'] ?? '',
        'fecha_ingreso' => $r['fecha_ingreso'] ?? '',
        'tipo_grua'     => $r['tipo_grua'] ?? '',
        'estado'        => $r['activo'] ? 'ACTIVO' : 'CESADO',
    ];
}
