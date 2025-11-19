<?php
/*
 * =============================================
 * CLASE DE REPORTES
 * Sistema de Gestión Documental
 * =============================================
 *
 * Esta clase genera reportes en PDF y Excel
 * Requiere:
 * - TCPDF para PDF: composer require tecnickcom/tcpdf
 * - PhpSpreadsheet para Excel: composer require phpoffice/phpspreadsheet
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class Reportes
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // =============================================
    // REPORTES DE DOCUMENTOS
    // =============================================

    /**
     * Reporte de documentos por categoría
     */
    public function reporteDocumentosPorCategoria($formato = 'excel')
    {
        $sql = "SELECT
                    c.name AS categoria,
                    COUNT(d.id) AS total_documentos,
                    COUNT(CASE WHEN d.publishable = 1 THEN 1 END) AS publicados,
                    COUNT(CASE WHEN d.publishable = 0 THEN 1 END) AS pendientes
                FROM odm_category c
                LEFT JOIN odm_data d ON c.id = d.category
                GROUP BY c.id, c.name
                ORDER BY c.name";

        $stmt = $this->pdo->query($sql);
        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($formato == 'excel') {
            return $this->generarExcelDocumentosPorCategoria($datos);
        } else {
            return $this->generarPDFDocumentosPorCategoria($datos);
        }
    }

    /**
     * Reporte de documentos vencidos o próximos a vencer
     */
    public function reporteDocumentosVencimiento($dias = null, $formato = 'excel')
    {
        $sql = "SELECT
                    d.id,
                    d.realname AS documento,
                    c.name AS categoria,
                    dept.name AS departamento,
                    d.fecha_vencimiento,
                    DATEDIFF(day, GETDATE(), d.fecha_vencimiento) AS dias_restantes,
                    u.first_name + ' ' + u.last_name AS responsable,
                    u.Email AS email_responsable,
                    CASE
                        WHEN d.fecha_vencimiento < GETDATE() THEN 'Vencido'
                        WHEN DATEDIFF(day, GETDATE(), d.fecha_vencimiento) <= 7 THEN 'Crítico'
                        WHEN DATEDIFF(day, GETDATE(), d.fecha_vencimiento) <= 15 THEN 'Próximo'
                        ELSE 'Normal'
                    END AS estado
                FROM odm_data d
                INNER JOIN odm_category c ON d.category = c.id
                LEFT JOIN odm_department dept ON d.department = dept.id
                LEFT JOIN odm_user u ON d.owner = u.id
                WHERE d.fecha_vencimiento IS NOT NULL";

        if ($dias !== null) {
            $sql .= " AND DATEDIFF(day, GETDATE(), d.fecha_vencimiento) <= :dias";
        }

        $sql .= " ORDER BY d.fecha_vencimiento ASC";

        $stmt = $this->pdo->prepare($sql);
        if ($dias !== null) {
            $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        }
        $stmt->execute();
        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($formato == 'excel') {
            return $this->generarExcelDocumentosVencimiento($datos);
        } else {
            return $this->generarPDFDocumentosVencimiento($datos);
        }
    }

    /**
     * Reporte de historial por usuario
     */
    public function reporteHistorialUsuario($user_id = null, $fecha_desde = null, $fecha_hasta = null, $formato = 'excel')
    {
        $sql = "SELECT
                    al.timestamp AS fecha_hora,
                    u.username,
                    u.first_name + ' ' + u.last_name AS usuario,
                    d.realname AS documento,
                    c.name AS categoria,
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
                        ELSE 'Otra'
                    END AS accion,
                    al.ip_address,
                    al.detalles
                FROM odm_access_log al
                INNER JOIN odm_user u ON al.user_id = u.id
                INNER JOIN odm_data d ON al.file_id = d.id
                INNER JOIN odm_category c ON d.category = c.id
                WHERE 1=1";

        $params = [];

        if ($user_id !== null) {
            $sql .= " AND al.user_id = :user_id";
            $params[':user_id'] = $user_id;
        }

        if ($fecha_desde !== null) {
            $sql .= " AND al.timestamp >= :fecha_desde";
            $params[':fecha_desde'] = $fecha_desde;
        }

        if ($fecha_hasta !== null) {
            $sql .= " AND al.timestamp <= :fecha_hasta";
            $params[':fecha_hasta'] = $fecha_hasta . ' 23:59:59';
        }

        $sql .= " ORDER BY al.timestamp DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($formato == 'excel') {
            return $this->generarExcelHistorialUsuario($datos);
        } else {
            return $this->generarPDFHistorialUsuario($datos);
        }
    }

    // =============================================
    // GENERACIÓN DE EXCEL
    // =============================================

    private function generarExcelDocumentosPorCategoria($datos)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Título
        $sheet->setCellValue('A1', 'REPORTE: DOCUMENTOS POR CATEGORÍA');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Fecha
        $sheet->setCellValue('A2', 'Fecha: ' . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A2:D2');

        // Encabezados
        $row = 4;
        $headers = ['Categoría', 'Total Documentos', 'Publicados', 'Pendientes'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($col . $row)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        // Datos
        $row = 5;
        foreach ($datos as $fila) {
            $sheet->setCellValue('A' . $row, $fila['categoria']);
            $sheet->setCellValue('B' . $row, $fila['total_documentos']);
            $sheet->setCellValue('C' . $row, $fila['publicados']);
            $sheet->setCellValue('D' . $row, $fila['pendientes']);
            $row++;
        }

        // Estilos y ajustes
        $sheet->getStyle('A4:D' . ($row - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);

        // Guardar
        $filename = 'documentos_por_categoria_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    private function generarExcelDocumentosVencimiento($datos)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Título
        $sheet->setCellValue('A1', 'REPORTE: DOCUMENTOS PRÓXIMOS A VENCER');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Fecha: ' . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A2:H2');

        // Encabezados
        $row = 4;
        $headers = ['Documento', 'Categoría', 'Departamento', 'Fecha Vencimiento', 'Días Restantes', 'Estado', 'Responsable', 'Email'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($col . $row)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        // Datos
        $row = 5;
        foreach ($datos as $fila) {
            $sheet->setCellValue('A' . $row, $fila['documento']);
            $sheet->setCellValue('B' . $row, $fila['categoria']);
            $sheet->setCellValue('C' . $row, $fila['departamento']);
            $sheet->setCellValue('D' . $row, $fila['fecha_vencimiento']);
            $sheet->setCellValue('E' . $row, $fila['dias_restantes']);
            $sheet->setCellValue('F' . $row, $fila['estado']);
            $sheet->setCellValue('G' . $row, $fila['responsable']);
            $sheet->setCellValue('H' . $row, $fila['email_responsable']);

            // Colorear según estado
            $color = 'FFFFFF';
            switch ($fila['estado']) {
                case 'Vencido':
                    $color = 'FFE6E6';
                    break;
                case 'Crítico':
                    $color = 'FFF3CD';
                    break;
                case 'Próximo':
                    $color = 'E7F3FF';
                    break;
            }
            $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($color);

            $row++;
        }

        // Estilos
        $sheet->getStyle('A4:H' . ($row - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Guardar
        $filename = 'documentos_vencimiento_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    private function generarExcelHistorialUsuario($datos)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Título
        $sheet->setCellValue('A1', 'REPORTE: HISTORIAL DE ACTIVIDAD POR USUARIO');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Fecha: ' . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A2:G2');

        // Encabezados
        $row = 4;
        $headers = ['Fecha/Hora', 'Usuario', 'Documento', 'Categoría', 'Acción', 'IP', 'Detalles'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($col . $row)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        // Datos
        $row = 5;
        foreach ($datos as $fila) {
            $sheet->setCellValue('A' . $row, $fila['fecha_hora']);
            $sheet->setCellValue('B' . $row, $fila['usuario']);
            $sheet->setCellValue('C' . $row, $fila['documento']);
            $sheet->setCellValue('D' . $row, $fila['categoria']);
            $sheet->setCellValue('E' . $row, $fila['accion']);
            $sheet->setCellValue('F' . $row, $fila['ip_address']);
            $sheet->setCellValue('G' . $row, $fila['detalles']);
            $row++;
        }

        // Estilos
        $sheet->getStyle('A4:G' . ($row - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Guardar
        $filename = 'historial_usuario_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    // =============================================
    // GENERACIÓN DE PDF
    // =============================================

    private function generarPDFDocumentosPorCategoria($datos)
    {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->SetCreator('Sistema DMS');
        $pdf->SetAuthor('Sistema de Gestión Documental');
        $pdf->SetTitle('Documentos por Categoría');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'REPORTE: DOCUMENTOS POR CATEGORÍA', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1, 'L');
        $pdf->Ln(5);

        // Tabla
        $pdf->SetFillColor(68, 114, 196);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 10);

        $pdf->Cell(80, 7, 'Categoría', 1, 0, 'C', 1);
        $pdf->Cell(35, 7, 'Total', 1, 0, 'C', 1);
        $pdf->Cell(35, 7, 'Publicados', 1, 0, 'C', 1);
        $pdf->Cell(35, 7, 'Pendientes', 1, 1, 'C', 1);

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9);

        foreach ($datos as $fila) {
            $pdf->Cell(80, 6, $fila['categoria'], 1, 0, 'L');
            $pdf->Cell(35, 6, $fila['total_documentos'], 1, 0, 'C');
            $pdf->Cell(35, 6, $fila['publicados'], 1, 0, 'C');
            $pdf->Cell(35, 6, $fila['pendientes'], 1, 1, 'C');
        }

        $pdf->Output('documentos_por_categoria_' . date('Ymd_His') . '.pdf', 'D');
        exit;
    }

    private function generarPDFDocumentosVencimiento($datos)
    {
        $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->SetCreator('Sistema DMS');
        $pdf->SetTitle('Documentos Próximos a Vencer');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'REPORTE: DOCUMENTOS PRÓXIMOS A VENCER', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1, 'L');
        $pdf->Ln(3);

        // Tabla
        $pdf->SetFillColor(68, 114, 196);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);

        $pdf->Cell(60, 6, 'Documento', 1, 0, 'C', 1);
        $pdf->Cell(35, 6, 'Categoría', 1, 0, 'C', 1);
        $pdf->Cell(30, 6, 'Vencimiento', 1, 0, 'C', 1);
        $pdf->Cell(20, 6, 'Días', 1, 0, 'C', 1);
        $pdf->Cell(25, 6, 'Estado', 1, 0, 'C', 1);
        $pdf->Cell(50, 6, 'Responsable', 1, 0, 'C', 1);
        $pdf->Cell(50, 6, 'Email', 1, 1, 'C', 1);

        $pdf->SetFont('helvetica', '', 7);

        foreach ($datos as $fila) {
            // Color según estado
            switch ($fila['estado']) {
                case 'Vencido':
                    $pdf->SetFillColor(255, 230, 230);
                    break;
                case 'Crítico':
                    $pdf->SetFillColor(255, 243, 205);
                    break;
                case 'Próximo':
                    $pdf->SetFillColor(231, 243, 255);
                    break;
                default:
                    $pdf->SetFillColor(255, 255, 255);
            }
            $pdf->SetTextColor(0, 0, 0);

            $pdf->Cell(60, 5, substr($fila['documento'], 0, 35), 1, 0, 'L', 1);
            $pdf->Cell(35, 5, $fila['categoria'], 1, 0, 'L', 1);
            $pdf->Cell(30, 5, date('d/m/Y', strtotime($fila['fecha_vencimiento'])), 1, 0, 'C', 1);
            $pdf->Cell(20, 5, $fila['dias_restantes'], 1, 0, 'C', 1);
            $pdf->Cell(25, 5, $fila['estado'], 1, 0, 'C', 1);
            $pdf->Cell(50, 5, substr($fila['responsable'], 0, 25), 1, 0, 'L', 1);
            $pdf->Cell(50, 5, substr($fila['email_responsable'], 0, 30), 1, 1, 'L', 1);
        }

        $pdf->Output('documentos_vencimiento_' . date('Ymd_His') . '.pdf', 'D');
        exit;
    }

    private function generarPDFHistorialUsuario($datos)
    {
        $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->SetCreator('Sistema DMS');
        $pdf->SetTitle('Historial de Usuario');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'REPORTE: HISTORIAL DE ACTIVIDAD', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1, 'L');
        $pdf->Ln(3);

        // Tabla
        $pdf->SetFillColor(68, 114, 196);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);

        $pdf->Cell(35, 6, 'Fecha/Hora', 1, 0, 'C', 1);
        $pdf->Cell(40, 6, 'Usuario', 1, 0, 'C', 1);
        $pdf->Cell(60, 6, 'Documento', 1, 0, 'C', 1);
        $pdf->Cell(35, 6, 'Categoría', 1, 0, 'C', 1);
        $pdf->Cell(30, 6, 'Acción', 1, 0, 'C', 1);
        $pdf->Cell(30, 6, 'IP', 1, 1, 'C', 1);

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 7);

        foreach ($datos as $fila) {
            $pdf->Cell(35, 5, date('d/m/Y H:i', strtotime($fila['fecha_hora'])), 1, 0, 'C');
            $pdf->Cell(40, 5, substr($fila['usuario'], 0, 25), 1, 0, 'L');
            $pdf->Cell(60, 5, substr($fila['documento'], 0, 35), 1, 0, 'L');
            $pdf->Cell(35, 5, $fila['categoria'], 1, 0, 'L');
            $pdf->Cell(30, 5, $fila['accion'], 1, 0, 'C');
            $pdf->Cell(30, 5, $fila['ip_address'], 1, 1, 'C');
        }

        $pdf->Output('historial_actividad_' . date('Ymd_His') . '.pdf', 'D');
        exit;
    }
}
?>
