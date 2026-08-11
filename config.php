<?php
// == Configuración general ==
// ── Conexión a la base de datos (cPanel) ───────────────────────────
// En cPanel el HOST casi siempre es 'localhost'.
define('DB_HOST', 'localhost');
// ⚠️ IMPORTANTE: En cPanel el NOMBRE de la base también lleva el prefijo de tu cuenta
//    (ej. portally_...). Verifica el nombre EXACTO en cPanel → "MySQL® Databases"
//    y reemplázalo aquí si no coincide.
define('DB_NAME', 'portally_operatorcontrol');
define('DB_USER', 'portally_operatorcontrol');
define('DB_PASS', 'Sistemas2100*');
define('APP_NAME', 'Registro de Competencias y Eficiencia Operativa');
define('APP_EMPRESA', 'COSCO SHIPPING Ports Chancay PERU S.A.');
// ── URL base ────────────────────────────────────────────────────────
// Los archivos están en la RAÍZ del dominio (public_html), por eso va vacío.
// URL: https://www.operatorcontrol.site/
define('BASE_URL', '');

// ── Google Sheets Sync ──────────────────────────────────────────────
// Pega aquí la URL del Web App de Apps Script después de desplegarlo.
// Déjalo vacío ('') para deshabilitar la sincronización.
define('SHEETS_WEBHOOK_URL', 'https://script.google.com/macros/s/AKfycbzzfQa_W5ehHreukhnkI2zTrNXzG12xoVeMYV0_vtOyfr41iNU5XYxAg2ibwKeMQLEaJQ/exec');
// URL del libro de Google Sheets (para el botón "Ver en Google Sheets")
define('SHEETS_DOC_URL', 'https://docs.google.com/spreadsheets/d/1WURF9x6vOuse36jHVm6Gxi1JQKuLaZ9rUCldA0hOIz0/edit');

// ── Google Drive (adjuntos de incidencias) ──────────────────────────
// ID de la carpeta de Drive donde se guardarán fotos y declaraciones.
// (extraído de la URL de la carpeta que compartiste)
define('DRIVE_FOLDER_ID', '19lh_mQOX5y6xdDKDaM01M5irNgDsNgKR');
// Límites de subida
define('DRIVE_MAX_FOTOS', 3);
define('DRIVE_MAX_FOTO_MB', 8);
define('DRIVE_MAX_DOC_MB', 15);

// Umbrales de status (porcentaje)
define('UMBRAL_OPTIMO', 80);   // >= 80  => OPTIMO / APTO
define('UMBRAL_REGULAR', 50);  // >= 50  => REGULAR

// Habilidades: nivel 0 <50 | nivel 1 50-69.9 | nivel 2 70-84.9 | nivel 3 >=85.
// Prefijos por área: ARMG=C, QC=A, PC=B y Wheel Loader=D.
define('ARMG_UMBRAL_C1', 50);
define('ARMG_UMBRAL_C2', 70);
define('ARMG_UMBRAL_C3', 85);
define('ARMG_UMBRAL_APTO', 70);
define('SKILL_STATUS_PREFIX', ['ARMG' => 'C', 'QC' => 'A', 'PC' => 'B', 'WL' => 'D']);

// == Definición de áreas y sus formularios ==
// skills_tipo: 'escala' = items 1-5 | 'check' = cumple/no cumple con grupos ponderados
$AREAS = [
    'ARMG' => [
        'nombre'          => 'Grúa de Patio (ARMG)',
        'horas_campos'    => ['TIEMPO DE INSTRUCCION'],
        'horas_lugares'   => ['SALA', 'ROS', 'CABINA'],
        'skills_tipo'     => 'escala',
        'skills_grupos'   => [
            'HABILIDADES OPERACIONALES' => ['peso' => 100, 'items' => [
                'Trabajo en simultáneo',
                'Seguimiento de tareas en TCS',
                'Manejo y reconocimiento BroadVision',
                'Respuesta ante fallas',
                'Proyección de actividades diarias',
                'Uso de controles',
                'Precisión en posicionamiento',
                'Percepción de altura',
                'Manejo con over height frame',
                'Dominio del sistema TOS',
                'Control de seguridad',
            ]],
        ],
        'skills_contextos' => ['ROS', 'CABINA', 'SIMULADOR'],
        'skills_ctx_label' => 'Lugar de instrucción',
        'speed_fases'      => ['Toma / Enganche (Pick-up)', 'Izaje / Elevación (Lift)', 'Traslado / Recorrido (Travel)', 'Posicionamiento Final / Desenganche', 'Retorno'],
        'speed_lugares'    => ['PATIO', 'ROS'],
        'speed_ctx_label'  => 'Tipo de maniobra',
        'speed_contextos'  => ['DESCARGA (DCV)', 'EMBARQUE (DCV)', 'REMOCION EN PATIO', 'TWIN', 'OTROS'],
    ],
    'QC' => [
        'nombre'          => 'Grúa Pórtico de Muelle (QC/STS)',
        'horas_campos'    => ['TIEMPO DE INSTRUCCION'],
        'horas_lugares'   => ['OFICINA', 'MUELLE 02', 'MUELLE 03', 'MUELLE 04'],
        'skills_tipo'     => 'check',
        'skills_grupos'   => [
            'CONOCIMIENTO DEL TOS'  => ['peso' => 30, 'items' => ['Reconocimiento del BAROTI', 'Conocimiento de ingreso al PAD', 'Colocar grúa asignada', 'Conocimiento del BY PLAN']],
            'CONTROL DE PENDULO'    => ['peso' => 50, 'items' => ['Movimiento de HOIST', 'Llegada al punto', 'Traslado de bulto', 'Control de braceo']],
            'USO DE CONTROLES'      => ['peso' => 10, 'items' => ['Uso del TRIM/LIST/VIEW', 'Uso del BYPASS']],
            'PERCEPCION DE ALTURA'  => ['peso' => 10, 'items' => ['Llegada a la viga de mar', 'Llegada al ROW asignado', 'Traslado sobre puente trinca', 'Llegada al DCV o ITV']],
        ],
        'skills_contextos' => ['ROS', 'CABINA'],
        'skills_ctx_label' => 'Lugar de instrucción',
        'speed_fases'      => ['TRASLADO INGRESO', 'POSICIONAMIENTO INGRESO', 'TRASLADO SALIDA', 'POSICIONAMIENTO SALIDA', 'RAMPA'],
        'speed_lugares'    => ['MUELLE 02', 'MUELLE 03', 'MUELLE 04'],
        'speed_ctx_label'  => 'Tipo de inspección',
        'speed_contextos'  => ['ROS', 'CABINA'],
    ],
    'PC' => [
        'nombre'          => 'Portal Crane (MHC)',
        'horas_campos'    => ['TIEMPO DE INSTRUCCION'],
        'horas_lugares'   => ['MUELLE 02', 'MUELLE 03', 'CIRCUITO', 'SALA'],
        'skills_tipo'     => 'check',
        'skills_grupos'   => [
            'CONTROL DE PÉNDULO'    => ['peso' => 50, 'items' => ['Precisión del gancho', 'Full speed', 'Movimiento simultáneo', 'Control de pluma']],
            'PERCEPCIÓN DE ALTURA'  => ['peso' => 30, 'items' => ['Movimiento de HOIST', 'Llegada al punto', 'Traslado de bulto', 'Control de braceo']],
            'USO DE CONTROLES'      => ['peso' => 20, 'items' => ['Control de tower y giro del spreader', 'Uso del BYPASS']],
        ],
        'skills_contextos' => ['BUQUE GENERAL (CUCHARON/APAREJO/GANCHO)', 'BUQUE CONTEINERO (SPREADER)'],
        'skills_ctx_label' => 'Tipo de buque / grúa',
        'speed_fases'      => ['TRASLADO INGRESO', 'POSICIONAMIENTO INGRESO', 'TRASLADO SALIDA', 'POSICIONAMIENTO SALIDA', 'MAR'],
        'speed_lugares'    => ['MUELLE 02', 'MUELLE 03'],
        'speed_ctx_label'  => 'Tipo de maniobra',
        'speed_contextos'  => ['INGRESO/SALIDA DE MUELLE', 'CICLO COMPLETO', 'OTROS'],
    ],
    'WL' => [
        'nombre'          => 'Cargador Frontal (Wheel Loader)',
        'operator_label'  => 'Operators Wheel Loaders',
        'horas_campos'    => ['TIEMPO DE INSTRUCCION'],
        'horas_lugares'   => ['PATIO', 'ALMACENES', 'ZONA DE ACOPIO', 'CIRCUITO'],
        'skills_tipo'     => 'trinivel',
        'skills_grupos'   => [
            'CHECK LIST DE DESEMPEÑO' => ['peso' => 100, 'items' => [
                'Realiza checklist antes de realizar alguna actividad.',
                'Demuestra familiaridad con el equipo y zonas de acopio.',
                'Verifica que el lugar de trabajo esté libre de obstáculos antes de iniciar las operaciones.',
                'Evita giros en falso o patina las ruedas en el producto.',
                'Ejecuta trabajos de acopio en patio o zonas de arrumaje de forma ordenada.',
                'Realiza trabajos de forma coordinada con el operador de grúa (radio o señales).',
                'Realiza trabajos de despacho y carga para camiones externos (almacenes y zonas de acopio).',
                'Opera de forma correcta y realiza el arrumaje de altura con el arrastre.',
                'Mantiene una distancia de seguridad con los operarios en bodega o almacenes.',
                'Realiza limpieza con la barredora en zonas permitidas.',
                'Realiza el acopio y el posicionamiento de la Hopper en puntos designados.',
                'Descarga y embarque de maquinarias en roro y estacionamiento en zonas (despacho).',
                'Realiza las actividades a tiempo y demuestra una excelente organización.',
            ]],
        ],
        'skills_contextos' => ['PATIO', 'ALMACENES', 'ZONA DE ACOPIO', 'DESPACHO', 'OTROS'],
        'skills_ctx_label' => 'Zona de operación',
        'speed_fases'      => ['ACERCAMIENTO', 'CARGA / ACOPIO', 'TRASLADO', 'DESCARGA / POSICIONAMIENTO', 'RETORNO'],
        'speed_lugares'    => ['PATIO', 'ALMACENES', 'ZONA DE ACOPIO', 'DESPACHO'],
        'speed_ctx_label'  => 'Tipo de maniobra',
        'speed_contextos'  => ['ACOPIO', 'CARGA DE CAMIÓN', 'POSICIONAMIENTO DE HOPPER', 'LIMPIEZA CON BARREDORA', 'OTROS'],
    ],
];

$TIPOS_PREPARACION = ['TRAINING', 'DIFUSION', 'CERTIFICACION'];
$TIPOS_ACTIVIDAD   = ['INSTRUCCION EN NAVE', 'INSTRUCCION EN CIRCUITO', 'TEORIA GENERAL', 'SOMBRA', 'SIMULADOR', 'PRACTICAS', 'INCIDENTE', 'DESCARGA Y EMBARQUE', 'RECONOCIMIENTO DEL EQUIPO', 'CERTIFICACION', 'OTROS'];
$CARGOS = [
    'OPERADOR DE GRUAS STS',
    'OPERADOR DE GRUA DE PATIO ARMG',
    'OPERADOR DE PORTAL CRANE',
    'OPERADOR DE REACH STACKER',
    'OPERADOR DE CARGADOR FRONTAL',
    'OPERADOR DE MONTACARGAS',
    'CONDUCTOR DE CAMION DE CONTENEDORES',
    'TÉCNICO DE REEFER',
    'ASISTENTE DE ESTIBA',
];
$INCIDENT_TIPOS = ['GOLPE / COLISION', 'DAÑO A EQUIPO', 'DAÑO A CARGA', 'ERROR OPERACIONAL', 'INCUMPLIMIENTO DE PROCEDIMIENTO', 'CASI ACCIDENTE', 'OTROS'];

// Criterios de evaluación de desempeño (escala 1-5, igual para ARMG/QC/PC)
$DESEMPENO_CRITERIOS = [
    'Puntualidad y asistencia',
    'Cumplimiento de metas y productividad',
    'Trabajo en equipo',
    'Actitud y disposición',
    'Cuidado y uso adecuado de equipos',
    'Cumplimiento de procedimientos de seguridad',
    'Comunicación con supervisores y compañeros',
];
