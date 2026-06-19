import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../models/telemetry_snapshot.dart';
import '../models/trip_session.dart';

class QueuedApiRequest {
  const QueuedApiRequest({
    required this.path,
    required this.payload,
    required this.kind,
    required this.createdAt,
  });

  final String path;
  final Map<String, dynamic> payload;
  final String kind;
  final DateTime createdAt;

  factory QueuedApiRequest.fromJson(Map<String, dynamic> json) {
    final rawPayload = json['payload'];
    return QueuedApiRequest(
      path: json['path']?.toString() ?? '',
      payload: rawPayload is Map
          ? rawPayload.cast<String, dynamic>()
          : <String, dynamic>{},
      kind: json['kind']?.toString() ?? 'telemetry',
      createdAt:
          DateTime.tryParse(json['created_at']?.toString() ?? '') ??
          DateTime.now().toUtc(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'path': path,
      'payload': payload,
      'kind': kind,
      'created_at': createdAt.toUtc().toIso8601String(),
    };
  }
}

class RsrsStorage {
  static const _activeTripKey = 'rsrs.active_trip';
  static const _latestTelemetryKey = 'rsrs.latest_telemetry';
  static const _queueKey = 'rsrs.pending_requests';
  static const _deviceIdKey = 'rsrs.device_id';

  Future<SharedPreferences> get _prefs => SharedPreferences.getInstance();

  Future<void> reload() async {
    final prefs = await _prefs;
    await prefs.reload();
  }

  Future<String?> loadDeviceId() async {
    return (await _prefs).getString(_deviceIdKey);
  }

  Future<void> saveDeviceId(String deviceId) async {
    await (await _prefs).setString(_deviceIdKey, deviceId);
  }

  Future<TripSession?> loadActiveTrip() async {
    final raw = (await _prefs).getString(_activeTripKey);
    if (raw == null || raw.isEmpty) {
      return null;
    }

    return TripSession.fromJson(jsonDecode(raw) as Map<String, dynamic>);
  }

  Future<void> saveActiveTrip(TripSession trip) async {
    await (await _prefs).setString(_activeTripKey, jsonEncode(trip.toJson()));
  }

  Future<void> clearActiveTrip() async {
    final prefs = await _prefs;
    await prefs.remove(_activeTripKey);
    await prefs.remove(_latestTelemetryKey);
  }

  Future<TelemetrySnapshot?> loadLatestTelemetry() async {
    final raw = (await _prefs).getString(_latestTelemetryKey);
    if (raw == null || raw.isEmpty) {
      return null;
    }

    return TelemetrySnapshot.fromJson(jsonDecode(raw) as Map<String, dynamic>);
  }

  Future<void> saveLatestTelemetry(TelemetrySnapshot snapshot) async {
    await (await _prefs).setString(
      _latestTelemetryKey,
      jsonEncode(snapshot.toJson()),
    );
  }

  Future<List<QueuedApiRequest>> loadQueue() async {
    final rows = (await _prefs).getStringList(_queueKey) ?? <String>[];
    return rows
        .map(
          (row) => QueuedApiRequest.fromJson(
            jsonDecode(row) as Map<String, dynamic>,
          ),
        )
        .where((request) => request.path.isNotEmpty)
        .toList();
  }

  Future<void> saveQueue(List<QueuedApiRequest> requests) async {
    await (await _prefs).setStringList(
      _queueKey,
      requests.map((request) => jsonEncode(request.toJson())).toList(),
    );
  }

  Future<void> enqueue(QueuedApiRequest request) async {
    final requests = await loadQueue();
    requests.add(request);
    await saveQueue(requests);
  }

  Future<int> queuedCount() async {
    return (await loadQueue()).length;
  }
}
