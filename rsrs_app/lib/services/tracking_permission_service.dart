import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:geolocator/geolocator.dart';

class TrackingPermissionException implements Exception {
  const TrackingPermissionException(this.message);

  final String message;

  @override
  String toString() => message;
}

class TrackingPermissionService {
  Future<void> ensureReadyForTrip() async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      throw const TrackingPermissionException(
        'Location service is off. Please enable GPS before starting a trip.',
      );
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw const TrackingPermissionException(
        'Location permission is required for RSRS trip tracking.',
      );
    }

    if (!kIsWeb &&
        Platform.isAndroid &&
        permission != LocationPermission.always) {
      final backgroundPermission = await Geolocator.requestPermission();
      if (backgroundPermission != LocationPermission.always) {
        throw const TrackingPermissionException(
          'Allow all-the-time location access so tracking can continue while the app is minimized.',
        );
      }
    }

    if (!kIsWeb && Platform.isAndroid) {
      final notifications = FlutterLocalNotificationsPlugin()
          .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin
          >();
      final granted = await notifications?.requestNotificationsPermission();
      if (granted == false) {
        throw const TrackingPermissionException(
          'Notification permission is required because Android must show a tracking notification.',
        );
      }
    }
  }
}
