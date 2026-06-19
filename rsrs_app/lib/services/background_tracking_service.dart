import 'dart:async';
import 'dart:io';
import 'dart:ui';

import 'package:battery_plus/battery_plus.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_background_service/flutter_background_service.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:geolocator/geolocator.dart';

import '../models/telemetry_snapshot.dart';
import '../models/trip_session.dart';
import 'api_client.dart';
import 'rsrs_storage.dart';

const rsrsTrackingChannelId = 'rsrs_trip_tracking';
const rsrsTrackingNotificationId = 7001;

Future<void> initializeTrackingService() async {
  if (kIsWeb || (!Platform.isAndroid && !Platform.isIOS)) {
    return;
  }

  final service = FlutterBackgroundService();
  final notifications = FlutterLocalNotificationsPlugin();

  if (Platform.isAndroid || Platform.isIOS) {
    await notifications.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('ic_bg_service_small'),
        iOS: DarwinInitializationSettings(),
      ),
    );
  }

  const channel = AndroidNotificationChannel(
    rsrsTrackingChannelId,
    'RSRS Trip Tracking',
    description: 'Shows passenger trip tracking while RSRS is monitoring.',
    importance: Importance.low,
  );

  await notifications
      .resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin
      >()
      ?.createNotificationChannel(channel);

  await service.configure(
    androidConfiguration: AndroidConfiguration(
      onStart: rsrsTrackingServiceEntryPoint,
      autoStart: false,
      autoStartOnBoot: false,
      isForegroundMode: true,
      notificationChannelId: rsrsTrackingChannelId,
      initialNotificationTitle: 'RSRS trip tracking active',
      initialNotificationContent: 'Your road safety trip is being monitored.',
      foregroundServiceNotificationId: rsrsTrackingNotificationId,
      foregroundServiceTypes: [AndroidForegroundType.location],
    ),
    iosConfiguration: IosConfiguration(
      autoStart: false,
      onForeground: rsrsTrackingServiceEntryPoint,
      onBackground: rsrsIosBackground,
    ),
  );
}

Future<void> startTripTrackingService() async {
  if (kIsWeb || (!Platform.isAndroid && !Platform.isIOS)) {
    return;
  }

  final service = FlutterBackgroundService();
  final running = await service.isRunning();
  if (!running) {
    await service.startService();
  }
  service.invoke('refreshTrip');
}

Future<void> stopTripTrackingService() async {
  if (kIsWeb || (!Platform.isAndroid && !Platform.isIOS)) {
    return;
  }

  final service = FlutterBackgroundService();
  if (await service.isRunning()) {
    service.invoke('stopService');
  }
}

Future<int> flushPendingRequests({
  RsrsStorage? storage,
  ApiClient? apiClient,
}) async {
  final activeStorage = storage ?? RsrsStorage();
  final api = apiClient ?? const ApiClient();
  final queue = await activeStorage.loadQueue();
  if (queue.isEmpty) {
    return 0;
  }

  final remaining = <QueuedApiRequest>[];
  var blocked = false;

  for (final request in queue) {
    if (blocked) {
      remaining.add(request);
      continue;
    }

    try {
      await api.sendQueued(request);
    } on ApiException catch (error) {
      if (error.statusCode == 409 && request.kind == 'telemetry') {
        continue;
      }
      blocked = true;
      remaining.add(request);
    } on Object {
      blocked = true;
      remaining.add(request);
    }
  }

  await activeStorage.saveQueue(remaining);
  return remaining.length;
}

@pragma('vm:entry-point')
Future<bool> rsrsIosBackground(ServiceInstance service) async {
  DartPluginRegistrant.ensureInitialized();
  return true;
}

@pragma('vm:entry-point')
void rsrsTrackingServiceEntryPoint(ServiceInstance service) async {
  DartPluginRegistrant.ensureInitialized();

  final storage = RsrsStorage();
  final notifications = FlutterLocalNotificationsPlugin();
  Timer? timer;
  var running = true;

  Future<void> stopSelf() async {
    running = false;
    timer?.cancel();
    await notifications.cancel(rsrsTrackingNotificationId);
    service.stopSelf();
  }

  service.on('stopService').listen((event) {
    stopSelf();
  });

  if (service is AndroidServiceInstance) {
    service.setAsForegroundService();
  }

  Future<void> tick() async {
    if (!running) {
      return;
    }

    await storage.reload();
    final trip = await storage.loadActiveTrip();
    if (trip == null || !trip.isActive) {
      await storage.clearActiveTrip();
      await stopSelf();
      return;
    }

    try {
      final snapshot = await _captureTelemetry(trip);
      await storage.saveLatestTelemetry(snapshot);
      await storage.enqueue(
        QueuedApiRequest(
          path: '/api/trips/${trip.id}/telemetry',
          payload: snapshot.toJson(),
          kind: 'telemetry',
          createdAt: DateTime.now().toUtc(),
        ),
      );

      final pendingCount = await flushPendingRequests(storage: storage);
      final updatedSnapshot = snapshot.copyWith(pendingCount: pendingCount);
      await storage.saveLatestTelemetry(updatedSnapshot);

      service.invoke('telemetry', {
        'trip': trip.toJson(),
        'latest': updatedSnapshot.toJson(),
      });

      await _showTrackingNotification(notifications, trip, updatedSnapshot);
    } on Object {
      final pendingCount = await storage.queuedCount();
      service.invoke('telemetry', {
        'trip': trip.toJson(),
        'pending_count': pendingCount,
      });
      await _showTrackingNotification(notifications, trip, null);
    }
  }

  await tick();
  timer = Timer.periodic(const Duration(seconds: 30), (_) => tick());
}

Future<TelemetrySnapshot> _captureTelemetry(TripSession trip) async {
  final position = await Geolocator.getCurrentPosition(
    locationSettings: const LocationSettings(
      accuracy: LocationAccuracy.high,
      timeLimit: Duration(seconds: 20),
    ),
  );

  final speedKmh = position.speed.isFinite && position.speed > 0
      ? position.speed * 3.6
      : 0.0;

  int? batteryLevel;
  try {
    batteryLevel = await Battery().batteryLevel;
  } on Object {
    batteryLevel = null;
  }

  String? networkType;
  try {
    final connectivity = await Connectivity().checkConnectivity();
    networkType = connectivity.map((item) => item.name).join(',');
  } on Object {
    networkType = null;
  }

  return TelemetrySnapshot(
    recordedAt: DateTime.now().toUtc(),
    latitude: position.latitude,
    longitude: position.longitude,
    speedKmh: double.parse(speedKmh.toStringAsFixed(2)),
    accuracyMeters: double.parse(position.accuracy.toStringAsFixed(2)),
    batteryLevel: batteryLevel,
    networkType: networkType,
  );
}

Future<void> _showTrackingNotification(
  FlutterLocalNotificationsPlugin notifications,
  TripSession trip,
  TelemetrySnapshot? snapshot,
) async {
  final route = trip.routeName?.trim().isNotEmpty == true
      ? trip.routeName!.trim()
      : trip.publicReference;
  final content = snapshot == null
      ? 'Tracking $route. Waiting for GPS update.'
      : 'Tracking $route. Speed ${snapshot.speedKmh.toStringAsFixed(0)} km/h.';

  await notifications.show(
    rsrsTrackingNotificationId,
    'RSRS trip tracking active',
    content,
    const NotificationDetails(
      android: AndroidNotificationDetails(
        rsrsTrackingChannelId,
        'RSRS Trip Tracking',
        icon: 'ic_bg_service_small',
        ongoing: true,
        onlyAlertOnce: true,
      ),
    ),
  );
}
