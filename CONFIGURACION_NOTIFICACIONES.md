# 📧 CONFIGURACIÓN DE NOTIFICACIONES - SISTEMA DMS

## 📋 Tabla de Contenidos

1. [Introducción](#introducción)
2. [Tipos de Notificaciones](#tipos-de-notificaciones)
3. [Configuración SMTP](#configuración-smtp)
4. [Notificaciones por Correo](#notificaciones-por-correo)
5. [Notificaciones Internas](#notificaciones-internas)
6. [Personalización de Plantillas](#personalización-de-plantillas)
7. [Configuración Avanzada](#configuración-avanzada)
8. [Solución de Problemas](#solución-de-problemas)

---

## 🎯 Introducción

El sistema DMS cuenta con **dos tipos** de notificaciones:

1. **Notificaciones por Correo Electrónico** (SMTP)
2. **Notificaciones Internas** (campana en pantalla)

Ambas se activan automáticamente en eventos importantes del sistema.

---

## 📨 Tipos de Notificaciones

El sistema envía notificaciones automáticas para los siguientes eventos:

| Evento | Correo | Interna | Destinatarios |
|--------|--------|---------|---------------|
| Documento nuevo subido | ✓ | ✓ | Revisores del departamento |
| Nueva versión de documento | ✓ | ✓ | Propietario + Revisores |
| Documento requiere revisión | ✓ | ✓ | Revisores asignados |
| Documento aprobado | ✓ | ✓ | Propietario |
| Documento rechazado | ✓ | ✓ | Propietario |
| Documento próximo a vencer | ✓ | ✓ | Propietario + Revisores |
| Documento eliminado | ✓ | ✓ | Revisores del departamento |
| Documento actualizado | ✓ | ✓ | Propietario + Revisores |
| Nuevo comentario agregado | ✓ | ✓ | Propietario |

---

## ⚙️ Configuración SMTP

### 1. Configuración en Base de Datos

Las credenciales SMTP se almacenan en la tabla `odm_smtp_config`:

```sql
SELECT * FROM odm_smtp_config;
```

Para actualizar la configuración:

```sql
UPDATE odm_smtp_config
SET
    smtp_host = 'tu_servidor_smtp',
    smtp_port = 587,
    smtp_security = 'tls',
    smtp_username = 'tu_usuario',
    smtp_password = 'tu_contraseña',
    smtp_from_email = 'noreply@tudominio.com',
    smtp_from_name = 'Sistema DMS',
    smtp_activo = 1,
    smtp_debug = 0
WHERE id = 1;
```

### 2. Configuraciones por Proveedor

#### Gmail

```sql
UPDATE odm_smtp_config SET
    smtp_host = 'smtp.gmail.com',
    smtp_port = 587,
    smtp_security = 'tls',
    smtp_username = 'tu_email@gmail.com',
    smtp_password = 'contraseña_aplicacion_16_digitos',
    smtp_from_email = 'tu_email@gmail.com',
    smtp_from_name = 'Sistema DMS'
WHERE id = 1;
```

**Pasos para Gmail:**
1. Activar verificación en dos pasos
2. Ir a: https://myaccount.google.com/apppasswords
3. Crear contraseña de aplicación para "Correo"
4. Usar esa contraseña de 16 dígitos

#### Outlook/Office 365

```sql
UPDATE odm_smtp_config SET
    smtp_host = 'smtp.office365.com',
    smtp_port = 587,
    smtp_security = 'tls',
    smtp_username = 'tu_email@outlook.com',
    smtp_password = 'tu_contraseña',
    smtp_from_email = 'tu_email@outlook.com',
    smtp_from_name = 'Sistema DMS'
WHERE id = 1;
```

#### SMTP Personalizado

```sql
UPDATE odm_smtp_config SET
    smtp_host = 'mail.tudominio.com',
    smtp_port = 465,                    -- 465 para SSL, 587 para TLS
    smtp_security = 'ssl',               -- ssl o tls
    smtp_username = 'noreply@tudominio.com',
    smtp_password = 'contraseña_segura',
    smtp_from_email = 'noreply@tudominio.com',
    smtp_from_name = 'Sistema DMS'
WHERE id = 1;
```

### 3. Activar/Desactivar SMTP

```sql
-- Activar notificaciones por correo
UPDATE odm_smtp_config SET smtp_activo = 1 WHERE id = 1;

-- Desactivar notificaciones por correo
UPDATE odm_smtp_config SET smtp_activo = 0 WHERE id = 1;
```

### 4. Modo Debug

```sql
-- Activar debug (muestra errores detallados)
UPDATE odm_smtp_config SET smtp_debug = 1 WHERE id = 1;

-- Desactivar debug (producción)
UPDATE odm_smtp_config SET smtp_debug = 0 WHERE id = 1;
```

---

## 📧 Notificaciones por Correo

### Estructura de la Clase `Notificaciones`

**Ubicación:** `Notificaciones.class.php`

```php
<?php
require_once 'Notificaciones.class.php';

$notif = new Notificaciones($pdo);

// Enviar notificación de documento nuevo
$notif->notificar_documento_nuevo(
    $id_documento,
    $nombre_documento,
    $categoria,
    $usuario,
    ['email1@example.com', 'email2@example.com']
);
?>
```

### Métodos Disponibles

#### 1. Notificar Documento Nuevo

```php
$notif->notificar_documento_nuevo(
    int $id_documento,
    string $nombre_documento,
    string $categoria,
    string $usuario,
    array $destinatarios
);
```

**Ejemplo:**
```php
$notif->notificar_documento_nuevo(
    123,
    "Manual de Procedimientos",
    "Procedimientos",
    "Juan Pérez",
    ['admin@empresa.com', 'jefe@empresa.com']
);
```

#### 2. Notificar Nueva Versión

```php
$notif->notificar_nueva_version(
    int $id_documento,
    string $nombre_documento,
    string $version,
    string $categoria,
    string $usuario,
    string $comentarios,
    array $destinatarios
);
```

**Ejemplo:**
```php
$notif->notificar_nueva_version(
    123,
    "Manual de Procedimientos",
    "2.1",
    "Procedimientos",
    "Juan Pérez",
    "Se actualizó la sección 3.2",
    ['revisor@empresa.com']
);
```

#### 3. Notificar Requiere Revisión

```php
$notif->notificar_requiere_revision(
    int $id_documento,
    string $nombre_documento,
    string $categoria,
    string $usuario,
    array $destinatarios
);
```

#### 4. Notificar Documento Aprobado

```php
$notif->notificar_documento_aprobado(
    int $id_documento,
    string $nombre_documento,
    string $categoria,
    string $aprobador,
    string $comentarios,
    array $destinatarios
);
```

#### 5. Notificar Documento Rechazado

```php
$notif->notificar_documento_rechazado(
    int $id_documento,
    string $nombre_documento,
    string $categoria,
    string $rechazador,
    string $motivo,
    array $destinatarios
);
```

#### 6. Notificar Documento por Vencer

```php
$notif->notificar_documento_por_vencer(
    int $id_documento,
    string $nombre_documento,
    string $categoria,
    int $dias_restantes,
    array $destinatarios
);
```

#### 7. Notificar Documento Modificado

```php
$notif->notificar_documento_modificado(
    int $id_documento,
    string $nombre_documento,
    string $categoria,
    string $accion,        // 'eliminado', 'actualizado'
    string $usuario,
    array $destinatarios
);
```

#### 8. Notificar Nuevo Comentario

```php
$notif->notificar_nuevo_comentario(
    int $id_documento,
    string $nombre_documento,
    string $categoria,
    string $usuario,
    string $comentario,
    array $destinatarios
);
```

### Historial de Notificaciones

Todas las notificaciones enviadas se registran en `odm_notificaciones`:

```sql
-- Ver notificaciones enviadas
SELECT
    n.*,
    d.realname AS documento,
    u.username AS enviado_por_usuario
FROM odm_notificaciones n
LEFT JOIN odm_data d ON n.id_documento = d.id
LEFT JOIN odm_user u ON n.enviado_por = u.id
ORDER BY n.fecha_envio DESC;
```

```sql
-- Ver notificaciones con error
SELECT *
FROM odm_notificaciones
WHERE status = 'error'
ORDER BY fecha_envio DESC;
```

---

## 🔔 Notificaciones Internas

### Estructura de la Clase `NotificacionesInternas`

**Ubicación:** `NotificacionesInternas.class.php`

```php
<?php
require_once 'NotificacionesInternas.class.php';

$notif_int = new NotificacionesInternas($pdo, $_SESSION['uid']);

// Crear notificación interna
$notif_int->crear_notificacion(
    'Nuevo Documento',
    'Se ha subido un nuevo documento: Manual.pdf',
    123,                    // ID del documento
    'documento_nuevo',      // Tipo
    'details.php?id=123',   // URL
    'fa-file-pdf',          // Icono FontAwesome
    'normal'                // Prioridad: baja, normal, alta, urgente
);
?>
```

### Métodos Disponibles

#### 1. Crear Notificación

```php
$notif_int->crear_notificacion(
    string $titulo,
    string $mensaje,
    int $id_documento = null,
    string $tipo = 'general',
    string $url = null,
    string $icono = 'fa-file',
    string $prioridad = 'normal'  // baja, normal, alta, urgente
);
```

#### 2. Obtener Notificaciones

```php
// Todas las notificaciones
$notificaciones = $notif_int->obtener_notificaciones(false, 50);

// Solo no leídas
$no_leidas = $notif_int->obtener_notificaciones(true, 20);
```

#### 3. Contar No Leídas

```php
$count = $notif_int->contar_no_leidas();
echo "Tienes $count notificaciones sin leer";
```

#### 4. Marcar como Leída

```php
$notif_int->marcar_como_leida($notificacion_id);
```

#### 5. Marcar Todas como Leídas

```php
$notif_int->marcar_todas_leidas();
```

### Integración en el Header

Agregar en el archivo de header (ej: `header.php`):

```php
<?php
require_once 'NotificacionesInternas.class.php';

if (isset($_SESSION['uid'])) {
    $notif_int = new NotificacionesInternas($pdo, $_SESSION['uid']);
    $count = $notif_int->contar_no_leidas();
}
?>

<!-- HTML del header -->
<div class="notification-widget">
    <button class="notification-bell" id="notificationBell" onclick="toggleNotifications()">
        <i class="fa fa-bell"></i>
        <?php if ($count > 0): ?>
            <span class="notification-badge"><?= $count ?></span>
        <?php endif; ?>
    </button>
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h3>Notificaciones</h3>
            <div class="notification-actions">
                <button onclick="marcarTodasLeidas()"><i class="fa fa-check-double"></i></button>
                <button onclick="limpiarLeidas()"><i class="fa fa-trash"></i></button>
            </div>
        </div>
        <div class="notification-list" id="notificationList">
            <!-- Se carga dinámicamente con AJAX -->
        </div>
    </div>
</div>

<!-- CSS y JS -->
<link rel="stylesheet" href="notificaciones.css">
<script src="notificaciones.js"></script>
```

### AJAX Handler

**Archivo:** `ajax_notificaciones.php`

Endpoints disponibles:

```javascript
// Obtener notificaciones
fetch('ajax_notificaciones.php?action=get')
    .then(response => response.json())
    .then(data => console.log(data));

// Contar no leídas
fetch('ajax_notificaciones.php?action=count')

// Marcar como leída
fetch('ajax_notificaciones.php?action=mark_read&id=123')

// Marcar todas como leídas
fetch('ajax_notificaciones.php?action=mark_all_read')

// Limpiar leídas
fetch('ajax_notificaciones.php?action=clear_read')
```

---

## 🎨 Personalización de Plantillas

### Plantilla HTML de Correos

**Ubicación:** Método `generar_plantilla_html()` en `Notificaciones.class.php`

Para personalizar el diseño de los correos, editar el método:

```php
public function generar_plantilla_html($titulo, $contenido, $datos = [])
{
    // Personalizar colores
    $color_primario = '#667eea';
    $color_secundario = '#764ba2';

    // Personalizar logo
    $logo_url = 'https://tudominio.com/logo.png';

    // Personalizar pie de página
    $footer_text = 'Tu Empresa © ' . date('Y');

    // ... resto del código
}
```

### Iconos de Notificaciones Internas

Personalizar iconos FontAwesome:

```php
// Iconos por tipo de notificación
$iconos = [
    'documento_nuevo' => 'fa-file-plus',
    'nueva_version' => 'fa-code-branch',
    'requiere_revision' => 'fa-eye',
    'aprobado' => 'fa-check-circle',
    'rechazado' => 'fa-times-circle',
    'por_vencer' => 'fa-clock',
    'comentario' => 'fa-comment'
];
```

---

## 🔧 Configuración Avanzada

### 1. Configurar Días de Aviso de Vencimiento

```sql
UPDATE odm_settings
SET value = '30'
WHERE name = 'dias_aviso_vencimiento';
```

### 2. Activar/Desactivar Notificaciones Globalmente

```sql
UPDATE odm_settings
SET value = 'True'
WHERE name = 'notificaciones_activas';
```

### 3. Crear Tarea Programada para Documentos por Vencer

**Archivo:** `cron_vencimientos.php`

```php
<?php
require_once 'conexion.php';
require_once 'Notificaciones.class.php';

// Obtener documentos próximos a vencer
$sql = "SELECT * FROM vw_documentos_por_vencer";
$stmt = $pdo->query($sql);
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$notif = new Notificaciones($pdo);

foreach ($documentos as $doc) {
    $notif->notificar_documento_por_vencer(
        $doc['id'],
        $doc['nombre_documento'],
        $doc['categoria'],
        $doc['dias_restantes'],
        [$doc['email_responsable']]
    );
}

echo "Se enviaron " . count($documentos) . " notificaciones de vencimiento\n";
?>
```

Programar en Windows:

```batch
schtasks /create /sc daily /tn "DMS_Vencimientos" /tr "C:\php\php.exe C:\inetpub\wwwroot\dms\cron_vencimientos.php" /st 08:00
```

---

## 🐛 Solución de Problemas

### Problema: No se envían correos

**Diagnóstico:**
```php
<?php
require_once 'conexion.php';
require_once 'Notificaciones.class.php';

$notif = new Notificaciones($pdo);
$notif->setDebug(true);  // Activar debug

$test = $notif->enviar_correo(
    'test@example.com',
    'Prueba',
    '<p>Prueba de correo</p>'
);

if ($test) {
    echo "✓ Correo enviado";
} else {
    echo "✗ Error al enviar";
}
?>
```

**Soluciones:**
1. Verificar `smtp_activo = 1`
2. Verificar credenciales SMTP
3. Verificar firewall (puerto 587 o 465)
4. Activar debug para ver error exacto

### Problema: Notificaciones internas no aparecen

**Diagnóstico:**
```sql
-- Verificar si hay notificaciones
SELECT COUNT(*) FROM odm_notificaciones_internas
WHERE usuario_id = 1 AND leida = 0;
```

**Soluciones:**
1. Verificar que `ajax_notificaciones.php` es accesible
2. Revisar consola del navegador (F12)
3. Verificar que CSS y JS están cargados

### Problema: Errores en el registro de notificaciones

**Diagnóstico:**
```sql
-- Ver notificaciones con error
SELECT * FROM odm_notificaciones
WHERE status = 'error'
ORDER BY fecha_envio DESC;
```

---

## 📝 Ejemplos de Uso

### Ejemplo 1: Enviar notificación al subir documento

```php
<?php
// En el archivo de subir documento
require_once 'Notificaciones.class.php';
require_once 'NotificacionesInternas.class.php';

// Después de subir el documento...
$notif = new Notificaciones($pdo);

// Obtener emails de revisores del departamento
$sql = "SELECT u.Email
        FROM odm_user u
        INNER JOIN odm_dept_reviewer dr ON u.id = dr.user_id
        WHERE dr.dept_id = :dept_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':dept_id' => $department_id]);
$emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Enviar notificación por correo
$notif->notificar_documento_nuevo(
    $documento_id,
    $nombre_archivo,
    $categoria_nombre,
    $usuario_nombre,
    $emails
);

// Crear notificación interna para cada revisor
$sql = "SELECT user_id FROM odm_dept_reviewer WHERE dept_id = :dept_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':dept_id' => $department_id]);
$revisores = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($revisores as $revisor_id) {
    $notif_int = new NotificacionesInternas($pdo, $revisor_id);
    $notif_int->crear_notificacion(
        'Nuevo Documento',
        "Se ha subido: $nombre_archivo",
        $documento_id,
        'documento_nuevo',
        "details.php?id=$documento_id",
        'fa-file-plus',
        'normal'
    );
}
?>
```

### Ejemplo 2: Notificar aprobación

```php
<?php
// Al aprobar un documento
$notif->notificar_documento_aprobado(
    $documento_id,
    $nombre_documento,
    $categoria,
    $aprobador_nombre,
    $comentarios,
    [$propietario_email]
);

// Notificación interna
$notif_int = new NotificacionesInternas($pdo, $propietario_id);
$notif_int->crear_notificacion(
    'Documento Aprobado',
    "Tu documento '$nombre_documento' ha sido aprobado",
    $documento_id,
    'aprobado',
    "details.php?id=$documento_id",
    'fa-check-circle',
    'alta'
);
?>
```

---

**Versión:** 1.0
**Fecha:** Noviembre 2025
**Sistema:** DMS - Sistema de Gestión Documental
