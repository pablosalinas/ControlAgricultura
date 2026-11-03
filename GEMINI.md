# Reglas Estrictas de Protección de Datos e Integridad de Base de Datos

## 1. RESTRICCIÓN INQUEBRANTABLE DE BASE DE DATOS
- **PROHIBICIÓN TOTAL DE BORRADOS O MODIFICACIONES SIN AUTORIZACIÓN EXPRESA**:
  El asistente tiene **estrictamente prohibido** ejecutar cualquier instrucción destructiva (`DELETE`, `DROP`, `TRUNCATE`, `ALTER TABLE ... DROP`) o sentencias `UPDATE` masivas sobre la base de datos (tanto en local como en producción) sin la **autorización previa, expresa y por escrito del usuario** en el turno actual.
- **MIGRACIONES AUTOMÁTICAS ESTRICTAMENTE SEGURAS**:
  Cualquier migrador automático o script de inicio solo tiene permitido crear tablas nuevas que no existan (`CREATE TABLE IF NOT EXISTS`) o añadir columnas (`ADD COLUMN IF NOT EXISTS`). Queda absolutamente prohibido sobrescribir, limpiar, resetear o recrear tablas existentes que contengan datos del usuario.
- **OBLIGATORIEDAD DE BORRADO LÓGICO (SOFT DELETE)**:
  Toda funcionalidad que permita al usuario "eliminar" registros en la aplicación (parajes, fincas, regantes, solicitudes, labores) debe implementarse mediante borrado lógico (`activo = 0` / descatalogado) y no mediante borrado físico `DELETE`, para garantizar que los datos nunca se pierdan y puedan recuperarse si fue una acción no intencionada.
- **VERIFICACIÓN OBLIGATORIA**:
  Antes de proponer o aplicar cualquier cambio que afecte a registros almacenados, el asistente debe consultar y confirmar con el usuario.
