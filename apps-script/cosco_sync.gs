/**
 * ================================================================
 * COSCO Competencias Operativas — Google Sheets Sync
 * Web App (doPost) que recibe datos desde el sistema PHP
 * y los escribe en las pestañas correspondientes del libro.
 *
 * INSTALACIÓN:
 *  1. Abre Extensions > Apps Script en este libro de Google Sheets
 *  2. Borra todo el contenido y pega este código completo
 *  3. Guarda (Ctrl+S)
 *  4. Click en "Deployar" > "New deployment"
 *  5. Tipo: "Web app"
 *  6. Execute as: "Me"
 *  7. Who has access: "Anyone"  (o "Anyone with Google account")
 *  8. Click "Deploy" y copia la URL generada
 *  9. Pega esa URL en config.php de tu sistema PHP como SHEETS_WEBHOOK_URL
 * ================================================================
 */

// ID del libro de Google Sheets (extraído de la URL)
const SPREADSHEET_ID = '1WURF9x6vOuse36jHVm6Gxi1JQKuLaZ9rUCldA0hOIz0';

// Cabeceras de cada pestaña
const SHEET_HEADERS = {
  Operadores: [
    'ID', 'Código', 'DNI', 'Nombres', 'Cargo', 'Lugar', 'Fecha Ingreso',
    'Tipo Grúa', 'Estado Laboral', 'Actualizado'
  ],
  Horas: [
    'ID', 'Fecha', 'Operador', 'Cargo', 'Área', 'Tipo Capacitación',
    'Grúa / Contexto', 'Actividad', 'Instructor', 'Tiempo Total (hh:mm:ss)', 'Registrado'
  ],
  Habilidades: [
    'ID', 'Fecha', 'Operador', 'Cargo', 'Área', 'Tipo Capacitación',
    'Contexto', 'Score (%)', 'Status', 'Apto', 'Instructor', 'Registrado'
  ],
  Velocidad: [
    'ID', 'Fecha', 'Operador', 'Cargo', 'Área', 'Tipo Capacitación',
    'Lugar', 'Tipo Maniobra', 'Fase 1 (hh:mm:ss)', 'Fase 2 (hh:mm:ss)', 'Fase 3 (hh:mm:ss)',
    'Fase 4 (hh:mm:ss)', 'Fase 5 (hh:mm:ss)', 'Total (hh:mm:ss)', 'Status', 'Apto', 'Registrado'
  ],
  Incidencias: [
    'ID', 'Fecha', 'Operador', 'Cargo', 'Área', 'Equipo',
    'Tipo', 'Descripción', 'Severidad', 'Estado', 'Reportado Por', 'Registrado'
  ]
};

/**
 * Punto de entrada HTTP para el Web App.
 * El sistema PHP enviará POST con JSON en el body.
 */
function doPost(e) {
  try {
    const payload = JSON.parse(e.postData.contents);
    const module  = payload.module;  // 'operadores' | 'horas' | 'habilidades' | 'velocidad' | 'incidencias'
    const action  = payload.action;  // 'upsert' | 'delete' | 'resync'
    const rows    = payload.rows || (payload.row ? [payload.row] : []);

    // ── Subida de archivos a Google Drive (adjuntos de incidencias) ──
    if (action === 'upload') {
      return handleDriveUpload(payload);
    }

    const ss = SpreadsheetApp.openById(SPREADSHEET_ID);

    if (action === 'resync') {
      // Reemplaza toda la pestaña con los datos enviados
      bulkResync(ss, module, rows);
    } else if (action === 'upsert') {
      rows.forEach(row => upsertRow(ss, module, row));
    } else if (action === 'delete') {
      rows.forEach(row => deleteRow(ss, module, row.id));
    }

    return ContentService
      .createTextOutput(JSON.stringify({ ok: true, module, action, count: rows.length }))
      .setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return ContentService
      .createTextOutput(JSON.stringify({ ok: false, error: err.message }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

/** Permite testear el web app desde el navegador */
function doGet(e) {
  return ContentService
    .createTextOutput(JSON.stringify({ ok: true, status: 'COSCO Sync Web App activo' }))
    .setMimeType(ContentService.MimeType.JSON);
}

/**
 * ⚙️ EJECUTAR UNA SOLA VEZ para autorizar el acceso a Google Drive.
 * En el editor de Apps Script: selecciona esta función "autorizarDrive"
 * en el menú desplegable de arriba y pulsa ▶ Ejecutar. Google mostrará
 * una ventana pidiendo permisos de Drive: acéptalos. Después de eso,
 * la subida de fotos/declaraciones funcionará.
 */
function autorizarDrive() {
  const folder = DriveApp.getFolderById('19lh_mQOX5y6xdDKDaM01M5irNgDsNgKR');
  // Crear un archivo de prueba fuerza el permiso de ESCRITURA (drive completo)…
  const test = folder.createFile('permiso_test.txt', 'ok', MimeType.PLAIN_TEXT);
  test.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
  // …y lo enviamos a la papelera para no dejar basura.
  test.setTrashed(true);
  Logger.log('Drive autorizado (lectura + escritura) en: ' + folder.getName());
}

// ── Subida de archivos a Drive ─────────────────────────────────────

/**
 * Recibe archivos en base64 y los guarda en la carpeta de Drive indicada.
 * Devuelve la lista con id, url de vista y url de miniatura de cada archivo.
 * Los archivos se comparten como "cualquiera con el enlace (lector)" para
 * poder mostrar las miniaturas dentro del sistema.
 */
function handleDriveUpload(payload) {
  const folder = DriveApp.getFolderById(payload.folderId);
  const out = [];

  (payload.files || []).forEach(function (f) {
    const bytes = Utilities.base64Decode(f.dataBase64);
    const blob  = Utilities.newBlob(bytes, f.mime || 'application/octet-stream', f.name || 'archivo');
    const file  = folder.createFile(blob);
    try {
      file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
    } catch (shareErr) {
      // Si la política del dominio bloquea el enlace público, continuamos igual.
    }
    const id = file.getId();
    out.push({
      id:    id,
      name:  file.getName(),
      kind:  f.kind || '',
      url:   'https://drive.google.com/file/d/' + id + '/view',
      thumb: 'https://drive.google.com/thumbnail?id=' + id + '&sz=w600'
    });
  });

  return ContentService
    .createTextOutput(JSON.stringify({ ok: true, files: out }))
    .setMimeType(ContentService.MimeType.JSON);
}

// ── Helpers ────────────────────────────────────────────────────────

/**
 * Obtiene (o crea) una pestaña con cabeceras correctas.
 */
function getOrCreateSheet(ss, moduleName) {
  const tabName = capitalize(moduleName);
  let sheet = ss.getSheetByName(tabName);

  if (!sheet) {
    sheet = ss.insertSheet(tabName);
    writeHeaders(sheet, tabName);
  } else if (sheet.getLastRow() === 0) {
    writeHeaders(sheet, tabName);
  } else {
    // Verificar que la cabecera coincida; si no, la sobreescribimos
    const existingHeaders = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    const expected = SHEET_HEADERS[tabName] || [];
    if (JSON.stringify(existingHeaders.slice(0, expected.length)) !== JSON.stringify(expected)) {
      writeHeaders(sheet, tabName);
    }
  }

  return sheet;
}

function writeHeaders(sheet, tabName) {
  const headers = SHEET_HEADERS[tabName] || [];
  if (!headers.length) return;

  // Limpiar fila 1 y escribir cabeceras
  sheet.getRange(1, 1, 1, Math.max(headers.length, sheet.getLastColumn() || headers.length))
       .clearContent();
  const range = sheet.getRange(1, 1, 1, headers.length);
  range.setValues([headers]);

  // Formato de cabecera: fondo azul oscuro, texto blanco, negrita
  range.setBackground('#0B3D73');
  range.setFontColor('#FFFFFF');
  range.setFontWeight('bold');
  range.setFontSize(10);
  sheet.setFrozenRows(1);

  // Autoajustar columnas
  for (let c = 1; c <= headers.length; c++) {
    sheet.setColumnWidth(c, 140);
  }
}

/**
 * Convierte un registro PHP en array de celdas según el módulo.
 */
function rowToArray(moduleName, row) {
  const tab = capitalize(moduleName);
  const now = new Date().toLocaleString('es-PE', { timeZone: 'America/Lima' });

  switch (tab) {
    case 'Operadores':
      return [
        row.id, row.codigo || '', row.dni || '', row.nombres || '',
        row.cargo || '', row.lugar || '', row.fecha_ingreso || '',
        row.tipo_grua || '', row.estado || 'ACTIVO', now
      ];
    case 'Horas':
      return [
        row.id, row.fecha, row.nombres || row.operador || '',
        row.cargo || '', row.area || '', row.tipo_capacitacion || '',
        row.contexto || '', row.actividad || '', row.evaluador || '',
        minsToHMS(row.total_minutos), now
      ];
    case 'Habilidades':
      return [
        row.id, row.fecha, row.nombres || row.operador || '',
        row.cargo || '', row.area || '', row.tipo_capacitacion || '',
        row.contexto || '', (parseFloat(row.score) || 0) / 100, row.status || '',
        row.apto ? 'SÍ' : 'NO', row.evaluador || '', now
      ];
    case 'Velocidad':
      return [
        row.id, row.fecha, row.nombres || row.operador || '',
        row.cargo || '', row.area || '', row.tipo_capacitacion || '',
        row.lugar || '', row.contexto || '',
        secsToHMS(row.f1), secsToHMS(row.f2), secsToHMS(row.f3), secsToHMS(row.f4), secsToHMS(row.f5),
        secsToHMS(row.total_seg), row.status || '', row.apto ? 'SÍ' : 'NO', now
      ];
    case 'Incidencias':
      return [
        row.id, row.fecha, row.nombres || row.operador || '',
        row.cargo || '', row.area || '', row.equipo || '',
        row.tipo || '', row.descripcion || '', row.severidad || '',
        row.estado || '', row.reportado_por || '', now
      ];
    default:
      return [];
  }
}

/**
 * Busca la fila con el mismo ID (columna A) y la actualiza, o agrega al final.
 */
function upsertRow(ss, moduleName, row) {
  const sheet = getOrCreateSheet(ss, moduleName);
  const rowArr = rowToArray(moduleName, row);
  if (!rowArr.length) return;

  const id = String(row.id);
  const lastRow = sheet.getLastRow();

  if (lastRow > 1) {
    const idCol = sheet.getRange(2, 1, lastRow - 1, 1).getValues();
    for (let i = 0; i < idCol.length; i++) {
      if (String(idCol[i][0]) === id) {
        // Actualizar fila existente
        sheet.getRange(i + 2, 1, 1, rowArr.length).setValues([rowArr]);
        applyRowFormatting(sheet, i + 2, moduleName);
        return;
      }
    }
  }

  // Nueva fila
  sheet.appendRow(rowArr);
  applyRowFormatting(sheet, sheet.getLastRow(), moduleName);
}

/**
 * Elimina la fila con el ID indicado.
 */
function deleteRow(ss, moduleName, id) {
  const sheet = getOrCreateSheet(ss, moduleName);
  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return;

  const idCol = sheet.getRange(2, 1, lastRow - 1, 1).getValues();
  for (let i = idCol.length - 1; i >= 0; i--) {
    if (String(idCol[i][0]) === String(id)) {
      sheet.deleteRow(i + 2);
      return;
    }
  }
}

/**
 * Reemplaza toda la pestaña con los datos del array enviado.
 */
function bulkResync(ss, moduleName, rows) {
  const sheet = getOrCreateSheet(ss, moduleName);

  // Limpiar datos (mantener cabecera fila 1)
  if (sheet.getLastRow() > 1) {
    sheet.getRange(2, 1, sheet.getLastRow() - 1, sheet.getLastColumn()).clearContent();
  }

  if (!rows.length) return;

  const data = rows.map(r => rowToArray(moduleName, r));
  const headers = SHEET_HEADERS[capitalize(moduleName)] || [];
  sheet.getRange(2, 1, data.length, headers.length).setValues(data);

  // Formato zebra
  for (let i = 0; i < data.length; i++) {
    applyRowFormatting(sheet, i + 2, moduleName);
  }
}

/**
 * Aplica formato zebra a una fila de datos.
 */
function applyRowFormatting(sheet, rowNum, moduleName) {
  const tab = capitalize(moduleName);
  const headers = SHEET_HEADERS[tab] || [];
  const range = sheet.getRange(rowNum, 1, 1, headers.length);
  range.setBackground(rowNum % 2 === 0 ? '#f8fafc' : '#ffffff');
  range.setFontSize(9);
  range.setVerticalAlignment('middle');

  if (tab === 'Habilidades') {
    const scoreCol = headers.indexOf('Score (%)') + 1; // columna H
    if (scoreCol > 0) {
      sheet.getRange(rowNum, scoreCol).setNumberFormat('0.00%');
    }
  }
}

function capitalize(str) {
  return str ? str.charAt(0).toUpperCase() + str.slice(1).toLowerCase() : '';
}

/**
 * Convierte minutos enteros a cadena HH:MM:SS
 * Ejemplo: 90 → "01:30:00"
 */
function minsToHMS(mins) {
  const totalSecs = Math.round((mins || 0) * 60);
  const h = Math.floor(totalSecs / 3600);
  const m = Math.floor((totalSecs % 3600) / 60);
  const s = totalSecs % 60;
  return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
}

/**
 * Convierte segundos enteros a cadena HH:MM:SS
 * Ejemplo: 189 → "00:03:09"
 */
function secsToHMS(secs) {
  const t = Math.round(secs || 0);
  const h = Math.floor(t / 3600);
  const m = Math.floor((t % 3600) / 60);
  const s = t % 60;
  return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
}
