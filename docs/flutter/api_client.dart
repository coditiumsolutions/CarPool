// Starter API client for the Ride Flutter app.
// pubspec.yaml: http: ^1.2.0, flutter_secure_storage: ^9.2.2
import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

class ApiException implements Exception {
  final int status;
  final String message;
  final Map<String, dynamic>? errors; // field => [messages] on 422
  ApiException(this.status, this.message, [this.errors]);
  @override
  String toString() => message;
}

class ApiClient {
  // Android emulator -> local Laravel: http://10.0.2.2:8000/api/v1
  static const baseUrl = 'https://ride.coditium.com/api/v1';
  final _storage = const FlutterSecureStorage();
  String? _token;

  Future<void> loadToken() async => _token = await _storage.read(key: 'token');

  Future<void> _saveToken(String? t) async {
    _token = t;
    t == null ? await _storage.delete(key: 'token') : await _storage.write(key: 'token', value: t);
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
      };

  Future<Map<String, dynamic>> _send(String method, String path,
      {Map<String, dynamic>? body, Map<String, String>? query}) async {
    final uri = Uri.parse('$baseUrl$path').replace(queryParameters: query);
    final req = http.Request(method, uri)..headers.addAll(_headers);
    if (body != null) req.body = jsonEncode(body);
    final res = await http.Response.fromStream(await req.send()).timeout(const Duration(seconds: 30));
    final json = res.body.isEmpty ? <String, dynamic>{} : jsonDecode(res.body) as Map<String, dynamic>;

    if (res.statusCode == 401 && _token != null && path != '/login') {
      await _saveToken(null); // token expired/revoked -> send user to login screen
    }
    if (res.statusCode >= 400) {
      throw ApiException(res.statusCode, json['message']?.toString() ?? 'Request failed', json['errors']);
    }
    return json;
  }

  // ---- Auth ----
  Future<Map<String, dynamic>> login(String login, String password) async {
    final r = await _send('POST', '/login', body: {'login': login, 'password': password, 'device_name': 'flutter'});
    await _saveToken(r['data']['token']);
    return r['data']['user'];
  }

  Future<Map<String, dynamic>> register(Map<String, dynamic> form) async {
    final r = await _send('POST', '/register', body: {...form, 'device_name': 'flutter'});
    await _saveToken(r['data']['token']);
    return r['data']['user'];
  }

  Future<void> logout() async {
    try {
      await _send('POST', '/logout');
    } finally {
      await _saveToken(null);
    }
  }

  Future<Map<String, dynamic>> me() async => (await _send('GET', '/user'))['data'];

  // ---- Examples (all other endpoints follow the same pattern) ----
  Future<List<dynamic>> cities() async => (await _send('GET', '/cities'))['data'];

  Future<Map<String, dynamic>> searchRides({int? fromCityId, int? toCityId, String? date, int page = 1}) =>
      _send('GET', '/rides', query: {
        if (fromCityId != null) 'from_city_id': '$fromCityId',
        if (toCityId != null) 'to_city_id': '$toCityId',
        if (date != null) 'date': date, // yyyy-MM-dd
        'page': '$page',
      }); // returns {data: [...], meta: {...}}

  Future<Map<String, dynamic>> bookRide(int rideId, int seats) async =>
      (await _send('POST', '/bookings', body: {'ride_id': rideId, 'seats': seats}))['data'];

  Future<Map<String, dynamic>> myBookings({int page = 1}) => _send('GET', '/bookings', query: {'page': '$page'});

  Future<void> cancelBooking(int id, {String? reason}) => _send('POST', '/bookings/$id/cancel', body: {'reason': reason});
}
