import 'dart:io';
import 'dart:math';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';

import 'rsrs_storage.dart';

class DeviceService {
  DeviceService({RsrsStorage? storage}) : _storage = storage ?? RsrsStorage();

  final RsrsStorage _storage;

  Future<String> anonymousDeviceId() async {
    final existing = await _storage.loadDeviceId();
    if (existing != null && existing.isNotEmpty) {
      return existing;
    }

    final random = Random.secure().nextInt(1 << 32).toRadixString(16);
    final deviceId = 'anon-${DateTime.now().millisecondsSinceEpoch}-$random';
    await _storage.saveDeviceId(deviceId);

    return deviceId;
  }

  Future<Map<String, dynamic>> metadata() async {
    if (kIsWeb) {
      return const {'platform': 'web', 'app': 'rsrs_android_tracker'};
    }

    final info = DeviceInfoPlugin();
    final metadata = <String, dynamic>{
      'platform': Platform.operatingSystem,
      'app': 'rsrs_android_tracker',
    };

    if (Platform.isAndroid) {
      final android = await info.androidInfo;
      metadata.addAll({
        'manufacturer': android.manufacturer,
        'model': android.model,
        'sdk_int': android.version.sdkInt,
      });
    } else if (Platform.isIOS) {
      final ios = await info.iosInfo;
      metadata.addAll({
        'model': ios.model,
        'system_version': ios.systemVersion,
      });
    }

    return metadata;
  }
}
