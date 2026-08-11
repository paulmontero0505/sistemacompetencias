# Registro de Competencias y Eficiencia Operativa

Sistema web para **COSCO SHIPPING Ports Chancay** que digitaliza los libros Excel de
competencias de operadores de **ARMG** (grúa de patio), **QC/STS** (grúa pórtico de muelle)
y **PC** (portal crane), y añade gestión de **incidencias**.

## Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 7.4 (sin frameworks), front controller `index.php` |
| Base de datos | MySQL / MariaDB (`competencias_digital`) |
| Frontend | Bootstrap 5, Bootstrap Icons, Chart.js, JavaScript vanilla |
| Importador | Node.js (`importer/import.js`, paquetes `xlsx` + `mysql2`) |

## Instalación

1. Copiar la carpeta a `C:\xampp\htdocs\sistemacompetencias`.
2. Ejecutar el instalador (crea BD, tablas y usuarios):
   ```
   C:\xampp\php\php.exe setup.php
   ```
3. Abrir `http://localhost:8080/sistemacompetencias/`.

**Accesos iniciales**

| Rol | Correo | Clave |
|---|---|---|
| Administrador | sistemas@cosco.com | admin123 |
| Evaluador | evaluador@cosco.com | eval123 |

## Importar los libros Excel históricos

```bash
cd importer
npm install
node import.js "C:\ruta\...ARMG.xlsx" "C:\ruta\...PC.xlsx" "C:\ruta\...QC.xlsx" --wipe
```

- El área se detecta por el nombre del archivo (contiene `ARMG`, `PC` o `QC`).
- `--wipe` vacía los registros previos (no toca usuarios ni datos manuales de incidencias).
- Importa: `DATA_OP` → operadores, hojas de horas, habilidades y velocidad/duración.

## Módulos

- **Dashboard** — KPIs y gráficas (horas por mes, status de habilidad, ranking, incidencias) con filtro por área.
- **Operadores** — maestro de personal (código, DNI, cargo, ingreso) con acceso al perfil individual (evolución de habilidad y eficiencia).
- **Horas** — sesiones de entrenamiento con desglose de horas según el área.
- **Habilidades** — ARMG: escala 1–5; PC/QC: checklist ponderado por grupos (péndulo 50%, altura, controles, TOS). Calcula % y status automáticamente.
- **Velocidad** — tiempos por fase de maniobra vs. tiempo estimado → % de eficiencia y status.
- **Incidencias** — registro por operador con severidad, estado y acciones correctivas.
- **Formato** — formato de evaluación imprimible (A4) filtrable por trabajador/área/mes, con firmas de evaluador y operador (`Ctrl+P` o botón imprimir).
- **Usuarios** — solo administrador.

## Configuración

- Umbrales de status en `config.php` (`UMBRAL_OPTIMO` = 80, `UMBRAL_REGULAR` = 50).
- Los campos por área (horas, grupos de habilidades y pesos, fases de velocidad) se definen
  en el arreglo `$AREAS` de `config.php` — se pueden ajustar sin tocar las vistas.
- Logo en `assets/img/logo.svg` (reemplazable por el archivo oficial, ej. `logo.png` — actualizar
  las referencias en `index.php` y `views/`).
