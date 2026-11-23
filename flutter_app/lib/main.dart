import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';
import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';
import 'models/models.dart';
import 'services/api_service.dart';
import 'services/ocr_service.dart';
import 'services/pdf_service.dart';

const String kAppVersion = String.fromEnvironment('APP_VERSION', defaultValue: 'V1.2611191345');
const String kApkDownloadUrl = 'https://github.com/pablosalinas/ControlCombustible/releases/latest/download/ControlCombustible.apk';

int compareAppVersions(String latest, String current) {
  List<int> parse(String v) {
    final clean = v.trim().replaceFirst(RegExp(r'^[vV]'), '');
    return clean.split('.').map((p) => int.tryParse(p) ?? 0).toList();
  }

  final a = parse(latest);
  final b = parse(current);
  final len = a.length > b.length ? a.length : b.length;
  for (int i = 0; i < len; i++) {
    final av = i < a.length ? a[i] : 0;
    final bv = i < b.length ? b[i] : 0;
    if (av != bv) return av.compareTo(bv);
  }
  return 0;
}

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const ControlCombustibleApp());
}

class ControlCombustibleApp extends StatelessWidget {
  const ControlCombustibleApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Control de Combustible',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF0F766E),
          primary: const Color(0xFF0F766E),
          secondary: const Color(0xFFF59E0B),
        ),
        useMaterial3: true,
      ),
      home: const RootScreen(),
    );
  }
}

class RootScreen extends StatefulWidget {
  const RootScreen({super.key});

  @override
  State<RootScreen> createState() => _RootScreenState();
}

class _RootScreenState extends State<RootScreen> with WidgetsBindingObserver {
  final ApiService _api = ApiService();
  bool _checkingSession = true;
  bool _loggedIn = false;
  Timer? _inactivityTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initSession();
    _inactivityTimer = Timer.periodic(const Duration(minutes: 1), (_) {
      _checkInactivityTimeout();
    });
  }

  @override
  void dispose() {
    _inactivityTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _checkInactivityTimeout();
    }
  }

  Future<void> _checkInactivityTimeout() async {
    if (!_loggedIn) return;
    if (_api.isSessionExpired) {
      await _api.logout();
      if (mounted) {
        setState(() => _loggedIn = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Sesión cerrada tras 60 minutos sin uso.')),
        );
      }
    }
  }

  Future<void> _initSession() async {
    try {
      final ok = await _api.restoreSession();
      if (mounted) {
        setState(() {
          _loggedIn = ok;
          _checkingSession = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() => _checkingSession = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_checkingSession) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    if (!_loggedIn) {
      return LoginScreen(
        api: _api,
        onLoggedIn: () => setState(() => _loggedIn = true),
      );
    }
    return Listener(
      behavior: HitTestBehavior.translucent,
      onPointerDown: (_) => _api.touchActivity(),
      child: DashboardScreen(
        api: _api,
        onLogout: () async {
          await _api.logout();
          setState(() => _loggedIn = false);
        },
      ),
    );
  }
}

class LoginScreen extends StatefulWidget {
  final ApiService api;
  final VoidCallback onLoggedIn;

  const LoginScreen({super.key, required this.api, required this.onLoggedIn});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _userCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  final _captchaCtrl = TextEditingController();
  int _n1 = 3;
  int _n2 = 4;
  bool _loading = false;
  String? _error;
  String? _latestVersion;
  String _latestApkUrl = kApkDownloadUrl;

  @override
  void initState() {
    super.initState();
    _generateCaptcha();
    _checkLatestAppVersion();
  }

  Future<void> _checkLatestAppVersion() async {
    try {
      final uri = Uri.parse('https://api.github.com/repos/pablosalinas/ControlCombustible/releases/latest');
      final resp = await http.get(uri, headers: {'Accept': 'application/vnd.github+json'}).timeout(const Duration(seconds: 5));
      if (resp.statusCode == 200) {
        final data = jsonDecode(utf8.decode(resp.bodyBytes)) as Map<String, dynamic>;
        final tag = (data['tag_name'] ?? '').toString().trim();
        if (tag.isNotEmpty && compareAppVersions(tag, kAppVersion) > 0) {
          String dlUrl = kApkDownloadUrl;
          final assets = data['assets'] as List? ?? [];
          for (final a in assets) {
            if (a is Map && (a['name']?.toString().endsWith('.apk') ?? false)) {
              final u = a['browser_download_url']?.toString() ?? '';
              if (u.isNotEmpty) {
                dlUrl = u;
                break;
              }
            }
          }
          if (mounted) {
            setState(() {
              _latestVersion = tag;
              _latestApkUrl = dlUrl;
            });
          }
        }
      }
    } catch (_) {
      // Silencioso si no hay red o hay límite de API
    }
  }

  void _generateCaptcha() {
    final rnd = Random();
    setState(() {
      _n1 = rnd.nextInt(9) + 1;
      _n2 = rnd.nextInt(9) + 1;
      _captchaCtrl.clear();
    });
  }

  Future<void> _submit() async {
    final userSum = int.tryParse(_captchaCtrl.text.trim());
    if (userSum == null || userSum != (_n1 + _n2)) {
      setState(() => _error = 'La suma de seguridad anti-robots es incorrecta.');
      _generateCaptcha();
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final ok = await widget.api.login(_userCtrl.text.trim(), _passCtrl.text);
      if (ok) {
        widget.onLoggedIn();
      } else {
        setState(() => _error = widget.api.lastError ?? 'Usuario o contraseña de moratalla-murcia.com incorrectos.');
        _generateCaptcha();
      }
    } catch (e) {
      setState(() => _error = 'Error de conexión: $e');
      _generateCaptcha();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _showRegisterDialog() async {
    final userCtrl = TextEditingController();
    final emailCtrl = TextEditingController();
    final passCtrl = TextEditingController();
    final pass2Ctrl = TextEditingController();
    String? dlgErr;
    bool sending = false;

    await showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDlgState) => AlertDialog(
          title: const Text('Solicitar Registro'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text(
                  'Tu solicitud quedará pendiente de validación por parte del superusuario.',
                  style: TextStyle(fontSize: 12.5, color: Colors.black54),
                ),
                const SizedBox(height: 12),
                if (dlgErr != null) ...[
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(dlgErr!, style: TextStyle(color: Colors.red.shade800, fontSize: 12.5)),
                  ),
                  const SizedBox(height: 10),
                ],
                TextField(
                  controller: userCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Usuario *',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: emailCtrl,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(
                    labelText: 'Correo electrónico (comunicaciones) *',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: passCtrl,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Contraseña (mín. 6 caracteres) *',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: pass2Ctrl,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Repetir contraseña *',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: sending ? null : () => Navigator.pop(ctx), child: const Text('Cancelar')),
            FilledButton.icon(
              onPressed: sending
                  ? null
                  : () async {
                      final u = userCtrl.text.trim();
                      final em = emailCtrl.text.trim();
                      final p1 = passCtrl.text;
                      final p2 = pass2Ctrl.text;
                      if (u.length < 3) {
                        setDlgState(() => dlgErr = 'El usuario debe tener al menos 3 caracteres.');
                        return;
                      }
                      if (!em.contains('@') || !em.contains('.')) {
                        setDlgState(() => dlgErr = 'Introduce un correo electrónico válido.');
                        return;
                      }
                      if (p1.length < 6) {
                        setDlgState(() => dlgErr = 'La contraseña debe tener al menos 6 caracteres.');
                        return;
                      }
                      if (p1 != p2) {
                        setDlgState(() => dlgErr = 'Las contraseñas no coinciden.');
                        return;
                      }
                      setDlgState(() {
                        sending = true;
                        dlgErr = null;
                      });
                      try {
                        final res = await widget.api.register(username: u, email: em, password: p1);
                        if (res['success'] == true) {
                          if (ctx.mounted) Navigator.pop(ctx);
                          if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(
                                  (res['message'] ?? 'Solicitud enviada. Pendiente de validación por el superusuario.').toString(),
                                ),
                                duration: const Duration(seconds: 5),
                              ),
                            );
                          }
                        } else {
                          setDlgState(() {
                            sending = false;
                            dlgErr = (res['error'] ?? 'Error al registrar').toString();
                          });
                        }
                      } catch (e) {
                        setDlgState(() {
                          sending = false;
                          dlgErr = 'Error de conexión: $e';
                        });
                      }
                    },
              icon: const Icon(Icons.person_add, size: 18),
              label: Text(sending ? 'Enviando...' : 'Enviar Solicitud'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Card(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(18),
                    child: Image.asset(
                      'assets/logo_v2.png',
                      width: 84,
                      height: 84,
                      fit: BoxFit.cover,
                    ),
                  ),
                  const SizedBox(height: 14),
                  const Text(
                    'Control de Combustible',
                    style: TextStyle(fontSize: 21, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Acceso con cuenta moratalla-murcia.com',
                    style: TextStyle(fontSize: 13, color: Colors.black54),
                  ),
                  const SizedBox(height: 20),
                  if (_latestVersion != null)
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(12),
                      margin: const EdgeInsets.only(bottom: 14),
                      decoration: BoxDecoration(
                        color: Colors.amber.shade50,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: Colors.amber.shade400),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Icon(Icons.system_update, color: Colors.amber.shade900, size: 20),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  'Nueva versión disponible: $_latestVersion (instalada: $kAppVersion)',
                                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Colors.amber.shade900),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          SizedBox(
                            width: double.infinity,
                            child: FilledButton.icon(
                              style: FilledButton.styleFrom(backgroundColor: const Color(0xFF0F766E)),
                              onPressed: () => launchUrl(
                                Uri.parse(_latestApkUrl),
                                mode: LaunchMode.externalApplication,
                              ),
                              icon: const Icon(Icons.download, size: 18),
                              label: Text('Actualizar a $_latestVersion'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  if (_error != null)
                    Container(
                      padding: const EdgeInsets.all(10),
                      margin: const EdgeInsets.only(bottom: 14),
                      decoration: BoxDecoration(
                        color: Colors.red.shade50,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(_error!, style: TextStyle(color: Colors.red.shade800, fontSize: 13)),
                    ),
                  TextField(
                    controller: _userCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Usuario',
                      prefixIcon: Icon(Icons.person_outline),
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 14),
                  TextField(
                    controller: _passCtrl,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'Contraseña',
                      prefixIcon: Icon(Icons.lock_outline),
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFCBD5E1)),
                    ),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                          ),
                          child: Text(
                            '$_n1 + $_n2 = ?',
                            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Color(0xFF0F766E)),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: TextField(
                            controller: _captchaCtrl,
                            keyboardType: TextInputType.number,
                            textAlign: TextAlign.center,
                            decoration: const InputDecoration(
                              hintText: 'Suma',
                              isDense: true,
                              border: OutlineInputBorder(),
                            ),
                          ),
                        ),
                        IconButton(
                          tooltip: 'Nueva suma',
                          onPressed: _generateCaptcha,
                          icon: const Icon(Icons.refresh),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    height: 48,
                    child: FilledButton.icon(
                      onPressed: _loading ? null : _submit,
                      icon: const Icon(Icons.login),
                      label: Text(_loading ? 'Verificando...' : 'Entrar'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    width: double.infinity,
                    height: 44,
                    child: OutlinedButton.icon(
                      onPressed: _loading ? null : _showRegisterDialog,
                      icon: const Icon(Icons.person_add_outlined, size: 18),
                      label: const Text('Solicitar Registro de Usuario'),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextButton.icon(
                    onPressed: () => launchUrl(
                      Uri.parse('https://www.moratalla-murcia.com'),
                      mode: LaunchMode.externalApplication,
                    ),
                    icon: const Icon(Icons.language, size: 18),
                    label: const Text('Ir a moratalla-murcia.com'),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Versión $kAppVersion',
                    style: const TextStyle(fontSize: 12, color: Colors.black45, fontWeight: FontWeight.w600),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class DashboardScreen extends StatefulWidget {
  final ApiService api;
  final VoidCallback onLogout;

  const DashboardScreen({super.key, required this.api, required this.onLogout});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  String _selectedScope = 'all'; // 'all', 'user:<id>', 'park:<id>'
  int _selectedVehiculoId = 0; // 0 = Todos (Resultado conjunto)
  List<Vehiculo> _vehiculos = [];
  List<ParqueMovil> _parques = [];
  List<Map<String, dynamic>> _usuariosFiltro = [];
  List<EstacionServicio> _estaciones = [];
  List<Proveedor> _proveedores = [];
  List<TareaMaestro> _tareasMaestro = [];
  List<VehiculoTarea> _vehiculoTareas = [];
  Map<int, double> _ultimoKmPorVehiculo = {};
  List<Repostaje> _repostajes = [];
  ResumenEstadisticas? _resumen;
  String _userEmail = '';
  String _currentUsername = '';
  int _currentUserId = 0;
  bool _loading = true;
  String _searchQuery = '';
  String _fechaDesde = '';
  String _fechaHasta = '';

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _loading = true);
    try {
      int filterUsuarioId = 0;
      int filterParqueId = 0;
      if (_selectedScope.startsWith('park:')) {
        filterParqueId = int.tryParse(_selectedScope.substring(5)) ?? 0;
      } else if (_selectedScope.startsWith('user:')) {
        filterUsuarioId = int.tryParse(_selectedScope.substring(5)) ?? 0;
      }
      final res = await widget.api.post('dashboard', {
        'vehiculo_id': _selectedVehiculoId,
        if (filterUsuarioId > 0) 'filter_usuario_id': filterUsuarioId,
        if (filterParqueId > 0) 'filter_parque_id': filterParqueId,
        if (_fechaDesde.isNotEmpty) 'fecha_desde': _fechaDesde,
        if (_fechaHasta.isNotEmpty) 'fecha_hasta': _fechaHasta,
      });
      if (res['success'] == true) {
        final vList = (res['vehiculos'] as List? ?? [])
            .map((e) => Vehiculo.fromJson(e as Map<String, dynamic>))
            .toList();
        final pqList = (res['parques'] as List? ?? [])
            .map((e) => ParqueMovil.fromJson(e as Map<String, dynamic>))
            .toList();
        final usrList = (res['usuarios'] as List? ?? [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList();
        final estList = (res['estaciones'] as List? ?? [])
            .map((e) => EstacionServicio.fromJson(e as Map<String, dynamic>))
            .toList();
        final provList = (res['proveedores'] as List? ?? [])
            .map((e) => Proveedor.fromJson(e as Map<String, dynamic>))
            .toList();
        final tmList = (res['tareas_maestro'] as List? ?? [])
            .map((e) => TareaMaestro.fromJson(e as Map<String, dynamic>))
            .toList();
        final vtList = (res['vehiculo_tareas'] as List? ?? [])
            .map((e) => VehiculoTarea.fromJson(e as Map<String, dynamic>))
            .toList();
        final userMap = res['user'] is Map
            ? Map<String, dynamic>.from(res['user'] as Map)
            : <String, dynamic>{};
        final rList = (res['repostajes'] as List? ?? [])
            .map((e) => Repostaje.fromJson(e as Map<String, dynamic>))
            .toList();
        final rawLastKm = res['ultimo_km_por_vehiculo'] is Map
            ? Map<String, dynamic>.from(res['ultimo_km_por_vehiculo'] as Map)
            : <String, dynamic>{};
        final parsedLastKm = <int, double>{};
        rawLastKm.forEach((k, v) {
          final id = int.tryParse(k);
          final km = double.tryParse(v.toString());
          if (id != null && km != null) parsedLastKm[id] = km;
        });
        final sumMap = res['resumen'] is Map
            ? Map<String, dynamic>.from(res['resumen'] as Map)
            : <String, dynamic>{};

        if (mounted) {
          setState(() {
            _vehiculos = vList;
            _parques = pqList;
            _usuariosFiltro = usrList;
            if (_selectedVehiculoId != 0 && !vList.any((v) => v.id == _selectedVehiculoId)) {
              _selectedVehiculoId = 0;
            }
            _estaciones = estList;
            _proveedores = provList;
            _tareasMaestro = tmList;
            _vehiculoTareas = vtList;
            _userEmail = (userMap['email'] ?? '').toString();
            _currentUsername = (userMap['username'] ?? '').toString();
            _currentUserId = int.tryParse((userMap['id'] ?? 0).toString()) ?? 0;
            _ultimoKmPorVehiculo = parsedLastKm;
            _repostajes = rList;
            _resumen = ResumenEstadisticas.fromJson(sumMap);
          });
        }
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text((res['error'] ?? 'No se pudieron cargar los datos.').toString())),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al cargar datos: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _pickDateFilter(bool isFrom) async {
    final currentStr = isFrom ? _fechaDesde : _fechaHasta;
    final initial = DateTime.tryParse(currentStr) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (picked != null) {
      final formatted =
          '${picked.year.toString().padLeft(4, '0')}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
      setState(() {
        if (isFrom) {
          _fechaDesde = formatted;
        } else {
          _fechaHasta = formatted;
        }
      });
      _loadData();
    }
  }

  Future<void> _openTareasScreen() async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TareasScreen(
          api: widget.api,
          vehiculos: _vehiculos,
          proveedores: _proveedores,
          tareasMaestro: _tareasMaestro,
          vehiculoTareas: _vehiculoTareas,
          ultimoKmPorVehiculo: _ultimoKmPorVehiculo,
          initialVehiculoId: _selectedVehiculoId,
        ),
      ),
    );
    _loadData();
  }

  Future<void> _showUserEmailDialog() async {
    final ctrl = TextEditingController(text: _userEmail);
    final saved = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Correo para Avisos'),
        content: TextField(
          controller: ctrl,
          keyboardType: TextInputType.emailAddress,
          decoration: const InputDecoration(
            labelText: 'Correo electrónico',
            hintText: 'usuario@ejemplo.com',
            border: OutlineInputBorder(),
            helperText: 'Se enviarán avisos de repostaje y tareas (<1000 km o ≤15 días).',
            helperMaxLines: 2,
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Guardar')),
        ],
      ),
    );
    if (saved == true) {
      final res = await widget.api.post('update_user_email', {'email': ctrl.text.trim()});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Actualizado').toString())),
        );
        if (res['success'] == true) _loadData();
      }
    }
  }

  Future<void> _sendTaskAlertsEmail() async {
    final res = await widget.api.post('send_task_alerts_email', {'vehiculo_id': _selectedVehiculoId});
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Operación completada').toString())),
      );
    }
  }

  String _getScopeTitle() {
    if (_selectedVehiculoId == 0) {
      return 'Todos los vehículos (Resultado Conjunto)';
    }
    final found = _vehiculos.where((v) => v.id == _selectedVehiculoId).toList();
    if (found.isEmpty) return 'Vehículo';
    return '${found.first.nombre} ${found.first.matricula.isNotEmpty ? "(${found.first.matricula})" : ""}';
  }

  Future<void> _openRepostajeForm({Repostaje? editItem}) async {
    if (_vehiculos.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Primero registra un vehículo.')),
      );
      return;
    }
    // Si hay más de un vehículo y es un nuevo repostaje, no preseleccionar ninguno
    final int? defaultVehId = editItem != null
        ? editItem.vehiculoId
        : (_vehiculos.length == 1 ? _vehiculos.first.id : null);

    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => RepostajeFormScreen(
          api: widget.api,
          vehiculos: _vehiculos,
          estaciones: _estaciones,
          ultimoKmPorVehiculo: _ultimoKmPorVehiculo,
          initialVehiculoId: defaultVehId,
          editItem: editItem,
        ),
      ),
    );
    if (saved == true) {
      _loadData();
    }
  }

  Future<void> _deactivateRepostaje(Repostaje item) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Baja lógica de repostaje'),
        content: Text('¿Dar de baja el repostaje del ${item.fecha} (${item.kmOdometro.toStringAsFixed(0)} km)?\nSe marcará como inactivo (activo = 0) sin borrar datos.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Confirmar baja')),
        ],
      ),
    );
    if (confirm == true) {
      await widget.api.post('deactivate_repostaje', {'id': item.id});
      _loadData();
    }
  }

  Future<void> _showPdfOptionsDialog(List<Repostaje> currentFiltered) async {
    final fromCtrl = TextEditingController();
    final toCtrl = TextEditingController();
    final emailCtrl = TextEditingController(text: _userEmail);
    bool ascending = false;

    final actionChoice = await showDialog<String>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDlgState) => AlertDialog(
          title: const Text('Configurar Informe PDF'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Ámbito: ${_getScopeTitle()}',
                  style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: Color(0xFF0F766E)),
                ),
                if (_searchQuery.isNotEmpty)
                  Text('Filtro texto activo: "$_searchQuery"', style: const TextStyle(fontSize: 12, color: Colors.black54)),
                const SizedBox(height: 12),
                TextField(
                  controller: fromCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Desde fecha (YYYY-MM-DD, opcional)',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: toCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Hasta fecha (YYYY-MM-DD, opcional)',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: emailCtrl,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(
                    labelText: 'Email destino (para envío por servidor)',
                    hintText: 'Opcional si ya tienes correo configurado',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                ),
                const SizedBox(height: 12),
                const Text('Ordenar por fecha:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                RadioListTile<bool>(
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  title: const Text('Descendente (Más recientes primero)'),
                  value: false,
                  groupValue: ascending,
                  onChanged: (v) => setDlgState(() => ascending = v ?? false),
                ),
                RadioListTile<bool>(
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  title: const Text('Ascendente (Más antiguos primero)'),
                  value: true,
                  groupValue: ascending,
                  onChanged: (v) => setDlgState(() => ascending = v ?? true),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, null), child: const Text('Cancelar')),
            OutlinedButton.icon(
              onPressed: () => Navigator.pop(ctx, 'share'),
              icon: const Icon(Icons.share, size: 18),
              label: const Text('Compartir / App Email'),
            ),
            OutlinedButton.icon(
              onPressed: () => Navigator.pop(ctx, 'email'),
              icon: const Icon(Icons.email_outlined, size: 18),
              label: const Text('Enviar Email'),
            ),
            FilledButton.icon(
              onPressed: () => Navigator.pop(ctx, 'pdf'),
              icon: const Icon(Icons.picture_as_pdf, size: 18),
              label: const Text('Ver / Imprimir'),
            ),
          ],
        ),
      ),
    );

    if (actionChoice == null) return;

    final fromStr = fromCtrl.text.trim();
    final toStr = toCtrl.text.trim();
    final pdfRows = currentFiltered.where((r) {
      if (fromStr.isNotEmpty && r.fecha.compareTo(fromStr) < 0) return false;
      if (toStr.isNotEmpty && r.fecha.compareTo(toStr) > 0) return false;
      return true;
    }).toList();

    pdfRows.sort((a, b) {
      final cmp = a.fecha.compareTo(b.fecha);
      if (cmp != 0) return ascending ? cmp : -cmp;
      final kmCmp = a.kmOdometro.compareTo(b.kmOdometro);
      return ascending ? kmCmp : -kmCmp;
    });

    if (pdfRows.isEmpty && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No hay repostajes que coincidan con el filtro.')),
      );
      return;
    }

    double totL = 0, totE = 0, totKm = 0;
    final cons = <double>[], precs = <double>[];
    for (final r in pdfRows) {
      totL += r.litros;
      totE += r.importeTotal;
      if (r.difKm > 0) totKm += r.difKm;
      if (r.litrosPor100 != null) cons.add(r.litrosPor100!);
      if (r.precioLitro > 0) precs.add(r.precioLitro);
    }
    final cMax = cons.isNotEmpty ? cons.reduce((a, b) => a > b ? a : b) : 0.0;
    final cMin = cons.isNotEmpty ? cons.reduce((a, b) => a < b ? a : b) : 0.0;
    final cMed = cons.isNotEmpty ? cons.reduce((a, b) => a + b) / cons.length : 0.0;
    final pMax = precs.isNotEmpty ? precs.reduce((a, b) => a > b ? a : b) : 0.0;
    final pMin = precs.isNotEmpty ? precs.reduce((a, b) => a < b ? a : b) : 0.0;
    final coste100 = totKm > 0 ? (totE / totKm) * 100.0 : 0.0;
    final eurKm = totKm > 0 ? totE / totKm : 0.0;

    final filteredResumen = ResumenEstadisticas(
      numRepostajes: pdfRows.length,
      totalLitros: totL,
      totalEuros: totE,
      totalKmRecorridos: totKm,
      consumoMaximo: cMax,
      consumoMinimo: cMin,
      diferenciaConsumo: cMax - cMin,
      precioMaximo: pMax,
      precioMinimo: pMin,
      diferenciaPrecio: pMax - pMin,
      consumoMedioL100: cMed,
      costeMedio100Km: coste100,
      eurosPorKmMedio: eurKm,
    );

    final orderLabel = ascending ? 'Ascendente' : 'Descendente';
    final scopeTitle = '${_getScopeTitle()} ($orderLabel)';
    final filename = 'Informe_Combustible_${DateTime.now().toIso8601String().substring(0, 10)}.pdf';
    final pdfBytes = await PdfReportService.generateAndSharePdf(
      scopeTitle: scopeTitle,
      resumen: filteredResumen,
      repostajes: pdfRows,
      sendOnly: actionChoice == 'email' || actionChoice == 'share',
    );
    if (actionChoice == 'share') {
      await PdfReportService.sharePdf(bytes: pdfBytes, filename: filename);
    } else if (actionChoice == 'email') {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Enviando informe PDF por correo...')),
        );
      }
      final summary = 'Ámbito: $scopeTitle\nRepostajes: ${pdfRows.length}\nConsumo medio: ${cMed.toStringAsFixed(2)} L/100km\nTotal: ${totL.toStringAsFixed(2)} L (${totE.toStringAsFixed(2)} €)';
      final res = await widget.api.post('send_report_email', {
        'title': 'Informe de Combustible - ${_getScopeTitle()}',
        'summary': summary,
        'filename': filename,
        'pdf_base64': base64Encode(pdfBytes),
        if (emailCtrl.text.trim().isNotEmpty) 'to_email': emailCtrl.text.trim(),
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Operación completada').toString())),
        );
      }
    }
  }

  void _showAppVersionDialog() {
    bool checking = true;
    String? latestTag;
    String dlUrl = kApkDownloadUrl;
    String statusText = 'Comprobando si hay una nueva versión...';
    String releaseNotes = '';
    bool hasUpdate = false;
    bool started = false;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDlgState) {
          if (!started) {
            started = true;
            http
                .get(
                  Uri.parse('https://api.github.com/repos/pablosalinas/ControlCombustible/releases/latest'),
                  headers: {'Accept': 'application/vnd.github+json'},
                )
                .timeout(const Duration(seconds: 6))
                .then((resp) {
              if (!ctx.mounted) return;
              if (resp.statusCode == 200) {
                final data = jsonDecode(utf8.decode(resp.bodyBytes)) as Map<String, dynamic>;
                final tag = (data['tag_name'] ?? '').toString().trim();
                final body = (data['body'] ?? '').toString().trim();
                final assets = data['assets'] as List? ?? [];
                for (final a in assets) {
                  if (a is Map && (a['name']?.toString().endsWith('.apk') ?? false)) {
                    final u = a['browser_download_url']?.toString() ?? '';
                    if (u.isNotEmpty) {
                      dlUrl = u;
                      break;
                    }
                  }
                }
                final isNewer = tag.isNotEmpty && compareAppVersions(tag, kAppVersion) > 0;
                setDlgState(() {
                  checking = false;
                  latestTag = tag;
                  releaseNotes = body;
                  hasUpdate = isNewer;
                  statusText = isNewer
                      ? '¡Nueva versión disponible: $tag!'
                      : 'Tienes la última versión disponible ($tag).';
                });
              } else {
                setDlgState(() {
                  checking = false;
                  statusText = 'No se pudo comprobar en GitHub (HTTP ${resp.statusCode}).';
                });
              }
            }).catchError((_) {
              if (!ctx.mounted) return;
              setDlgState(() {
                checking = false;
                statusText = 'No se pudo conectar para comprobar actualizaciones.';
              });
            });
          }

          return AlertDialog(
            title: Row(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.asset('assets/logo_v2.png', width: 32, height: 32),
                ),
                const SizedBox(width: 10),
                const Expanded(child: Text('Versión y Actualización')),
              ],
            ),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Versión instalada: $kAppVersion', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                const SizedBox(height: 10),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: hasUpdate ? Colors.teal.shade50 : Colors.grey.shade100,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: hasUpdate ? Colors.teal.shade300 : Colors.grey.shade300),
                  ),
                  child: Row(
                    children: [
                      if (checking)
                        const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                      else
                        Icon(
                          hasUpdate ? Icons.new_releases : Icons.check_circle,
                          size: 18,
                          color: hasUpdate ? const Color(0xFF0F766E) : Colors.green.shade700,
                        ),
                      const SizedBox(width: 8),
                      Expanded(child: Text(statusText, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600))),
                    ],
                  ),
                ),
                if (releaseNotes.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  const Text('Novedades de la versión:', style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: Color(0xFF0F766E))),
                  const SizedBox(height: 4),
                  Container(
                    width: double.infinity,
                    constraints: const BoxConstraints(maxHeight: 165),
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.grey.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.grey.shade300),
                    ),
                    child: SingleChildScrollView(
                      child: Text(releaseNotes, style: const TextStyle(fontSize: 12, height: 1.35, color: Colors.black87)),
                    ),
                  ),
                ],
              ],
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cerrar')),
              FilledButton.icon(
                onPressed: () {
                  Navigator.pop(ctx);
                  launchUrl(Uri.parse(dlUrl), mode: LaunchMode.externalApplication);
                },
                icon: const Icon(Icons.download),
                label: Text(hasUpdate && latestTag != null ? 'Descargar $latestTag' : 'Descargar último APK'),
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _showPendingUsersDialog() async {
    await showDialog(
      context: context,
      builder: (ctx) {
        List<Map<String, dynamic>>? pending;
        String? err;
        bool loaded = false;

        return StatefulBuilder(
          builder: (ctx, setDlgState) {
            if (!loaded) {
              loaded = true;
              widget.api.post('list_pending_users', {}).then((res) {
                if (ctx.mounted) {
                  setDlgState(() {
                    if (res['success'] == true) {
                      pending = (res['pending_users'] as List? ?? [])
                          .map((e) => Map<String, dynamic>.from(e as Map))
                          .toList();
                    } else {
                      err = (res['error'] ?? 'Error al cargar').toString();
                    }
                  });
                }
              }).catchError((e) {
                if (ctx.mounted) {
                  setDlgState(() => err = 'Error de conexión: $e');
                }
              });
            }

            return AlertDialog(
              title: const Text('Solicitudes Pendientes'),
              content: SizedBox(
                width: double.maxFinite,
                child: err != null
                    ? Text(err!, style: const TextStyle(color: Colors.red))
                    : pending == null
                        ? const SizedBox(height: 80, child: Center(child: CircularProgressIndicator()))
                        : pending!.isEmpty
                            ? const Text('No hay solicitudes pendientes de validación.')
                            : ListView.builder(
                                shrinkWrap: true,
                                itemCount: pending!.length,
                                itemBuilder: (_, idx) {
                                  final u = pending![idx];
                                  final uid = int.tryParse(u['id'].toString()) ?? 0;
                                  return Card(
                                    child: Padding(
                                      padding: const EdgeInsets.all(10),
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(u['username'].toString(), style: const TextStyle(fontWeight: FontWeight.bold)),
                                          Text(u['email']?.toString() ?? 'Sin correo', style: const TextStyle(fontSize: 12, color: Colors.black54)),
                                          const SizedBox(height: 8),
                                          Wrap(
                                            spacing: 6,
                                            children: [
                                              FilledButton(
                                                onPressed: () async {
                                                  await widget.api.post('approve_user', {'id': uid, 'acceso_sistema': 'ambos'});
                                                  setDlgState(() {
                                                    pending!.removeAt(idx);
                                                  });
                                                },
                                                child: const Text('Validar', style: TextStyle(fontSize: 12)),
                                              ),
                                              OutlinedButton(
                                                onPressed: () async {
                                                  await widget.api.post('reject_user', {'id': uid});
                                                  setDlgState(() {
                                                    pending!.removeAt(idx);
                                                  });
                                                },
                                                child: const Text('Rechazar', style: TextStyle(fontSize: 12, color: Colors.red)),
                                              ),
                                            ],
                                          ),
                                        ],
                                      ),
                                    ),
                                  );
                                },
                              ),
              ),
              actions: [
                TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cerrar')),
              ],
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _repostajes.where((r) {
      if (_searchQuery.isEmpty) return true;
      final q = _searchQuery.toLowerCase();
      return r.fecha.toLowerCase().contains(q) ||
          r.kmOdometro.toStringAsFixed(0).contains(q) ||
          r.notas.toLowerCase().contains(q) ||
          r.vehiculoNombre.toLowerCase().contains(q);
    }).toList();

    return Scaffold(
      appBar: AppBar(
        backgroundColor: const Color(0xFF0F172A),
        foregroundColor: Colors.white,
        title: InkWell(
          onTap: _showAppVersionDialog,
          child: Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: Image.asset('assets/logo_v2.png', width: 32, height: 32),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      widget.api.isAdmin ? 'Control de Combustible.' : 'Control de Combustible',
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 16),
                    ),
                    const Text(
                      kAppVersion,
                      style: TextStyle(fontSize: 11, color: Colors.white70, fontWeight: FontWeight.normal),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        actions: [
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                tooltip: 'Mantenimiento y Tareas',
                icon: const Icon(Icons.build_circle_outlined),
                onPressed: _openTareasScreen,
              ),
              if (_vehiculoTareas.any((t) => t.enAlerta))
                Positioned(
                  right: 6,
                  top: 8,
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: const BoxDecoration(color: Colors.red, shape: BoxShape.circle),
                    child: Text(
                      '${_vehiculoTareas.where((t) => t.enAlerta).length}',
                      style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.bold, color: Colors.white),
                    ),
                  ),
                ),
            ],
          ),
          IconButton(
            tooltip: 'Generar Informe PDF',
            icon: const Icon(Icons.picture_as_pdf),
            onPressed: _resumen == null
                ? null
                : () => _showPdfOptionsDialog(filtered),
          ),
          PopupMenuButton<String>(
            tooltip: 'Opciones y Menú',
            icon: const Icon(Icons.more_vert),
            onSelected: (val) async {
              switch (val) {
                case 'version':
                  _showAppVersionDialog();
                  break;
                case 'vehiculos':
                  await Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => VehiculosScreen(api: widget.api, vehiculos: _vehiculos, parques: _parques),
                    ),
                  );
                  _loadData();
                  break;
                case 'parques':
                  await Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ParquesMovilesScreen(api: widget.api, currentUserId: _currentUserId),
                    ),
                  );
                  _loadData();
                  break;
                case 'papelera':
                  await Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => PapeleraScreen(api: widget.api),
                    ),
                  );
                  _loadData();
                  break;
                case 'estaciones':
                  await Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => EstacionesScreen(api: widget.api, estaciones: _estaciones),
                    ),
                  );
                  _loadData();
                  break;
                case 'proveedores':
                  await Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ProveedoresScreen(api: widget.api, proveedores: _proveedores),
                    ),
                  );
                  _loadData();
                  break;
                case 'email':
                  _showUserEmailDialog();
                  break;
                case 'pending':
                  _showPendingUsersDialog();
                  break;
                case 'logout':
                  widget.onLogout();
                  break;
              }
            },
            itemBuilder: (ctx) => [
              PopupMenuItem<String>(
                value: 'version',
                child: Row(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(6),
                      child: Image.asset('assets/logo_v2.png', width: 24, height: 24),
                    ),
                    const SizedBox(width: 10),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text('Versión y Actualizaciones', style: TextStyle(fontWeight: FontWeight.w600)),
                          Text(kAppVersion, style: TextStyle(fontSize: 11, color: Colors.black54)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const PopupMenuDivider(),
              const PopupMenuItem<String>(
                value: 'vehiculos',
                child: ListTile(leading: Icon(Icons.directions_car), title: Text('Gestionar Vehículos'), dense: true, contentPadding: EdgeInsets.zero),
              ),
              const PopupMenuItem<String>(
                value: 'parques',
                child: ListTile(leading: Icon(Icons.groups, color: Color(0xFF0F766E)), title: Text('Parques Móviles'), dense: true, contentPadding: EdgeInsets.zero),
              ),
              const PopupMenuItem<String>(
                value: 'papelera',
                child: ListTile(leading: Icon(Icons.restore_from_trash, color: Colors.redAccent), title: Text('Papelera (Recuperar)'), dense: true, contentPadding: EdgeInsets.zero),
              ),
              const PopupMenuItem<String>(
                value: 'estaciones',
                child: ListTile(leading: Icon(Icons.storefront), title: Text('Estaciones de Servicio'), dense: true, contentPadding: EdgeInsets.zero),
              ),
              const PopupMenuItem<String>(
                value: 'proveedores',
                child: ListTile(leading: Icon(Icons.build_circle_outlined), title: Text('Talleres, Aseguradoras e ITVs'), dense: true, contentPadding: EdgeInsets.zero),
              ),
              const PopupMenuItem<String>(
                value: 'email',
                child: ListTile(leading: Icon(Icons.email_outlined), title: Text('Correo de Avisos'), dense: true, contentPadding: EdgeInsets.zero),
              ),
              if (widget.api.isAdmin)
                const PopupMenuItem<String>(
                  value: 'pending',
                  child: ListTile(leading: Icon(Icons.how_to_reg), title: Text('Solicitudes de Registro'), dense: true, contentPadding: EdgeInsets.zero),
                ),
              const PopupMenuDivider(),
              const PopupMenuItem<String>(
                value: 'logout',
                child: ListTile(leading: Icon(Icons.logout, color: Colors.red), title: Text('Cerrar sesión', style: TextStyle(color: Colors.red)), dense: true, contentPadding: EdgeInsets.zero),
              ),
            ],
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadData,
              child: ListView(
                padding: const EdgeInsets.all(12),
                children: [
                  // Selector de ámbito (Usuario / Parque Móvil / Todos)
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                      child: DropdownButtonHideUnderline(
                        child: DropdownButton<String>(
                          isExpanded: true,
                          value: ([
                            'all',
                            ..._parques.map((pq) => 'park:${pq.id}'),
                            if (widget.api.isAdmin)
                              ..._usuariosFiltro.map((u) => 'user:${u['id']}')
                            else if (_currentUserId > 0)
                              'user:$_currentUserId',
                          ].contains(_selectedScope))
                              ? _selectedScope
                              : 'all',
                          items: [
                            DropdownMenuItem(
                              value: 'all',
                              child: Text(
                                widget.api.isAdmin
                                    ? '🌐 Todos los vehículos (Vista Global)'
                                    : '🌐 Todos mis vehículos (Usuario + Parques)',
                                style: const TextStyle(fontWeight: FontWeight.bold),
                              ),
                            ),
                            ..._parques.map(
                              (pq) => DropdownMenuItem(
                                value: 'park:${pq.id}',
                                child: Text('🏢 Parque Móvil: ${pq.nombre}'),
                              ),
                            ),
                            if (widget.api.isAdmin)
                              ..._usuariosFiltro.map(
                                (u) => DropdownMenuItem(
                                  value: 'user:${u['id']}',
                                  child: Text('👤 Usuario: ${u['username']}'),
                                ),
                              )
                            else if (_currentUserId > 0)
                              DropdownMenuItem(
                                value: 'user:$_currentUserId',
                                child: Text('👤 Mis vehículos ($_currentUsername)'),
                              ),
                          ],
                          onChanged: (val) {
                            if (val != null) {
                              setState(() {
                                _selectedScope = val;
                                _selectedVehiculoId = 0;
                              });
                              _loadData();
                            }
                          },
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  // Selector de vehículo / Resultado conjunto
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                      child: DropdownButtonHideUnderline(
                        child: DropdownButton<int>(
                          isExpanded: true,
                          value: _selectedVehiculoId,
                          items: [
                            const DropdownMenuItem(
                              value: 0,
                              child: Text('📊 Todos los vehículos (Resultado Conjunto)', style: TextStyle(fontWeight: FontWeight.bold)),
                            ),
                            ..._vehiculos.map(
                              (v) => DropdownMenuItem(
                                value: v.id,
                                child: Text('🚗 ${v.nombre} ${v.matricula.isNotEmpty ? "(${v.matricula})" : ""}'),
                              ),
                            ),
                          ],
                          onChanged: (val) {
                            if (val != null) {
                              setState(() => _selectedVehiculoId = val);
                              _loadData();
                            }
                          },
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          style: OutlinedButton.styleFrom(
                            backgroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
                          ),
                          onPressed: () => _pickDateFilter(true),
                          icon: const Icon(Icons.date_range, size: 17),
                          label: Text(
                            _fechaDesde.isEmpty ? 'Desde: Todas' : 'Desde: $_fechaDesde',
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontSize: 12.5),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: OutlinedButton.icon(
                          style: OutlinedButton.styleFrom(
                            backgroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
                          ),
                          onPressed: () => _pickDateFilter(false),
                          icon: const Icon(Icons.event, size: 17),
                          label: Text(
                            _fechaHasta.isEmpty ? 'Hasta: Todas' : 'Hasta: $_fechaHasta',
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontSize: 12.5),
                          ),
                        ),
                      ),
                      if (_fechaDesde.isNotEmpty || _fechaHasta.isNotEmpty)
                        IconButton(
                          tooltip: 'Limpiar fechas',
                          icon: const Icon(Icons.clear, size: 20),
                          onPressed: () {
                            setState(() {
                              _fechaDesde = '';
                              _fechaHasta = '';
                            });
                            _loadData();
                          },
                        ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  if (_vehiculoTareas.any((t) => t.enAlerta)) ...[
                    Card(
                      color: Colors.amber.shade50,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                        side: BorderSide(color: Colors.amber.shade400),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        child: Row(
                          children: [
                            const Icon(Icons.warning_amber_rounded, color: Color(0xFFD97706)),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                '${_vehiculoTareas.where((t) => t.enAlerta).length} tarea(s) de mantenimiento próxima(s) o vencida(s) (<1000 km / ≤15 días)',
                                style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: Color(0xFF92400E)),
                              ),
                            ),
                            TextButton(onPressed: _openTareasScreen, child: const Text('Ver')),
                            IconButton(
                              tooltip: 'Enviar aviso por correo',
                              icon: const Icon(Icons.send, size: 18, color: Color(0xFFB45309)),
                              onPressed: _sendTaskAlertsEmail,
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                  ],
                  if (_resumen != null) _buildKpiGrid(_resumen!),
                  const SizedBox(height: 10),
                  if (_repostajes.isNotEmpty) ...[
                    _buildEvolutionChartCard(),
                    const SizedBox(height: 10),
                  ],
                  TextField(
                    decoration: InputDecoration(
                      hintText: 'Buscar por fecha, kilómetros, vehículo o nota...',
                      prefixIcon: const Icon(Icons.search),
                      filled: true,
                      fillColor: Colors.white,
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    ),
                    onChanged: (v) => setState(() => _searchQuery = v),
                  ),
                  const SizedBox(height: 10),
                  ...filtered.map((r) => _buildRepostajeCard(r)),
                  const SizedBox(height: 80),
                ],
              ),
            ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openRepostajeForm(),
        icon: const Icon(Icons.add),
        label: const Text('Nuevo Repostaje'),
      ),
    );
  }

  Widget _buildKpiGrid(ResumenEstadisticas s) {
    final latestOdometro = _repostajes.isNotEmpty ? _repostajes.first.kmOdometro : 0.0;
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 8,
      crossAxisSpacing: 8,
      childAspectRatio: 1.9,
      children: [
        _kpiTile('Consumo Medio', '${s.consumoMedioL100.toStringAsFixed(2)} L/100', 'Coste: ${s.costeMedio100Km.toStringAsFixed(2)} €/100km'),
        _kpiTile('Consumo Máx / Mín', '${s.consumoMaximo.toStringAsFixed(2)} / ${s.consumoMinimo.toStringAsFixed(2)}', 'Dif: ${s.diferenciaConsumo.toStringAsFixed(2)} L/100'),
        _kpiTile('Precio Máx / Mín', '${s.precioMaximo.toStringAsFixed(3)} / ${s.precioMinimo.toStringAsFixed(3)} €', 'Dif: ${s.diferenciaPrecio.toStringAsFixed(3)} €/L'),
        _kpiTile('Total Combustible', '${s.totalLitros.toStringAsFixed(2)} L', '${s.numRepostajes} repostajes'),
        _kpiTile('Gasto Acumulado', '${s.totalEuros.toStringAsFixed(2)} €', '${s.eurosPorKmMedio.toStringAsFixed(3)} €/km'),
        _kpiTile('Km Recorridos', '${s.totalKmRecorridos.toStringAsFixed(0)} km', 'Odómetro: ${latestOdometro.toStringAsFixed(0)} km'),
      ],
    );
  }

  Widget _buildEvolutionChartCard() {
    final hasDateFilter = _fechaDesde.isNotEmpty || _fechaHasta.isNotEmpty;
    final reversed = _repostajes.reversed.toList();
    final chrono = (!hasDateFilter && reversed.length > 50)
        ? reversed.sublist(reversed.length - 50)
        : reversed;

    final consSpots = <FlSpot>[];
    final priceValues = <double>[];
    final consValues = <double>[];

    for (var i = 0; i < chrono.length; i++) {
      final r = chrono[i];
      if (r.litrosPor100 != null && r.litrosPor100! > 0) {
        consSpots.add(FlSpot(i.toDouble(), r.litrosPor100!));
        consValues.add(r.litrosPor100!);
      }
      if (r.precioLitro > 0) {
        priceValues.add(r.precioLitro);
      }
    }

    if (consSpots.isEmpty && priceValues.isEmpty) {
      return const SizedBox.shrink();
    }

    double minCons = consValues.isNotEmpty ? consValues.reduce(min) : 0.0;
    double maxCons = consValues.isNotEmpty ? consValues.reduce(max) : 10.0;
    if ((maxCons - minCons).abs() < 0.5) {
      minCons = max(0.0, minCons - 1.0);
      maxCons = maxCons + 1.0;
    } else {
      final pad = (maxCons - minCons) * 0.15;
      minCons = max(0.0, minCons - pad);
      maxCons = maxCons + pad;
    }

    double minPrice = priceValues.isNotEmpty ? priceValues.reduce(min) : 0.0;
    double maxPrice = priceValues.isNotEmpty ? priceValues.reduce(max) : 2.0;
    if ((maxPrice - minPrice).abs() < 0.05) {
      minPrice = max(0.0, minPrice - 0.1);
      maxPrice = maxPrice + 0.1;
    } else {
      final pad = (maxPrice - minPrice) * 0.15;
      minPrice = max(0.0, minPrice - pad);
      maxPrice = maxPrice + pad;
    }

    double normalizePrice(double price) {
      if ((maxPrice - minPrice).abs() < 1e-6) return (minCons + maxCons) / 2;
      return minCons + ((price - minPrice) / (maxPrice - minPrice)) * (maxCons - minCons);
    }

    double denormalizePrice(double y) {
      if ((maxCons - minCons).abs() < 1e-6) return minPrice;
      return minPrice + ((y - minCons) / (maxCons - minCons)) * (maxPrice - minPrice);
    }

    final priceSpots = <FlSpot>[];
    for (var i = 0; i < chrono.length; i++) {
      if (chrono[i].precioLitro > 0) {
        priceSpots.add(FlSpot(i.toDouble(), normalizePrice(chrono[i].precioLitro)));
      }
    }

    final xStep = max(1, (chrono.length / 4).ceil());

    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.show_chart, color: Color(0xFF0F766E), size: 20),
                SizedBox(width: 6),
                Expanded(
                  child: Text(
                    'Evolución de Consumo (L/100km) y Precio (€/L)',
                    style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            const Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.circle, size: 10, color: Color(0xFF0F766E)),
                SizedBox(width: 4),
                Text('Consumo (L/100km)', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600)),
                SizedBox(width: 14),
                Icon(Icons.circle, size: 10, color: Color(0xFFF59E0B)),
                SizedBox(width: 4),
                Text('Precio (€/L)', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600)),
              ],
            ),
            const SizedBox(height: 10),
            SizedBox(
              height: 210,
              child: LineChart(
                LineChartData(
                  minX: 0,
                  maxX: max(0, chrono.length - 1).toDouble(),
                  minY: minCons,
                  maxY: maxCons,
                  gridData: FlGridData(
                    show: true,
                    drawVerticalLine: false,
                    getDrawingHorizontalLine: (_) => FlLine(
                      color: Colors.grey.shade200,
                      strokeWidth: 1,
                    ),
                  ),
                  borderData: FlBorderData(
                    show: true,
                    border: Border(
                      left: BorderSide(color: Colors.grey.shade300),
                      right: BorderSide(color: Colors.grey.shade300),
                      bottom: BorderSide(color: Colors.grey.shade300),
                    ),
                  ),
                  titlesData: FlTitlesData(
                    topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    leftTitles: AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: true,
                        reservedSize: 38,
                        getTitlesWidget: (value, meta) {
                          if (value == meta.min || value == meta.max) return const SizedBox.shrink();
                          return Text(
                            value.toStringAsFixed(1),
                            style: const TextStyle(fontSize: 10, color: Color(0xFF0F766E), fontWeight: FontWeight.w600),
                          );
                        },
                      ),
                    ),
                    rightTitles: AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: true,
                        reservedSize: 42,
                        getTitlesWidget: (value, meta) {
                          if (value == meta.min || value == meta.max) return const SizedBox.shrink();
                          final p = denormalizePrice(value);
                          return Padding(
                            padding: const EdgeInsets.only(left: 4),
                            child: Text(
                              p.toStringAsFixed(2),
                              style: const TextStyle(fontSize: 10, color: Color(0xFFD97706), fontWeight: FontWeight.w600),
                            ),
                          );
                        },
                      ),
                    ),
                    bottomTitles: AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: true,
                        reservedSize: 22,
                        interval: xStep.toDouble(),
                        getTitlesWidget: (value, meta) {
                          final idx = value.round();
                          if (idx < 0 || idx >= chrono.length) return const SizedBox.shrink();
                          final f = chrono[idx].fecha;
                          final label = f.length >= 10 ? '${f.substring(8, 10)}/${f.substring(5, 7)}' : f;
                          return Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: Text(label, style: const TextStyle(fontSize: 9.5, color: Colors.black54)),
                          );
                        },
                      ),
                    ),
                  ),
                  lineTouchData: LineTouchData(
                    touchTooltipData: LineTouchTooltipData(
                      getTooltipColor: (_) => const Color(0xFF0F172A).withOpacity(0.92),
                      getTooltipItems: (touchedSpots) {
                        return touchedSpots.map((spot) {
                          final idx = spot.x.round().clamp(0, chrono.length - 1);
                          final r = chrono[idx];
                          if (spot.bar.color == const Color(0xFF0F766E)) {
                            return LineTooltipItem(
                              '${_fmtDateEs(r.fecha)}\nConsumo: ${r.litrosPor100?.toStringAsFixed(2) ?? "-"} L/100',
                              const TextStyle(color: Color(0xFF5EEAD4), fontSize: 11.5, fontWeight: FontWeight.w600),
                            );
                          }
                          return LineTooltipItem(
                            'Precio: ${r.precioLitro.toStringAsFixed(3)} €/L',
                            const TextStyle(color: Color(0xFFFCD34D), fontSize: 11.5, fontWeight: FontWeight.w600),
                          );
                        }).toList();
                      },
                    ),
                  ),
                  lineBarsData: [
                    if (consSpots.isNotEmpty)
                      LineChartBarData(
                        spots: consSpots,
                        isCurved: true,
                        curveSmoothness: 0.3,
                        color: const Color(0xFF0F766E),
                        barWidth: 2.5,
                        dotData: FlDotData(show: consSpots.length <= 20),
                        belowBarData: BarAreaData(
                          show: true,
                          color: const Color(0xFF0F766E).withOpacity(0.10),
                        ),
                      ),
                    if (priceSpots.isNotEmpty)
                      LineChartBarData(
                        spots: priceSpots,
                        isCurved: true,
                        curveSmoothness: 0.3,
                        color: const Color(0xFFF59E0B),
                        barWidth: 2.2,
                        dotData: FlDotData(show: priceSpots.length <= 20),
                        belowBarData: BarAreaData(
                          show: true,
                          color: const Color(0xFFF59E0B).withOpacity(0.08),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _kpiTile(String title, String mainValue, String subValue) {
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(title, style: const TextStyle(fontSize: 11, color: Colors.black54, fontWeight: FontWeight.w600)),
            const SizedBox(height: 3),
            Text(mainValue, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F766E))),
            const SizedBox(height: 2),
            Text(subValue, style: const TextStyle(fontSize: 11, color: Colors.black54)),
          ],
        ),
      ),
    );
  }

  String _fmtDateEs(String val) {
    final m = RegExp(r'^(\d{4})-(\d{2})-(\d{2})').firstMatch(val.trim());
    return m != null ? '${m.group(3)}-${m.group(2)}-${m.group(1)}' : val;
  }

  void _openRemotePhotoDialog(String relPath, String title) {
    final base = widget.api.baseUrl;
    final idx = base.lastIndexOf('/');
    final prefix = idx >= 0 ? base.substring(0, idx + 1) : base;
    final url = (relPath.startsWith('http://') || relPath.startsWith('https://')) ? relPath : '$prefix$relPath';
    showDialog(
      context: context,
      builder: (ctx) => Dialog(
        insetPadding: const EdgeInsets.all(16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AppBar(
              title: Text(title, style: const TextStyle(fontSize: 16)),
              automaticallyImplyLeading: false,
              actions: [IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(ctx))],
            ),
            ConstrainedBox(
              constraints: BoxConstraints(maxHeight: MediaQuery.of(ctx).size.height * 0.68),
              child: InteractiveViewer(
                child: Image.network(url, fit: BoxFit.contain),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRepostajeCard(Repostaje r) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ExpansionTile(
        title: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(_fmtDateEs(r.fecha), style: const TextStyle(fontWeight: FontWeight.bold)),
            Text(
              '${r.importeTotal.toStringAsFixed(2)} €${r.esPesetas && r.importeTotalPesetas != null ? " (${r.importeTotalPesetas!.toStringAsFixed(0)} pts)" : ""}',
              style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF0F766E)),
            ),
          ],
        ),
        subtitle: Text(
          '${r.kmOdometro.toStringAsFixed(0)} km (${r.difKm > 0 ? "+${r.difKm.toStringAsFixed(0)} km" : "Inicio"}) · ${r.litros.toStringAsFixed(2)} L a ${r.precioLitro.toStringAsFixed(3)} €/L${r.esPesetas && r.precioLitroPesetas != null ? " (${r.precioLitroPesetas!.toStringAsFixed(1)} pts)" : ""}'
          '${r.litrosPor100 != null ? " · ${r.litrosPor100!.toStringAsFixed(2)} L/100" : ""}',
          style: const TextStyle(fontSize: 12),
        ),
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 14,
                  runSpacing: 6,
                  children: [
                    Text('Vehículo: ${r.vehiculoNombre}'),
                    if (r.estacionServicio.isNotEmpty) Text('Estación: ${r.estacionServicio}'),
                    Text('KM.x.LTS: ${r.kmPorLitro?.toStringAsFixed(2) ?? "-"}'),
                    Text('LTS.x.100: ${r.litrosPor100?.toStringAsFixed(2) ?? "-"}'),
                    Text('KM.x.EUR: ${r.kmPorEuro?.toStringAsFixed(2) ?? "-"}'),
                    Text('€ x 1KM.: ${r.eurosPorKm?.toStringAsFixed(4) ?? "-"} €'),
                    Text('T.Litros: ${r.totalLitrosAcum.toStringAsFixed(2)} L'),
                    Text('Total €: ${r.totalEurosAcum.toStringAsFixed(2)} €'),
                    Text('Media €/100: ${r.costeMedio100Acum?.toStringAsFixed(2) ?? "-"} €'),
                  ],
                ),
                if (r.notas.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text('Nota: ${r.notas}', style: const TextStyle(fontStyle: FontStyle.italic)),
                ],
                if (r.fotoTicketPath.isNotEmpty || r.fotoSurtidorPath.isNotEmpty || r.fotoOdometroPath.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 6,
                    children: [
                      if (r.fotoTicketPath.isNotEmpty)
                        OutlinedButton.icon(
                          onPressed: () => _openRemotePhotoDialog(r.fotoTicketPath, 'Factura / Ticket (${_fmtDateEs(r.fecha)})'),
                          style: OutlinedButton.styleFrom(visualDensity: VisualDensity.compact),
                          icon: const Icon(Icons.receipt_long, size: 16, color: Color(0xFF0F766E)),
                          label: const Text('Factura', style: TextStyle(fontSize: 12)),
                        ),
                      if (r.fotoSurtidorPath.isNotEmpty)
                        OutlinedButton.icon(
                          onPressed: () => _openRemotePhotoDialog(r.fotoSurtidorPath, 'Surtidor (${_fmtDateEs(r.fecha)})'),
                          style: OutlinedButton.styleFrom(visualDensity: VisualDensity.compact),
                          icon: const Icon(Icons.local_gas_station, size: 16, color: Color(0xFF0284C7)),
                          label: const Text('Surtidor', style: TextStyle(fontSize: 12)),
                        ),
                      if (r.fotoOdometroPath.isNotEmpty)
                        OutlinedButton.icon(
                          onPressed: () => _openRemotePhotoDialog(r.fotoOdometroPath, 'Odómetro (${_fmtDateEs(r.fecha)})'),
                          style: OutlinedButton.styleFrom(visualDensity: VisualDensity.compact),
                          icon: const Icon(Icons.speed, size: 16, color: Color(0xFFF59E0B)),
                          label: const Text('Odómetro', style: TextStyle(fontSize: 12)),
                        ),
                    ],
                  ),
                ],
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    TextButton.icon(
                      onPressed: () => _openRepostajeForm(editItem: r),
                      icon: const Icon(Icons.edit, size: 18),
                      label: const Text('Modificar'),
                    ),
                    TextButton.icon(
                      onPressed: () => _deactivateRepostaje(r),
                      icon: const Icon(Icons.delete_outline, size: 18, color: Colors.red),
                      label: const Text('Baja', style: TextStyle(color: Colors.red)),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class RepostajeFormScreen extends StatefulWidget {
  final ApiService api;
  final List<Vehiculo> vehiculos;
  final List<EstacionServicio> estaciones;
  final Map<int, double> ultimoKmPorVehiculo;
  final int? initialVehiculoId;
  final Repostaje? editItem;

  const RepostajeFormScreen({
    super.key,
    required this.api,
    required this.vehiculos,
    required this.estaciones,
    required this.ultimoKmPorVehiculo,
    required this.initialVehiculoId,
    this.editItem,
  });

  @override
  State<RepostajeFormScreen> createState() => _RepostajeFormScreenState();
}

class _RepostajeFormScreenState extends State<RepostajeFormScreen> {
  final OcrService _ocr = OcrService();
  int? _vehiculoId;
  late TextEditingController _fechaCtrl;
  late TextEditingController _kmCtrl;
  late TextEditingController _litrosCtrl;
  late TextEditingController _precioCtrl;
  late TextEditingController _importeCtrl;
  String _selectedEstacion = '';
  late List<EstacionServicio> _localEstaciones;
  String _fotoTicketPath = '';
  String _fotoSurtidorPath = '';
  String _fotoOdometroPath = '';
  String? _localTicketPath;
  String? _localSurtidorPath;
  String? _localOdometroPath;
  late TextEditingController _notasCtrl;
  bool _saving = false;
  bool _ocrBusy = false;
  String? _ocrMessage;
  String _lastOcrRawText = '';

  String _isoToDisplayDate(String iso) {
    final s = iso.trim();
    final m = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(s);
    if (m != null) return '${m.group(3)}-${m.group(2)}-${m.group(1)}';
    return s;
  }

  String _displayToIsoDate(String disp) {
    final s = disp.trim();
    final m = RegExp(r'^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$').firstMatch(s);
    if (m != null) return '${m.group(3)}-${m.group(2)}-${m.group(1)}';
    return s;
  }

  String _normStation(String s) {
    return s
        .toLowerCase()
        .replaceAll(RegExp(r'[áàä]'), 'a')
        .replaceAll(RegExp(r'[éèë]'), 'e')
        .replaceAll(RegExp(r'[íìï]'), 'i')
        .replaceAll(RegExp(r'[óòö]'), 'o')
        .replaceAll(RegExp(r'[úùü]'), 'u')
        .replaceAll('ñ', 'n')
        .replaceAll(RegExp(r'[^a-z0-9\s]'), ' ')
        .replaceAll(RegExp(r'\s+'), ' ')
        .trim();
  }

  @override
  void initState() {
    super.initState();
    final e = widget.editItem;
    _vehiculoId = e?.vehiculoId ?? widget.initialVehiculoId;
    final isoFecha = e?.fecha ?? DateTime.now().toIso8601String().substring(0, 10);
    _fechaCtrl = TextEditingController(text: _isoToDisplayDate(isoFecha));
    _kmCtrl = TextEditingController(text: e != null ? e.kmOdometro.toStringAsFixed(0) : '');
    _litrosCtrl = TextEditingController(text: e != null ? e.litros.toStringAsFixed(2) : '');
    _precioCtrl = TextEditingController(text: e != null ? e.precioLitro.toStringAsFixed(3) : '');
    _importeCtrl = TextEditingController(text: e != null ? e.importeTotal.toStringAsFixed(2) : '');
    _selectedEstacion = e?.estacionServicio ?? '';
    _localEstaciones = List<EstacionServicio>.from(widget.estaciones);
    _fotoTicketPath = e?.fotoTicketPath ?? '';
    _fotoSurtidorPath = e?.fotoSurtidorPath ?? '';
    _fotoOdometroPath = e?.fotoOdometroPath ?? '';
    _notasCtrl = TextEditingController(text: e?.notas ?? '');
  }

  double? _getPreviousKm() {
    if (_vehiculoId == null) return null;
    if (widget.editItem != null && widget.editItem!.vehiculoId == _vehiculoId && widget.editItem!.difKm > 0) {
      return widget.editItem!.kmOdometro - widget.editItem!.difKm;
    }
    return widget.ultimoKmPorVehiculo[_vehiculoId!];
  }

  String _getParcialMessage() {
    if (_vehiculoId == null) {
      return 'Selecciona un vehículo para ver su lectura anterior.';
    }
    final prevKm = _getPreviousKm();
    if (prevKm == null) {
      return 'Primer repostaje registrado para este vehículo.';
    }
    final currentKm = double.tryParse(_kmCtrl.text.replaceAll(',', '.'));
    if (currentKm == null || currentKm <= 0) {
      return 'Lectura anterior: ${prevKm.toStringAsFixed(0)} km';
    }
    final parcial = currentKm - prevKm;
    if (parcial < 0) {
      return '⚠️ Menor que la lectura anterior (${prevKm.toStringAsFixed(0)} km): ${parcial.toStringAsFixed(0)} km';
    }
    return '✓ Lectura anterior: ${prevKm.toStringAsFixed(0)} km → Parcial calculado: +${parcial.toStringAsFixed(1)} km';
  }

  void _recalcFromLitrosOrPrecio() {
    final l = double.tryParse(_litrosCtrl.text.replaceAll(',', '.'));
    final p = double.tryParse(_precioCtrl.text.replaceAll(',', '.'));
    if (l != null && p != null && l > 0 && p > 0) {
      _importeCtrl.text = (l * p).toStringAsFixed(2);
    }
  }

  String _resolveRemotePhotoUrl(String relPath) {
    final p = relPath.trim();
    if (p.isEmpty) return '';
    if (p.startsWith('http://') || p.startsWith('https://')) return p;
    final base = widget.api.baseUrl;
    final idx = base.lastIndexOf('/');
    final prefix = idx >= 0 ? base.substring(0, idx + 1) : base;
    return '$prefix$p';
  }

  Future<void> _uploadPhotoInBackground(String filePath, String tipo) async {
    try {
      final bytes = await File(filePath).readAsBytes();
      final b64 = 'data:image/jpeg;base64,${base64Encode(bytes)}';
      final res = await widget.api.post('upload_repostaje_foto', {
        'tipo': tipo,
        'foto_base64': b64,
      });
      if (res['success'] == true && mounted) {
        final savedPath = (res['path'] ?? '').toString();
        setState(() {
          if (tipo == 'odometro') {
            _fotoOdometroPath = savedPath;
          } else if (tipo == 'surtidor') {
            _fotoSurtidorPath = savedPath;
          } else {
            _fotoTicketPath = savedPath;
          }
        });
      }
    } catch (_) {
      // Silencioso en segundo plano para no interrumpir el registro
    }
  }

  Future<void> _createStationInBackground(DetectedStation st) async {
    try {
      final notas = [
        if (st.cif.isNotEmpty) 'CIF: ${st.cif}',
      ].join(' · ');
      final res = await widget.api.post('save_estacion', {
        'id': 0,
        'nombre': st.nombre,
        'marca': st.marca.isNotEmpty ? st.marca : st.nombre,
        'localidad': st.localidad,
        'direccion': st.direccion,
        'notas': notas,
      });
      if (res['success'] == true && mounted) {
        final newId = int.tryParse((res['estacion_id'] ?? 0).toString()) ?? 0;
        setState(() {
          _localEstaciones.add(EstacionServicio(
            id: newId,
            usuarioId: 0,
            nombre: st.nombre,
            marca: st.marca,
            localidad: st.localidad,
            direccion: st.direccion,
            notas: notas,
          ));
          _selectedEstacion = st.nombre;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Estación "${st.nombre}" creada y asociada al repostaje.')),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No se pudo crear la estación en segundo plano.')),
        );
      }
    }
  }

  Future<void> _handleDetectedStation(DetectedStation st) async {
    final normTarget = _normStation(st.nombre);
    EstacionServicio? matched;
    for (final est in _localEstaciones) {
      final normEst = _normStation(est.nombre);
      if (normEst.isEmpty) continue;
      if (normEst == normTarget || normEst.contains(normTarget) || normTarget.contains(normEst)) {
        matched = est;
        break;
      }
      final wordsT = normTarget.split(' ').where((w) => w.length >= 3 && w != 'estacion' && w != 'servicio' && w != 'slu').toList();
      final wordsE = normEst.split(' ').where((w) => w.length >= 3).toSet();
      final commonCount = wordsT.where((w) => wordsE.contains(w)).length;
      if (commonCount >= 2) {
        matched = est;
        break;
      }
    }

    if (matched != null) {
      setState(() {
        _selectedEstacion = matched!.nombre;
        _ocrMessage = 'Datos y estación (${matched.nombre}) extraídos del ticket.';
      });
      return;
    }

    if (!mounted) return;
    final details = [
      if (st.localidad.isNotEmpty) 'Localidad: ${st.localidad}',
      if (st.direccion.isNotEmpty) 'Dirección: ${st.direccion}',
      if (st.cif.isNotEmpty) 'CIF/NIF: ${st.cif}',
    ].join('\n');

    final create = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Estación de servicio no registrada'),
        content: Text(
          'Se ha detectado la estación:\n\n${st.nombre}'
          '${details.isNotEmpty ? '\n$details' : ''}\n\n'
          'No existe todavía en tu lista de estaciones. ¿Quieres crearla en segundo plano y asociarla a este repostaje?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Omitir'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Crear y asociar'),
          ),
        ],
      ),
    );

    if (create == true) {
      setState(() {
        _selectedEstacion = st.nombre;
        _ocrMessage = 'Creando estación "${st.nombre}" en segundo plano...';
      });
      // Lanzar en segundo plano sin bloquear la edición del repostaje
      unawaited(_createStationInBackground(st));
    }
  }

  Future<void> _applyTicketOcrResult(TicketScanResult res) async {
    setState(() {
      if (res.fecha != null) _fechaCtrl.text = _isoToDisplayDate(res.fecha!);
      if (res.litros != null) _litrosCtrl.text = res.litros!.toStringAsFixed(2);
      if (res.precioLitro != null) _precioCtrl.text = res.precioLitro!.toStringAsFixed(3);
      if (res.importeTotal != null) _importeCtrl.text = res.importeTotal!.toStringAsFixed(2);
      _lastOcrRawText = res.rawText;
      final gotAny = res.fecha != null || res.litros != null || res.precioLitro != null || res.importeTotal != null || res.estacion != null;
      _ocrMessage = gotAny
          ? 'Datos extraídos de la factura/ticket. Revisa y confirma.'
          : 'Foto guardada. No se pudieron leer todos los datos del ticket; puedes reprocesar o completarlos.';
    });
    if (res.estacion != null) {
      await _handleDetectedStation(res.estacion!);
    }
  }

  void _applyPumpOcrResult(TicketScanResult res) {
    setState(() {
      if (res.litros != null) _litrosCtrl.text = res.litros!.toStringAsFixed(2);
      if (res.precioLitro != null) _precioCtrl.text = res.precioLitro!.toStringAsFixed(3);
      if (res.importeTotal != null) _importeCtrl.text = res.importeTotal!.toStringAsFixed(2);
      _lastOcrRawText = res.rawText;
      final gotAny = res.litros != null || res.precioLitro != null || res.importeTotal != null;
      _ocrMessage = gotAny
          ? 'Datos extraídos del surtidor (precio/L, litros y total). Revisa y confirma.'
          : 'Foto del surtidor guardada. No se pudieron leer todos los dígitos; puedes reprocesar o completarlos.';
    });
  }

  void _applyOdometerOcrResult(OdometerScanResult res) {
    setState(() {
      _lastOcrRawText = res.rawText;
      if (res.km == null) {
        _ocrMessage = 'Foto guardada. No se detectaron dígitos claros en el cuentakilómetros.';
      } else {
        _kmCtrl.text = res.km!.toStringAsFixed(0);
        _ocrMessage = 'Kilometraje capturado: ${res.km!.toStringAsFixed(0)} km';
      }
    });
  }

  Future<void> _scanTicket({ImageSource source = ImageSource.camera}) async {
    setState(() {
      _ocrBusy = true;
      _ocrMessage = 'Escaneando factura/ticket de combustible...';
    });
    try {
      final res = await _ocr.scanFuelTicket(source: source);
      if (res == null) {
        if (mounted) setState(() => _ocrMessage = null);
        return;
      }
      if (res.imagePath != null) {
        setState(() => _localTicketPath = res.imagePath);
        unawaited(_uploadPhotoInBackground(res.imagePath!, 'ticket'));
      }
      await _applyTicketOcrResult(res);
    } finally {
      if (mounted) setState(() => _ocrBusy = false);
    }
  }

  Future<void> _scanPumpDisplay({ImageSource source = ImageSource.camera}) async {
    setState(() {
      _ocrBusy = true;
      _ocrMessage = 'Escaneando marcador del surtidor...';
    });
    try {
      final res = await _ocr.scanPumpDisplay(source: source);
      if (res == null) {
        if (mounted) setState(() => _ocrMessage = null);
        return;
      }
      if (res.imagePath != null) {
        setState(() => _localSurtidorPath = res.imagePath);
        unawaited(_uploadPhotoInBackground(res.imagePath!, 'surtidor'));
      }
      _applyPumpOcrResult(res);
    } finally {
      if (mounted) setState(() => _ocrBusy = false);
    }
  }

  Future<void> _scanOdometer({ImageSource source = ImageSource.camera}) async {
    setState(() {
      _ocrBusy = true;
      _ocrMessage = 'Capturando lectura de cuentakilómetros...';
    });
    try {
      final res = await _ocr.scanOdometerWithImage(source: source, minExpectedKm: _getPreviousKm());
      if (res == null) {
        if (mounted) setState(() => _ocrMessage = null);
        return;
      }
      if (res.imagePath != null) {
        setState(() => _localOdometroPath = res.imagePath);
        unawaited(_uploadPhotoInBackground(res.imagePath!, 'odometro'));
      }
      _applyOdometerOcrResult(res);
    } finally {
      if (mounted) setState(() => _ocrBusy = false);
    }
  }

  Future<String?> _ensureLocalPhotoForOcr(String tipo) async {
    String? localPath = tipo == 'odometro'
        ? _localOdometroPath
        : (tipo == 'surtidor' ? _localSurtidorPath : _localTicketPath);
    if (localPath != null && localPath.isNotEmpty && File(localPath).existsSync()) {
      return localPath;
    }
    final remotePath = tipo == 'odometro'
        ? _fotoOdometroPath
        : (tipo == 'surtidor' ? _fotoSurtidorPath : _fotoTicketPath);
    if (remotePath.isEmpty) return null;
    final url = _resolveRemotePhotoUrl(remotePath);
    final resp = await http.get(Uri.parse(url));
    if (resp.statusCode != 200 || resp.bodyBytes.isEmpty) {
      throw Exception('No se pudo descargar la imagen para reprocesar (${resp.statusCode}).');
    }
    final tmpFile = File('${Directory.systemTemp.path}/reocr_${tipo}_${DateTime.now().millisecondsSinceEpoch}.jpg');
    await tmpFile.writeAsBytes(resp.bodyBytes);
    if (mounted) {
      setState(() {
        if (tipo == 'odometro') {
          _localOdometroPath = tmpFile.path;
        } else if (tipo == 'surtidor') {
          _localSurtidorPath = tmpFile.path;
        } else {
          _localTicketPath = tmpFile.path;
        }
      });
    }
    return tmpFile.path;
  }

  Future<void> _reprocessPhoto(String tipo) async {
    setState(() {
      _ocrBusy = true;
      _ocrMessage = 'Reprocesando reconocimiento OCR...';
    });
    try {
      final path = await _ensureLocalPhotoForOcr(tipo);
      if (path == null) {
        if (mounted) setState(() => _ocrMessage = 'No hay imagen disponible para reprocesar.');
        return;
      }
      if (tipo == 'ticket') {
        final res = await _ocr.processFuelTicketImage(path);
        await _applyTicketOcrResult(res);
      } else if (tipo == 'surtidor') {
        final res = await _ocr.processPumpDisplayImage(path);
        _applyPumpOcrResult(res);
      } else {
        final res = await _ocr.processOdometerImage(path, minExpectedKm: _getPreviousKm());
        _applyOdometerOcrResult(res);
      }
    } catch (e) {
      if (mounted) {
        setState(() => _ocrMessage = 'Error al reprocesar OCR: $e');
      }
    } finally {
      if (mounted) setState(() => _ocrBusy = false);
    }
  }

  Future<void> _removePhoto(String tipo) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Borrar foto'),
        content: const Text('¿Quitar esta fotografía del repostaje? (Se aplicará al guardar el repostaje).'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Borrar'),
          ),
        ],
      ),
    );
    if (confirm != true) return;
    setState(() {
      if (tipo == 'odometro') {
        _fotoOdometroPath = '';
        _localOdometroPath = null;
      } else if (tipo == 'surtidor') {
        _fotoSurtidorPath = '';
        _localSurtidorPath = null;
      } else {
        _fotoTicketPath = '';
        _localTicketPath = null;
      }
    });
  }

  void _showPhotoDialog({String? localPath, required String remotePath, required String title}) {
    final hasLocal = localPath != null && localPath.isNotEmpty && File(localPath).existsSync();
    final url = _resolveRemotePhotoUrl(remotePath);
    showDialog(
      context: context,
      builder: (ctx) => Dialog(
        insetPadding: const EdgeInsets.all(16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AppBar(
              title: Text(title, style: const TextStyle(fontSize: 16)),
              automaticallyImplyLeading: false,
              actions: [IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(ctx))],
            ),
            ConstrainedBox(
              constraints: BoxConstraints(maxHeight: MediaQuery.of(ctx).size.height * 0.68),
              child: InteractiveViewer(
                child: hasLocal
                    ? Image.file(File(localPath), fit: BoxFit.contain)
                    : Image.network(url, fit: BoxFit.contain),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showRawOcrDialog() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Texto detectado por OCR'),
        content: SingleChildScrollView(
          child: SelectableText(
            _lastOcrRawText.isEmpty ? '(Sin texto detectado)' : _lastOcrRawText,
            style: const TextStyle(fontFamily: 'monospace', fontSize: 12),
          ),
        ),
        actions: [TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cerrar'))],
      ),
    );
  }

  Widget _buildPhotoSlotCard({
    required String tipo,
    required String title,
    required IconData icon,
    required Color color,
    required String? localPath,
    required String remotePath,
    required Future<void> Function({ImageSource source}) onCapture,
  }) {
    final hasLocal = localPath != null && localPath.isNotEmpty && File(localPath).existsSync();
    final hasRemote = remotePath.isNotEmpty;
    final hasPhoto = hasLocal || hasRemote;

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, color: color, size: 20),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(title, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5)),
                ),
                if (hasPhoto)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: Colors.green.shade50,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      hasRemote ? 'Guardada' : 'Subiendo...',
                      style: TextStyle(fontSize: 11, color: Colors.green.shade800, fontWeight: FontWeight.w600),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            if (hasPhoto)
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  GestureDetector(
                    onTap: () => _showPhotoDialog(localPath: localPath, remotePath: remotePath, title: title),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: SizedBox(
                        width: 72,
                        height: 72,
                        child: hasLocal
                            ? Image.file(File(localPath), fit: BoxFit.cover)
                            : Image.network(
                                _resolveRemotePhotoUrl(remotePath),
                                fit: BoxFit.cover,
                                errorBuilder: (_, __, ___) => const Icon(Icons.broken_image, size: 32, color: Colors.grey),
                              ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        OutlinedButton.icon(
                          onPressed: () => _showPhotoDialog(localPath: localPath, remotePath: remotePath, title: title),
                          style: OutlinedButton.styleFrom(visualDensity: VisualDensity.compact),
                          icon: const Icon(Icons.visibility_outlined, size: 16),
                          label: const Text('Ver', style: TextStyle(fontSize: 12)),
                        ),
                        OutlinedButton.icon(
                          onPressed: _ocrBusy ? null : () => _reprocessPhoto(tipo),
                          style: OutlinedButton.styleFrom(visualDensity: VisualDensity.compact),
                          icon: const Icon(Icons.refresh, size: 16),
                          label: const Text('Reprocesar OCR', style: TextStyle(fontSize: 12)),
                        ),
                        OutlinedButton.icon(
                          onPressed: _ocrBusy ? null : () => onCapture(source: ImageSource.camera),
                          style: OutlinedButton.styleFrom(visualDensity: VisualDensity.compact),
                          icon: const Icon(Icons.camera_alt_outlined, size: 16),
                          label: const Text('Cámara', style: TextStyle(fontSize: 12)),
                        ),
                        OutlinedButton.icon(
                          onPressed: _ocrBusy ? null : () => onCapture(source: ImageSource.gallery),
                          style: OutlinedButton.styleFrom(visualDensity: VisualDensity.compact),
                          icon: const Icon(Icons.photo_library_outlined, size: 16),
                          label: const Text('Galería', style: TextStyle(fontSize: 12)),
                        ),
                        OutlinedButton.icon(
                          onPressed: () => _removePhoto(tipo),
                          style: OutlinedButton.styleFrom(
                            visualDensity: VisualDensity.compact,
                            foregroundColor: Colors.red,
                          ),
                          icon: const Icon(Icons.delete_outline, size: 16),
                          label: const Text('Borrar', style: TextStyle(fontSize: 12)),
                        ),
                      ],
                    ),
                  ),
                ],
              )
            else
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _ocrBusy ? null : () => onCapture(source: ImageSource.camera),
                      icon: const Icon(Icons.camera_alt_outlined, size: 17),
                      label: const Text('Cámara', style: TextStyle(fontSize: 12.5)),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _ocrBusy ? null : () => onCapture(source: ImageSource.gallery),
                      icon: const Icon(Icons.photo_library_outlined, size: 17),
                      label: const Text('Galería', style: TextStyle(fontSize: 12.5)),
                    ),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _pickFechaForm() async {
    final iso = _displayToIsoDate(_fechaCtrl.text);
    final initial = DateTime.tryParse(iso) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (picked != null) {
      setState(() {
        _fechaCtrl.text =
            '${picked.day.toString().padLeft(2, '0')}-${picked.month.toString().padLeft(2, '0')}-${picked.year.toString().padLeft(4, '0')}';
      });
    }
  }

  Future<void> _save() async {
    if (_vehiculoId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Debes seleccionar un vehículo antes de guardar.')),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      final res = await widget.api.post('save_repostaje', {
        'id': widget.editItem?.id ?? 0,
        'vehiculo_id': _vehiculoId,
        'fecha': _displayToIsoDate(_fechaCtrl.text.trim()),
        'km_odometro': _kmCtrl.text.trim(),
        'litros': _litrosCtrl.text.trim(),
        'precio_litro': _precioCtrl.text.trim(),
        'importe_total': _importeCtrl.text.trim(),
        'estacion_servicio': _selectedEstacion,
        'notas': _notasCtrl.text.trim(),
        'foto_ticket_path': _fotoTicketPath,
        'foto_surtidor_path': _fotoSurtidorPath,
        'foto_odometro_path': _fotoOdometroPath,
      });
      if (res['success'] == true && mounted) {
        if (res['email_sent'] == true) {
          final alertsCnt = int.tryParse((res['alertas_count'] ?? 0).toString()) ?? 0;
          final msg = alertsCnt > 0
              ? 'Repostaje guardado y correo enviado (incluye $alertsCnt aviso(s) de mantenimiento).'
              : 'Repostaje guardado y correo enviado.';
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
        }
        Navigator.of(context).pop(true);
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text((res['error'] ?? 'Error al guardar').toString())),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _pickSourceAndScan(
    String title,
    Future<void> Function({ImageSource source}) onCapture,
  ) async {
    if (_ocrBusy) return;
    final selected = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
            ),
            ListTile(
              leading: const Icon(Icons.camera_alt, color: Color(0xFF0F766E)),
              title: const Text('Hacer foto con la cámara'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library, color: Color(0xFF0284C7)),
              title: const Text('Elegir archivo / galería'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (selected != null) {
      await onCapture(source: selected);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.editItem == null ? 'Nuevo Repostaje' : 'Modificar Repostaje'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _ocrBusy ? null : () => _pickSourceAndScan('Foto Factura / Ticket', _scanTicket),
                  style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 10)),
                  icon: const Icon(Icons.receipt_long, size: 18),
                  label: const Text('Factura', style: TextStyle(fontSize: 12.5), overflow: TextOverflow.ellipsis),
                ),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _ocrBusy ? null : () => _pickSourceAndScan('Foto Surtidor', _scanPumpDisplay),
                  style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 10)),
                  icon: const Icon(Icons.local_gas_station, size: 18),
                  label: const Text('Surtidor', style: TextStyle(fontSize: 12.5), overflow: TextOverflow.ellipsis),
                ),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _ocrBusy ? null : () => _pickSourceAndScan('Foto Odómetro', _scanOdometer),
                  style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 10)),
                  icon: const Icon(Icons.speed, size: 18),
                  label: const Text('Odómetro', style: TextStyle(fontSize: 12.5), overflow: TextOverflow.ellipsis),
                ),
              ),
            ],
          ),
          if (_ocrMessage != null) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.teal.shade50,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  Expanded(child: Text(_ocrMessage!, style: const TextStyle(fontSize: 13))),
                  if (_lastOcrRawText.isNotEmpty)
                    TextButton(
                      onPressed: _showRawOcrDialog,
                      style: TextButton.styleFrom(visualDensity: VisualDensity.compact),
                      child: const Text('Ver texto', style: TextStyle(fontSize: 12)),
                    ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 10),
          ExpansionTile(
            tilePadding: EdgeInsets.zero,
            initiallyExpanded: _fotoTicketPath.isNotEmpty ||
                _fotoSurtidorPath.isNotEmpty ||
                _fotoOdometroPath.isNotEmpty ||
                _localTicketPath != null ||
                _localSurtidorPath != null ||
                _localOdometroPath != null,
            title: const Text(
              'Fotos del repostaje (Factura · Surtidor · Odómetro)',
              style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600),
            ),
            children: [
              _buildPhotoSlotCard(
                tipo: 'ticket',
                title: 'Foto Factura / Ticket',
                icon: Icons.receipt_long,
                color: const Color(0xFF0F766E),
                localPath: _localTicketPath,
                remotePath: _fotoTicketPath,
                onCapture: _scanTicket,
              ),
              _buildPhotoSlotCard(
                tipo: 'surtidor',
                title: 'Foto Surtidor',
                icon: Icons.local_gas_station,
                color: const Color(0xFF0284C7),
                localPath: _localSurtidorPath,
                remotePath: _fotoSurtidorPath,
                onCapture: _scanPumpDisplay,
              ),
              _buildPhotoSlotCard(
                tipo: 'odometro',
                title: 'Foto Odómetro',
                icon: Icons.speed,
                color: const Color(0xFFF59E0B),
                localPath: _localOdometroPath,
                remotePath: _fotoOdometroPath,
                onCapture: _scanOdometer,
              ),
            ],
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<int>(
            value: _vehiculoId,
            hint: const Text('-- Selecciona un vehículo --'),
            decoration: const InputDecoration(
              labelText: 'Vehículo * (Permite reasignar al editar)',
              border: OutlineInputBorder(),
            ),
            items: widget.vehiculos
                .map((v) => DropdownMenuItem(value: v.id, child: Text(v.nombre)))
                .toList(),
            onChanged: (v) {
              setState(() => _vehiculoId = v);
            },
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _fechaCtrl,
            decoration: InputDecoration(
              labelText: widget.api.isAdmin ? 'Fecha. (dd-mm-aaaa)' : 'Fecha (dd-mm-aaaa)',
              border: const OutlineInputBorder(),
              suffixIcon: IconButton(
                icon: const Icon(Icons.calendar_today),
                onPressed: _pickFechaForm,
              ),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _kmCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(
              labelText: 'Kilómetros en cuentakilómetros (KM.)',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            _getParcialMessage(),
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
              color: _getParcialMessage().startsWith('⚠️') ? Colors.red.shade700 : const Color(0xFF0F766E),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _litrosCtrl,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(labelText: 'Litros', border: OutlineInputBorder()),
                  onChanged: (_) => _recalcFromLitrosOrPrecio(),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: TextField(
                  controller: _precioCtrl,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(labelText: 'Precio (€/L)', border: OutlineInputBorder()),
                  onChanged: (_) => _recalcFromLitrosOrPrecio(),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _importeCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Importe Total (€)', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 12),
          Builder(
            builder: (context) {
              final seen = <String>{};
              final stationItems = <DropdownMenuItem<String>>[
                const DropdownMenuItem<String>(
                  value: '',
                  child: Text('-- Sin especificar (Opcional) --'),
                ),
              ];
              for (final est in _localEstaciones) {
                if (est.nombre.isNotEmpty && seen.add(est.nombre)) {
                  final label = est.localidad.isNotEmpty ? '${est.nombre} (${est.localidad})' : est.nombre;
                  stationItems.add(DropdownMenuItem<String>(
                    value: est.nombre,
                    child: Text(label, overflow: TextOverflow.ellipsis),
                  ));
                }
              }
              if (_selectedEstacion.isNotEmpty && seen.add(_selectedEstacion)) {
                stationItems.add(DropdownMenuItem<String>(
                  value: _selectedEstacion,
                  child: Text(_selectedEstacion, overflow: TextOverflow.ellipsis),
                ));
              }
              return DropdownButtonFormField<String>(
                value: _selectedEstacion,
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'Estación de Servicio (opcional)',
                  border: OutlineInputBorder(),
                ),
                items: stationItems,
                onChanged: (val) => setState(() => _selectedEstacion = val ?? ''),
              );
            },
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _notasCtrl,
            decoration: const InputDecoration(labelText: 'Notas / Mantenimiento', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 20),
          SizedBox(
            height: 50,
            child: FilledButton.icon(
              onPressed: _saving ? null : _save,
              icon: const Icon(Icons.save),
              label: Text(_saving ? 'Guardando...' : 'Guardar Repostaje'),
            ),
          ),
        ],
      ),
    );
  }
}

class VehiculosScreen extends StatefulWidget {
  final ApiService api;
  final List<Vehiculo> vehiculos;
  final List<ParqueMovil> parques;

  const VehiculosScreen({
    super.key,
    required this.api,
    required this.vehiculos,
    this.parques = const [],
  });

  @override
  State<VehiculosScreen> createState() => _VehiculosScreenState();
}

class _VehiculosScreenState extends State<VehiculosScreen> {
  final _nombreCtrl = TextEditingController();
  final _matriculaCtrl = TextEditingController();
  final _modeloCtrl = TextEditingController();
  String _tipoCombustible = 'Diésel';
  int? _selectedParqueId;
  int _editingId = 0;

  static const List<String> _fuelTypes = [
    'Diésel',
    'Gasolina 95',
    'Gasolina 98',
    'GLP',
    'Híbrido',
    'Eléctrico',
  ];

  void _clearForm() {
    setState(() {
      _editingId = 0;
      _nombreCtrl.clear();
      _matriculaCtrl.clear();
      _modeloCtrl.clear();
      _tipoCombustible = 'Diésel';
      _selectedParqueId = null;
    });
  }

  Future<void> _saveVehiculo() async {
    if (_nombreCtrl.text.trim().isEmpty) return;
    final res = await widget.api.post('save_vehiculo', {
      'id': _editingId,
      'parque_id': _selectedParqueId,
      'nombre': _nombreCtrl.text.trim(),
      'matricula': _matriculaCtrl.text.trim(),
      'modelo': _modeloCtrl.text.trim(),
      'tipo_combustible': _tipoCombustible,
    });
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Vehículo guardado').toString())),
      );
      if (res['success'] == true) {
        Navigator.of(context).pop();
      }
    }
  }

  Future<void> _confirmDeleteVehiculo(Vehiculo v) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Eliminar Vehículo'),
        content: Text(
          '¿Seguro que deseas dar de baja "${v.nombre}"${v.matricula.isNotEmpty ? " (${v.matricula})" : ""}?\n\n'
          'Se aplicará borrado lógico (activo = 0): dejará de mostrarse en la lista activa, pero su historial permanece guardado en la base de datos.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Dar de baja'),
          ),
        ],
      ),
    );
    if (confirm != true) return;

    final res = await widget.api.post('deactivate_vehiculo', {'id': v.id});
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Vehículo dado de baja').toString())),
      );
      if (res['success'] == true) {
        Navigator.of(context).pop();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final fuelOptions = _fuelTypes.contains(_tipoCombustible)
        ? _fuelTypes
        : [_tipoCombustible, ..._fuelTypes];

    return Scaffold(
      appBar: AppBar(title: const Text('Mis Vehículos (SCRUB)')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ...widget.vehiculos.map(
            (v) => Card(
              child: ListTile(
                leading: const Icon(Icons.directions_car, color: Color(0xFF0F766E)),
                title: Text(v.nombre, style: const TextStyle(fontWeight: FontWeight.bold)),
                subtitle: Text(
                  '${v.matricula.isNotEmpty ? v.matricula : "Sin matrícula"} · ${v.tipoCombustible}'
                  '${v.parqueNombre.isNotEmpty ? " · Parque: ${v.parqueNombre}" : " · Personal"}',
                ),
                trailing: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    IconButton(
                      tooltip: 'Editar vehículo',
                      icon: const Icon(Icons.edit),
                      onPressed: () {
                        setState(() {
                          _editingId = v.id;
                          _nombreCtrl.text = v.nombre;
                          _matriculaCtrl.text = v.matricula;
                          _modeloCtrl.text = v.modelo;
                          _tipoCombustible = v.tipoCombustible.isNotEmpty ? v.tipoCombustible : 'Diésel';
                          _selectedParqueId = v.parqueId;
                        });
                      },
                    ),
                    IconButton(
                      tooltip: 'Borrar vehículo (baja lógica)',
                      icon: const Icon(Icons.delete_outline, color: Colors.red),
                      onPressed: () => _confirmDeleteVehiculo(v),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const Divider(height: 32),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                _editingId == 0 ? 'Añadir Nuevo Vehículo' : 'Editar Vehículo',
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              if (_editingId != 0)
                TextButton.icon(
                  onPressed: _clearForm,
                  icon: const Icon(Icons.close, size: 18),
                  label: const Text('Cancelar'),
                ),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _nombreCtrl,
            decoration: const InputDecoration(labelText: 'Nombre del vehículo *', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _matriculaCtrl,
            decoration: const InputDecoration(labelText: 'Matrícula', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _modeloCtrl,
            decoration: const InputDecoration(labelText: 'Marca / Modelo', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            value: _tipoCombustible,
            decoration: const InputDecoration(labelText: 'Tipo de combustible', border: OutlineInputBorder()),
            items: fuelOptions.map((f) => DropdownMenuItem(value: f, child: Text(f))).toList(),
            onChanged: (v) {
              if (v != null) setState(() => _tipoCombustible = v);
            },
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<int?>(
            value: (_selectedParqueId != null && widget.parques.any((p) => p.id == _selectedParqueId))
                ? _selectedParqueId
                : null,
            decoration: const InputDecoration(
              labelText: 'Parque Móvil / Flota (opcional)',
              border: OutlineInputBorder(),
            ),
            items: [
              const DropdownMenuItem<int?>(
                value: null,
                child: Text('Personal (solo mi usuario)'),
              ),
              ...widget.parques.map(
                (pq) => DropdownMenuItem<int?>(
                  value: pq.id,
                  child: Text('Parque Móvil: ${pq.nombre}'),
                ),
              ),
            ],
            onChanged: (val) => setState(() => _selectedParqueId = val),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: _saveVehiculo,
            icon: const Icon(Icons.save),
            label: Text(_editingId == 0 ? 'Guardar Vehículo' : 'Actualizar Vehículo'),
          ),
        ],
      ),
    );
  }
}

class EstacionesScreen extends StatefulWidget {
  final ApiService api;
  final List<EstacionServicio> estaciones;

  const EstacionesScreen({super.key, required this.api, required this.estaciones});

  @override
  State<EstacionesScreen> createState() => _EstacionesScreenState();
}

class _EstacionesScreenState extends State<EstacionesScreen> {
  final _nombreCtrl = TextEditingController();
  final _marcaCtrl = TextEditingController();
  final _localidadCtrl = TextEditingController();
  final _direccionCtrl = TextEditingController();
  final _notasCtrl = TextEditingController();
  int _editingId = 0;

  Future<void> _saveEstacion() async {
    if (_nombreCtrl.text.trim().isEmpty) return;
    await widget.api.post('save_estacion', {
      'id': _editingId,
      'nombre': _nombreCtrl.text.trim(),
      'marca': _marcaCtrl.text.trim(),
      'localidad': _localidadCtrl.text.trim(),
      'direccion': _direccionCtrl.text.trim(),
      'notas': _notasCtrl.text.trim(),
    });
    if (mounted) Navigator.of(context).pop();
  }

  Future<void> _deactivateEstacion(int id) async {
    await widget.api.post('deactivate_estacion', {'id': id});
    if (mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Estaciones de Servicio')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ...widget.estaciones.map(
            (e) => Card(
              child: ListTile(
                leading: const Icon(Icons.storefront, color: Color(0xFFF59E0B)),
                title: Text(e.nombre, style: const TextStyle(fontWeight: FontWeight.bold)),
                subtitle: Text('${e.marca} ${e.localidad.isNotEmpty ? "· ${e.localidad}" : ""}'),
                trailing: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    IconButton(
                      icon: const Icon(Icons.edit),
                      onPressed: () {
                        setState(() {
                          _editingId = e.id;
                          _nombreCtrl.text = e.nombre;
                          _marcaCtrl.text = e.marca;
                          _localidadCtrl.text = e.localidad;
                          _direccionCtrl.text = e.direccion;
                          _notasCtrl.text = e.notas;
                        });
                      },
                    ),
                    IconButton(
                      icon: const Icon(Icons.delete_outline, color: Colors.red),
                      onPressed: () => _deactivateEstacion(e.id),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const Divider(height: 32),
          Text(
            _editingId == 0 ? 'Añadir Estación de Servicio' : 'Editar Estación',
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _nombreCtrl,
            decoration: const InputDecoration(labelText: 'Nombre de la estación *', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _marcaCtrl,
            decoration: const InputDecoration(labelText: 'Marca (Repsol, Cepsa, etc.)', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _localidadCtrl,
            decoration: const InputDecoration(labelText: 'Localidad', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _direccionCtrl,
            decoration: const InputDecoration(labelText: 'Dirección', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _notasCtrl,
            decoration: const InputDecoration(labelText: 'Notas', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: _saveEstacion,
            icon: const Icon(Icons.save),
            label: const Text('Guardar Estación'),
          ),
        ],
      ),
    );
  }
}

Future<Proveedor?> showQuickCreateProveedorDialog(BuildContext context, ApiService api, {String initialTipo = 'taller'}) async {
  String tipo = initialTipo;
  final nombreCtrl = TextEditingController();
  final telCtrl = TextEditingController();
  final locCtrl = TextEditingController();
  final dirCtrl = TextEditingController();
  final notasCtrl = TextEditingController();

  final created = await showDialog<Proveedor?>(
    context: context,
    builder: (ctx) => StatefulBuilder(
      builder: (ctx, setDlg) => AlertDialog(
        title: const Text('Nuevo Taller / Aseguradora / ITV'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<String>(
                value: tipo,
                decoration: const InputDecoration(labelText: 'Tipo *', border: OutlineInputBorder(), isDense: true),
                items: const [
                  DropdownMenuItem(value: 'taller', child: Text('Taller')),
                  DropdownMenuItem(value: 'aseguradora', child: Text('Aseguradora')),
                  DropdownMenuItem(value: 'itv', child: Text('ITV')),
                ],
                onChanged: (v) {
                  if (v != null) setDlg(() => tipo = v);
                },
              ),
              const SizedBox(height: 10),
              TextField(
                controller: nombreCtrl,
                decoration: const InputDecoration(labelText: 'Nombre *', border: OutlineInputBorder(), isDense: true),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: telCtrl,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(labelText: 'Teléfono', border: OutlineInputBorder(), isDense: true),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: locCtrl,
                decoration: const InputDecoration(labelText: 'Localidad', border: OutlineInputBorder(), isDense: true),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: dirCtrl,
                decoration: const InputDecoration(labelText: 'Dirección', border: OutlineInputBorder(), isDense: true),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: notasCtrl,
                decoration: const InputDecoration(labelText: 'Notas', border: OutlineInputBorder(), isDense: true),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, null), child: const Text('Cancelar')),
          FilledButton(
            onPressed: () async {
              if (nombreCtrl.text.trim().isEmpty) return;
              final res = await api.post('save_proveedor', {
                'id': 0,
                'tipo': tipo,
                'nombre': nombreCtrl.text.trim(),
                'telefono': telCtrl.text.trim(),
                'localidad': locCtrl.text.trim(),
                'direccion': dirCtrl.text.trim(),
                'notas': notasCtrl.text.trim(),
              });
              if (res['success'] == true && res['proveedor'] is Map<String, dynamic>) {
                if (ctx.mounted) Navigator.pop(ctx, Proveedor.fromJson(res['proveedor'] as Map<String, dynamic>));
              } else if (ctx.mounted) {
                ScaffoldMessenger.of(ctx).showSnackBar(
                  SnackBar(content: Text((res['error'] ?? 'Error al guardar').toString())),
                );
              }
            },
            child: const Text('Crear'),
          ),
        ],
      ),
    ),
  );
  return created;
}

class ProveedoresScreen extends StatefulWidget {
  final ApiService api;
  final List<Proveedor> proveedores;

  const ProveedoresScreen({super.key, required this.api, required this.proveedores});

  @override
  State<ProveedoresScreen> createState() => _ProveedoresScreenState();
}

class _ProveedoresScreenState extends State<ProveedoresScreen> {
  late List<Proveedor> _list;
  String _activeTipo = 'taller';
  final _nombreCtrl = TextEditingController();
  final _telCtrl = TextEditingController();
  final _locCtrl = TextEditingController();
  final _dirCtrl = TextEditingController();
  final _notasCtrl = TextEditingController();
  int _editingId = 0;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _list = List<Proveedor>.from(widget.proveedores);
    _reload();
  }

  Future<void> _reload() async {
    setState(() => _loading = true);
    try {
      final res = await widget.api.post('list_proveedores', {});
      if (res['success'] == true && mounted) {
        setState(() {
          _list = (res['proveedores'] as List? ?? [])
              .map((e) => Proveedor.fromJson(e as Map<String, dynamic>))
              .toList();
        });
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _resetForm() {
    setState(() {
      _editingId = 0;
      _nombreCtrl.clear();
      _telCtrl.clear();
      _locCtrl.clear();
      _dirCtrl.clear();
      _notasCtrl.clear();
    });
  }

  Future<void> _save() async {
    if (_nombreCtrl.text.trim().isEmpty) return;
    final res = await widget.api.post('save_proveedor', {
      'id': _editingId,
      'tipo': _activeTipo,
      'nombre': _nombreCtrl.text.trim(),
      'telefono': _telCtrl.text.trim(),
      'localidad': _locCtrl.text.trim(),
      'direccion': _dirCtrl.text.trim(),
      'notas': _notasCtrl.text.trim(),
    });
    if (res['success'] == true) {
      _resetForm();
      await _reload();
    } else if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['error'] ?? 'Error al guardar').toString())),
      );
    }
  }

  Future<void> _deactivate(int id) async {
    await widget.api.post('deactivate_proveedor', {'id': id});
    await _reload();
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _list.where((p) => p.tipo == _activeTipo).toList();
    final tipoLabel = _activeTipo == 'aseguradora' ? 'Aseguradora' : (_activeTipo == 'itv' ? 'ITV' : 'Taller');
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Talleres, Aseguradoras e ITVs'),
          bottom: TabBar(
            onTap: (idx) {
              setState(() {
                _activeTipo = idx == 0 ? 'taller' : (idx == 1 ? 'aseguradora' : 'itv');
              });
              _resetForm();
            },
            tabs: const [
              Tab(icon: Icon(Icons.build), text: 'Talleres'),
              Tab(icon: Icon(Icons.shield_outlined), text: 'Aseguradoras'),
              Tab(icon: Icon(Icons.fact_check_outlined), text: 'ITVs'),
            ],
          ),
        ),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (filtered.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 20),
                      child: Center(child: Text('No hay registros en esta categoría.')),
                    )
                  else
                    ...filtered.map(
                      (p) => Card(
                        child: ListTile(
                          leading: Icon(
                            p.tipo == 'aseguradora'
                                ? Icons.shield_outlined
                                : (p.tipo == 'itv' ? Icons.fact_check_outlined : Icons.build),
                            color: const Color(0xFF0284C7),
                          ),
                          title: Text(p.nombre, style: const TextStyle(fontWeight: FontWeight.bold)),
                          subtitle: Text([
                            if (p.telefono.isNotEmpty) 'Tel: ${p.telefono}',
                            if (p.localidad.isNotEmpty) p.localidad,
                            if (p.direccion.isNotEmpty) p.direccion,
                            if (p.notas.isNotEmpty) p.notas,
                          ].join(' · ')),
                          trailing: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              IconButton(
                                icon: const Icon(Icons.edit),
                                onPressed: () {
                                  setState(() {
                                    _editingId = p.id;
                                    _nombreCtrl.text = p.nombre;
                                    _telCtrl.text = p.telefono;
                                    _locCtrl.text = p.localidad;
                                    _dirCtrl.text = p.direccion;
                                    _notasCtrl.text = p.notas;
                                  });
                                },
                              ),
                              IconButton(
                                icon: const Icon(Icons.delete_outline, color: Colors.red),
                                onPressed: () => _deactivate(p.id),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  const Divider(height: 32),
                  Text(
                    _editingId == 0 ? 'Añadir $tipoLabel' : 'Editar $tipoLabel',
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _nombreCtrl,
                    decoration: InputDecoration(labelText: 'Nombre ($tipoLabel) *', border: const OutlineInputBorder()),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _telCtrl,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(labelText: 'Teléfono', border: OutlineInputBorder()),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _locCtrl,
                    decoration: const InputDecoration(labelText: 'Localidad', border: OutlineInputBorder()),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _dirCtrl,
                    decoration: const InputDecoration(labelText: 'Dirección', border: OutlineInputBorder()),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _notasCtrl,
                    decoration: const InputDecoration(labelText: 'Notas', border: OutlineInputBorder()),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      if (_editingId > 0) ...[
                        OutlinedButton(onPressed: _resetForm, child: const Text('Cancelar')),
                        const SizedBox(width: 10),
                      ],
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: _save,
                          icon: const Icon(Icons.save),
                          label: Text('Guardar $tipoLabel'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
      ),
    );
  }
}

class TareasScreen extends StatefulWidget {
  final ApiService api;
  final List<Vehiculo> vehiculos;
  final List<Proveedor> proveedores;
  final List<TareaMaestro> tareasMaestro;
  final List<VehiculoTarea> vehiculoTareas;
  final Map<int, double> ultimoKmPorVehiculo;
  final int initialVehiculoId;

  const TareasScreen({
    super.key,
    required this.api,
    required this.vehiculos,
    required this.proveedores,
    required this.tareasMaestro,
    required this.vehiculoTareas,
    required this.ultimoKmPorVehiculo,
    required this.initialVehiculoId,
  });

  @override
  State<TareasScreen> createState() => _TareasScreenState();
}

class _TareasScreenState extends State<TareasScreen> {
  late int _vehiculoId;
  String _estadoFilter = 'todas'; // 'todas', 'pendiente', 'proximas', 'finalizada'
  String _search = '';
  late List<Proveedor> _proveedores;
  late List<TareaMaestro> _maestro;
  late List<VehiculoTarea> _tareas;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _vehiculoId = widget.initialVehiculoId;
    _proveedores = List<Proveedor>.from(widget.proveedores);
    _maestro = widget.tareasMaestro;
    _tareas = widget.vehiculoTareas;
    _reload();
  }

  Future<void> _reload() async {
    setState(() => _loading = true);
    try {
      final res = await widget.api.post('dashboard', {'vehiculo_id': _vehiculoId});
      if (res['success'] == true && mounted) {
        setState(() {
          _proveedores = (res['proveedores'] as List? ?? [])
              .map((e) => Proveedor.fromJson(e as Map<String, dynamic>))
              .toList();
          _maestro = (res['tareas_maestro'] as List? ?? [])
              .map((e) => TareaMaestro.fromJson(e as Map<String, dynamic>))
              .toList();
          _tareas = (res['vehiculo_tareas'] as List? ?? [])
              .map((e) => VehiculoTarea.fromJson(e as Map<String, dynamic>))
              .toList();
        });
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<VehiculoTarea> get _filtered {
    return _tareas.where((t) {
      if (_vehiculoId > 0 && t.vehiculoId != _vehiculoId) return false;
      if (_estadoFilter == 'pendiente' && t.estado != 'pendiente') return false;
      if (_estadoFilter == 'finalizada' && t.estado != 'finalizada') return false;
      if (_estadoFilter == 'proximas' && !(t.estado == 'pendiente' && t.enAlerta)) return false;
      if (_search.isNotEmpty) {
        final q = _search.toLowerCase();
        final txt = '${t.vehiculoNombre} ${t.descripcion} ${t.notas} ${t.motivoAlerta}'.toLowerCase();
        if (!txt.contains(q)) return false;
      }
      return true;
    }).toList();
  }

  Future<void> _completeTask(VehiculoTarea t) async {
    final double? prevTaskKm = (t.ultimoKm != null && t.ultimoKm! > 0)
        ? t.ultimoKm
        : (t.kmActual > 0 ? t.kmActual : null);
    final fechaCtrl = TextEditingController(text: DateTime.now().toIso8601String().substring(0, 10));
    final kmCtrl = TextEditingController(
      text: t.kmActual > 0
          ? t.kmActual.toStringAsFixed(0)
          : (prevTaskKm != null ? prevTaskKm.toStringAsFixed(0) : ''),
    );
    final costeCtrl = TextEditingController(text: t.coste != null ? t.coste!.toStringAsFixed(2) : '');
    final garantiaCtrl = TextEditingController(text: t.garantiaMeses != null ? t.garantiaMeses.toString() : '');
    final notasCtrl = TextEditingController();
    int selectedProvId = (t.proveedorId != null && _proveedores.any((p) => p.id == t.proveedorId)) ? t.proveedorId! : 0;
    final bool isRenewable = t.tipoPeriodicidad != 'unica' && ((t.periodoDias ?? 0) > 0 || (t.periodoKm ?? 0) > 0);

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDlg) => AlertDialog(
          title: const Text('Finalizar Tarea'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${t.vehiculoNombre}: ${t.descripcion}', style: const TextStyle(fontWeight: FontWeight.bold)),
                const SizedBox(height: 12),
                TextField(
                  controller: fechaCtrl,
                  decoration: const InputDecoration(labelText: 'Fecha de realización (YYYY-MM-DD)', border: OutlineInputBorder(), isDense: true),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: kmCtrl,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'Km al realizarla', border: OutlineInputBorder(), isDense: true),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<int>(
                        value: selectedProvId,
                        isExpanded: true,
                        decoration: const InputDecoration(labelText: 'Taller / Aseguradora / ITV', border: OutlineInputBorder(), isDense: true),
                        items: [
                          const DropdownMenuItem(value: 0, child: Text('-- Sin especificar --')),
                          ..._proveedores.map(
                            (p) => DropdownMenuItem(
                              value: p.id,
                              child: Text('[${p.tipoLabel}] ${p.nombre}', overflow: TextOverflow.ellipsis),
                            ),
                          ),
                        ],
                        onChanged: (v) => setDlg(() => selectedProvId = v ?? 0),
                      ),
                    ),
                    const SizedBox(width: 6),
                    IconButton(
                      tooltip: 'Crear Taller / Aseguradora / ITV',
                      icon: const Icon(Icons.add_circle_outline, color: Color(0xFF0F766E)),
                      onPressed: () async {
                        final created = await showQuickCreateProveedorDialog(ctx, widget.api);
                        if (created != null) {
                          setState(() => _proveedores.add(created));
                          setDlg(() => selectedProvId = created.id);
                        }
                      },
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: costeCtrl,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(labelText: 'Coste / Precio (€)', border: OutlineInputBorder(), isDense: true),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: garantiaCtrl,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'Garantía (meses, opcional)', border: OutlineInputBorder(), isDense: true),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: notasCtrl,
                  decoration: const InputDecoration(labelText: 'Notas (observaciones...)', border: OutlineInputBorder(), isDense: true),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Confirmar')),
          ],
        ),
      ),
    );
    if (ok == true) {
      int crearSiguiente = 0;
      if (isRenewable && mounted) {
        final renew = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Text('Siguiente periodo'),
            content: const Text('Esta tarea es periódica. ¿Deseas registrar también una tarea para el siguiente periodo?'),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Solo finalizar')),
              FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Sí, crear siguiente')),
            ],
          ),
        );
        crearSiguiente = (renew == true) ? 1 : 0;
      }
      final enteredKm = double.tryParse(kmCtrl.text.trim().replaceAll(',', '.')) ?? 0;
      if (crearSiguiente == 1 && enteredKm <= 0 && mounted) {
        final hasPrev = prevTaskKm != null && prevTaskKm > 0;
        final confirmFallback = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Text('Sin nuevo kilometraje'),
            content: Text(
              hasPrev
                  ? 'No se ha indicado un nuevo kilometraje al renovar la tarea.\n\nPor defecto se utilizará el kilometraje de la tarea anterior (${prevTaskKm.toStringAsFixed(0)} km). ¿Deseas continuar?'
                  : 'No se ha indicado un nuevo kilometraje y la tarea anterior tampoco tenía kilómetros registrados. ¿Deseas continuar igualmente?',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('Introducir km'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(ctx, true),
                child: Text(hasPrev ? 'Usar ${prevTaskKm.toStringAsFixed(0)} km' : 'Continuar'),
              ),
            ],
          ),
        );
        if (confirmFallback != true) return;
        if (hasPrev) {
          kmCtrl.text = prevTaskKm.toStringAsFixed(0);
        }
      }
      final res = await widget.api.post('complete_vehiculo_tarea', {
        'id': t.id,
        'fecha_finalizacion': fechaCtrl.text.trim(),
        'km_finalizacion': kmCtrl.text.trim(),
        'proveedor_id': selectedProvId,
        'coste': costeCtrl.text.trim(),
        'garantia_meses': garantiaCtrl.text.trim(),
        'notas': notasCtrl.text.trim(),
        'crear_siguiente': crearSiguiente,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Actualizado').toString())),
        );
        _reload();
      }
    }
  }

  Future<void> _reopenTask(VehiculoTarea t) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Deshacer finalización'),
        content: Text('¿Volver a dejar "${t.descripcion}" como pendiente?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Sí, reabrir')),
        ],
      ),
    );
    if (ok == true) {
      final res = await widget.api.post('reopen_vehiculo_tarea', {'id': t.id});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Actualizado').toString())),
        );
        _reload();
      }
    }
  }

  void _showComparativaDialog(VehiculoTarea t) {
    String relKey(VehiculoTarea x) =>
        '${x.vehiculoId}:${x.tareaMaestroId != null && x.tareaMaestroId! > 0 ? "m:${x.tareaMaestroId}" : "d:${x.descripcion.trim().toLowerCase()}"}';
    final targetKey = relKey(t);
    final related = _tareas.where((x) => relKey(x) == targetKey).toList()
      ..sort((a, b) {
        final fa = a.fechaFinalizacion.isNotEmpty ? a.fechaFinalizacion : a.ultimaFecha;
        final fb = b.fechaFinalizacion.isNotEmpty ? b.fechaFinalizacion : b.ultimaFecha;
        final cmp = fa.compareTo(fb);
        return cmp != 0 ? cmp : a.id.compareTo(b.id);
      });
    final costs = related.where((x) => x.coste != null).map((x) => x.coste!).toList();
    final total = costs.fold<double>(0.0, (a, b) => a + b);
    final avg = costs.isNotEmpty ? total / costs.length : 0.0;

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Comparativa de Costes'),
        content: SizedBox(
          width: double.maxFinite,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${t.vehiculoNombre} · ${t.descripcion}', style: const TextStyle(fontWeight: FontWeight.bold)),
                const SizedBox(height: 8),
                if (costs.isNotEmpty)
                  Text(
                    'Registros: ${costs.length} · Total: ${total.toStringAsFixed(2)} € · Media: ${avg.toStringAsFixed(2)} €',
                    style: const TextStyle(fontSize: 12, color: Color(0xFF0F766E), fontWeight: FontWeight.w600),
                  )
                else
                  const Text('Aún no hay costes registrados.', style: TextStyle(fontSize: 12, color: Colors.grey)),
                const Divider(height: 16),
                ...(() {
                  double? prevCost;
                  return related.map((r) {
                    final fecha = r.fechaFinalizacion.isNotEmpty ? r.fechaFinalizacion : (r.ultimaFecha.isNotEmpty ? r.ultimaFecha : '-');
                    final km = r.kmFinalizacion ?? r.ultimoKm;
                    String diffTxt = '';
                    Color diffColor = Colors.grey;
                    if (r.coste != null && prevCost != null) {
                      final diff = r.coste! - prevCost!;
                      final sign = diff > 0 ? '+' : '';
                      final pct = prevCost! > 0 ? ' (${sign}${((diff / prevCost!) * 100).toStringAsFixed(1)}%)' : '';
                      diffTxt = '$sign${diff.toStringAsFixed(2)} €$pct';
                      diffColor = diff > 0 ? Colors.red : (diff < 0 ? Colors.green : Colors.grey);
                    }
                    if (r.coste != null) prevCost = r.coste;
                    return ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        '$fecha ${km != null ? "· ${km.toStringAsFixed(0)} km" : ""}',
                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                      ),
                      subtitle: Text(
                        [
                          if (r.proveedorNombre.isNotEmpty) r.proveedorNombre,
                          r.estado == 'finalizada' ? 'Finalizada' : 'Pendiente',
                          if (r.notas.isNotEmpty) r.notas,
                        ].join(' · '),
                        style: const TextStyle(fontSize: 11.5),
                      ),
                      trailing: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            r.coste != null ? '${r.coste!.toStringAsFixed(2)} €' : '-',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                          ),
                          if (diffTxt.isNotEmpty)
                            Text(diffTxt, style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: diffColor)),
                        ],
                      ),
                    );
                  }).toList();
                })(),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cerrar')),
        ],
      ),
    );
  }

  Future<void> _exportPdf({bool sendEmail = false}) async {
    final list = _filtered;
    if (list.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No hay tareas que coincidan con el filtro.')),
      );
      return;
    }

    String mode = sendEmail ? 'email' : 'pdf';
    String toEmail = '';
    if (sendEmail) {
      final emailCtrl = TextEditingController();
      final choice = await showDialog<String>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Enviar Informe PDF de Tareas'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Puedes compartir el PDF directamente con tu app de correo (Gmail, Outlook, etc.) o enviarlo desde el servidor.',
                style: TextStyle(fontSize: 13),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: emailCtrl,
                keyboardType: TextInputType.emailAddress,
                decoration: const InputDecoration(
                  labelText: 'Email destino (solo para envío por servidor)',
                  hintText: 'Opcional si ya tienes correo configurado',
                  border: OutlineInputBorder(),
                  isDense: true,
                ),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, null), child: const Text('Cancelar')),
            OutlinedButton.icon(
              onPressed: () => Navigator.pop(ctx, 'share'),
              icon: const Icon(Icons.share, size: 18),
              label: const Text('Compartir / App Email'),
            ),
            FilledButton.icon(
              onPressed: () => Navigator.pop(ctx, 'email'),
              icon: const Icon(Icons.email_outlined, size: 18),
              label: const Text('Enviar Email'),
            ),
          ],
        ),
      );
      if (choice == null) return;
      mode = choice;
      toEmail = emailCtrl.text.trim();
    }

    final labels = {
      'todas': 'Todas',
      'pendiente': 'Pendientes',
      'proximas': 'Próximas / En Alerta (<1000 km o <=15d)',
      'finalizada': 'Finalizadas',
    };
    final vehName = _vehiculoId == 0
        ? 'Todos los vehículos'
        : widget.vehiculos.firstWhere((v) => v.id == _vehiculoId, orElse: () => widget.vehiculos.first).nombre;
    final filterLabel = labels[_estadoFilter] ?? 'Todas';
    final filename = 'Informe_Tareas_${DateTime.now().toIso8601String().substring(0, 10)}.pdf';
    final pdfBytes = await PdfReportService.generateAndShareTareasPdf(
      scopeTitle: vehName,
      filterLabel: filterLabel,
      tareas: list,
      sendOnly: mode != 'pdf',
    );
    if (mode == 'share') {
      await PdfReportService.sharePdf(bytes: pdfBytes, filename: filename);
    } else if (mode == 'email') {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Enviando informe PDF de tareas por correo...')),
        );
      }
      final res = await widget.api.post('send_report_email', {
        'title': 'Informe de Mantenimiento - $vehName',
        'summary': 'Ámbito: $vehName\nFiltro: $filterLabel\nTareas incluidas: ${list.length}',
        'filename': filename,
        'pdf_base64': base64Encode(pdfBytes),
        if (toEmail.isNotEmpty) 'to_email': toEmail,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Operación completada').toString())),
        );
      }
    }
  }

  Future<void> _sendEmailAlerts() async {
    final res = await widget.api.post('send_task_alerts_email', {'vehiculo_id': _vehiculoId});
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Operación completada').toString())),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final list = _filtered;
    return Scaffold(
      appBar: AppBar(
        backgroundColor: const Color(0xFF0F172A),
        foregroundColor: Colors.white,
        title: const Text('Mantenimiento y Tareas'),
        actions: [
          IconButton(
            tooltip: 'Maestro de Tareas (SCRUB)',
            icon: const Icon(Icons.list_alt),
            onPressed: () async {
              await Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => TareasMaestroScreen(api: widget.api, initialList: _maestro)),
              );
              _reload();
            },
          ),
          IconButton(
            tooltip: 'Talleres, Aseguradoras e ITVs',
            icon: const Icon(Icons.build_circle_outlined),
            onPressed: () async {
              await Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => ProveedoresScreen(api: widget.api, proveedores: _proveedores),
                ),
              );
              _reload();
            },
          ),
          IconButton(
            tooltip: 'Enviar Avisos por Correo',
            icon: const Icon(Icons.send),
            onPressed: _sendEmailAlerts,
          ),
          IconButton(
            tooltip: 'Enviar Informe Tareas por Email',
            icon: const Icon(Icons.mark_email_read_outlined),
            onPressed: () => _exportPdf(sendEmail: true),
          ),
          IconButton(
            tooltip: 'Informe PDF Tareas',
            icon: const Icon(Icons.picture_as_pdf),
            onPressed: () => _exportPdf(sendEmail: false),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 6),
            child: Column(
              children: [
                DropdownButtonFormField<int>(
                  value: _vehiculoId,
                  isExpanded: true,
                  decoration: const InputDecoration(
                    labelText: 'Vehículo',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                  items: [
                    const DropdownMenuItem(value: 0, child: Text('Todos los vehículos')),
                    ...widget.vehiculos.map((v) => DropdownMenuItem(value: v.id, child: Text(v.nombre))),
                  ],
                  onChanged: (v) {
                    if (v != null) {
                      setState(() => _vehiculoId = v);
                      _reload();
                    }
                  },
                ),
                const SizedBox(height: 8),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      for (final entry in const {
                        'todas': 'Todas',
                        'pendiente': 'Pendientes',
                        'proximas': 'Próximas / Alerta',
                        'finalizada': 'Finalizadas',
                      }.entries)
                        Padding(
                          padding: const EdgeInsets.only(right: 6),
                          child: ChoiceChip(
                            label: Text(entry.value, style: const TextStyle(fontSize: 12)),
                            selected: _estadoFilter == entry.key,
                            onSelected: (_) => setState(() => _estadoFilter = entry.key),
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 6),
                TextField(
                  decoration: const InputDecoration(
                    hintText: 'Buscar tarea, vehículo o nota...',
                    prefixIcon: Icon(Icons.search),
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                  onChanged: (v) => setState(() => _search = v),
                ),
              ],
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : list.isEmpty
                    ? const Center(child: Text('No hay tareas con este filtro.'))
                    : ListView.builder(
                        padding: const EdgeInsets.fromLTRB(12, 4, 12, 80),
                        itemCount: list.length,
                        itemBuilder: (_, i) {
                          final t = list[i];
                          Color badgeBg = Colors.green.shade50;
                          Color badgeFg = Colors.green.shade800;
                          String badgeText = 'En plazo';
                          if (t.estado == 'finalizada') {
                            badgeBg = Colors.grey.shade200;
                            badgeFg = Colors.grey.shade800;
                            badgeText = 'Finalizada (${t.fechaFinalizacion})';
                          } else if (t.tipoPeriodicidad == 'unica') {
                            badgeBg = const Color(0xFFE0F2FE);
                            badgeFg = const Color(0xFF0369A1);
                            badgeText = 'Reparación / Única (No periódica)';
                          } else if (t.vencida) {
                            badgeBg = Colors.red.shade50;
                            badgeFg = Colors.red.shade800;
                            badgeText = 'VENCIDA: ${t.motivoAlerta}';
                          } else if (t.enAlerta) {
                            badgeBg = Colors.amber.shade50;
                            badgeFg = Colors.amber.shade900;
                            badgeText = 'PRÓXIMA: ${t.motivoAlerta}';
                          } else {
                            final parts = <String>[];
                            if (t.diasRestantes != null) parts.add('${t.diasRestantes} d');
                            if (t.kmRestantes != null) parts.add('${t.kmRestantes!.toStringAsFixed(0)} km');
                            if (parts.isNotEmpty) badgeText = 'En plazo (${parts.join(" / ")})';
                          }
                          return Card(
                            margin: const EdgeInsets.only(bottom: 8),
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          '${t.vehiculoNombre} · ${t.descripcion}',
                                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 6),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                    decoration: BoxDecoration(color: badgeBg, borderRadius: BorderRadius.circular(6)),
                                    child: Text(badgeText, style: TextStyle(color: badgeFg, fontWeight: FontWeight.w700, fontSize: 11.5)),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    t.tipoPeriodicidad == 'unica'
                                        ? 'Fecha: ${t.ultimaFecha.isNotEmpty ? t.ultimaFecha : "-"} · Km: ${t.ultimoKm != null ? "${t.ultimoKm!.toStringAsFixed(0)} km" : "-"}'
                                        : 'Próx. Fecha: ${t.proximaFecha.isNotEmpty ? t.proximaFecha : "-"} · Próx. Km: ${t.proximoKm != null ? "${t.proximoKm!.toStringAsFixed(0)} km" : "-"}',
                                    style: const TextStyle(fontSize: 12, color: Colors.black87),
                                  ),
                                  if (t.garantiaMeses != null)
                                    Padding(
                                      padding: const EdgeInsets.only(top: 3),
                                      child: Text(
                                        'Garantía: ${t.garantiaMeses} meses${t.garantiaHasta.isNotEmpty ? " (hasta ${t.garantiaHasta})" : ""}',
                                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0369A1)),
                                      ),
                                    ),
                                  if (t.proveedorNombre.isNotEmpty || t.coste != null)
                                    Padding(
                                      padding: const EdgeInsets.only(top: 4),
                                      child: Wrap(
                                        spacing: 8,
                                        runSpacing: 4,
                                        crossAxisAlignment: WrapCrossAlignment.center,
                                        children: [
                                          if (t.proveedorNombre.isNotEmpty)
                                            Text(
                                              '${t.proveedorTipo == "aseguradora" ? "Aseguradora" : (t.proveedorTipo == "itv" ? "ITV" : "Taller")}: ${t.proveedorNombre}',
                                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0369A1)),
                                            ),
                                          if (t.coste != null)
                                            Text(
                                              'Coste: ${t.coste!.toStringAsFixed(2)} €',
                                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                                            ),
                                          if (t.diferenciaCoste != null)
                                            Text(
                                              '${t.diferenciaCoste! > 0 ? "+" : ""}${t.diferenciaCoste!.toStringAsFixed(2)} €${t.porcentajeCoste != null ? " (${t.diferenciaCoste! > 0 ? "+" : ""}${t.porcentajeCoste!.toStringAsFixed(1)}%)" : ""}',
                                              style: TextStyle(
                                                fontSize: 11.5,
                                                fontWeight: FontWeight.bold,
                                                color: t.diferenciaCoste! > 0
                                                    ? Colors.red
                                                    : (t.diferenciaCoste! < 0 ? Colors.green : Colors.grey),
                                              ),
                                            ),
                                        ],
                                      ),
                                    ),
                                  if (t.notas.isNotEmpty)
                                    Text('Notas: ${t.notas}', style: const TextStyle(fontSize: 11.5, fontStyle: FontStyle.italic)),
                                  Wrap(
                                    alignment: WrapAlignment.end,
                                    children: [
                                      if (t.relacionadasCount > 1 || t.coste != null)
                                        TextButton.icon(
                                          onPressed: () => _showComparativaDialog(t),
                                          icon: const Icon(Icons.analytics_outlined, size: 18, color: Color(0xFF0284C7)),
                                          label: const Text('Comparar', style: TextStyle(color: Color(0xFF0284C7))),
                                        ),
                                      if (t.estado == 'pendiente') ...[
                                        TextButton.icon(
                                          onPressed: () => _completeTask(t),
                                          icon: const Icon(Icons.check_circle_outline, size: 18, color: Colors.green),
                                          label: const Text('Finalizar', style: TextStyle(color: Colors.green)),
                                        ),
                                        TextButton.icon(
                                          onPressed: () async {
                                            final saved = await Navigator.of(context).push<bool>(
                                              MaterialPageRoute(
                                                builder: (_) => VehiculoTareaFormScreen(
                                                  api: widget.api,
                                                  vehiculos: widget.vehiculos,
                                                  proveedores: _proveedores,
                                                  maestro: _maestro,
                                                  ultimoKmPorVehiculo: widget.ultimoKmPorVehiculo,
                                                  editItem: t,
                                                ),
                                              ),
                                            );
                                            if (saved == true) _reload();
                                          },
                                          icon: const Icon(Icons.edit, size: 18),
                                          label: const Text('Editar'),
                                        ),
                                      ],
                                      if (t.estado == 'finalizada')
                                        TextButton.icon(
                                          onPressed: () => _reopenTask(t),
                                          icon: const Icon(Icons.undo, size: 18, color: Color(0xFFD97706)),
                                          label: const Text('Reabrir', style: TextStyle(color: Color(0xFFD97706))),
                                        ),
                                      TextButton.icon(
                                        onPressed: () async {
                                          await widget.api.post('deactivate_vehiculo_tarea', {'id': t.id});
                                          _reload();
                                        },
                                        icon: const Icon(Icons.delete_outline, size: 18, color: Colors.red),
                                        label: const Text('Baja', style: TextStyle(color: Colors.red)),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          if (widget.vehiculos.isEmpty) return;
          final saved = await Navigator.of(context).push<bool>(
            MaterialPageRoute(
              builder: (_) => VehiculoTareaFormScreen(
                api: widget.api,
                vehiculos: widget.vehiculos,
                proveedores: _proveedores,
                maestro: _maestro,
                ultimoKmPorVehiculo: widget.ultimoKmPorVehiculo,
                initialVehiculoId: _vehiculoId > 0 ? _vehiculoId : widget.vehiculos.first.id,
              ),
            ),
          );
          if (saved == true) _reload();
        },
        icon: const Icon(Icons.add),
        label: const Text('Asignar Tarea'),
      ),
    );
  }
}

class VehiculoTareaFormScreen extends StatefulWidget {
  final ApiService api;
  final List<Vehiculo> vehiculos;
  final List<Proveedor> proveedores;
  final List<TareaMaestro> maestro;
  final Map<int, double> ultimoKmPorVehiculo;
  final int? initialVehiculoId;
  final VehiculoTarea? editItem;

  const VehiculoTareaFormScreen({
    super.key,
    required this.api,
    required this.vehiculos,
    required this.proveedores,
    required this.maestro,
    required this.ultimoKmPorVehiculo,
    this.initialVehiculoId,
    this.editItem,
  });

  @override
  State<VehiculoTareaFormScreen> createState() => _VehiculoTareaFormScreenState();
}

class _VehiculoTareaFormScreenState extends State<VehiculoTareaFormScreen> {
  late int _vehiculoId;
  int _maestroId = 0;
  int _proveedorId = 0;
  late List<Proveedor> _proveedores;
  String _tipo = 'ambos';
  bool _avisarTiempoSinKm = true;
  late TextEditingController _descCtrl;
  late TextEditingController _diasCtrl;
  late TextEditingController _kmCtrl;
  late TextEditingController _ultFechaCtrl;
  late TextEditingController _ultKmCtrl;
  late TextEditingController _costeCtrl;
  late TextEditingController _garantiaCtrl;
  late TextEditingController _notasCtrl;

  @override
  void initState() {
    super.initState();
    final e = widget.editItem;
    _proveedores = List<Proveedor>.from(widget.proveedores);
    _vehiculoId = e?.vehiculoId ?? widget.initialVehiculoId ?? widget.vehiculos.first.id;
    _maestroId = e?.tareaMaestroId ?? 0;
    _proveedorId = (e?.proveedorId != null && _proveedores.any((p) => p.id == e!.proveedorId)) ? e!.proveedorId! : 0;
    _tipo = e?.tipoPeriodicidad ?? 'ambos';
    _avisarTiempoSinKm = e?.avisarPorTiempoSiNoKm ?? true;
    _descCtrl = TextEditingController(text: e?.descripcion ?? '');
    _diasCtrl = TextEditingController(text: e?.periodoDias?.toString() ?? '365');
    _kmCtrl = TextEditingController(text: e?.periodoKm?.toStringAsFixed(0) ?? '15000');
    _ultFechaCtrl = TextEditingController(text: e?.ultimaFecha.isNotEmpty == true ? e!.ultimaFecha : DateTime.now().toIso8601String().substring(0, 10));
    final defKm = e?.ultimoKm ?? widget.ultimoKmPorVehiculo[_vehiculoId];
    _ultKmCtrl = TextEditingController(text: defKm != null ? defKm.toStringAsFixed(0) : '');
    _costeCtrl = TextEditingController(text: e?.coste != null ? e!.coste!.toStringAsFixed(2) : '');
    _garantiaCtrl = TextEditingController(text: e?.garantiaMeses != null ? e!.garantiaMeses.toString() : '');
    _notasCtrl = TextEditingController(text: e?.notas ?? '');
  }

  Future<void> _save() async {
    final res = await widget.api.post('save_vehiculo_tarea', {
      'id': widget.editItem?.id ?? 0,
      'vehiculo_id': _vehiculoId,
      'tarea_maestro_id': _maestroId,
      'proveedor_id': _proveedorId,
      'descripcion': _descCtrl.text.trim(),
      'tipo_periodicidad': _tipo,
      'periodo_dias': _tipo == 'unica' ? '' : _diasCtrl.text.trim(),
      'periodo_km': _tipo == 'unica' ? '' : _kmCtrl.text.trim(),
      'ultima_fecha': _ultFechaCtrl.text.trim(),
      'ultimo_km': _ultKmCtrl.text.trim(),
      'coste': _costeCtrl.text.trim(),
      'garantia_meses': _garantiaCtrl.text.trim(),
      'avisar_por_tiempo_si_no_km': (_tipo != 'unica' && _avisarTiempoSinKm) ? 1 : 0,
      'notas': _notasCtrl.text.trim(),
    });
    if (res['success'] == true && mounted) {
      Navigator.of(context).pop(true);
    } else if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['error'] ?? 'Error al guardar').toString())),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.editItem == null ? 'Asignar Tarea' : 'Editar Tarea')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          DropdownButtonFormField<int>(
            value: _vehiculoId,
            decoration: const InputDecoration(labelText: 'Vehículo *', border: OutlineInputBorder()),
            items: widget.vehiculos.map((v) => DropdownMenuItem(value: v.id, child: Text(v.nombre))).toList(),
            onChanged: (v) {
              if (v != null) setState(() => _vehiculoId = v);
            },
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<int>(
            value: _maestroId,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Plantilla del Maestro (Opcional)', border: OutlineInputBorder()),
            items: [
              const DropdownMenuItem(value: 0, child: Text('-- Personalizada --')),
              ...widget.maestro.map((m) => DropdownMenuItem(value: m.id, child: Text(m.nombre))),
            ],
            onChanged: (v) {
              if (v == null) return;
              setState(() {
                _maestroId = v;
                if (v > 0) {
                  final tm = widget.maestro.firstWhere((x) => x.id == v);
                  _descCtrl.text = tm.descripcion.isNotEmpty ? '${tm.nombre} - ${tm.descripcion}' : tm.nombre;
                  _tipo = tm.tipoPeriodicidad;
                  _diasCtrl.text = tm.periodoDias?.toString() ?? '';
                  _kmCtrl.text = tm.periodoKm?.toStringAsFixed(0) ?? '';
                  _avisarTiempoSinKm = tm.avisarPorTiempoSiNoKm;
                }
              });
            },
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _descCtrl,
            decoration: const InputDecoration(labelText: 'Descripción de la tarea *', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _tipo,
            decoration: const InputDecoration(labelText: 'Periodicidad', border: OutlineInputBorder()),
            items: const [
              DropdownMenuItem(value: 'ambos', child: Text('Tiempo y Kilómetros (Ambos)')),
              DropdownMenuItem(value: 'kilometros', child: Text('Solo Kilómetros')),
              DropdownMenuItem(value: 'tiempo', child: Text('Solo Tiempo (Días)')),
              DropdownMenuItem(value: 'unica', child: Text('Única / Reparación (No periódica)')),
            ],
            onChanged: (v) {
              if (v != null) {
                setState(() {
                  _tipo = v;
                  if (v == 'unica') {
                    _diasCtrl.clear();
                    _kmCtrl.clear();
                  }
                });
              }
            },
          ),
          const SizedBox(height: 12),
          if (_tipo != 'kilometros' && _tipo != 'unica') ...[
            TextField(
              controller: _diasCtrl,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Periodo en días (ej: 365)', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 12),
          ],
          if (_tipo != 'tiempo' && _tipo != 'unica') ...[
            TextField(
              controller: _kmCtrl,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Periodo en kilómetros (ej: 15000)', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 12),
          ],
          if (_tipo != 'unica')
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Avisar por tiempo aunque no llegue al kilometraje'),
              value: _avisarTiempoSinKm,
              onChanged: (v) => setState(() => _avisarTiempoSinKm = v),
            ),
          const SizedBox(height: 8),
          TextField(
            controller: _ultFechaCtrl,
            decoration: const InputDecoration(labelText: 'Última realización / inicio (YYYY-MM-DD)', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _ultKmCtrl,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Km última realización / inicio', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: DropdownButtonFormField<int>(
                  value: _proveedorId,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Taller / Aseguradora / ITV', border: OutlineInputBorder()),
                  items: [
                    const DropdownMenuItem(value: 0, child: Text('-- Sin especificar --')),
                    ..._proveedores.map(
                      (p) => DropdownMenuItem(
                        value: p.id,
                        child: Text('[${p.tipoLabel}] ${p.nombre}', overflow: TextOverflow.ellipsis),
                      ),
                    ),
                  ],
                  onChanged: (v) => setState(() => _proveedorId = v ?? 0),
                ),
              ),
              const SizedBox(width: 8),
              IconButton.outlined(
                tooltip: 'Crear Taller / Aseguradora / ITV',
                icon: const Icon(Icons.add),
                onPressed: () async {
                  final created = await showQuickCreateProveedorDialog(context, widget.api);
                  if (created != null && mounted) {
                    setState(() {
                      _proveedores.add(created);
                      _proveedorId = created.id;
                    });
                  }
                },
              ),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _costeCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Coste / Precio (€) (Opcional)', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _garantiaCtrl,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Garantía (meses, opcional)', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _notasCtrl,
            decoration: const InputDecoration(labelText: 'Notas', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 20),
          SizedBox(
            height: 48,
            child: FilledButton.icon(onPressed: _save, icon: const Icon(Icons.save), label: const Text('Guardar Tarea')),
          ),
        ],
      ),
    );
  }
}

class TareasMaestroScreen extends StatefulWidget {
  final ApiService api;
  final List<TareaMaestro> initialList;

  const TareasMaestroScreen({super.key, required this.api, required this.initialList});

  @override
  State<TareasMaestroScreen> createState() => _TareasMaestroScreenState();
}

class _TareasMaestroScreenState extends State<TareasMaestroScreen> {
  late List<TareaMaestro> _list;
  int _editId = 0;
  String _tipo = 'ambos';
  bool _avisarTiempo = true;
  final _nombreCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  final _diasCtrl = TextEditingController();
  final _kmCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _list = List<TareaMaestro>.from(widget.initialList);
  }

  Future<void> _refresh() async {
    final res = await widget.api.post('list_tareas_maestro', {});
    if (res['success'] == true && mounted) {
      setState(() {
        _list = (res['tareas_maestro'] as List? ?? [])
            .map((e) => TareaMaestro.fromJson(e as Map<String, dynamic>))
            .toList();
      });
    }
  }

  Future<void> _save() async {
    if (_nombreCtrl.text.trim().isEmpty) return;
    final res = await widget.api.post('save_tarea_maestro', {
      'id': _editId,
      'nombre': _nombreCtrl.text.trim(),
      'descripcion': _descCtrl.text.trim(),
      'tipo_periodicidad': _tipo,
      'periodo_dias': _diasCtrl.text.trim(),
      'periodo_km': _kmCtrl.text.trim(),
      'avisar_por_tiempo_si_no_km': _avisarTiempo ? 1 : 0,
    });
    if (res['success'] == true && mounted) {
      setState(() {
        _editId = 0;
        _nombreCtrl.clear();
        _descCtrl.clear();
        _diasCtrl.clear();
        _kmCtrl.clear();
      });
      await _refresh();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Maestro de Tareas Periódicas')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ..._list.map((t) => Card(
                child: ListTile(
                  title: Text(t.nombre, style: const TextStyle(fontWeight: FontWeight.bold)),
                  subtitle: Text('${t.tipoPeriodicidad}${t.periodoDias != null ? " · ${t.periodoDias} días" : ""}${t.periodoKm != null ? " · ${t.periodoKm!.toStringAsFixed(0)} km" : ""}'),
                  trailing: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      IconButton(
                        icon: const Icon(Icons.edit),
                        onPressed: () => setState(() {
                          _editId = t.id;
                          _nombreCtrl.text = t.nombre;
                          _descCtrl.text = t.descripcion;
                          _tipo = t.tipoPeriodicidad;
                          _diasCtrl.text = t.periodoDias?.toString() ?? '';
                          _kmCtrl.text = t.periodoKm?.toStringAsFixed(0) ?? '';
                          _avisarTiempo = t.avisarPorTiempoSiNoKm;
                        }),
                      ),
                      IconButton(
                        icon: const Icon(Icons.delete_outline, color: Colors.red),
                        onPressed: () async {
                          await widget.api.post('deactivate_tarea_maestro', {'id': t.id});
                          _refresh();
                        },
                      ),
                    ],
                  ),
                ),
              )),
          const Divider(height: 28),
          Text(_editId == 0 ? 'Nueva Tarea Maestra' : 'Editar Tarea Maestra', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
          const SizedBox(height: 10),
          TextField(controller: _nombreCtrl, decoration: const InputDecoration(labelText: 'Nombre *', border: OutlineInputBorder())),
          const SizedBox(height: 10),
          TextField(controller: _descCtrl, decoration: const InputDecoration(labelText: 'Descripción', border: OutlineInputBorder())),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            value: _tipo,
            decoration: const InputDecoration(labelText: 'Periodicidad', border: OutlineInputBorder()),
            items: const [
              DropdownMenuItem(value: 'ambos', child: Text('Tiempo y Kilómetros')),
              DropdownMenuItem(value: 'kilometros', child: Text('Solo Kilómetros')),
              DropdownMenuItem(value: 'tiempo', child: Text('Solo Tiempo (Días)')),
            ],
            onChanged: (v) {
              if (v != null) setState(() => _tipo = v);
            },
          ),
          const SizedBox(height: 10),
          if (_tipo != 'kilometros') ...[
            TextField(controller: _diasCtrl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Periodo días', border: OutlineInputBorder())),
            const SizedBox(height: 10),
          ],
          if (_tipo != 'tiempo') ...[
            TextField(controller: _kmCtrl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Periodo km', border: OutlineInputBorder())),
            const SizedBox(height: 10),
          ],
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Avisar por tiempo si no llega al km'),
            value: _avisarTiempo,
            onChanged: (v) => setState(() => _avisarTiempo = v),
          ),
          const SizedBox(height: 12),
          FilledButton.icon(onPressed: _save, icon: const Icon(Icons.save), label: const Text('Guardar en Maestro')),
        ],
      ),
    );
  }
}

class ParquesMovilesScreen extends StatefulWidget {
  final ApiService api;
  final int currentUserId;

  const ParquesMovilesScreen({super.key, required this.api, this.currentUserId = 0});

  @override
  State<ParquesMovilesScreen> createState() => _ParquesMovilesScreenState();
}

class _ParquesMovilesScreenState extends State<ParquesMovilesScreen> {
  final _nombreCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  int _editingId = 0;
  bool _loading = true;
  List<ParqueMovil> _parques = [];
  List<Map<String, dynamic>> _users = [];
  List<Map<String, dynamic>> _allVehiculos = [];
  final Map<int, int?> _selectedUserByParque = {};
  final Map<int, int?> _selectedVehByParque = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final res = await widget.api.post('list_parques', {});
    if (mounted) {
      setState(() {
        _loading = false;
        if (res['success'] == true) {
          _parques = (res['parques'] as List? ?? [])
              .map((e) => ParqueMovil.fromJson(e as Map<String, dynamic>))
              .toList();
          _users = (res['users'] as List? ?? [])
              .map((e) => Map<String, dynamic>.from(e as Map))
              .toList();
          _allVehiculos = (res['all_vehiculos'] as List? ?? [])
              .map((e) => Map<String, dynamic>.from(e as Map))
              .toList();
        }
      });
    }
  }

  void _clearForm() {
    setState(() {
      _editingId = 0;
      _nombreCtrl.clear();
      _descCtrl.clear();
    });
  }

  Future<void> _saveParque() async {
    if (_nombreCtrl.text.trim().isEmpty) return;
    final res = await widget.api.post('save_parque', {
      'id': _editingId,
      'nombre': _nombreCtrl.text.trim(),
      'descripcion': _descCtrl.text.trim(),
    });
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Guardado').toString())),
      );
      if (res['success'] == true) {
        _clearForm();
        await _load();
      }
    }
  }

  Future<void> _assignUser(int parqueId) async {
    final uid = _selectedUserByParque[parqueId];
    if (uid == null || uid <= 0) return;
    final res = await widget.api.post('assign_parque_user', {
      'parque_id': parqueId,
      'usuario_id': uid,
    });
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Usuario añadido').toString())),
      );
      if (res['success'] == true) {
        _selectedUserByParque[parqueId] = null;
        await _load();
      }
    }
  }

  Future<void> _removeUser(int parqueId, int usuarioId) async {
    final res = await widget.api.post('remove_parque_user', {
      'parque_id': parqueId,
      'usuario_id': usuarioId,
    });
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Usuario retirado').toString())),
      );
      if (res['success'] == true) await _load();
    }
  }

  Future<void> _assignVehiculo(int parqueId) async {
    final vid = _selectedVehByParque[parqueId];
    if (vid == null || vid <= 0) return;
    final res = await widget.api.post('assign_parque_vehiculo', {
      'parque_id': parqueId,
      'vehiculo_id': vid,
    });
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Vehículo añadido').toString())),
      );
      if (res['success'] == true) {
        _selectedVehByParque[parqueId] = null;
        await _load();
      }
    }
  }

  Future<void> _removeVehiculo(int parqueId, int vehiculoId) async {
    final res = await widget.api.post('remove_parque_vehiculo', {
      'parque_id': parqueId,
      'vehiculo_id': vehiculoId,
    });
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Vehículo retirado').toString())),
      );
      if (res['success'] == true) await _load();
    }
  }

  Future<void> _deactivateParque(ParqueMovil pq) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Baja lógica de Parque Móvil'),
        content: Text('¿Dar de baja el parque móvil "${pq.nombre}" (activo = 0)?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Dar de baja'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    final res = await widget.api.post('deactivate_parque', {'id': pq.id});
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Operación completada').toString())),
      );
      if (res['success'] == true) await _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final isAdmin = widget.api.isAdmin;
    return Scaffold(
      appBar: AppBar(title: const Text('Parques Móviles')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (_parques.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 20),
                    child: Text(
                      'No hay parques móviles creados o asignados todavía.',
                      style: TextStyle(color: Colors.black54),
                    ),
                  ),
                ..._parques.map((pq) {
                  final availableUsers = _users
                      .where((u) => !pq.miembros.any((m) => m.usuarioId == int.tryParse(u['id'].toString())))
                      .toList();
                  final availableVehs = _allVehiculos.where((v) {
                    final vParque = int.tryParse((v['parque_id'] ?? '').toString()) ?? 0;
                    final vOwner = int.tryParse((v['usuario_id'] ?? 0).toString()) ?? 0;
                    return vParque != pq.id && (isAdmin || vOwner == widget.currentUserId);
                  }).toList();
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              const Icon(Icons.groups, color: Color(0xFF0F766E)),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  pq.nombre,
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                                ),
                              ),
                              Chip(
                                label: Text('${pq.numVehiculos} veh.'),
                                visualDensity: VisualDensity.compact,
                              ),
                              if (isAdmin) ...[
                                IconButton(
                                  icon: const Icon(Icons.edit, size: 20),
                                  onPressed: () {
                                    setState(() {
                                      _editingId = pq.id;
                                      _nombreCtrl.text = pq.nombre;
                                      _descCtrl.text = pq.descripcion;
                                    });
                                  },
                                ),
                                IconButton(
                                  icon: const Icon(Icons.delete_outline, color: Colors.red, size: 20),
                                  onPressed: () => _deactivateParque(pq),
                                ),
                              ],
                            ],
                          ),
                          if (pq.descripcion.isNotEmpty)
                            Padding(
                              padding: const EdgeInsets.only(top: 4, bottom: 6),
                              child: Text(pq.descripcion, style: const TextStyle(color: Colors.black54, fontSize: 13)),
                            ),
                          const SizedBox(height: 6),
                          Wrap(
                            spacing: 6,
                            runSpacing: 4,
                            children: pq.miembros.isEmpty
                                ? [const Text('Sin usuarios asignados', style: TextStyle(color: Colors.black45, fontSize: 12))]
                                : pq.miembros
                                    .map(
                                      (m) => InputChip(
                                        avatar: const Icon(Icons.person, size: 16),
                                        label: Text(m.username),
                                        onDeleted: isAdmin ? () => _removeUser(pq.id, m.usuarioId) : null,
                                      ),
                                    )
                                    .toList(),
                          ),
                          const SizedBox(height: 8),
                          const Text('Vehículos del parque:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Colors.black54)),
                          const SizedBox(height: 4),
                          Wrap(
                            spacing: 6,
                            runSpacing: 4,
                            children: pq.vehiculos.isEmpty
                                ? [const Text('Sin vehículos asignados', style: TextStyle(color: Colors.black45, fontSize: 12))]
                                : pq.vehiculos.map((v) {
                                    final vid = int.tryParse((v['id'] ?? 0).toString()) ?? 0;
                                    final vOwner = int.tryParse((v['usuario_id'] ?? 0).toString()) ?? 0;
                                    final canRem = isAdmin || vOwner == widget.currentUserId;
                                    final mat = (v['matricula'] ?? '').toString();
                                    return InputChip(
                                      avatar: const Icon(Icons.directions_car, size: 16),
                                      label: Text('${v['nombre']}${mat.isNotEmpty ? " ($mat)" : ""}'),
                                      onDeleted: canRem ? () => _removeVehiculo(pq.id, vid) : null,
                                    );
                                  }).toList(),
                          ),
                          if (availableVehs.isNotEmpty) ...[
                            const SizedBox(height: 10),
                            Row(
                              children: [
                                Expanded(
                                  child: DropdownButtonFormField<int>(
                                    value: availableVehs.any((v) => int.tryParse(v['id'].toString()) == _selectedVehByParque[pq.id])
                                        ? _selectedVehByParque[pq.id]
                                        : null,
                                    decoration: const InputDecoration(
                                      labelText: 'Añadir vehículo al parque',
                                      border: OutlineInputBorder(),
                                      isDense: true,
                                    ),
                                    items: availableVehs.map((v) {
                                      final mat = (v['matricula'] ?? '').toString();
                                      final prop = (v['propietario'] ?? '').toString();
                                      return DropdownMenuItem<int>(
                                        value: int.tryParse(v['id'].toString()) ?? 0,
                                        child: Text(
                                          '${v['nombre']}${mat.isNotEmpty ? " ($mat)" : ""}${isAdmin && prop.isNotEmpty ? " · $prop" : ""}',
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      );
                                    }).toList(),
                                    onChanged: (v) => setState(() => _selectedVehByParque[pq.id] = v),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                FilledButton.icon(
                                  onPressed: () => _assignVehiculo(pq.id),
                                  icon: const Icon(Icons.add, size: 18),
                                  label: const Text('Añadir'),
                                ),
                              ],
                            ),
                          ],
                          if (isAdmin && availableUsers.isNotEmpty) ...[
                            const SizedBox(height: 10),
                            Row(
                              children: [
                                Expanded(
                                  child: DropdownButtonFormField<int>(
                                    value: availableUsers.any((u) => int.tryParse(u['id'].toString()) == _selectedUserByParque[pq.id])
                                        ? _selectedUserByParque[pq.id]
                                        : null,
                                    decoration: const InputDecoration(
                                      labelText: 'Añadir usuario al parque',
                                      border: OutlineInputBorder(),
                                      isDense: true,
                                    ),
                                    items: availableUsers
                                        .map((u) => DropdownMenuItem<int>(
                                              value: int.tryParse(u['id'].toString()) ?? 0,
                                              child: Text('${u['username']} (${u['role']})'),
                                            ))
                                        .toList(),
                                    onChanged: (v) => setState(() => _selectedUserByParque[pq.id] = v),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                FilledButton.icon(
                                  onPressed: () => _assignUser(pq.id),
                                  icon: const Icon(Icons.person_add, size: 18),
                                  label: const Text('Añadir'),
                                ),
                              ],
                            ),
                          ],
                        ],
                      ),
                    ),
                  );
                }),
                if (isAdmin) ...[
                  const Divider(height: 32),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        _editingId == 0 ? 'Crear Nuevo Parque Móvil' : 'Editar Parque Móvil',
                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                      if (_editingId != 0)
                        TextButton.icon(
                          onPressed: _clearForm,
                          icon: const Icon(Icons.close, size: 18),
                          label: const Text('Cancelar'),
                        ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _nombreCtrl,
                    decoration: const InputDecoration(labelText: 'Nombre del Parque Móvil *', border: OutlineInputBorder()),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _descCtrl,
                    decoration: const InputDecoration(labelText: 'Descripción (opcional)', border: OutlineInputBorder()),
                  ),
                  const SizedBox(height: 14),
                  FilledButton.icon(
                    onPressed: _saveParque,
                    icon: const Icon(Icons.save),
                    label: Text(_editingId == 0 ? 'Crear Parque Móvil' : 'Actualizar Parque Móvil'),
                  ),
                ],
              ],
            ),
    );
  }
}

class PapeleraScreen extends StatefulWidget {
  final ApiService api;

  const PapeleraScreen({super.key, required this.api});

  @override
  State<PapeleraScreen> createState() => _PapeleraScreenState();
}

class _PapeleraScreenState extends State<PapeleraScreen> {
  bool _loading = true;
  List<Map<String, dynamic>> _vehiculos = [];
  List<Map<String, dynamic>> _repostajes = [];
  final Set<String> _selectedKeys = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final res = await widget.api.post('list_inactive_records', {});
    if (mounted) {
      setState(() {
        _loading = false;
        _selectedKeys.clear();
        if (res['success'] == true) {
          _vehiculos = (res['vehiculos'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
          _repostajes = (res['repostajes'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
        }
      });
    }
  }

  String _fmtDateEs(String val) {
    final m = RegExp(r'^(\d{4})-(\d{2})-(\d{2})').firstMatch(val.trim());
    return m != null ? '${m.group(3)}-${m.group(2)}-${m.group(1)}' : val;
  }

  List<int> _selectedIdsFor(String prefix) {
    return _selectedKeys
        .where((k) => k.startsWith('$prefix:'))
        .map((k) => int.tryParse(k.split(':')[1]) ?? 0)
        .where((id) => id > 0)
        .toList();
  }

  void _toggleSelectAll(bool selectAll) {
    setState(() {
      _selectedKeys.clear();
      if (selectAll) {
        for (final v in _vehiculos) {
          _selectedKeys.add('vehiculo:${v['id']}');
        }
        for (final r in _repostajes) {
          _selectedKeys.add('repostaje:${r['id']}');
        }
      }
    });
  }

  Future<void> _reactivate(String tipo, int id) async {
    final res = await widget.api.post('reactivate_records', {'tipo': tipo, 'id': id});
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Reactivado').toString())),
      );
      if (res['success'] == true) await _load();
    }
  }

  Future<void> _reactivateSelected() async {
    final vehIds = _selectedIdsFor('vehiculo');
    final repIds = _selectedIdsFor('repostaje');
    if (vehIds.isEmpty && repIds.isEmpty) return;
    final res = await widget.api.post('reactivate_records', {
      'tipo': 'batch',
      'vehiculo_ids': vehIds,
      'repostaje_ids': repIds,
    });
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Reactivados').toString())),
      );
      if (res['success'] == true) await _load();
    }
  }

  Future<void> _purgeSingle(String tipo, int id) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Borrado definitivo'),
        content: const Text('¿Borrar DEFINITIVAMENTE este registro de la base de datos? Esta acción no se puede deshacer.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Borrar'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    final res = await widget.api.post('purge_inactive_records', {'tipo': tipo, 'id': id});
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Eliminado').toString())),
      );
      if (res['success'] == true) await _load();
    }
  }

  Future<void> _purgeSelected() async {
    final vehIds = _selectedIdsFor('vehiculo');
    final repIds = _selectedIdsFor('repostaje');
    final total = vehIds.length + repIds.length;
    if (total == 0) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Borrado definitivo'),
        content: Text('¿Borrar DEFINITIVAMENTE los $total registros seleccionados de la base de datos? Esta acción no se puede deshacer.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Borrar definitivamente'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    final res = await widget.api.post('purge_inactive_records', {
      'tipo': 'batch',
      'vehiculo_ids': vehIds,
      'repostaje_ids': repIds,
    });
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((res['message'] ?? res['error'] ?? 'Eliminados').toString())),
      );
      if (res['success'] == true) await _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final totalItems = _vehiculos.length + _repostajes.length;
    final allSelected = totalItems > 0 && _selectedKeys.length == totalItems;

    return Scaffold(
      appBar: AppBar(title: const Text('Papelera')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (totalItems > 0) ...[
                  Card(
                    color: Colors.grey.shade50,
                    child: Padding(
                      padding: const EdgeInsets.all(10),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          CheckboxListTile(
                            dense: true,
                            contentPadding: EdgeInsets.zero,
                            value: allSelected,
                            title: Text('Seleccionar todo (${_selectedKeys.length}/$totalItems)'),
                            onChanged: (v) => _toggleSelectAll(v == true),
                          ),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              FilledButton.icon(
                                onPressed: _selectedKeys.isEmpty ? null : _reactivateSelected,
                                icon: const Icon(Icons.restore, size: 18),
                                label: const Text('Recuperar sel.'),
                              ),
                              FilledButton.icon(
                                style: FilledButton.styleFrom(backgroundColor: Colors.red),
                                onPressed: _selectedKeys.isEmpty ? null : _purgeSelected,
                                icon: const Icon(Icons.delete_forever, size: 18),
                                label: const Text('Borrar def.'),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                ],
                if (_vehiculos.isEmpty && _repostajes.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 24),
                    child: Center(child: Text('No hay vehículos ni repostajes inactivos.', style: TextStyle(color: Colors.black54))),
                  ),
                if (_vehiculos.isNotEmpty) ...[
                  const Text('Vehículos dados de baja', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                  const SizedBox(height: 8),
                  ..._vehiculos.map((v) {
                    final vid = int.tryParse(v['id'].toString()) ?? 0;
                    final key = 'vehiculo:$vid';
                    return Card(
                      child: ListTile(
                        leading: Checkbox(
                          value: _selectedKeys.contains(key),
                          onChanged: (chk) => setState(() {
                            if (chk == true) _selectedKeys.add(key); else _selectedKeys.remove(key);
                          }),
                        ),
                        title: Text('#$vid ${v['nombre']}'),
                        subtitle: Text('${v['matricula'] ?? 'Sin matrícula'} · ${v['propietario'] ?? v['usuario_id']}'),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              tooltip: 'Recuperar',
                              icon: const Icon(Icons.restore, color: Color(0xFF0F766E)),
                              onPressed: () => _reactivate('vehiculo', vid),
                            ),
                            IconButton(
                              tooltip: 'Borrar definitivamente',
                              icon: const Icon(Icons.delete_forever, color: Colors.red),
                              onPressed: () => _purgeSingle('vehiculo', vid),
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
                  const SizedBox(height: 16),
                ],
                if (_repostajes.isNotEmpty) ...[
                  Text('Repostajes dados de baja (${_repostajes.length})', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                  const SizedBox(height: 8),
                  ..._repostajes.map((r) {
                    final rid = int.tryParse(r['id'].toString()) ?? 0;
                    final key = 'repostaje:$rid';
                    return Card(
                      child: ListTile(
                        leading: Checkbox(
                          value: _selectedKeys.contains(key),
                          onChanged: (chk) => setState(() {
                            if (chk == true) _selectedKeys.add(key); else _selectedKeys.remove(key);
                          }),
                        ),
                        title: Text('#$rid · ${_fmtDateEs((r['fecha'] ?? '').toString())} · ${r['vehiculo_nombre'] ?? ''}'),
                        subtitle: Text('${r['litros']} L · ${r['importe_total']} € · ${r['km_odometro']} km'),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              tooltip: 'Recuperar',
                              icon: const Icon(Icons.restore, color: Color(0xFF0F766E)),
                              onPressed: () => _reactivate('repostaje', rid),
                            ),
                            IconButton(
                              tooltip: 'Borrar definitivamente',
                              icon: const Icon(Icons.delete_forever, color: Colors.red),
                              onPressed: () => _purgeSingle('repostaje', rid),
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
                ],
              ],
            ),
    );
  }
}
