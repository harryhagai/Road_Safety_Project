class TripSession {
  const TripSession({
    required this.id,
    required this.publicReference,
    required this.status,
    required this.startedAt,
    required this.expiresAt,
    required this.maxDurationHours,
    required this.telemetryIntervalSeconds,
    this.deviceId,
    this.routeName,
    this.endedAt,
  });

  final int id;
  final String publicReference;
  final String? deviceId;
  final String? routeName;
  final String status;
  final DateTime startedAt;
  final DateTime expiresAt;
  final DateTime? endedAt;
  final int maxDurationHours;
  final int telemetryIntervalSeconds;

  bool get isActive =>
      status == 'active' && DateTime.now().isBefore(expiresAt.toLocal());

  Duration get remaining {
    final value = expiresAt.toLocal().difference(DateTime.now());
    return value.isNegative ? Duration.zero : value;
  }

  Duration get elapsed {
    final end = endedAt?.toLocal() ?? DateTime.now();
    final value = end.difference(startedAt.toLocal());
    return value.isNegative ? Duration.zero : value;
  }

  factory TripSession.fromJson(Map<String, dynamic> json) {
    return TripSession(
      id: _asInt(json['id']),
      publicReference: json['public_reference']?.toString() ?? '',
      deviceId: json['device_id']?.toString(),
      routeName: json['route_name']?.toString(),
      status: json['status']?.toString() ?? 'active',
      startedAt: _asDate(json['started_at']) ?? DateTime.now().toUtc(),
      expiresAt:
          _asDate(json['expires_at']) ??
          DateTime.now().toUtc().add(const Duration(hours: 8)),
      endedAt: _asDate(json['ended_at']),
      maxDurationHours: _asInt(json['max_duration_hours'], fallback: 8),
      telemetryIntervalSeconds: _asInt(
        json['telemetry_interval_seconds'],
        fallback: 30,
      ),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'public_reference': publicReference,
      'device_id': deviceId,
      'route_name': routeName,
      'status': status,
      'started_at': startedAt.toUtc().toIso8601String(),
      'expires_at': expiresAt.toUtc().toIso8601String(),
      'ended_at': endedAt?.toUtc().toIso8601String(),
      'max_duration_hours': maxDurationHours,
      'telemetry_interval_seconds': telemetryIntervalSeconds,
    };
  }

  static int _asInt(Object? value, {int fallback = 0}) {
    if (value is int) {
      return value;
    }
    if (value is num) {
      return value.toInt();
    }
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }

  static DateTime? _asDate(Object? value) {
    if (value == null) {
      return null;
    }

    return DateTime.tryParse(value.toString());
  }
}
