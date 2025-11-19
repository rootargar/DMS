# 📘 MANUAL DE INSTALACIÓN - SISTEMA DE GESTIÓN DOCUMENTAL (DMS)

## 📋 Tabla de Contenidos

1. [Requisitos del Sistema](#requisitos-del-sistema)
2. [Instalación de Componentes](#instalación-de-componentes)
3. [Configuración de la Base de Datos](#configuración-de-la-base-de-datos)
4. [Configuración del Servidor Web](#configuración-del-servidor-web)
5. [Configuración de PHP](#configuración-de-php)
6. [Instalación del Sistema](#instalación-del-sistema)
7. [Configuración de Notificaciones](#configuración-de-notificaciones)
8. [Pruebas del Sistema](#pruebas-del-sistema)
9. [Solución de Problemas](#solución-de-problemas)

---

## 📌 Requisitos del Sistema

### Requisitos Mínimos de Hardware

- **Procesador:** Intel Core i3 o equivalente
- **RAM:** 4 GB mínimo (8 GB recomendado)
- **Disco Duro:** 50 GB libres
- **Red:** Conexión a Internet para envío de notificaciones

### Requisitos de Software

#### Sistema Operativo
- Windows Server 2012 R2 o superior
- Windows 10/11 (para ambiente de desarrollo)

#### Base de Datos
- **SQL Server 2012 o superior** (recomendado 2016+)
- SQL Server Express es suficiente para instalaciones pequeñas

#### Servidor Web
- **IIS 7.5 o superior** o **Apache 2.4+**
- Soporte para PHP

#### PHP
- **PHP 7.4 o superior** (recomendado PHP 8.0+)
- Extensiones requeridas:
  - `pdo_sqlsrv` (para SQL Server)
  - `mbstring`
  - `openssl`
  - `fileinfo`
  - `gd` o `imagick` (para manipulación de imágenes)
  - `zip`
  - `xml`

---

## 🔧 Instalación de Componentes

### 1. Instalación de SQL Server

1. Descargar SQL Server desde: https://www.microsoft.com/sql-server/
2. Ejecutar el instalador
3. Seleccionar "Instalación nueva independiente"
4. Elegir tipo de instalación: **Motor de base de datos**
5. Configurar autenticación: **Modo mixto** (Windows + SQL Server)
6. Establecer contraseña para usuario `sa`
7. Completar instalación

### 2. Instalación de SQL Server Management Studio (SSMS)

1. Descargar desde: https://aka.ms/ssmsfullsetup
2. Instalar SSMS
3. Conectarse al servidor SQL Server

### 3. Instalación de PHP

#### Opción A: PHP en Windows (Manual)

1. Descargar PHP desde: https://windows.php.net/download/
2. Extraer en `C:\php`
3. Copiar `php.ini-production` a `php.ini`
4. Editar `php.ini`:

```ini
extension_dir = "C:\php\ext"
extension=pdo_sqlsrv
extension=sqlsrv
extension=mbstring
extension=openssl
extension=fileinfo
extension=gd
extension=zip
extension=xml
max_execution_time = 300
max_input_time = 300
upload_max_filesize = 50M
post_max_size = 50M
memory_limit = 256M
```

5. Agregar `C:\php` al PATH del sistema

#### Opción B: Usar XAMPP

1. Descargar XAMPP desde: https://www.apachefriends.org/
2. Instalar XAMPP
3. Habilitar extensiones en `php.ini`

### 4. Instalación de Drivers de SQL Server para PHP

1. Descargar drivers desde: https://docs.microsoft.com/sql/connect/php/download-drivers-php-sql-server
2. Copiar archivos `.dll` a `C:\php\ext\`
3. Habilitar en `php.ini`:

```ini
extension=php_sqlsrv_82_ts_x64.dll
extension=php_pdo_sqlsrv_82_ts_x64.dll
```

**Nota:** El nombre exacto del archivo depende de tu versión de PHP

### 5. Instalación de Composer

1. Descargar desde: https://getcomposer.org/download/
2. Ejecutar instalador
3. Verificar instalación:

```bash
composer --version
```

### 6. Instalación de IIS (si usas Windows)

1. Panel de Control → Programas → Activar o desactivar características de Windows
2. Marcar: **Internet Information Services**
3. Expandir y marcar:
   - Servicios World Wide Web
   - Características comunes HTTP
   - Desarrollo de aplicaciones → CGI
4. Reiniciar sistema

---

## 🗄️ Configuración de la Base de Datos

### 1. Crear la Base de Datos

1. Abrir SQL Server Management Studio (SSMS)
2. Conectarse al servidor
3. Ejecutar el siguiente script:

```sql
-- Crear la base de datos
CREATE DATABASE dms_database;
GO

-- Usar la base de datos
USE dms_database;
GO
```

### 2. Importar el Esquema

1. Abrir el archivo `database_sqlserver.sql`
2. En SSMS, hacer clic en **Nueva Consulta**
3. Copiar todo el contenido de `database_sqlserver.sql`
4. Ejecutar el script (F5)
5. Verificar que se crearon todas las tablas:

```sql
SELECT TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;
```

Deberías ver las siguientes tablas:
- `odm_access_log`
- `odm_admin`
- `odm_category`
- `odm_data`
- `odm_department`
- `odm_dept_perms`
- `odm_dept_reviewer`
- `odm_filetypes`
- `odm_log`
- `odm_notificaciones`
- `odm_notificaciones_internas`
- `odm_odmsys`
- `odm_rights`
- `odm_roles`
- `odm_settings`
- `odm_smtp_config`
- `odm_udf`
- `odm_user`
- `odm_user_perms`

### 3. Verificar Datos Iniciales

```sql
-- Verificar usuario administrador
SELECT * FROM odm_user;

-- Verificar roles
SELECT * FROM odm_roles;

-- Verificar categorías
SELECT * FROM odm_category;
```

---

## 🌐 Configuración del Servidor Web

### Configuración de IIS

1. Abrir **Administrador de IIS**
2. Crear nuevo sitio web:
   - Clic derecho en **Sitios** → **Agregar sitio web**
   - **Nombre del sitio:** DMS
   - **Ruta física:** C:\inetpub\wwwroot\dms
   - **Puerto:** 80 (o el que prefieras)
3. Configurar permisos:
   - Clic derecho en el sitio → **Editar permisos**
   - Pestaña **Seguridad** → **Editar**
   - Agregar usuario `IIS_IUSRS` con permisos de lectura

### Configuración de Apache

Si usas Apache (XAMPP), editar `httpd.conf`:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/dms"
    ServerName localhost

    <Directory "C:/xampp/htdocs/dms">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## ⚙️ Instalación del Sistema

### 1. Copiar Archivos

1. Copiar todos los archivos del sistema a la carpeta del servidor web:
   - Para IIS: `C:\inetpub\wwwroot\dms\`
   - Para XAMPP: `C:\xampp\htdocs\dms\`

### 2. Configurar Conexión a la Base de Datos

Editar el archivo `conexion.php`:

```php
<?php
// Configuración de la base de datos
define('DB_SERVER', 'localhost');        // O nombre del servidor SQL
define('DB_PORT', '1433');                // Puerto SQL Server
define('DB_NAME', 'dms_database');        // Nombre de la base de datos
define('DB_USER', 'sa');                  // Usuario SQL Server
define('DB_PASS', 'TU_CONTRASEÑA_AQUI');  // Contraseña
define('DB_DRIVER', 'sqlsrv');            // sqlsrv para Windows

define('DB_DEBUG', true);                 // Cambiar a false en producción
?>
```

### 3. Crear Carpeta de Documentos

1. Crear carpeta para almacenar documentos:

```bash
mkdir C:\dms\documentos
```

2. Dar permisos de escritura:
   - Clic derecho en la carpeta → **Propiedades**
   - Pestaña **Seguridad** → **Editar**
   - Agregar `IIS_IUSRS` o `IUSR` con permisos de **Modificar**

### 4. Instalar Dependencias de PHP

Navegar a la carpeta del proyecto y ejecutar:

```bash
cd C:\inetpub\wwwroot\dms
composer install
```

Esto instalará:
- PHPMailer (para envío de correos)
- PhpSpreadsheet (para exportar a Excel)
- TCPDF (para exportar a PDF)

Si no tienes archivo `composer.json`, créalo con este contenido:

```json
{
    "require": {
        "phpmailer/phpmailer": "^6.6",
        "phpoffice/phpspreadsheet": "^1.23",
        "tecnickcom/tcpdf": "^6.5"
    }
}
```

Luego ejecuta `composer install`.

### 5. Probar la Instalación

1. Abrir navegador
2. Ir a: `http://localhost/dms/` (o la URL configurada)
3. Deberías ver la página de login

---

## 📧 Configuración de Notificaciones

### 1. Configurar SMTP en la Base de Datos

Ejecutar en SSMS:

```sql
UPDATE odm_smtp_config
SET
    smtp_host = 'smtp.gmail.com',           -- Servidor SMTP
    smtp_port = 587,                         -- Puerto (587 para TLS, 465 para SSL)
    smtp_security = 'tls',                   -- Seguridad: tls o ssl
    smtp_username = 'tu_email@gmail.com',    -- Tu email
    smtp_password = 'tu_contraseña_app',     -- Contraseña de aplicación
    smtp_from_email = 'noreply@tudms.com',   -- Email remitente
    smtp_from_name = 'Sistema DMS',          -- Nombre remitente
    smtp_activo = 1,                         -- Activar SMTP
    smtp_debug = 0                           -- 0 = sin debug, 1 = con debug
WHERE id = 1;
```

### 2. Configurar Gmail para SMTP (Si usas Gmail)

1. Ir a tu cuenta de Google
2. Seguridad → Verificación en dos pasos (activar)
3. Seguridad → Contraseñas de aplicaciones
4. Generar nueva contraseña de aplicación
5. Copiar la contraseña generada
6. Usarla en `smtp_password`

**Nota:** No uses tu contraseña real de Gmail, usa la contraseña de aplicación generada.

### 3. Probar Envío de Correos

Crear archivo `test_email.php`:

```php
<?php
require_once 'conexion.php';
require_once 'Notificaciones.class.php';

$notif = new Notificaciones($pdo);
$notif->setDebug(true);

$destinatario = 'tu_email@example.com';
$asunto = 'Prueba de Notificaciones DMS';
$html = $notif->generar_plantilla_html(
    'Prueba de Sistema',
    '<p>Si recibes este correo, el sistema de notificaciones está funcionando correctamente.</p>',
    []
);

if ($notif->enviar_correo($destinatario, $asunto, $html)) {
    echo "✓ Correo enviado exitosamente";
} else {
    echo "✗ Error al enviar correo";
}
?>
```

Ejecutar: `http://localhost/dms/test_email.php`

---

## ✅ Pruebas del Sistema

### 1. Login Inicial

- **URL:** `http://localhost/dms/`
- **Usuario:** admin
- **Contraseña:** admin

**¡IMPORTANTE!** Cambiar la contraseña de admin inmediatamente después del primer login.

### 2. Verificar Módulos

Probar los siguientes módulos:

#### a) Gestión de Usuarios
1. Ir a **Administración** → **Usuarios**
2. Crear un nuevo usuario
3. Asignar un rol (Editor, Revisor, Empleado)

#### b) Subir Documento
1. Ir a **Documentos** → **Nuevo**
2. Seleccionar categoría
3. Subir un archivo PDF
4. Verificar que se crea la notificación

#### c) Notificaciones Internas
1. Verificar que aparece el ícono de campana
2. Verificar contador de notificaciones
3. Hacer clic y ver lista

#### d) Reportes
1. Ir a **Reportes**
2. Generar reporte de "Documentos por Categoría"
3. Exportar a Excel
4. Exportar a PDF

### 3. Verificar Permisos por Rol

Crear usuarios de prueba para cada rol y verificar:

| Rol | Puede Ver | Puede Crear | Puede Editar | Puede Eliminar | Puede Aprobar |
|-----|-----------|-------------|--------------|----------------|---------------|
| Administrador | ✓ | ✓ | ✓ | ✓ | ✓ |
| Revisor | ✓ | ✗ | ✗ | ✗ | ✓ |
| Editor | ✓ | ✓ | ✓ | ✗ | ✗ |
| Empleado | ✓ | ✗ | ✗ | ✗ | ✗ |

---

## 🔧 Solución de Problemas

### Problema: No se puede conectar a SQL Server

**Síntomas:** Error "Could not connect to database"

**Soluciones:**
1. Verificar que SQL Server esté corriendo:
   - Servicios → SQL Server (MSSQLSERVER) → Iniciar
2. Verificar firewall:
   - Agregar regla para puerto 1433
3. Habilitar autenticación SQL Server:
   - SSMS → Propiedades del servidor → Seguridad → Modo de autenticación

### Problema: Extensiones PHP no cargadas

**Síntomas:** Error "driver not found"

**Soluciones:**
1. Verificar que los drivers están en `C:\php\ext\`
2. Verificar `php.ini`:
   ```ini
   extension=pdo_sqlsrv
   extension=sqlsrv
   ```
3. Reiniciar servidor web
4. Ejecutar `php -m` para ver extensiones cargadas

### Problema: No se envían correos

**Síntomas:** Notificaciones no llegan

**Soluciones:**
1. Verificar configuración SMTP en base de datos
2. Verificar que `smtp_activo = 1`
3. Si usas Gmail, verificar contraseña de aplicación
4. Activar debug: `smtp_debug = 1`
5. Revisar logs de PHP

### Problema: Errores de permisos al subir archivos

**Síntomas:** "Permission denied" al subir documentos

**Soluciones:**
1. Verificar carpeta de documentos existe: `C:\dms\documentos\`
2. Dar permisos de escritura a `IIS_IUSRS` o `IUSR`
3. Verificar configuración en `odm_settings`:
   ```sql
   SELECT * FROM odm_settings WHERE name = 'dataDir';
   ```

### Problema: Reportes no se generan

**Síntomas:** Error al exportar a Excel/PDF

**Soluciones:**
1. Verificar que Composer instaló las dependencias:
   ```bash
   composer show
   ```
2. Debe mostrar:
   - phpoffice/phpspreadsheet
   - tecnickcom/tcpdf
3. Si faltan, ejecutar:
   ```bash
   composer require phpoffice/phpspreadsheet
   composer require tecnickcom/tcpdf
   ```

---

## 🎯 Pasos Siguientes

1. ✅ Cambiar contraseña de administrador
2. ✅ Configurar SMTP para notificaciones
3. ✅ Crear usuarios del sistema
4. ✅ Configurar categorías personalizadas
5. ✅ Subir documentos de prueba
6. ✅ Realizar backup de la base de datos

---

## 📞 Soporte

Para soporte adicional:
- Revisar archivo `CONFIGURACION_NOTIFICACIONES.md`
- Revisar archivo `RAZONAMIENTO_SELECCION.md`
- Revisar logs de PHP: `C:\php\logs\php_errors.log`
- Revisar logs de IIS: `C:\inetpub\logs\`

---

**Versión del Manual:** 1.0
**Fecha:** Noviembre 2025
**Sistema:** Gestión Documental DMS basado en OpenDocMan
