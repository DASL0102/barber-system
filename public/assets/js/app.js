/**
 * ==========================================================================
 * BARBERÍA ELITE - JAVASCRIPT CONTROLLER (BOOTSTRAP 5 & VANILLA JS)
 * Features:
 *  - Classic Barber Shop Theme with Luxury Gold, Red & Blue Accents
 *  - Customer Identification by unique ID/Cédula (prevents duplicate DB records)
 *  - Customer Self-Service Booking Flow (Service -> Barber -> Date/Time -> Confirm)
 *  - Customer Appointment Tracking & Cancellation
 *  - Protected Admin Access with Default Password ('admin123')
 *  - Full 8-Table Admin CRUD with Bootstrap Modals, Badges, Tables & Toasts
 *  - PostgreSQL 16 Integrity Constraint Error Mapping
 * ==========================================================================
 */

const API_BASE = 'api.php';
const ADMIN_PASSWORD_DEFAULT = 'admin123'; // Default academic evaluation key

// Application State
const state = {
  viewMode: 'client', // 'client' or 'admin'
  activeClient: JSON.parse(localStorage.getItem('barberia_active_client') || 'null'),
  isAdminAuthenticated: sessionStorage.getItem('barberia_admin_authenticated') === 'true',
  clientBooking: {
    serviceId: null,
    barberId: null,
    date: null,
    time: null
  },
  clientAppointments: [],
  currentTab: 'clientes',
  data: {},
  counts: {},
  options: {
    clientes: [],
    barberos: [],
    turnos: [],
    servicios: [],
    citas: []
  },
  stats: {},
  filterText: '',
  editingId: null,
  deletingId: null,
  isSubmitting: false
};

// Configuration for each of the 8 relational tables
const TABLES_CONFIG = {
  clientes: {
    title: 'Clientes',
    singular: 'Cliente',
    icon: '👥',
    pk: 'id_cliente',
    columns: [
      { key: 'id_cliente', label: 'ID', width: '70px' },
      { key: 'cedula', label: 'Cédula / Identificación', bold: true, mono: true },
      { key: 'nombre', label: 'Nombre Completo' },
      { key: 'telefono', label: 'Teléfono / WhatsApp' },
      { key: 'email', label: 'Correo', fallback: 'Sin correo' },
      { key: 'fecha_registro', label: 'Fecha de Registro', type: 'datetime' }
    ],
    fields: [
      { name: 'cedula', label: 'Cédula / Identificación (Solo números, ÚNICA)', type: 'text', required: true, placeholder: 'Ej. 28456123', span: 1, numericOnly: true },
      { name: 'telefono', label: 'Teléfono / WhatsApp', type: 'text', required: true, placeholder: 'Ej. +58 412 1234567', span: 1 },
      { name: 'nombre', label: 'Nombre Completo', type: 'text', required: true, placeholder: 'Ej. Gabriel Torres', span: 2 },
      { name: 'email', label: 'Correo Electrónico (Opcional)', type: 'email', required: false, placeholder: 'Ej. gabriel@gmail.com', span: 2 }
    ]
  },

  barberos: {
    title: 'Barberos',
    singular: 'Barbero',
    icon: '💈',
    pk: 'id_barbero',
    columns: [
      { key: 'id_barbero', label: 'ID', width: '70px' },
      { key: 'nombre', label: 'Nombre del Barbero', bold: true },
      { key: 'telefono', label: 'Teléfono', fallback: 'Sin teléfono' },
      { key: 'porcentaje_comision', label: '% Comisión', type: 'percent' },
      { key: 'estado', label: 'Estado', type: 'badge' }
    ],
    fields: [
      { name: 'nombre', label: 'Nombre del Barbero', type: 'text', required: true, placeholder: 'Ej. Alejandro Morales', span: 2 },
      { name: 'telefono', label: 'Teléfono de Contacto', type: 'text', required: false, placeholder: 'Ej. +58 414 9876543' },
      { name: 'porcentaje_comision', label: '% Comisión por Servicio', type: 'number', step: '0.01', min: '0', max: '100', defaultValue: '50.00', required: true },
      { name: 'estado', label: 'Estado Operativo', type: 'select', required: true, options: [
        { value: 'Activo', label: 'Activo' },
        { value: 'Inactivo', label: 'Inactivo' }
      ], defaultValue: 'Activo', span: 2 }
    ]
  },

  turnos_trabajo: {
    title: 'Turnos de Trabajo',
    singular: 'Turno de Trabajo',
    icon: '⏱️',
    pk: 'id_turno',
    columns: [
      { key: 'id_turno', label: 'ID', width: '70px' },
      { key: 'nombre_turno', label: 'Nombre del Turno', bold: true },
      { key: 'hora_inicio', label: 'Hora Entrada', type: 'time' },
      { key: 'hora_fin', label: 'Hora Salida', type: 'time' }
    ],
    fields: [
      { name: 'nombre_turno', label: 'Nombre Descriptivo del Turno', type: 'text', required: true, placeholder: 'Ej. Turno Mañana', span: 2 },
      { name: 'hora_inicio', label: 'Hora Inicio (Entrada)', type: 'time', required: true },
      { name: 'hora_fin', label: 'Hora Fin (Salida)', type: 'time', required: true }
    ]
  },

  horarios_barbero: {
    title: 'Horarios de Barbero',
    singular: 'Asignación de Horario',
    icon: '📅',
    pk: 'id_horario',
    columns: [
      { key: 'id_horario', label: 'ID', width: '70px' },
      { key: 'barbero_nombre', label: 'Barbero Asignado', bold: true },
      { key: 'dia_semana', label: 'Día de la Semana', type: 'day' },
      { key: 'nombre_turno', label: 'Turno Asignado', type: 'turno_details' }
    ],
    fields: [
      { name: 'id_barbero', label: 'Barbero', type: 'select_barbero', required: true, span: 2 },
      { name: 'id_turno', label: 'Turno de Trabajo', type: 'select_turno', required: true },
      { name: 'dia_semana', label: 'Día de la Semana', type: 'select', required: true, options: [
        { value: 'Lunes', label: 'Lunes' },
        { value: 'Martes', label: 'Martes' },
        { value: 'Miércoles', label: 'Miércoles' },
        { value: 'Jueves', label: 'Jueves' },
        { value: 'Viernes', label: 'Viernes' },
        { value: 'Sábado', label: 'Sábado' },
        { value: 'Domingo', label: 'Domingo' }
      ]}
    ]
  },

  servicios: {
    title: 'Servicios',
    singular: 'Servicio',
    icon: '✂️',
    pk: 'id_servicio',
    columns: [
      { key: 'id_servicio', label: 'ID', width: '70px' },
      { key: 'nombre', label: 'Nombre del Servicio', bold: true },
      { key: 'descripcion', label: 'Descripción', fallback: 'Sin descripción' },
      { key: 'precio', label: 'Precio', type: 'currency' },
      { key: 'duracion_minutos', label: 'Duración', type: 'duration' },
      { key: 'estado', label: 'Estado', type: 'badge' }
    ],
    fields: [
      { name: 'nombre', label: 'Nombre del Servicio', type: 'text', required: true, placeholder: 'Ej. Corte Ejecutivo', span: 2 },
      { name: 'descripcion', label: 'Descripción del Servicio', type: 'textarea', required: false, placeholder: 'Detalles del servicio, técnicas...', span: 2 },
      { name: 'precio', label: 'Precio ($ USD)', type: 'number', step: '0.01', min: '0.01', placeholder: '15.00', required: true },
      { name: 'duracion_minutos', label: 'Duración Estimada (Minutos)', type: 'number', min: '5', max: '480', defaultValue: '30', required: true },
      { name: 'estado', label: 'Disponibilidad en Catálogo', type: 'select', required: true, options: [
        { value: 'Activo', label: 'Activo' },
        { value: 'Inactivo', label: 'Inactivo' }
      ], defaultValue: 'Activo', span: 2 }
    ]
  },

  citas: {
    title: 'Citas y Reservas',
    singular: 'Cita',
    icon: '📋',
    pk: 'id_cita',
    columns: [
      { key: 'id_cita', label: 'ID Cita', width: '75px' },
      { key: 'cliente_nombre', label: 'Cliente', bold: true, subtextKey: 'cliente_cedula' },
      { key: 'barbero_nombre', label: 'Barbero' },
      { key: 'servicio_nombre', label: 'Servicio Solicitado', type: 'service_price' },
      { key: 'fecha', label: 'Fecha', type: 'date' },
      { key: 'hora', label: 'Hora', type: 'time' },
      { key: 'estado', label: 'Estado', type: 'badge' }
    ],
    fields: [
      { name: 'id_cliente', label: 'Cliente', type: 'select_cliente', required: true },
      { name: 'id_barbero', label: 'Barbero', type: 'select_barbero', required: true },
      { name: 'id_servicio', label: 'Servicio', type: 'select_servicio', required: true, span: 2 },
      { name: 'fecha', label: 'Fecha de la Cita', type: 'date', required: true },
      { name: 'hora', label: 'Hora Programada', type: 'time', required: true },
      { name: 'estado', label: 'Estado de la Cita', type: 'select', required: true, options: [
        { value: 'Pendiente', label: 'Pendiente' },
        { value: 'Confirmada', label: 'Confirmada' },
        { value: 'Completada', label: 'Completada' },
        { value: 'Cancelada', label: 'Cancelada' }
      ], defaultValue: 'Pendiente', span: 2 }
    ]
  },

  pagos: {
    title: 'Pagos e Ingresos',
    singular: 'Pago',
    icon: '💳',
    pk: 'id_pago',
    columns: [
      { key: 'id_pago', label: 'ID Pago', width: '80px' },
      { key: 'id_cita', label: 'ID Cita Relacionada', type: 'cita_reference' },
      { key: 'cliente_nombre', label: 'Cliente / Servicio', bold: true, subtextKey: 'servicio_nombre' },
      { key: 'monto', label: 'Monto Pagado', type: 'currency' },
      { key: 'metodo_pago', label: 'Método de Pago', type: 'metodo' },
      { key: 'fecha_pago', label: 'Fecha y Hora Pago', type: 'datetime' }
    ],
    fields: [
      { name: 'id_cita', label: 'Cita a Cobrar (Relación 1:1)', type: 'select_cita', required: true, span: 2 },
      { name: 'monto', label: 'Monto a Cobrar ($)', type: 'number', step: '0.01', min: '0.01', required: true },
      { name: 'metodo_pago', label: 'Método de Pago', type: 'select', required: true, options: [
        { value: 'Efectivo', label: 'Efectivo' },
        { value: 'Transferencia', label: 'Transferencia' },
        { value: 'Punto de Venta', label: 'Punto de Venta' },
        { value: 'Pago Móvil', label: 'Pago Móvil' },
        { value: 'Tarjeta de Débito/Crédito', label: 'Tarjeta de Débito/Crédito' }
      ], defaultValue: 'Efectivo' },
      { name: 'fecha_pago', label: 'Fecha y Hora del Pago (Opcional, defecto ahora)', type: 'datetime-local', required: false, span: 2 }
    ]
  },

  liquidaciones_barbero: {
    title: 'Liquidaciones de Barbero',
    singular: 'Liquidación',
    icon: '💰',
    pk: 'id_liquidacion',
    columns: [
      { key: 'id_liquidacion', label: 'ID', width: '70px' },
      { key: 'barbero_nombre', label: 'Barbero', bold: true },
      { key: 'fecha_corte', label: 'Fecha de Corte', type: 'date' },
      { key: 'total_servicios', label: 'Total Servicios', type: 'number_count' },
      { key: 'monto_comision', label: 'Monto Comisión', type: 'currency' },
      { key: 'estado_pago', label: 'Estado Pago', type: 'badge' }
    ],
    fields: [
      { name: 'id_barbero', label: 'Barbero', type: 'select_barbero', required: true, span: 2 },
      { name: 'fecha_corte', label: 'Fecha de Corte', type: 'date', required: true },
      { name: 'total_servicios', label: 'Total de Servicios Realizados', type: 'number', min: '0', defaultValue: '1', required: true },
      { name: 'monto_comision', label: 'Monto de Comisión Calculado ($)', type: 'number', step: '0.01', min: '0', required: true },
      { name: 'estado_pago', label: 'Estado del Pago', type: 'select', required: true, options: [
        { value: 'Pendiente', label: 'Pendiente' },
        { value: 'Pagado', label: 'Pagado' }
      ], defaultValue: 'Pendiente' }
    ]
  }
};

// ==========================================================================
// INITIALIZATION
// ==========================================================================
document.addEventListener('DOMContentLoaded', () => {
  initViewModes();
  initClientAuth();
  initClientBooking();
  initTabs();
  initSearch();
  initModals();
  initActions();

  // Load initial global data
  loadOptions().then(() => {
    const dateInput = document.getElementById('bookingDate');
    if (dateInput) {
      dateInput.min = new Date().toISOString().split('T')[0];
    }

    if (state.activeClient) {
      setupLoggedInClient(state.activeClient);
    } else {
      setupLoggedOutClient();
    }

    switchViewMode(state.viewMode);
  });
});

// ==========================================================================
// VIEW MODES & ADMIN AUTHENTICATION
// ==========================================================================
let adminAuthModalInstance = null;
function getAdminAuthModal() {
  if (!adminAuthModalInstance) {
    adminAuthModalInstance = bootstrap.Modal.getOrCreateInstance(document.getElementById('adminAuthModal'));
  }
  return adminAuthModalInstance;
}

window.handleAdminAccess = function() {
  const isAuth = sessionStorage.getItem('barberia_admin_authenticated') === 'true';
  if (isAuth) {
    switchViewMode('admin');
  } else {
    // Open Admin Access Modal
    const passInput = document.getElementById('adminPasswordInput');
    const errAlert = document.getElementById('adminAuthError');
    if (passInput) passInput.value = '';
    if (errAlert) errAlert.style.display = 'none';
    getAdminAuthModal().show();
  }
};

window.submitAdminAuth = function(e) {
  if (e) e.preventDefault();
  const passInput = document.getElementById('adminPasswordInput');
  const errAlert = document.getElementById('adminAuthError');
  const entered = passInput ? passInput.value.trim() : '';

  if (entered === ADMIN_PASSWORD_DEFAULT || entered === 'admin') {
    sessionStorage.setItem('barberia_admin_authenticated', 'true');
    state.isAdminAuthenticated = true;
    getAdminAuthModal().hide();
    switchViewMode('admin');
    showToast('¡Acceso Concedido!', 'Bienvenido al Panel de Administración de Barbería Elite.', 'success');
  } else {
    if (errAlert) errAlert.style.display = 'block';
    if (passInput) {
      passInput.focus();
      passInput.select();
    }
  }
};

window.logoutAdmin = function() {
  sessionStorage.removeItem('barberia_admin_authenticated');
  state.isAdminAuthenticated = false;
  switchViewMode('client');
  showToast('Sesión de Admin Finalizada', 'Has regresado al portal de clientes.', 'info');
};

function initViewModes() {
  const btnModeClient = document.getElementById('btnModeClient');
  const btnModeAdmin = document.getElementById('btnModeAdmin');

  if (btnModeClient) {
    btnModeClient.addEventListener('click', () => switchViewMode('client'));
  }
  if (btnModeAdmin) {
    btnModeAdmin.addEventListener('click', () => handleAdminAccess());
  }
}

window.switchViewMode = function(mode) {
  state.viewMode = mode;
  const btnModeClient = document.getElementById('btnModeClient');
  const btnModeAdmin = document.getElementById('btnModeAdmin');
  const clientView = document.getElementById('clientPortalView');
  const adminView = document.getElementById('adminPanelView');
  const adminActions = document.getElementById('adminHeaderActions');
  const clientWidget = document.getElementById('clientSessionWidget');

  if (mode === 'client') {
    if (btnModeClient) btnModeClient.classList.add('active');
    if (btnModeAdmin) btnModeAdmin.classList.remove('active');
    if (clientView) clientView.style.display = 'block';
    if (adminView) adminView.style.display = 'none';
    if (adminActions) adminActions.style.display = 'none';

    if (state.activeClient) {
      if (clientWidget) clientWidget.style.display = 'flex';
      renderClientBookingCatalog();
    } else {
      if (clientWidget) clientWidget.style.display = 'none';
    }
  } else {
    // Mode is admin
    if (btnModeAdmin) btnModeAdmin.classList.add('active');
    if (btnModeClient) btnModeClient.classList.remove('active');
    if (clientView) clientView.style.display = 'none';
    if (adminView) adminView.style.display = 'block';
    if (adminActions) adminActions.style.display = 'flex';
    if (clientWidget) clientWidget.style.display = 'none';

    loadStats();
    loadTable(state.currentTab);
  }
};

// ==========================================================================
// CLIENT PORTAL: AUTHENTICATION & TABS
// ==========================================================================
window.switchAuthTab = function(tab) {
  const tabLogin = document.getElementById('tabBtnLogin');
  const tabRegister = document.getElementById('tabBtnRegister');
  const panelLogin = document.getElementById('panelLogin');
  const panelRegister = document.getElementById('panelRegister');

  if (tab === 'register') {
    if (tabRegister) tabRegister.classList.add('active');
    if (tabLogin) tabLogin.classList.remove('active');
    if (panelRegister) panelRegister.style.display = 'block';
    if (panelLogin) panelLogin.style.display = 'none';
  } else {
    if (tabLogin) tabLogin.classList.add('active');
    if (tabRegister) tabRegister.classList.remove('active');
    if (panelLogin) panelLogin.style.display = 'block';
    if (panelRegister) panelRegister.style.display = 'none';
  }
};

function initClientAuth() {
  const tabLogin = document.getElementById('tabBtnLogin');
  const tabRegister = document.getElementById('tabBtnRegister');

  if (tabLogin) tabLogin.addEventListener('click', () => switchAuthTab('login'));
  if (tabRegister) tabRegister.addEventListener('click', () => switchAuthTab('register'));

  // Switch to register button if not found
  const btnSwitch = document.getElementById('btnSwitchToRegister');
  if (btnSwitch) {
    btnSwitch.addEventListener('click', () => {
      const typedCedula = document.getElementById('loginCedula').value.replace(/[^0-9]/g, '');
      document.getElementById('regCedula').value = typedCedula;
      switchAuthTab('register');
      document.getElementById('regNombre').focus();
    });
  }

  // Client Login Form (Lookup by cedula)
  const formLogin = document.getElementById('formClientLogin');
  if (formLogin) {
    formLogin.addEventListener('submit', async (e) => {
      e.preventDefault();
      const rawCedula = document.getElementById('loginCedula').value.trim();
      const cedula = rawCedula.replace(/[^0-9]/g, '');
      if (!cedula) {
        showToast('Atención', 'Ingresa tu número de identificación (solo números).', 'warning');
        return;
      }
      if (cedula.length < 4) {
        showToast('Atención', 'El número de identificación debe contener al menos 4 dígitos numéricos.', 'warning');
        return;
      }

      const alertNotFound = document.getElementById('alertLoginNotFound');
      if (alertNotFound) alertNotFound.style.display = 'none';

      const submitBtn = document.getElementById('btnSubmitLogin');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Consultando en base de datos...';
      }

      try {
        const res = await apiRequest(`${API_BASE}?action=find_client&cedula=${encodeURIComponent(cedula)}`);
        if (res.found && res.data) {
          setupLoggedInClient(res.data);
          showToast('¡Bienvenido!', `Hola de nuevo, ${res.data.nombre}. Ya puedes seleccionar tu turno.`, 'success');
        } else {
          if (alertNotFound) alertNotFound.style.display = 'flex';
        }
      } catch (err) {
        // Handled in apiRequest
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<span>Ingresar y Solicitar Cita</span> <i class="bi bi-arrow-right-circle ms-1"></i>';
        }
      }
    });
  }

  // Client Register Form (Insert new client into DB with UNIQUE constraint)
  const formRegister = document.getElementById('formClientRegister');
  if (formRegister) {
    formRegister.addEventListener('submit', async (e) => {
      e.preventDefault();
      const rawCedula = document.getElementById('regCedula').value.trim();
      const cedula = rawCedula.replace(/[^0-9]/g, '');
      const nombre = document.getElementById('regNombre').value.trim();
      const telefono = document.getElementById('regTelefono').value.trim();
      const email = document.getElementById('regEmail').value.trim();

      if (!cedula || cedula.length < 4) {
        showToast('Atención', 'El número de identificación debe contener únicamente dígitos numéricos (mínimo 4 dígitos, sin letras ni guiones).', 'warning');
        return;
      }

      const submitBtn = document.getElementById('btnSubmitRegister');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Registrando en PostgreSQL...';
      }

      try {
        const res = await apiRequest(`${API_BASE}?resource=clientes`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ cedula, nombre, telefono, email })
        });

        if (res.data) {
          setupLoggedInClient(res.data);
          showToast('¡Registro Exitoso!', `Bienvenido, ${res.data.nombre}. Tu perfil ha sido creado con éxito.`, 'success');
          formRegister.reset();
        }
      } catch (err) {
        // Handled in apiRequest
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<span>Guardar y Continuar a Reservar</span> <i class="bi bi-check-circle-fill ms-1"></i>';
        }
      }
    });
  }

  // Logout Buttons
  const btnLogout = document.getElementById('btnLogoutClient');
  const btnExit = document.getElementById('btnExitClientSession');
  const doLogout = () => {
    state.activeClient = null;
    localStorage.removeItem('barberia_active_client');
    setupLoggedOutClient();
    showToast('Sesión Finalizada', 'Has salido de tu sesión de cliente.', 'info');
  };

  if (btnLogout) btnLogout.addEventListener('click', doLogout);
  if (btnExit) btnExit.addEventListener('click', doLogout);
}

function setupLoggedInClient(client) {
  state.activeClient = client;
  localStorage.setItem('barberia_active_client', JSON.stringify(client));

  // Update DOM displays
  const dashName = document.getElementById('dashClientName');
  const dashCedula = document.getElementById('dashClientCedula');
  const dashPhone = document.getElementById('dashClientPhone');
  const sessName = document.getElementById('sessionClientName');
  const sessCedula = document.getElementById('sessionClientCedula');

  if (dashName) dashName.innerText = client.nombre;
  if (dashCedula) dashCedula.innerText = client.cedula;
  if (dashPhone) dashPhone.innerText = client.telefono;
  if (sessName) sessName.innerText = client.nombre.split(' ')[0];
  if (sessCedula) sessCedula.innerText = client.cedula;

  if (state.viewMode === 'client') {
    const widget = document.getElementById('clientSessionWidget');
    if (widget) widget.style.display = 'flex';
  }

  // Toggle screens
  const authSec = document.getElementById('clientAuthSection');
  const dashSec = document.getElementById('clientDashboardSection');
  if (authSec) authSec.style.display = 'none';
  if (dashSec) dashSec.style.display = 'block';

  renderClientBookingCatalog();
  loadClientAppointments();
}

function setupLoggedOutClient() {
  const authSec = document.getElementById('clientAuthSection');
  const dashSec = document.getElementById('clientDashboardSection');
  const widget = document.getElementById('clientSessionWidget');
  const loginInput = document.getElementById('loginCedula');
  const alertNotFound = document.getElementById('alertLoginNotFound');

  if (authSec) authSec.style.display = 'block';
  if (dashSec) dashSec.style.display = 'none';
  if (widget) widget.style.display = 'none';
  if (loginInput) loginInput.value = '';
  if (alertNotFound) alertNotFound.style.display = 'none';
}

// ==========================================================================
// CLIENT PORTAL: BOOKING WIZARD & APPOINTMENTS
// ==========================================================================
function initClientBooking() {
  const btnNavBook = document.getElementById('btnNavBookAppointment');
  const btnNavAppts = document.getElementById('btnNavMyAppointments');
  const boxBook = document.getElementById('clientBookAppointmentBox');
  const boxAppts = document.getElementById('clientMyAppointmentsBox');

  if (btnNavBook) {
    btnNavBook.addEventListener('click', () => {
      btnNavBook.classList.add('active');
      btnNavAppts.classList.remove('active');
      if (boxBook) boxBook.style.display = 'block';
      if (boxAppts) boxAppts.style.display = 'none';
    });
  }

  if (btnNavAppts) {
    btnNavAppts.addEventListener('click', () => {
      btnNavAppts.classList.add('active');
      btnNavBook.classList.remove('active');
      if (boxAppts) boxAppts.style.display = 'block';
      if (boxBook) boxBook.style.display = 'none';
      loadClientAppointments();
    });
  }

  const btnRefreshAppts = document.getElementById('btnRefreshClientAppts');
  if (btnRefreshAppts) {
    btnRefreshAppts.addEventListener('click', () => {
      loadClientAppointments();
      showToast('Actualizado', 'Historial de reservas sincronizado.', 'info');
    });
  }

  const dateInput = document.getElementById('bookingDate');
  if (dateInput) {
    dateInput.addEventListener('change', (e) => {
      state.clientBooking.date = e.target.value;
      updateBookingSummary();
    });
  }

  const timeBtns = document.querySelectorAll('.time-slot-btn');
  timeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      timeBtns.forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
      state.clientBooking.time = btn.getAttribute('data-time');
      const hiddenTime = document.getElementById('selectedBookingTime');
      if (hiddenTime) hiddenTime.value = state.clientBooking.time;
      updateBookingSummary();
    });
  });

  const btnConfirm = document.getElementById('btnConfirmBooking');
  if (btnConfirm) {
    btnConfirm.addEventListener('click', handleClientBookingSubmit);
  }
}

function renderClientBookingCatalog() {
  // 1. Render Active Services
  const servicesGrid = document.getElementById('clientServicesGrid');
  if (servicesGrid) {
    const activeServices = (state.options.servicios || []).filter(s => s.estado === 'Activo');

    if (activeServices.length === 0) {
      servicesGrid.innerHTML = `<div class="text-center py-4 text-secondary col-12">No hay servicios disponibles en este momento.</div>`;
    } else {
      servicesGrid.innerHTML = activeServices.map(s => `
        <div class="selector-card ${state.clientBooking.serviceId == s.id_servicio ? 'selected' : ''}" 
             onclick="selectBookingService(${s.id_servicio})" 
             id="serviceCard_${s.id_servicio}">
          <div>
            <div class="selector-card-top">
              <div class="selector-card-title">${escapeHtml(s.nombre)}</div>
              <span class="selector-card-badge">⏱️ ${s.duracion_minutos} min</span>
            </div>
            <div class="selector-card-desc">${escapeHtml(s.descripcion || 'Servicio profesional de barbería')}</div>
          </div>
          <div class="selector-card-bottom">
            <div class="selector-card-price">$${parseFloat(s.precio).toFixed(2)}</div>
            <div class="selector-card-check">${state.clientBooking.serviceId == s.id_servicio ? '<i class="bi bi-check-circle-fill me-1"></i> Elegido' : '<i class="bi bi-plus-circle me-1"></i> Elegir'}</div>
          </div>
        </div>
      `).join('');
    }
  }

  // 2. Render Active Barbers
  const barbersGrid = document.getElementById('clientBarbersGrid');
  if (barbersGrid) {
    const activeBarbers = (state.options.barberos || []).filter(b => b.estado === 'Activo');

    if (activeBarbers.length === 0) {
      barbersGrid.innerHTML = `<div class="text-center py-4 text-secondary col-12">No hay barberos activos disponibles en este momento.</div>`;
    } else {
      barbersGrid.innerHTML = activeBarbers.map(b => `
        <div class="selector-card barber-selector-card ${state.clientBooking.barberId == b.id_barbero ? 'selected' : ''}" 
             onclick="selectBookingBarber(${b.id_barbero})"
             id="barberCard_${b.id_barbero}">
          <div class="barber-avatar-thumb">💈</div>
          <div class="barber-details">
            <div class="barber-name">${escapeHtml(b.nombre)}</div>
            <div class="barber-phone">${escapeHtml(b.telefono || 'Especialista en cortes y perfilado')}</div>
          </div>
          <div class="selector-card-check">${state.clientBooking.barberId == b.id_barbero ? '<i class="bi bi-check-circle-fill me-1"></i> Elegido' : '<i class="bi bi-plus-circle me-1"></i> Elegir'}</div>
        </div>
      `).join('');
    }
  }

  updateBookingSummary();
}

window.selectBookingService = function(id) {
  state.clientBooking.serviceId = id;
  document.querySelectorAll('#clientServicesGrid .selector-card').forEach(c => {
    c.classList.remove('selected');
    const chk = c.querySelector('.selector-card-check');
    if (chk) chk.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Elegir';
  });

  const card = document.getElementById(`serviceCard_${id}`);
  if (card) {
    card.classList.add('selected');
    const chk = card.querySelector('.selector-card-check');
    if (chk) chk.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Elegido';
  }

  updateBookingSummary();
};

window.selectBookingBarber = function(id) {
  state.clientBooking.barberId = id;
  document.querySelectorAll('#clientBarbersGrid .selector-card').forEach(c => {
    c.classList.remove('selected');
    const chk = c.querySelector('.selector-card-check');
    if (chk) chk.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Elegir';
  });

  const card = document.getElementById(`barberCard_${id}`);
  if (card) {
    card.classList.add('selected');
    const chk = card.querySelector('.selector-card-check');
    if (chk) chk.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Elegido';
  }

  updateBookingSummary();
};

function updateBookingSummary() {
  const service = (state.options.servicios || []).find(s => s.id_servicio == state.clientBooking.serviceId);
  const barber = (state.options.barberos || []).find(b => b.id_barbero == state.clientBooking.barberId);

  const elService = document.getElementById('summaryService');
  const elBarber = document.getElementById('summaryBarber');
  const elDateTime = document.getElementById('summaryDateTime');
  const elPrice = document.getElementById('summaryPrice');

  if (elService) {
    if (service) {
      elService.innerHTML = `<span class="text-warning">${escapeHtml(service.nombre)}</span> (${service.duracion_minutos} min)`;
      if (elPrice) elPrice.innerText = `$${parseFloat(service.precio).toFixed(2)}`;
    } else {
      elService.innerText = 'Ninguno seleccionado (Paso 1)';
      if (elPrice) elPrice.innerText = '$0.00';
    }
  }

  if (elBarber) {
    if (barber) {
      elBarber.innerHTML = `<span class="text-info">${escapeHtml(barber.nombre)}</span>`;
    } else {
      elBarber.innerText = 'Ninguno seleccionado (Paso 2)';
    }
  }

  if (elDateTime) {
    if (state.clientBooking.date && state.clientBooking.time) {
      elDateTime.innerHTML = `<strong class="text-light">${state.clientBooking.date}</strong> a las <strong class="text-warning">${state.clientBooking.time.substring(0, 5)}</strong>`;
    } else if (state.clientBooking.date) {
      elDateTime.innerText = `${state.clientBooking.date} (Falta elegir hora)`;
    } else {
      elDateTime.innerText = 'Por seleccionar (Paso 3)';
    }
  }
}

async function handleClientBookingSubmit() {
  if (!state.activeClient) {
    showToast('Atención', 'Debes identificarte antes de agendar una cita.', 'warning');
    return;
  }

  const { serviceId, barberId, date, time } = state.clientBooking;

  if (!serviceId) {
    showToast('Paso 1 Incompleto', 'Por favor selecciona un servicio del catálogo.', 'warning');
    return;
  }

  if (!barberId) {
    showToast('Paso 2 Incompleto', 'Por favor selecciona tu barbero.', 'warning');
    return;
  }

  if (!date) {
    showToast('Paso 3 Incompleto', 'Por favor selecciona la fecha de tu cita.', 'warning');
    return;
  }

  if (!time) {
    showToast('Paso 3 Incompleto', 'Por favor selecciona un turno u horario.', 'warning');
    return;
  }

  const btnConfirm = document.getElementById('btnConfirmBooking');
  if (btnConfirm) {
    btnConfirm.disabled = true;
    btnConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Procesando reserva en PostgreSQL...';
  }

  try {
    const payload = {
      id_cliente: state.activeClient.id_cliente,
      id_barbero: barberId,
      id_servicio: serviceId,
      fecha: date,
      hora: time,
      estado: 'Pendiente'
    };

    const res = await apiRequest(`${API_BASE}?resource=citas`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    showToast('¡Cita Confirmada!', 'Tu turno ha sido registrado exitosamente en la base de datos.', 'success');

    state.clientBooking.serviceId = null;
    state.clientBooking.barberId = null;
    state.clientBooking.date = null;
    state.clientBooking.time = null;
    const bookingDateInput = document.getElementById('bookingDate');
    if (bookingDateInput) bookingDateInput.value = '';
    document.querySelectorAll('.time-slot-btn').forEach(b => b.classList.remove('selected'));
    renderClientBookingCatalog();

    const navAppts = document.getElementById('btnNavMyAppointments');
    if (navAppts) navAppts.click();
  } catch (err) {
    // Handled in apiRequest
  } finally {
    if (btnConfirm) {
      btnConfirm.disabled = false;
      btnConfirm.innerHTML = '<i class="bi bi-calendar-check-fill me-2"></i> Confirmar y Reservar Cita';
    }
  }
}

async function loadClientAppointments() {
  if (!state.activeClient) return;

  const container = document.getElementById('clientApptsList');
  if (!container) return;

  container.innerHTML = `<div class="text-center py-5 text-secondary col-12">Cargando tus citas agendadas...</div>`;

  try {
    const res = await apiRequest(`${API_BASE}?action=client_citas&id_cliente=${state.activeClient.id_cliente}`);
    state.clientAppointments = res.data || [];

    const badgeCount = document.getElementById('badgeClientApptCount');
    if (badgeCount) badgeCount.innerText = state.clientAppointments.length;

    if (state.clientAppointments.length === 0) {
      container.innerHTML = `
        <div class="col-12 text-center py-5">
          <i class="bi bi-calendar-x fs-1 text-secondary mb-2 d-block"></i>
          <h4 class="text-light fw-bold">No tienes citas agendadas</h4>
          <p class="text-secondary small mb-3">Aún no has solicitado turnos. ¡Agenda tu primer corte o afeitado ahora!</p>
          <button class="btn btn-barber-gold fw-bold rounded-pill px-4" onclick="document.getElementById('btnNavBookAppointment').click()">
            <i class="bi bi-plus-circle me-1"></i> Solicitar Mi Primera Cita
          </button>
        </div>
      `;
      return;
    }

    container.innerHTML = state.clientAppointments.map(c => {
      let badgeClass = 'bg-secondary';
      if (c.estado === 'Completada') badgeClass = 'bg-success text-white';
      else if (c.estado === 'Pendiente') badgeClass = 'bg-warning text-dark';
      else if (c.estado === 'Confirmada') badgeClass = 'bg-info text-dark';
      else if (c.estado === 'Cancelada') badgeClass = 'bg-danger text-white';

      const canCancel = c.estado === 'Pendiente';

      return `
        <div class="client-appt-card">
          <div class="client-appt-header">
            <span class="client-appt-id"><i class="bi bi-ticket-perforated me-1"></i> Cita #${c.id_cita}</span>
            <span class="badge ${badgeClass} rounded-pill px-2 py-1">${escapeHtml(c.estado)}</span>
          </div>

          <div class="client-appt-body">
            <div class="client-appt-service text-truncate">${escapeHtml(c.servicio_nombre)}</div>
            <div class="client-appt-detail">
              <span><i class="bi bi-scissors text-warning me-1"></i> Barbero:</span> <strong>${escapeHtml(c.barbero_nombre)}</strong>
            </div>
            <div class="client-appt-detail">
              <span><i class="bi bi-calendar-event text-info me-1"></i> Fecha:</span> <strong>${c.fecha}</strong> &bull; <strong>${String(c.hora).substring(0, 5)}</strong>
            </div>
            <div class="client-appt-price">$${parseFloat(c.servicio_precio).toFixed(2)}</div>
          </div>

          <div class="client-appt-footer">
            ${canCancel ? `
              <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="cancelClientAppt(${c.id_cita})">
                <i class="bi bi-x-circle me-1"></i> Cancelar Cita
              </button>
            ` : `
              <small class="text-secondary">${c.estado === 'Completada' ? 'Servicio atendido' : 'No cancelable'}</small>
            `}
          </div>
        </div>
      `;
    }).join('');

  } catch (err) {
    container.innerHTML = `<div class="col-12 text-center py-4 text-danger">Error al cargar las citas del cliente.</div>`;
  }
}

window.cancelClientAppt = async function(idCita) {
  if (!confirm('¿Estás seguro de que deseas cancelar esta reserva?')) return;

  try {
    const res = await apiRequest(`${API_BASE}?action=client_cancel_cita`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id_cita: idCita,
        id_cliente: state.activeClient.id_cliente
      })
    });

    showToast('Cita Cancelada', res.message || 'Tu cita ha sido cancelada.', 'info');
    loadClientAppointments();
  } catch (err) {
    // Handled in apiRequest
  }
};

// ==========================================================================
// ADMIN CRUD: TABS & NAVIGATION
// ==========================================================================
function initTabs() {
  const tabButtons = document.querySelectorAll('.tab-btn');
  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetTable = btn.getAttribute('data-table');
      if (targetTable && targetTable !== state.currentTab) {
        switchTab(targetTable);
      }
    });
  });
}

function switchTab(table) {
  state.currentTab = table;
  state.filterText = '';
  const searchInput = document.getElementById('tableSearchInput');
  if (searchInput) searchInput.value = '';

  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.toggle('active', btn.getAttribute('data-table') === table);
  });

  const config = TABLES_CONFIG[table];
  const titleEl = document.getElementById('currentTableTitle');
  const descEl = document.getElementById('currentTableDesc');
  const addBtnText = document.getElementById('btnAddRecordText');

  if (titleEl) titleEl.innerText = config.title;
  if (descEl) descEl.innerText = `Gestión CRUD de ${config.title.toLowerCase()} registrados en el sistema con validación de restricciones en PostgreSQL.`;
  if (addBtnText) addBtnText.innerText = `+ Nuevo ${config.singular}`;

  loadTable(table);
}

// ==========================================================================
// DATA FETCHING & API CALLS
// ==========================================================================
async function apiRequest(endpoint, options = {}) {
  try {
    const res = await fetch(endpoint, options);
    const json = await res.json();
    if (!res.ok || json.success === false) {
      throw new Error(json.error || 'Ocurrió un error en el servidor');
    }
    return json;
  } catch (err) {
    showToast('Error', err.message, 'error');
    throw err;
  }
}

async function loadTable(table) {
  const tbody = document.getElementById('tableBody');
  if (!tbody) return;

  tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-secondary"><span class="spinner-border spinner-border-sm me-2 text-warning"></span> Cargando registros de ${table}...</td></tr>`;

  try {
    const res = await apiRequest(`${API_BASE}?resource=${table}`);
    state.data[table] = res.data || [];
    updateTabBadge(table, state.data[table].length);
    renderTable();
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-danger">Error al cargar los datos de ${table}.</td></tr>`;
  }
}

async function loadOptions() {
  try {
    const res = await apiRequest(`${API_BASE}?resource=options`);
    state.options = res.data || {};
  } catch (err) {
    console.error('Error al cargar opciones de relaciones:', err);
  }
}

async function loadStats() {
  try {
    const res = await apiRequest(`${API_BASE}?resource=stats`);
    state.stats = res.data || {};

    const elCli = document.getElementById('statClientes');
    const elBar = document.getElementById('statBarberos');
    const elSer = document.getElementById('statServicios');
    const elCit = document.getElementById('statCitas');
    const elIng = document.getElementById('statIngresos');
    const elLiq = document.getElementById('statLiquidaciones');

    if (elCli) elCli.innerText = state.stats.total_clientes ?? 0;
    if (elBar) elBar.innerText = state.stats.barberos_activos ?? 0;
    if (elSer) elSer.innerText = state.stats.servicios_activos ?? 0;
    if (elCit) elCit.innerText = state.stats.total_citas ?? 0;
    if (elIng) elIng.innerText = `$${parseFloat(state.stats.total_ingresos || 0).toFixed(2)}`;
    if (elLiq) elLiq.innerText = state.stats.liquidaciones_pendientes ?? 0;
  } catch (err) {
    console.error('Error al cargar estadísticas:', err);
  }
}

function updateTabBadge(table, count) {
  state.counts[table] = count;
  const badge = document.querySelector(`.tab-btn[data-table="${table}"] .tab-count`);
  if (badge) {
    badge.innerText = count;
  }
}

// ==========================================================================
// TABLE RENDERING
// ==========================================================================
function renderTable() {
  const table = state.currentTab;
  const config = TABLES_CONFIG[table];
  const thead = document.getElementById('tableHead');
  const tbody = document.getElementById('tableBody');
  if (!thead || !tbody) return;

  // Render Table Headers
  let headHtml = '<tr>';
  config.columns.forEach(col => {
    const style = col.width ? `style="width: ${col.width};"` : '';
    headHtml += `<th ${style}>${col.label}</th>`;
  });
  headHtml += '<th class="text-end" style="width: 170px;">Acciones</th></tr>';
  thead.innerHTML = headHtml;

  // Filter Data
  let items = state.data[table] || [];
  if (state.filterText) {
    const q = state.filterText.toLowerCase();
    items = items.filter(item => {
      return Object.values(item).some(val => 
        val !== null && val !== undefined && String(val).toLowerCase().includes(q)
      );
    });
  }

  // Render Empty State
  if (items.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="${config.columns.length + 1}" class="text-center py-5">
          <div class="my-3">
            <span class="fs-1 d-block mb-2">${config.icon}</span>
            <h5 class="fw-bold text-light">No hay registros de ${config.title}</h5>
            <p class="text-secondary small mb-3">${state.filterText ? 'No se encontraron resultados para la búsqueda actual.' : `Aún no se han creado registros en esta tabla. Haz clic en "Nuevo ${config.singular}" para comenzar.`}</p>
            ${!state.filterText ? `<button class="btn btn-barber-gold btn-sm rounded-pill px-3 fw-bold" onclick="openCreateModal()">+ Crear Primer ${config.singular}</button>` : ''}
          </div>
        </td>
      </tr>
    `;
    return;
  }

  // Render Rows
  tbody.innerHTML = items.map(row => {
    const id = row[config.pk];
    let rowHtml = `<tr>`;
    
    config.columns.forEach(col => {
      const val = row[col.key];
      rowHtml += `<td>${formatCellValue(row, col, val)}</td>`;
    });

    rowHtml += `
      <td class="text-end text-nowrap">
        <button class="btn btn-outline-secondary btn-sm rounded-pill px-2 py-1 me-1" onclick="openEditModal(${id})" title="Editar">
          <i class="bi bi-pencil-square me-1"></i> Editar
        </button>
        <button class="btn btn-outline-danger btn-sm rounded-pill px-2 py-1" onclick="promptDelete(${id})" title="Eliminar">
          <i class="bi bi-trash-fill"></i>
        </button>
      </td>
    `;
    rowHtml += `</tr>`;
    return rowHtml;
  }).join('');
}

function formatCellValue(row, col, val) {
  if (val === null || val === undefined || val === '') {
    return `<span class="text-secondary">${col.fallback || '—'}</span>`;
  }

  switch (col.type) {
    case 'currency':
      return `<span class="price-tag fw-bold">$${parseFloat(val).toFixed(2)}</span>`;

    case 'percent':
      return `<span class="fw-bold text-info">${parseFloat(val).toFixed(2)}%</span>`;

    case 'badge':
      let badgeClass = 'bg-secondary';
      const cleanVal = String(val).trim();
      if (['Activo', 'Completada', 'Pagado'].includes(cleanVal)) badgeClass = 'bg-success text-white';
      else if (['Pendiente'].includes(cleanVal)) badgeClass = 'bg-warning text-dark';
      else if (['Inactivo', 'Cancelada'].includes(cleanVal)) badgeClass = 'bg-danger text-white';
      else if (['Confirmada'].includes(cleanVal)) badgeClass = 'bg-info text-dark';
      return `<span class="badge ${badgeClass} rounded-pill px-2 py-1">${cleanVal}</span>`;

    case 'date':
      try {
        const parts = String(val).split('-');
        if (parts.length === 3) {
          return `<span class="fw-medium">${parts[2]}/${parts[1]}/${parts[0]}</span>`;
        }
        return val;
      } catch (e) { return val; }

    case 'datetime':
      try {
        const d = new Date(val);
        return `<span class="text-secondary small">${d.toLocaleDateString()} ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>`;
      } catch (e) { return val; }

    case 'time':
      return `<span class="fw-bold text-light">${String(val).substring(0, 5)}</span>`;

    case 'duration':
      return `<span>⏱️ ${val} min</span>`;

    case 'day':
      return `<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">${val}</span>`;

    case 'turno_details':
      return `<div><strong>${row.nombre_turno || 'Turno'}</strong><span class="cell-subtext">${String(row.hora_inicio || '').substring(0, 5)} - ${String(row.hora_fin || '').substring(0, 5)}</span></div>`;

    case 'service_price':
      return `<div><strong>${row.servicio_nombre || 'Servicio'}</strong><span class="cell-subtext">$${parseFloat(row.servicio_precio || 0).toFixed(2)}</span></div>`;

    case 'cita_reference':
      return `<div><strong>Cita #${val}</strong><span class="cell-subtext">${row.cita_fecha || ''} ${String(row.cita_hora || '').substring(0, 5)}</span></div>`;

    default:
      let content = col.bold ? `<strong>${escapeHtml(String(val))}</strong>` : escapeHtml(String(val));
      if (col.mono) {
        content = `<code class="text-warning">${content}</code>`;
      }
      if (col.subtextKey && row[col.subtextKey]) {
        content += `<span class="cell-subtext">${escapeHtml(String(row[col.subtextKey]))}</span>`;
      }
      return content;
  }
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// ==========================================================================
// SEARCH & FILTER
// ==========================================================================
function initSearch() {
  const searchInput = document.getElementById('tableSearchInput');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      state.filterText = e.target.value.trim();
      renderTable();
    });
  }
}

// ==========================================================================
// BOOTSTRAP MODALS & CRUD FORMS
// ==========================================================================
let formModalInstance = null;
let confirmModalInstance = null;

function getFormModal() {
  if (!formModalInstance) {
    formModalInstance = bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal'));
  }
  return formModalInstance;
}

function getConfirmModal() {
  if (!confirmModalInstance) {
    confirmModalInstance = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmModal'));
  }
  return confirmModalInstance;
}

function initModals() {
  const formEl = document.getElementById('recordForm');
  if (formEl) formEl.addEventListener('submit', handleFormSubmit);

  const delBtn = document.getElementById('btnConfirmDelete');
  if (delBtn) delBtn.addEventListener('click', executeDelete);
}

function getMissingDependencies(table, isEditing = false) {
  if (isEditing) return [];

  const missing = [];
  switch (table) {
    case 'horarios_barbero':
      if (!state.options.barberos || state.options.barberos.length === 0) {
        missing.push({ table: 'barberos', label: 'Barbero' });
      }
      if (!state.options.turnos || state.options.turnos.length === 0) {
        missing.push({ table: 'turnos_trabajo', label: 'Turno de Trabajo' });
      }
      break;

    case 'citas':
      if (!state.options.clientes || state.options.clientes.length === 0) {
        missing.push({ table: 'clientes', label: 'Cliente' });
      }
      if (!state.options.barberos || state.options.barberos.length === 0) {
        missing.push({ table: 'barberos', label: 'Barbero' });
      }
      if (!state.options.servicios || state.options.servicios.length === 0) {
        missing.push({ table: 'servicios', label: 'Servicio' });
      }
      break;

    case 'pagos':
      const citasPendientes = (state.options.citas || []).filter(c => !c.id_pago_existente);
      if (citasPendientes.length === 0) {
        missing.push({ table: 'citas', label: 'Cita pendiente de cobro' });
      }
      break;

    case 'liquidaciones_barbero':
      if (!state.options.barberos || state.options.barberos.length === 0) {
        missing.push({ table: 'barberos', label: 'Barbero' });
      }
      break;
  }
  return missing;
}

window.jumpToCreate = function(targetTable) {
  getFormModal().hide();
  switchTab(targetTable);
  setTimeout(() => openCreateModal(), 300);
};

window.openCreateModal = function() {
  state.editingId = null;
  const config = TABLES_CONFIG[state.currentTab];
  const titleEl = document.getElementById('modalFormTitle');
  if (titleEl) titleEl.innerHTML = `<span><i class="bi bi-plus-circle me-1 text-warning"></i> Nuevo ${config.singular}</span>`;
  buildFormFields(config.fields);
  getFormModal().show();
};

window.openEditModal = function(id) {
  state.editingId = id;
  const config = TABLES_CONFIG[state.currentTab];
  const record = (state.data[state.currentTab] || []).find(r => r[config.pk] == id);

  if (!record) {
    showToast('Error', 'Registro no encontrado para edición.', 'error');
    return;
  }

  const titleEl = document.getElementById('modalFormTitle');
  if (titleEl) titleEl.innerHTML = `<span><i class="bi bi-pencil-square me-1 text-warning"></i> Editar ${config.singular} (ID #${id})</span>`;
  buildFormFields(config.fields, record);
  getFormModal().show();
};

function buildFormFields(fields, currentValues = null) {
  const container = document.getElementById('formFieldsContainer');
  if (!container) return;
  container.innerHTML = '';

  const submitBtn = document.getElementById('btnSubmitForm');
  const isEditing = currentValues !== null;
  const missingDeps = getMissingDependencies(state.currentTab, isEditing);

  if (missingDeps.length > 0) {
    const alertBox = document.createElement('div');
    alertBox.className = 'col-12';
    alertBox.innerHTML = `
      <div class="alert barber-alert-warning d-flex align-items-start gap-3 p-3">
        <i class="bi bi-shield-exclamation fs-3 text-warning flex-shrink-0"></i>
        <div>
          <h5 class="alert-heading fw-bold mb-1">Restricción de Integridad Referencial</h5>
          <p class="small mb-2">No se puede crear un registro en <strong>${TABLES_CONFIG[state.currentTab].title}</strong> porque requiere datos previos de otras tablas que aún no existen en la base de datos:</p>
          <ul class="mb-2 small">
            ${missingDeps.map(m => `<li>❌ Falta registrar: <strong>${m.label}</strong></li>`).join('')}
          </ul>
          <div class="d-flex flex-wrap gap-2 mt-2">
            ${missingDeps.map(m => `
              <button type="button" class="btn btn-sm btn-outline-gold rounded-pill" onclick="jumpToCreate('${m.table}')">
                + Crear ${m.label} primero →
              </button>
            `).join('')}
          </div>
        </div>
      </div>
    `;
    container.appendChild(alertBox);

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.title = 'Debes registrar primero los datos requeridos en las otras tablas.';
      submitBtn.innerText = 'Requisitos Previos Requeridos';
    }
  } else {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.title = '';
      submitBtn.innerText = isEditing ? 'Guardar Cambios' : 'Guardar Registro';
    }
  }

  fields.forEach(field => {
    const col = document.createElement('div');
    col.className = field.span === 2 ? 'col-12' : 'col-12 col-md-6';

    const label = document.createElement('label');
    label.className = 'form-label text-light small fw-semibold';
    label.innerHTML = `${field.label} ${field.required ? '<span class="text-danger">*</span>' : ''}`;
    col.appendChild(label);

    let input;
    const value = currentValues ? currentValues[field.name] : (field.defaultValue ?? '');

    switch (field.type) {
      case 'textarea':
        input = document.createElement('textarea');
        input.className = 'form-control barber-input';
        input.rows = 3;
        input.name = field.name;
        input.placeholder = field.placeholder || '';
        input.value = value || '';
        if (field.required) input.required = true;
        break;

      case 'select':
        input = document.createElement('select');
        input.className = 'form-select barber-input';
        input.name = field.name;
        if (field.required) input.required = true;

        (field.options || []).forEach(opt => {
          const optEl = document.createElement('option');
          optEl.value = opt.value;
          optEl.textContent = opt.label;
          if (String(opt.value) === String(value)) optEl.selected = true;
          input.appendChild(optEl);
        });
        break;

      case 'select_cliente':
        input = document.createElement('select');
        input.className = 'form-select barber-input';
        input.name = field.name;
        if (field.required) input.required = true;

        const clientes = state.options.clientes || [];
        if (clientes.length === 0) {
          const opt = new Option('-- (No hay clientes registrados - Debe registrar uno primero) --', '');
          opt.disabled = true;
          input.appendChild(opt);
        } else {
          input.appendChild(new Option('-- Seleccione un Cliente --', ''));
          clientes.forEach(c => {
            const opt = new Option(`${c.nombre} (Cédula: ${c.cedula || 'N/A'} - Tel: ${c.telefono})`, c.id_cliente);
            if (String(c.id_cliente) === String(value)) opt.selected = true;
            input.appendChild(opt);
          });
        }
        break;

      case 'select_barbero':
        input = document.createElement('select');
        input.className = 'form-select barber-input';
        input.name = field.name;
        if (field.required) input.required = true;

        const barberos = state.options.barberos || [];
        if (barberos.length === 0) {
          const opt = new Option('-- (No hay barberos registrados - Debe registrar uno primero) --', '');
          opt.disabled = true;
          input.appendChild(opt);
        } else {
          input.appendChild(new Option('-- Seleccione un Barbero --', ''));
          barberos.forEach(b => {
            const opt = new Option(`${b.nombre} (${b.estado} - Comisión: ${parseFloat(b.porcentaje_comision)}%)`, b.id_barbero);
            if (String(b.id_barbero) === String(value)) opt.selected = true;
            input.appendChild(opt);
          });
        }
        break;

      case 'select_turno':
        input = document.createElement('select');
        input.className = 'form-select barber-input';
        input.name = field.name;
        if (field.required) input.required = true;

        const turnos = state.options.turnos || [];
        if (turnos.length === 0) {
          const opt = new Option('-- (No hay turnos registrados - Debe registrar uno primero) --', '');
          opt.disabled = true;
          input.appendChild(opt);
        } else {
          input.appendChild(new Option('-- Seleccione un Turno --', ''));
          turnos.forEach(t => {
            const opt = new Option(`${t.nombre_turno} (${String(t.hora_inicio).substring(0, 5)} - ${String(t.hora_fin).substring(0, 5)})`, t.id_turno);
            if (String(t.id_turno) === String(value)) opt.selected = true;
            input.appendChild(opt);
          });
        }
        break;

      case 'select_servicio':
        input = document.createElement('select');
        input.className = 'form-select barber-input';
        input.name = field.name;
        if (field.required) input.required = true;

        const servicios = state.options.servicios || [];
        if (servicios.length === 0) {
          const opt = new Option('-- (No hay servicios registrados - Debe registrar uno primero) --', '');
          opt.disabled = true;
          input.appendChild(opt);
        } else {
          input.appendChild(new Option('-- Seleccione un Servicio --', ''));
          servicios.forEach(s => {
            const opt = new Option(`${s.nombre} ($${parseFloat(s.precio).toFixed(2)} - ${s.duracion_minutos} min - ${s.estado})`, s.id_servicio);
            if (String(s.id_servicio) === String(value)) opt.selected = true;
            input.appendChild(opt);
          });
        }
        break;

      case 'select_cita':
        input = document.createElement('select');
        input.className = 'form-select barber-input';
        input.name = field.name;
        if (field.required) input.required = true;

        const citas = state.options.citas || [];
        const availableCitas = citas.filter(c => isEditing || !c.id_pago_existente || String(c.id_cita) === String(value));

        if (availableCitas.length === 0) {
          const opt = new Option('-- (No hay citas pendientes de cobro disponibles) --', '');
          opt.disabled = true;
          input.appendChild(opt);
        } else {
          input.appendChild(new Option('-- Seleccione una Cita para Cobrar --', ''));
          availableCitas.forEach(c => {
            const opt = new Option(`Cita #${c.id_cita}: ${c.cliente_nombre} - ${c.servicio_nombre} ($${parseFloat(c.servicio_precio).toFixed(2)}) [${c.fecha} ${String(c.hora).substring(0, 5)}]`, c.id_cita);
            if (String(c.id_cita) === String(value)) opt.selected = true;
            input.appendChild(opt);
          });
        }

        input.addEventListener('change', (e) => {
          const selCitaId = e.target.value;
          const foundCita = citas.find(c => String(c.id_cita) === String(selCitaId));
          const montoInput = document.querySelector('input[name="monto"]');
          if (foundCita && montoInput && (!montoInput.value || !isEditing)) {
            montoInput.value = parseFloat(foundCita.servicio_precio || 0).toFixed(2);
          }
        });
        break;

      default:
        input = document.createElement('input');
        input.className = 'form-control barber-input';
        input.type = field.type || 'text';
        input.name = field.name;
        input.placeholder = field.placeholder || '';
        input.value = value || '';
        if (field.required) input.required = true;
        if (field.step) input.step = field.step;
        if (field.min) input.min = field.min;
        if (field.max) input.max = field.max;
        if (field.numericOnly) {
          input.inputMode = 'numeric';
          input.pattern = '[0-9]+';
          input.addEventListener('input', () => {
            input.value = input.value.replace(/[^0-9]/g, '');
          });
        }
        break;
    }

    col.appendChild(input);
    container.appendChild(col);
  });
}

async function handleFormSubmit(e) {
  e.preventDefault();
  if (state.isSubmitting) return;

  const form = e.target;
  const formData = new FormData(form);
  const payload = {};

  formData.forEach((value, key) => {
    payload[key] = value.trim();
  });

  state.isSubmitting = true;
  const submitBtn = document.getElementById('btnSubmitForm');
  const originalText = submitBtn ? submitBtn.innerText : 'Guardar';
  if (submitBtn) {
    submitBtn.innerText = 'Guardando en base de datos...';
    submitBtn.disabled = true;
  }

  try {
    const table = state.currentTab;
    const isEditing = state.editingId !== null;
    let endpoint = `${API_BASE}?resource=${table}`;
    let method = 'POST';

    if (isEditing) {
      endpoint += `&id=${state.editingId}`;
      method = 'PUT';
    }

    const res = await apiRequest(endpoint, {
      method: method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    showToast('¡Éxito!', res.message || 'Registro procesado correctamente.', 'success');
    getFormModal().hide();

    await loadOptions();
    await loadStats();
    await loadTable(table);
  } catch (err) {
    // Error is shown in apiRequest toast
  } finally {
    state.isSubmitting = false;
    if (submitBtn) {
      submitBtn.innerText = originalText;
      submitBtn.disabled = false;
    }
  }
}

// ==========================================================================
// DELETE HANDLER
// ==========================================================================
window.promptDelete = function(id) {
  state.deletingId = id;
  const config = TABLES_CONFIG[state.currentTab];
  let warningExtra = '';
  if (['clientes', 'barberos', 'turnos_trabajo', 'servicios', 'citas'].includes(state.currentTab)) {
    warningExtra = `<div class="mt-3 p-2 bg-warning-subtle text-warning border border-warning-subtle rounded-3 small text-start">
      🛡️ <strong>Restricción de Integridad (PostgreSQL):</strong> Si este registro está siendo utilizado por citas, pagos u horarios, PostgreSQL bloqueará su eliminación para preservar la consistencia de la base de datos.
    </div>`;
  }

  const msgEl = document.getElementById('confirmDeleteMessage');
  if (msgEl) {
    msgEl.innerHTML = `¿Estás seguro de que deseas eliminar este registro (${config.singular} #${id})? Esta acción no se puede deshacer.${warningExtra}`;
  }
  getConfirmModal().show();
};

window.executeDelete = async function() {
  if (!state.deletingId) return;

  const btn = document.getElementById('btnConfirmDelete');
  if (btn) {
    btn.disabled = true;
    btn.innerText = 'Eliminando...';
  }

  try {
    const table = state.currentTab;
    const res = await apiRequest(`${API_BASE}?resource=${table}&id=${state.deletingId}`, {
      method: 'DELETE'
    });

    showToast('Eliminado', res.message || 'Registro eliminado.', 'success');
    getConfirmModal().hide();

    await loadOptions();
    await loadStats();
    await loadTable(table);
  } catch (err) {
    // Handled in apiRequest
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerText = 'Sí, Eliminar';
    }
    state.deletingId = null;
  }
};

// ==========================================================================
// TOP ACTIONS (REFRESH & DEMO SEED VIA CRUD)
// ==========================================================================
function initActions() {
  const btnRefresh = document.getElementById('btnRefreshAll');
  if (btnRefresh) {
    btnRefresh.addEventListener('click', async () => {
      showToast('Sincronizando', 'Consultando datos actualizados de PostgreSQL...', 'info');
      await loadOptions();
      await loadStats();
      await loadTable(state.currentTab);
      showToast('Actualizado', 'Datos sincronizados exitosamente.', 'success');
    });
  }

  const btnSeed = document.getElementById('btnSeedDemo');
  if (btnSeed) {
    btnSeed.addEventListener('click', async () => {
      if (!confirm('¿Deseas poblar datos iniciales de demostración a través del CRUD (servicios, barberos, clientes, citas, etc.)?')) {
        return;
      }
      try {
        btnSeed.disabled = true;
        btnSeed.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generando...';
        const res = await apiRequest(`${API_BASE}?action=seed_demo`, { method: 'POST' });
        showToast('Datos Demo', res.message, res.seeded ? 'success' : 'warning');
        await loadOptions();
        await loadStats();
        await loadTable(state.currentTab);
      } catch (e) {
        // Handled
      } finally {
        btnSeed.disabled = false;
        btnSeed.innerHTML = '<i class="bi bi-lightning-charge-fill me-1"></i> Demo CRUD';
      }
    });
  }
}

// ==========================================================================
// TOAST NOTIFICATIONS (BOOTSTRAP 5)
// ==========================================================================
function showToast(title, message, type = 'info') {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = `toast toast-${type} align-items-center text-bg-dark border-0 show mb-2 shadow-lg`;
  toast.setAttribute('role', 'alert');
  toast.setAttribute('aria-live', 'assertive');
  toast.setAttribute('aria-atomic', 'true');

  let icon = 'ℹ️';
  if (type === 'success') icon = '✅';
  else if (type === 'error') icon = '❌';
  else if (type === 'warning') icon = '⚠️';

  toast.innerHTML = `
    <div class="d-flex">
      <div class="toast-body d-flex align-items-start gap-2 py-3 px-3">
        <span class="fs-5">${icon}</span>
        <div>
          <div class="toast-title text-light fw-bold">${escapeHtml(title)}</div>
          <div class="toast-message text-secondary small">${escapeHtml(message)}</div>
        </div>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close" onclick="this.closest('.toast').remove()"></button>
    </div>
  `;

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.transition = 'opacity 0.4s ease';
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 400);
  }, 4500);
}
