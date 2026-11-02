<?php
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../src/CrudService.php';

try {
    $crud = new CrudService();
    $method = $_SERVER['REQUEST_METHOD'];

    // Handle method override if sent via headers or query
    if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
        $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    }

    $resource = $_GET['resource'] ?? '';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $action = $_GET['action'] ?? '';

    // Action: Seed demo data via CRUD
    if ($action === 'seed_demo' && $method === 'POST') {
        $result = $crud->seedDemoData();
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'seeded' => $result['seeded']
        ]);
        exit;
    }

    // Action: Look up client by cedula (Client Portal)
    if ($action === 'find_client' && $method === 'GET') {
        $cedula = trim($_GET['cedula'] ?? '');
        if ($cedula === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Debes ingresar un número de cédula o identificación.']);
            exit;
        }
        $client = $crud->getClientByCedula($cedula);
        echo json_encode([
            'success' => true,
            'found' => $client !== null,
            'data' => $client
        ]);
        exit;
    }

    // Action: Get appointments of a specific client (Client Portal)
    if ($action === 'client_citas' && $method === 'GET') {
        $idCliente = isset($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0;
        if ($idCliente <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID de cliente inválido.']);
            exit;
        }
        $citas = $crud->getClientAppointments($idCliente);
        echo json_encode([
            'success' => true,
            'data' => $citas,
            'count' => count($citas)
        ]);
        exit;
    }

    // Action: Client cancels their own pending appointment (Client Portal)
    if ($action === 'client_cancel_cita' && ($method === 'POST' || $method === 'PATCH')) {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $idCita = isset($input['id_cita']) ? (int)$input['id_cita'] : (isset($_GET['id_cita']) ? (int)$_GET['id_cita'] : 0);
        $idCliente = isset($input['id_cliente']) ? (int)$input['id_cliente'] : (isset($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0);

        if ($idCita <= 0 || $idCliente <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Datos insuficientes para cancelar la cita.']);
            exit;
        }

        $crud->cancelClientAppointment($idCita, $idCliente);
        echo json_encode([
            'success' => true,
            'message' => 'Tu cita ha sido cancelada exitosamente.'
        ]);
        exit;
    }

    // Special resource: Options for Selects
    if ($resource === 'options' && $method === 'GET') {
        $options = $crud->getOptions();
        echo json_encode([
            'success' => true,
            'data' => $options
        ]);
        exit;
    }

    // Special resource: General Stats
    if ($resource === 'stats' && $method === 'GET') {
        $stats = $crud->getStats();
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        exit;
    }

    // Allowed tables for CRUD
    $allowedTables = [
        'clientes',
        'barberos',
        'turnos_trabajo',
        'horarios_barbero',
        'servicios',
        'citas',
        'pagos',
        'liquidaciones_barbero'
    ];

    if (!in_array($resource, $allowedTables, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "Recurso '{$resource}' no es válido."
        ]);
        exit;
    }

    // Process CRUD Request
    switch ($method) {
        case 'GET':
            if ($id !== null) {
                $item = $crud->getById($resource, $id);
                if (!$item) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Registro no encontrado.']);
                    exit;
                }
                echo json_encode(['success' => true, 'data' => $item]);
            } else {
                $items = $crud->getAll($resource);
                echo json_encode(['success' => true, 'data' => $items, 'count' => count($items)]);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $created = $crud->create($resource, $input);
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Registro creado exitosamente.',
                'data' => $created
            ]);
            break;

        case 'PUT':
        case 'PATCH':
            if ($id === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Se requiere el ID para actualizar.']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Datos de actualización inválidos o vacíos.']);
                exit;
            }
            $updated = $crud->update($resource, $id, $input);
            echo json_encode([
                'success' => true,
                'message' => 'Registro actualizado exitosamente.',
                'data' => $updated
            ]);
            break;

        case 'DELETE':
            if ($id === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Se requiere el ID para eliminar.']);
                exit;
            }
            $deleted = $crud->delete($resource, $id);
            echo json_encode([
                'success' => true,
                'message' => 'Registro eliminado exitosamente.'
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
            break;
    }

} catch (PDOException $e) {
    http_response_code(400);
    $errorCode = (string)$e->getCode();
    $rawMsg = $e->getMessage();
    $errorMessage = $rawMsg;

    // Academic handling of PostgreSQL database constraints
    if ($errorCode === '23505') {
        // Unique violation
        if (str_contains($rawMsg, 'uq_cliente_cedula') || str_contains($rawMsg, 'cedula')) {
            $errorMessage = "Restricción BD (UNIQUE): Ya existe un cliente registrado con esta identificación/cédula. Para evitar registros duplicados, debes ingresar con esta cédula existente.";
        } elseif (str_contains($rawMsg, 'uq_cita_barbero_fecha_hora')) {
            $errorMessage = "Restricción BD (Índice Único Anticolisión): El barbero seleccionado ya tiene una cita activa asignada para esa fecha y hora. Por favor elige otro horario.";
        } elseif (str_contains($rawMsg, 'uq_cita_cliente_fecha_hora')) {
            $errorMessage = "Restricción BD (Índice Único Anticolisión): Este cliente ya tiene una cita activa agendada para esa misma fecha y hora.";
        } elseif (str_contains($rawMsg, 'pagos') || str_contains($rawMsg, 'id_cita')) {
            $errorMessage = "Restricción BD (UNIQUE 1:1): Ya existe un pago registrado para esta cita.";
        } elseif (str_contains($rawMsg, 'uq_horario_barbero_dia_turno')) {
            $errorMessage = "Restricción BD (UNIQUE): Este barbero ya tiene asignado este turno en el mismo día.";
        } else {
            $errorMessage = "Restricción BD (UNIQUE): Se ha intentado ingresar un registro duplicado en campos que deben ser únicos.";
        }
    } elseif ($errorCode === '23514') {
        // Check violation
        if (str_contains($rawMsg, 'chk_cliente_cedula')) {
            $errorMessage = "Restricción BD (CHECK): La cédula o identificación debe ser exclusivamente numérica (solo números, entre 4 y 20 dígitos), sin letras ni prefijos como 'V-'.";
        } elseif (str_contains($rawMsg, 'chk_cliente_nombre')) {
            $errorMessage = "Restricción BD (CHECK): El nombre del cliente debe tener mínimo 2 caracteres.";
        } elseif (str_contains($rawMsg, 'chk_cliente_telefono')) {
            $errorMessage = "Restricción BD (CHECK): El número de teléfono debe tener mínimo 7 caracteres.";
        } elseif (str_contains($rawMsg, 'chk_barbero_nombre')) {
            $errorMessage = "Restricción BD (CHECK): El nombre del barbero debe tener mínimo 2 caracteres.";
        } elseif (str_contains($rawMsg, 'chk_barbero_comision')) {
            $errorMessage = "Restricción BD (CHECK): El porcentaje de comisión debe ubicarse entre 0.00% y 100.00%.";
        } elseif (str_contains($rawMsg, 'chk_barbero_estado')) {
            $errorMessage = "Restricción BD (CHECK): El estado del barbero solo puede ser 'Activo' o 'Inactivo'.";
        } elseif (str_contains($rawMsg, 'chk_turno_nombre')) {
            $errorMessage = "Restricción BD (CHECK): El nombre del turno debe tener mínimo 2 caracteres.";
        } elseif (str_contains($rawMsg, 'chk_turno_horas')) {
            $errorMessage = "Restricción BD (CHECK): La hora de salida (fin) del turno debe ser estrictamente posterior a la hora de entrada.";
        } elseif (str_contains($rawMsg, 'chk_horario_dia')) {
            $errorMessage = "Restricción BD (CHECK): El día de la semana debe ser un día válido (Lunes a Domingo).";
        } elseif (str_contains($rawMsg, 'chk_servicio_nombre')) {
            $errorMessage = "Restricción BD (CHECK): El nombre del servicio debe tener mínimo 2 caracteres.";
        } elseif (str_contains($rawMsg, 'chk_servicio_precio')) {
            $errorMessage = "Restricción BD (CHECK): El precio del servicio debe ser estrictamente mayor a 0.";
        } elseif (str_contains($rawMsg, 'chk_servicio_duracion')) {
            $errorMessage = "Restricción BD (CHECK): La duración del servicio debe ser de entre 1 y 480 minutos.";
        } elseif (str_contains($rawMsg, 'chk_servicio_estado')) {
            $errorMessage = "Restricción BD (CHECK): El estado del servicio solo puede ser 'Activo' o 'Inactivo'.";
        } elseif (str_contains($rawMsg, 'chk_cita_estado')) {
            $errorMessage = "Restricción BD (CHECK): El estado de la cita solo admite: Pendiente, Confirmada, Completada o Cancelada.";
        } elseif (str_contains($rawMsg, 'chk_pago_monto')) {
            $errorMessage = "Restricción BD (CHECK): El monto a pagar debe ser mayor a 0.";
        } elseif (str_contains($rawMsg, 'chk_pago_metodo')) {
            $errorMessage = "Restricción BD (CHECK): El método de pago especificado no está autorizado en la base de datos.";
        } elseif (str_contains($rawMsg, 'chk_liq_servicios')) {
            $errorMessage = "Restricción BD (CHECK): El total de servicios de la liquidación no puede ser negativo.";
        } elseif (str_contains($rawMsg, 'chk_liq_monto')) {
            $errorMessage = "Restricción BD (CHECK): El monto de comisión de la liquidación no puede ser negativo.";
        } elseif (str_contains($rawMsg, 'chk_liq_estado')) {
            $errorMessage = "Restricción BD (CHECK): El estado de liquidación solo admite 'Pendiente' o 'Pagado'.";
        } else {
            $errorMessage = "Restricción BD (CHECK): No se superó la condición de validación requerida por el esquema de base de datos.";
        }
    } elseif ($errorCode === '23503') {
        // Foreign key violation
        $errorMessage = "Restricción BD (FOREIGN KEY): No se puede realizar la acción debido a la integridad referencial. Verifica que las claves foráneas existan o que el registro no tenga hijos vinculados.";
    } elseif ($errorCode === '23502') {
        // Not null violation
        $errorMessage = "Restricción BD (NOT NULL): Falta uno o varios campos obligatorios exigidos por la base de datos.";
    } elseif ($errorCode === 'P0001') {
        // Trigger / PL/pgSQL RAISE EXCEPTION
        // Strip out PostgreSQL wrapper text if present
        if (preg_match('/ERROR:\s*(.+?)(?:\n|$)/i', $rawMsg, $matches)) {
            $errorMessage = trim($matches[1]);
        }
    }

    echo json_encode([
        'success' => false,
        'error' => $errorMessage,
        'code' => $errorCode
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno en el servidor: ' . $e->getMessage()
    ]);
}
