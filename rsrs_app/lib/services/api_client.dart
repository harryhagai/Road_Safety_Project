import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/app_config.dart';
import '../models/trip_session.dart';
import 'rsrs_storage.dart';

class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

class ApiClient {
  const ApiClient({http.Client? client}) : _client = client;

  final http.Client? _client;

  http.Client get _http => _client ?? http.Client();

  Future<TripSession> startTrip({
    required String deviceId,
    required String routeName,
    required double? latitude,
    required double? longitude,
    required Map<String, dynamic> metadata,
  }) async {
    final response = await postJson('/api/trips/start', {
      'device_id': deviceId,
      'route_name': routeName.isEmpty ? null : routeName,
      'started_at': DateTime.now().toUtc().toIso8601String(),
      'start_latitude': latitude,
      'start_longitude': longitude,
      'metadata': metadata,
    });

    return TripSession.fromJson(response['trip'] as Map<String, dynamic>);
  }

  Future<Map<String, dynamic>> fetchTripStatus(int tripId) {
    return getJson('/api/trips/$tripId/status');
  }

  Future<Map<String, dynamic>> sendTelemetry(
    int tripId,
    Map<String, dynamic> payload,
  ) {
    return postJson('/api/trips/$tripId/telemetry', payload);
  }

  Future<Map<String, dynamic>> submitViolation(
    int tripId,
    Map<String, dynamic> payload,
  ) {
    return postJson('/api/trips/$tripId/violations', payload);
  }

  Future<TripSession> stopTrip(
    int tripId, {
    required String reason,
    double? latitude,
    double? longitude,
  }) async {
    final response = await postJson('/api/trips/$tripId/stop', {
      'ended_at': DateTime.now().toUtc().toIso8601String(),
      'end_reason': reason,
      'end_latitude': latitude,
      'end_longitude': longitude,
    });

    return TripSession.fromJson(response['trip'] as Map<String, dynamic>);
  }

  Future<void> sendQueued(QueuedApiRequest request) async {
    await postJson(request.path, request.payload);
  }

  Future<Map<String, dynamic>> getJson(String path) async {
    final response = await _http
        .get(
          AppConfig.apiUri(path),
          headers: const {'Accept': 'application/json'},
        )
        .timeout(const Duration(seconds: 25));

    return _decodeResponse(response);
  }

  Future<Map<String, dynamic>> postJson(
    String path,
    Map<String, dynamic> payload,
  ) async {
    final response = await _http
        .post(
          AppConfig.apiUri(path),
          headers: const {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: jsonEncode(_withoutNulls(payload)),
        )
        .timeout(const Duration(seconds: 25));

    return _decodeResponse(response);
  }

  Map<String, dynamic> _decodeResponse(http.Response response) {
    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode < 200 || response.statusCode >= 300) {
      final message =
          decoded['message']?.toString() ?? 'RSRS API request failed.';
      throw ApiException(message, statusCode: response.statusCode);
    }

    return decoded;
  }

  Map<String, dynamic> _withoutNulls(Map<String, dynamic> value) {
    final result = <String, dynamic>{};
    value.forEach((key, item) {
      if (item != null) {
        result[key] = item;
      }
    });

    return result;
  }
}
