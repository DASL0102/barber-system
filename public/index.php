<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Barbería Elite - Sistema de Gestión & Reservas</title>
  <meta name="description" content="Plataforma de citas de barbería con Bootstrap 5, colores de barbería tradicional y arquitectura relacional PostgreSQL 16.">
  
  <!-- Google Fonts: Plus Jakarta Sans & Bebas Neue -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5.3.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons 1.11.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  
  <!-- Custom Barber Shop Stylesheet (Cache-Busting for instant updates) -->
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">

  <!-- Chart.js 4.4.7 for Interactive Admin Analytics & Graphs -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
</head>
<body class="barber-app-body">

  <!-- Animated Traditional Barber Pole Stripe Banner -->
  <div class="barber-pole-stripe" aria-hidden="true"></div>

  <!-- Top Navigation Bar -->
  <nav class="navbar navbar-expand-lg barber-navbar sticky-top shadow-lg">
    <div class="container-xl py-1">
      
      <!-- Brand Logo -->
      <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none m-0" href="#">
        <div class="brand-pole-emblem">
          <i class="bi bi-scissors fs-4 text-dark"></i>
        </div>
        <div>
          <span class="barber-brand-title">BARBERÍA ELITE</span>
          <span class="barber-brand-subtitle d-block">Classic Grooming & Master Cuts</span>
        </div>
      </a>

      <!-- View Switcher (Portal Clientes vs Vista Barbero vs Panel Administrador) -->
      <div class="mode-switcher-container my-2 my-lg-0 mx-lg-auto">
        <div class="btn-group p-1 barber-mode-pill" role="group" aria-label="Selector de Vista">
          <button type="button" class="btn btn-sm mode-btn active rounded-pill px-3 py-1 fw-bold" id="btnModeClient" onclick="switchViewMode('client')">
            <i class="bi bi-person-fill me-1"></i> Portal Clientes
          </button>
          <button type="button" class="btn btn-sm mode-btn rounded-pill px-3 py-1 fw-bold" id="btnModeBarber" onclick="switchViewMode('barber')">
            <i class="bi bi-scissors me-1"></i> Vista Barbero
          </button>
          <button type="button" class="btn btn-sm mode-btn rounded-pill px-3 py-1 fw-bold" id="btnModeAdmin" onclick="handleAdminAccess()">
            <i class="bi bi-shield-lock-fill me-1"></i> Panel Administrador
          </button>
        </div>
      </div>

      <!-- Right Header Actions -->
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <!-- PostgreSQL Badge -->
        <span class="badge barber-badge-db d-none d-sm-inline-flex align-items-center gap-1 py-2 px-3 rounded-pill" title="Motor relacional PostgreSQL con restricciones activas">
          <span class="db-dot-live"></span>
          <span class="fw-semibold">PostgreSQL 16</span>
        </span>

        <!-- Logged-in Customer Session Info -->
        <div class="client-session-widget align-items-center gap-2 py-1 px-2 rounded-pill" id="clientSessionWidget" style="display: none;">
          <div class="client-avatar-mini">
            <i class="bi bi-person-circle"></i>
          </div>
          <div class="client-session-info pe-1">
            <span class="client-session-name fw-bold" id="sessionClientName">Cliente</span>
            <span class="client-session-id" id="sessionClientCedula">00000000</span>
          </div>
          <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 rounded-pill" id="btnLogoutClient" title="Cerrar sesión">
            <i class="bi bi-box-arrow-right"></i> Salir
          </button>
        </div>

        <!-- Logged-in Barber Session Info -->
        <div class="client-session-widget align-items-center gap-2 py-1 px-2 rounded-pill" id="barberSessionWidget" style="display: none;">
          <div class="client-avatar-mini" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b;">
            <i class="bi bi-scissors"></i>
          </div>
          <div class="client-session-info pe-1">
            <span class="client-session-name fw-bold" id="sessionBarberName">Barbero</span>
            <span class="client-session-id" id="sessionBarberComision">Comisión 50%</span>
          </div>
          <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 rounded-pill" id="btnLogoutBarber" onclick="logoutBarber()" title="Cerrar turno de barbero">
            <i class="bi bi-box-arrow-right"></i> Salir
          </button>
        </div>

        <!-- Barber Header Actions -->
        <div class="barber-actions-group d-flex gap-2" id="barberHeaderActions" style="display: none;">
          <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="refreshBarberData()" title="Actualizar mis citas y comisiones">
            <i class="bi bi-arrow-clockwise me-1"></i> Refrescar
          </button>
        </div>

        <!-- Admin Actions (Only in Admin view) -->
        <div class="admin-actions-group d-flex gap-2" id="adminHeaderActions" style="display: none;">
          <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" id="btnRefreshAll" title="Recargar todos los datos">
            <i class="bi bi-arrow-clockwise me-1"></i> Refrescar
          </button>
          <button type="button" class="btn btn-barber-gold btn-sm rounded-pill px-3 fw-bold" id="btnSeedDemo" title="Poblar registros de prueba mediante operaciones CRUD">
            <i class="bi bi-lightning-charge-fill me-1"></i> Demo CRUD
          </button>
          <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-semibold" id="btnLogoutAdmin" onclick="logoutAdmin()" title="Cerrar sesión de administrador">
            <i class="bi bi-box-arrow-right me-1"></i> Salir Admin
          </button>
        </div>
      </div>

    </div>
  </nav>

  <!-- Main App Container -->
  <main class="container-xl my-4 my-md-5">

    <!-- =======================================================
         1. PORTAL DE CLIENTES (VISTA EXCLUSIVA PARA CLIENTES)
         ======================================================= -->
    <section class="portal-view active" id="clientPortalView" aria-label="Portal de Clientes">

      <!-- SUB-VISTA A: Identificación / Acceso (Si no ha iniciado sesión) -->
      <div class="client-auth-wrapper" id="clientAuthSection">
        
        <!-- Barber Hero Header -->
        <div class="text-center mb-4 pb-2">
          <div class="d-inline-flex align-items-center gap-2 barber-vintage-badge px-3 py-1 rounded-pill mb-2">
            <span class="barber-pole-dot"></span>
            <span class="text-uppercase fw-bold letter-spacing-1">Barbería Tradicional & Moderna</span>
          </div>
          <h1 class="barber-display-title mt-2">RESERVA TU TURNO EXCLUSIVO</h1>
          <p class="text-secondary max-w-650 mx-auto fs-6">
            Para garantizar la atención y evitar duplicidad en nuestros registros, ingresa con tu cédula o regístrate en solo 30 segundos.
          </p>
        </div>

        <!-- Auth Card with Bootstrap Nav Tabs -->
        <div class="card barber-card border-0 shadow-lg mx-auto" style="max-width: 580px;">
          
          <div class="card-header bg-dark-subtle p-2 border-bottom border-secondary-subtle">
            <ul class="nav nav-pills nav-fill gap-1" role="tablist">
              <li class="nav-item">
                <button type="button" class="nav-link active rounded-pill fw-bold" id="tabBtnLogin" onclick="switchAuthTab('login')">
                  <i class="bi bi-key-fill me-1"></i> Ya soy Cliente (Ingresar)
                </button>
              </li>
              <li class="nav-item">
                <button type="button" class="nav-link rounded-pill fw-bold" id="tabBtnRegister" onclick="switchAuthTab('register')">
                  <i class="bi bi-person-plus-fill me-1"></i> Nuevo Cliente (Registrarme)
                </button>
              </li>
            </ul>
          </div>

          <div class="card-body p-4">
            
            <!-- Tab Content 1: Ingreso por Identificación -->
            <div class="auth-tab-panel active" id="panelLogin">
              <div class="mb-3">
                <h4 class="fw-bold text-light mb-1">Ingresa con tu Cédula</h4>
                <p class="text-secondary small">Tu número de identificación te conecta directo con tus reservas previas sin volver a registrarte.</p>
              </div>

              <form id="formClientLogin" autocomplete="off">
                <div class="mb-3">
                  <label for="loginCedula" class="form-label fw-semibold text-light">
                    Número de Cédula / Identificación (Solo números) <span class="text-danger">*</span>
                  </label>
                  <div class="input-group input-group-lg">
                    <span class="input-group-text barber-input-icon"><i class="bi bi-hash"></i></span>
                    <input type="text" inputmode="numeric" pattern="[0-9]+" class="form-control barber-input" id="loginCedula" name="cedula" placeholder="Ej. 28456123 (solo números)" required autofocus oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                  </div>
                  <div class="form-text text-secondary small mt-1">
                    <i class="bi bi-shield-check text-warning me-1"></i> Ingresa únicamente los dígitos numéricos (sin letras como V-, ni puntos ni guiones).
                  </div>
                </div>

                <!-- Alert: No encontrado -->
                <div class="alert barber-alert-warning d-flex align-items-start gap-3 mt-3" id="alertLoginNotFound" style="display: none;" role="alert">
                  <i class="bi bi-exclamation-triangle-fill fs-4 text-warning flex-shrink-0"></i>
                  <div>
                    <h6 class="alert-heading fw-bold mb-1">Cédula no registrada en el sistema</h6>
                    <p class="small mb-2 text-secondary-emphasis">No encontramos este documento registrado. ¿Es tu primera vez en Barbería Elite?</p>
                    <button type="button" class="btn btn-sm btn-barber-gold fw-bold rounded-pill px-3" id="btnSwitchToRegister" onclick="switchAuthTab('register')">
                      <i class="bi bi-person-plus me-1"></i> Registrarme ahora en 1 minuto →
                    </button>
                  </div>
                </div>

                <div class="mt-4 pt-2">
                  <button type="submit" class="btn btn-barber-gold w-100 py-3 fw-bold fs-6 rounded-3 shadow" id="btnSubmitLogin">
                    <span>Ingresar y Solicitar Cita</span> <i class="bi bi-arrow-right-circle ms-1"></i>
                  </button>
                </div>
              </form>
            </div>

            <!-- Tab Content 2: Registro de Nuevo Cliente -->
            <div class="auth-tab-panel" id="panelRegister" style="display: none;">
              <div class="mb-3">
                <h4 class="fw-bold text-light mb-1">Registro de Nuevo Cliente</h4>
                <p class="text-secondary small">Llena tus datos una única vez. Tu documento será tu llave única para futuras reservas.</p>
              </div>

              <form id="formClientRegister" autocomplete="off">
                <div class="row g-3">
                  <div class="col-12 col-md-6">
                    <label for="regCedula" class="form-label fw-semibold text-light small">
                      Cédula / Identificación (Solo números) <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text barber-input-icon"><i class="bi bi-hash"></i></span>
                      <input type="text" inputmode="numeric" pattern="[0-9]+" class="form-control barber-input" id="regCedula" name="cedula" placeholder="Ej. 28456123" required oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle mt-1 small">Solo dígitos numéricos (sin V-)</span>
                  </div>

                  <div class="col-12 col-md-6">
                    <label for="regTelefono" class="form-label fw-semibold text-light small">
                      Teléfono / WhatsApp <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text barber-input-icon"><i class="bi bi-whatsapp"></i></span>
                      <input type="tel" class="form-control barber-input" id="regTelefono" name="telefono" placeholder="Ej. +58 412 1112233" required>
                    </div>
                  </div>

                  <div class="col-12">
                    <label for="regNombre" class="form-label fw-semibold text-light small">
                      Nombre Completo <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text barber-input-icon"><i class="bi bi-person"></i></span>
                      <input type="text" class="form-control barber-input" id="regNombre" name="nombre" placeholder="Ej. Gabriel Torres" required>
                    </div>
                  </div>

                  <div class="col-12">
                    <label for="regEmail" class="form-label fw-semibold text-light small">
                      Correo Electrónico (Opcional)
                    </label>
                    <div class="input-group">
                      <span class="input-group-text barber-input-icon"><i class="bi bi-envelope"></i></span>
                      <input type="email" class="form-control barber-input" id="regEmail" name="email" placeholder="cliente@correo.com">
                    </div>
                  </div>
                </div>

                <div class="mt-4 pt-2">
                  <button type="submit" class="btn btn-barber-gold w-100 py-3 fw-bold fs-6 rounded-3 shadow" id="btnSubmitRegister">
                    <span>Guardar y Continuar a Reservar</span> <i class="bi bi-check-circle-fill ms-1"></i>
                  </button>
                </div>
              </form>
            </div>

          </div>
        </div>

      </div>

      <!-- SUB-VISTA B: Dashboard del Cliente Identificado -->
      <div class="client-dashboard" id="clientDashboardSection" style="display: none;">
        
        <!-- Welcome Card -->
        <div class="card barber-card border-0 shadow-lg mb-4 barber-welcome-card">
          <div class="card-body p-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
              <div class="barber-avatar-circle flex-shrink-0">
                <i class="bi bi-scissors fs-2 text-dark"></i>
              </div>
              <div>
                <span class="badge bg-gold-subtle text-gold text-uppercase fw-bold px-2 py-1 mb-1">Cliente VIP Registrado</span>
                <h2 class="h3 fw-bold text-light mb-1">¡Hola, <span id="dashClientName">Cliente</span>!</h2>
                <div class="text-secondary small d-flex flex-wrap gap-3">
                  <span><i class="bi bi-person-vcard text-warning me-1"></i> Cédula: <strong class="text-light" id="dashClientCedula">00000000</strong></span>
                  <span><i class="bi bi-telephone text-info me-1"></i> Teléfono: <span class="text-light" id="dashClientPhone">+58 000 0000000</span></span>
                </div>
              </div>
            </div>
            <div>
              <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-2 fw-semibold" id="btnExitClientSession">
                <i class="bi bi-box-arrow-right me-1"></i> Cerrar Sesión
              </button>
            </div>
          </div>
        </div>

        <!-- Client Nav Tabs (Solicitar Cita vs Mis Citas) -->
        <ul class="nav nav-pills barber-client-nav gap-2 mb-4 pb-2 border-bottom border-secondary-subtle" role="tablist">
          <li class="nav-item">
            <button type="button" class="nav-link active rounded-pill px-4 py-2 fw-bold" id="btnNavBookAppointment">
              <i class="bi bi-calendar-plus me-1"></i> Solicitar Nueva Cita
            </button>
          </li>
          <li class="nav-item">
            <button type="button" class="nav-link rounded-pill px-4 py-2 fw-bold" id="btnNavMyAppointments">
              <i class="bi bi-clock-history me-1"></i> Mis Citas Agendadas 
              <span class="badge bg-warning text-dark ms-1 rounded-pill" id="badgeClientApptCount">0</span>
            </button>
          </li>
        </ul>

        <!-- SUB-PANEL 1: Agendar Cita (Paso a Paso Guiado) -->
        <div class="client-panel-box active" id="clientBookAppointmentBox">
          <div class="d-flex flex-column gap-4">
            
            <!-- Paso 1: Servicios -->
            <div class="card barber-step-card border-0 shadow-sm">
              <div class="card-header bg-transparent border-0 pt-3 pb-1 d-flex align-items-center gap-2">
                <span class="barber-step-badge">1</span>
                <div>
                  <h4 class="fw-bold text-light mb-0">Selecciona tu Servicio</h4>
                  <p class="text-secondary small mb-0">Elige el estilo, corte o tratamiento que deseas.</p>
                </div>
              </div>
              <div class="card-body pt-2">
                <div class="cards-selector-grid" id="clientServicesGrid">
                  <div class="text-center py-4 text-secondary">Cargando catálogo de servicios disponibles...</div>
                </div>
              </div>
            </div>

            <!-- Paso 2: Barbero -->
            <div class="card barber-step-card border-0 shadow-sm">
              <div class="card-header bg-transparent border-0 pt-3 pb-1 d-flex align-items-center gap-2">
                <span class="barber-step-badge">2</span>
                <div>
                  <h4 class="fw-bold text-light mb-0">Selecciona tu Barbero</h4>
                  <p class="text-secondary small mb-0">Escoge al profesional de tu preferencia.</p>
                </div>
              </div>
              <div class="card-body pt-2">
                <div class="cards-selector-grid" id="clientBarbersGrid">
                  <div class="text-center py-4 text-secondary">Cargando barberos disponibles...</div>
                </div>
              </div>
            </div>

            <!-- Paso 3: Fecha y Hora -->
            <div class="card barber-step-card border-0 shadow-sm">
              <div class="card-header bg-transparent border-0 pt-3 pb-1 d-flex align-items-center gap-2">
                <span class="barber-step-badge">3</span>
                <div>
                  <h4 class="fw-bold text-light mb-0">Fecha y Horario de Atención</h4>
                  <p class="text-secondary small mb-0">Elige el día y bloque que mejor se ajuste a tu tiempo.</p>
                </div>
              </div>
              <div class="card-body pt-2">
                <div class="row g-4 align-items-start">
                  <div class="col-12 col-md-4">
                    <label for="bookingDate" class="form-label fw-bold text-light">Fecha de la Cita <span class="text-danger">*</span></label>
                    <input type="date" class="form-control barber-input form-control-lg" id="bookingDate" name="bookingDate" required>
                    <div class="form-text text-secondary small">Solo se admiten fechas a partir de hoy.</div>
                  </div>

                  <div class="col-12 col-md-8">
                    <label class="form-label fw-bold text-light">Turnos Disponibles <span class="text-danger">*</span></label>
                    <div class="time-slots-grid" id="timeSlotsContainer">
                      <button type="button" class="btn btn-outline-secondary time-slot-btn" data-time="08:00:00">08:00 AM</button>
                      <button type="button" class="btn btn-outline-secondary time-slot-btn" data-time="09:00:00">09:00 AM</button>
                      <button type="button" class="btn btn-outline-secondary time-slot-btn" data-time="10:00:00">10:00 AM</button>
                      <button type="button" class="btn btn-outline-secondary time-slot-btn" data-time="11:00:00">11:00 AM</button>
                      <button type="button" class="btn btn-outline-secondary time-slot-btn" data-time="12:00:00">12:00 PM</button>
                      <button type="button" class="btn btn-outline-secondary time-slot-btn" data-time="13:00:00">01:00 PM</button>
                      <button type="button" class="btn btn-outline-secondary time-slot-btn" data-time="14:00:00">02:00 PM</button>
                      <button type="button" class="btn btn-outline-secondary time-slot-btn" data-time="15:00:00">03:00 PM</button>
                      <button type="button" class="btn btn-outline-secondary time-slot-btn" data-time="16:00:00">04:00 PM</button>
                      <button type="button" class="btn btn-outline-secondary time-slot-btn" data-time="17:00:00">05:00 PM</button>
                      <button type="button" class="btn btn-outline-secondary time-slot-btn" data-time="18:00:00">06:00 PM</button>
                    </div>
                    <input type="hidden" id="selectedBookingTime" value="">
                  </div>
                </div>
              </div>
            </div>

            <!-- Paso 4: Resumen y Confirmación -->
            <div class="card barber-summary-card border-0 shadow-lg">
              <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                  <i class="bi bi-receipt-cutoff fs-4 text-warning"></i>
                  <h4 class="fw-bold text-light mb-0">Resumen de tu Reserva</h4>
                </div>

                <div class="row g-3 py-2 border-top border-bottom border-secondary-subtle">
                  <div class="col-12 col-md-4">
                    <span class="text-secondary small d-block">Servicio:</span>
                    <strong class="text-light fs-6" id="summaryService">Ninguno seleccionado (Paso 1)</strong>
                  </div>
                  <div class="col-12 col-md-4">
                    <span class="text-secondary small d-block">Barbero asignado:</span>
                    <strong class="text-light fs-6" id="summaryBarber">Ninguno seleccionado (Paso 2)</strong>
                  </div>
                  <div class="col-12 col-md-4">
                    <span class="text-secondary small d-block">Fecha y Horario:</span>
                    <strong class="text-light fs-6" id="summaryDateTime">Por seleccionar (Paso 3)</strong>
                  </div>
                </div>

                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mt-3 pt-2">
                  <div>
                    <span class="text-secondary small d-block">Total a pagar en la barbería:</span>
                    <span class="barber-price-highlight" id="summaryPrice">$0.00</span>
                  </div>

                  <div>
                    <button type="button" class="btn btn-barber-gold btn-lg rounded-pill px-5 py-3 fw-bold shadow" id="btnConfirmBooking">
                      <i class="bi bi-calendar-check-fill me-2"></i> Confirmar y Reservar Cita
                    </button>
                  </div>
                </div>

              </div>
            </div>

          </div>
        </div>

        <!-- SUB-PANEL 2: Mis Citas Agendadas -->
        <div class="client-panel-box" id="clientMyAppointmentsBox" style="display: none;">
          <div class="card barber-card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between p-3">
              <div>
                <h4 class="fw-bold text-light mb-0"><i class="bi bi-journal-bookmark-fill text-warning me-2"></i>Historial de tus Citas</h4>
                <p class="text-secondary small mb-0">Revisa el estado de tus citas o cancela reservas pendientes.</p>
              </div>
              <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" id="btnRefreshClientAppts">
                <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
              </button>
            </div>
            <div class="card-body p-3">
              <div class="client-appts-container" id="clientApptsList">
                <div class="text-center py-5 text-secondary">Cargando tus citas...</div>
              </div>
            </div>
          </div>
        </div>

      </div>

    </section>

    <!-- =======================================================
         2. PORTAL DE BARBEROS (VISTA EXCLUSIVA PARA EL BARBERO)
         ======================================================= -->
    <section class="portal-view" id="barberPortalView" style="display: none;" aria-label="Portal de Barberos">

      <!-- SUB-VISTA A: Selección / Identificación del Barbero -->
      <div class="barber-auth-wrapper" id="barberAuthSection">
        <div class="text-center mb-4 pb-2">
          <div class="d-inline-flex align-items-center gap-2 barber-vintage-badge px-3 py-1 rounded-pill mb-2">
            <span class="barber-pole-dot"></span>
            <span class="text-uppercase fw-bold letter-spacing-1">Equipo Profesional de Barbería</span>
          </div>
          <h1 class="barber-display-title mt-2">PORTAL DE GESTIÓN DEL BARBERO</h1>
          <p class="text-secondary max-w-650 mx-auto fs-6">
            Selecciona tu perfil profesional para consultar tu agenda del día, marcar citas completadas, gestionar cobros y revisar tus comisiones.
          </p>
        </div>

        <div class="row g-4 justify-content-center" id="barberCardsContainer">
          <div class="col-12 text-center text-secondary py-5">
            <div class="spinner-border text-warning" role="status"></div>
            <p class="mt-2 mb-0">Cargando barberos registrados...</p>
          </div>
        </div>
      </div>

      <!-- SUB-VISTA B: Dashboard del Barbero Identificado -->
      <div class="barber-dashboard" id="barberDashboardSection" style="display: none;">

        <!-- Barber Header Banner -->
        <div class="card barber-card border-0 shadow-lg mb-4 barber-welcome-card">
          <div class="card-body p-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
              <div class="barber-avatar-circle flex-shrink-0" style="background: linear-gradient(135deg, #f59e0b, #b45309);">
                <i class="bi bi-scissors fs-2 text-dark"></i>
              </div>
              <div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                  <span class="badge bg-gold-subtle text-gold text-uppercase fw-bold px-2 py-1" id="dashBarberComisionBadge">Comisión: 50%</span>
                  <span class="badge bg-success-subtle text-success text-uppercase fw-bold px-2 py-1" id="dashBarberEstadoBadge">Turno Activo</span>
                </div>
                <h2 class="h3 fw-bold text-light mb-1">¡Buen turno, <span id="dashBarberName">Barbero</span>!</h2>
                <div class="text-secondary small d-flex flex-wrap gap-3">
                  <span><i class="bi bi-telephone text-info me-1"></i> Contacto: <span class="text-light" id="dashBarberPhone">+58 000 0000000</span></span>
                  <span><i class="bi bi-calendar-check text-warning me-1"></i> Sesión abierta en este dispositivo</span>
                </div>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 fw-semibold" onclick="refreshBarberData()">
                <i class="bi bi-arrow-clockwise me-1"></i> Refrescar Agenda
              </button>
              <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-2 fw-semibold" onclick="logoutBarber()">
                <i class="bi bi-box-arrow-right me-1"></i> Cambiar Barbero
              </button>
            </div>
          </div>
        </div>

        <!-- KPI Metrics Grid for Barber -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-md-4 col-xl-2">
            <div class="card barber-kpi-card border-0 shadow-sm h-100">
              <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="text-secondary small fw-semibold">Citas de Hoy</span>
                  <span class="barber-kpi-icon bg-warning-subtle text-warning"><i class="bi bi-calendar-event"></i></span>
                </div>
                <div class="h3 fw-bold text-light mb-0" id="bStatHoy">0</div>
              </div>
            </div>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <div class="card barber-kpi-card border-0 shadow-sm h-100">
              <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="text-secondary small fw-semibold">Pendientes</span>
                  <span class="barber-kpi-icon bg-info-subtle text-info"><i class="bi bi-hourglass-split"></i></span>
                </div>
                <div class="h3 fw-bold text-light mb-0" id="bStatPendientes">0</div>
              </div>
            </div>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <div class="card barber-kpi-card border-0 shadow-sm h-100">
              <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="text-secondary small fw-semibold">Confirmadas</span>
                  <span class="barber-kpi-icon bg-primary-subtle text-primary"><i class="bi bi-check2-circle"></i></span>
                </div>
                <div class="h3 fw-bold text-light mb-0" id="bStatConfirmadas">0</div>
              </div>
            </div>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <div class="card barber-kpi-card border-0 shadow-sm h-100">
              <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="text-secondary small fw-semibold">Completadas</span>
                  <span class="barber-kpi-icon bg-success-subtle text-success"><i class="bi bi-patch-check-fill"></i></span>
                </div>
                <div class="h3 fw-bold text-light mb-0" id="bStatCompletadas">0</div>
              </div>
            </div>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <div class="card barber-kpi-card border-0 shadow-sm h-100">
              <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="text-secondary small fw-semibold">Mis Ganancias</span>
                  <span class="barber-kpi-icon bg-gold-subtle text-gold"><i class="bi bi-cash-stack"></i></span>
                </div>
                <div class="h3 fw-bold text-warning mb-0" id="bStatComisiones">$0.00</div>
              </div>
            </div>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <div class="card barber-kpi-card border-0 shadow-sm h-100">
              <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="text-secondary small fw-semibold">Por Liquidar</span>
                  <span class="barber-kpi-icon bg-danger-subtle text-danger"><i class="bi bi-wallet2"></i></span>
                </div>
                <div class="h3 fw-bold text-light mb-0" id="bStatPorLiquidar">$0.00</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Barber Sub-Navigation Tabs -->
        <ul class="nav nav-pills barber-client-nav gap-2 mb-4 pb-2 border-bottom border-secondary-subtle" role="tablist">
          <li class="nav-item">
            <button type="button" class="nav-link active rounded-pill px-4 py-2 fw-bold" id="btnBarberTabAppts" onclick="switchBarberTab('appts')">
              <i class="bi bi-calendar-week me-1"></i> Mi Agenda & Citas
            </button>
          </li>
          <li class="nav-item">
            <button type="button" class="nav-link rounded-pill px-4 py-2 fw-bold" id="btnBarberTabSchedule" onclick="switchBarberTab('schedule')">
              <i class="bi bi-clock-history me-1"></i> Mi Horario Semanal
            </button>
          </li>
          <li class="nav-item">
            <button type="button" class="nav-link rounded-pill px-4 py-2 fw-bold" id="btnBarberTabFinancials" onclick="switchBarberTab('financials')">
              <i class="bi bi-cash-coin me-1"></i> Comisiones & Liquidaciones
            </button>
          </li>
        </ul>

        <!-- BARBER PANEL 1: Citas y Agenda -->
        <div class="barber-panel-tab active" id="barberPanelAppts">
          <div class="card barber-card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-bottom border-secondary-subtle p-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
              <div>
                <h4 class="fw-bold text-light mb-0"><i class="bi bi-scissors text-warning me-2"></i>Agenda de Servicios</h4>
                <p class="text-secondary small mb-0">Gestiona los clientes asignados a tu turno, confirma citas y registra los cobros.</p>
              </div>

              <!-- Quick Status Filter Pills -->
              <div class="btn-group btn-group-sm p-1 barber-mode-pill" role="group">
                <button type="button" class="btn btn-sm mode-btn active rounded-pill px-3 py-1 fw-bold" onclick="filterBarberAppts('hoy', this)">Hoy</button>
                <button type="button" class="btn btn-sm mode-btn rounded-pill px-3 py-1 fw-bold" onclick="filterBarberAppts('pendientes', this)">Pendientes</button>
                <button type="button" class="btn btn-sm mode-btn rounded-pill px-3 py-1 fw-bold" onclick="filterBarberAppts('completadas', this)">Completadas</button>
                <button type="button" class="btn btn-sm mode-btn rounded-pill px-3 py-1 fw-bold" onclick="filterBarberAppts('todas', this)">Todas</button>
              </div>
            </div>

            <div class="card-body p-3 p-md-4">
              <div id="barberApptsContainer">
                <div class="text-center py-5 text-secondary">Cargando agenda de citas...</div>
              </div>
            </div>
          </div>
        </div>

        <!-- BARBER PANEL 2: Horarios Semanales -->
        <div class="barber-panel-tab" id="barberPanelSchedule" style="display: none;">
          <div class="card barber-card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-bottom border-secondary-subtle p-3">
              <h4 class="fw-bold text-light mb-0"><i class="bi bi-calendar3 text-warning me-2"></i>Horarios y Turnos Asignados</h4>
              <p class="text-secondary small mb-0">Planificación de jornadas laborales asignadas en el sistema relacional.</p>
            </div>
            <div class="card-body p-3 p-md-4">
              <div class="row g-3" id="barberScheduleCards">
                <div class="text-center py-5 text-secondary">Cargando horario asignado...</div>
              </div>
            </div>
          </div>
        </div>

        <!-- BARBER PANEL 3: Finanzas y Liquidaciones -->
        <div class="barber-panel-tab" id="barberPanelFinancials" style="display: none;">
          <div class="card barber-card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-bottom border-secondary-subtle p-3">
              <h4 class="fw-bold text-light mb-0"><i class="bi bi-wallet2 text-warning me-2"></i>Comisiones y Liquidaciones</h4>
              <p class="text-secondary small mb-0">Detalle de cortes atendidos, recaudación generada y cortes de pago.</p>
            </div>
            <div class="card-body p-3 p-md-4">
              <div class="row g-4 mb-4">
                <div class="col-12 col-md-4">
                  <div class="p-3 rounded-3 bg-dark-subtle border border-secondary-subtle text-center">
                    <span class="text-secondary small d-block mb-1">Total Facturado por tus Servicios</span>
                    <h3 class="fw-bold text-light mb-0" id="finTotalRecaudado">$0.00</h3>
                  </div>
                </div>
                <div class="col-12 col-md-4">
                  <div class="p-3 rounded-3 bg-dark-subtle border border-warning-subtle text-center">
                    <span class="text-warning small d-block mb-1">Tu Comisión Acumulada</span>
                    <h3 class="fw-bold text-warning mb-0" id="finComisionTotal">$0.00</h3>
                  </div>
                </div>
                <div class="col-12 col-md-4">
                  <div class="p-3 rounded-3 bg-dark-subtle border border-secondary-subtle text-center">
                    <span class="text-secondary small d-block mb-1">Comisión Ya Liquidada / Pagada</span>
                    <h3 class="fw-bold text-success mb-0" id="finComisionLiquidada">$0.00</h3>
                  </div>
                </div>
              </div>

              <h5 class="fw-bold text-light mb-3">Historial de Cortes y Liquidaciones de Comisiones</h5>
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 barber-table">
                  <thead class="table-dark">
                    <tr>
                      <th>ID</th>
                      <th>Fecha de Corte</th>
                      <th>Total Servicios</th>
                      <th>Monto Comisión ($)</th>
                      <th>Estado de Pago</th>
                    </tr>
                  </thead>
                  <tbody id="barberLiquidacionesTableBody">
                    <tr>
                      <td colspan="5" class="text-center py-4 text-secondary">Cargando liquidaciones...</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div>

    </section>

    <!-- =======================================================
         3. PANEL DE ADMINISTRADOR (GRÁFICAS & CRUD 8 TABLAS)
         ======================================================= -->
    <section class="admin-view" id="adminPanelView" style="display: none;" aria-label="Panel Administrativo">

      <!-- KPI Summary Cards (Bootstrap Grid) -->
      <div class="row g-3 mb-4" aria-label="Métricas Principales">
        
        <div class="col-6 col-md-4 col-xl-2">
          <div class="card barber-kpi-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary small fw-semibold">Clientes</span>
                <span class="barber-kpi-icon bg-gold-subtle text-gold"><i class="bi bi-people-fill"></i></span>
              </div>
              <div class="h3 fw-bold text-light mb-0" id="statClientes">0</div>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
          <div class="card barber-kpi-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary small fw-semibold">Barberos</span>
                <span class="barber-kpi-icon bg-info-subtle text-info"><i class="bi bi-scissors"></i></span>
              </div>
              <div class="h3 fw-bold text-light mb-0" id="statBarberos">0</div>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
          <div class="card barber-kpi-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary small fw-semibold">Servicios</span>
                <span class="barber-kpi-icon bg-primary-subtle text-primary"><i class="bi bi-tag-fill"></i></span>
              </div>
              <div class="h3 fw-bold text-light mb-0" id="statServicios">0</div>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
          <div class="card barber-kpi-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary small fw-semibold">Citas Totales</span>
                <span class="barber-kpi-icon bg-warning-subtle text-warning"><i class="bi bi-calendar-event-fill"></i></span>
              </div>
              <div class="h3 fw-bold text-light mb-0" id="statCitas">0</div>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
          <div class="card barber-kpi-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary small fw-semibold">Ingresos</span>
                <span class="barber-kpi-icon bg-success-subtle text-success"><i class="bi bi-cash-stack"></i></span>
              </div>
              <div class="h3 fw-bold text-light mb-0" id="statIngresos">$0.00</div>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
          <div class="card barber-kpi-card border-0 shadow-sm h-100">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary small fw-semibold">Liquidaciones</span>
                <span class="barber-kpi-icon bg-danger-subtle text-danger"><i class="bi bi-wallet2"></i></span>
              </div>
              <div class="h3 fw-bold text-light mb-0" id="statLiquidaciones">0</div>
            </div>
          </div>
        </div>

      </div>

      <!-- Navigation Tabs (Métricas & Gráficas + 8 Relational Entities) -->
      <div class="card barber-card border-0 shadow-sm mb-4">
        <div class="card-body p-2 overflow-x-auto">
          <ul class="nav nav-pills flex-nowrap gap-1" role="tablist">
            <li class="nav-item">
              <button type="button" class="nav-link tab-btn active rounded-pill px-3 py-2 fw-semibold" data-table="analytics">
                <i class="bi bi-pie-chart-fill me-1 text-warning"></i> Dashboard & Gráficas
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link tab-btn rounded-pill px-3 py-2 fw-semibold" data-table="clientes">
                <i class="bi bi-people-fill me-1"></i> Clientes <span class="badge bg-secondary rounded-pill tab-count ms-1">0</span>
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link tab-btn rounded-pill px-3 py-2 fw-semibold" data-table="barberos">
                <i class="bi bi-scissors me-1"></i> Barberos <span class="badge bg-secondary rounded-pill tab-count ms-1">0</span>
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link tab-btn rounded-pill px-3 py-2 fw-semibold" data-table="turnos_trabajo">
                <i class="bi bi-clock-fill me-1"></i> Turnos <span class="badge bg-secondary rounded-pill tab-count ms-1">0</span>
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link tab-btn rounded-pill px-3 py-2 fw-semibold" data-table="horarios_barbero">
                <i class="bi bi-calendar3 me-1"></i> Horarios <span class="badge bg-secondary rounded-pill tab-count ms-1">0</span>
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link tab-btn rounded-pill px-3 py-2 fw-semibold" data-table="servicios">
                <i class="bi bi-tag-fill me-1"></i> Servicios <span class="badge bg-secondary rounded-pill tab-count ms-1">0</span>
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link tab-btn rounded-pill px-3 py-2 fw-semibold" data-table="citas">
                <i class="bi bi-calendar-check-fill me-1"></i> Citas <span class="badge bg-secondary rounded-pill tab-count ms-1">0</span>
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link tab-btn rounded-pill px-3 py-2 fw-semibold" data-table="pagos">
                <i class="bi bi-credit-card-fill me-1"></i> Pagos <span class="badge bg-secondary rounded-pill tab-count ms-1">0</span>
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link tab-btn rounded-pill px-3 py-2 fw-semibold" data-table="liquidaciones_barbero">
                <i class="bi bi-cash-coin me-1"></i> Liquidaciones <span class="badge bg-secondary rounded-pill tab-count ms-1">0</span>
              </button>
            </li>
          </ul>
        </div>
      </div>

      <!-- =======================================================
           PANEL A: DASHBOARD ANALÍTICO CON GRÁFICAS DE INFORMACIÓN
           ======================================================= -->
      <div id="adminAnalyticsContainer" class="admin-tab-content">

        <!-- Business Highlights Insights Bar -->
        <div class="row g-3 mb-4">
          <div class="col-12 col-md-6 col-xl-3">
            <div class="p-3 chart-insight-card h-100 d-flex align-items-center gap-3">
              <div class="brand-pole-emblem flex-shrink-0" style="width: 46px; height: 46px;">
                <i class="bi bi-trophy-fill fs-4 text-dark"></i>
              </div>
              <div>
                <span class="text-secondary small d-block">Barbero Más Solicitado</span>
                <strong class="text-warning fs-6" id="anaTopBarbero">Cargando...</strong>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-xl-3">
            <div class="p-3 chart-insight-card h-100 d-flex align-items-center gap-3">
              <div class="brand-pole-emblem flex-shrink-0" style="width: 46px; height: 46px; background: linear-gradient(135deg, #60a5fa, #2563eb);">
                <i class="bi bi-stars fs-4 text-white"></i>
              </div>
              <div>
                <span class="text-secondary small d-block">Servicio Estrella</span>
                <strong class="text-light fs-6" id="anaTopServicio">Cargando...</strong>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-xl-3">
            <div class="p-3 chart-insight-card h-100 d-flex align-items-center gap-3">
              <div class="brand-pole-emblem flex-shrink-0" style="width: 46px; height: 46px; background: linear-gradient(135deg, #34d399, #059669);">
                <i class="bi bi-receipt fs-4 text-white"></i>
              </div>
              <div>
                <span class="text-secondary small d-block">Ticket Promedio por Cobro</span>
                <strong class="text-success fs-6" id="anaTicketPromedio">$0.00</strong>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-xl-3">
            <div class="p-3 chart-insight-card h-100 d-flex align-items-center gap-3">
              <div class="brand-pole-emblem flex-shrink-0" style="width: 46px; height: 46px; background: linear-gradient(135deg, #a78bfa, #7c3aed);">
                <i class="bi bi-check-all fs-4 text-white"></i>
              </div>
              <div>
                <span class="text-secondary small d-block">Tasa de Citas Cumplidas</span>
                <strong class="text-info fs-6" id="anaTasaEfectividad">0%</strong>
              </div>
            </div>
          </div>
        </div>

        <!-- 6 Interactive Analytics Charts Grid -->
        <div class="row g-4">
          
          <!-- Gráfica 1: Facturación y Cobros por Fecha (Línea / Área) -->
          <div class="col-12 col-xl-8">
            <div class="barber-chart-card shadow-lg h-100">
              <div class="chart-header">
                <div>
                  <h5 class="chart-title"><i class="bi bi-graph-up text-warning"></i> Tendencia de Facturación & Cobros ($)</h5>
                  <p class="chart-subtitle">Historial de pagos recaudados en caja a lo largo de las fechas registradas</p>
                </div>
                <span class="badge bg-gold-subtle text-gold fw-bold">Ingresos Reales</span>
              </div>
              <div class="chart-container-box" style="height: 320px;">
                <canvas id="chartRevenueDate"></canvas>
              </div>
            </div>
          </div>

          <!-- Gráfica 2: Distribución del Estado de Citas (Doughnut) -->
          <div class="col-12 col-xl-4">
            <div class="barber-chart-card shadow-lg h-100">
              <div class="chart-header">
                <div>
                  <h5 class="chart-title"><i class="bi bi-pie-chart text-info"></i> Estado de las Citas</h5>
                  <p class="chart-subtitle">Proporción de reservas atendidas, pendientes y canceladas</p>
                </div>
              </div>
              <div class="chart-container-box d-flex align-items-center justify-content-center" style="height: 320px;">
                <canvas id="chartAppointmentStatus"></canvas>
              </div>
            </div>
          </div>

          <!-- Gráfica 3: Desempeño y Recaudación por Barbero (Barras Agrupadas) -->
          <div class="col-12 col-xl-6">
            <div class="barber-chart-card shadow-lg h-100">
              <div class="chart-header">
                <div>
                  <h5 class="chart-title"><i class="bi bi-person-lines-fill text-warning"></i> Rendimiento por Barbero</h5>
                  <p class="chart-subtitle">Citas atendidas vs Ingresos generados para la barbería</p>
                </div>
              </div>
              <div class="chart-container-box" style="height: 300px;">
                <canvas id="chartBarberPerformance"></canvas>
              </div>
            </div>
          </div>

          <!-- Gráfica 4: Servicios Más Populares y Solicitados (Doughnut) -->
          <div class="col-12 col-xl-6">
            <div class="barber-chart-card shadow-lg h-100">
              <div class="chart-header">
                <div>
                  <h5 class="chart-title"><i class="bi bi-scissors text-danger"></i> Servicios Más Solicitados</h5>
                  <p class="chart-subtitle">Cortes, afeitados y combos preferidos por la clientela</p>
                </div>
              </div>
              <div class="chart-container-box d-flex align-items-center justify-content-center" style="height: 300px;">
                <canvas id="chartPopularServices"></canvas>
              </div>
            </div>
          </div>

          <!-- Gráfica 5: Métodos de Pago Utilizados (Barras Horizontales) -->
          <div class="col-12 col-xl-6">
            <div class="barber-chart-card shadow-lg h-100">
              <div class="chart-header">
                <div>
                  <h5 class="chart-title"><i class="bi bi-credit-card-2-front text-success"></i> Métodos de Pago Más Utilizados</h5>
                  <p class="chart-subtitle">Preferencia de canales financieros (Efectivo, Pago Móvil, Punto, etc.)</p>
                </div>
              </div>
              <div class="chart-container-box" style="height: 280px;">
                <canvas id="chartPaymentMethods"></canvas>
              </div>
            </div>
          </div>

          <!-- Gráfica 6: Horarios Pico de Demanda (Barras) -->
          <div class="col-12 col-xl-6">
            <div class="barber-chart-card shadow-lg h-100">
              <div class="chart-header">
                <div>
                  <h5 class="chart-title"><i class="bi bi-clock-history text-primary"></i> Horarios de Mayor Demanda</h5>
                  <p class="chart-subtitle">Concentración de citas reservadas por franja horaria</p>
                </div>
              </div>
              <div class="chart-container-box" style="height: 280px;">
                <canvas id="chartAppointmentsByHour"></canvas>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- =======================================================
           PANEL B: GESTIÓN DE TABLA RELACIONAL (CRUD COMPLETO)
           ======================================================= -->
      <div id="adminCrudTableContainer" class="admin-tab-content" style="display: none;">
        <div class="card barber-card border-0 shadow-lg">
          
          <div class="card-header bg-transparent border-bottom border-secondary-subtle p-3 p-md-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
              <div>
                <h3 class="fw-bold text-light mb-1" id="currentTableTitle">Clientes</h3>
                <p class="text-secondary small mb-0" id="currentTableDesc">Gestión CRUD de clientes con restricciones de integridad de PostgreSQL.</p>
              </div>

              <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="input-group" style="max-width: 280px;">
                  <span class="input-group-text barber-input-icon"><i class="bi bi-search"></i></span>
                  <input type="text" class="form-control barber-input" id="tableSearchInput" placeholder="Buscar en esta tabla..." autocomplete="off">
                </div>

                <button type="button" class="btn btn-barber-gold fw-bold rounded-pill px-3 py-2 shadow-sm" id="btnOpenCreate" onclick="openCreateModal()">
                  <i class="bi bi-plus-circle-fill me-1"></i> <span id="btnAddRecordText">+ Nuevo Cliente</span>
                </button>
              </div>
            </div>
          </div>

          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0 barber-table" id="crudTable">
                <thead id="tableHead" class="table-dark">
                  <!-- Rendered by JS -->
                </thead>
                <tbody id="tableBody">
                  <!-- Rendered by JS -->
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>

    </section>

  </main>

  <!-- Admin Access Authentication Modal -->
  <div class="modal fade" id="adminAuthModal" tabindex="-1" aria-labelledby="adminAuthModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
      <div class="modal-content barber-card border-0 shadow-lg">
        <div class="modal-header border-bottom border-secondary-subtle">
          <h5 class="modal-title fw-bold text-light" id="adminAuthModalTitle">
            <i class="bi bi-shield-lock-fill text-warning me-2"></i>Acceso de Administrador
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <form id="adminAuthForm" onsubmit="submitAdminAuth(event)" autocomplete="off">
          <div class="modal-body p-4">
            <p class="text-secondary small mb-3">
              Para ingresar al panel de control CRUD y gestión financiera, introduce la clave de administrador.
            </p>
            
            <div class="mb-3">
              <label for="adminPasswordInput" class="form-label text-light small fw-bold">Clave de Administrador</label>
              <div class="input-group">
                <span class="input-group-text barber-input-icon"><i class="bi bi-key-fill"></i></span>
                <input type="password" class="form-control barber-input" id="adminPasswordInput" placeholder="Introduce la clave de acceso..." required autofocus>
              </div>
              <div class="alert alert-danger py-2 px-3 small mt-2" id="adminAuthError" style="display: none;">
                <i class="bi bi-x-circle-fill me-1"></i> Clave incorrecta. Intenta nuevamente.
              </div>
              <div class="p-2 mt-2 bg-dark-subtle border border-secondary-subtle rounded-3 small text-secondary">
                <i class="bi bi-info-circle text-warning me-1"></i> Clave de evaluación académica: <strong class="text-warning">admin123</strong>
              </div>
            </div>
          </div>
          <div class="modal-footer border-top border-secondary-subtle justify-content-between p-3">
            <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-barber-gold fw-bold rounded-pill px-4" id="btnSubmitAdminAuth">
              <i class="bi bi-unlock-fill me-1"></i> Ingresar al Panel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Create / Edit Record Modal (Bootstrap Modal) -->
  <div class="modal fade" id="formModal" tabindex="-1" aria-labelledby="modalFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content barber-card border-0 shadow-lg">
        <div class="modal-header border-bottom border-secondary-subtle">
          <h5 class="modal-title fw-bold text-light" id="modalFormTitle">+ Nuevo Registro</h5>
          <button type="button" class="btn-close btn-close-white modal-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <form id="recordForm" autocomplete="off">
          <div class="modal-body p-4">
            <div class="row g-3" id="formFieldsContainer">
              <!-- Dynamic Form Inputs Generated by JS -->
            </div>
          </div>
          <div class="modal-footer border-top border-secondary-subtle">
            <button type="button" class="btn btn-secondary rounded-pill px-4 modal-cancel" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-barber-gold fw-bold rounded-pill px-4" id="btnSubmitForm">Guardar Registro</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal (Bootstrap Modal) -->
  <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
      <div class="modal-content barber-card border-0 shadow-lg text-center">
        <div class="modal-body p-4">
          <div class="barber-delete-icon mx-auto mb-3">
            <i class="bi bi-exclamation-triangle-fill fs-1 text-danger"></i>
          </div>
          <h4 class="fw-bold text-light mb-2">Confirmar Eliminación</h4>
          <p class="text-secondary small mb-0" id="confirmDeleteMessage">¿Estás seguro de que deseas eliminar este registro?</p>
        </div>
        <div class="modal-footer border-top border-secondary-subtle justify-content-center p-3">
          <button type="button" class="btn btn-secondary rounded-pill px-4 modal-cancel" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-danger fw-bold rounded-pill px-4" id="btnConfirmDelete">Sí, Eliminar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Barber Payment & Complete Modal (Bootstrap Modal) -->
  <div class="modal fade" id="barberPaymentModal" tabindex="-1" aria-labelledby="barberPaymentModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
      <div class="modal-content barber-card border-0 shadow-lg">
        <div class="modal-header border-bottom border-secondary-subtle">
          <h5 class="modal-title fw-bold text-light" id="barberPaymentModalTitle">
            <i class="bi bi-credit-card-fill text-warning me-2"></i>Cobrar y Completar Servicio
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <form id="barberPaymentForm" onsubmit="submitBarberPayment(event)" autocomplete="off">
          <input type="hidden" id="barberPayCitaId" value="">
          <div class="modal-body p-4">
            
            <div class="p-3 mb-3 bg-dark-subtle border border-secondary-subtle rounded-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-secondary small">Cliente:</span>
                <strong class="text-light" id="barberPayClientName">-</strong>
              </div>
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-secondary small">Servicio:</span>
                <span class="text-warning fw-semibold" id="barberPayServiceName">-</span>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-secondary small">Horario:</span>
                <span class="text-light small" id="barberPayDateTime">-</span>
              </div>
            </div>

            <div class="mb-3">
              <label for="barberPayMonto" class="form-label text-light fw-bold small">Monto Total a Cobrar ($) <span class="text-danger">*</span></label>
              <div class="input-group input-group-lg">
                <span class="input-group-text barber-input-icon"><i class="bi bi-currency-dollar"></i></span>
                <input type="number" step="0.01" min="0.01" class="form-control barber-input fw-bold fs-5 text-warning" id="barberPayMonto" required>
              </div>
            </div>

            <div class="mb-3">
              <label for="barberPayMetodo" class="form-label text-light fw-bold small">Método de Pago <span class="text-danger">*</span></label>
              <select class="form-select barber-input form-select-lg" id="barberPayMetodo" required>
                <option value="Efectivo" selected>💵 Efectivo</option>
                <option value="Pago Móvil">📱 Pago Móvil</option>
                <option value="Transferencia">🏦 Transferencia Bancaria</option>
                <option value="Punto de Venta">💳 Punto de Venta</option>
                <option value="Tarjeta de Débito/Crédito">💳 Tarjeta de Débito/Crédito</option>
              </select>
            </div>

            <div class="p-2 bg-dark-subtle border border-warning-subtle rounded-3 small text-secondary">
              <i class="bi bi-info-circle-fill text-warning me-1"></i> El trigger de PostgreSQL marcará automáticamente la cita como <strong>Completada</strong> y registrará tu comisión.
            </div>

          </div>
          <div class="modal-footer border-top border-secondary-subtle justify-content-between p-3">
            <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-barber-gold fw-bold rounded-pill px-4" id="btnSubmitBarberPayment">
              <i class="bi bi-check2-circle me-1"></i> Registrar Cobro
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Toast Notification Container -->
  <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" aria-live="polite"></div>

  <!-- Bootstrap 5.3.3 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Main JavaScript Controller (Cache-Busting for instant updates) -->
  <script src="assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
