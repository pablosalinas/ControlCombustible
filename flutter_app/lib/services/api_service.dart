import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiService {
  static const String defaultBaseUrl = 'https://www.moratalla-murcia.com/combustible/api.php';
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  String _baseUrl = defaultBaseUrl;
  String? _username;
  String? _password;
  bool isAdmin = false;

  String get username => _username ?? '';
  String get baseUrl => _baseUrl;

  Future<bool> restoreSession() async {
    final u = await _storage.read(key: 'mm_user');
    final p = await _storage.read(key: 'mm_pass');
    final url = await _storage.read(key: 'mm_url');
    if (url != null && url.isNotEmpty) {
      _baseUrl = url;
    }
    if (u != null && p != null && u.isNotEmpty && p.isNotEmpty) {
      return await login(u, p, customUrl: _baseUrl);
    }
    return false;
  }

  Future<bool> login(String username, String password, {String? customUrl}) async {
    if (customUrl != null && customUrl.isNotEmpty) {
      _baseUrl = customUrl;
      await _storage.write(key: 'mm_url', value: _baseUrl);
    }
    _username = username;
    _password = password;

    final res = await post('login', {});
    if (res['success'] == true) {
      final userMap = res['user'] as Map<String, dynamic>? ?? {};
      isAdmin = userMap['is_admin'] == true;
      await _storage.write(key: 'mm_user', value: username);
      await _storage.write(key: 'mm_pass', value: password);
      return true;
    }
    return false;
  }

  Future<void> logout() async {
    _username = null;
    _password = null;
    isAdmin = false;
    await _storage.delete(key: 'mm_user');
    await _storage.delete(key: 'mm_pass');
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

    return jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;
  }
}
