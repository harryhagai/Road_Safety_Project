import 'package:geolocator/geolocator.dart';

import '../models/telemetry_snapshot.dart';
import '../models/trip_session.dart';
import 'api_client.dart';
import 'background_tracking_service.dart';
import 'device_service.dart';
import 'rsrs_storage.dart';
import 'tracking_permission_service.dart';

class TripRepository {
  TripRepository({
    ApiClient? apiClient,
    RsrsStorage? storage,
    DeviceService? deviceService,
    TrackingPermissionService? permissionService,
  }) : _api = apiClient ?? const ApiClient(),
       _storage = storage ?? RsrsStorage(),
       _deviceService = deviceService ?? DeviceService(),
       _permissionService = permissionService ?? TrackingPermissionService();

  final ApiClient _api;
  final RsrsStorage _storage;
  final DeviceService _deviceService;
  final TrackingPermissionService _permissionService;

  Future<TripSession?> loadActiveTrip() async {
    final trip = await _storage.loadActiveTrip();
    if (trip == null) {
      return null;
    }

    if (!trip.isActive) {
      await _storage.clearActiveTrip();
      await stopTripTrackingService();
      return null;
    }

    return trip;
  }

  Future<TelemetrySnapshot?> loadLatestTelemetry() {
    return _storage.loadLatestTelemetry();
  }

  Future<int> pendingCount() {
    return _storage.queuedCount();
  }

  Future<TripSession> startTrip([String routeName = '']) async {
    await _permissionService.ensureReadyForTrip();

    Position? position;
    try {
      position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 18),
        ),
      );
    } on Object {
      position = null;
    }

    final trip = await _api.startTrip(
      deviceId: await _deviceService.anonymousDeviceId(),
      routeName: routeName,
      latitude: position?.latitude,
      longitude: position?.longitude,
      metadata: await _deviceService.metadata(),
    );

    await _storage.saveActiveTrip(trip);
    await startTripTrackingService();

    return trip;
  }

  Future<TripSession?> refreshStatus() async {
    final trip = await _storage.loadActiveTrip();
    if (trip == null) {
      return null;
    }

    final response = await _api.fetchTripStatus(trip.id);
    final updated = TripSession.fromJson(
      response['trip'] as Map<String, dynamic>,
    );

    if (updated.isActive) {
      await _storage.saveActiveTrip(updated);
      return updated;
    }

    await _storage.clearActiveTrip();
    await stopTripTrackingService();
    return null;
  }

  Future<void> stopTrip({String reason = 'completed'}) async {
    final trip = await _storage.loadActiveTrip();
    if (trip == null) {
      await stopTripTrackingService();
      return;
    }

    Position? position;
    try {
      position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.medium,
          timeLimit: Duration(seconds: 12),
        ),
      );
    } on Object {
      position = null;
    }

    try {
      await _api.stopTrip(
        trip.id,
        reason: reason,
        latitude: position?.latitude,
        longitude: position?.longitude,
      );
    } on Object {
      await _storage.enqueue(
        QueuedApiRequest(
          path: '/api/trips/${trip.id}/stop',
          payload: {
            'ended_at': DateTime.now().toUtc().toIso8601String(),
            'end_reason': reason,
            'end_latitude': position?.latitude,
            'end_longitude': position?.longitude,
          },
          kind: 'stop',
          createdAt: DateTime.now().toUtc(),
        ),
      );
    }

    await _storage.clearActiveTrip();
    await stopTripTrackingService();
  }

  Future<bool> submitViolation({
    required String type,
    required String description,
  }) async {
    final trip = await _storage.loadActiveTrip();
    if (trip == null) {
      throw const ApiException(
        'Start a trip before sending a violation report.',
      );
    }

    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        timeLimit: Duration(seconds: 18),
      ),
    );

    final payload = {
      'type': type,
      'description': description.trim().isEmpty ? null : description.trim(),
      'latitude': position.latitude,
      'longitude': position.longitude,
      'recorded_at': DateTime.now().toUtc().toIso8601String(),
    };

    try {
      await _api.submitViolation(trip.id, payload);
      await flushPendingRequests(storage: _storage, apiClient: _api);
      return true;
    } on Object {
      await _storage.enqueue(
        QueuedApiRequest(
          path: '/api/trips/${trip.id}/violations',
          payload: payload,
          kind: 'violation',
          createdAt: DateTime.now().toUtc(),
        ),
      );
      return false;
    }
  }
}
