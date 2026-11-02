# Sistema Web de Gestión de Citas para Barbería (PostgreSQL 16 + PHP + Bootstrap 5 + Vanilla JS)

Aplicación web desarrollada en **PHP puro**, **Bootstrap 5.3** y **JavaScript nativo (Vanilla JS)** con motor relacional **PostgreSQL 16**, contenida y orquestada en **Docker** para su ejecución inmediata.

Diseñada específicamente con altos estándares de **Modelado de Bases de Datos**, implementando **integridad de dominio (CHECK constraints)**, **integridad de entidad (PK, UNIQUE)**, **integridad referencial (FOREIGN KEYS con ON DELETE / ON UPDATE)**, **índices únicos parciales anticolisión** y **disparadores PL/pgSQL (Triggers)** directamente en la base de datos.

---

## 🏛️ Estructura del Proyecto

```
barber/
├── database/
│   └── init.sql            # Definición DDL formal (Tablas, Constraints, Índices, Triggers y Views)
├── src/
│   ├── Database.php        # Conexión Singleton PDO PostgreSQL y migraciones automáticas
│   └── CrudService.php     # Lógica de datos, consultas descriptivas con JOINs y gestión de citas
├── public/
│   ├── index.php           # Interfaz gráfica dual con Bootstrap 5 (Portal de Clientes + Panel de Administración)
│   ├── api.php             # API REST en JSON con captura de errores SQLSTATE de PostgreSQL
│   └── assets/
│       ├── css/
│       │   └── style.css   # Estilos temáticos de Barbería (Dorado/Latón, Poste de Barbero Rojo/Blanco/Azul y Obsidiana)
│       └── js/
│           └── app.js      # Controlador Vanilla JS (Gestión de modos, citas, modales y validaciones)
├── readme_de_esquema_de_base_de_datos.md # Documentación formal del modelo relacional
├── Dockerfile              # Entorno PHP 8.2 con Apache y extensiones pdo_pgsql
├── docker-compose.yml      # Orquestación de servicios (app en puerto 8080 y PostgreSQL en 5432)
└── .dockerignore           # Exclusiones de construcción de imágenes Docker
```

---

## 🧔 Portal de Clientes vs ⚙️ Panel de Administración

El sistema cuenta con un selector de vista en la cabecera que separa la experiencia del cliente de la administración interna:

### 1. Portal de Clientes (Vista Pública / Autoservicio)
* **Ingreso sin duplicados**: El cliente ingresa con su **Número de Cédula / Identificación**.
  * Si ya está registrado: accede de inmediato a su panel sin volver a llenar formularios.
  * Si es nuevo: completa su registro una sola vez (Cédula, Nombre, Teléfono, Email). La base de datos garantiza mediante `CONSTRAINT uq_cliente_cedula UNIQUE` que no se repitan registros.
* **Flujo Guiado de Reserva**:
  1. **Selección de Servicio**: Catálogo interactivo de servicios con precio y duración en minutos.
  2. **Selección de Barbero**: Elección del barbero activo de su preferencia.
  3. **Fecha y Horario**: Selector con fecha mínima (hoy) y bloques horarios rápidos (08:00 a 18:00).
  4. **Confirmación**: Resumen claro con el total a pagar en barbería y solicitud instantánea.
* **Mis Citas Agendadas**:
  * Lista detallada de reservas del cliente (Servicio, Barbero, Fecha, Hora, Precio y Estado).
  * Opción para **Cancelar Cita** si se encuentra en estado `Pendiente`.
* **Privacidad**: El cliente **no tiene acceso al panel administrativo ni al CRUD de otras tablas**.

### 2. Panel Administrativo (CRUD Relacional Completo)
* **Gestión de las 8 tablas**: Clientes, Barberos, Turnos de Trabajo, Horarios de Barbero, Servicios, Citas, Pagos y Liquidaciones.
* **Métricas en tiempo real**: Tarjetas KPI en la cabecera (Total Clientes, Barberos Activos, Servicios, Citas, Ingresos Recaudados y Liquidaciones Pendientes).
* **Selects Inteligentes**: Los menús desplegables muestran los nombres y documentos descriptivos en lugar de identificadores numéricos crudos.
* **Botón de Demostración**: Permite poblar datos de prueba a través de las operaciones CRUD de la aplicación (sin alterar la pureza del DDL en `init.sql`).

---

## 🛡️ Restricciones Implementadas en la Base de Datos (PostgreSQL 16)

Todas las reglas críticas de negocio y validaciones de datos se encuentran aseguradas en **PostgreSQL**:

1. **Restricción de Identificación Única (Evitar Clientes Repetidos)**:
   * `CONSTRAINT uq_cliente_cedula UNIQUE (cedula)` en `clientes`.
2. **Restricciones de Anticolisión de Horarios (No doble reserva)**:
   * `CREATE UNIQUE INDEX uq_cita_barbero_fecha_hora ON citas (id_barbero, fecha, hora) WHERE estado != 'Cancelada';`
     *Impide que un mismo barbero tenga dos turnos a la misma fecha y hora.*
   * `CREATE UNIQUE INDEX uq_cita_cliente_fecha_hora ON citas (id_cliente, fecha, hora) WHERE estado != 'Cancelada';`
     *Impide que un cliente tenga dos turnos a la misma fecha y hora.*
3. **Restricciones CHECK de Validación de Campos**:
   * Cédula exclusivamente numérica: `chk_cliente_cedula CHECK (cedula ~ '^[0-9]{4,20}$')` (sin letras, guiones ni prefijos como "V-").
   * Precios estrictamente positivos: `chk_servicio_precio CHECK (precio > 0.00)` y `chk_pago_monto CHECK (monto > 0.00)`.
   * Coherencia de turnos de trabajo: `chk_turno_horas CHECK (hora_fin > hora_inicio)`.
   * Comisión de barberos: `chk_barbero_comision CHECK (porcentaje_comision >= 0 AND porcentaje_comision <= 100)`.
   * Días válidos: `chk_horario_dia CHECK (dia_semana IN ('Lunes', 'Martes', ...))`.
   * Estados controlados: `chk_barbero_estado`, `chk_servicio_estado`, `chk_cita_estado`, `chk_liq_estado`.
4. **Triggers PL/pgSQL Activos**:
   * `trg_validar_cita_activa`: Verifica que el barbero y el servicio estén en estado `'Activo'` antes de permitir la reserva.
   * `trg_validar_pago_cita`: Rechaza pagos sobre citas canceladas.
   * `trg_actualizar_cita_al_pagar`: Al registrar un pago exitoso, actualiza automáticamente la cita a `'Completada'`.

---

## 🚀 Despliegue con Docker

### 1. Iniciar los contenedores

En la terminal, dentro de la raíz del proyecto:

```bash
docker compose up -d --build
```

*(Si tu usuario en Linux requiere permisos de administrador para interactuar con Docker, ejecuta `sudo docker compose up -d --build`).*

### 2. Acceso al Sistema

* **Aplicación Web**: [http://localhost:8080](http://localhost:8080)
* **PostgreSQL Directo**: Puerto `5432` (`host: localhost`, `usuario: barber_user`, `contraseña: barber_secret_pass`, `bd: barberia_db`)
# barber-system
