class TelemetrySnapshot {
  const TelemetrySnapshot({
    required this.recordedAt,
    required this.latitude,
    required this.longitude,
    required this.speedKmh,
    this.accuracyMeters,
    this.batteryLevel,
    this.networkType,
    this.pendingCount = 0,
  });

  final DateTime recordedAt;
  final double latitude;
  final double longitude;
  final double speedKmh;
  final double? accuracyMeters;
  final int? batteryLevel;
  final String? networkType;
  final int pendingCount;

  factory TelemetrySnapshot.fromJson(Map<String, dynamic> json) {
    return TelemetrySnapshot(
      recordedAt:
          DateTime.tryParse(json['recorded_at']?.toString() ?? '') ??
          DateTime.now().toUtc(),
      latitude: _asDouble(json['latitude']),
      longitude: _asDouble(json['longitude']),
      speedKmh: _asDouble(json['speed_kmh']),
      accuracyMeters: _asNullableDouble(json['accuracy_meters']),
      batteryLevel: _asNullableInt(json['battery_level']),
      networkType: json['network_type']?.toString(),
      pendingCount: _asInt(json['pending_count']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'recorded_at': recordedAt.toUtc().toIso8601String(),
      'latitude': latitude,
      'longitude': longitude,
      'speed_kmh': speedKmh,
      'accuracy_meters': accuracyMeters,
      'battery_level': batteryLevel,
      'network_type': networkType,
      'pending_count': pendingCount,
    };
  }

  TelemetrySnapshot copyWith({int? pendingCount}) {
    return TelemetrySnapshot(
      recordedAt: recordedAt,
      latitude: latitude,
      longitude: longitude,
      speedKmh: speedKmh,
      accuracyMeters: accuracyMeters,
      batteryLevel: batteryLevel,
      networkType: networkType,
      pendingCount: pendingCount ?? this.pendingCount,
    );
  }

  static double _asDouble(Object? value) {
    if (value is num) {
      return value.toDouble();
    }
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  static double? _asNullableDouble(Object? value) {
    if (value == null) {
      return null;
    }
    if (value is num) {
      return value.toDouble();
    }
    return double.tryParse(value.toString());
  }

  static int _asInt(Object? value) {
    if (value is int) {
      return value;
    }
    if (value is num) {
      return value.toInt();
    }
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static int? _asNullableInt(Object? value) {
    if (value == null) {
      return null;
    }
    if (value is int) {
      return value;
    }
    if (value is num) {
      return value.toInt();
    }
    return int.tryParse(value.toString());
  }
}
