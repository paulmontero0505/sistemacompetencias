<?php
/* ============================================================
   PÁGINA 1 · RESUMEN EJECUTIVO  +  MATRIZ DE TALENTO (9-Box)
   Dashboard ejecutivo con filtros globales, KPIs, gauge y gráficos.
   ============================================================ */

// ── Filtro de área (botones superiores) ─────────────────────
$areaF = strtoupper($_GET['area'] ?? 'TODAS');
if ($areaF !== 'TODAS' && !isset($AREAS[$areaF])) $areaF = 'TODAS';

// ── Filtros globales ────────────────────────────────────────
$F = [
    'desde'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde'] ?? '') ? $_GET['desde'] : '',
    'hasta'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'] ?? '') ? $_GET['hasta'] : '',
    'area'    => $areaF !== 'TODAS' ? $areaF : '',
    'cargo'   => trim($_GET['cargo'] ?? ''),
    'tipocap' => trim($_GET['tipocap'] ?? ''),
    'instr'   => trim($_GET['instr'] ?? ''),
    'lugar'   => trim($_GET['lugar'] ?? ''),
    'op'      => (int)($_GET['op'] ?? 0),
];
$hayFiltroFecha = $F['desde'] !== '' || $F['hasta'] !== '';

// Constructor de WHERE por tabla: recibe el mapa de columnas disponibles.
$flt = function (array $cols) use ($F) {
    $w = [];
    if ($F['desde']   && !empty($cols['fecha']))   $w[] = "{$cols['fecha']} >= "   . db()->quote($F['desde']);
    if ($F['hasta']   && !empty($cols['fecha']))   $w[] = "{$cols['fecha']} <= "   . db()->quote($F['hasta']);
    if ($F['area']    && !empty($cols['area']))    $w[] = "{$cols['area']} = "     . db()->quote($F['area']);
    if ($F['cargo']   && !empty($cols['cargo']))   $w[] = "{$cols['cargo']} = "    . db()->quote($F['cargo']);
    if ($F['tipocap'] && !empty($cols['tipocap'])) $w[] = "{$cols['tipocap']} = "  . db()->quote($F['tipocap']);
    if ($F['instr']   && !empty($cols['instr']))   $w[] = "{$cols['instr']} = "    . db()->quote($F['instr']);
    if ($F['lugar']   && !empty($cols['lugar']))   $w[] = "{$cols['lugar']} = "    . db()->quote($F['lugar']);
    if ($F['op']      && !empty($cols['op']))      $w[] = "{$cols['op']} = "       . (int)$F['op'];
    return $w ? (' AND ' . implode(' AND ', $w)) : '';
};

// Mapas de columnas por tabla
$C_HORAS = ['fecha'=>'h.fecha','area'=>'h.area','cargo'=>'o.cargo','instr'=>'h.instructor','lugar'=>'h.lugar','op'=>'h.operator_id'];
$C_SKILL = ['fecha'=>'s.fecha','area'=>'s.area','cargo'=>'o.cargo','tipocap'=>'s.tipo_capacitacion','instr'=>'s.evaluador','op'=>'s.operator_id'];
$C_SPEED = ['fecha'=>'v.fecha','area'=>'v.area','cargo'=>'o.cargo','tipocap'=>'v.tipo_capacitacion','instr'=>'v.evaluador','lugar'=>'v.lugar','op'=>'v.operator_id'];
$C_INC   = ['fecha'=>'i.fecha','area'=>'i.area','cargo'=>'o.cargo','op'=>'i.operator_id'];

$wH = $flt($C_HORAS); $wS = $flt($C_SKILL); $wV = $flt($C_SPEED); $wI = $flt($C_INC);

// ── KPIs de operadores ──────────────────────────────────────
$opW = "WHERE 1=1";
if ($F['cargo']) $opW .= " AND cargo = " . db()->quote($F['cargo']);
if ($F['op'])    $opW .= " AND id = " . (int)$F['op'];
if ($F['area'])  $opW .= " AND (tipos_grua IS NULL OR tipos_grua='' OR FIND_IN_SET(" . db()->quote($F['area']) . ", tipos_grua))";
$totOps = (int) db()->query("SELECT COUNT(*) FROM operators $opW")->fetchColumn();
$actOps = (int) db()->query("SELECT COUNT(*) FROM operators $opW AND activo=1")->fetchColumn();

// ── KPIs de horas ───────────────────────────────────────────
$rowH = db()->query("SELECT COALESCE(SUM(h.total_min),0) mins, COUNT(DISTINCT h.operator_id) ops
    FROM hours_records h JOIN operators o ON o.id=h.operator_id WHERE 1=1 $wH")->fetch();
$totHorasMin  = (int)$rowH['mins'];
$totHoras     = round($totHorasMin / 60, 1);
$opsConHoras  = (int)$rowH['ops'];
$promHorasOp  = $opsConHoras ? round($totHoras / $opsConHoras, 1) : 0;

// ── KPIs de habilidades (score / aptos) ─────────────────────
$rowS = db()->query("SELECT ROUND(AVG(s.score),1) prom, COUNT(*) tot, SUM(s.apto=1) ap
    FROM skill_records s JOIN operators o ON o.id=s.operator_id WHERE 1=1 $wS")->fetch();
$promScore = $rowS['prom'] !== null ? (float)$rowS['prom'] : 0;
$totSkill  = (int)$rowS['tot'];
$pctApto   = $totSkill ? round(100 * (int)$rowS['ap'] / $totSkill) : 0;
$pctNoApto = $totSkill ? 100 - $pctApto : 0;

// ── KPI velocidad ───────────────────────────────────────────
$rowV = db()->query("SELECT AVG(v.total_seg) prom_seg, COUNT(*) tot
    FROM speed_records v JOIN operators o ON o.id=v.operator_id WHERE 1=1 $wV")->fetch();
$promVelSeg = (int) round((float)$rowV['prom_seg']);
$totVel = (int)$rowV['tot'];

// ── KPI incidencias ─────────────────────────────────────────
$totInc = (int) db()->query("SELECT COUNT(*) FROM incidents i JOIN operators o ON o.id=i.operator_id WHERE 1=1 $wI")->fetchColumn();

// ── Gráfico: horas de entrenamiento (TRAINING) por mes ───────
$wHmes = $wH . " AND h.tipo_preparacion='TRAINING'" . (!$hayFiltroFecha ? " AND h.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)" : "");
$horasSerie = db()->query("SELECT DATE_FORMAT(h.fecha,'%Y-%m') ym, SUM(h.total_min) t
    FROM hours_records h JOIN operators o ON o.id=h.operator_id WHERE 1=1 $wHmes GROUP BY ym ORDER BY ym")->fetchAll();
$serieLabels=[]; $serieData=[];
foreach ($horasSerie as $r) { $serieLabels[]=$r['ym']; $serieData[]=round($r['t']/60,1); }
$totHorasTraining = round(array_sum($serieData), 1);

// ── Gráfico: capacitaciones por tipo ────────────────────────
$capTipo = db()->query("SELECT COALESCE(NULLIF(s.tipo_capacitacion,''),'—') tc, COUNT(*) n
    FROM skill_records s JOIN operators o ON o.id=s.operator_id WHERE 1=1 $wS GROUP BY tc ORDER BY n DESC")->fetchAll();

// ── Gráfico: score promedio por área ────────────────────────
$scoreArea = db()->query("SELECT s.area, ROUND(AVG(s.score),1) prom
    FROM skill_records s JOIN operators o ON o.id=s.operator_id WHERE 1=1 $wS GROUP BY s.area ORDER BY s.area")->fetchAll();

// ── Gráfico: velocidad promedio por tipo de maniobra ────────
$velManiobra = db()->query("SELECT COALESCE(NULLIF(v.contexto,''),'—') ctx, ROUND(AVG(v.total_seg)) t
    FROM speed_records v JOIN operators o ON o.id=v.operator_id WHERE 1=1 $wV GROUP BY ctx ORDER BY t ASC LIMIT 12")->fetchAll();

// ── Gráfico: incidencias por severidad ──────────────────────
// Se construye a partir del resultado real (no de una lista fija) para que
// la suma del gráfico siempre coincida con el KPI "Total incidencias".
$incSev = db()->query("SELECT COALESCE(NULLIF(severidad,''),'SIN DATO') severidad, COUNT(*) n
    FROM incidents i JOIN operators o ON o.id=i.operator_id
    WHERE 1=1 $wI GROUP BY severidad")->fetchAll();
$SEV_ORDEN = ['LEVE'=>0,'MODERADA'=>1,'GRAVE'=>2,'SIN DATO'=>3];
usort($incSev, fn($a,$b)=> ($SEV_ORDEN[$a['severidad']] ?? 9) <=> ($SEV_ORDEN[$b['severidad']] ?? 9));
$SEV_COLOR = ['LEVE'=>'#95a5a6','MODERADA'=>'#F2C94C','GRAVE'=>'#EB5757','SIN DATO'=>'#c9d2df'];

// ── Top 10 operadores por horas ─────────────────────────────
$topHoras = db()->query("SELECT o.id, o.nombres, ROUND(SUM(h.total_min)/60,1) hrs
    FROM hours_records h JOIN operators o ON o.id=h.operator_id WHERE 1=1 $wH
    GROUP BY o.id, o.nombres ORDER BY hrs DESC LIMIT 10")->fetchAll();

// ── Top 10 operadores por score ─────────────────────────────
$topScore = db()->query("SELECT o.id, o.nombres, ROUND(AVG(s.score),1) prom, COUNT(*) n
    FROM skill_records s JOIN operators o ON o.id=s.operator_id WHERE 1=1 $wS
    GROUP BY o.id, o.nombres ORDER BY prom DESC LIMIT 10")->fetchAll();

// ── Matriz de Talento 9-Box ─────────────────────────────────
// Eje X = score promedio de habilidad · Eje Y = horas de capacitación
$skAvg = db()->query("SELECT o.id, o.nombres, o.cargo, ROUND(AVG(s.score),1) score
    FROM skill_records s JOIN operators o ON o.id=s.operator_id WHERE 1=1 $wS
    GROUP BY o.id, o.nombres, o.cargo")->fetchAll();
$hrsOp = db()->query("SELECT h.operator_id id, ROUND(SUM(h.total_min)/60,1) hrs
    FROM hours_records h JOIN operators o ON o.id=h.operator_id WHERE 1=1 $wH
    GROUP BY h.operator_id")->fetchAll();
$hrsById = array_column($hrsOp, 'hrs', 'id');

$box = [];
foreach ($skAvg as $r) {
    $box[] = ['id'=>$r['id'],'nombres'=>$r['nombres'],'cargo'=>$r['cargo'],
              'score'=>(float)$r['score'],'hrs'=>(float)($hrsById[$r['id']] ?? 0)];
}
// Umbrales de horas por percentiles (33 / 66) sobre valores > 0
$hrsVals = array_values(array_filter(array_map(fn($b)=>$b['hrs'],$box), fn($v)=>$v>0));
sort($hrsVals);
$pctile = function(array $a, float $p){ if(!$a) return 0.0; $i=(count($a)-1)*$p; $lo=(int)floor($i); $hi=(int)ceil($i);
    return $lo==$hi ? (float)$a[$lo] : $a[$lo] + ($a[$hi]-$a[$lo])*($i-$lo); };
$hT1 = round($pctile($hrsVals, 0.33), 1);
$hT2 = round($pctile($hrsVals, 0.66), 1);
if ($hT2 <= $hT1) $hT2 = $hT1 + 0.1; // evita bandas colapsadas

$BOX_CLASES = [
    'Experto'              => '#27AE60',
    'Talento clave'        => '#2D9CDB',
    'Alto potencial'       => '#6FCF97',
    'En desarrollo'        => '#F2C94C',
    'Requiere seguimiento' => '#F2994A',
    'Bajo desempeño'       => '#EB5757',
];
$clasifica = function(float $score, float $hrs) use ($hT1, $hT2) {
    $sb = $score >= UMBRAL_OPTIMO ? 2 : ($score >= UMBRAL_REGULAR ? 1 : 0);
    $hb = $hrs   >= $hT2 ? 2 : ($hrs >= $hT1 ? 1 : 0);
    $m = [
        '2-2'=>'Experto','2-1'=>'Talento clave','2-0'=>'Alto potencial',
        '1-2'=>'Talento clave','1-1'=>'En desarrollo','1-0'=>'En desarrollo',
        '0-2'=>'Requiere seguimiento','0-1'=>'Requiere seguimiento','0-0'=>'Bajo desempeño',
    ];
    return $m["$sb-$hb"];
};
$boxDatasets = []; $boxCount = array_fill_keys(array_keys($BOX_CLASES), 0);
foreach ($BOX_CLASES as $lab => $col) $boxDatasets[$lab] = ['label'=>$lab,'color'=>$col,'points'=>[]];
foreach ($box as $b) {
    $cl = $clasifica($b['score'], $b['hrs']);
    $boxCount[$cl]++;
    $boxDatasets[$cl]['points'][] = ['x'=>$b['score'],'y'=>$b['hrs'],'n'=>$b['nombres']];
}
$boxDatasets = array_values($boxDatasets);

// ── Opciones para filtros ───────────────────────────────────
$fCargos  = db()->query("SELECT DISTINCT cargo FROM operators WHERE cargo<>'' ORDER BY cargo")->fetchAll(PDO::FETCH_COLUMN);
$fTipos   = db()->query("SELECT DISTINCT v FROM (SELECT tipo_capacitacion v FROM skill_records WHERE tipo_capacitacion<>''
    UNION SELECT tipo_capacitacion FROM speed_records WHERE tipo_capacitacion<>'') t ORDER BY v")->fetchAll(PDO::FETCH_COLUMN);
$fInstrs  = db()->query("SELECT DISTINCT v FROM (SELECT instructor v FROM hours_records WHERE instructor<>''
    UNION SELECT evaluador FROM skill_records WHERE evaluador<>''
    UNION SELECT evaluador FROM speed_records WHERE evaluador<>'') t ORDER BY v")->fetchAll(PDO::FETCH_COLUMN);
$fLugares = db()->query("SELECT DISTINCT v FROM (SELECT lugar v FROM hours_records WHERE lugar<>''
    UNION SELECT lugar FROM speed_records WHERE lugar<>'') t ORDER BY v")->fetchAll(PDO::FETCH_COLUMN);
$fOps     = db()->query("SELECT id, nombres FROM operators WHERE activo=1 ORDER BY nombres")->fetchAll();

$hayFiltros = $F['desde']||$F['hasta']||$F['cargo']||$F['tipocap']||$F['instr']||$F['lugar']||$F['op'];

// Paleta corporativa (para el JS)
$PAL = ['navy'=>'#163A70','blue'=>'#2D9CDB','green'=>'#27AE60','amber'=>'#F2C94C','orange'=>'#F2994A','red'=>'#EB5757','mint'=>'#6FCF97'];
?>

<div class="dashboard-intro d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h4 class="mb-0"><i class="bi bi-speedometer2" style="color:var(--c-blue)"></i> Resumen Ejecutivo</h4>
    <small class="text-muted">Competencias, capacitación y desempeño operativo</small>
  </div>
  <div class="dashboard-actions d-flex align-items-center gap-2 flex-wrap">
    <div class="btn-group dashboard-area-tabs">
      <?php foreach (array_merge(['TODAS'], array_keys($AREAS)) as $a):
        $qs = array_merge($_GET, ['page'=>'dashboard','area'=>$a]); ?>
        <a href="?<?= h(http_build_query($qs)) ?>" class="btn btn-sm <?= $areaF === $a ? 'btn-cosco' : 'btn-outline-primary' ?>"><?= $a ?></a>
      <?php endforeach; ?>
    </div>
    <?php if (defined('SHEETS_DOC_URL') && SHEETS_DOC_URL !== ''): ?>
    <a href="<?= h(SHEETS_DOC_URL) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"
       title="Abrir el libro de Google Sheets en una pestaña nueva">
      <i class="bi bi-file-earmark-spreadsheet"></i> Ver en Google Sheets
    </a>
    <?php endif; ?>
    <?php if (is_admin()): $syncReady = defined('SHEETS_WEBHOOK_URL') && SHEETS_WEBHOOK_URL !== ''; ?>
    <form method="post" action="?action=sheets_resync_all"
          onsubmit="return <?= $syncReady ? "confirm('¿Sincronizar TODOS los datos con Google Sheets? Esto puede tardar unos segundos.')" : "(alert('Primero configura SHEETS_WEBHOOK_URL en config.php con la URL de tu Apps Script Web App.'), false)" ?>">
      <button type="submit" class="btn btn-sm <?= $syncReady ? 'btn-outline-success' : 'btn-outline-warning' ?>"
              title="<?= $syncReady ? 'Sincronizar con Google Sheets' : 'Configura SHEETS_WEBHOOK_URL en config.php primero' ?>">
        <i class="bi bi-<?= $syncReady ? 'table' : 'exclamation-triangle' ?>"></i>
        Sync Google Sheets <?= !$syncReady ? '<span class="badge bg-warning text-dark ms-1" style="font-size:.65rem">Sin URL</span>' : '' ?>
      </button>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- ── Filtros globales ─────────────────────────────────────── -->
<form method="get" class="filter-bar dashboard-filters mb-3">
  <input type="hidden" name="page" value="dashboard">
  <input type="hidden" name="area" value="<?= h($areaF) ?>">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-3 col-lg-2 fb-field">
      <label><i class="bi bi-calendar-event"></i> Desde</label>
      <input type="date" name="desde" value="<?= h($F['desde']) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-6 col-md-3 col-lg-2 fb-field">
      <label><i class="bi bi-calendar-event"></i> Hasta</label>
      <input type="date" name="hasta" value="<?= h($F['hasta']) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-6 col-md-3 col-lg-2 fb-field">
      <label><i class="bi bi-person-badge"></i> Cargo</label>
      <select name="cargo" class="form-select form-select-sm">
        <option value="">Todos</option>
        <?php foreach ($fCargos as $c): ?><option value="<?= h($c) ?>" <?= $F['cargo']===$c?'selected':'' ?>><?= h($c) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3 col-lg-2 fb-field">
      <label><i class="bi bi-mortarboard"></i> Tipo capacitación</label>
      <select name="tipocap" class="form-select form-select-sm">
        <option value="">Todas</option>
        <?php foreach ($fTipos as $t): ?><option value="<?= h($t) ?>" <?= $F['tipocap']===$t?'selected':'' ?>><?= h($t) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3 col-lg-2 fb-field">
      <label><i class="bi bi-person-workspace"></i> Instructor</label>
      <select name="instr" class="form-select form-select-sm">
        <option value="">Todos</option>
        <?php foreach ($fInstrs as $ins): ?><option value="<?= h($ins) ?>" <?= $F['instr']===$ins?'selected':'' ?>><?= h($ins) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3 col-lg-2 fb-field">
      <label><i class="bi bi-geo-alt"></i> Lugar</label>
      <select name="lugar" class="form-select form-select-sm">
        <option value="">Todos</option>
        <?php foreach ($fLugares as $lg): ?><option value="<?= h($lg) ?>" <?= $F['lugar']===$lg?'selected':'' ?>><?= h($lg) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-6 col-lg-4 fb-field">
      <label><i class="bi bi-person"></i> Operador</label>
      <select name="op" class="form-select form-select-sm">
        <option value="0">Todos</option>
        <?php foreach ($fOps as $o): ?><option value="<?= $o['id'] ?>" <?= $F['op']===(int)$o['id']?'selected':'' ?>><?= h($o['nombres']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-lg-8 d-flex gap-2 justify-content-lg-end">
      <button type="submit" class="btn btn-cosco btn-sm"><i class="bi bi-funnel-fill"></i> Aplicar filtros</button>
      <a href="?page=dashboard&area=<?= h($areaF) ?>" class="btn btn-outline-secondary btn-sm <?= $hayFiltros?'':'disabled' ?>"><i class="bi bi-arrow-counterclockwise"></i> Limpiar</a>
    </div>
  </div>
</form>

<!-- ── KPIs ejecutivos ──────────────────────────────────────── -->
<div class="exec-kpis mb-3">
  <div class="exec-kpi k-navy"><i class="bi bi-people-fill ek-ico"></i>
    <div class="ek-label">Total operadores</div><div class="ek-value"><?= $totOps ?></div><div class="ek-sub">Catastro de operadores</div></div>
  <div class="exec-kpi k-blue"><i class="bi bi-clock-history ek-ico"></i>
    <div class="ek-label">Total horas capacitación</div><div class="ek-value"><?= number_format($totHoras,1) ?><small class="fs-6">h</small></div></div>
  <div class="exec-kpi k-blue"><i class="bi bi-hourglass-split ek-ico"></i>
    <div class="ek-label">Prom. horas / operador</div><div class="ek-value"><?= number_format($promHorasOp,1) ?><small class="fs-6">h</small></div><div class="ek-sub"><?= $opsConHoras ?> con registros de horas</div></div>
  <div class="exec-kpi k-navy"><i class="bi bi-stars ek-ico"></i>
    <div class="ek-label">Prom. score habilidades</div><div class="ek-value"><?= number_format($promScore,1) ?>%</div>
    <div class="ek-sub">Habilidades · <?= $totSkill ?> evaluaciones</div></div>
  <div class="exec-kpi k-blue"><i class="bi bi-stopwatch ek-ico"></i>
    <div class="ek-label">Prom. tiempo velocidad</div><div class="ek-value"><?= $promVelSeg ? fmt_seconds($promVelSeg) : '—' ?></div></div>
  <div class="exec-kpi k-green"><i class="bi bi-patch-check-fill ek-ico"></i>
    <div class="ek-label">% Aptos</div><div class="ek-value"><?= $pctApto ?>%</div></div>
  <div class="exec-kpi k-red"><i class="bi bi-x-octagon-fill ek-ico"></i>
    <div class="ek-label">% No aptos</div><div class="ek-value"><?= $pctNoApto ?>%</div></div>
  <div class="exec-kpi k-amber"><i class="bi bi-exclamation-triangle-fill ek-ico"></i>
    <div class="ek-label">Total incidencias</div><div class="ek-value"><?= $totInc ?></div></div>
</div>

<!-- ── Fila: horas por mes + gauge score ────────────────────── -->
<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card chart-card h-100"><div class="card-header d-flex justify-content-between align-items-center gap-2">
        <span><i class="bi bi-bar-chart-line"></i> Horas de entrenamiento (Training) por mes</span>
        <span class="badge chart-total-badge">Total: <?= number_format($totHorasTraining,1) ?> h</span>
      </div>
      <div class="card-body"><canvas id="chHoras" height="110"></canvas></div></div>
  </div>
  <div class="col-lg-4">
    <div class="card chart-card h-100"><div class="card-header"><i class="bi bi-speedometer"></i> Índice de desempeño (score promedio)</div>
      <div class="card-body d-flex flex-column align-items-center justify-content-center">
        <canvas id="chGauge" style="max-height:190px"></canvas>
      </div></div>
  </div>
</div>

<!-- ── Fila: tipo capacitación + score por área + incidencias ─ -->
<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card chart-card h-100"><div class="card-header"><i class="bi bi-mortarboard"></i> Capacitaciones por tipo</div>
      <div class="card-body"><canvas id="chCapTipo" style="max-height:240px"></canvas></div></div>
  </div>
  <div class="col-lg-4">
    <div class="card chart-card h-100"><div class="card-header"><i class="bi bi-diagram-3"></i> Score promedio por área</div>
      <div class="card-body"><canvas id="chScoreArea" style="max-height:240px"></canvas></div></div>
  </div>
  <div class="col-lg-4">
    <div class="card chart-card h-100"><div class="card-header d-flex justify-content-between align-items-center gap-2">
        <span><i class="bi bi-exclamation-diamond"></i> Incidencias por severidad</span>
        <span class="badge chart-total-badge">Total: <?= $totInc ?></span>
      </div>
      <div class="card-body d-flex justify-content-center align-items-center"><canvas id="chInc" style="max-height:240px"></canvas></div></div>
  </div>
</div>

<!-- ── Fila: velocidad por maniobra + tops ──────────────────── -->
<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card chart-card h-100"><div class="card-header"><i class="bi bi-stopwatch"></i> Velocidad promedio por tipo de maniobra <small class="text-muted">(menor es mejor)</small></div>
      <div class="card-body"><canvas id="chVel" height="150"></canvas></div></div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card chart-card h-100"><div class="card-header"><i class="bi bi-trophy"></i> Top 10 · Horas</div>
      <div class="card-body">
        <ol class="mini-rank">
          <?php foreach ($topHoras as $i=>$r): ?>
          <li><span class="mr-pos"><?= $i+1 ?></span>
            <a class="mr-name text-decoration-none" href="?page=perfil&id=<?= $r['id'] ?>" title="<?= h($r['nombres']) ?>"><?= h($r['nombres']) ?></a>
            <span class="mr-val"><?= number_format($r['hrs'],1) ?>h</span></li>
          <?php endforeach; if(!$topHoras): ?><li class="text-muted">Sin datos</li><?php endif; ?>
        </ol>
      </div></div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card chart-card h-100"><div class="card-header"><i class="bi bi-award"></i> Top 10 · Score</div>
      <div class="card-body">
        <ol class="mini-rank">
          <?php foreach ($topScore as $i=>$r): ?>
          <li><span class="mr-pos"><?= $i+1 ?></span>
            <a class="mr-name text-decoration-none" href="?page=perfil&id=<?= $r['id'] ?>" title="<?= h($r['nombres']) ?>"><?= h($r['nombres']) ?></a>
            <span class="mr-val"><?= number_format($r['prom'],1) ?>%</span></li>
          <?php endforeach; if(!$topScore): ?><li class="text-muted">Sin datos</li><?php endif; ?>
        </ol>
      </div></div>
  </div>
</div>

<!-- ── Matriz de Talento 9-Box ──────────────────────────────── -->
<div class="row g-3">
  <div class="col-12">
    <div class="card chart-card">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-grid-3x3-gap-fill"></i> Matriz de Talento · 9-Box</span>
        <small class="text-muted">Eje X: score de habilidad &nbsp;·&nbsp; Eje Y: horas de capacitación</small>
      </div>
      <div class="card-body">
        <div class="row g-3 align-items-center">
          <div class="col-lg-8"><canvas id="ch9box" height="170"></canvas></div>
          <div class="col-lg-4">
            <div class="matrix-legend flex-column">
              <?php foreach ($BOX_CLASES as $lab=>$col): ?>
              <div class="ml-item"><span class="ml-dot" style="background:<?= $col ?>"></span>
                <span class="flex-grow-1"><?= h($lab) ?></span>
                <span class="ml-count"><?= $boxCount[$lab] ?></span></div>
              <?php endforeach; ?>
            </div>
            <hr class="my-2">
            <div class="small text-muted">
              Umbrales de horas: <b><?= number_format($hT1,1) ?>h</b> / <b><?= number_format($hT2,1) ?>h</b> ·
              Score: <b><?= UMBRAL_REGULAR ?>%</b> / <b><?= UMBRAL_OPTIMO ?>%</b><br>
              <?= count($box) ?> operadores evaluados en el filtro actual.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
  const PAL = <?= json_encode($PAL) ?>;
  const U_REG = <?= (int)UMBRAL_REGULAR ?>, U_OPT = <?= (int)UMBRAL_OPTIMO ?>;
  const HT1 = <?= json_encode($hT1) ?>, HT2 = <?= json_encode($hT2) ?>;
  const gridColor = 'rgba(22,58,112,.07)';
  Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
  Chart.defaults.color = '#5a6b82';
  if (window.ChartDataLabels) Chart.register(ChartDataLabels);
  Chart.defaults.set('plugins.datalabels', { display: false }); // opt-in por gráfico

  // Formato corto de meses "2026-05" -> "May 2026"
  const MESES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
  const fmtMes = (ym) => { const [y,m] = ym.split('-'); return `${MESES[parseInt(m,10)-1]} ${y}`; };

  // Horas de entrenamiento (TRAINING) por mes
  new Chart(chHoras, {
    type: 'bar',
    data: { labels: <?= json_encode($serieLabels) ?>.map(fmtMes),
      datasets: [{ label:'Horas de entrenamiento', data: <?= json_encode($serieData) ?>, backgroundColor: PAL.blue, borderRadius: 8, maxBarThickness: 46 }] },
    options: {
      plugins:{
        legend:{display:false},
        tooltip:{ callbacks:{ label:(c)=> ` ${c.formattedValue} h` } },
        datalabels:{ display:true, anchor:'end', align:'top', color:'#163A70', font:{weight:700, size:11},
          formatter:(v)=> v > 0 ? v.toFixed(1) : '' }
      },
      layout:{ padding:{ top:18 } },
      scales:{ y:{ beginAtZero:true, grid:{color:gridColor}, title:{display:true, text:'Horas'} }, x:{ grid:{display:false} } }
    }
  });

  // Gauge score promedio (semicírculo)
  const gv = <?= json_encode($promScore) ?>;
  const gColor = gv >= U_OPT ? PAL.green : (gv >= U_REG ? PAL.amber : PAL.red);
  new Chart(chGauge, {
    type: 'doughnut',
    data: { datasets:[{ data:[gv, 100-gv], backgroundColor:[gColor,'#eef2f7'], borderWidth:0, circumference:180, rotation:270 }] },
    options: { cutout:'72%', plugins:{legend:{display:false}, tooltip:{enabled:false}} },
    plugins: [{ id:'gaugeText', afterDraw(c){
      const {ctx, chartArea:{width,top,height}} = c; ctx.save();
      const cx = c.chartArea.left + width/2, cy = top + height*0.92;
      ctx.textAlign='center'; ctx.fillStyle=gColor; ctx.font='700 30px Segoe UI';
      ctx.fillText(gv.toFixed(1)+'%', cx, cy);
      ctx.fillStyle='#8a97a8'; ctx.font='600 12px Segoe UI';
      ctx.fillText('<?= h(calc_status($promScore)) ?>', cx, cy+18); ctx.restore();
    }}]
  });

  // Capacitaciones por tipo
  new Chart(chCapTipo, {
    type: 'doughnut',
    data: { labels: <?= json_encode(array_column($capTipo,'tc')) ?>,
      datasets:[{ data: <?= json_encode(array_map('intval', array_column($capTipo,'n'))) ?>,
        backgroundColor:[PAL.navy,PAL.blue,PAL.green,PAL.amber,PAL.orange,PAL.red,PAL.mint,'#9b59b6','#95a5a6'],
        borderColor:'#fff', borderWidth:2 }] },
    options: {
      cutout:'62%',
      plugins:{
        legend:{position:'bottom', labels:{boxWidth:12, padding:14, font:{size:11}, usePointStyle:true, pointStyle:'circle'}},
        tooltip:{ callbacks:{ label:(c)=>{ const tot=c.dataset.data.reduce((a,b)=>a+b,0); const pct=tot? (100*c.raw/tot).toFixed(1):0; return ` ${c.label}: ${c.raw} (${pct}%)`; } } },
        datalabels:{ display:true, color:'#fff', font:{weight:700, size:11},
          formatter:(v,ctx)=>{ const tot=ctx.dataset.data.reduce((a,b)=>a+b,0); const pct= tot? 100*v/tot : 0; return pct>=6 ? pct.toFixed(0)+'%' : ''; } }
      }
    }
  });

  // Score por área
  new Chart(chScoreArea, {
    type: 'bar',
    data: { labels: <?= json_encode(array_column($scoreArea,'area')) ?>,
      datasets:[{ label:'Score', data: <?= json_encode(array_map('floatval', array_column($scoreArea,'prom'))) ?>,
        backgroundColor: <?= json_encode(array_map(fn($r)=> ((float)$r['prom']>=UMBRAL_OPTIMO?$PAL['green']:((float)$r['prom']>=UMBRAL_REGULAR?$PAL['amber']:$PAL['red'])), $scoreArea)) ?>,
        borderRadius:8, maxBarThickness:60 }] },
    options: {
      plugins:{
        legend:{display:false},
        tooltip:{ callbacks:{ label:(c)=> ` ${c.formattedValue}%` } },
        datalabels:{ display:true, anchor:'end', align:'top', color:'#163A70', font:{weight:700, size:12},
          formatter:(v)=> v.toFixed(1)+'%' }
      },
      layout:{ padding:{ top:18 } },
      scales:{ y:{beginAtZero:true, max:100, grid:{color:gridColor}, title:{display:true, text:'Score (%)'}}, x:{grid:{display:false}} }
    }
  });

  // Incidencias por severidad (a partir de los datos reales; la suma coincide con el KPI)
  const incLabels = <?= json_encode(array_column($incSev,'severidad')) ?>;
  const incData   = <?= json_encode(array_map('intval', array_column($incSev,'n'))) ?>;
  const incColors = <?= json_encode(array_map(fn($r)=>$SEV_COLOR[$r['severidad']] ?? '#c9d2df', $incSev)) ?>;
  new Chart(chInc, {
    type: 'pie',
    data: { labels: incLabels,
      datasets:[{ data: incData, backgroundColor: incColors, borderColor:'#fff', borderWidth:2 }] },
    options: {
      plugins:{
        legend:{position:'bottom', labels:{boxWidth:12, padding:14, font:{size:11}, usePointStyle:true, pointStyle:'circle'}},
        tooltip:{ callbacks:{ label:(c)=>{ const tot=c.dataset.data.reduce((a,b)=>a+b,0); const pct=tot? (100*c.raw/tot).toFixed(1):0; return ` ${c.label}: ${c.raw} (${pct}%)`; } } },
        datalabels:{ display:true, color:'#fff', font:{weight:700, size:12},
          formatter:(v,ctx)=>{ if(!v) return ''; const tot=ctx.dataset.data.reduce((a,b)=>a+b,0); const pct= tot? 100*v/tot : 0; return `${v} (${pct.toFixed(0)}%)`; } }
      }
    }
  });

  // Velocidad por maniobra (barra horizontal)
  new Chart(chVel, {
    type:'bar',
    data:{ labels: <?= json_encode(array_column($velManiobra,'ctx')) ?>,
      datasets:[{ label:'Segundos prom.', data: <?= json_encode(array_map('intval', array_column($velManiobra,'t'))) ?>,
        backgroundColor: PAL.navy, borderRadius:6, maxBarThickness:26 }] },
    options:{
      indexAxis:'y',
      plugins:{
        legend:{display:false},
        tooltip:{ callbacks:{ label:(c)=> ` ${c.formattedValue} s` } },
        datalabels:{ display:true, anchor:'end', align:'end', color:'#163A70', font:{weight:700, size:11},
          formatter:(v)=> v+'s' }
      },
      layout:{ padding:{ right:26 } },
      scales:{ x:{beginAtZero:true, grid:{color:gridColor}, title:{display:true, text:'Segundos (menor es mejor)'}}, y:{grid:{display:false}} }
    }
  });

  // 9-Box (scatter con zonas)
  const boxDs = <?= json_encode($boxDatasets) ?>;
  const zonesPlugin = { id:'zones', beforeDatasetsDraw(chart){
    const {ctx, chartArea:{left,right,top,bottom}, scales:{x,y}} = chart;
    ctx.save(); ctx.strokeStyle='rgba(22,58,112,.22)'; ctx.setLineDash([6,4]); ctx.lineWidth=1;
    [U_REG,U_OPT].forEach(v=>{ const px=x.getPixelForValue(v); ctx.beginPath(); ctx.moveTo(px,top); ctx.lineTo(px,bottom); ctx.stroke(); });
    [HT1,HT2].forEach(v=>{ const py=y.getPixelForValue(v); ctx.beginPath(); ctx.moveTo(left,py); ctx.lineTo(right,py); ctx.stroke(); });
    ctx.restore();
  }};
  new Chart(ch9box, {
    type:'scatter',
    data:{ datasets: boxDs.map(d=>({ label:d.label, data:d.points, backgroundColor:d.color,
      pointRadius:6, pointHoverRadius:9, borderColor:'#fff', borderWidth:1 })) },
    options:{
      plugins:{
        legend:{position:'bottom', labels:{boxWidth:10, font:{size:10.5}, usePointStyle:true}},
        tooltip:{ callbacks:{ label:(c)=> `${c.raw.n}: ${c.raw.x}% · ${c.raw.y}h` } }
      },
      scales:{
        x:{ title:{display:true, text:'Score de habilidad (%)'}, min:0, max:100, grid:{color:gridColor} },
        y:{ title:{display:true, text:'Horas de capacitación'}, beginAtZero:true, grid:{color:gridColor} }
      }
    },
    plugins:[zonesPlugin]
  });
});
</script>
