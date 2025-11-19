<?php
/*
 * =============================================
 * ARCHIVO DE CONEXIÓN A SQL SERVER
 * Sistema de Gestión Documental
 * =============================================
 *
 * Este archivo maneja la conexión a SQL Server usando PDO
 * Compatible con SQL Server 2012 o superior
 */

// Configuración de la base de datos
define('DB_SERVER', 'localhost');        // Servidor SQL Server
define('DB_PORT', '1433');                // Puerto SQL Server
define('DB_NAME', 'dms_database');        // Nombre de la base de datos
define('DB_USER', 'sa');                  // Usuario SQL Server
define('DB_PASS', 'YourStrongPassword');  // Contraseña
define('DB_DRIVER', 'sqlsrv');            // Driver: sqlsrv para Windows, dblib para Linux

// Configuración de errores
define('DB_DEBUG', true);                 // Mostrar errores de base de datos (cambiar a false en producción)

// Variable global de conexión PDO
$pdo = null;

/**
 * Función para establecer conexión a SQL Server
 * @return PDO|null Retorna objeto PDO o null en caso de error
 */
function conectar_db() {
    global $pdo;

    try {
        // Detectar sistema operativo para usar el driver apropiado
        $driver = DB_DRIVER;

        // DSN para SQL Server
        if ($driver == 'sqlsrv') {
            // Windows con driver SQL Server Native Client
            $dsn = "sqlsrv:Server=" . DB_SERVER . "," . DB_PORT . ";Database=" . DB_NAME;
        } else {
            // Linux con FreeTDS
            $dsn = "dblib:host=" . DB_SERVER . ":" . DB_PORT . ";dbname=" . DB_NAME;
        }

        // Opciones de PDO
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
        );

        // Crear conexión PDO
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        if (DB_DEBUG) {
            error_log("Conexión a SQL Server exitosa");
        }

        return $pdo;

    } catch (PDOException $e) {
        if (DB_DEBUG) {
            error_log("Error de conexión a SQL Server: " . $e->getMessage());
            echo "<div style='color: red; padding: 20px; border: 2px solid red; margin: 20px;'>";
            echo "<strong>Error de conexión a la base de datos:</strong><br>";
            echo $e->getMessage();
            echo "</div>";
        }
        return null;
    }
}

/**
 * Función para cerrar la conexión
 */
function cerrar_db() {
    global $pdo;
    $pdo = null;
}

/**
 * Función para ejecutar una consulta preparada
 * @param string $sql Consulta SQL
 * @param array $params Parámetros para la consulta preparada
 * @return PDOStatement|false Resultado de la consulta
 */
function ejecutar_query($sql, $params = array()) {
    global $pdo;

    try {
        if ($pdo === null) {
            conectar_db();
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;

    } catch (PDOException $e) {
        if (DB_DEBUG) {
            error_log("Error en query: " . $e->getMessage());
            error_log("SQL: " . $sql);
        }
        return false;
    }
}

/**
 * Función para obtener una sola fila
 * @param string $sql Consulta SQL
 * @param array $params Parámetros
 * @return array|false Fila obtenida o false
 */
function obtener_fila($sql, $params = array()) {
    $stmt = ejecutar_query($sql, $params);
    if ($stmt) {
        return $stmt->fetch();
    }
    return false;
}

/**
 * Función para obtener todas las filas
 * @param string $sql Consulta SQL
 * @param array $params Parámetros
 * @return array Arreglo de filas
 */
function obtener_filas($sql, $params = array()) {
    $stmt = ejecutar_query($sql, $params);
    if ($stmt) {
        return $stmt->fetchAll();
    }
    return array();
}

/**
 * Función para obtener el último ID insertado
 * @return int Último ID insertado
 */
function obtener_ultimo_id() {
    global $pdo;

    try {
        // En SQL Server se usa SCOPE_IDENTITY()
        $stmt = $pdo->query("SELECT SCOPE_IDENTITY() AS last_id");
        $row = $stmt->fetch();
        return $row['last_id'];
    } catch (PDOException $e) {
        if (DB_DEBUG) {
            error_log("Error obteniendo último ID: " . $e->getMessage());
        }
        return 0;
    }
}

/**
 * Función para iniciar una transacción
 * @return bool
 */
function iniciar_transaccion() {
    global $pdo;
    try {
        return $pdo->beginTransaction();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Función para confirmar una transacción
 * @return bool
 */
function confirmar_transaccion() {
    global $pdo;
    try {
        return $pdo->commit();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Función para revertir una transacción
 * @return bool
 */
function revertir_transaccion() {
    global $pdo;
    try {
        return $pdo->rollBack();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Función para escapar strings (aunque PDO prepared statements ya lo hace)
 * @param string $str String a escapar
 * @return string String escapado
 */
function escapar_string($str) {
    global $pdo;
    if ($pdo === null) {
        conectar_db();
    }
    return $pdo->quote($str);
}

/**
 * Función de compatibilidad: Convertir sintaxis MySQL a SQL Server
 * @param string $sql Consulta MySQL
 * @return string Consulta SQL Server
 */
function convertir_mysql_a_sqlserver($sql) {
    // LIMIT -> TOP
    $sql = preg_replace('/LIMIT\s+(\d+)/i', 'TOP $1', $sql);

    // NOW() -> GETDATE()
    $sql = str_ireplace('NOW()', 'GETDATE()', $sql);

    // UNIX_TIMESTAMP() -> DATEDIFF(second, '1970-01-01', GETDATE())
    $sql = str_ireplace('UNIX_TIMESTAMP()', "DATEDIFF(second, '1970-01-01', GETDATE())", $sql);

    // CONCAT() -> +
    // Esto es más complejo y puede requerir ajustes manuales

    return $sql;
}

// Establecer conexión automáticamente al incluir este archivo
$pdo = conectar_db();

// Si la conexión falla, detener ejecución
if ($pdo === null && !defined('INSTALLING')) {
    die('<div style="color: red; padding: 20px;">
        <h2>Error de Conexión a la Base de Datos</h2>
        <p>No se pudo establecer conexión con SQL Server.</p>
        <p>Verifique la configuración en <strong>conexion.php</strong></p>
        <ul>
            <li>Servidor: ' . DB_SERVER . '</li>
            <li>Puerto: ' . DB_PORT . '</li>
            <li>Base de datos: ' . DB_NAME . '</li>
            <li>Usuario: ' . DB_USER . '</li>
            <li>Driver: ' . DB_DRIVER . '</li>
        </ul>
        <p><strong>Nota:</strong> Asegúrese de que:</p>
        <ul>
            <li>SQL Server esté corriendo</li>
            <li>El driver PDO para SQL Server esté instalado</li>
            <li>Las credenciales sean correctas</li>
            <li>El firewall permita conexiones al puerto ' . DB_PORT . '</li>
        </ul>
        </div>');
}

?>
