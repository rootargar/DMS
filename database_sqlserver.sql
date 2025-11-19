-- =============================================
-- SISTEMA DE GESTIÓN DOCUMENTAL - SQL SERVER
-- Adaptado de OpenDocMan para SQL Server
-- =============================================

-- Eliminar tablas existentes si existen (para instalación limpia)
IF OBJECT_ID('dbo.odm_notificaciones', 'U') IS NOT NULL DROP TABLE dbo.odm_notificaciones;
IF OBJECT_ID('dbo.odm_notificaciones_internas', 'U') IS NOT NULL DROP TABLE dbo.odm_notificaciones_internas;
IF OBJECT_ID('dbo.odm_access_log', 'U') IS NOT NULL DROP TABLE dbo.odm_access_log;
IF OBJECT_ID('dbo.odm_user_perms', 'U') IS NOT NULL DROP TABLE dbo.odm_user_perms;
IF OBJECT_ID('dbo.odm_dept_perms', 'U') IS NOT NULL DROP TABLE dbo.odm_dept_perms;
IF OBJECT_ID('dbo.odm_dept_reviewer', 'U') IS NOT NULL DROP TABLE dbo.odm_dept_reviewer;
IF OBJECT_ID('dbo.odm_log', 'U') IS NOT NULL DROP TABLE dbo.odm_log;
IF OBJECT_ID('dbo.odm_data', 'U') IS NOT NULL DROP TABLE dbo.odm_data;
IF OBJECT_ID('dbo.odm_user', 'U') IS NOT NULL DROP TABLE dbo.odm_user;
IF OBJECT_ID('dbo.odm_admin', 'U') IS NOT NULL DROP TABLE dbo.odm_admin;
IF OBJECT_ID('dbo.odm_roles', 'U') IS NOT NULL DROP TABLE dbo.odm_roles;
IF OBJECT_ID('dbo.odm_category', 'U') IS NOT NULL DROP TABLE dbo.odm_category;
IF OBJECT_ID('dbo.odm_department', 'U') IS NOT NULL DROP TABLE dbo.odm_department;
IF OBJECT_ID('dbo.odm_rights', 'U') IS NOT NULL DROP TABLE dbo.odm_rights;
IF OBJECT_ID('dbo.odm_udf', 'U') IS NOT NULL DROP TABLE dbo.odm_udf;
IF OBJECT_ID('dbo.odm_odmsys', 'U') IS NOT NULL DROP TABLE dbo.odm_odmsys;
IF OBJECT_ID('dbo.odm_settings', 'U') IS NOT NULL DROP TABLE dbo.odm_settings;
IF OBJECT_ID('dbo.odm_filetypes', 'U') IS NOT NULL DROP TABLE dbo.odm_filetypes;
IF OBJECT_ID('dbo.odm_smtp_config', 'U') IS NOT NULL DROP TABLE dbo.odm_smtp_config;
GO

-- =============================================
-- TABLA: odm_roles (NUEVO)
-- Sistema de roles: Administrador, Revisor, Editor, Empleado
-- =============================================
CREATE TABLE dbo.odm_roles (
    id INT IDENTITY(1,1) PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    permisos_nivel INT NOT NULL DEFAULT 1,
    puede_agregar BIT DEFAULT 0,
    puede_editar BIT DEFAULT 0,
    puede_eliminar BIT DEFAULT 0,
    puede_aprobar BIT DEFAULT 0,
    puede_revisar BIT DEFAULT 0,
    puede_solo_ver BIT DEFAULT 1,
    fecha_creacion DATETIME DEFAULT GETDATE()
);
GO

-- Datos iniciales de roles
INSERT INTO dbo.odm_roles (nombre, descripcion, permisos_nivel, puede_agregar, puede_editar, puede_eliminar, puede_aprobar, puede_revisar, puede_solo_ver) VALUES
('Administrador', 'Control total del sistema', 4, 1, 1, 1, 1, 1, 1),
('Revisor', 'Puede revisar y aprobar documentos', 3, 0, 0, 0, 1, 1, 1),
('Editor', 'Puede crear y editar documentos', 2, 1, 1, 0, 0, 0, 1),
('Empleado', 'Solo puede visualizar documentos', 1, 0, 0, 0, 0, 0, 1);
GO

-- =============================================
-- TABLA: odm_department
-- =============================================
CREATE TABLE dbo.odm_department (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);
GO

INSERT INTO dbo.odm_department (name) VALUES ('Sistemas de Información');
GO

-- =============================================
-- TABLA: odm_user (MODIFICADA)
-- Agregado campo rol_id
-- =============================================
CREATE TABLE dbo.odm_user (
    id INT IDENTITY(1,1) PRIMARY KEY,
    username VARCHAR(25) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol_id INT NULL,
    department INT NULL,
    phone VARCHAR(20) NULL,
    Email VARCHAR(50) NULL,
    last_name VARCHAR(255) NULL,
    first_name VARCHAR(255) NULL,
    pw_reset_code CHAR(32) NULL,
    can_add BIT DEFAULT 1,
    can_checkin BIT DEFAULT 1,
    activo BIT DEFAULT 1,
    fecha_creacion DATETIME DEFAULT GETDATE(),
    ultimo_acceso DATETIME NULL,
    FOREIGN KEY (rol_id) REFERENCES dbo.odm_roles(id),
    FOREIGN KEY (department) REFERENCES dbo.odm_department(id)
);
GO

-- Usuario administrador por defecto (password: admin - cambiar después de instalación)
-- Hash MD5 de 'admin' = 21232f297a57a5a743894a0e4a801fc3
INSERT INTO dbo.odm_user (username, password, rol_id, department, phone, Email, last_name, first_name, can_add, can_checkin)
VALUES ('admin', '21232f297a57a5a743894a0e4a801fc3', 1, 1, '5555551212', 'admin@localhost.com', 'Administrador', 'Sistema', 1, 1);
GO

-- =============================================
-- TABLA: odm_admin
-- =============================================
CREATE TABLE dbo.odm_admin (
    id INT NULL,
    admin TINYINT NULL
);
GO

INSERT INTO dbo.odm_admin VALUES (1, 1);
GO

-- =============================================
-- TABLA: odm_category (MODIFICADA)
-- Categorías personalizadas: Políticas, Procesos, Procedimientos, Instructivos, Formularios
-- =============================================
CREATE TABLE dbo.odm_category (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    descripcion VARCHAR(500) NULL,
    codigo VARCHAR(20) NULL,
    activo BIT DEFAULT 1,
    fecha_creacion DATETIME DEFAULT GETDATE()
);
GO

-- Datos iniciales de categorías personalizadas
INSERT INTO dbo.odm_category (name, descripcion, codigo, activo) VALUES
('Políticas', 'Documentos de políticas organizacionales', 'POL', 1),
('Procesos', 'Documentos de procesos de negocio', 'PROC', 1),
('Procedimientos', 'Procedimientos operativos estándar', 'PROCED', 1),
('Instructivos', 'Instructivos y guías de trabajo', 'INST', 1),
('Formularios', 'Formularios y plantillas', 'FORM', 1);
GO

-- =============================================
-- TABLA: odm_data (MODIFICADA)
-- Agregados campos para mejor control
-- =============================================
CREATE TABLE dbo.odm_data (
    id INT IDENTITY(1,1) PRIMARY KEY,
    category INT NOT NULL,
    owner INT NULL,
    realname VARCHAR(255) NOT NULL,
    created DATETIME NOT NULL DEFAULT GETDATE(),
    description VARCHAR(500) NULL,
    comment VARCHAR(500) NULL,
    status SMALLINT NULL,
    department SMALLINT NULL,
    default_rights TINYINT NULL,
    publishable TINYINT NULL,
    reviewer INT NULL,
    reviewer_comments VARCHAR(500) NULL,
    -- Campos adicionales
    version_actual VARCHAR(20) DEFAULT '1.0',
    fecha_vencimiento DATE NULL,
    dias_vigencia INT DEFAULT 365,
    aprobado_por INT NULL,
    fecha_aprobacion DATETIME NULL,
    rechazado_por INT NULL,
    fecha_rechazo DATETIME NULL,
    motivo_rechazo VARCHAR(500) NULL,
    FOREIGN KEY (category) REFERENCES dbo.odm_category(id),
    FOREIGN KEY (owner) REFERENCES dbo.odm_user(id),
    FOREIGN KEY (reviewer) REFERENCES dbo.odm_user(id),
    FOREIGN KEY (aprobado_por) REFERENCES dbo.odm_user(id),
    FOREIGN KEY (rechazado_por) REFERENCES dbo.odm_user(id)
);
GO

CREATE INDEX idx_data_owner ON dbo.odm_data(id, owner);
CREATE INDEX idx_data_publishable ON dbo.odm_data(publishable);
CREATE INDEX idx_data_category ON dbo.odm_data(category);
CREATE INDEX idx_data_status ON dbo.odm_data(status);
CREATE INDEX idx_data_vencimiento ON dbo.odm_data(fecha_vencimiento);
GO

-- =============================================
-- TABLA: odm_log (MODIFICADA)
-- Historial de versiones de documentos
-- =============================================
CREATE TABLE dbo.odm_log (
    id INT NOT NULL,
    modified_on DATETIME NOT NULL DEFAULT GETDATE(),
    modified_by VARCHAR(25) NULL,
    note TEXT NULL,
    revision VARCHAR(255) NULL,
    file_size BIGINT NULL,
    checksum VARCHAR(64) NULL,
    FOREIGN KEY (id) REFERENCES dbo.odm_data(id)
);
GO

CREATE INDEX idx_log_id ON dbo.odm_log(id);
CREATE INDEX idx_log_modified_on ON dbo.odm_log(modified_on);
GO

-- =============================================
-- TABLA: odm_access_log (MODIFICADA - AUDITORÍA EXTENDIDA)
-- Acciones: A=Agregado, B=?, C=Check-out, V=Ver, D=Eliminar, M=Modificar, X=?, I=?, O=?, Y=?, R=Rechazar
-- NUEVAS: U=Actualizar, P=Aprobar, W=Descargar, N=Notificación enviada
-- =============================================
CREATE TABLE dbo.odm_access_log (
    log_id INT IDENTITY(1,1) PRIMARY KEY,
    file_id INT NOT NULL,
    user_id INT NOT NULL,
    timestamp DATETIME NOT NULL DEFAULT GETDATE(),
    action CHAR(1) NOT NULL CHECK (action IN ('A','B','C','V','D','M','X','I','O','Y','R','U','P','W','N')),
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    detalles VARCHAR(1000) NULL,
    FOREIGN KEY (file_id) REFERENCES dbo.odm_data(id),
    FOREIGN KEY (user_id) REFERENCES dbo.odm_user(id)
);
GO

CREATE INDEX idx_access_log_file ON dbo.odm_access_log(file_id);
CREATE INDEX idx_access_log_user ON dbo.odm_access_log(user_id);
CREATE INDEX idx_access_log_timestamp ON dbo.odm_access_log(timestamp);
CREATE INDEX idx_access_log_action ON dbo.odm_access_log(action);
GO

-- =============================================
-- TABLA: odm_rights
-- =============================================
CREATE TABLE dbo.odm_rights (
    RightId TINYINT PRIMARY KEY,
    Description VARCHAR(255) NULL
);
GO

INSERT INTO dbo.odm_rights VALUES
(-1, 'forbidden'),
(0, 'none'),
(1, 'view'),
(2, 'read'),
(3, 'write'),
(4, 'admin');
GO

-- =============================================
-- TABLA: odm_user_perms
-- =============================================
CREATE TABLE dbo.odm_user_perms (
    fid INT NULL,
    uid INT NOT NULL,
    rights TINYINT NOT NULL DEFAULT 0,
    FOREIGN KEY (fid) REFERENCES dbo.odm_data(id),
    FOREIGN KEY (uid) REFERENCES dbo.odm_user(id),
    FOREIGN KEY (rights) REFERENCES dbo.odm_rights(RightId)
);
GO

CREATE INDEX idx_user_perms_fid ON dbo.odm_user_perms(fid);
CREATE INDEX idx_user_perms_uid ON dbo.odm_user_perms(uid);
CREATE INDEX idx_user_perms_rights ON dbo.odm_user_perms(rights);
GO

-- =============================================
-- TABLA: odm_dept_perms
-- =============================================
CREATE TABLE dbo.odm_dept_perms (
    fid INT NULL,
    dept_id INT NULL,
    rights TINYINT NOT NULL DEFAULT 0,
    FOREIGN KEY (fid) REFERENCES dbo.odm_data(id),
    FOREIGN KEY (dept_id) REFERENCES dbo.odm_department(id),
    FOREIGN KEY (rights) REFERENCES dbo.odm_rights(RightId)
);
GO

CREATE INDEX idx_dept_perms_fid ON dbo.odm_dept_perms(fid);
CREATE INDEX idx_dept_perms_dept ON dbo.odm_dept_perms(dept_id);
CREATE INDEX idx_dept_perms_rights ON dbo.odm_dept_perms(rights);
GO

-- =============================================
-- TABLA: odm_dept_reviewer
-- =============================================
CREATE TABLE dbo.odm_dept_reviewer (
    dept_id INT NULL,
    user_id INT NULL,
    FOREIGN KEY (dept_id) REFERENCES dbo.odm_department(id),
    FOREIGN KEY (user_id) REFERENCES dbo.odm_user(id)
);
GO

INSERT INTO dbo.odm_dept_reviewer VALUES (1, 1);
GO

-- =============================================
-- TABLA: odm_udf
-- User Defined Fields
-- =============================================
CREATE TABLE dbo.odm_udf (
    id INT IDENTITY(1,1) PRIMARY KEY,
    table_name VARCHAR(50) NULL,
    display_name VARCHAR(16) NULL,
    field_type INT NULL
);
GO

-- =============================================
-- TABLA: odm_odmsys
-- =============================================
CREATE TABLE dbo.odm_odmsys (
    id INT IDENTITY(1,1) PRIMARY KEY,
    sys_name VARCHAR(16) NULL,
    sys_value VARCHAR(255) NULL
);
GO

INSERT INTO dbo.odm_odmsys (sys_name, sys_value) VALUES ('version', '2.0.0');
GO

-- =============================================
-- TABLA: odm_settings
-- =============================================
CREATE TABLE dbo.odm_settings (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    value VARCHAR(255) NOT NULL,
    description VARCHAR(255) NOT NULL,
    validation VARCHAR(255) NOT NULL
);
GO

INSERT INTO dbo.odm_settings (name, value, description, validation) VALUES
('debug', 'False', '(True/False) - Default=False - Debug de la instalación', 'bool'),
('demo', 'False', '(True/False) Modo demostración', 'bool'),
('authen', 'mysql', 'Tipo de autenticación (mysql, ldap)', ''),
('title', 'Sistema de Gestión Documental', 'Título del navegador', 'maxsize=255'),
('site_mail', 'admin@localhost.com', 'Email del administrador del sistema', 'email|maxsize=255|req'),
('root_id', '1', 'ID del usuario root', 'num|req'),
('dataDir', 'C:/dms/documentos/', 'Ubicación del repositorio de archivos', 'maxsize=255'),
('max_filesize', '10000000', 'Tamaño máximo de archivo (bytes)', 'num|maxsize=255'),
('revision_expiration', '365', 'Días hasta que un archivo necesita revisión', 'num|maxsize=255'),
('file_expired_action', '1', 'Acción cuando un archivo expira (1-4)', 'num'),
('authorization', 'True', 'Requiere autorización de documentos', 'bool'),
('allow_signup', 'False', 'Permitir registro de usuarios', 'bool'),
('allow_password_reset', 'True', 'Permitir reset de contraseña', 'bool'),
('language', 'spanish', 'Idioma por defecto', 'alpha|req'),
('max_query', '500', 'Máximo de filas en listados', 'num'),
('notificaciones_activas', 'True', 'Activar sistema de notificaciones por correo', 'bool'),
('dias_aviso_vencimiento', '15', 'Días antes de vencimiento para enviar notificación', 'num');
GO

-- =============================================
-- TABLA: odm_filetypes
-- =============================================
CREATE TABLE dbo.odm_filetypes (
    id INT IDENTITY(1,1) PRIMARY KEY,
    type VARCHAR(255) NOT NULL,
    active BIT NOT NULL DEFAULT 1
);
GO

INSERT INTO dbo.odm_filetypes (type, active) VALUES
('image/gif', 1),
('text/html', 1),
('text/plain', 1),
('application/pdf', 1),
('image/pdf', 1),
('application/x-pdf', 1),
('application/msword', 1),
('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 1),
('application/vnd.ms-excel', 1),
('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 1),
('application/vnd.ms-powerpoint', 1),
('application/vnd.openxmlformats-officedocument.presentationml.presentation', 1),
('image/jpeg', 1),
('image/pjpeg', 1),
('image/png', 1),
('application/zip', 1),
('application/x-zip-compressed', 1),
('text/csv', 1);
GO

-- =============================================
-- TABLA: odm_smtp_config (NUEVA)
-- Configuración SMTP para envío de correos
-- =============================================
CREATE TABLE dbo.odm_smtp_config (
    id INT IDENTITY(1,1) PRIMARY KEY,
    smtp_host VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_security VARCHAR(10) NOT NULL DEFAULT 'tls',
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password VARCHAR(255) NOT NULL,
    smtp_from_email VARCHAR(255) NOT NULL,
    smtp_from_name VARCHAR(255) NOT NULL DEFAULT 'Sistema DMS',
    smtp_activo BIT DEFAULT 1,
    smtp_debug BIT DEFAULT 0,
    fecha_creacion DATETIME DEFAULT GETDATE(),
    fecha_modificacion DATETIME DEFAULT GETDATE()
);
GO

-- Configuración SMTP por defecto (DEBE SER CONFIGURADA)
INSERT INTO dbo.odm_smtp_config (smtp_host, smtp_port, smtp_security, smtp_username, smtp_password, smtp_from_email, smtp_from_name)
VALUES ('smtp.gmail.com', 587, 'tls', 'tu_email@gmail.com', 'tu_password', 'noreply@dms.com', 'Sistema DMS');
GO

-- =============================================
-- TABLA: odm_notificaciones (NUEVA)
-- Registro de notificaciones por correo enviadas
-- =============================================
CREATE TABLE dbo.odm_notificaciones (
    id INT IDENTITY(1,1) PRIMARY KEY,
    id_documento INT NULL,
    tipo_notificacion VARCHAR(50) NOT NULL,
    enviado_a VARCHAR(255) NOT NULL,
    enviado_por INT NULL,
    fecha_envio DATETIME DEFAULT GETDATE(),
    status VARCHAR(20) DEFAULT 'pendiente' CHECK (status IN ('pendiente', 'enviado', 'error')),
    mensaje TEXT NULL,
    error_detalle TEXT NULL,
    asunto VARCHAR(500) NULL,
    cuerpo_html TEXT NULL,
    FOREIGN KEY (id_documento) REFERENCES dbo.odm_data(id),
    FOREIGN KEY (enviado_por) REFERENCES dbo.odm_user(id)
);
GO

CREATE INDEX idx_notificaciones_documento ON dbo.odm_notificaciones(id_documento);
CREATE INDEX idx_notificaciones_status ON dbo.odm_notificaciones(status);
CREATE INDEX idx_notificaciones_fecha ON dbo.odm_notificaciones(fecha_envio);
CREATE INDEX idx_notificaciones_tipo ON dbo.odm_notificaciones(tipo_notificacion);
GO

-- =============================================
-- TABLA: odm_notificaciones_internas (NUEVA)
-- Notificaciones en pantalla para usuarios
-- =============================================
CREATE TABLE dbo.odm_notificaciones_internas (
    id INT IDENTITY(1,1) PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT NULL,
    id_documento INT NULL,
    tipo VARCHAR(50) NOT NULL,
    leida BIT DEFAULT 0,
    fecha_creacion DATETIME DEFAULT GETDATE(),
    fecha_lectura DATETIME NULL,
    url VARCHAR(500) NULL,
    icono VARCHAR(50) DEFAULT 'fa-file',
    prioridad VARCHAR(20) DEFAULT 'normal' CHECK (prioridad IN ('baja', 'normal', 'alta', 'urgente')),
    FOREIGN KEY (usuario_id) REFERENCES dbo.odm_user(id),
    FOREIGN KEY (id_documento) REFERENCES dbo.odm_data(id)
);
GO

CREATE INDEX idx_notif_internas_usuario ON dbo.odm_notificaciones_internas(usuario_id);
CREATE INDEX idx_notif_internas_leida ON dbo.odm_notificaciones_internas(leida);
CREATE INDEX idx_notif_internas_fecha ON dbo.odm_notificaciones_internas(fecha_creacion);
GO

-- =============================================
-- VISTAS ÚTILES
-- =============================================

-- Vista para auditoría completa
IF OBJECT_ID('dbo.vw_auditoria_completa', 'V') IS NOT NULL DROP VIEW dbo.vw_auditoria_completa;
GO

CREATE VIEW dbo.vw_auditoria_completa AS
SELECT
    al.log_id,
    al.file_id,
    d.realname AS nombre_documento,
    c.name AS categoria,
    al.user_id,
    u.username,
    u.first_name + ' ' + u.last_name AS nombre_completo,
    r.nombre AS rol_usuario,
    al.timestamp AS fecha_hora,
    CASE al.action
        WHEN 'A' THEN 'Agregado'
        WHEN 'C' THEN 'Check-out'
        WHEN 'V' THEN 'Visualizado'
        WHEN 'D' THEN 'Eliminado'
        WHEN 'M' THEN 'Modificado'
        WHEN 'R' THEN 'Rechazado'
        WHEN 'U' THEN 'Actualizado'
        WHEN 'P' THEN 'Aprobado'
        WHEN 'W' THEN 'Descargado'
        WHEN 'N' THEN 'Notificación enviada'
        ELSE 'Otra acción'
    END AS accion,
    al.ip_address,
    al.detalles
FROM dbo.odm_access_log al
INNER JOIN dbo.odm_data d ON al.file_id = d.id
INNER JOIN dbo.odm_category c ON d.category = c.id
INNER JOIN dbo.odm_user u ON al.user_id = u.id
LEFT JOIN dbo.odm_roles r ON u.rol_id = r.id;
GO

-- Vista para documentos próximos a vencer
IF OBJECT_ID('dbo.vw_documentos_por_vencer', 'V') IS NOT NULL DROP VIEW dbo.vw_documentos_por_vencer;
GO

CREATE VIEW dbo.vw_documentos_por_vencer AS
SELECT
    d.id,
    d.realname AS nombre_documento,
    c.name AS categoria,
    dept.name AS departamento,
    d.fecha_vencimiento,
    DATEDIFF(day, GETDATE(), d.fecha_vencimiento) AS dias_restantes,
    u.Email AS email_responsable,
    u.first_name + ' ' + u.last_name AS responsable
FROM dbo.odm_data d
INNER JOIN dbo.odm_category c ON d.category = c.id
LEFT JOIN dbo.odm_department dept ON d.department = dept.id
LEFT JOIN dbo.odm_user u ON d.owner = u.id
WHERE d.fecha_vencimiento IS NOT NULL
  AND d.fecha_vencimiento >= GETDATE()
  AND DATEDIFF(day, GETDATE(), d.fecha_vencimiento) <= (SELECT CAST(value AS INT) FROM dbo.odm_settings WHERE name = 'dias_aviso_vencimiento');
GO

-- =============================================
-- PROCEDIMIENTOS ALMACENADOS
-- =============================================

-- SP para registrar log de acceso
IF OBJECT_ID('dbo.sp_registrar_acceso', 'P') IS NOT NULL DROP PROCEDURE dbo.sp_registrar_acceso;
GO

CREATE PROCEDURE dbo.sp_registrar_acceso
    @file_id INT,
    @user_id INT,
    @action CHAR(1),
    @ip_address VARCHAR(45) = NULL,
    @user_agent VARCHAR(500) = NULL,
    @detalles VARCHAR(1000) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.odm_access_log (file_id, user_id, timestamp, action, ip_address, user_agent, detalles)
    VALUES (@file_id, @user_id, GETDATE(), @action, @ip_address, @user_agent, @detalles);
END;
GO

-- SP para crear notificación interna
IF OBJECT_ID('dbo.sp_crear_notificacion_interna', 'P') IS NOT NULL DROP PROCEDURE dbo.sp_crear_notificacion_interna;
GO

CREATE PROCEDURE dbo.sp_crear_notificacion_interna
    @usuario_id INT,
    @titulo VARCHAR(255),
    @mensaje TEXT,
    @id_documento INT = NULL,
    @tipo VARCHAR(50),
    @url VARCHAR(500) = NULL,
    @icono VARCHAR(50) = 'fa-file',
    @prioridad VARCHAR(20) = 'normal'
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.odm_notificaciones_internas (usuario_id, titulo, mensaje, id_documento, tipo, url, icono, prioridad)
    VALUES (@usuario_id, @titulo, @mensaje, @id_documento, @tipo, @url, @icono, @prioridad);
END;
GO

-- SP para marcar notificación como leída
IF OBJECT_ID('dbo.sp_marcar_notificacion_leida', 'P') IS NOT NULL DROP PROCEDURE dbo.sp_marcar_notificacion_leida;
GO

CREATE PROCEDURE dbo.sp_marcar_notificacion_leida
    @notificacion_id INT,
    @usuario_id INT
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.odm_notificaciones_internas
    SET leida = 1,
        fecha_lectura = GETDATE()
    WHERE id = @notificacion_id
      AND usuario_id = @usuario_id;
END;
GO

-- SP para obtener conteo de notificaciones no leídas
IF OBJECT_ID('dbo.sp_contar_notificaciones_no_leidas', 'P') IS NOT NULL DROP PROCEDURE dbo.sp_contar_notificaciones_no_leidas;
GO

CREATE PROCEDURE dbo.sp_contar_notificaciones_no_leidas
    @usuario_id INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS total_no_leidas
    FROM dbo.odm_notificaciones_internas
    WHERE usuario_id = @usuario_id
      AND leida = 0;
END;
GO

PRINT '==============================================';
PRINT 'Base de datos creada exitosamente!';
PRINT '==============================================';
PRINT '';
PRINT 'Tablas creadas:';
PRINT '- odm_roles (NUEVO)';
PRINT '- odm_user (modificado con roles)';
PRINT '- odm_category (categorías personalizadas)';
PRINT '- odm_data (campos adicionales)';
PRINT '- odm_access_log (auditoría extendida)';
PRINT '- odm_notificaciones (NUEVO)';
PRINT '- odm_notificaciones_internas (NUEVO)';
PRINT '- odm_smtp_config (NUEVO)';
PRINT '';
PRINT 'Usuario administrador creado:';
PRINT 'Username: admin';
PRINT 'Password: admin (CAMBIAR INMEDIATAMENTE)';
PRINT '';
PRINT 'IMPORTANTE: Configure el SMTP en la tabla odm_smtp_config';
GO
