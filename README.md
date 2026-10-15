<div align="center">

# Biometrico Backend

API backend para consultar y sincronizar información de personas, fotografías, asistencias y eventos de acceso entre los sistemas institucionales y **HikCentral**.

![PHP](https://img.shields.io/badge/PHP-%5E8.1-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-10.x-FF2D20?logo=laravel&logoColor=white)
![API](https://img.shields.io/badge/API-REST-2F80ED)

</div>

---

## Contenido

- [Descripción](#descripción)
- [Funciones principales](#funciones-principales)
- [Tecnologías](#tecnologías)
- [Requisitos](#requisitos)
- [Instalación local](#instalación-local)
- [Configuración](#configuración)
- [Ejecución](#ejecución)
- [API](#api)
- [Sincronización automática](#sincronización-automática)
- [Pruebas y compilación](#pruebas-y-compilación)
- [Estructura y catálogo de archivos](#estructura-y-catálogo-de-archivos)
- [Datos y seguridad](#datos-y-seguridad)

## Descripción

Este repositorio contiene un servicio Laravel que expone endpoints JSON bajo `/api/biometrico`. Integra el sistema con HikCentral para consultar dispositivos, personas, fotografías, niveles de acceso y eventos; además, gestiona datos institucionales, invitados y registros de asistencia.

El proyecto utiliza bases de datos configuradas en el entorno Laravel y no incluye migraciones de esquema en este repositorio. Antes de ejecutarlo, se necesita acceso a las bases de datos y tablas que utiliza la instalación institucional, además de credenciales y conectividad válidas para HikCentral.

## Funciones principales

- Autenticación de usuarios y emisión de tokens JWT.
- Consulta de estudiantes, docentes y personal; recuperación y descarga de fotografías.
- Sincronización y comparación de personas y fotografías con HikCentral.
- Consulta de dispositivos, cámaras, departamentos, niveles de acceso y eventos de puertas.
- Generación de consultas e informes de asistencia y de accesos.
- Sincronización manual y programada de marcaciones de asistencia.
- Registro y administración de invitados, incluyendo carga de fotos y evidencias.
- Consulta de carreras y períodos lectivos activos.

## Tecnologías

| Tecnología | Uso |
| --- | --- |
| PHP 8.1 o superior | Lenguaje de la aplicación |
| Laravel 10 | Framework HTTP, rutas, consola y acceso a datos |
| MySQL (configurable) | Conexión de base de datos predeterminada del ejemplo de entorno |
| `tymon/jwt-auth` | Tokens JWT |
| Laravel Sanctum | Dependencia y ruta de usuario estándar de Laravel |
| Guzzle HTTP | Cliente HTTP |
| Intervention Image | Procesamiento de imágenes |
| Vite, Axios | Herramientas de recursos del cliente incluidas en el esqueleto Laravel |
| PHPUnit | Pruebas automatizadas |

## Requisitos

- PHP `^8.1` con las extensiones requeridas por Laravel y los paquetes instalados.
- Composer.
- Node.js y npm para compilar los recursos gestionados por Vite.
- Acceso a las bases de datos utilizadas por la aplicación.
- Acceso de red y credenciales de HikCentral para los flujos que lo requieren.

## Instalación local

Desde la carpeta del repositorio, en PowerShell:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan jwt:secret
npm install
npm run build
```

Edita `.env` con los valores de tu entorno antes de iniciar la aplicación. En Laragon, configura un host virtual o utiliza el servidor local de Laravel:

```powershell
php artisan serve
```

La raíz web responde desde `/`; los endpoints de la API comienzan en `/api/biometrico`. Para que se ejecute la tarea programada de asistencia, configura también el scheduler de Laravel según [Sincronización automática](#sincronización-automática).

## Configuración

`.env.example` contiene valores de desarrollo para Laravel y ejemplos de configuración de base de datos, correo, caché, sesiones, colas, almacenamiento y Pusher. Debes completar, al menos:

1. `APP_NAME`, `APP_ENV`, `APP_DEBUG` y `APP_URL` con valores apropiados al entorno.
2. `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` para las bases existentes.
3. `JWT_SECRET` y el tiempo de vida del token (`JWT_TTL`).
4. Las variables `HIKCENTRAL_*` que consume la integración, con sus URLs y credenciales provistas por el administrador de HikCentral.

Las variables requeridas por HikCentral se leen desde la aplicación, pero no están todas declaradas en `.env.example`; confirma los nombres y valores con la configuración del despliegue antes de ejecutar esos flujos. No guardes secretos reales en el repositorio ni habilites `APP_DEBUG` en producción.

Después de cambiar parámetros cacheados de Laravel, limpia la configuración:

```powershell
php artisan config:clear
php artisan cache:clear
```

## Ejecución

Comandos habituales:

```powershell
# Servidor HTTP de desarrollo
php artisan serve

# Mostrar las rutas registradas
php artisan route:list

# Ejecutar las pruebas
php artisan test

# Compilar CSS y JavaScript
npm run build

# Desarrollo de recursos con recarga
npm run dev
```

## API

Todas las rutas API se cargan bajo el prefijo `/api`; las rutas de negocio se agrupan además bajo `/biometrico`.

| Área | Rutas / operaciones |
| --- | --- |
| Autenticación | `POST /api/biometrico/login`; `GET /me`, `POST /refresh` y `GET /logout` para sesión autenticada |
| Usuarios | Recurso REST `apiResource` en `/api/biometrico/users` |
| Datos institucionales | Consultas de estudiantes, docentes, personal y carreras; consultas por identificación |
| Fotografías | Consulta individual de fotos y descargas masivas de estudiantes, pre-estudiantes, docentes y personal |
| HikCentral | Consulta y sincronización de personas, estado, fotos y diferencias entre los registros locales y HikCentral |
| Dispositivos y acceso | Consulta de dispositivos, cámaras, departamentos, niveles de acceso, accesos de gimnasio y eventos de puertas |
| Asistencia | Informes de asistencia, verificación de marcaciones locales y sincronización de registros |
| Invitados | Recurso REST `/api/biometrico/hikcentral_invitados`, carga de fotos/evidencias y acciones de habilitación |
| Período lectivo | Consulta de períodos activos |

La mayoría de rutas de integración están definidas en `routes/api.php`; esa ruta agrupa un conjunto amplio de endpoints especializados para estos flujos. Consulta el archivo para conocer los nombres exactos, métodos HTTP y parámetros. Las rutas `me`, `refresh`, `logout` y varias consultas masivas están dentro del middleware `auth:api`; no se debe asumir que las demás rutas exigen autenticación. Revisa y protege la exposición de cada endpoint al configurar el despliegue.

### Autenticación

`POST /api/biometrico/login` recibe `LoginUsu` y `ClaveUsu`. Si las credenciales son válidas, responde con un token bearer y su tiempo de expiración. Para las rutas protegidas, envía el token en la cabecera:

```http
Authorization: Bearer <token>
```

La ruta estándar `GET /api/user` usa el middleware `auth:sanctum`, distinto del middleware JWT `auth:api` empleado por el grupo de sesión del proyecto.

## Sincronización automática

El comando `asistencia:sync-hikcentral` consulta las marcaciones del día en HikCentral para personal activo con fotografía y las registra o actualiza en la base local. El Kernel programa su ejecución cada cinco minutos entre las 07:00 y las 23:00, y agrega la salida a `storage/logs/sincronizacion_hikcentral.log`.

Para activar la programación en el servidor, ejecuta el scheduler de Laravel de forma recurrente, por ejemplo cada minuto:

```powershell
php artisan schedule:run
```

En producción debe configurarse el programador del sistema operativo para invocar ese comando periódicamente. También se puede ejecutar el proceso manualmente:

```powershell
php artisan asistencia:sync-hikcentral
```

## Pruebas y compilación

Las suites PHPUnit se encuentran en `tests/Unit` y `tests/Feature`; para ejecutarlas:

```powershell
php artisan test
```

La compilación de CSS y JavaScript se realiza con:

```powershell
npm run build
```

Las pruebas de ejemplo requieren el entorno PHPUnit configurado y no reemplazan la verificación de conectividad con bases institucionales o HikCentral.

## Estructura y catálogo de archivos

El catálogo siguiente describe los **113 archivos versionados** del proyecto. `vendor/`, `node_modules/`, el directorio `.git/`, los archivos locales ignorados y los datos temporales generados en ejecución no forman parte del inventario versionado.

### Archivos de la raíz

| Archivo | Propósito |
| --- | --- |
| `.editorconfig` | Mantiene reglas comunes de formato entre editores. |
| `.env.example` | Plantilla de variables de entorno para configurar Laravel localmente. |
| `.gitattributes` | Define atributos de Git, como normalización de finales de línea y manejo de archivos. |
| `.gitignore` | Excluye del control de versiones dependencias, secretos, cachés y otros artefactos locales. |
| `README.md` | Documentación del propósito, instalación, uso y estructura del proyecto. |
| `artisan` | Punto de entrada de comandos de Laravel ejecutados desde la terminal. |
| `composer.json` | Declara dependencias PHP, autoloading y scripts de Composer. |
| `composer.lock` | Fija las versiones resueltas de las dependencias PHP para instalaciones reproducibles. |
| `package.json` | Declara las herramientas JavaScript y scripts de Vite (`dev` y `build`). |
| `phpunit.xml` | Configura las suites, directorios y variables de entorno para PHPUnit. |
| `vite.config.js` | Configura el plugin Laravel de Vite y las entradas CSS/JavaScript. |

### Aplicación (`app/`)

#### Consola, excepciones y HTTP

| Archivo | Propósito |
| --- | --- |
| `app/Console/Commands/SyncAsistenciaAutomatica.php` | Implementa el comando que obtiene marcaciones de HikCentral y sincroniza la asistencia diaria local. |
| `app/Console/Kernel.php` | Registra comandos y programa la sincronización de asistencia. |
| `app/Exceptions/Handler.php` | Centraliza el reporte y la respuesta ante excepciones de la aplicación. |
| `app/Http/Controllers/Controller.php` | Controlador base del que extienden los controladores HTTP. |
| `app/Http/Controllers/Asistencia_empleadoController.php` | Consulta marcaciones locales y combina/sincroniza registros recibidos desde HikCentral. |
| `app/Http/Controllers/AuthController.php` | Valida el inicio de sesión, entrega JWT y administra consulta, cierre y renovación de sesión. |
| `app/Http/Controllers/CarreraController.php` | Expone consultas de carreras. |
| `app/Http/Controllers/HikcentralController.php` | Agrupa la integración con HikCentral: personas, fotos, dispositivos, eventos, accesos, departamentos, estadísticas y sincronización. |
| `app/Http/Controllers/InformacionPersonalController.php` | Consulta información y fotografías de estudiantes y pre-estudiantes, incluidas descargas masivas. |
| `app/Http/Controllers/InformacionPersonalDController.php` | Consulta información, personal, docentes y fotografías asociadas; ofrece descargas masivas. |
| `app/Http/Controllers/InvitadoHikcentralController.php` | Administra invitados y el manejo de sus fotos, evidencias y estado. |
| `app/Http/Controllers/PeriodoLectivoController.php` | Consulta períodos lectivos activos. |
| `app/Http/Controllers/UserController.php` | Implementa operaciones REST para usuarios y acciones de habilitación/inicio de sesión definidas por el controlador. |
| `app/Http/Kernel.php` | Define middleware global, grupos HTTP (`web`, `api`) y alias de middleware. |

#### Middleware (`app/Http/Middleware/`)

| Archivo | Propósito |
| --- | --- |
| `app/Http/Middleware/Authenticate.php` | Determina el destino de autenticación para solicitudes no autenticadas. |
| `app/Http/Middleware/EncryptCookies.php` | Configura el cifrado de cookies HTTP. |
| `app/Http/Middleware/PreventRequestsDuringMaintenance.php` | Gestiona el acceso durante el modo de mantenimiento. |
| `app/Http/Middleware/RedirectIfAuthenticated.php` | Controla la redirección de usuarios ya autenticados. |
| `app/Http/Middleware/TrimStrings.php` | Elimina espacios al principio y al final de entradas de texto. |
| `app/Http/Middleware/TrustHosts.php` | Permite configurar los hosts de confianza para solicitudes entrantes. |
| `app/Http/Middleware/TrustProxies.php` | Configura el tratamiento de proxies y cabeceras de origen. |
| `app/Http/Middleware/ValidateSignature.php` | Valida firmas de URL firmadas. |
| `app/Http/Middleware/VerifyCsrfToken.php` | Gestiona la validación CSRF para las rutas web. |

#### Modelos (`app/Models/`)

| Archivo | Propósito |
| --- | --- |
| `app/Models/Asistencia_empleado.php` | Modelo Eloquent de la tabla `asistencia_empleado` y relación con información del personal. |
| `app/Models/Bitacora.php` | Modelo Eloquent de la bitácora de acciones. |
| `app/Models/Carrera.php` | Modelo de carreras y sus relaciones con sedes, facultades y modalidades. |
| `app/Models/CarreraModalidad.php` | Modelo de la relación entre carreras y modalidades. |
| `app/Models/Facultad.php` | Modelo de facultades, sus sedes y carreras. |
| `app/Models/InvitadoHikcentral.php` | Modelo de invitados registrados para HikCentral. |
| `app/Models/PeriodoLectivo.php` | Modelo de períodos lectivos. |
| `app/Models/RegistroTitulos.php` | Modelo de registros de títulos y su relación con información personal. |
| `app/Models/Sede.php` | Modelo de sedes y sus relaciones con facultades y carreras. |
| `app/Models/User.php` | Modelo de usuario autenticable, incluyendo la integración requerida por JWT. |
| `app/Models/informacionpersonal.php` | Modelo de información personal utilizado por consultas institucionales de estudiantes. |
| `app/Models/informacionpersonal_D.php` | Modelo de información personal de docentes/personal y su relación con asistencias. |

#### Proveedores (`app/Providers/`)

| Archivo | Propósito |
| --- | --- |
| `app/Providers/AppServiceProvider.php` | Punto de registro general de servicios de la aplicación. |
| `app/Providers/AuthServiceProvider.php` | Configuración de servicios y políticas de autorización. |
| `app/Providers/BroadcastServiceProvider.php` | Registro de rutas/canales para broadcasting. |
| `app/Providers/EventServiceProvider.php` | Registro de listeners y eventos de Laravel. |
| `app/Providers/RouteServiceProvider.php` | Carga las rutas web/API, define el prefijo `/api` y limita solicitudes API. |

### Inicio y configuración

| Archivo | Propósito |
| --- | --- |
| `bootstrap/app.php` | Crea la aplicación Laravel y enlaza los kernels HTTP/consola y el manejador de excepciones. |
| `bootstrap/cache/.gitignore` | Conserva el directorio de caché de bootstrap sin versionar sus artefactos generados. |
| `config/app.php` | Configuración general de la aplicación Laravel. |
| `config/auth.php` | Guards, proveedores y opciones de autenticación. |
| `config/broadcasting.php` | Drivers y credenciales para broadcasting. |
| `config/cache.php` | Drivers, prefijos y opciones de caché. |
| `config/cors.php` | Reglas de Cross-Origin Resource Sharing. |
| `config/database.php` | Conexiones y opciones de bases de datos. |
| `config/filesystems.php` | Discos locales, públicos y remotos de almacenamiento. |
| `config/hashing.php` | Configuración de algoritmos de hashing de Laravel. |
| `config/jwt.php` | Claves, expiración y opciones del paquete JWT. |
| `config/logging.php` | Canales y destinos de logs. |
| `config/mail.php` | Transporte y parámetros de correo electrónico. |
| `config/queue.php` | Drivers y parámetros de colas de trabajo. |
| `config/sanctum.php` | Configuración de Laravel Sanctum. |
| `config/services.php` | Credenciales de servicios externos compatibles con Laravel, como Mailgun, Postmark y SES. |
| `config/session.php` | Driver, duración y opciones de sesión. |
| `config/view.php` | Rutas y configuración de vistas Blade compiladas. |

### Base de datos (`database/`)

| Archivo | Propósito |
| --- | --- |
| `database/.gitignore` | Evita versionar archivos locales de base de datos generados en ese directorio. |
| `database/factories/UserFactory.php` | Genera usuarios de prueba/fábrica para tests y seeders. |
| `database/seeders/DatabaseSeeder.php` | Punto de entrada para poblar datos; el seeder incluido no crea registros actualmente. |

> **Nota:** No hay archivos de migración versionados. El esquema debe existir y ser proporcionado por el entorno de base de datos institucional.

### Archivos públicos (`public/`)

| Archivo o patrón versionado | Propósito |
| --- | --- |
| `public/.htaccess` | Reglas de reescritura para dirigir solicitudes al punto de entrada de Laravel en Apache. |
| `public/index.php` | Punto de entrada HTTP: carga Composer, inicia Laravel y despacha la solicitud. |
| `public/favicon.ico` | Icono del sitio. |
| `public/robots.txt` | Instrucciones de rastreo para robots web. |
| `public/Documentos/Biometrico/Invitados/Evidencia/<identificador>/*.pdf` (3 archivos) | Evidencias PDF asociadas a registros de invitados; los archivos concretos versionados contienen identificadores personales en sus nombres. |
| `public/Documentos/Biometrico/Invitados/Fotos/<identificador>/*.jpeg` (3 archivos) | Fotografías asociadas a registros de invitados; los archivos concretos versionados contienen identificadores personales en sus nombres. |

### Recursos de interfaz (`resources/`)

| Archivo | Propósito |
| --- | --- |
| `resources/css/app.css` | Hoja de estilos fuente para los recursos frontend gestionados por Vite. |
| `resources/js/app.js` | Punto de entrada JavaScript de la aplicación frontend. |
| `resources/js/bootstrap.js` | Inicialización del entorno JavaScript, incluyendo Axios. |
| `resources/views/welcome.blade.php` | Vista de bienvenida servida en la ruta web `/`. |
| `resources/views/errors/layout.blade.php` | Diseño compartido para páginas de error Blade. |
| `resources/views/errors/minimal.blade.php` | Vista compacta de error usada por las páginas de error. |
| `resources/views/errors/401.blade.php` | Vista para respuesta HTTP 401 (no autenticado). |
| `resources/views/errors/402.blade.php` | Vista para respuesta HTTP 402. |
| `resources/views/errors/403.blade.php` | Vista para respuesta HTTP 403 (prohibido). |
| `resources/views/errors/404.blade.php` | Vista para respuesta HTTP 404 (no encontrado). |
| `resources/views/errors/419.blade.php` | Vista para respuesta HTTP 419 (sesión/CSRF expirada). |
| `resources/views/errors/429.blade.php` | Vista para respuesta HTTP 429 (límite de solicitudes excedido). |
| `resources/views/errors/500.blade.php` | Vista para respuesta HTTP 500 (error interno). |
| `resources/views/errors/503.blade.php` | Vista para respuesta HTTP 503 (servicio no disponible). |

### Rutas (`routes/`)

| Archivo | Propósito |
| --- | --- |
| `routes/api.php` | Declara la API REST, endpoints de negocio y grupos de middleware. |
| `routes/web.php` | Declara rutas web; actualmente sirve la vista de bienvenida en `/`. |
| `routes/console.php` | Define comandos de consola basados en closures; contiene el comando de ejemplo `inspire`. |
| `routes/channels.php` | Define canales de autorización para broadcasting. |

### Almacenamiento (`storage/`)

| Archivo | Propósito |
| --- | --- |
| `storage/app/.gitignore` | Mantiene la carpeta de almacenamiento de la aplicación sin incluir datos generados. |
| `storage/app/public/.gitignore` | Mantiene el directorio reservado para archivos públicos generados. |
| `storage/framework/.gitignore` | Excluye artefactos temporales del framework. |
| `storage/framework/cache/.gitignore` | Excluye archivos generados de caché del framework. |
| `storage/framework/cache/data/.gitignore` | Conserva el subdirectorio de datos de caché sin versionar el contenido. |
| `storage/framework/sessions/.gitignore` | Excluye sesiones serializadas generadas durante la ejecución. |
| `storage/framework/testing/.gitignore` | Excluye archivos temporales creados por pruebas. |
| `storage/framework/views/.gitignore` | Excluye vistas Blade compiladas. |
| `storage/logs/.gitignore` | Excluye logs generados durante la ejecución. |

### Pruebas (`tests/`)

| Archivo | Propósito |
| --- | --- |
| `tests/CreatesApplication.php` | Inicializa la aplicación Laravel para el entorno de pruebas. |
| `tests/TestCase.php` | Clase base para las pruebas PHPUnit de Laravel. |
| `tests/Feature/ExampleTest.php` | Prueba de ejemplo de comportamiento a nivel de aplicación/HTTP. |
| `tests/Unit/ExampleTest.php` | Prueba unitaria de ejemplo. |

## Datos y seguridad

- El repositorio contiene archivos de evidencia y fotografías de invitados. Son datos potencialmente sensibles y sus nombres incluyen identificadores; evita compartirlos o utilizarlos fuera de un entorno autorizado.
- No subas archivos `.env`, claves JWT, contraseñas ni secretos de HikCentral. Mantén `APP_DEBUG=false` en producción.
- Limita el acceso de red y la exposición de endpoints que consultan datos personales, fotografías, asistencias o control de acceso.
- Realiza respaldos y define reglas de retención para datos de identidad, asistencia y evidencias conforme a las políticas institucionales.