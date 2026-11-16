<?php

require_once __DIR__ . '/Database.php';

class CrudService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Devuelve la lista de registros para una tabla específica con JOINs descriptivos
     */
    public function getAll(string $table): array {
        switch ($table) {
            case 'clientes':
                $stmt = $this->db->query("SELECT id_cliente, cedula, nombre, telefono, email, fecha_registro FROM clientes ORDER BY id_cliente DESC");
                return $stmt->fetchAll();

            case 'barberos':
                $stmt = $this->db->query("SELECT * FROM barberos ORDER BY id_barbero ASC");
                return $stmt->fetchAll();

            case 'turnos_trabajo':
                $stmt = $this->db->query("SELECT * FROM turnos_trabajo ORDER BY hora_inicio ASC");
                return $stmt->fetchAll();

            case 'horarios_barbero':
                $sql = "SELECT h.*, 
                               b.nombre AS barbero_nombre,
                               t.nombre_turno, 
                               t.hora_inicio, 
                               t.hora_fin
                        FROM horarios_barbero h
                        JOIN barberos b ON h.id_barbero = b.id_barbero
                        JOIN turnos_trabajo t ON h.id_turno = t.id_turno
                        ORDER BY h.id_horario DESC";
                return $this->db->query($sql)->fetchAll();

            case 'servicios':
                $stmt = $this->db->query("SELECT * FROM servicios ORDER BY id_servicio ASC");
                return $stmt->fetchAll();

            case 'citas':
                $sql = "SELECT c.*, 
                               cl.cedula AS cliente_cedula,
                               cl.nombre AS cliente_nombre, 
                               cl.telefono AS cliente_telefono,
                               b.nombre AS barbero_nombre,
                               s.nombre AS servicio_nombre, 
                               s.precio AS servicio_precio
                        FROM citas c
                        JOIN clientes cl ON c.id_cliente = cl.id_cliente
                        JOIN barberos b ON c.id_barbero = b.id_barbero
                        JOIN servicios s ON c.id_servicio = s.id_servicio
                        ORDER BY c.fecha DESC, c.hora DESC";
                return $this->db->query($sql)->fetchAll();

            case 'pagos':
                $sql = "SELECT p.*, 
                               c.fecha AS cita_fecha, 
                               c.hora AS cita_hora,
                               cl.cedula AS cliente_cedula,
                               cl.nombre AS cliente_nombre,
                               b.nombre AS barbero_nombre,
                               s.nombre AS servicio_nombre
                        FROM pagos p
                        JOIN citas c ON p.id_cita = c.id_cita
                        JOIN clientes cl ON c.id_cliente = cl.id_cliente
                        JOIN barberos b ON c.id_barbero = b.id_barbero
                        JOIN servicios s ON c.id_servicio = s.id_servicio
                        ORDER BY p.id_pago DESC";
                return $this->db->query($sql)->fetchAll();

            case 'liquidaciones_barbero':
                $sql = "SELECT l.*, 
                               b.nombre AS barbero_nombre,
                               b.porcentaje_comision AS barbero_porcentaje
                        FROM liquidaciones_barbero l
                        JOIN barberos b ON l.id_barbero = b.id_barbero
                        ORDER BY l.id_liquidacion DESC";
                return $this->db->query($sql)->fetchAll();

            default:
                throw new InvalidArgumentException("Tabla no válida: {$table}");
        }
    }

    /**
     * Obtiene un registro por su ID
     */
    public function getById(string $table, int $id): ?array {
        $pk = $this->getPrimaryKey($table);
        $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE {$pk} = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Busca un cliente por su número de cédula o identificación
     */
    public function getClientByCedula(string $cedula): ?array {
        $cleanCedula = preg_replace('/\D/', '', $cedula);
        $stmt = $this->db->prepare("
            SELECT id_cliente, cedula, nombre, telefono, email, fecha_registro 
            FROM clientes 
            WHERE cedula = :cedula OR (LENGTH(:clean) >= 4 AND cedula = :clean)
            LIMIT 1
        ");
        $stmt->execute([':cedula' => trim($cedula), ':clean' => $cleanCedula]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene todas las citas de un cliente específico (para el portal de clientes)
     */
    public function getClientAppointments(int $idCliente): array {
        $stmt = $this->db->prepare("
            SELECT c.id_cita,
                   c.fecha,
                   c.hora,
                   c.estado,
                   c.creado_en,
                   b.nombre AS barbero_nombre,
                   s.nombre AS servicio_nombre,
                   s.precio AS servicio_precio,
                   s.duracion_minutos
            FROM citas c
            JOIN barberos b ON c.id_barbero = b.id_barbero
            JOIN servicios s ON c.id_servicio = s.id_servicio
            WHERE c.id_cliente = :id_cliente
            ORDER BY c.fecha DESC, c.hora DESC
        ");
        $stmt->execute([':id_cliente' => $idCliente]);
        return $stmt->fetchAll();
    }

    /**
     * Cancela una cita perteneciente a un cliente si está en estado 'Pendiente'
     */
    public function cancelClientAppointment(int $idCita, int $idCliente): bool {
        $stmt = $this->db->prepare("
            UPDATE citas 
            SET estado = 'Cancelada' 
            WHERE id_cita = :id_cita AND id_cliente = :id_cliente AND estado = 'Pendiente'
        ");
        $stmt->execute([
            ':id_cita' => $idCita,
            ':id_cliente' => $idCliente
        ]);
        if ($stmt->rowCount() === 0) {
            throw new InvalidArgumentException("La cita no pudo ser cancelada. Puede que no exista o ya haya sido procesada.");
        }
        return true;
    }

    /**
     * Crea un registro en la tabla especificada (todas las restricciones son validadas en PostgreSQL)
     */
    public function create(string $table, array $data): array {
        switch ($table) {
            case 'clientes':
                $this->validateRequired($data, ['cedula', 'nombre', 'telefono']);
                $stmt = $this->db->prepare("
                    INSERT INTO clientes (cedula, nombre, telefono, email)
                    VALUES (:cedula, :nombre, :telefono, :email)
                    RETURNING id_cliente
                ");
                $stmt->execute([
                    ':cedula' => trim($data['cedula']),
                    ':nombre' => trim($data['nombre']),
                    ':telefono' => trim($data['telefono']),
                    ':email' => !empty($data['email']) ? trim($data['email']) : null
                ]);
                $id = $stmt->fetchColumn();
                return $this->getById($table, (int)$id);

            case 'barberos':
                $this->validateRequired($data, ['nombre']);
                $stmt = $this->db->prepare("
                    INSERT INTO barberos (nombre, telefono, porcentaje_comision, estado)
                    VALUES (:nombre, :telefono, :porcentaje_comision, :estado)
                    RETURNING id_barbero
                ");
                $stmt->execute([
                    ':nombre' => trim($data['nombre']),
                    ':telefono' => !empty($data['telefono']) ? trim($data['telefono']) : null,
                    ':porcentaje_comision' => isset($data['porcentaje_comision']) && $data['porcentaje_comision'] !== '' ? (float)$data['porcentaje_comision'] : 50.00,
                    ':estado' => !empty($data['estado']) ? trim($data['estado']) : 'Activo'
                ]);
                $id = $stmt->fetchColumn();
                return $this->getById($table, (int)$id);

            case 'turnos_trabajo':
                $this->validateRequired($data, ['nombre_turno', 'hora_inicio', 'hora_fin']);
                $stmt = $this->db->prepare("
                    INSERT INTO turnos_trabajo (nombre_turno, hora_inicio, hora_fin)
                    VALUES (:nombre_turno, :hora_inicio, :hora_fin)
                    RETURNING id_turno
                ");
                $stmt->execute([
                    ':nombre_turno' => trim($data['nombre_turno']),
                    ':hora_inicio' => trim($data['hora_inicio']),
                    ':hora_fin' => trim($data['hora_fin'])
                ]);
                $id = $stmt->fetchColumn();
                return $this->getById($table, (int)$id);

            case 'horarios_barbero':
                $this->validateRequired($data, ['id_barbero', 'id_turno', 'dia_semana']);
                $stmt = $this->db->prepare("
                    INSERT INTO horarios_barbero (id_barbero, id_turno, dia_semana)
                    VALUES (:id_barbero, :id_turno, :dia_semana)
                    RETURNING id_horario
                ");
                $stmt->execute([
                    ':id_barbero' => (int)$data['id_barbero'],
                    ':id_turno' => (int)$data['id_turno'],
                    ':dia_semana' => trim($data['dia_semana'])
                ]);
                $id = $stmt->fetchColumn();
                return $this->getById($table, (int)$id);

            case 'servicios':
                $this->validateRequired($data, ['nombre', 'precio']);
                $stmt = $this->db->prepare("
                    INSERT INTO servicios (nombre, descripcion, precio, duracion_minutos, estado)
                    VALUES (:nombre, :descripcion, :precio, :duracion_minutos, :estado)
                    RETURNING id_servicio
                ");
                $stmt->execute([
                    ':nombre' => trim($data['nombre']),
                    ':descripcion' => !empty($data['descripcion']) ? trim($data['descripcion']) : null,
                    ':precio' => (float)$data['precio'],
                    ':duracion_minutos' => !empty($data['duracion_minutos']) ? (int)$data['duracion_minutos'] : 30,
                    ':estado' => !empty($data['estado']) ? trim($data['estado']) : 'Activo'
                ]);
                $id = $stmt->fetchColumn();
                return $this->getById($table, (int)$id);

            case 'citas':
                $this->validateRequired($data, ['id_cliente', 'id_barbero', 'id_servicio', 'fecha', 'hora']);
                $stmt = $this->db->prepare("
                    INSERT INTO citas (id_cliente, id_barbero, id_servicio, fecha, hora, estado)
                    VALUES (:id_cliente, :id_barbero, :id_servicio, :fecha, :hora, :estado)
                    RETURNING id_cita
                ");
                $stmt->execute([
                    ':id_cliente' => (int)$data['id_cliente'],
                    ':id_barbero' => (int)$data['id_barbero'],
                    ':id_servicio' => (int)$data['id_servicio'],
                    ':fecha' => trim($data['fecha']),
                    ':hora' => trim($data['hora']),
                    ':estado' => !empty($data['estado']) ? trim($data['estado']) : 'Pendiente'
                ]);
                $id = $stmt->fetchColumn();
                return $this->getById($table, (int)$id);

            case 'pagos':
                $this->validateRequired($data, ['id_cita', 'monto', 'metodo_pago']);
                $stmt = $this->db->prepare("
                    INSERT INTO pagos (id_cita, monto, metodo_pago, fecha_pago)
                    VALUES (:id_cita, :monto, :metodo_pago, :fecha_pago)
                    RETURNING id_pago
                ");
                $fechaPago = !empty($data['fecha_pago']) ? trim($data['fecha_pago']) : date('Y-m-d H:i:s');
                $stmt->execute([
                    ':id_cita' => (int)$data['id_cita'],
                    ':monto' => (float)$data['monto'],
                    ':metodo_pago' => trim($data['metodo_pago']),
                    ':fecha_pago' => $fechaPago
                ]);
                $id = $stmt->fetchColumn();
                return $this->getById($table, (int)$id);

            case 'liquidaciones_barbero':
                $this->validateRequired($data, ['id_barbero', 'fecha_corte', 'total_servicios', 'monto_comision']);
                $stmt = $this->db->prepare("
                    INSERT INTO liquidaciones_barbero (id_barbero, fecha_corte, total_servicios, monto_comision, estado_pago)
                    VALUES (:id_barbero, :fecha_corte, :total_servicios, :monto_comision, :estado_pago)
                    RETURNING id_liquidacion
                ");
                $stmt->execute([
                    ':id_barbero' => (int)$data['id_barbero'],
                    ':fecha_corte' => trim($data['fecha_corte']),
                    ':total_servicios' => (int)$data['total_servicios'],
                    ':monto_comision' => (float)$data['monto_comision'],
                    ':estado_pago' => !empty($data['estado_pago']) ? trim($data['estado_pago']) : 'Pendiente'
                ]);
                $id = $stmt->fetchColumn();
                return $this->getById($table, (int)$id);

            default:
                throw new InvalidArgumentException("Tabla no válida: {$table}");
        }
    }

    /**
     * Actualiza un registro existente
     */
    public function update(string $table, int $id, array $data): array {
        switch ($table) {
            case 'clientes':
                $this->validateRequired($data, ['cedula', 'nombre', 'telefono']);
                $stmt = $this->db->prepare("
                    UPDATE clientes 
                    SET cedula = :cedula,
                        nombre = :nombre, 
                        telefono = :telefono,
                        email = :email
                    WHERE id_cliente = :id
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':cedula' => trim($data['cedula']),
                    ':nombre' => trim($data['nombre']),
                    ':telefono' => trim($data['telefono']),
                    ':email' => !empty($data['email']) ? trim($data['email']) : null
                ]);
                break;

            case 'barberos':
                $this->validateRequired($data, ['nombre']);
                $stmt = $this->db->prepare("
                    UPDATE barberos 
                    SET nombre = :nombre, 
                        telefono = :telefono, 
                        porcentaje_comision = :porcentaje_comision, 
                        estado = :estado
                    WHERE id_barbero = :id
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':nombre' => trim($data['nombre']),
                    ':telefono' => !empty($data['telefono']) ? trim($data['telefono']) : null,
                    ':porcentaje_comision' => isset($data['porcentaje_comision']) ? (float)$data['porcentaje_comision'] : 50.00,
                    ':estado' => !empty($data['estado']) ? trim($data['estado']) : 'Activo'
                ]);
                break;

            case 'turnos_trabajo':
                $this->validateRequired($data, ['nombre_turno', 'hora_inicio', 'hora_fin']);
                $stmt = $this->db->prepare("
                    UPDATE turnos_trabajo 
                    SET nombre_turno = :nombre_turno, 
                        hora_inicio = :hora_inicio, 
                        hora_fin = :hora_fin
                    WHERE id_turno = :id
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':nombre_turno' => trim($data['nombre_turno']),
                    ':hora_inicio' => trim($data['hora_inicio']),
                    ':hora_fin' => trim($data['hora_fin'])
                ]);
                break;

            case 'horarios_barbero':
                $this->validateRequired($data, ['id_barbero', 'id_turno', 'dia_semana']);
                $stmt = $this->db->prepare("
                    UPDATE horarios_barbero 
                    SET id_barbero = :id_barbero, 
                        id_turno = :id_turno, 
                        dia_semana = :dia_semana
                    WHERE id_horario = :id
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':id_barbero' => (int)$data['id_barbero'],
                    ':id_turno' => (int)$data['id_turno'],
                    ':dia_semana' => trim($data['dia_semana'])
                ]);
                break;

            case 'servicios':
                $this->validateRequired($data, ['nombre', 'precio']);
                $stmt = $this->db->prepare("
                    UPDATE servicios 
                    SET nombre = :nombre, 
                        descripcion = :descripcion, 
                        precio = :precio, 
                        duracion_minutos = :duracion_minutos, 
                        estado = :estado
                    WHERE id_servicio = :id
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':nombre' => trim($data['nombre']),
                    ':descripcion' => !empty($data['descripcion']) ? trim($data['descripcion']) : null,
                    ':precio' => (float)$data['precio'],
                    ':duracion_minutos' => !empty($data['duracion_minutos']) ? (int)$data['duracion_minutos'] : 30,
                    ':estado' => !empty($data['estado']) ? trim($data['estado']) : 'Activo'
                ]);
                break;

            case 'citas':
                $this->validateRequired($data, ['id_cliente', 'id_barbero', 'id_servicio', 'fecha', 'hora']);
                $stmt = $this->db->prepare("
                    UPDATE citas 
                    SET id_cliente = :id_cliente, 
                        id_barbero = :id_barbero, 
                        id_servicio = :id_servicio, 
                        fecha = :fecha, 
                        hora = :hora, 
                        estado = :estado
                    WHERE id_cita = :id
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':id_cliente' => (int)$data['id_cliente'],
                    ':id_barbero' => (int)$data['id_barbero'],
                    ':id_servicio' => (int)$data['id_servicio'],
                    ':fecha' => trim($data['fecha']),
                    ':hora' => trim($data['hora']),
                    ':estado' => !empty($data['estado']) ? trim($data['estado']) : 'Pendiente'
                ]);
                break;

            case 'pagos':
                $this->validateRequired($data, ['id_cita', 'monto', 'metodo_pago']);
                $stmt = $this->db->prepare("
                    UPDATE pagos 
                    SET id_cita = :id_cita, 
                        monto = :monto, 
                        metodo_pago = :metodo_pago,
                        fecha_pago = :fecha_pago
                    WHERE id_pago = :id
                ");
                $fechaPago = !empty($data['fecha_pago']) ? trim($data['fecha_pago']) : date('Y-m-d H:i:s');
                $stmt->execute([
                    ':id' => $id,
                    ':id_cita' => (int)$data['id_cita'],
                    ':monto' => (float)$data['monto'],
                    ':metodo_pago' => trim($data['metodo_pago']),
                    ':fecha_pago' => $fechaPago
                ]);
                break;

            case 'liquidaciones_barbero':
                $this->validateRequired($data, ['id_barbero', 'fecha_corte', 'total_servicios', 'monto_comision']);
                $stmt = $this->db->prepare("
                    UPDATE liquidaciones_barbero 
                    SET id_barbero = :id_barbero, 
                        fecha_corte = :fecha_corte, 
                        total_servicios = :total_servicios, 
                        monto_comision = :monto_comision, 
                        estado_pago = :estado_pago
                    WHERE id_liquidacion = :id
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':id_barbero' => (int)$data['id_barbero'],
                    ':fecha_corte' => trim($data['fecha_corte']),
                    ':total_servicios' => (int)$data['total_servicios'],
                    ':monto_comision' => (float)$data['monto_comision'],
                    ':estado_pago' => !empty($data['estado_pago']) ? trim($data['estado_pago']) : 'Pendiente'
                ]);
                break;

            default:
                throw new InvalidArgumentException("Tabla no válida: {$table}");
        }

        return $this->getById($table, $id);
    }

    /**
     * Elimina un registro por ID
     */
    public function delete(string $table, int $id): bool {
        $pk = $this->getPrimaryKey($table);
        $stmt = $this->db->prepare("DELETE FROM {$table} WHERE {$pk} = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Obtiene listas de opciones para los select de llaves foráneas y catálogos
     */
    public function getOptions(): array {
        return [
            'clientes' => $this->db->query("SELECT id_cliente, cedula, nombre, telefono, email FROM clientes ORDER BY nombre ASC")->fetchAll(),
            'barberos' => $this->db->query("SELECT id_barbero, nombre, telefono, estado, porcentaje_comision FROM barberos ORDER BY nombre ASC")->fetchAll(),
            'turnos' => $this->db->query("SELECT id_turno, nombre_turno, hora_inicio, hora_fin FROM turnos_trabajo ORDER BY hora_inicio ASC")->fetchAll(),
            'servicios' => $this->db->query("SELECT id_servicio, nombre, descripcion, precio, duracion_minutos, estado FROM servicios ORDER BY nombre ASC")->fetchAll(),
            'citas' => $this->db->query("
                SELECT c.id_cita, c.fecha, c.hora, c.estado,
                       cl.cedula AS cliente_cedula,
                       cl.nombre AS cliente_nombre,
                       b.nombre AS barbero_nombre,
                       s.nombre AS servicio_nombre,
                       s.precio AS servicio_precio,
                       (SELECT id_pago FROM pagos WHERE pagos.id_cita = c.id_cita LIMIT 1) AS id_pago_existente
                FROM citas c
                JOIN clientes cl ON c.id_cliente = cl.id_cliente
                JOIN barberos b ON c.id_barbero = b.id_barbero
                JOIN servicios s ON c.id_servicio = s.id_servicio
                ORDER BY c.fecha DESC, c.hora DESC
            ")->fetchAll()
        ];
    }

    /**
     * Resumen estadístico del sistema para el Dashboard
     */
    public function getStats(): array {
        $totalClientes = (int)$this->db->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
        $totalBarberos = (int)$this->db->query("SELECT COUNT(*) FROM barberos WHERE estado = 'Activo'")->fetchColumn();
        $totalServicios = (int)$this->db->query("SELECT COUNT(*) FROM servicios WHERE estado = 'Activo'")->fetchColumn();
        $totalCitas = (int)$this->db->query("SELECT COUNT(*) FROM citas")->fetchColumn();
        $citasPendientes = (int)$this->db->query("SELECT COUNT(*) FROM citas WHERE estado = 'Pendiente'")->fetchColumn();
        $totalIngresos = (float)$this->db->query("SELECT COALESCE(SUM(monto), 0) FROM pagos")->fetchColumn();
        $liquidacionesPendientes = (int)$this->db->query("SELECT COUNT(*) FROM liquidaciones_barbero WHERE estado_pago = 'Pendiente'")->fetchColumn();

        return [
            'total_clientes' => $totalClientes,
            'barberos_activos' => $totalBarberos,
            'servicios_activos' => $totalServicios,
            'total_citas' => $totalCitas,
            'citas_pendientes' => $citasPendientes,
            'total_ingresos' => $totalIngresos,
            'liquidaciones_pendientes' => $liquidacionesPendientes
        ];
    }

    /**
     * Obtiene métricas analíticas avanzadas y agregaciones para las gráficas del Administrador
     */
    public function getAnalyticsData(): array {
        // 1. Ingresos por fecha (últimos 14 días con pagos registrados)
        $stmtRevenue = $this->db->query("
            SELECT TO_CHAR(fecha_pago, 'YYYY-MM-DD') AS fecha,
                   SUM(monto) AS total,
                   COUNT(*) AS transacciones
            FROM pagos
            GROUP BY TO_CHAR(fecha_pago, 'YYYY-MM-DD')
            ORDER BY fecha ASC
            LIMIT 14
        ");
        $revenueByDate = $stmtRevenue->fetchAll();

        // 2. Rendimiento por Barbero (citas totales, completadas, canceladas, ingresos y comisiones)
        $stmtBarbers = $this->db->query("
            SELECT b.id_barbero,
                   b.nombre,
                   b.porcentaje_comision,
                   b.estado,
                   COUNT(c.id_cita) AS total_citas,
                   COUNT(CASE WHEN c.estado = 'Completada' THEN 1 END) AS completadas,
                   COUNT(CASE WHEN c.estado = 'Pendiente' THEN 1 END) AS pendientes,
                   COUNT(CASE WHEN c.estado = 'Cancelada' THEN 1 END) AS canceladas,
                   COALESCE(SUM(p.monto), 0) AS total_ingresos,
                   COALESCE(SUM(p.monto * (b.porcentaje_comision / 100.0)), 0) AS comisiones_generadas
            FROM barberos b
            LEFT JOIN citas c ON b.id_barbero = c.id_barbero
            LEFT JOIN pagos p ON c.id_cita = p.id_cita
            GROUP BY b.id_barbero, b.nombre, b.porcentaje_comision, b.estado
            ORDER BY total_ingresos DESC, total_citas DESC
        ");
        $barberPerformance = $stmtBarbers->fetchAll();

        // 3. Servicios más solicitados y recaudación por servicio
        $stmtServices = $this->db->query("
            SELECT s.id_servicio,
                   s.nombre,
                   s.precio,
                   COUNT(c.id_cita) AS total_citas,
                   COALESCE(SUM(p.monto), 0) AS ingresos_reales
            FROM servicios s
            LEFT JOIN citas c ON s.id_servicio = c.id_servicio
            LEFT JOIN pagos p ON c.id_cita = p.id_cita
            GROUP BY s.id_servicio, s.nombre, s.precio
            ORDER BY total_citas DESC, ingresos_reales DESC
        ");
        $popularServices = $stmtServices->fetchAll();

        // 4. Distribución de Citas por Estado
        $stmtStatus = $this->db->query("
            SELECT estado, COUNT(*) AS total
            FROM citas
            GROUP BY estado
            ORDER BY total DESC
        ");
        $appointmentsByStatus = $stmtStatus->fetchAll();

        // 5. Métodos de Pago más utilizados
        $stmtPaymentMethods = $this->db->query("
            SELECT metodo_pago, 
                   COUNT(*) AS cantidad, 
                   COALESCE(SUM(monto), 0) AS total
            FROM pagos
            GROUP BY metodo_pago
            ORDER BY total DESC
        ");
        $paymentMethods = $stmtPaymentMethods->fetchAll();

        // 6. Demanda por Horario (Horas pico de atención)
        $stmtHours = $this->db->query("
            SELECT TO_CHAR(hora, 'HH24:MI') AS franja,
                   COUNT(*) AS total
            FROM citas
            GROUP BY TO_CHAR(hora, 'HH24:MI')
            ORDER BY franja ASC
        ");
        $appointmentsByHour = $stmtHours->fetchAll();

        // 7. KPIs consolidados
        $totalIngresos = (float)$this->db->query("SELECT COALESCE(SUM(monto), 0) FROM pagos")->fetchColumn();
        $totalCitas = (int)$this->db->query("SELECT COUNT(*) FROM citas")->fetchColumn();
        $citasCompletadas = (int)$this->db->query("SELECT COUNT(*) FROM citas WHERE estado = 'Completada'")->fetchColumn();
        $citasCanceladas = (int)$this->db->query("SELECT COUNT(*) FROM citas WHERE estado = 'Cancelada'")->fetchColumn();
        $ticketPromedio = (float)$this->db->query("SELECT COALESCE(AVG(monto), 0) FROM pagos")->fetchColumn();

        $tasaEfectividad = $totalCitas > 0 ? round(($citasCompletadas / $totalCitas) * 100, 1) : 0;
        $topBarbero = !empty($barberPerformance) && isset($barberPerformance[0]['nombre']) ? $barberPerformance[0]['nombre'] : 'Por determinar';
        $topServicio = !empty($popularServices) && isset($popularServices[0]['nombre']) ? $popularServices[0]['nombre'] : 'Por determinar';

        return [
            'kpis' => [
                'total_ingresos' => $totalIngresos,
                'total_citas' => $totalCitas,
                'citas_completadas' => $citasCompletadas,
                'citas_canceladas' => $citasCanceladas,
                'ticket_promedio' => round($ticketPromedio, 2),
                'tasa_efectividad' => $tasaEfectividad,
                'top_barbero' => $topBarbero,
                'top_servicio' => $topServicio
            ],
            'revenue_by_date' => $revenueByDate,
            'barber_performance' => $barberPerformance,
            'popular_services' => $popularServices,
            'appointments_by_status' => $appointmentsByStatus,
            'payment_methods' => $paymentMethods,
            'appointments_by_hour' => $appointmentsByHour
        ];
    }

    /**
     * Obtiene el listado de citas de un barbero específico (para la Vista de Barbero)
     */
    public function getBarberAppointments(int $idBarbero): array {
        $stmt = $this->db->prepare("
            SELECT c.id_cita,
                   c.fecha,
                   c.hora,
                   c.estado AS cita_estado,
                   c.creado_en,
                   cl.id_cliente,
                   cl.nombre AS cliente_nombre,
                   cl.telefono AS cliente_telefono,
                   cl.cedula AS cliente_cedula,
                   s.id_servicio,
                   s.nombre AS servicio_nombre,
                   s.precio AS servicio_precio,
                   s.duracion_minutos,
                   p.id_pago,
                   p.monto AS pago_monto,
                   p.metodo_pago,
                   p.fecha_pago
            FROM citas c
            JOIN clientes cl ON c.id_cliente = cl.id_cliente
            JOIN servicios s ON c.id_servicio = s.id_servicio
            LEFT JOIN pagos p ON c.id_cita = p.id_cita
            WHERE c.id_barbero = :id_barbero
            ORDER BY c.fecha DESC, c.hora ASC
        ");
        $stmt->execute([':id_barbero' => $idBarbero]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene el horario y turnos asignados a un barbero
     */
    public function getBarberSchedule(int $idBarbero): array {
        $stmt = $this->db->prepare("
            SELECT h.id_horario,
                   h.dia_semana,
                   t.nombre_turno,
                   t.hora_inicio,
                   t.hora_fin
            FROM horarios_barbero h
            JOIN turnos_trabajo t ON h.id_turno = t.id_turno
            WHERE h.id_barbero = :id_barbero
            ORDER BY 
                CASE h.dia_semana 
                    WHEN 'Lunes' THEN 1 
                    WHEN 'Martes' THEN 2 
                    WHEN 'Miércoles' THEN 3 
                    WHEN 'Jueves' THEN 4 
                    WHEN 'Viernes' THEN 5 
                    WHEN 'Sábado' THEN 6 
                    WHEN 'Domingo' THEN 7 
                    ELSE 8
                END ASC, t.hora_inicio ASC
        ");
        $stmt->execute([':id_barbero' => $idBarbero]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene información financiera, comisiones acumuladas y liquidaciones de un barbero
     */
    public function getBarberFinancials(int $idBarbero): array {
        $barbero = $this->getById('barberos', $idBarbero);
        if (!$barbero) {
            throw new InvalidArgumentException("Barbero no encontrado con ID {$idBarbero}.");
        }

        $comisionPct = (float)$barbero['porcentaje_comision'];

        // Citas completadas con pago registrado
        $stmtPagos = $this->db->prepare("
            SELECT COALESCE(SUM(p.monto), 0) AS total_recaudado,
                   COUNT(p.id_pago) AS servicios_pagados
            FROM citas c
            JOIN pagos p ON c.id_cita = p.id_cita
            WHERE c.id_barbero = :id_barbero AND c.estado = 'Completada'
        ");
        $stmtPagos->execute([':id_barbero' => $idBarbero]);
        $resPagos = $stmtPagos->fetch();

        $totalRecaudado = (float)($resPagos['total_recaudado'] ?? 0);
        $serviciosPagados = (int)($resPagos['servicios_pagados'] ?? 0);
        $comisionGanada = round($totalRecaudado * ($comisionPct / 100.0), 2);

        // Liquidaciones del barbero
        $stmtLiq = $this->db->prepare("
            SELECT id_liquidacion, fecha_corte, total_servicios, monto_comision, estado_pago
            FROM liquidaciones_barbero
            WHERE id_barbero = :id_barbero
            ORDER BY fecha_corte DESC, id_liquidacion DESC
        ");
        $stmtLiq->execute([':id_barbero' => $idBarbero]);
        $liquidaciones = $stmtLiq->fetchAll();

        $comisionLiquidada = 0.0;
        foreach ($liquidaciones as $liq) {
            if ($liq['estado_pago'] === 'Pagado') {
                $comisionLiquidada += (float)$liq['monto_comision'];
            }
        }

        // Citas del día de hoy
        $hoy = date('Y-m-d');
        $stmtHoy = $this->db->prepare("
            SELECT COUNT(*) FROM citas 
            WHERE id_barbero = :id_barbero AND fecha = :hoy AND estado != 'Cancelada'
        ");
        $stmtHoy->execute([':id_barbero' => $idBarbero, ':hoy' => $hoy]);
        $citasHoy = (int)$stmtHoy->fetchColumn();

        // Citas pendientes de atención o confirmación
        $stmtPend = $this->db->prepare("
            SELECT COUNT(*) FROM citas 
            WHERE id_barbero = :id_barbero AND estado = 'Pendiente'
        ");
        $stmtPend->execute([':id_barbero' => $idBarbero]);
        $citasPendientes = (int)$stmtPend->fetchColumn();

        // Citas confirmadas
        $stmtConf = $this->db->prepare("
            SELECT COUNT(*) FROM citas 
            WHERE id_barbero = :id_barbero AND estado = 'Confirmada'
        ");
        $stmtConf->execute([':id_barbero' => $idBarbero]);
        $citasConfirmadas = (int)$stmtConf->fetchColumn();

        return [
            'barbero' => $barbero,
            'citas_hoy' => $citasHoy,
            'citas_pendientes' => $citasPendientes,
            'citas_confirmadas' => $citasConfirmadas,
            'servicios_completados' => $serviciosPagados,
            'total_recaudado' => $totalRecaudado,
            'comision_porcentaje' => $comisionPct,
            'comision_ganada_total' => $comisionGanada,
            'comision_liquidada' => round($comisionLiquidada, 2),
            'comision_por_liquidar' => max(0.0, round($comisionGanada - $comisionLiquidada, 2)),
            'liquidaciones' => $liquidaciones
        ];
    }

    /**
     * Permite al barbero cambiar el estado de su cita (Confirmada, Cancelada, etc.)
     */
    public function updateBarberAppointmentStatus(int $idCita, int $idBarbero, string $nuevoEstado): bool {
        $allowed = ['Pendiente', 'Confirmada', 'Completada', 'Cancelada'];
        if (!in_array($nuevoEstado, $allowed, true)) {
            throw new InvalidArgumentException("Estado de cita no válido.");
        }

        $stmt = $this->db->prepare("
            UPDATE citas 
            SET estado = :nuevo_estado
            WHERE id_cita = :id_cita AND id_barbero = :id_barbero
        ");
        $stmt->execute([
            ':nuevo_estado' => $nuevoEstado,
            ':id_cita' => $idCita,
            ':id_barbero' => $idBarbero
        ]);

        if ($stmt->rowCount() === 0) {
            throw new InvalidArgumentException("No se encontró la cita o no pertenece al barbero indicado.");
        }
        return true;
    }

    /**
     * Permite al barbero registrar el pago de un servicio atendido
     */
    public function registerBarberPayment(int $idCita, int $idBarbero, float $monto, string $metodoPago): array {
        $stmtCita = $this->db->prepare("SELECT * FROM citas WHERE id_cita = :id_cita AND id_barbero = :id_barbero");
        $stmtCita->execute([':id_cita' => $idCita, ':id_barbero' => $idBarbero]);
        $cita = $stmtCita->fetch();

        if (!$cita) {
            throw new InvalidArgumentException("La cita no pertenece a este barbero o no existe.");
        }

        if ($cita['estado'] === 'Cancelada') {
            throw new InvalidArgumentException("No es posible registrar un pago para una cita cancelada.");
        }

        // Crear pago (el trigger trg_actualizar_cita_al_pagar cambiará el estado a 'Completada' automáticamente)
        return $this->create('pagos', [
            'id_cita' => $idCita,
            'monto' => $monto,
            'metodo_pago' => $metodoPago,
            'fecha_pago' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Población de datos de prueba opcional ejecutada a través del CRUD (nunca en SQL)
     */
    public function seedDemoData(): array {
        $statsBefore = $this->getStats();

        // Si una ejecución previa falló a la mitad (se crearon los barberos de demo pero falló en clientes por la cédula),
        // limpiamos las tablas para garantizar una inserción limpia y completa.
        if ($statsBefore['total_clientes'] === 0 && $statsBefore['total_citas'] === 0 && $statsBefore['barberos_activos'] > 0) {
            $demoCheck = (int)$this->db->query("SELECT count(*) FROM barberos WHERE nombre = 'Alejandro Morales'")->fetchColumn();
            if ($demoCheck > 0) {
                $this->db->exec("TRUNCATE TABLE liquidaciones_barbero, pagos, citas, horarios_barbero, turnos_trabajo, servicios, barberos, clientes RESTART IDENTITY CASCADE;");
                $statsBefore = $this->getStats();
            }
        }

        if ($statsBefore['total_clientes'] > 0 || $statsBefore['barberos_activos'] > 0) {
            return ['message' => 'La base de datos ya contiene registros.', 'seeded' => false];
        }

        $this->db->beginTransaction();
        try {
            // 1. Barberos
            $b1 = $this->create('barberos', ['nombre' => 'Alejandro Morales', 'telefono' => '+58 412 8765432', 'porcentaje_comision' => 50, 'estado' => 'Activo']);
            $b2 = $this->create('barberos', ['nombre' => 'Carlos Mendoza', 'telefono' => '+58 414 1234567', 'porcentaje_comision' => 45, 'estado' => 'Activo']);
            $b3 = $this->create('barberos', ['nombre' => 'David Navarro', 'telefono' => '+58 424 9988776', 'porcentaje_comision' => 50, 'estado' => 'Activo']);

            // 2. Turnos de trabajo
            $t1 = $this->create('turnos_trabajo', ['nombre_turno' => 'Turno Mañana', 'hora_inicio' => '08:00:00', 'hora_fin' => '13:00:00']);
            $t2 = $this->create('turnos_trabajo', ['nombre_turno' => 'Turno Tarde', 'hora_inicio' => '13:00:00', 'hora_fin' => '19:00:00']);
            $t3 = $this->create('turnos_trabajo', ['nombre_turno' => 'Jornada Completa', 'hora_inicio' => '09:00:00', 'hora_fin' => '18:00:00']);

            // 3. Horarios
            $this->create('horarios_barbero', ['id_barbero' => $b1['id_barbero'], 'id_turno' => $t1['id_turno'], 'dia_semana' => 'Lunes']);
            $this->create('horarios_barbero', ['id_barbero' => $b1['id_barbero'], 'id_turno' => $t1['id_turno'], 'dia_semana' => 'Miércoles']);
            $this->create('horarios_barbero', ['id_barbero' => $b2['id_barbero'], 'id_turno' => $t2['id_turno'], 'dia_semana' => 'Martes']);
            $this->create('horarios_barbero', ['id_barbero' => $b2['id_barbero'], 'id_turno' => $t2['id_turno'], 'dia_semana' => 'Jueves']);
            $this->create('horarios_barbero', ['id_barbero' => $b3['id_barbero'], 'id_turno' => $t3['id_turno'], 'dia_semana' => 'Sábado']);

            // 4. Servicios
            $s1 = $this->create('servicios', ['nombre' => 'Corte Clásico & Degradado', 'descripcion' => 'Corte con tijera/máquina, perfilado y lavado premium', 'precio' => 15.00, 'duracion_minutos' => 40, 'estado' => 'Activo']);
            $s2 = $this->create('servicios', ['nombre' => 'Arreglo de Barba Tradicional', 'descripcion' => 'Recorte, toalla caliente y bálsamo hidratante', 'precio' => 10.00, 'duracion_minutos' => 30, 'estado' => 'Activo']);
            $s3 = $this->create('servicios', ['nombre' => 'Combo Ejecutivo (Corte + Barba)', 'descripcion' => 'Experiencia completa de corte y toalla caliente para barba', 'precio' => 22.00, 'duracion_minutos' => 60, 'estado' => 'Activo']);
            $s4 = $this->create('servicios', ['nombre' => 'Tratamiento Exfoliante Facial', 'descripcion' => 'Limpieza facial profunda y mascarilla de carbón activado', 'precio' => 18.00, 'duracion_minutos' => 35, 'estado' => 'Activo']);
            $s5 = $this->create('servicios', ['nombre' => 'Perfilado de Cejas y Barba', 'descripcion' => 'Delineado preciso con navaja japonesa y loción refrescante', 'precio' => 8.00, 'duracion_minutos' => 20, 'estado' => 'Activo']);

            // 5. Clientes (con Cédula única numérica y Correo)
            $c1 = $this->create('clientes', ['cedula' => '20123456', 'nombre' => 'Gabriel Torres', 'telefono' => '+58 412 1112233', 'email' => 'gabriel.torres@gmail.com']);
            $c2 = $this->create('clientes', ['cedula' => '24555888', 'nombre' => 'Fernando Rivas', 'telefono' => '+58 414 4445566', 'email' => 'f.rivas@hotmail.com']);
            $c3 = $this->create('clientes', ['cedula' => '28999111', 'nombre' => 'Leonardo Silva', 'telefono' => '+58 424 7778899', 'email' => 'leo.silva@outlook.com']);
            $c4 = $this->create('clientes', ['cedula' => '19444333', 'nombre' => 'Andrés Gutiérrez', 'telefono' => '+58 412 5556677', 'email' => 'andres.g@gmail.com']);
            $c5 = $this->create('clientes', ['cedula' => '23111222', 'nombre' => 'Miguel Delgado', 'telefono' => '+58 416 8889900', 'email' => 'miguel.d@gmail.com']);
            $c6 = $this->create('clientes', ['cedula' => '26777888', 'nombre' => 'Javier Castillo', 'telefono' => '+58 424 3332211', 'email' => 'javier.c@yahoo.com']);

            // Fechas relativas para alimentar métricas y gráficas
            $ayer = date('Y-m-d', strtotime('-1 day'));
            $antier = date('Y-m-d', strtotime('-2 days'));
            $hace3Dias = date('Y-m-d', strtotime('-3 days'));
            $hoy = date('Y-m-d');
            $manana = date('Y-m-d', strtotime('+1 day'));

            // 6. Citas históricas y actuales
            // Antier:
            $cita1 = $this->create('citas', ['id_cliente' => $c1['id_cliente'], 'id_barbero' => $b1['id_barbero'], 'id_servicio' => $s3['id_servicio'], 'fecha' => $hace3Dias, 'hora' => '10:00:00', 'estado' => 'Completada']);
            $cita2 = $this->create('citas', ['id_cliente' => $c2['id_cliente'], 'id_barbero' => $b2['id_barbero'], 'id_servicio' => $s1['id_servicio'], 'fecha' => $antier, 'hora' => '11:00:00', 'estado' => 'Completada']);
            $cita3 = $this->create('citas', ['id_cliente' => $c4['id_cliente'], 'id_barbero' => $b3['id_barbero'], 'id_servicio' => $s4['id_servicio'], 'fecha' => $antier, 'hora' => '15:00:00', 'estado' => 'Completada']);
            
            // Ayer:
            $cita4 = $this->create('citas', ['id_cliente' => $c3['id_cliente'], 'id_barbero' => $b1['id_barbero'], 'id_servicio' => $s1['id_servicio'], 'fecha' => $ayer, 'hora' => '09:00:00', 'estado' => 'Completada']);
            $cita5 = $this->create('citas', ['id_cliente' => $c5['id_cliente'], 'id_barbero' => $b2['id_barbero'], 'id_servicio' => $s2['id_servicio'], 'fecha' => $ayer, 'hora' => '14:00:00', 'estado' => 'Completada']);
            $cita6 = $this->create('citas', ['id_cliente' => $c6['id_cliente'], 'id_barbero' => $b3['id_barbero'], 'id_servicio' => $s3['id_servicio'], 'fecha' => $ayer, 'hora' => '16:00:00', 'estado' => 'Cancelada']);

            // Hoy:
            $cita7 = $this->create('citas', ['id_cliente' => $c1['id_cliente'], 'id_barbero' => $b1['id_barbero'], 'id_servicio' => $s1['id_servicio'], 'fecha' => $hoy, 'hora' => '10:00:00', 'estado' => 'Completada']);
            $cita8 = $this->create('citas', ['id_cliente' => $c2['id_cliente'], 'id_barbero' => $b2['id_barbero'], 'id_servicio' => $s3['id_servicio'], 'fecha' => $hoy, 'hora' => '11:00:00', 'estado' => 'Confirmada']);
            $cita9 = $this->create('citas', ['id_cliente' => $c3['id_cliente'], 'id_barbero' => $b3['id_barbero'], 'id_servicio' => $s2['id_servicio'], 'fecha' => $hoy, 'hora' => '14:00:00', 'estado' => 'Pendiente']);
            $cita10 = $this->create('citas', ['id_cliente' => $c4['id_cliente'], 'id_barbero' => $b1['id_barbero'], 'id_servicio' => $s5['id_servicio'], 'fecha' => $hoy, 'hora' => '16:00:00', 'estado' => 'Pendiente']);

            // Mañana:
            $cita11 = $this->create('citas', ['id_cliente' => $c5['id_cliente'], 'id_barbero' => $b2['id_barbero'], 'id_servicio' => $s1['id_servicio'], 'fecha' => $manana, 'hora' => '10:00:00', 'estado' => 'Pendiente']);
            $cita12 = $this->create('citas', ['id_cliente' => $c6['id_cliente'], 'id_barbero' => $b3['id_barbero'], 'id_servicio' => $s3['id_servicio'], 'fecha' => $manana, 'hora' => '15:00:00', 'estado' => 'Confirmada']);

            // 7. Pagos para las citas completadas
            $this->create('pagos', ['id_cita' => $cita1['id_cita'], 'monto' => 22.00, 'metodo_pago' => 'Transferencia', 'fecha_pago' => $hace3Dias . ' 11:05:00']);
            $this->create('pagos', ['id_cita' => $cita2['id_cita'], 'monto' => 15.00, 'metodo_pago' => 'Pago Móvil', 'fecha_pago' => $antier . ' 11:50:00']);
            $this->create('pagos', ['id_cita' => $cita3['id_cita'], 'monto' => 18.00, 'metodo_pago' => 'Efectivo', 'fecha_pago' => $antier . ' 15:45:00']);
            $this->create('pagos', ['id_cita' => $cita4['id_cita'], 'monto' => 15.00, 'metodo_pago' => 'Punto de Venta', 'fecha_pago' => $ayer . ' 09:48:00']);
            $this->create('pagos', ['id_cita' => $cita5['id_cita'], 'monto' => 10.00, 'metodo_pago' => 'Efectivo', 'fecha_pago' => $ayer . ' 14:35:00']);
            $this->create('pagos', ['id_cita' => $cita7['id_cita'], 'monto' => 15.00, 'metodo_pago' => 'Pago Móvil', 'fecha_pago' => $hoy . ' 10:45:00']);

            // 8. Liquidaciones registradas para los barberos
            $this->create('liquidaciones_barbero', ['id_barbero' => $b1['id_barbero'], 'fecha_corte' => $ayer, 'total_servicios' => 2, 'monto_comision' => 18.50, 'estado_pago' => 'Pagado']);
            $this->create('liquidaciones_barbero', ['id_barbero' => $b2['id_barbero'], 'fecha_corte' => $ayer, 'total_servicios' => 2, 'monto_comision' => 11.25, 'estado_pago' => 'Pendiente']);
            $this->create('liquidaciones_barbero', ['id_barbero' => $b3['id_barbero'], 'fecha_corte' => $ayer, 'total_servicios' => 1, 'monto_comision' => 9.00, 'estado_pago' => 'Pendiente']);

            $this->db->commit();
            return ['message' => 'Datos demo generados exitosamente a través del CRUD.', 'seeded' => true];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function getPrimaryKey(string $table): string {
        $keys = [
            'clientes' => 'id_cliente',
            'barberos' => 'id_barbero',
            'turnos_trabajo' => 'id_turno',
            'horarios_barbero' => 'id_horario',
            'servicios' => 'id_servicio',
            'citas' => 'id_cita',
            'pagos' => 'id_pago',
            'liquidaciones_barbero' => 'id_liquidacion'
        ];
        if (!isset($keys[$table])) {
            throw new InvalidArgumentException("Tabla no reconocida: {$table}");
        }
        return $keys[$table];
    }

    private function validateRequired(array $data, array $fields): void {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                throw new InvalidArgumentException("El campo '{$field}' es obligatorio.");
            }
        }
    }
}
