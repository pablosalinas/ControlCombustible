# ⛽ Control de Combustible y Gastos de Vehículos

Aplicación multiplataforma (**Android en Flutter** + **Interfaz Web/PWA**) conectada a la base de datos y sistema de usuarios/roles de `moratalla-murcia.com` (`Moratalla-Murcia-2026`).

## ✨ Características Principales

- **Autenticación y Roles Integrados (`moratalla-murcia.com`)**:
  - Valida contra la tabla `users` en modo de **solo lectura**.
  - Los usuarios con rol `admin` disponen de acceso individual a todos los vehículos registrados y a la vista consolidada global; los demás usuarios gestionan sus propios vehículos.
- **Protección Total de Datos (No-Destrucción)**:
  - Esquema SQL estrictamente aditivo (`CREATE TABLE IF NOT EXISTS veh_vehiculos`, `veh_repostajes`).
  - Borrado lógico (`activo = 0`) en todas las operaciones de baja.
- **Cálculos Encadenados de Consumo (Fórmulas Exactas de la Hoja de Cálculo)**:
  - Referencia automática entre el repostaje anterior ($i-1$) y el actual ($i$): `DIF. Km`, `KM.x.LTS`, `LTS.x.100`, `KM.x.EUR`, `€ x 1KM.`, `LTSxKM.`, `T.LITROS`, `TOTAL €`, `Consumo Máximo / Mínimo / Medio` y `Precio Máximo / Mínimo`.
- **Escaneo OCR Inteligente**:
  - Lectura automática de **tickets/facturas de gasolinera** (Fecha, Litros, Precio/L e Importe).
  - Lectura óptica del **cuentakilómetros (odómetro)** mediante fotografía.
- **Generación de Informes PDF desde el Móvil**:
  - Exportación en PDF apaisado con indicadores globales y tabla detallada por vehículo o consolidada.

## 📁 Estructura del Proyecto

- `api.php`: Endpoint REST JSON para la App Android y Web.
- `index.php`: Interfaz Web / PWA móvil con gráficos, CRUD, OCR y exportación PDF.
- `setup_db.php`: Instalador aditivo e idempotente (crea las tablas `veh_*` e importa `datos/historico_repostajes.csv` sin alterar ni borrar datos).
- `config/database.php`: Conexión automática a la BD de `Moratalla-Murcia-2026` (compatible con PHP 5.6, 7.x y 8.x).
- `src/FuelCalculator.php`: Motor matemático de cálculo de series por vehículo y flota consolidada.
- `database/001_create_vehiculos_tables.sql`: Definición SQL aditiva de las tablas `veh_vehiculos` y `veh_repostajes`.
- `datos/historico_repostajes.csv`: 233 registros históricos (2015–2025).
- `flutter_app/`: Código fuente completo de la aplicación Android en Flutter + workflow de GitHub Actions para compilar el `.apk`.
