import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_background_service/flutter_background_service.dart';

import '../models/telemetry_snapshot.dart';
import '../models/trip_session.dart';
import '../services/trip_repository.dart';
import 'report_violation_screen.dart';

class TrackingScreen extends StatefulWidget {
  const TrackingScreen({super.key, required this.repository});

  final TripRepository repository;

  @override
  State<TrackingScreen> createState() => _TrackingScreenState();
}

class _TrackingScreenState extends State<TrackingScreen> {
  StreamSubscription<Map<String, dynamic>?>? _subscription;
  TripSession? _trip;
  TelemetrySnapshot? _snapshot;
  int _pendingCount = 0;
  bool _loading = true;
  bool _stopping = false;

  @override
  void initState() {
    super.initState();
    _load();
    if (!kIsWeb && (Platform.isAndroid || Platform.isIOS)) {
      _subscription = FlutterBackgroundService()
          .on('telemetry')
          .listen(_handleServiceUpdate);
    }
  }

  @override
  void dispose() {
    _subscription?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    final trip = await widget.repository.loadActiveTrip();
    final snapshot = await widget.repository.loadLatestTelemetry();
    final pending = await widget.repository.pendingCount();
    if (!mounted) {
      return;
    }
    setState(() {
      _trip = trip;
      _snapshot = snapshot;
      _pendingCount = pending;
      _loading = false;
    });
  }

  void _handleServiceUpdate(Map<String, dynamic>? event) {
    if (event == null || !mounted) {
      return;
    }

    final tripJson = event['trip'];
    final latestJson = event['latest'];
    setState(() {
      if (tripJson is Map) {
        _trip = TripSession.fromJson(tripJson.cast<String, dynamic>());
      }
      if (latestJson is Map) {
        _snapshot = TelemetrySnapshot.fromJson(
          latestJson.cast<String, dynamic>(),
        );
        _pendingCount = _snapshot!.pendingCount;
      } else if (event['pending_count'] is int) {
        _pendingCount = event['pending_count'] as int;
      }
    });
  }

  Future<void> _stopTrip() async {
    setState(() => _stopping = true);
    await widget.repository.stopTrip();
    if (!mounted) {
      return;
    }
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final trip = _trip;
    if (trip == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Tracking')),
        body: const Center(child: Text('No active trip.')),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Tracking')),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
            children: [
              _TripHeader(trip: trip),
              const SizedBox(height: 12),
              _TelemetryGrid(snapshot: _snapshot, pendingCount: _pendingCount),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) =>
                        ReportViolationScreen(repository: widget.repository),
                  ),
                ),
                icon: const Icon(Icons.report_problem_outlined),
                label: const Text('Report Violation'),
              ),
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: _stopping ? null : _stopTrip,
                icon: _stopping
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.stop_circle_outlined),
                label: Text(_stopping ? 'Ending...' : 'End Trip'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TripHeader extends StatelessWidget {
  const _TripHeader({required this.trip});

  final TripSession trip;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              trip.routeName?.trim().isNotEmpty == true
                  ? trip.routeName!
                  : trip.publicReference,
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 8),
            Text(
              trip.publicReference,
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: const Color(0xFF667085)),
            ),
            const SizedBox(height: 16),
            LinearProgressIndicator(
              value: _progress(trip),
              minHeight: 8,
              borderRadius: BorderRadius.circular(999),
            ),
            const SizedBox(height: 8),
            Text(
              '${_formatDuration(trip.elapsed)} elapsed, ${_formatDuration(trip.remaining)} left',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }

  double _progress(TripSession trip) {
    final total = trip.expiresAt.difference(trip.startedAt).inSeconds;
    if (total <= 0) {
      return 0;
    }
    return (trip.elapsed.inSeconds / total).clamp(0, 1).toDouble();
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

class _TelemetryGrid extends StatelessWidget {
  const _TelemetryGrid({required this.snapshot, required this.pendingCount});

  final TelemetrySnapshot? snapshot;
  final int pendingCount;

  @override
  Widget build(BuildContext context) {
    final rows = <_TelemetryItem>[
      _TelemetryItem(
        icon: Icons.speed,
        label: 'Speed',
        value: snapshot == null
            ? '--'
            : '${snapshot!.speedKmh.toStringAsFixed(0)} km/h',
      ),
      _TelemetryItem(
        icon: Icons.my_location,
        label: 'Accuracy',
        value: snapshot?.accuracyMeters == null
            ? '--'
            : '${snapshot!.accuracyMeters!.toStringAsFixed(0)} m',
      ),
      _TelemetryItem(
        icon: Icons.battery_5_bar,
        label: 'Battery',
        value: snapshot?.batteryLevel == null
            ? '--'
            : '${snapshot!.batteryLevel}%',
      ),
      _TelemetryItem(
        icon: Icons.sync,
        label: 'Pending',
        value: pendingCount.toString(),
      ),
      _TelemetryItem(
        icon: Icons.network_cell,
        label: 'Network',
        value: snapshot?.networkType?.isNotEmpty == true
            ? snapshot!.networkType!
            : '--',
      ),
      _TelemetryItem(
        icon: Icons.schedule,
        label: 'Updated',
        value: snapshot == null
            ? '--'
            : TimeOfDay.fromDateTime(
                snapshot!.recordedAt.toLocal(),
              ).format(context),
      ),
    ];

    return GridView.builder(
      itemCount: rows.length,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        childAspectRatio: 1.62,
      ),
      itemBuilder: (context, index) => _TelemetryTile(item: rows[index]),
    );
  }
}

class _TelemetryItem {
  const _TelemetryItem({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;
}

class _TelemetryTile extends StatelessWidget {
  const _TelemetryTile({required this.item});

  final _TelemetryItem item;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(item.icon, color: Theme.of(context).colorScheme.primary),
            const SizedBox(height: 8),
            Text(
              item.label,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: const Color(0xFF667085)),
            ),
            const SizedBox(height: 2),
            Text(
              item.value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ],
        ),
      ),
    );
  }
}
