# 🌾 AgroDietario - Sistema de Gestión Agrícola y Cuaderno de Campo Multi-Propietario

Plataforma web integral para la gestión técnica, agronómica y registral de explotaciones agrícolas, diseñada bajo el estándar de un **Cuaderno de Explotación / Cuaderno de Campo Digital**.

---

## 🚀 Características Principales

- **Arquitectura Multi-Propietario (*Multi-tenant*)**: Permite a distintos propietarios y socios agrícolas administrar de forma totalmente aislada sus fincas, plantaciones, labores y costes.
- **Acceso y Seguridad**:
  - Portal de bienvenida / escaparate configurable.
  - Registro con **Captcha** (seguridad contra bots).
  - Aprobación previa de cuentas por el **Superadministrador**.
- **Gestión de Fincas y Parajes**:
  - Datos catastrales (referencia catastral, polígono, parcela, superficie en hectáreas).
  - Gestión de regadío tradicional y moderno: comunidad de regantes, parada, tablón, número de socio, horas en propiedad y horas por tanda.
- **Catálogo Botánico de Cultivos**:
  - Nombre común, nombre científico (con acceso a búsqueda botánica), variedades y ciclos vegetativos aproximados.
- **Ciclos de Plantación y Diario de Evolución**:
  - Trazabilidad de siembras, trasplantes y fechas de cosecha por parcela.
  - Cuaderno fotográfico de evolución fenológica e incidencias climáticas.
- **Dietario de Labores de Campo**:
  - **Tratamientos**: Convencionales y ecológicos certificados, materias activas, dosis y plazos de seguridad.
  - **Laboreo y Suelo**: Arado, subsolado, grada, desbroce y control mecánico de malas hierbas.
  - **Poda y Tala**: Poda de formación, mantenimiento o tala, con gestión y reciclaje de restos (triturado *in situ*, biomasa, quema autorizada).
  - **Riegos**: Registro de tiempos, volumen en m³, turnos/tandas y fertirrigación.
  - **Recolecciones**: Kilos recogidos, clasificación por calidades (1ª, 2ª, destrío), albaranes y precios de liquidación.

---

## 🛠️ Requisitos e Instalación

### Entorno de desarrollo local (XAMPP):
1. Servidor web Apache con módulo `rewrite` activado.
2. PHP 8.1 o superior (con extensiones `pdo_mysql`, `gd`, `session`, `json`).
3. Servidor de bases de datos MySQL / MariaDB.

---

## 📁 Estructura del Proyecto

```text
├── config/             # Configuración de base de datos y entorno
├── database/           # Scripts SQL y migraciones
├── public/             # Punto de entrada web (index.php, CSS, JS, assets)
├── src/                # Lógica del backend (Modelos, Controladores, Helpers)
├── uploads/            # Fotos del diario de campo y documentos
├── views/              # Vistas HTML / plantillas de interfaz
├── .gitignore          # Exclusión de archivos sensibles y temporales
└── README.md           # Documentación del proyecto
```

---

## 📄 Licencia
Proyecto privado para gestión agrícola.
