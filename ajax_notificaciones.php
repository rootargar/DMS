<?php
/*
 * =============================================
 * AJAX HANDLER PARA NOTIFICACIONES INTERNAS
 * Sistema de Gestión Documental
 * =============================================
 */

session_start();
require_once 'conexion.php';
require_once 'NotificacionesInternas.class.php';

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['uid'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Crear instancia de NotificacionesInternas
$notif = new NotificacionesInternas($pdo, $_SESSION['uid']);

// Obtener acción
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Ejecutar acción
switch ($action) {
    case 'get':
        // Obtener notificaciones
        $solo_no_leidas = isset($_GET['unread_only']) && $_GET['unread_only'] == '1';
        $limite = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        echo $notif->obtener_json($solo_no_leidas, $limite);
        break;

    case 'count':
        // Contar no leídas
        echo json_encode([
            'success' => true,
            'count' => $notif->contar_no_leidas()
        ]);
        break;

    case 'mark_read':
        // Marcar como leída
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id > 0) {
            $result = $notif->marcar_como_leida($id);
            echo json_encode([
                'success' => $result,
                'count' => $notif->contar_no_leidas()
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
        }
        break;

    case 'mark_all_read':
        // Marcar todas como leídas
        $result = $notif->marcar_todas_leidas();
        echo json_encode([
            'success' => $result,
            'count' => $notif->contar_no_leidas()
        ]);
        break;

    case 'clear_read':
        // Limpiar notificaciones leídas
        try {
            $sql = "DELETE FROM odm_notificaciones_internas
                    WHERE usuario_id = :usuario_id AND leida = 1";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([':usuario_id' => $_SESSION['uid']]);
            echo json_encode([
                'success' => $result,
                'count' => $notif->contar_no_leidas()
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        break;
}
?>
