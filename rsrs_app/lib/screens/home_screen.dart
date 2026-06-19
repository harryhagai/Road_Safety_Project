import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_background_service/flutter_background_service.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/app_config.dart';
import '../models/telemetry_snapshot.dart';
import '../models/trip_session.dart';
import '../services/api_client.dart';
import '../services/tracking_permission_service.dart';
import '../services/trip_repository.dart';
import 'report_violation_screen.dart';
import 'tracking_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.repository});

  final TripRepository repository;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  static const _defaultCenter = LatLng(-6.7924, 39.2083);

  final _mapController = MapController();
  StreamSubscription<Map<String, dynamic>?>? _serviceSubscription;

  TripSession? _trip;
  TelemetrySnapshot? _snapshot;
  bool _loading = true;
  bool _starting = false;
  bool _stopping = false;
  bool _consented = true;

  @override
  void initState() {
    super.initState();
    _loadState();
    if (!kIsWeb && (Platform.isAndroid || Platform.isIOS)) {
      _serviceSubscription = FlutterBackgroundService()
          .on('telemetry')
          .listen(_handleServiceUpdate);
    }
  }

  @override
  void dispose() {
    _serviceSubscription?.cancel();
    super.dispose();
  }

  Future<void> _loadState() async {
    final trip = await widget.repository.loadActiveTrip();
    final snapshot = await widget.repository.loadLatestTelemetry();
    if (!mounted) {
      return;
    }
    setState(() {
      _trip = trip;
      _snapshot = snapshot;
      _loading = false;
    });
    _moveMapToSnapshot(snapshot);
  }

  void _handleServiceUpdate(Map<String, dynamic>? event) {
    if (event == null || !mounted) {
      return;
    }

    final tripJson = event['trip'];
    final latestJson = event['latest'];
    TelemetrySnapshot? updatedSnapshot;

    setState(() {
      if (tripJson is Map) {
        _trip = TripSession.fromJson(tripJson.cast<String, dynamic>());
      }
      if (latestJson is Map) {
        updatedSnapshot = TelemetrySnapshot.fromJson(
          latestJson.cast<String, dynamic>(),
        );
        _snapshot = updatedSnapshot;
      }
    });

    _moveMapToSnapshot(updatedSnapshot);
  }

  void _moveMapToSnapshot(TelemetrySnapshot? snapshot) {
    if (snapshot == null) {
      return;
    }

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }
      _mapController.move(LatLng(snapshot.latitude, snapshot.longitude), 15);
    });
  }

  Future<void> _startTrip() async {
    if (!_consented) {
      _showMessage('Please accept the tracking notice before starting.');
      return;
    }

    setState(() => _starting = true);
    try {
      final trip = await widget.repository.startTrip();
      final snapshot = await widget.repository.loadLatestTelemetry();
      if (!mounted) {
        return;
      }
      setState(() {
        _trip = trip;
        _snapshot = snapshot;
      });
      _moveMapToSnapshot(snapshot);
    } on TrackingPermissionException catch (error) {
      _showMessage(
        error.message,
        actionLabel: 'Settings',
        onAction: Geolocator.openAppSettings,
      );
    } on ApiException catch (error) {
      _showMessage(error.message);
    } on Object {
      _showMessage('Unable to start tracking. Check internet and try again.');
    } finally {
      if (mounted) {
        setState(() => _starting = false);
      }
    }
  }

  Future<void> _stopTrip() async {
    setState(() => _stopping = true);
    try {
      await widget.repository.stopTrip();
      if (!mounted) {
        return;
      }
      setState(() {
        _trip = null;
        _snapshot = null;
      });
      _showMessage('Tracking ended.');
    } finally {
      if (mounted) {
        setState(() => _stopping = false);
      }
    }
  }

  void _showMessage(
    String message, {
    String? actionLabel,
    Future<void> Function()? onAction,
  }) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        action: actionLabel == null || onAction == null
            ? null
            : SnackBarAction(
                label: actionLabel,
                onPressed: () => unawaited(onAction()),
              ),
      ),
    );
  }

  Future<void> _openWeb(String path) async {
    await launchUrl(
      AppConfig.webUri(path),
      mode: LaunchMode.externalApplication,
    );
  }

  @override
  Widget build(BuildContext context) {
    final active = _trip?.isActive == true;
    final markerPoint = _snapshot == null
        ? null
        : LatLng(_snapshot!.latitude, _snapshot!.longitude);

    return Scaffold(
      body: SafeArea(
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : Stack(
                children: [
                  Positioned.fill(
                    child: _MapStage(
                      controller: _mapController,
                      initialCenter: markerPoint ?? _defaultCenter,
                      markerPoint: markerPoint,
                    ),
                  ),
                  Positioned(
                    top: 12,
                    left: 12,
                    right: 12,
                    child: _SpeedAlert(trip: _trip, snapshot: _snapshot),
                  ),
                  Positioned(
                    top: 108,
                    right: 12,
                    child: _QuickLinks(onOpen: _openWeb),
                  ),
                  Positioned(
                    left: 14,
                    bottom: active ? 142 : 160,
                    child: _SpeedWidget(active: active, snapshot: _snapshot),
                  ),
                  Positioned(
                    left: 12,
                    right: 12,
                    bottom: 12,
                    child: active
                        ? _ActiveDock(
                            trip: _trip!,
                            stopping: _stopping,
                            onOpenTracking: () => Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => TrackingScreen(
                                  repository: widget.repository,
                                ),
                              ),
                            ),
                            onReport: () => Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => ReportViolationScreen(
                                  repository: widget.repository,
                                ),
                              ),
                            ),
                            onStop: _stopTrip,
                          )
                        : _StartDock(
                            consented: _consented,
                            starting: _starting,
                            onConsentChanged: (value) =>
                                setState(() => _consented = value),
                            onStart: _startTrip,
                          ),
                  ),
                ],
              ),
      ),
    );
  }
}

class _MapStage extends StatelessWidget {
  const _MapStage({
    required this.controller,
    required this.initialCenter,
    required this.markerPoint,
  });

  final MapController controller;
  final LatLng initialCenter;
  final LatLng? markerPoint;

  @override
  Widget build(BuildContext context) {
    return FlutterMap(
      mapController: controller,
      options: MapOptions(initialCenter: initialCenter, initialZoom: 13),
      children: [
        TileLayer(
          urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
          userAgentPackageName: 'tracker.rsrs.rsrs_app',
        ),
        if (markerPoint != null)
          MarkerLayer(
            markers: [
              Marker(
                width: 58,
                height: 58,
                point: markerPoint!,
                child: const _LocationMarker(),
              ),
            ],
          ),
        RichAttributionWidget(
          showFlutterMapAttribution: false,
          attributions: [
            TextSourceAttribution(
              'OpenStreetMap contributors',
              onTap: () async => launchUrl(
                Uri.parse('https://www.openstreetmap.org/copyright'),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _LocationMarker extends StatelessWidget {
  const _LocationMarker();

  @override
  Widget build(BuildContext context) {
    return Stack(
      alignment: Alignment.center,
      children: [
        Container(
          width: 58,
          height: 58,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: const Color(0xFFF3B74A).withValues(alpha: 0.24),
          ),
        ),
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: const Color(0xFF232C3A),
            border: Border.all(color: Colors.white, width: 4),
            boxShadow: const [
              BoxShadow(
                color: Color(0x33000000),
                blurRadius: 16,
                offset: Offset(0, 8),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _SpeedAlert extends StatelessWidget {
  const _SpeedAlert({required this.trip, required this.snapshot});

  final TripSession? trip;
  final TelemetrySnapshot? snapshot;

  @override
  Widget build(BuildContext context) {
    final active = trip?.isActive == true;

    return _GlassPanel(
      padding: const EdgeInsets.all(12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              color: active
                  ? const Color(0xFF166534).withValues(alpha: 0.1)
                  : const Color(0xFF232C3A).withValues(alpha: 0.07),
            ),
            child: Icon(
              active ? Icons.sensors : Icons.info,
              color: active ? const Color(0xFF166534) : const Color(0xFF232C3A),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  active ? 'Trip monitoring active' : 'Speed info',
                  style: const TextStyle(
                    color: Color(0xFF232C3A),
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  active
                      ? 'RSRS is collecting trip telemetry for road safety.'
                      : 'We are checking your location and the nearest speed rule.',
                  style: const TextStyle(
                    color: Color(0xFF526176),
                    fontSize: 13,
                    height: 1.28,
                  ),
                ),
                const SizedBox(height: 7),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    _MiniMeta(
                      text: active
                          ? 'Trip: ${trip!.publicReference}'
                          : 'Segment: waiting...',
                    ),
                    _MiniMeta(
                      text: snapshot == null
                          ? 'Speed limit: unknown'
                          : 'Speed: ${snapshot!.speedKmh.toStringAsFixed(0)} km/h',
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MiniMeta extends StatelessWidget {
  const _MiniMeta({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(999),
        color: const Color(0xFFF8FAFC),
        border: Border.all(color: const Color(0x1A232C3A)),
      ),
      child: Text(
        text,
        style: const TextStyle(color: Color(0xFF6F7C90), fontSize: 11),
      ),
    );
  }
}

class _SpeedWidget extends StatelessWidget {
  const _SpeedWidget({required this.active, required this.snapshot});

  final bool active;
  final TelemetrySnapshot? snapshot;

  @override
  Widget build(BuildContext context) {
    final speed = snapshot?.speedKmh.toStringAsFixed(0) ?? '0';

    return _GlassPanel(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Stack(
            alignment: Alignment.center,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: const Color(0xFFF3B74A), width: 3),
                ),
              ),
              Container(
                width: 28,
                height: 28,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: Color(0xFF232C3A),
                ),
              ),
            ],
          ),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Speed',
                style: TextStyle(color: Color(0xFF6F7C90), fontSize: 11),
              ),
              RichText(
                text: TextSpan(
                  children: [
                    TextSpan(
                      text: speed,
                      style: const TextStyle(
                        color: Color(0xFF232C3A),
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const TextSpan(
                      text: ' km/h',
                      style: TextStyle(
                        color: Color(0xFF8A6A28),
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              Text(
                active ? 'Tracking movement...' : 'Waiting for movement...',
                style: const TextStyle(color: Color(0xFF6F7C90), fontSize: 10),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StartDock extends StatelessWidget {
  const _StartDock({
    required this.consented,
    required this.starting,
    required this.onConsentChanged,
    required this.onStart,
  });

  final bool consented;
  final bool starting;
  final ValueChanged<bool> onConsentChanged;
  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) {
    return _GlassPanel(
      padding: const EdgeInsets.all(12),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          CheckboxListTile(
            value: consented,
            dense: true,
            onChanged: (value) => onConsentChanged(value ?? false),
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            title: const Text(
              'I agree to RSRS trip location tracking.',
              style: TextStyle(fontWeight: FontWeight.w600),
            ),
            subtitle: const Text(
              'Tracking stops after End Trip or 8 hours.',
              style: TextStyle(color: Color(0xFF6F7C90)),
            ),
          ),
          const SizedBox(height: 8),
          FilledButton.icon(
            onPressed: starting ? null : onStart,
            icon: starting
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.play_arrow),
            label: Text(starting ? 'Starting...' : 'Start Tracking'),
          ),
        ],
      ),
    );
  }
}

class _ActiveDock extends StatelessWidget {
  const _ActiveDock({
    required this.trip,
    required this.stopping,
    required this.onOpenTracking,
    required this.onReport,
    required this.onStop,
  });

  final TripSession trip;
  final bool stopping;
  final VoidCallback onOpenTracking;
  final VoidCallback onReport;
  final VoidCallback onStop;

  @override
  Widget build(BuildContext context) {
    return _GlassPanel(
      padding: const EdgeInsets.all(12),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.verified_user, color: Color(0xFF166534)),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  '${_formatDuration(trip.remaining)} left',
                  style: const TextStyle(
                    color: Color(0xFF232C3A),
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: FilledButton.icon(
                  onPressed: onOpenTracking,
                  icon: const Icon(Icons.sensors),
                  label: const Text('Tracking'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onReport,
                  icon: const Icon(Icons.report_problem_outlined),
                  label: const Text('Report'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: stopping ? null : onStop,
            icon: stopping
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.stop_circle_outlined),
            label: Text(stopping ? 'Ending...' : 'End Trip'),
          ),
        ],
      ),
    );
  }

  String _formatDuration(Duration value) {
    final hours = value.inHours;
    final minutes = value.inMinutes.remainder(60);
    if (hours > 0) {
      return '${hours}h ${minutes}m';
    }
    return '${minutes}m';
  }
}

class _QuickLinks extends StatelessWidget {
  const _QuickLinks({required this.onOpen});

  final Future<void> Function(String path) onOpen;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        _FloatingIconButton(
          tooltip: 'Full website',
          icon: Icons.language,
          onPressed: () => onOpen('/'),
        ),
        const SizedBox(height: 8),
        _FloatingIconButton(
          tooltip: 'About',
          icon: Icons.info_outline,
          onPressed: () => onOpen('/about'),
        ),
        const SizedBox(height: 8),
        _FloatingIconButton(
          tooltip: 'Privacy',
          icon: Icons.privacy_tip_outlined,
          onPressed: () => onOpen('/privacy'),
        ),
      ],
    );
  }
}

class _FloatingIconButton extends StatelessWidget {
  const _FloatingIconButton({
    required this.tooltip,
    required this.icon,
    required this.onPressed,
  });

  final String tooltip;
  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: Material(
        color: Colors.white.withValues(alpha: 0.92),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: Color(0x1A232C3A)),
        ),
        elevation: 4,
        shadowColor: const Color(0x1F1B2330),
        child: IconButton(
          onPressed: onPressed,
          icon: Icon(icon),
          color: const Color(0xFF232C3A),
        ),
      ),
    );
  }
}

class _GlassPanel extends StatelessWidget {
  const _GlassPanel({required this.child, required this.padding});

  final Widget child;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.92),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0x1A232C3A)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x1F1B2330),
            blurRadius: 28,
            offset: Offset(0, 14),
          ),
        ],
      ),
      child: Padding(padding: padding, child: child),
    );
  }
}
