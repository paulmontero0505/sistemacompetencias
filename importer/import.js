/**
 * Importador de los libros "Registro de Competencias y Eficiencia Operativa" (ARMG, PC, QC) a MySQL.
 *
 * Uso:
 *   node import.js "C:\ruta\ARMG.xlsx" "C:\ruta\PC.xlsx" "C:\ruta\QC.xlsx" [--wipe]
 *
 * El área se detecta por el nombre del archivo (ARMG / PC / QC).
 * --wipe: vacía las tablas de registros antes de importar (no borra usuarios).
 */
const XLSX = require('xlsx');
const mysql = require('mysql2/promise');
const path = require('path');

const DB = { host: '127.0.0.1', user: 'root', password: '', database: 'competencias_digital' };

const args = process.argv.slice(2);
const wipe = args.includes('--wipe');
const files = args.filter(a => !a.startsWith('--'));
if (!files.length) {
    console.error('Uso: node import.js <ARMG.xlsx> <PC.xlsx> <QC.xlsx> [--wipe]');
    process.exit(1);
}

// ---------- utilidades ----------
const norm = s => String(s || '').toUpperCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();

function excelDate(v) {
    if (v == null || v === '') return null;
    if (v instanceof Date) return v.toISOString().slice(0, 10);
    if (typeof v === 'number' && v > 366) {
        const d = new Date(Math.round((v - 25569) * 86400) * 1000);
        return d.toISOString().slice(0, 10);
    }
    const s = String(v).trim();
    let m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) return m[0];
    m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
    if (m) return `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`;
    return null;
}

/** Duración -> segundos. Acepta fracción de día (número), "h:mm:ss", "mm:ss", Date (hora). */
function toSeconds(v) {
    if (v == null || v === '') return 0;
    if (v instanceof Date) return v.getUTCHours() * 3600 + v.getUTCMinutes() * 60 + v.getUTCSeconds();
    if (typeof v === 'number') {
        if (v > 366) return 0; // es una fecha, no duración
        return Math.round(v * 86400);
    }
    const p = String(v).trim().split(':').map(Number);
    if (p.some(isNaN)) return 0;
    if (p.length === 3) return p[0] * 3600 + p[1] * 60 + p[2];
    if (p.length === 2) return p[0] * 60 + p[1];
    return 0;
}
const toMinutes = v => Math.round(toSeconds(v) / 60);

const isCheck = v => /^APROBADO|^SI|✅|^1$|TRUE/i.test(String(v || '').trim()) && !/^NO |DESAPROB/i.test(String(v || '').trim());
const scale15 = v => { const m = String(v || '').match(/^([1-5])/); return m ? +m[1] : null; };
const pct = v => {
    if (v == null || v === '') return null;
    const n = parseFloat(v);
    if (isNaN(n)) return null;
    return Math.round((n <= 1.5 ? n * 100 : n) * 100) / 100;
};
const statusOf = (p, area) => {
    const prefix = { ARMG: 'C', QC: 'A', PC: 'B', WL: 'D' }[area] || 'C';
    return prefix + (p >= 85 ? '3' : p >= 70 ? '2' : p >= 50 ? '1' : '0');
};
const cleanStatus = s => { s = norm(s); return ['OPTIMO', 'REGULAR', 'BAJO'].includes(s) ? s : null; };
const cleanDni = v => { const s = String(v || '').replace(/\.0$/, '').replace(/\D/g, ''); return s.length >= 7 && s.length <= 12 ? s : null; };

function sheetRows(wb, name) {
    const ws = wb.Sheets[name];
    if (!ws) return [];
    return XLSX.utils.sheet_to_json(ws, { header: 1, raw: true, defval: null });
}

// ---------- main ----------
(async () => {
    const con = await mysql.createConnection(DB);
    if (wipe) {
        for (const t of ['hours_records', 'skill_records', 'speed_records']) await con.query(`DELETE FROM ${t}`);
        console.log('Tablas de registros vaciadas (--wipe).');
    }

    // 1) Operadores (DATA_OP de todos los libros)
    const opsByName = new Map();
    const opsByDni = new Map();
    const books = [];
    for (const f of files) {
        const base = path.basename(f).toUpperCase();
        const area = base.includes('ARMG') ? 'ARMG' : base.includes('QC') ? 'QC' : 'PC';
        const wb = XLSX.readFile(f, { raw: true });
        books.push({ area, wb, file: f });
        for (const r of sheetRows(wb, 'DATA_OP').slice(1)) {
            const dni = cleanDni(r[1]);
            const nombres = norm(r[2]);
            if (!dni || !nombres) continue;
            if (!opsByDni.has(dni)) {
                opsByDni.set(dni, { codigo: String(r[0] || '').trim() || null, dni, nombres, cargo: norm(r[3]) || 'OPERADOR', lugar: norm(r[4]) || 'CHANCAY', fecha_ingreso: excelDate(r[5]) });
            }
        }
    }
    let nuevos = 0;
    for (const op of opsByDni.values()) {
        const [res] = await con.query(
            `INSERT INTO operators (codigo, dni, nombres, cargo, lugar, fecha_ingreso)
             VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE codigo=VALUES(codigo), cargo=VALUES(cargo), id=LAST_INSERT_ID(id)`,
            [op.codigo, op.dni, op.nombres, op.cargo, op.lugar, op.fecha_ingreso]);
        op.id = res.insertId;
        if (res.affectedRows === 1) nuevos++;
        opsByName.set(op.nombres, op);
    }
    console.log(`Operadores: ${opsByDni.size} en maestros (${nuevos} nuevos).`);

    async function findOp(nombre, dni, cargo) {
        const n = norm(nombre);
        if (opsByName.has(n)) return opsByName.get(n);
        const d = cleanDni(dni);
        if (d && opsByDni.has(d)) return opsByDni.get(d);
        if (!n) return null;
        // crear operador no presente en DATA_OP
        const op = { codigo: null, dni: d || ('SIN-' + Math.random().toString().slice(2, 9)), nombres: n, cargo: norm(cargo) || 'OPERADOR', lugar: 'CHANCAY', fecha_ingreso: null };
        const [res] = await con.query(
            `INSERT INTO operators (codigo, dni, nombres, cargo, lugar) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)`,
            [op.codigo, op.dni, op.nombres, op.cargo, op.lugar]);
        op.id = res.insertId;
        opsByName.set(n, op);
        opsByDni.set(op.dni, op);
        return op;
    }

    const counts = { horas: 0, skills: 0, speed: 0, saltados: 0 };

    async function insHoras(op, area, fecha, tipoPrep, actividad, lugar, instructor, obs, detalle) {
        const total = Object.values(detalle).reduce((a, b) => a + b, 0);
        if (!fecha || total <= 0) { counts.saltados++; return; }
        const det = Object.fromEntries(Object.entries(detalle).filter(([, v]) => v > 0));
        await con.query(
            `INSERT INTO hours_records (operator_id, area, fecha, tipo_preparacion, tipo_actividad, lugar, instructor, observacion, detalle, total_min)
             VALUES (?,?,?,?,?,?,?,?,?,?)`,
            [op.id, area, fecha, norm(tipoPrep) || 'ENTRENAMIENTO', String(actividad || '').trim() || null,
             norm(lugar) || null, String(instructor || '').trim() || null, String(obs || '').trim() || null,
             JSON.stringify(det), total]);
        counts.horas++;
    }

    async function insSkill(op, area, fecha, tipoCap, contexto, evaluador, items, score, status, comentarios) {
        if (!fecha || !items.length) { counts.saltados++; return; }
        const s = score != null ? score : 0;
        await con.query(
            `INSERT INTO skill_records (operator_id, area, fecha, tipo_capacitacion, contexto, evaluador, items, score, status, apto, comentarios)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)`,
            [op.id, area, fecha, norm(tipoCap) || 'ENTRENAMIENTO', contexto || null, String(evaluador || '').trim() || null,
            JSON.stringify(items), s, statusOf(s, area), s >= 70 ? 1 : 0, String(comentarios || '').trim() || null]);
        counts.skills++;
    }

    async function insSpeed(op, area, fecha, tipoCap, lugar, contexto, evaluador, fases, totalSeg, estSeg, ef, status, obs) {
        if (!fecha || totalSeg <= 0) { counts.saltados++; return; }
        const e = ef != null ? Math.min(150, ef) : (estSeg > 0 ? Math.round(Math.min(150, estSeg / totalSeg * 100) * 100) / 100 : 0);
        await con.query(
            `INSERT INTO speed_records (operator_id, area, fecha, tipo_capacitacion, lugar, contexto, evaluador, fases, total_seg, estimado_seg, eficiencia, status, cumple, observaciones)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
            [op.id, area, fecha, norm(tipoCap) || 'ENTRENAMIENTO', norm(lugar) || null, contexto || null,
             String(evaluador || '').trim() || null, JSON.stringify(fases), totalSeg, estSeg, e,
             status || statusOf(Math.min(100, e)), e >= 80 ? 1 : 0, String(obs || '').trim() || null]);
        counts.speed++;
    }

    for (const { area, wb, file } of books) {
        console.log(`\n== ${area} :: ${path.basename(file)} ==`);

        if (area === 'ARMG') {
            for (const r of sheetRows(wb, 'HORAS').slice(1)) {
                const op = await findOp(r[2] || r[1], r[11], r[12]);
                if (!op) { counts.saltados++; continue; }
                await insHoras(op, area, excelDate(r[3]) || excelDate(r[0]), r[5], r[8], r[6], r[4], null,
                    { 'INDUCCION': toMinutes(r[9]), 'INSTRUCCION': toMinutes(r[7]) });
            }
            const items6 = ['Uso de controles', 'Precisión en posicionamiento', 'Percepción de altura', 'Manejo con over height frame', 'Dominio del sistema TOS', 'Control de seguridad'];
            const items5 = ['Trabajo en simultáneo', 'Seguimiento de tareas en TCS', 'Manejo y reconocimiento BroadVision', 'Respuesta ante fallas', 'Proyección de actividades diarias'];
            for (const r of sheetRows(wb, 'HABILIDADES').slice(1)) {
                const op = await findOp(r[1], r[19], r[20]);
                if (!op) { counts.saltados++; continue; }
                const items = [];
                items6.forEach((it, i) => { const v = scale15(r[5 + i]); if (v) items.push({ g: 'HABILIDADES OPERACIONALES', i: it, v }); });
                items5.forEach((it, i) => { const v = scale15(r[14 + i]); if (v) items.push({ g: 'HABILIDADES OPERACIONALES', i: it, v }); });
                const score = items.length ? Math.round(items.reduce((a, b) => a + b.v, 0) / (items.length * 5) * 10000) / 100 : null;
                await insSkill(op, area, excelDate(r[2]) || excelDate(r[0]), r[4], null, r[3], items, score, null,
                    [r[11], /apto/i.test(String(r[13] || '')) && !/No,/i.test(String(r[13] || '')) ? 'APTO' : (r[13] ? 'REQUIERE MÁS ENTRENAMIENTO' : '')].filter(Boolean).join(' | '));
            }
            const fasesA = ['Toma / Enganche (Pick-up)', 'Izaje / Elevación (Lift)', 'Traslado / Recorrido (Travel)', 'Posicionamiento Final / Desenganche', 'Retorno'];
            for (const r of sheetRows(wb, 'VELOCIDAD').slice(1)) {
                const op = await findOp(r[1], null, null);
                if (!op) { counts.saltados++; continue; }
                const src = [r[15] ?? r[4], r[16] ?? r[5], r[17] ?? r[6], r[18] ?? r[7], r[8]];
                const fases = fasesA.map((f, i) => ({ f, s: toSeconds(src[i]) }));
                const total = fases.reduce((a, b) => a + b.s, 0);
                await insSpeed(op, area, excelDate(r[2]) || excelDate(r[0]), r[14], null, String(r[3] || '').trim() || null, null, fases, total, 0, null, null, r[13]);
            }
        }

        if (area === 'PC') {
            for (const r of sheetRows(wb, 'FORM HORAS').slice(1)) {
                const op = await findOp(r[3], r[9], r[10]);
                if (!op) { counts.saltados++; continue; }
                await insHoras(op, area, excelDate(r[1]) || excelDate(r[0]), r[2], r[7], null, null, null, {
                    'BUQUE GRANELERO (CUCHARA)': toMinutes(r[4]),
                    'BUQUE CARGA SUELTA (GANCHO-APAREJO)': toMinutes(r[5]),
                    'BUQUE CONTEINERO (SPREADER)': toMinutes(r[6]),
                });
            }
            const gruposPC = [
                ['CONTROL DE PÉNDULO', ['Precisión del gancho', 'Full speed', 'Movimiento simultáneo', 'Control de pluma'], 4],
                ['PERCEPCIÓN DE ALTURA', ['Movimiento de HOIST', 'Llegada al punto', 'Traslado de bulto', 'Control de braceo'], 8],
                ['USO DE CONTROLES', ['Control de tower y giro del spreader', 'Uso del BYPASS'], 12],
            ];
            for (const r of sheetRows(wb, 'FORM HABILIDAD').slice(1)) {
                const op = await findOp(r[3], r[21], r[22]);
                if (!op) { counts.saltados++; continue; }
                const items = [];
                for (const [g, its, off] of gruposPC) its.forEach((it, i) => { if (r[off + i] != null) items.push({ g, i: it, v: isCheck(r[off + i]) ? 1 : 0 }); });
                await insSkill(op, area, excelDate(r[1]) || excelDate(r[0]), r[2], String(r[15] || '').trim() || null, r[14], items, pct(r[20]), cleanStatus(r[25]), r[16]);
            }
            const fasesP = ['TRASLADO INGRESO', 'POSICIONAMIENTO INGRESO', 'TRASLADO SALIDA', 'POSICIONAMIENTO SALIDA', 'MAR'];
            for (const r of sheetRows(wb, 'FORM VELOCIDAD').slice(1)) {
                const op = await findOp(r[3], r[14], r[15]);
                if (!op) { counts.saltados++; continue; }
                const fases = fasesP.map((f, i) => ({ f, s: toSeconds(r[5 + i]) }));
                await insSpeed(op, area, excelDate(r[1]) || excelDate(r[0]), r[2], r[4], null, r[10], fases,
                    toSeconds(r[11]) || fases.reduce((a, b) => a + b.s, 0), toSeconds(r[12]), pct(r[13]), cleanStatus(r[18]), null);
            }
        }

        if (area === 'QC') {
            for (const r of sheetRows(wb, 'HORAS').slice(1)) {
                const op = await findOp(r[3], null, r[14]); // DNI de la hoja es poco confiable; se busca por nombre
                if (!op) { counts.saltados++; continue; }
                await insHoras(op, area, excelDate(r[1]) || excelDate(r[0]), r[2], r[8], r[10], r[9], r[11], {
                    'INDUCCION': toMinutes(r[4]),
                    'INSTRUCCION EN GRUA (CABINA)': toMinutes(r[5]),
                    'INSTRUCCION EN NAVE (CABINA)': toMinutes(r[6]),
                    'INSTRUCCION EN NAVE (ROS)': toMinutes(r[7]),
                });
            }
            const gruposQC = [
                ['CONOCIMIENTO DEL TOS', ['Reconocimiento del BAROTI', 'Conocimiento de ingreso al PAD', 'Colocar grúa asignada', 'Conocimiento del BY PLAN'], 4],
                ['CONTROL DE PENDULO', ['Movimiento de HOIST', 'Llegada al punto', 'Traslado de bulto', 'Control de braceo'], 8],
                ['USO DE CONTROLES', ['Uso del TRIM/LIST/VIEW', 'Uso del BYPASS'], 12],
                ['PERCEPCION DE ALTURA', ['Llegada a la viga de mar', 'Llegada al ROW asignado', 'Traslado sobre puente trinca', 'Llegada al DCV o ITV'], 14],
            ];
            for (const r of sheetRows(wb, 'HABILIDAD').slice(1)) {
                const op = await findOp(r[3], r[25], r[26]);
                if (!op) { counts.saltados++; continue; }
                const items = [];
                for (const [g, its, off] of gruposQC) its.forEach((it, i) => { if (r[off + i] != null) items.push({ g, i: it, v: isCheck(r[off + i]) ? 1 : 0 }); });
                await insSkill(op, area, excelDate(r[1]) || excelDate(r[0]), r[2], norm(r[19]) || null, null, items, pct(r[24]), cleanStatus(r[29]), r[18]);
            }
            const fasesQ = ['TRASLADO INGRESO', 'POSICIONAMIENTO INGRESO', 'TRASLADO SALIDA', 'POSICIONAMIENTO SALIDA', 'RAMPA'];
            for (const r of sheetRows(wb, 'DURACION').slice(1)) {
                const op = await findOp(r[3], r[15], r[16]);
                if (!op) { counts.saltados++; continue; }
                const fases = fasesQ.map((f, i) => ({ f, s: toSeconds(r[5 + i]) }));
                await insSpeed(op, area, excelDate(r[1]) || excelDate(r[0]), r[2], r[4], norm(r[11]) || null, r[10], fases,
                    toSeconds(r[12]) || fases.reduce((a, b) => a + b.s, 0), toSeconds(r[13]), pct(r[14]), cleanStatus(r[19]), null);
            }
        }
    }

    console.log(`\nImportación completa: ${counts.horas} registros de horas, ${counts.skills} evaluaciones, ${counts.speed} mediciones de velocidad. Saltados: ${counts.saltados}.`);
    await con.end();
})().catch(e => { console.error('ERROR:', e.message); process.exit(1); });
