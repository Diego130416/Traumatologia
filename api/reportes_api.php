<?php
// --- api/reportes_api.php (COMPLETO: PAGOS Y RECETAS) ---

// Habilitamos errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'conexion.php'; 

// --- SEGURIDAD ---
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'medico') {
    http_response_code(403);
    header('Content-Type: application/json'); 
    die(json_encode(['success' => false, 'error' => 'Acceso no autorizado.']));
}

$method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'error' => 'Acción no válida.'];

try {
    
    if ($method === 'GET') {
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        // ---------------------------------------------------------
        // 1. REPORTE DE PAGOS
        // ---------------------------------------------------------
        if ($action === 'get_payments') {
            
            $payments = [];
            
            // Usamos 'fecha_registro' que es el nombre correcto en tu BD
            $sql = "SELECT 
                        p.nombre_completo, 
                        h.datos, 
                        h.fecha_registro 
                    FROM historial_clinico h
                    JOIN pacientes p ON h.id_paciente = p.id
                    WHERE h.tipo_registro = 'Registro de Pago'
                    ORDER BY h.fecha_registro DESC";

            $result = $conn->query($sql);

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $datos_pago = json_decode($row['datos'], true);
                    
                    $payments[] = [
                        'paciente' => $row['nombre_completo'],
                        'fecha' => $row['fecha_registro'], 
                        'concepto' => isset($datos_pago['concept']) ? $datos_pago['concept'] : 'N/A',
                        'monto' => isset($datos_pago['amount']) ? $datos_pago['amount'] : 0,
                        'metodo' => isset($datos_pago['method']) ? $datos_pago['method'] : 'N/A',
                        'facturado' => isset($datos_pago['facturado']) ? $datos_pago['facturado'] : false,
                        'facturaEmitida' => isset($datos_pago['facturaEmitida']) ? $datos_pago['facturaEmitida'] : false
                    ];
                }
                $response = ['success' => true, 'payments' => $payments];
            } else {
                throw new Exception("Error al generar reporte de pagos: " . $conn->error);
            }

        // ---------------------------------------------------------
        // 2. REPORTE DE RECETAS (NUEVO)
        // ---------------------------------------------------------
        } elseif ($action === 'get_prescriptions') {
            
            $prescriptions = [];
            
            // Buscamos 'Receta emitida' y ordenamos por fecha
            $sql = "SELECT 
                        p.nombre_completo, 
                        h.datos, 
                        h.fecha_registro 
                    FROM historial_clinico h
                    JOIN pacientes p ON h.id_paciente = p.id
                    WHERE h.tipo_registro = 'Receta emitida'
                    ORDER BY h.fecha_registro DESC";

            $result = $conn->query($sql);

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $datos_receta = json_decode($row['datos'], true);
                    
                    $prescriptions[] = [
                        'paciente' => $row['nombre_completo'],
                        'fecha' => $row['fecha_registro'],
                        // Extraemos la lista de medicamentos y recomendaciones del JSON
                        'medications' => isset($datos_receta['medications']) ? $datos_receta['medications'] : [],
                        'recommendations' => isset($datos_receta['recommendations']) ? $datos_receta['recommendations'] : ''
                    ];
                }
                $response = ['success' => true, 'prescriptions' => $prescriptions];
            } else {
                throw new Exception("Error al generar reporte de recetas: " . $conn->error);
            }

        } else {
            throw new Exception('Acción GET no válida.');
        }
        
    } else {
        throw new Exception('Método no permitido.');
    }

} catch (Exception $e) {
    http_response_code(400); 
    $response['error'] = $e->getMessage();
}

$conn->close();

header('Content-Type: application/json');
echo json_encode($response);
?>