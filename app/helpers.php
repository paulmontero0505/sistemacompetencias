<?php
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $to): void {
    header('Location: ' . BASE_URL . '/index.php' . $to);
    exit;
}

function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool {
    $u = current_user();
    // El supervisor tiene los mismos privilegios que el administrador.
    return $u && in_array($u['rol'], ['admin', 'supervisor'], true);
}

/** Registra acciones relevantes para consulta exclusiva del administrador. */
function audit_log(string $accion, string $modulo, ?int $registroId = null, array $detalle = []): void {
    $u = current_user();
    try {
        db()->prepare("INSERT INTO audit_logs (user_id, usuario, accion, modulo, registro_id, detalle) VALUES (?,?,?,?,?,?)")
            ->execute([$u['id'] ?? null, $u['nombre'] ?? null, $accion, $modulo, $registroId,
                $detalle ? json_encode($detalle, JSON_UNESCAPED_UNICODE) : null]);
    } catch (Throwable $e) {
        error_log('No se pudo registrar auditoría: ' . $e->getMessage());
    }
}

/** Tablas incluidas en los respaldos administrativos. */
function backup_tables(): array {
    return ['users', 'operators', 'operator_grua_estado', 'hours_records', 'skill_records', 'performance_records', 'speed_records', 'incidents', 'custom_activities', 'custom_lugares', 'custom_contexts', 'audit_logs'];
}

function xlsx_col_name(int $index): string {
    $name = '';
    while ($index >= 0) { $name = chr(($index % 26) + 65) . $name; $index = intdiv($index, 26) - 1; }
    return $name;
}

function xlsx_cell_xml(string $ref, $value): string {
    $value = (string)($value ?? '');
    return '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</t></is></c>';
}

/** Genera un respaldo XLSX simple y compatible con la restauración interna. */
function backup_export_xlsx(array $sheets): string {
    if (!class_exists('ZipArchive')) throw new RuntimeException('La extensión ZIP de PHP no está disponible.');
    $tmp = tempnam(sys_get_temp_dir(), 'backup_xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('No se pudo generar el archivo Excel.');
    $sheetXml = [];
    foreach ($sheets as $name => $rows) {
        $headers = $rows ? array_keys($rows[0]) : [];
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $allRows = $headers ? array_merge([$headers], array_map(fn($row) => array_map(fn($h) => $row[$h] ?? '', $headers), $rows)) : [];
        foreach ($allRows as $r => $row) {
            $xml .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($row) as $c => $value) $xml .= xlsx_cell_xml(xlsx_col_name($c) . ($r + 1), $value);
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $sheetXml[] = $xml;
    }
    $count = count($sheetXml);
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    for ($i = 1; $i <= $count; $i++) $contentTypes .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    $contentTypes .= '</Types>';
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    foreach (array_keys($sheets) as $i => $name) { $n = $i + 1; $workbook .= '<sheet name="' . htmlspecialchars($name, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>'; $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>'; }
    $workbook .= '</sheets></workbook>'; $rels .= '<Relationship Id="rId' . ($count + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', $workbook); $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="1"><xf fontId="0" fillId="0" borderId="0"/></cellXfs></styleSheet>');
    foreach ($sheetXml as $i => $xml) $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $xml);
    $zip->close();
    return $tmp;
}

function xlsx_col_index(string $letters): int { $out = 0; foreach (str_split($letters) as $letter) $out = $out * 26 + (ord($letter) - 64); return $out - 1; }

/** Lee exclusivamente los XLSX de respaldo emitidos por esta aplicación. */
function backup_import_xlsx(string $file): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('La extensión ZIP de PHP no está disponible.');
    $zip = new ZipArchive(); if ($zip->open($file) !== true) throw new RuntimeException('No se pudo abrir el archivo Excel.');
    $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml') ?: '');
    if (!$workbook) throw new RuntimeException('El archivo no es un respaldo Excel válido.');
    $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels') ?: '');
    if (!$rels) throw new RuntimeException('El archivo Excel no contiene sus relaciones internas.');
    $relsNs = $rels->getNamespaces(true);
    $relMap = [];
    foreach ($rels->children($relsNs[''] ?? null)->Relationship as $rel) {
        $attr = $rel->attributes();
        $relMap[(string)$attr['Id']] = 'xl/' . ltrim((string)$attr['Target'], '/');
    }
    $workbookNs = $workbook->getNamespaces(true);
    $workbookMain = $workbook->children($workbookNs[''] ?? null);
    $result = [];
    foreach ($workbookMain->sheets->sheet as $sheet) {
        $sheetAttr = $sheet->attributes(); $name = (string)$sheetAttr['name']; $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships'); $path = $relMap[(string)$attrs['id']] ?? '';
        if (!$path || !($xml = simplexml_load_string($zip->getFromName($path) ?: ''))) continue;
        $sheetNs = $xml->getNamespaces(true); $sheetMain = $xml->children($sheetNs[''] ?? null); $rows = $sheetMain->sheetData->row ?: []; $parsed = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $attr = $cell->attributes(); preg_match('/([A-Z]+)/', (string)$attr['r'], $m); $idx = xlsx_col_index($m[1] ?? 'A'); $contents = $cell->children($sheetNs[''] ?? null);
                $cells[$idx] = (string)$attr['t'] === 'inlineStr' ? (string)$contents->is->t : (string)$contents->v;
            }
            ksort($cells); $parsed[] = $cells;
        }
        if (!$parsed) continue; $headers = array_values($parsed[0]); $data = [];
        foreach (array_slice($parsed, 1) as $row) { $entry = []; foreach ($headers as $i => $header) $entry[$header] = $row[$i] ?? ''; $data[] = $entry; }
        $result[$name] = $data;
    }
    $zip->close(); return $result;
}

/** "1:30" o "1:30:00" o "90" (min) -> minutos totales (int) */
function parse_minutes(?string $s): int {
    $s = trim((string)$s);
    if ($s === '') return 0;
    if (strpos($s, ':') !== false) {
        $p = array_map('intval', explode(':', $s));
        if (count($p) === 3) return $p[0] * 60 + $p[1] + (int)round($p[2] / 60);
        return $p[0] * 60 + ($p[1] ?? 0);
    }
    return (int)round((float)$s);
}

/** "mm:ss" o "h:mm:ss" o segundos -> segundos totales (int) */
function parse_seconds(?string $s): int {
    $s = trim((string)$s);
    if ($s === '') return 0;
    if (strpos($s, ':') !== false) {
        $p = array_map('intval', explode(':', $s));
        if (count($p) === 3) return $p[0] * 3600 + $p[1] * 60 + $p[2];
        return $p[0] * 60 + ($p[1] ?? 0);
    }
    return (int)round((float)$s);
}

function fmt_minutes(int $min): string {
    return sprintf('%d:%02d', intdiv($min, 60), $min % 60);
}

function fmt_seconds(int $seg): string {
    if ($seg >= 3600) return sprintf('%d:%02d:%02d', intdiv($seg, 3600), intdiv($seg % 3600, 60), $seg % 60);
    return sprintf('%02d:%02d', intdiv($seg, 60), $seg % 60);
}

function calc_status(float $pct): string {
    if ($pct >= UMBRAL_OPTIMO) return 'OPTIMO';
    if ($pct >= UMBRAL_REGULAR) return 'REGULAR';
    return 'BAJO';
}

/** Nivel de competencia para una evaluación de habilidades. */
function calc_skill_status(float $pct, string $area): string {
    $prefix = SKILL_STATUS_PREFIX[$area] ?? 'C';
    if ($pct >= ARMG_UMBRAL_C3) return $prefix . '3';
    if ($pct >= ARMG_UMBRAL_C2) return $prefix . '2';
    if ($pct >= ARMG_UMBRAL_C1) return $prefix . '1';
    return $prefix . '0';
}

/** Determina la aptitud de una evaluación de habilidades por área. */
function skill_es_apto(float $pct, string $area): bool {
    return $pct >= ARMG_UMBRAL_APTO;
}

/** Nombre legible del nivel de competencia calculado. */
function skill_level_name(float $pct, string $area): string {
    $level = substr(calc_skill_status($pct, $area), -1);
    if ($level === '0') return 'Nivel bajo';
    if ($level === '1') return 'Nivel básico';
    if ($level === '2') return 'Nivel promedio';
    return 'Nivel avanzado';
}

/** Texto del nivel para reportes: código propio del área y nombre legible. */
function skill_level_label(float $pct, string $area): string {
    return calc_skill_status($pct, $area) . ' - ' . skill_level_name($pct, $area);
}

function status_badge(?string $status): string {
    $map = ['C0' => 'danger-subtle text-danger border border-danger-subtle', 'C1' => 'warning-subtle text-warning-emphasis border border-warning-subtle', 'C2' => 'primary-subtle text-primary border border-primary-subtle', 'C3' => 'success-subtle text-success border border-success-subtle',
            'A0' => 'danger-subtle text-danger border border-danger-subtle', 'A1' => 'warning-subtle text-warning-emphasis border border-warning-subtle', 'A2' => 'primary-subtle text-primary border border-primary-subtle', 'A3' => 'success-subtle text-success border border-success-subtle',
            'B0' => 'danger-subtle text-danger border border-danger-subtle', 'B1' => 'warning-subtle text-warning-emphasis border border-warning-subtle', 'B2' => 'primary-subtle text-primary border border-primary-subtle', 'B3' => 'success-subtle text-success border border-success-subtle',
            'D0' => 'danger-subtle text-danger border border-danger-subtle', 'D1' => 'warning-subtle text-warning-emphasis border border-warning-subtle', 'D2' => 'primary-subtle text-primary border border-primary-subtle', 'D3' => 'success-subtle text-success border border-success-subtle',
            'OPTIMO' => 'success-subtle text-success border border-success-subtle', 'REGULAR' => 'warning-subtle text-warning-emphasis border border-warning-subtle', 'BAJO' => 'danger-subtle text-danger border border-danger-subtle',
            'ABIERTA' => 'danger-subtle text-danger border border-danger-subtle', 'EN PROCESO' => 'warning-subtle text-warning-emphasis border border-warning-subtle', 'CERRADA' => 'success-subtle text-success border border-success-subtle',
            'LEVE' => 'secondary-subtle text-secondary border border-secondary-subtle', 'MODERADA' => 'warning-subtle text-warning-emphasis border border-warning-subtle', 'GRAVE' => 'danger-subtle text-danger border border-danger-subtle'];
    $cls = $map[$status] ?? 'secondary-subtle text-secondary border border-secondary-subtle';
    return '<span class="badge bg-' . $cls . '">' . h($status) . '</span>';
}

function meses_es(): array {
    return [1=>'ENERO',2=>'FEBRERO',3=>'MARZO',4=>'ABRIL',5=>'MAYO',6=>'JUNIO',7=>'JULIO',8=>'AGOSTO',9=>'SETIEMBRE',10=>'OCTUBRE',11=>'NOVIEMBRE',12=>'DICIEMBRE'];
}

/** Operadores activos. Si se indica $area, excluye a quienes tengan tipos_grua asignado
 *  y esa área no esté incluida (tipos_grua vacío/NULL = habilitado para todas). */
function operadores_activos(?string $area = null): array {
    if ($area === null) {
        return db()->query("SELECT id, codigo, dni, nombres, cargo, tipos_grua FROM operators WHERE activo=1 ORDER BY nombres")->fetchAll();
    }
    $st = db()->prepare("SELECT id, codigo, dni, nombres, cargo, tipos_grua FROM operators
        WHERE activo=1 AND (tipos_grua IS NULL OR tipos_grua='' OR FIND_IN_SET(?, tipos_grua))
        ORDER BY nombres");
    $st->execute([$area]);
    return $st->fetchAll();
}

/** Lista de actividades base + las agregadas manualmente (con OTRO siempre al final) */
function actividades_con_personalizadas(array $base): array {
    $custom = db()->query("SELECT nombre FROM custom_activities ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
    $fijas = array_values(array_diff($base, ['OTROS']));
    return array_values(array_unique(array_merge($fijas, $custom, ['OTROS'])));
}

/** Lista de lugares base de un área + los agregados manualmente (con OTRO siempre al final) */
function lugares_con_personalizados(array $base, string $area): array {
    $st = db()->prepare("SELECT nombre FROM custom_lugares WHERE area=? ORDER BY nombre");
    $st->execute([$area]);
    $custom = $st->fetchAll(PDO::FETCH_COLUMN);
    $fijos = array_values(array_diff($base, ['OTROS']));
    return array_values(array_unique(array_merge($fijos, $custom, ['OTROS'])));
}

/** Lista de contextos base de un área + los agregados manualmente (con OTROS siempre al final) */
function contextos_con_personalizados(array $base, string $area): array {
    $st = db()->prepare("SELECT nombre FROM custom_contexts WHERE area=? ORDER BY nombre");
    $st->execute([$area]);
    $custom = $st->fetchAll(PDO::FETCH_COLUMN);
    $fijos = array_values(array_diff($base, ['OTROS']));
    return array_values(array_unique(array_merge($fijos, $custom, ['OTROS'])));
}
