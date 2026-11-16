import 'dart:math';
import 'package:flutter/material.dart';
import 'models/models.dart';
import 'services/api_service.dart';
import 'services/ocr_service.dart';
import 'services/pdf_service.dart';

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

class _RootScreenState extends State<RootScreen> {
  final ApiService _api = ApiService();
  bool _checkingSession = true;
  bool _loggedIn = false;

  @override
  void initState() {
    super.initState();
    _initSession();
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
    return DashboardScreen(
      api: _api,
      onLogout: () async {
        await _api.logout();
        setState(() => _loggedIn = false);
      },
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

  @override
  void initState() {
    super.initState();
    _generateCaptcha();
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
        setState(() => _error = 'Usuario o contraseña de moratalla-murcia.com incorrectos.');
        _generateCaptcha();
      }
    } catch (e) {
      setState(() => _error = 'Error de conexión: $e');
      _generateCaptcha();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
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
  int _selectedVehiculoId = 0; // 0 = Todos (Resultado conjunto)
  List<Vehiculo> _vehiculos = [];
  List<EstacionServicio> _estaciones = [];
  Map<int, double> _ultimoKmPorVehiculo = {};
  List<Repostaje> _repostajes = [];
  ResumenEstadisticas? _resumen;
  bool _loading = true;
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _loading = true);
    try {
      final res = await widget.api.post('dashboard', {
        'vehiculo_id': _selectedVehiculoId,
      });
      if (res['success'] == true) {
        final vList = (res['vehiculos'] as List? ?? [])
            .map((e) => Vehiculo.fromJson(e as Map<String, dynamic>))
            .toList();
        final estList = (res['estaciones'] as List? ?? [])
            .map((e) => EstacionServicio.fromJson(e as Map<String, dynamic>))
            .toList();
        final rList = (res['repostajes'] as List? ?? [])
            .map((e) => Repostaje.fromJson(e as Map<String, dynamic>))
            .toList();
        final rawLastKm = res['ultimo_km_por_vehiculo'] as Map<String, dynamic>? ?? {};
        final parsedLastKm = <int, double>{};
        rawLastKm.forEach((k, v) {
          final id = int.tryParse(k);
          final km = double.tryParse(v.toString());
          if (id != null && km != null) parsedLastKm[id] = km;
        });
        final sumMap = res['resumen'] as Map<String, dynamic>? ?? {};

        if (mounted) {
          setState(() {
            _vehiculos = vList;
            _estaciones = estList;
            _ultimoKmPorVehiculo = parsedLastKm;
            _repostajes = rList;
            _resumen = ResumenEstadisticas.fromJson(sumMap);
          });
        }
      }
    } finally {
      if (mounted) setState(() => _loading = false);
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
        title: Row(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: Image.asset('assets/logo_v2.png', width: 32, height: 32),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                widget.api.isAdmin ? 'Control de Combustible.' : 'Control de Combustible',
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Estaciones de Servicio',
            icon: const Icon(Icons.storefront),
            onPressed: () async {
              await Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => EstacionesScreen(api: widget.api, estaciones: _estaciones),
                ),
              );
              _loadData();
            },
          ),
          IconButton(
            tooltip: 'Gestionar Vehículos',
            icon: const Icon(Icons.directions_car),
            onPressed: () async {
              await Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => VehiculosScreen(api: widget.api, vehiculos: _vehiculos),
                ),
              );
              _loadData();
            },
          ),
          IconButton(
            tooltip: 'Generar Informe PDF',
            icon: const Icon(Icons.picture_as_pdf),
            onPressed: _resumen == null
                ? null
                : () => PdfReportService.generateAndSharePdf(
                      scopeTitle: _getScopeTitle(),
                      resumen: _resumen!,
                      repostajes: filtered,
                    ),
          ),
          IconButton(
            tooltip: 'Cerrar sesión',
            icon: const Icon(Icons.logout),
            onPressed: widget.onLogout,
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
                  const SizedBox(height: 8),
                  if (_resumen != null) _buildKpiGrid(_resumen!),
                  const SizedBox(height: 10),
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
        _kpiTile('Total Acumulado', '${s.totalEuros.toStringAsFixed(2)} €', '${s.totalLitros.toStringAsFixed(1)} L · ${s.totalKmRecorridos.toStringAsFixed(0)} km'),
      ],
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

  Widget _buildRepostajeCard(Repostaje r) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ExpansionTile(
        title: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(r.fecha, style: const TextStyle(fontWeight: FontWeight.bold)),
            Text('${r.importeTotal.toStringAsFixed(2)} €', style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF0F766E))),
          ],
        ),
        subtitle: Text(
          '${r.kmOdometro.toStringAsFixed(0)} km (${r.difKm > 0 ? "+${r.difKm.toStringAsFixed(0)} km" : "Inicio"}) · ${r.litros.toStringAsFixed(2)} L a ${r.precioLitro.toStringAsFixed(3)} €/L'
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
                    Text('KM.x.LTS: ${r.kmPorLitro?.toStringAsFixed(2) ?? "-"}'),
                    Text('LTS.x.100: ${r.litrosPor100?.toStringAsFixed(2) ?? "-"}'),
                    Text('KM.x.EUR: ${r.kmPorEuro?.toStringAsFixed(2) ?? "-"}'),
                    Text('€ x 1KM.: ${r.eurosPorKm?.toStringAsFixed(4) ?? "-"} €'),
                    Text('T.Litros: ${r.totalLitrosAcum.toStringAsFixed(2)} L'),
                    Text('Total €: ${r.totalEurosAcum.toStringAsFixed(2)} €'),
                  ],
                ),
                if (r.notas.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text('Nota: ${r.notas}', style: const TextStyle(fontStyle: FontStyle.italic)),
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
  late TextEditingController _estacionCtrl;
  late TextEditingController _notasCtrl;
  bool _saving = false;
  String? _ocrMessage;

  @override
  void initState() {
    super.initState();
    final e = widget.editItem;
    _vehiculoId = e?.vehiculoId ?? widget.initialVehiculoId;
    _fechaCtrl = TextEditingController(text: e?.fecha ?? DateTime.now().toIso8601String().substring(0, 10));
    _kmCtrl = TextEditingController(text: e != null ? e.kmOdometro.toStringAsFixed(0) : '');
    _litrosCtrl = TextEditingController(text: e != null ? e.litros.toStringAsFixed(2) : '');
    _precioCtrl = TextEditingController(text: e != null ? e.precioLitro.toStringAsFixed(3) : '');
    _importeCtrl = TextEditingController(text: e != null ? e.importeTotal.toStringAsFixed(2) : '');
    _estacionCtrl = TextEditingController(text: e?.estacionServicio ?? '');
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

  Future<void> _scanTicket() async {
    setState(() => _ocrMessage = 'Escaneando ticket de combustible...');
    final res = await _ocr.scanFuelTicket();
    if (res == null) {
      setState(() => _ocrMessage = null);
      return;
    }
    setState(() {
      if (res.fecha != null) _fechaCtrl.text = res.fecha!;
      if (res.litros != null) _litrosCtrl.text = res.litros!.toStringAsFixed(2);
      if (res.precioLitro != null) _precioCtrl.text = res.precioLitro!.toStringAsFixed(3);
      if (res.importeTotal != null) _importeCtrl.text = res.importeTotal!.toStringAsFixed(2);
      _ocrMessage = 'Datos extraídos del ticket correctamente. Revisa y confirma.';
    });
  }

  Future<void> _scanOdometer() async {
    setState(() => _ocrMessage = 'Capturando lectura de cuentakilómetros...');
    final km = await _ocr.scanOdometer();
    if (km == null) {
      setState(() => _ocrMessage = 'No se detectaron dígitos claros en el cuentakilómetros.');
      return;
    }
    setState(() {
      _kmCtrl.text = km.toStringAsFixed(0);
      _ocrMessage = 'Kilometraje capturado: ${km.toStringAsFixed(0)} km';
    });
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
        'fecha': _fechaCtrl.text.trim(),
        'km_odometro': _kmCtrl.text.trim(),
        'litros': _litrosCtrl.text.trim(),
        'precio_litro': _precioCtrl.text.trim(),
        'importe_total': _importeCtrl.text.trim(),
        'estacion_servicio': _estacionCtrl.text.trim(),
        'notas': _notasCtrl.text.trim(),
      });
      if (res['success'] == true && mounted) {
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
                  onPressed: _scanTicket,
                  icon: const Icon(Icons.receipt_long),
                  label: const Text('Escanear Ticket'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _scanOdometer,
                  icon: const Icon(Icons.speed),
                  label: const Text('Foto Odómetro'),
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
              child: Text(_ocrMessage!, style: const TextStyle(fontSize: 13)),
            ),
          ],
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
              labelText: widget.api.isAdmin ? 'Fecha. (YYYY-MM-DD)' : 'Fecha (YYYY-MM-DD)',
              border: const OutlineInputBorder(),
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
          TextField(
            controller: _estacionCtrl,
            decoration: const InputDecoration(labelText: 'Estación de Servicio (opcional)', border: OutlineInputBorder()),
          ),
          if (widget.estaciones.isNotEmpty) ...[
            const SizedBox(height: 6),
            Wrap(
              spacing: 6,
              runSpacing: 4,
              children: widget.estaciones.map((est) {
                return ActionChip(
                  label: Text(est.nombre, style: const TextStyle(fontSize: 12)),
                  onPressed: () {
                    setState(() => _estacionCtrl.text = est.nombre);
                  },
                );
              }).toList(),
            ),
          ],
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

  const VehiculosScreen({super.key, required this.api, required this.vehiculos});

  @override
  State<VehiculosScreen> createState() => _VehiculosScreenState();
}

class _VehiculosScreenState extends State<VehiculosScreen> {
  final _nombreCtrl = TextEditingController();
  final _matriculaCtrl = TextEditingController();
  final _modeloCtrl = TextEditingController();
  int _editingId = 0;

  Future<void> _saveVehiculo() async {
    if (_nombreCtrl.text.trim().isEmpty) return;
    await widget.api.post('save_vehiculo', {
      'id': _editingId,
      'nombre': _nombreCtrl.text.trim(),
      'matricula': _matriculaCtrl.text.trim(),
      'modelo': _modeloCtrl.text.trim(),
      'tipo_combustible': 'Diésel',
    });
    if (mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mis Vehículos')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ...widget.vehiculos.map(
            (v) => Card(
              child: ListTile(
                leading: const Icon(Icons.directions_car, color: Color(0xFF0F766E)),
                title: Text(v.nombre, style: const TextStyle(fontWeight: FontWeight.bold)),
                subtitle: Text('${v.matricula} · ${v.tipoCombustible}'),
                trailing: IconButton(
                  icon: const Icon(Icons.edit),
                  onPressed: () {
                    setState(() {
                      _editingId = v.id;
                      _nombreCtrl.text = v.nombre;
                      _matriculaCtrl.text = v.matricula;
                      _modeloCtrl.text = v.modelo;
                    });
                  },
                ),
              ),
            ),
          ),
          const Divider(height: 32),
          Text(
            _editingId == 0 ? 'Añadir Nuevo Vehículo' : 'Editar Vehículo',
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
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
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: _saveVehiculo,
            icon: const Icon(Icons.save),
            label: const Text('Guardar Vehículo'),
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
