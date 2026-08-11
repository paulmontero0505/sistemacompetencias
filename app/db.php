<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        // Las migraciones no deben impedir el acceso si el usuario MySQL de cPanel
        // no tiene privilegios DDL. El detalle queda en el log de PHP.
        try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS custom_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(180) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        $pdo->exec("CREATE TABLE IF NOT EXISTS custom_lugares (
            id INT AUTO_INCREMENT PRIMARY KEY,
            area ENUM('ARMG','QC','PC','WL') NOT NULL,
            nombre VARCHAR(120) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_area_nombre (area, nombre)
        ) ENGINE=InnoDB");

        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            name VARCHAR(80) PRIMARY KEY,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            usuario VARCHAR(120) NULL,
            accion VARCHAR(80) NOT NULL,
            modulo VARCHAR(60) NOT NULL,
            registro_id INT NULL,
            detalle TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (created_at), INDEX (modulo), INDEX (user_id)
        ) ENGINE=InnoDB");
        $applied = $pdo->query("SELECT name FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);

        $col = $pdo->query("SHOW COLUMNS FROM operators LIKE 'estado_capacitacion'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE operators ADD COLUMN estado_capacitacion ENUM('ENTRENAMIENTO','REENTRENAMIENTO') NOT NULL DEFAULT 'ENTRENAMIENTO' AFTER activo");
        }

        $col2 = $pdo->query("SHOW COLUMNS FROM operators LIKE 'tipos_grua'")->fetch();
        if (!$col2) {
            $pdo->exec("ALTER TABLE operators ADD COLUMN tipos_grua VARCHAR(30) NULL AFTER estado_capacitacion");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS performance_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            operator_id INT NOT NULL,
            area ENUM('ARMG','QC','PC','WL') NOT NULL,
            fecha DATE NOT NULL,
            evaluador VARCHAR(120) NULL,
            items TEXT NULL,
            score DECIMAL(5,2) NOT NULL DEFAULT 0,
            status VARCHAR(10) NOT NULL DEFAULT 'BAJO',
            comentarios TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (operator_id), INDEX (area), INDEX (fecha),
            CONSTRAINT fk_pf_op FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        if (!in_array('backfill_estado_capacitacion', $applied, true)) {
            // Si la última evaluación registrada de un operador fue REENTRENAMIENTO, marcarlo así (una sola vez).
            $pdo->exec("UPDATE operators o
                SET o.estado_capacitacion = 'REENTRENAMIENTO'
                WHERE (SELECT s.tipo_capacitacion FROM skill_records s
                       WHERE s.operator_id = o.id
                       ORDER BY s.fecha DESC, s.id DESC LIMIT 1) = 'REENTRENAMIENTO'");
            $pdo->prepare("INSERT INTO schema_migrations (name) VALUES ('backfill_estado_capacitacion')")->execute();
        }

        if (!in_array('backfill_tipos_grua_por_cargo', $applied, true)) {
            // Asigna automáticamente el tipo de grúa según el cargo del operador (una sola vez,
            // solo si aún no tiene grúas asignadas manualmente).
            $mapaCargoGrua = [
                'OPERADOR DE GRUA DE PATIO ARMG' => 'ARMG',
                'OPERADOR DE GRUAS STS'          => 'QC',
                'OPERADOR DE PORTAL CRANE'       => 'PC',
                'OPERADOR DE CARGADOR FRONTAL'   => 'WL',
            ];
            $stmt = $pdo->prepare("UPDATE operators SET tipos_grua=? WHERE cargo=? AND (tipos_grua IS NULL OR tipos_grua='')");
            foreach ($mapaCargoGrua as $cargo => $grua) {
                $stmt->execute([$grua, $cargo]);
            }
            $pdo->prepare("INSERT INTO schema_migrations (name) VALUES ('backfill_tipos_grua_por_cargo')")->execute();
        }

        if (!in_array('unificar_tipo_preparacion_training', $applied, true)) {
            // ENTRENAMIENTO y REENTRENAMIENTO pasan a ser un único tipo: TRAINING.
            $pdo->exec("UPDATE hours_records SET tipo_preparacion='TRAINING' WHERE tipo_preparacion IN ('ENTRENAMIENTO','REENTRENAMIENTO')");
            $pdo->prepare("INSERT INTO schema_migrations (name) VALUES ('unificar_tipo_preparacion_training')")->execute();
        }

        $col3 = $pdo->query("SHOW COLUMNS FROM users LIKE 'dni'")->fetch();
        if (!$col3) {
            $pdo->exec("ALTER TABLE users ADD COLUMN dni VARCHAR(15) NULL AFTER nombre");
        }

        if (!in_array('backfill_dni_usuarios', $applied, true)) {
            // Completa el DNI de los usuarios cruzando su nombre con el módulo de Operadores.
            $pdo->exec("UPDATE users u
                JOIN operators o ON UPPER(TRIM(o.nombres)) = UPPER(TRIM(u.nombre))
                SET u.dni = o.dni
                WHERE u.dni IS NULL OR u.dni = ''");
            $pdo->prepare("INSERT INTO schema_migrations (name) VALUES ('backfill_dni_usuarios')")->execute();
        }

        $rolCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'rol'")->fetch();
        if ($rolCol && strpos($rolCol['Type'], "'supervisor'") === false) {
            $pdo->exec("ALTER TABLE users MODIFY COLUMN rol ENUM('admin','instructor','supervisor') NOT NULL DEFAULT 'instructor'");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS operator_grua_estado (
            id INT AUTO_INCREMENT PRIMARY KEY,
            operator_id INT NOT NULL,
            area ENUM('ARMG','QC','PC','WL') NOT NULL,
            estado_capacitacion ENUM('ENTRENAMIENTO','REENTRENAMIENTO') NOT NULL DEFAULT 'ENTRENAMIENTO',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_operator_area (operator_id, area),
            CONSTRAINT fk_oge_op FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        // Adjuntos de incidencias (fotos + declaración) almacenados en Google Drive.
        // Se guardan como JSON con los datos que devuelve el Apps Script (id/url/thumb/name).
        $fotosCol = $pdo->query("SHOW COLUMNS FROM incidents LIKE 'fotos'")->fetch();
        if (!$fotosCol) {
            $pdo->exec("ALTER TABLE incidents
                ADD COLUMN fotos TEXT NULL AFTER acciones,
                ADD COLUMN declaracion TEXT NULL AFTER fotos");
        }

        if (!in_array('seed_operator_grua_estado', $applied, true)) {
            // Inicializa el estado por grúa de cada operador designado con su estado global actual
            // (a partir de aquí cada grúa puede tener un estado de capacitación independiente).
            $pdo->exec("INSERT IGNORE INTO operator_grua_estado (operator_id, area, estado_capacitacion)
                SELECT o.id, x.area, o.estado_capacitacion
                FROM operators o
                JOIN (SELECT 'ARMG' AS area UNION SELECT 'QC' UNION SELECT 'PC') x
                  ON FIND_IN_SET(x.area, o.tipos_grua)");
            $pdo->prepare("INSERT INTO schema_migrations (name) VALUES ('seed_operator_grua_estado')")->execute();
        }

        if (!in_array('add_wheel_loader_area', $applied, true)) {
            // Amplía las áreas existentes sin perder los registros históricos.
            foreach (['hours_records', 'skill_records', 'performance_records', 'speed_records', 'incidents', 'custom_lugares', 'operator_grua_estado'] as $table) {
                $pdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN area ENUM('ARMG','QC','PC','WL') NOT NULL");
            }
            $pdo->prepare("UPDATE operators SET tipos_grua='WL' WHERE cargo='OPERADOR DE CARGADOR FRONTAL' AND (tipos_grua IS NULL OR tipos_grua='')")->execute();
            $pdo->exec("INSERT IGNORE INTO operator_grua_estado (operator_id, area, estado_capacitacion)
                SELECT id, 'WL', estado_capacitacion FROM operators WHERE FIND_IN_SET('WL', tipos_grua)");
            $pdo->prepare("INSERT INTO schema_migrations (name) VALUES ('add_wheel_loader_area')")->execute();
        }
        } catch (PDOException $e) {
            error_log('Migración de esquema omitida: ' . $e->getMessage());
        }
    }
    return $pdo;
}
