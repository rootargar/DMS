<?php
/*
 * =============================================
 * CLASE DE NOTIFICACIONES INTERNAS
 * Sistema de Gestión Documental
 * =============================================
 *
 * Esta clase maneja las notificaciones en pantalla
 * Tipo campana con contador
 */

class NotificacionesInternas
{
    private $pdo;
    private $usuario_id;

    /**
     * Constructor
     * @param PDO $pdo Conexión a la base de datos
     * @param int $usuario_id ID del usuario actual
     */
    public function __construct($pdo, $usuario_id)
    {
        $this->pdo = $pdo;
        $this->usuario_id = $usuario_id;
    }

    /**
     * Crear una nueva notificación interna
     */
    public function crear_notificacion($titulo, $mensaje, $id_documento = null, $tipo = 'general', $url = null, $icono = 'fa-file', $prioridad = 'normal')
    {
        try {
            $sql = "INSERT INTO odm_notificaciones_internas
                    (usuario_id, titulo, mensaje, id_documento, tipo, url, icono, prioridad)
                    VALUES (:usuario_id, :titulo, :mensaje, :id_documento, :tipo, :url, :icono, :prioridad)";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':usuario_id' => $this->usuario_id,
                ':titulo' => $titulo,
                ':mensaje' => $mensaje,
                ':id_documento' => $id_documento,
                ':tipo' => $tipo,
                ':url' => $url,
                ':icono' => $icono,
                ':prioridad' => $prioridad
            ]);
        } catch (PDOException $e) {
            error_log("Error creando notificación interna: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener notificaciones del usuario
     */
    public function obtener_notificaciones($solo_no_leidas = false, $limite = 50)
    {
        try {
            $sql = "SELECT TOP " . (int)$limite . "
                        n.id,
                        n.titulo,
                        n.mensaje,
                        n.id_documento,
                        n.tipo,
                        n.leida,
                        n.fecha_creacion,
                        n.url,
                        n.icono,
                        n.prioridad,
                        d.realname AS nombre_documento
                    FROM odm_notificaciones_internas n
                    LEFT JOIN odm_data d ON n.id_documento = d.id
                    WHERE n.usuario_id = :usuario_id";

            if ($solo_no_leidas) {
                $sql .= " AND n.leida = 0";
            }

            $sql .= " ORDER BY n.fecha_creacion DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':usuario_id' => $this->usuario_id]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error obteniendo notificaciones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar notificaciones no leídas
     */
    public function contar_no_leidas()
    {
        try {
            $sql = "SELECT COUNT(*) as total
                    FROM odm_notificaciones_internas
                    WHERE usuario_id = :usuario_id AND leida = 0";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':usuario_id' => $this->usuario_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)$result['total'];
        } catch (PDOException $e) {
            error_log("Error contando notificaciones: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Marcar notificación como leída
     */
    public function marcar_como_leida($notificacion_id)
    {
        try {
            $sql = "UPDATE odm_notificaciones_internas
                    SET leida = 1, fecha_lectura = GETDATE()
                    WHERE id = :id AND usuario_id = :usuario_id";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':id' => $notificacion_id,
                ':usuario_id' => $this->usuario_id
            ]);
        } catch (PDOException $e) {
            error_log("Error marcando notificación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marcar todas como leídas
     */
    public function marcar_todas_leidas()
    {
        try {
            $sql = "UPDATE odm_notificaciones_internas
                    SET leida = 1, fecha_lectura = GETDATE()
                    WHERE usuario_id = :usuario_id AND leida = 0";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([':usuario_id' => $this->usuario_id]);
        } catch (PDOException $e) {
            error_log("Error marcando todas: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener JSON para AJAX
     */
    public function obtener_json($solo_no_leidas = false, $limite = 20)
    {
        $notificaciones = $this->obtener_notificaciones($solo_no_leidas, $limite);
        return json_encode([
            'success' => true,
            'count' => $this->contar_no_leidas(),
            'notifications' => $notificaciones
        ]);
    }
}
?>
