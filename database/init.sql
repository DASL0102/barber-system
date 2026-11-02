
-- 1. Tabla: Clientes
CREATE TABLE IF NOT EXISTS clientes (
    id_cliente SERIAL PRIMARY KEY,
    cedula VARCHAR(20) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    email VARCHAR(120) NULL,
    fecha_registro TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_cliente_cedula UNIQUE (cedula),
    CONSTRAINT chk_cliente_cedula CHECK (cedula ~ '^[0-9]{4,20}$'),
    CONSTRAINT chk_cliente_nombre CHECK (length(trim(nombre)) >= 2),
    CONSTRAINT chk_cliente_telefono CHECK (length(trim(telefono)) >= 7),
    CONSTRAINT chk_cliente_email CHECK (email IS NULL OR length(trim(email)) >= 5)
);

-- 2. Tabla: Barberos
CREATE TABLE IF NOT EXISTS barberos (
    id_barbero SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NULL,
    porcentaje_comision NUMERIC(5,2) DEFAULT 50.00,
    estado VARCHAR(20) DEFAULT 'Activo',
    CONSTRAINT chk_barbero_nombre CHECK (length(trim(nombre)) >= 2),
    CONSTRAINT chk_barbero_telefono CHECK (telefono IS NULL OR length(trim(telefono)) >= 7),
    CONSTRAINT chk_barbero_comision CHECK (porcentaje_comision >= 0.00 AND porcentaje_comision <= 100.00),
    CONSTRAINT chk_barbero_estado CHECK (estado IN ('Activo', 'Inactivo'))
);

-- 3. Tabla: Turnos de trabajo
CREATE TABLE IF NOT EXISTS turnos_trabajo (
    id_turno SERIAL PRIMARY KEY,
    nombre_turno VARCHAR(50) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    CONSTRAINT chk_turno_nombre CHECK (length(trim(nombre_turno)) >= 2),
    CONSTRAINT chk_turno_horas CHECK (hora_fin > hora_inicio)
);

-- 4. Tabla: Horarios asignados al barbero
CREATE TABLE IF NOT EXISTS horarios_barbero (
    id_horario SERIAL PRIMARY KEY,
    id_barbero INT NOT NULL,
    id_turno INT NOT NULL,
    dia_semana VARCHAR(15) NOT NULL,
    CONSTRAINT fk_horario_barbero FOREIGN KEY (id_barbero) REFERENCES barberos(id_barbero) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_horario_turno FOREIGN KEY (id_turno) REFERENCES turnos_trabajo(id_turno) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_horario_dia CHECK (dia_semana IN ('Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo')),
    CONSTRAINT uq_horario_barbero_dia_turno UNIQUE (id_barbero, dia_semana, id_turno)
);

-- 5. Tabla: Catálogo de servicios
CREATE TABLE IF NOT EXISTS servicios (
    id_servicio SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    precio NUMERIC(10,2) NOT NULL,
    duracion_minutos INT NOT NULL DEFAULT 30,
    estado VARCHAR(20) DEFAULT 'Activo',
    CONSTRAINT chk_servicio_nombre CHECK (length(trim(nombre)) >= 2),
    CONSTRAINT chk_servicio_precio CHECK (precio > 0.00),
    CONSTRAINT chk_servicio_duracion CHECK (duracion_minutos > 0 AND duracion_minutos <= 480),
    CONSTRAINT chk_servicio_estado CHECK (estado IN ('Activo', 'Inactivo'))
);

-- 6. Tabla: Reservas y Citas
CREATE TABLE IF NOT EXISTS citas (
    id_cita SERIAL PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_barbero INT NOT NULL,
    id_servicio INT NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    estado VARCHAR(20) DEFAULT 'Pendiente',
    creado_en TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cita_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cita_barbero FOREIGN KEY (id_barbero) REFERENCES barberos(id_barbero) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cita_servicio FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_cita_estado CHECK (estado IN ('Pendiente', 'Confirmada', 'Completada', 'Cancelada'))
);

-- 7. Tabla: Registro de pagos
CREATE TABLE IF NOT EXISTS pagos (
    id_pago SERIAL PRIMARY KEY,
    id_cita INT NOT NULL UNIQUE,
    monto NUMERIC(10,2) NOT NULL,
    metodo_pago VARCHAR(30) NOT NULL,
    fecha_pago TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pago_cita FOREIGN KEY (id_cita) REFERENCES citas(id_cita) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_pago_monto CHECK (monto > 0.00),
    CONSTRAINT chk_pago_metodo CHECK (metodo_pago IN ('Efectivo', 'Transferencia', 'Punto de Venta', 'Pago Móvil', 'Tarjeta de Débito/Crédito'))
);

-- 8. Tabla: Liquidaciones de comisiones a barberos
CREATE TABLE IF NOT EXISTS liquidaciones_barbero (
    id_liquidacion SERIAL PRIMARY KEY,
    id_barbero INT NOT NULL,
    fecha_corte DATE NOT NULL,
    total_servicios INT NOT NULL,
    monto_comision NUMERIC(10,2) NOT NULL,
    estado_pago VARCHAR(20) DEFAULT 'Pendiente',
    CONSTRAINT fk_liquidacion_barbero FOREIGN KEY (id_barbero) REFERENCES barberos(id_barbero) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_liq_servicios CHECK (total_servicios >= 0),
    CONSTRAINT chk_liq_monto CHECK (monto_comision >= 0.00),
    CONSTRAINT chk_liq_estado CHECK (estado_pago IN ('Pendiente', 'Pagado'))
);

-- Índices de consulta rápida
CREATE INDEX IF NOT EXISTS idx_citas_fecha ON citas (fecha);
CREATE INDEX IF NOT EXISTS idx_pagos_fecha ON pagos (fecha_pago);
CREATE INDEX IF NOT EXISTS idx_clientes_cedula ON clientes (cedula);

-- RESTRICCIÓN DE NO DOBLE RESERVA (Índices Únicos Parciales):
-- Un barbero no puede tener dos citas activas en la misma fecha y hora
CREATE UNIQUE INDEX IF NOT EXISTS uq_cita_barbero_fecha_hora 
ON citas (id_barbero, fecha, hora) 
WHERE estado != 'Cancelada';

-- Un cliente no puede tener dos citas activas en la misma fecha y hora
CREATE UNIQUE INDEX IF NOT EXISTS uq_cita_cliente_fecha_hora 
ON citas (id_cliente, fecha, hora) 
WHERE estado != 'Cancelada';

-- =======================================================
-- PROCEDIMIENTOS Y TRIGGERS DE BASE DE DATOS (PL/pgSQL)
-- =======================================================

-- Trigger 1: Validar que Barbero y Servicio estén Activos antes de agendar cita
CREATE OR REPLACE FUNCTION fn_validar_cita_activa()
RETURNS TRIGGER AS $$
DECLARE
    v_barbero_estado VARCHAR(20);
    v_servicio_estado VARCHAR(20);
BEGIN
    SELECT estado INTO v_barbero_estado FROM barberos WHERE id_barbero = NEW.id_barbero;
    IF v_barbero_estado IS NULL OR v_barbero_estado != 'Activo' THEN
        RAISE EXCEPTION 'Restricción BD: El barbero seleccionado no se encuentra activo.';
    END IF;

    SELECT estado INTO v_servicio_estado FROM servicios WHERE id_servicio = NEW.id_servicio;
    IF v_servicio_estado IS NULL OR v_servicio_estado != 'Activo' THEN
        RAISE EXCEPTION 'Restricción BD: El servicio seleccionado no se encuentra activo en el catálogo.';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_validar_cita_activa ON citas;
CREATE TRIGGER trg_validar_cita_activa
BEFORE INSERT OR UPDATE OF id_barbero, id_servicio ON citas
FOR EACH ROW
EXECUTE FUNCTION fn_validar_cita_activa();

-- Trigger 2: Validar que el pago no sea para una cita cancelada
CREATE OR REPLACE FUNCTION fn_validar_pago_cita()
RETURNS TRIGGER AS $$
DECLARE
    v_cita_estado VARCHAR(20);
BEGIN
    SELECT estado INTO v_cita_estado FROM citas WHERE id_cita = NEW.id_cita;
    IF v_cita_estado = 'Cancelada' THEN
        RAISE EXCEPTION 'Restricción BD: No se puede registrar un pago para una cita cancelada.';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_validar_pago_cita ON pagos;
CREATE TRIGGER trg_validar_pago_cita
BEFORE INSERT ON pagos
FOR EACH ROW
EXECUTE FUNCTION fn_validar_pago_cita();

-- Trigger 3: Actualizar automáticamente el estado de la cita a 'Completada' al registrarse su pago
CREATE OR REPLACE FUNCTION fn_actualizar_cita_al_pagar()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE citas 
    SET estado = 'Completada' 
    WHERE id_cita = NEW.id_cita AND estado != 'Completada';
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_actualizar_cita_al_pagar ON pagos;
CREATE TRIGGER trg_actualizar_cita_al_pagar
AFTER INSERT ON pagos
FOR EACH ROW
EXECUTE FUNCTION fn_actualizar_cita_al_pagar();

-- =======================================================
-- VISTAS RELACIONALES (VIEWS)
-- =======================================================
CREATE OR REPLACE VIEW vista_citas_detalladas AS
SELECT 
    c.id_cita,
    c.fecha,
    c.hora,
    c.estado AS cita_estado,
    c.creado_en,
    cl.id_cliente,
    cl.cedula AS cliente_cedula,
    cl.nombre AS cliente_nombre,
    cl.telefono AS cliente_telefono,
    cl.email AS cliente_email,
    b.id_barbero,
    b.nombre AS barbero_nombre,
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
JOIN barberos b ON c.id_barbero = b.id_barbero
JOIN servicios s ON c.id_servicio = s.id_servicio
LEFT JOIN pagos p ON c.id_cita = p.id_cita;
