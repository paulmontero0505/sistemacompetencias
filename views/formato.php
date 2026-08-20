<?php
// Formato imprimible de evaluación por trabajador (como la PLANTILLA EVALUACION del Excel)
$ops = operadores_activos();
$opId  = (int)($_GET['operator_id'] ?? 0);
$areaS = strtoupper($_GET['farea'] ?? 'PC');
if (!isset($AREAS[$areaS])) $areaS = 'PC';
$isBlank = isset($_GET['blank']);
$mes  = (int)($_GET['mes'] ?? date('n'));
$anio = (int)($_GET['anio'] ?? date('Y'));
$cfgF = $AREAS[$areaS];
$meses = meses_es();
$horasTipo = strtoupper(trim((string)($_GET['htipo'] ?? 'ALL')));
if ($horasTipo !== 'ALL' && !in_array($horasTipo, $TIPOS_PREPARACION, true)) $horasTipo = 'ALL';
$horasTipoTexto = $horasTipo === 'ALL' ? '' : ' (' . $horasTipo . ')';

$op = null;
if ($opId) {
    $st = db()->prepare("SELECT * FROM operators WHERE id=?");
    $st->execute([$opId]);
    $op = $st->fetch();
}

// Modo "snapshot": consolidado por últimos registros / totales de la grúa (usado al imprimir desde Desempeño).
// Sin snap: filtra por mes/año (formato mensual clásico).
$snap = isset($_GET['snap']);

$skills = $speeds = $horas = [];
if ($op) {
    if ($snap) {
        // Última evaluación por contexto (todo el histórico, no un mes)
        $st = db()->prepare("SELECT * FROM skill_records WHERE operator_id=? AND area=? ORDER BY fecha DESC, id DESC");
        $st->execute([$opId, $areaS]);
        foreach ($st->fetchAll() as $r) {
            $ctx = $r['contexto'] ?: 'GENERAL';
            if (!isset($skills[$ctx])) $skills[$ctx] = $r;
        }
        // Velocidad: última medición
        $st = db()->prepare("SELECT eficiencia ef, total_seg prom, fecha, observaciones, 1 n FROM speed_records WHERE operator_id=? AND area=? ORDER BY fecha DESC, id DESC LIMIT 1");
        $st->execute([$opId, $areaS]);
        $speeds = $st->fetch() ?: [];
        // Horas: total acumulado en la grúa
        $sqlHoras = "SELECT SUM(total_min) t, GROUP_CONCAT(DISTINCT tipo_actividad SEPARATOR ', ') acts FROM hours_records WHERE operator_id=? AND area=?";
        $paramsHoras = [$opId, $areaS];
        if ($horasTipo !== 'ALL') { $sqlHoras .= " AND UPPER(TRIM(tipo_preparacion))=?"; $paramsHoras[] = $horasTipo; }
        $st = db()->prepare($sqlHoras);
        $st->execute($paramsHoras);
        $horas = $st->fetch();
    } else {
        $st = db()->prepare("SELECT * FROM skill_records WHERE operator_id=? AND area=? AND MONTH(fecha)=? AND YEAR(fecha)=? ORDER BY fecha DESC, id DESC");
        $st->execute([$opId, $areaS, $mes, $anio]);
        // una evaluación por contexto (la más reciente del mes)
        foreach ($st->fetchAll() as $r) {
            $ctx = $r['contexto'] ?: 'GENERAL';
            if (!isset($skills[$ctx])) $skills[$ctx] = $r;
        }
        $st = db()->prepare("SELECT eficiencia ef, total_seg prom, fecha, observaciones, 1 n FROM speed_records WHERE operator_id=? AND area=? AND MONTH(fecha)=? AND YEAR(fecha)=? ORDER BY fecha DESC, id DESC LIMIT 1");
        $st->execute([$opId, $areaS, $mes, $anio]);
        $speeds = $st->fetch();
        $sqlHoras = "SELECT SUM(total_min) t, GROUP_CONCAT(DISTINCT tipo_actividad SEPARATOR ', ') acts FROM hours_records WHERE operator_id=? AND area=? AND MONTH(fecha)=? AND YEAR(fecha)=?";
        $paramsHoras = [$opId, $areaS, $mes, $anio];
        if ($horasTipo !== 'ALL') { $sqlHoras .= " AND UPPER(TRIM(tipo_preparacion))=?"; $paramsHoras[] = $horasTipo; }
        $st = db()->prepare($sqlHoras);
        $st->execute($paramsHoras);
        $horas = $st->fetch();
    }
}
$evaluador = '';
$descripcion = '';
$observaciones = [];
foreach ($skills as $s) {
    if (!$evaluador && $s['evaluador']) $evaluador = $s['evaluador'];
    if (!$descripcion) $descripcion = $s['tipo_capacitacion'];
    // El estado de aptitud no forma parte de la observación del evaluador.
    $comentario = preg_replace('/(?:\s*[-.:]?\s*)\bAPTO\b\.?\s*$/iu', '', trim((string)($s['comentarios'] ?? '')));
    $comentario = trim($comentario);
    if ($comentario !== '') {
        $observaciones[] = [
            'origen' => 'Habilidades · ' . ($s['contexto'] ?: 'GENERAL') . ' (' . $s['fecha'] . ')',
            'texto' => $comentario,
        ];
    }
}
if (!empty($speeds['observaciones'])) {
    $observaciones[] = [
        'origen' => 'Velocidad (' . ($speeds['fecha'] ?? 'sin fecha') . ')',
        'texto' => trim((string)$speeds['observaciones']),
    ];
}
$isPrint = isset($_GET['print']);
?>
<?php if (!$isPrint): ?>
<div class="card mb-3 no-print">
  <div class="card-header"><i class="bi bi-printer-fill"></i> Formato de evaluación — filtrar e imprimir para firma</div>
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <input type="hidden" name="page" value="formato">
      <div class="col-md-4"><label class="form-label small">Trabajador</label>
        <select name="operator_id" class="form-select form-select-sm js-op-select" required>
          <option value="">— Seleccionar —</option>
          <?php foreach ($ops as $o): ?><option value="<?= $o['id'] ?>" <?= $o['id'] == $opId ? 'selected' : '' ?>><?= h($o['nombres']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-2"><label class="form-label small">Área</label>
        <select name="farea" class="form-select form-select-sm">
          <?php foreach (array_keys($AREAS) as $a): ?><option <?= $a === $areaS ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-2"><label class="form-label small">Mes</label>
        <select name="mes" class="form-select form-select-sm">
          <?php foreach ($meses as $n => $m): ?><option value="<?= $n ?>" <?= $n === $mes ? 'selected' : '' ?>><?= $m ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-md-2"><label class="form-label small">Año</label>
        <select name="anio" class="form-select form-select-sm">
          <?php for ($y = date('Y'); $y >= 2024; $y--): ?><option <?= $y == $anio ? 'selected' : '' ?>><?= $y ?></option><?php endfor; ?>
        </select></div>
      <div class="col-md-2 d-flex gap-1">
        <button class="btn btn-cosco btn-sm flex-fill"><i class="bi bi-search"></i> Ver</button>
        <?php if ($op): ?><button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print()"><i class="bi bi-printer"></i></button><?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($isBlank): ?>
<?php if ($isPrint): ?>
<div class="no-print d-flex justify-content-end mb-3">
  <button type="button" class="btn btn-cosco btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
</div>
<?php endif; ?>
<div class="print-sheet">
  <table class="mb-0">
    <tr>
      <td style="width:30%; border:none; text-align:left"><img src="<?= BASE_URL ?>/assets/img/logo.svg" style="height:52px" alt="COSCO SHIPPING"></td>
      <td style="border:none" class="ph-title">FORMATO DE EVALUACIÓN DE OPERADORES — <?= h($cfgF['nombre']) ?></td>
    </tr>
  </table>
  <table>
    <tr><td class="lbl">NOMBRES</td><td colspan="3">&nbsp;</td></tr>
    <tr><td class="lbl">CARGO</td><td>&nbsp;</td><td class="lbl">DNI</td><td>&nbsp;</td></tr>
    <tr><td class="lbl">PERIODO</td><td>&nbsp;</td><td class="lbl">DESCRIPCIÓN</td><td>&nbsp;</td></tr>
  </table>

  <table class="mt-2">
    <tr><td colspan="4" class="grp text-center" style="background:#c9dcf0">EVALUACIÓN DE DESEMPEÑO OPERATIVO</td></tr>
    <tr><th class="grp" style="width:32%">GRUPO</th><th class="grp">ACTIVIDAD / HABILIDAD</th><th class="grp text-center" style="width:14%">RESULTADO</th><th class="grp text-center" style="width:12%">PUNTAJE</th></tr>
    <?php foreach ($cfgF['skills_grupos'] as $grupo => $grupoCfg):
        $items = $grupoCfg['items'] ?? [];
        foreach ($items as $i => $item): ?>
      <tr>
        <?php if ($i === 0): ?><td class="grp" rowspan="<?= count($items) ?>"><?= h($grupo) ?></td><?php endif; ?>
        <td><?= h($item) ?></td>
        <td>&nbsp;</td>
        <?php if ($i === 0): ?><td rowspan="<?= count($items) ?>">&nbsp;</td><?php endif; ?>
      </tr>
    <?php endforeach; endforeach; ?>
    <tr><td colspan="2" class="grp text-end">TOTAL HABILIDAD</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  </table>

  <table class="mt-2">
    <tr><td colspan="4" class="grp text-center" style="background:#dbeadb">PORCENTAJE INDIVIDUAL POR <?= h(mb_strtoupper($cfgF['skills_ctx_label'] ?? 'LUGAR DE INSTRUCCIÓN')) ?></td></tr>
    <tr><th class="grp"><?= h(mb_strtoupper($cfgF['skills_ctx_label'] ?? 'LUGAR DE INSTRUCCIÓN')) ?></th><th class="grp text-center" style="width:22%">FECHA</th><th class="grp text-center" style="width:16%">PUNTAJE</th><th class="grp text-center" style="width:18%">NIVEL ALCANZADO</th></tr>
    <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  </table>

  <table class="mt-2" style="table-layout:fixed">
    <colgroup><col style="width:33.33%"><col style="width:33.33%"><col style="width:33.34%"></colgroup>
    <tr><td class="lbl">VELOCIDAD</td><td>PORCENTAJE OBTENIDO: &nbsp;</td><td>TIEMPO OBTENIDO: &nbsp;</td></tr>
    <tr><td class="lbl">HORAS ENTRENADAS</td><td>&nbsp;</td><td>INCIDENTE</td></tr>
  </table>

  <table class="mt-3">
    <tr><td class="firma-box" style="width:50%"><div style="border-top:1px solid #000; margin:0 30px; padding-top:4px">FIRMA DEL JEFE DEL CENTRO DE OPERACIONES<br><b>VASQUEZ BRUNO VICTOR ELIAS</b></div></td><td class="firma-box"><div style="border-top:1px solid #000; margin:0 30px; padding-top:4px">FIRMA DEL OPERADOR</div></td></tr>
  </table>
</div>
<?php elseif ($op): ?>
<?php if ($isPrint): ?>
<div class="no-print d-flex justify-content-end mb-3">
  <button type="button" class="btn btn-cosco btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
</div>
<?php endif; ?>
<div class="print-sheet">
  <table class="mb-0">
    <tr>
      <td style="width:30%; border:none; text-align:left"><img src="<?= BASE_URL ?>/assets/img/logo.svg" style="height:52px"></td>
      <td style="border:none" class="ph-title">FORMATO DE EVALUACIÓN DE OPERADORES — <?= h($cfgF['nombre']) ?></td>
    </tr>
  </table>
  <table>
    <tr><td class="lbl">NOMBRES</td><td colspan="3"><?= h($op['nombres']) ?></td></tr>
    <tr><td class="lbl">CARGO</td><td><?= h($op['cargo']) ?></td><td class="lbl">DNI</td><td><?= h($op['dni']) ?></td></tr>
    <tr><td class="lbl">PERIODO</td><td><?= $snap ? 'Consolidado (últimos registros)' : $meses[$mes] . ' ' . $anio ?></td><td class="lbl">DESCRIPCIÓN</td><td><?= h($descripcion ?: '—') ?></td></tr>
  </table>

  <?php if (!$skills): ?>
    <table class="mt-2"><tr><td class="text-center text-muted">Sin evaluaciones de habilidad<?= $snap ? '' : ' en ' . $meses[$mes] . ' ' . $anio ?> para <?= $areaS ?></td></tr></table>

  <?php elseif ($snap):
    // ── Consolidado HÍBRIDO: promedio de los últimos registros por lugar de instrucción ──
    $UAPTO = ARMG_UMBRAL_APTO; // aprobado desde 75%
    $esEscala = $cfgF['skills_tipo'] === 'escala';
    $esTresNiveles = $cfgF['skills_tipo'] === 'trinivel';
    $grupos = array_keys($cfgF['skills_grupos']);
    $perLugarGrupo = []; $perLugarTotal = []; $perLugarFecha = [];
    $itemsByLugar = []; // [ctx][item] = valor (para el promedio por ítem)
    foreach ($skills as $ctx => $s) {
        $items = json_decode($s['items'] ?? '[]', true) ?: [];
        $porGrupo = [];
        foreach ($items as $it) { $porGrupo[$it['g']][] = $it; $itemsByLugar[$ctx][$it['i']] = $it['v']; }
        foreach ($porGrupo as $g => $its) {
            $peso = $cfgF['skills_grupos'][$g]['peso'] ?? 100;
            $perLugarGrupo[$ctx][$g] = $esEscala
                ? (count($its) ? array_sum(array_column($its, 'v')) / (count($its) * 5) * 100 : 0)
                : ($esTresNiveles
                    ? (count($its) ? array_sum(array_column($its, 'v')) / (count($its) * 2) * 100 : 0)
                    : (count($its) ? array_sum(array_column($its, 'v')) / count($its) * $peso : 0));
        }
        $perLugarTotal[$ctx] = (float)$s['score'];
        $perLugarFecha[$ctx] = $s['fecha'];
    }
    $hibGrupo = [];
    foreach ($grupos as $g) {
        $vals = [];
        foreach ($perLugarGrupo as $gg) if (isset($gg[$g])) $vals[] = $gg[$g];
        if ($vals) $hibGrupo[$g] = array_sum($vals) / count($vals);
    }
    $hibTotal = $perLugarTotal ? array_sum($perLugarTotal) / count($perLugarTotal) : 0;
    $hibApto = $hibTotal >= $UAPTO;
  ?>
    <table class="mt-2">
      <tr><td colspan="4" class="grp text-center" style="background:#c9dcf0">EVALUACIÓN DE DESEMPEÑO OPERATIVO</td></tr>
      <tr><th class="grp" style="width:32%">GRUPO</th><th class="grp">ACTIVIDAD / HABILIDAD</th><th class="grp text-center" style="width:14%">RESULTADO</th><th class="grp text-center" style="width:12%">PUNTAJE</th></tr>
      <?php foreach ($grupos as $g):
          $peso = $cfgF['skills_grupos'][$g]['peso'] ?? 100;
          $cfgItems = $cfgF['skills_grupos'][$g]['items'] ?? [];
          // ítems con al menos un valor en algún lugar, con su promedio entre lugares
          $filas = [];
          foreach ($cfgItems as $lbl) {
              $vals = [];
              foreach ($itemsByLugar as $mp) if (array_key_exists($lbl, $mp)) $vals[] = (float)$mp[$lbl];
              if ($vals) $filas[] = ['i' => $lbl, 'avg' => array_sum($vals) / count($vals)];
          }
          if (!$filas) continue;
          $nf = count($filas);
          foreach ($filas as $j => $r): ?>
        <tr>
          <?php if ($j === 0): ?><td class="grp" rowspan="<?= $nf ?>"><?= h($g) ?><?= $esEscala ? '' : " ({$peso}%)" ?></td><?php endif; ?>
          <td><?= h($r['i']) ?></td>
          <td class="text-center">
            <?php if ($esEscala): ?>
              <b><?= number_format($r['avg'], 1) ?></b>/5
            <?php elseif ($esTresNiveles): ?>
              <b><?= number_format($r['avg'], 1) ?></b>/2
            <?php else: $pct = $r['avg'] * 100; ?>
              <span class="<?= $pct >= 100 ? 'check-ok' : ($pct <= 0 ? 'check-bad' : '') ?>"><?= number_format($pct, 0) ?>%</span>
            <?php endif; ?>
          </td>
          <?php if ($j === 0): ?><td class="text-center" rowspan="<?= $nf ?>"><b><?= number_format($hibGrupo[$g] ?? 0, 1) ?>%</b></td><?php endif; ?>
        </tr>
      <?php endforeach; endforeach; ?>
      <tr>
        <td colspan="2" class="grp text-end">TOTAL HABILIDAD</td>
        <td class="text-center"><b><?= number_format($hibTotal, 1) ?>%</b></td>
        <td class="text-center"><b class="<?= $hibApto ? 'check-ok' : 'check-bad' ?>"><?= $hibApto ? 'APROBADO' : 'DESAPROBADO' ?></b></td>
      </tr>
    </table>

    <table class="mt-2">
      <tr><td colspan="4" class="grp text-center" style="background:#dbeadb">PORCENTAJE INDIVIDUAL POR LUGAR DE INSTRUCCIÓN</td></tr>
      <tr><th class="grp">LUGAR DE INSTRUCCIÓN</th><th class="grp text-center" style="width:22%">FECHA</th><th class="grp text-center" style="width:16%">PUNTAJE</th><th class="grp text-center" style="width:18%">NIVEL ALCANZADO</th></tr>
      <?php foreach ($perLugarTotal as $ctx => $sc): ?>
        <tr>
          <td><?= h($ctx) ?></td>
          <td class="text-center"><?= h($perLugarFecha[$ctx]) ?></td>
          <td class="text-center"><b><?= number_format($sc, 1) ?>%</b></td>
          <td class="text-center"><b><?= h(skill_level_label((float)$sc, $areaS)) ?></b></td>
        </tr>
      <?php endforeach; ?>
    </table>

  <?php else: foreach ($skills as $ctx => $s): $items = json_decode($s['items'] ?? '[]', true) ?: []; ?>
    <table class="mt-2">
      <tr><td colspan="4" class="grp text-center" style="background:#c9dcf0"><?= h($ctx) ?> — <?= h($s['fecha']) ?></td></tr>
      <tr><th class="grp" style="width:32%">GRUPO</th><th class="grp">ACTIVIDAD / HABILIDAD</th><th class="grp" style="width:10%">RESULTADO</th><th class="grp" style="width:12%">PUNTAJE</th></tr>
      <?php
      $porGrupo = [];
      foreach ($items as $it) $porGrupo[$it['g']][] = $it;
      foreach ($porGrupo as $g => $its):
          $peso = $cfgF['skills_grupos'][$g]['peso'] ?? 100;
          $esEscala = $cfgF['skills_tipo'] === 'escala';
          $esTresNiveles = $cfgF['skills_tipo'] === 'trinivel';
          if ($esEscala) { $gsc = count($its) ? array_sum(array_column($its, 'v')) / (count($its) * 5) * 100 : 0; }
          elseif ($esTresNiveles) { $gsc = count($its) ? array_sum(array_column($its, 'v')) / (count($its) * 2) * 100 : 0; }
          else { $gsc = count($its) ? array_sum(array_column($its, 'v')) / count($its) * $peso : 0; }
          foreach ($its as $j => $it): ?>
        <tr>
          <?php if ($j === 0): ?><td class="grp" rowspan="<?= count($its) ?>"><?= h($g) ?><?= $esEscala ? '' : " ({$peso}%)" ?></td><?php endif; ?>
          <td><?= h($it['i']) ?></td>
          <td class="text-center"><?= $esEscala ? '<b>' . (int)$it['v'] . '</b>/5' : ((int)$it['v'] ? '<span class="check-ok">✔</span>' : '<span class="check-bad">✘</span>') ?></td>
          <?php if ($j === 0): ?><td class="text-center" rowspan="<?= count($its) ?>"><b><?= number_format($gsc, 1) ?>%</b></td><?php endif; ?>
        </tr>
      <?php endforeach; endforeach; ?>
      <tr>
        <td colspan="2" class="grp text-end">TOTAL HABILIDAD</td>
        <td class="text-center"><b><?= number_format((float)$s['score'], 1) ?>%</b></td>
        <?php $aprobado = skill_es_apto((float)$s['score'], $areaS); ?>
        <td class="text-center"><b class="<?= $aprobado ? 'check-ok' : 'check-bad' ?>"><?= $aprobado ? 'APROBADO' : 'DESAPROBADO' ?></b></td>
      </tr>
    </table>
  <?php endforeach; endif; ?>

  <table class="mt-2" style="table-layout:fixed">
    <colgroup>
      <col style="width:33.33%">
      <col style="width:33.33%">
      <col style="width:33.34%">
    </colgroup>
    <tr>
      <td class="lbl">VELOCIDAD</td>
      <td>PORCENTAJE OBTENIDO: <b><?= ($speeds['n'] ?? 0) ? number_format((float)$speeds['ef'], 1) . '%' : '—' ?></b></td>
      <td>TIEMPO OBTENIDO: <b><?= ($speeds['n'] ?? 0) ? fmt_seconds((int)$speeds['prom']) : '—' ?></b><?= ($speeds['fecha'] ?? '') ? ' <span class="small text-muted">(' . h($speeds['fecha']) . ')</span>' : '' ?></td>
    </tr>
    <tr>
      <td class="lbl">HORAS ENTRENADAS</td>
      <td><b><?= fmt_minutes((int)($horas['t'] ?? 0)) ?> h</b> <?= $snap ? 'acumuladas en la grúa' : 'en el periodo' ?><?= h($horasTipoTexto) ?></td>
      <td class="small"><?= h(mb_strimwidth($horas['acts'] ?? '—', 0, 90, '…')) ?></td>
    </tr>
  </table>

  <?php if ($observaciones): ?>
  <table class="mt-2">
    <tr>
      <td class="lbl" style="width:30%">OBSERVACIONES / COMENTARIOS</td>
      <td>
        <?php foreach ($observaciones as $observacion): ?>
          <div class="mb-1"><?= nl2br(h($observacion['texto'])) ?></div>
        <?php endforeach; ?>
      </td>
    </tr>
  </table>
  <?php endif; ?>

  <table class="mt-3">
    <tr>
      <td class="firma-box" style="width:50%"><div style="border-top:1px solid #000; margin:0 30px; padding-top:4px">FIRMA DEL JEFE DEL CENTRO DE OPERACIONES<br><b>VASQUEZ BRUNO VICTOR ELIAS</b></div></td>
      <td class="firma-box"><div style="border-top:1px solid #000; margin:0 30px; padding-top:4px">FIRMA DEL OPERADOR<br><b><?= h($op['nombres']) ?></b></div></td>
    </tr>
  </table>
  <div class="text-center small text-muted mt-2"><?= h(APP_EMPRESA) ?> — Generado el <?= date('d/m/Y H:i') ?></div>
</div>
<?php elseif (!$isPrint): ?>
  <div class="alert alert-info no-print"><i class="bi bi-info-circle"></i> Selecciona un trabajador, área y periodo para generar el formato.</div>
<?php endif; ?>
