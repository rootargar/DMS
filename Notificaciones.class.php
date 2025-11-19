<?php
/*
 * =============================================
 * CLASE DE NOTIFICACIONES POR CORREO
 * Sistema de Gestión Documental
 * =============================================
 *
 * Esta clase maneja el envío de notificaciones por correo electrónico
 * usando SMTP con PHPMailer
 *
 * Requiere: PHPMailer (instalable vía composer)
 * composer require phpmailer/phpmailer
 */

// Importar PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Notificaciones
{
    private $pdo;
    private $smtp_config;
    private $debug_mode;

    /**
     * Constructor
     * @param PDO $pdo Conexión a la base de datos
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->cargar_configuracion_smtp();
        $this->debug_mode = false;
    }

    /**
     * Cargar configuración SMTP desde la base de datos
     */
    private function cargar_configuracion_smtp()
    {
        try {
            $sql = "SELECT TOP 1 * FROM odm_smtp_config WHERE smtp_activo = 1 ORDER BY id DESC";
            $stmt = $this->pdo->query($sql);
            $this->smtp_config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$this->smtp_config) {
                error_log("No se encontró configuración SMTP activa");
            }
        } catch (PDOException $e) {
            error_log("Error cargando configuración SMTP: " . $e->getMessage());
        }
    }

    /**
     * Activar/desactivar modo debug
     * @param bool $debug
     */
    public function setDebug($debug)
    {
        $this->debug_mode = $debug;
    }

    /**
     * Crear un objeto PHPMailer configurado
     * @return PHPMailer
     * @throws Exception
     */
    private function crear_mailer()
    {
        $mail = new PHPMailer(true);

        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = $this->smtp_config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $this->smtp_config['smtp_username'];
        $mail->Password = $this->smtp_config['smtp_password'];
        $mail->Port = $this->smtp_config['smtp_port'];

        // Seguridad
        if (strtolower($this->smtp_config['smtp_security']) == 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif (strtolower($this->smtp_config['smtp_security']) == 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        // Debug
        if ($this->debug_mode || $this->smtp_config['smtp_debug']) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }

        // Remitente
        $mail->setFrom(
            $this->smtp_config['smtp_from_email'],
            $this->smtp_config['smtp_from_name']
        );

        // Codificación
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        return $mail;
    }

    /**
     * Enviar notificación por correo
     * @param string $destinatario Email del destinatario
     * @param string $asunto Asunto del correo
     * @param string $cuerpo_html Cuerpo del correo en HTML
     * @param int $id_documento ID del documento relacionado (opcional)
     * @param string $tipo_notificacion Tipo de notificación
     * @param int $enviado_por ID del usuario que envía
     * @return bool True si se envió correctamente
     */
    public function enviar_correo($destinatario, $asunto, $cuerpo_html, $id_documento = null, $tipo_notificacion = 'general', $enviado_por = null)
    {
        if (!$this->smtp_config || !$this->smtp_config['smtp_activo']) {
            error_log("SMTP no está activo o configurado");
            $this->registrar_notificacion($id_documento, $tipo_notificacion, $destinatario, $enviado_por, 'error', 'SMTP no configurado', null, $asunto, $cuerpo_html);
            return false;
        }

        try {
            $mail = $this->crear_mailer();

            // Destinatario
            $mail->addAddress($destinatario);

            // Contenido
            $mail->Subject = $asunto;
            $mail->Body = $cuerpo_html;
            $mail->AltBody = strip_tags($cuerpo_html);

            // Enviar
            $enviado = $mail->send();

            if ($enviado) {
                $this->registrar_notificacion($id_documento, $tipo_notificacion, $destinatario, $enviado_por, 'enviado', 'Correo enviado exitosamente', null, $asunto, $cuerpo_html);
                return true;
            } else {
                $this->registrar_notificacion($id_documento, $tipo_notificacion, $destinatario, $enviado_por, 'error', 'No se pudo enviar', null, $asunto, $cuerpo_html);
                return false;
            }

        } catch (Exception $e) {
            error_log("Error al enviar correo: " . $e->getMessage());
            $this->registrar_notificacion($id_documento, $tipo_notificacion, $destinatario, $enviado_por, 'error', 'Error: ' . $e->getMessage(), $e->getMessage(), $asunto, $cuerpo_html);
            return false;
        }
    }

    /**
     * Registrar notificación en la base de datos
     */
    private function registrar_notificacion($id_documento, $tipo_notificacion, $enviado_a, $enviado_por, $status, $mensaje, $error_detalle, $asunto, $cuerpo_html)
    {
        try {
            $sql = "INSERT INTO odm_notificaciones
                    (id_documento, tipo_notificacion, enviado_a, enviado_por, status, mensaje, error_detalle, asunto, cuerpo_html)
                    VALUES (:id_documento, :tipo, :enviado_a, :enviado_por, :status, :mensaje, :error_detalle, :asunto, :cuerpo_html)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id_documento' => $id_documento,
                ':tipo' => $tipo_notificacion,
                ':enviado_a' => $enviado_a,
                ':enviado_por' => $enviado_por,
                ':status' => $status,
                ':mensaje' => $mensaje,
                ':error_detalle' => $error_detalle,
                ':asunto' => $asunto,
                ':cuerpo_html' => $cuerpo_html
            ]);
        } catch (PDOException $e) {
            error_log("Error registrando notificación: " . $e->getMessage());
        }
    }

    /**
     * Generar plantilla HTML para correos
     * @param string $titulo Título del correo
     * @param string $contenido Contenido principal
     * @param array $datos Datos adicionales
     * @return string HTML del correo
     */
    public function generar_plantilla_html($titulo, $contenido, $datos = [])
    {
        $base_url = isset($datos['base_url']) ? $datos['base_url'] : 'http://localhost';

        $html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($titulo) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 20px 0; text-align: center;">
                <table role="presentation" style="width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Sistema de Gestión Documental</h1>
                        </td>
                    </tr>

                    <!-- Título -->
                    <tr>
                        <td style="padding: 30px 40px 20px;">
                            <h2 style="color: #333333; margin: 0 0 10px; font-size: 20px;">' . htmlspecialchars($titulo) . '</h2>
                        </td>
                    </tr>

                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <div style="color: #666666; font-size: 14px; line-height: 1.6;">
                                ' . $contenido . '
                            </div>
                        </td>
                    </tr>';

        // Información del documento si está disponible
        if (isset($datos['documento'])) {
            $html .= '
                    <tr>
                        <td style="padding: 0 40px 20px;">
                            <table style="width: 100%; border: 1px solid #e0e0e0; border-radius: 4px; overflow: hidden;">
                                <tr style="background-color: #f8f8f8;">
                                    <td colspan="2" style="padding: 12px; font-weight: bold; color: #333333; border-bottom: 1px solid #e0e0e0;">
                                        📄 Información del Documento
                                    </td>
                                </tr>';

            if (isset($datos['documento']['nombre'])) {
                $html .= '
                                <tr>
                                    <td style="padding: 10px; width: 30%; font-weight: bold; color: #666666; border-bottom: 1px solid #f0f0f0;">Nombre:</td>
                                    <td style="padding: 10px; color: #333333; border-bottom: 1px solid #f0f0f0;">' . htmlspecialchars($datos['documento']['nombre']) . '</td>
                                </tr>';
            }

            if (isset($datos['documento']['version'])) {
                $html .= '
                                <tr>
                                    <td style="padding: 10px; width: 30%; font-weight: bold; color: #666666; border-bottom: 1px solid #f0f0f0;">Versión:</td>
                                    <td style="padding: 10px; color: #333333; border-bottom: 1px solid #f0f0f0;">' . htmlspecialchars($datos['documento']['version']) . '</td>
                                </tr>';
            }

            if (isset($datos['documento']['categoria'])) {
                $html .= '
                                <tr>
                                    <td style="padding: 10px; width: 30%; font-weight: bold; color: #666666; border-bottom: 1px solid #f0f0f0;">Categoría:</td>
                                    <td style="padding: 10px; color: #333333; border-bottom: 1px solid #f0f0f0;">' . htmlspecialchars($datos['documento']['categoria']) . '</td>
                                </tr>';
            }

            if (isset($datos['usuario'])) {
                $html .= '
                                <tr>
                                    <td style="padding: 10px; width: 30%; font-weight: bold; color: #666666; border-bottom: 1px solid #f0f0f0;">Usuario:</td>
                                    <td style="padding: 10px; color: #333333; border-bottom: 1px solid #f0f0f0;">' . htmlspecialchars($datos['usuario']) . '</td>
                                </tr>';
            }

            if (isset($datos['fecha'])) {
                $html .= '
                                <tr>
                                    <td style="padding: 10px; width: 30%; font-weight: bold; color: #666666;">Fecha:</td>
                                    <td style="padding: 10px; color: #333333;">' . htmlspecialchars($datos['fecha']) . '</td>
                                </tr>';
            }

            $html .= '
                            </table>
                        </td>
                    </tr>';
        }

        // Enlace si está disponible
        if (isset($datos['enlace'])) {
            $html .= '
                    <tr>
                        <td style="padding: 0 40px 30px; text-align: center;">
                            <a href="' . htmlspecialchars($datos['enlace']) . '"
                               style="display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold;">
                                Ver Documento
                            </a>
                        </td>
                    </tr>';
        }

        // Comentarios si están disponibles
        if (isset($datos['comentarios']) && !empty($datos['comentarios'])) {
            $html .= '
                    <tr>
                        <td style="padding: 0 40px 20px;">
                            <div style="background-color: #f8f9fa; padding: 15px; border-left: 4px solid #667eea; border-radius: 4px;">
                                <strong style="color: #333333;">Comentarios:</strong>
                                <p style="margin: 10px 0 0; color: #666666;">' . nl2br(htmlspecialchars($datos['comentarios'])) . '</p>
                            </div>
                        </td>
                    </tr>';
        }

        $html .= '
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f8f8; padding: 20px 40px; border-top: 1px solid #e0e0e0;">
                            <p style="margin: 0; font-size: 12px; color: #999999; text-align: center;">
                                Este es un correo automático del Sistema de Gestión Documental.<br>
                                Por favor no responda a este mensaje.
                            </p>
                            <p style="margin: 10px 0 0; font-size: 12px; color: #999999; text-align: center;">
                                &copy; ' . date('Y') . ' Sistema DMS. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

        return $html;
    }

    // ===========================================
    // MÉTODOS ESPECÍFICOS PARA CADA TIPO DE NOTIFICACIÓN
    // ===========================================

    /**
     * Notificar cuando se sube un documento nuevo
     */
    public function notificar_documento_nuevo($id_documento, $nombre_documento, $categoria, $usuario, $destinatarios)
    {
        $asunto = "Nuevo Documento: " . $nombre_documento;
        $contenido = "<p>Se ha subido un nuevo documento al sistema.</p>";

        $datos = [
            'documento' => [
                'nombre' => $nombre_documento,
                'version' => '1.0',
                'categoria' => $categoria
            ],
            'usuario' => $usuario,
            'fecha' => date('d/m/Y H:i:s'),
            'enlace' => $this->construir_enlace_documento($id_documento)
        ];

        $html = $this->generar_plantilla_html($asunto, $contenido, $datos);

        foreach ($destinatarios as $email) {
            $this->enviar_correo($email, $asunto, $html, $id_documento, 'documento_nuevo', $_SESSION['uid'] ?? null);
        }
    }

    /**
     * Notificar nueva versión de documento
     */
    public function notificar_nueva_version($id_documento, $nombre_documento, $version, $categoria, $usuario, $comentarios, $destinatarios)
    {
        $asunto = "Nueva Versión: " . $nombre_documento . " (v" . $version . ")";
        $contenido = "<p>Se ha creado una nueva versión del documento.</p>";

        $datos = [
            'documento' => [
                'nombre' => $nombre_documento,
                'version' => $version,
                'categoria' => $categoria
            ],
            'usuario' => $usuario,
            'fecha' => date('d/m/Y H:i:s'),
            'comentarios' => $comentarios,
            'enlace' => $this->construir_enlace_documento($id_documento)
        ];

        $html = $this->generar_plantilla_html($asunto, $contenido, $datos);

        foreach ($destinatarios as $email) {
            $this->enviar_correo($email, $asunto, $html, $id_documento, 'nueva_version', $_SESSION['uid'] ?? null);
        }
    }

    /**
     * Notificar que un documento requiere revisión
     */
    public function notificar_requiere_revision($id_documento, $nombre_documento, $categoria, $usuario, $destinatarios)
    {
        $asunto = "Documento Requiere Revisión: " . $nombre_documento;
        $contenido = "<p>Se ha solicitado la revisión del siguiente documento.</p>
                      <p><strong>Por favor revise y apruebe o rechace el documento.</strong></p>";

        $datos = [
            'documento' => [
                'nombre' => $nombre_documento,
                'categoria' => $categoria
            ],
            'usuario' => $usuario,
            'fecha' => date('d/m/Y H:i:s'),
            'enlace' => $this->construir_enlace_documento($id_documento)
        ];

        $html = $this->generar_plantilla_html($asunto, $contenido, $datos);

        foreach ($destinatarios as $email) {
            $this->enviar_correo($email, $asunto, $html, $id_documento, 'requiere_revision', $_SESSION['uid'] ?? null);
        }
    }

    /**
     * Notificar documento aprobado
     */
    public function notificar_documento_aprobado($id_documento, $nombre_documento, $categoria, $aprobador, $comentarios, $destinatarios)
    {
        $asunto = "Documento Aprobado: " . $nombre_documento;
        $contenido = "<p style='color: #28a745; font-weight: bold;'>✓ El documento ha sido aprobado.</p>";

        $datos = [
            'documento' => [
                'nombre' => $nombre_documento,
                'categoria' => $categoria
            ],
            'usuario' => $aprobador,
            'fecha' => date('d/m/Y H:i:s'),
            'comentarios' => $comentarios,
            'enlace' => $this->construir_enlace_documento($id_documento)
        ];

        $html = $this->generar_plantilla_html($asunto, $contenido, $datos);

        foreach ($destinatarios as $email) {
            $this->enviar_correo($email, $asunto, $html, $id_documento, 'documento_aprobado', $_SESSION['uid'] ?? null);
        }
    }

    /**
     * Notificar documento rechazado
     */
    public function notificar_documento_rechazado($id_documento, $nombre_documento, $categoria, $rechazador, $motivo, $destinatarios)
    {
        $asunto = "Documento Rechazado: " . $nombre_documento;
        $contenido = "<p style='color: #dc3545; font-weight: bold;'>✗ El documento ha sido rechazado.</p>
                      <p>Por favor revise los comentarios y realice las correcciones necesarias.</p>";

        $datos = [
            'documento' => [
                'nombre' => $nombre_documento,
                'categoria' => $categoria
            ],
            'usuario' => $rechazador,
            'fecha' => date('d/m/Y H:i:s'),
            'comentarios' => $motivo,
            'enlace' => $this->construir_enlace_documento($id_documento)
        ];

        $html = $this->generar_plantilla_html($asunto, $contenido, $datos);

        foreach ($destinatarios as $email) {
            $this->enviar_correo($email, $asunto, $html, $id_documento, 'documento_rechazado', $_SESSION['uid'] ?? null);
        }
    }

    /**
     * Notificar documento próximo a vencer
     */
    public function notificar_documento_por_vencer($id_documento, $nombre_documento, $categoria, $dias_restantes, $destinatarios)
    {
        $asunto = "Documento Próximo a Vencer: " . $nombre_documento;
        $contenido = "<p style='color: #ff9800; font-weight: bold;'>⚠ Advertencia: Este documento está próximo a vencer.</p>
                      <p>Días restantes: <strong>" . $dias_restantes . "</strong></p>
                      <p>Por favor renueve o actualice el documento.</p>";

        $datos = [
            'documento' => [
                'nombre' => $nombre_documento,
                'categoria' => $categoria
            ],
            'fecha' => date('d/m/Y H:i:s'),
            'enlace' => $this->construir_enlace_documento($id_documento)
        ];

        $html = $this->generar_plantilla_html($asunto, $contenido, $datos);

        foreach ($destinatarios as $email) {
            $this->enviar_correo($email, $asunto, $html, $id_documento, 'documento_por_vencer', null);
        }
    }

    /**
     * Notificar documento eliminado o actualizado
     */
    public function notificar_documento_modificado($id_documento, $nombre_documento, $categoria, $accion, $usuario, $destinatarios)
    {
        $asunto = "Documento " . ucfirst($accion) . ": " . $nombre_documento;
        $contenido = "<p>El documento ha sido <strong>" . $accion . "</strong>.</p>";

        $datos = [
            'documento' => [
                'nombre' => $nombre_documento,
                'categoria' => $categoria
            ],
            'usuario' => $usuario,
            'fecha' => date('d/m/Y H:i:s')
        ];

        if ($accion != 'eliminado') {
            $datos['enlace'] = $this->construir_enlace_documento($id_documento);
        }

        $html = $this->generar_plantilla_html($asunto, $contenido, $datos);

        foreach ($destinatarios as $email) {
            $this->enviar_correo($email, $asunto, $html, $id_documento, 'documento_' . $accion, $_SESSION['uid'] ?? null);
        }
    }

    /**
     * Notificar cuando se agrega un comentario
     */
    public function notificar_nuevo_comentario($id_documento, $nombre_documento, $categoria, $usuario, $comentario, $destinatarios)
    {
        $asunto = "Nuevo Comentario en: " . $nombre_documento;
        $contenido = "<p>Se ha agregado un nuevo comentario al documento.</p>";

        $datos = [
            'documento' => [
                'nombre' => $nombre_documento,
                'categoria' => $categoria
            ],
            'usuario' => $usuario,
            'fecha' => date('d/m/Y H:i:s'),
            'comentarios' => $comentario,
            'enlace' => $this->construir_enlace_documento($id_documento)
        ];

        $html = $this->generar_plantilla_html($asunto, $contenido, $datos);

        foreach ($destinatarios as $email) {
            $this->enviar_correo($email, $asunto, $html, $id_documento, 'nuevo_comentario', $_SESSION['uid'] ?? null);
        }
    }

    /**
     * Construir enlace al documento
     * @param int $id_documento
     * @return string
     */
    private function construir_enlace_documento($id_documento)
    {
        // Asume que tienes una variable global o configuración con la URL base
        $base_url = isset($GLOBALS['CONFIG']['base_url']) ? $GLOBALS['CONFIG']['base_url'] : 'http://localhost';
        return $base_url . '/details.php?id=' . $id_documento;
    }
}
?>
