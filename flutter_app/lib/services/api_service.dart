import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiService {
  static const String defaultBaseUrl = 'https://www.moratalla-murcia.com/combustible/api.php';
  Duration sessionInactivityTimeout = const Duration(minutes: 60);
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  String _baseUrl = defaultBaseUrl;
  String? _username;
  String? _password;
  bool isAdmin = false;
  String? lastError;
  DateTime? _lastActivity;
  DateTime? _lastPersistedActivity;

  String get username => _username ?? '';
  String get baseUrl => _baseUrl;
  DateTime? get lastActivity => _lastActivity;

  bool get isSessionExpired {
    if (_lastActivity == null) return false;
    return DateTime.now().difference(_lastActivity!) >= sessionInactivityTimeout;
  }

  Future<void> touchActivity({bool forcePersist = false}) async {
    final now = DateTime.now();
    _lastActivity = now;
    if (forcePersist ||
        _lastPersistedActivity == null ||
        now.difference(_lastPersistedActivity!) >= const Duration(seconds: 15)) {
      _lastPersistedActivity = now;
      await _storage.write(key: 'mm_last_activity', value: now.millisecondsSinceEpoch.toString());
    }
  }

  Future<void> updateInactivityMinutes(int mins) async {
    final safeMins = mins < 1 ? 60 : mins;
    sessionInactivityTimeout = Duration(minutes: safeMins);
    await _storage.write(key: 'mm_inactivity_min', value: safeMins.toString());
  }

  Future<bool> restoreSession() async {
    final u = await _storage.read(key: 'mm_user');
    final p = await _storage.read(key: 'mm_pass');
    final url = await _storage.read(key: 'mm_url');
    final lastActStr = await _storage.read(key: 'mm_last_activity');
    final adminStr = await _storage.read(key: 'mm_is_admin');
    final inactStr = await _storage.read(key: 'mm_inactivity_min');
    if (inactStr != null) {
      final mins = int.tryParse(inactStr);
      if (mins != null && mins >= 1) {
        sessionInactivityTimeout = Duration(minutes: mins);
      }
    }

    if (url != null && url.isNotEmpty) {
      _baseUrl = url;
    }
    if (u == null || p == null || u.isEmpty || p.isEmpty) {
      return false;
    }

    if (lastActStr != null) {
      final ms = int.tryParse(lastActStr);
      if (ms != null) {
        _lastActivity = DateTime.fromMillisecondsSinceEpoch(ms);
        if (isSessionExpired) {
          await logout();
          return false;
        }
      }
    }

    _username = u;
    _password = p;
    isAdmin = adminStr == '1';
    await touchActivity(forcePersist: true);

    try {
      final res = await post('login', {});
      if (res['success'] == true) {
        final userMap = res['user'] as Map<String, dynamic>? ?? {};
        isAdmin = userMap['is_admin'] == true;
        await _storage.write(key: 'mm_is_admin', value: isAdmin ? '1' : '0');
        final params = res['parametros'];
        if (params is Map && params['inactividad_app_min'] != null) {
          final m = int.tryParse(params['inactividad_app_min'].toString());
          if (m != null && m >= 1) await updateInactivityMinutes(m);
        }
        return true;
      }
      // Credenciales rechazadas explícitamente por el servidor
      await logout();
      return false;
    } catch (_) {
      // Error de red/timeout al reanudar: mantener sesión autenticada localmente
      return true;
    }
  }

  Future<bool> login(String username, String password, {String? customUrl}) async {
    if (customUrl != null && customUrl.isNotEmpty) {
      _baseUrl = customUrl;
      await _storage.write(key: 'mm_url', value: _baseUrl);
    }
    _username = username;
    _password = password;

    lastError = null;
    final res = await post('login', {});
    if (res['success'] == true) {
      final userMap = res['user'] as Map<String, dynamic>? ?? {};
      isAdmin = userMap['is_admin'] == true;
      await _storage.write(key: 'mm_user', value: username);
      await _storage.write(key: 'mm_pass', value: password);
      await _storage.write(key: 'mm_is_admin', value: isAdmin ? '1' : '0');
      final params = res['parametros'];
      if (params is Map && params['inactividad_app_min'] != null) {
        final m = int.tryParse(params['inactividad_app_min'].toString());
        if (m != null && m >= 1) await updateInactivityMinutes(m);
      }
      await touchActivity(forcePersist: true);
      return true;
    }
    lastError = res['error']?.toString();
    return false;
  }

  Future<Map<String, dynamic>> register({
    required String username,
    required String email,
    required String password,
  }) async {
    final uri = Uri.parse('$_baseUrl?action=register');
    final response = await http.post(
      uri,
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'action': 'register',
        'username': username,
        'email': email,
        'password': password,
      }),
    );
    return jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;
  }

  Future<void> logout() async {
    _username = null;
    _password = null;
    isAdmin = false;
    _lastActivity = null;
    _lastPersistedActivity = null;
    await _storage.delete(key: 'mm_user');
    await _storage.delete(key: 'mm_pass');
    await _storage.delete(key: 'mm_is_admin');
    await _storage.delete(key: 'mm_last_activity');
  }

  Future<Map<String, dynamic>> post(String action, Map<String, dynamic> body) async {
    final basicAuth = 'Basic ${base64Encode(utf8.encode('${_username ?? ''}:${_password ?? ''}'))}';
    final uri = Uri.parse('$_baseUrl?action=$action');
    final payload = {
      ...body,
      'username': _username,
      'password': _password,
      'action': action,
    };

    final response = await http.post(
      uri,
      headers: {
        'Content-Type': 'application/json',
        'Authorization': basicAuth,
      },
      body: jsonEncode(payload),
    );

    final decoded = jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;
    if (action != 'login' && decoded['success'] == true) {
      await touchActivity();
    }
    return decoded;
  }
}
