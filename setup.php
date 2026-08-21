<?php
// Instalador: crea la base de datos, tablas y usuarios iniciales.
// Ejecutar una vez: http://localhost:8080/sistemacompetencias/setup.php  (o php setup.php)
require __DIR__ . '/config.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `" . DB_NAME . "`");

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    dni VARCHAR(15) NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin','instructor','supervisor','visita') NOT NULL DEFAULT 'instructor',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS operators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(15) NULL,
    dni VARCHAR(15) NOT NULL UNIQUE,
    nombres VARCHAR(160) NOT NULL,
    cargo VARCHAR(100) NOT NULL,
    lugar VARCHAR(80) NOT NULL DEFAULT 'CHANCAY',
    fecha_ingreso DATE NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    estado_capacitacion ENUM('ENTRENAMIENTO','REENTRENAMIENTO') NOT NULL DEFAULT 'ENTRENAMIENTO',
    tipos_grua VARCHAR(30) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS hours_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    area ENUM('ARMG','QC','PC','WL') NOT NULL,
    fecha DATE NOT NULL,
    tipo_preparacion VARCHAR(40) NOT NULL,
    tipo_actividad VARCHAR(180) NULL,
    lugar VARCHAR(60) NULL,
    instructor VARCHAR(120) NULL,
    observacion TEXT NULL,
    detalle TEXT NULL,               -- JSON {campo: minutos}
    total_min INT NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (operator_id), INDEX (area), INDEX (fecha),
    CONSTRAINT fk_hr_op FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS skill_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    area ENUM('ARMG','QC','PC','WL') NOT NULL,
    fecha DATE NOT NULL,
    tipo_capacitacion VARCHAR(40) NOT NULL,
    contexto VARCHAR(120) NULL,      -- tipo de buque (PC) / lugar (QC-ARMG)
    evaluador VARCHAR(120) NULL,
    items TEXT NULL,                 -- JSON [{g,i,v}] v: 1-5 (escala) o 0/1 (check)
    score DECIMAL(5,2) NOT NULL DEFAULT 0,
    status VARCHAR(10) NOT NULL DEFAULT 'BAJO',
    apto TINYINT(1) NULL,
    comentarios TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (operator_id), INDEX (area), INDEX (fecha),
    CONSTRAINT fk_sk_op FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS performance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    area ENUM('ARMG','QC','PC','WL') NOT NULL,
    fecha DATE NOT NULL,
    evaluador VARCHAR(120) NULL,
    items TEXT NULL,                 -- JSON [{i,v}] v: 1-5
    score DECIMAL(5,2) NOT NULL DEFAULT 0,
    status VARCHAR(10) NOT NULL DEFAULT 'BAJO',
    comentarios TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (operator_id), INDEX (area), INDEX (fecha),
    CONSTRAINT fk_pf_op FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS speed_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    area ENUM('ARMG','QC','PC','WL') NOT NULL,
    fecha DATE NOT NULL,
    tipo_capacitacion VARCHAR(40) NOT NULL,
    lugar VARCHAR(60) NULL,
    contexto VARCHAR(120) NULL,      -- tipo de maniobra / inspección
    evaluador VARCHAR(120) NULL,
    fases TEXT NULL,                 -- JSON [{f, s(segundos)}]
    total_seg INT NOT NULL DEFAULT 0,
    estimado_seg INT NOT NULL DEFAULT 0,
    eficiencia DECIMAL(6,2) NOT NULL DEFAULT 0,   -- % estimado/real
    status VARCHAR(10) NOT NULL DEFAULT 'BAJO',
    movimientos INT NULL,
    cumple TINYINT(1) NULL,
    observaciones TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (operator_id), INDEX (area), INDEX (fecha),
    CONSTRAINT fk_sp_op FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    area ENUM('ARMG','QC','PC','WL') NOT NULL,
    fecha DATE NOT NULL,
    equipo VARCHAR(100) NULL,
    tipo VARCHAR(80) NOT NULL,
    severidad ENUM('LEVE','MODERADA','GRAVE') NOT NULL DEFAULT 'LEVE',
    descripcion TEXT NOT NULL,
    acciones TEXT NULL,
    estado ENUM('ABIERTA','EN PROCESO','CERRADA','CERRADO') NOT NULL DEFAULT 'EN PROCESO',
    reportado_por VARCHAR(120) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (operator_id), INDEX (area), INDEX (fecha), INDEX (estado),
    CONSTRAINT fk_in_op FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
) ENGINE=InnoDB");

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

$pdo->exec("CREATE TABLE IF NOT EXISTS operator_grua_estado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    area ENUM('ARMG','QC','PC','WL') NOT NULL,
    estado_capacitacion ENUM('ENTRENAMIENTO','REENTRENAMIENTO') NOT NULL DEFAULT 'ENTRENAMIENTO',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_operator_area (operator_id, area),
    CONSTRAINT fk_oge_op FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
) ENGINE=InnoDB");

// Usuarios iniciales
$seed = $pdo->prepare("INSERT IGNORE INTO users (nombre, email, password_hash, rol) VALUES (?,?,?,?)");
$seed->execute(['Paul Montero', 'sistemas@cosco.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
$seed->execute(['Gianpier Zavaleta', 'evaluador@cosco.com', password_hash('eval123', PASSWORD_DEFAULT), 'instructor']);

echo "OK - Base de datos '" . DB_NAME . "' instalada.\n";
echo "Admin: sistemas@cosco.com / admin123\n";
echo "Instructor: evaluador@cosco.com / eval123\n";
