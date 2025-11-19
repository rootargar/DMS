<?php
/*
 * =============================================
 * PÁGINA DE REPORTES
 * Sistema de Gestión Documental
 * =============================================
 */

session_start();
require_once 'conexion.php';
require_once 'Reportes.class.php';

// Verificar autenticación
if (!isset($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}

// Si se solicita generar un reporte
if (isset($_GET['generar'])) {
    $reportes = new Reportes($pdo);
    $tipo = $_GET['tipo'] ?? '';
    $formato = $_GET['formato'] ?? 'excel';

    switch ($tipo) {
        case 'por_categoria':
            $reportes->reporteDocumentosPorCategoria($formato);
            break;

        case 'vencimiento':
            $dias = isset($_GET['dias']) ? (int)$_GET['dias'] : null;
            $reportes->reporteDocumentosVencimiento($dias, $formato);
            break;

        case 'historial':
            $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
            $fecha_desde = $_GET['fecha_desde'] ?? null;
            $fecha_hasta = $_GET['fecha_hasta'] ?? null;
            $reportes->reporteHistorialUsuario($user_id, $fecha_desde, $fecha_hasta, $formato);
            break;

        default:
            echo "Tipo de reporte no válido";
            exit;
    }
    exit;
}

// Obtener lista de usuarios para el filtro
$sql = "SELECT id, username, first_name, last_name FROM odm_user WHERE activo = 1 ORDER BY username";
$stmt = $pdo->query($sql);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema DMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }

        .report-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }

        .report-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .report-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-right: 15px;
        }

        .report-card-header h3 {
            color: #333;
            font-size: 18px;
        }

        .report-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            color: #555;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            flex: 1;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-excel {
            background: #217346;
            color: white;
        }

        .btn-excel:hover {
            background: #1a5c37;
        }

        .btn-pdf {
            background: #dc3545;
            color: white;
        }

        .btn-pdf:hover {
            background: #c82333;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Volver al inicio
        </a>

        <div class="header">
            <h1><i class="fas fa-chart-bar"></i> Generador de Reportes</h1>
            <p>Genere reportes detallados del sistema de gestión documental</p>
        </div>

        <div class="reports-grid">
            <!-- Reporte 1: Documentos por Categoría -->
            <div class="report-card">
                <div class="report-card-header">
                    <div class="report-icon">
                        <i class="fas fa-folder"></i>
                    </div>
                    <div>
                        <h3>Documentos por Categoría</h3>
                    </div>
                </div>
                <div class="report-description">
                    Resumen de todos los documentos agrupados por categoría, mostrando totales, documentos publicados y pendientes.
                </div>
                <form id="form1" action="reportes.php" method="GET">
                    <input type="hidden" name="generar" value="1">
                    <input type="hidden" name="tipo" value="por_categoria">
                    <div class="button-group">
                        <button type="submit" name="formato" value="excel" class="btn btn-excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button type="submit" name="formato" value="pdf" class="btn btn-pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                    </div>
                </form>
            </div>

            <!-- Reporte 2: Documentos Próximos a Vencer -->
            <div class="report-card">
                <div class="report-card-header">
                    <div class="report-icon">
                        <i class="fas fa-calendar-exclamation"></i>
                    </div>
                    <div>
                        <h3>Documentos Próximos a Vencer</h3>
                    </div>
                </div>
                <div class="report-description">
                    Lista de documentos que están vencidos o próximos a vencer, con información del responsable.
                </div>
                <form action="reportes.php" method="GET">
                    <input type="hidden" name="generar" value="1">
                    <input type="hidden" name="tipo" value="vencimiento">
                    <div class="form-group">
                        <label>Filtrar próximos (días):</label>
                        <select name="dias" class="form-control">
                            <option value="">Todos</option>
                            <option value="7">Próximos 7 días</option>
                            <option value="15" selected>Próximos 15 días</option>
                            <option value="30">Próximos 30 días</option>
                            <option value="60">Próximos 60 días</option>
                        </select>
                    </div>
                    <div class="button-group">
                        <button type="submit" name="formato" value="excel" class="btn btn-excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button type="submit" name="formato" value="pdf" class="btn btn-pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                    </div>
                </form>
            </div>

            <!-- Reporte 3: Historial de Usuario -->
            <div class="report-card">
                <div class="report-card-header">
                    <div class="report-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div>
                        <h3>Historial de Actividad</h3>
                    </div>
                </div>
                <div class="report-description">
                    Registro completo de todas las acciones realizadas por los usuarios en el sistema.
                </div>
                <form action="reportes.php" method="GET">
                    <input type="hidden" name="generar" value="1">
                    <input type="hidden" name="tipo" value="historial">
                    <div class="form-group">
                        <label>Usuario (opcional):</label>
                        <select name="user_id" class="form-control">
                            <option value="">Todos los usuarios</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?= $usuario['id'] ?>">
                                    <?= htmlspecialchars($usuario['username'] . ' - ' . $usuario['first_name'] . ' ' . $usuario['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Fecha desde:</label>
                        <input type="date" name="fecha_desde" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Fecha hasta:</label>
                        <input type="date" name="fecha_hasta" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="button-group">
                        <button type="submit" name="formato" value="excel" class="btn btn-excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button type="submit" name="formato" value="pdf" class="btn btn-pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
