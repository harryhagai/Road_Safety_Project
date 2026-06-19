import 'package:flutter/material.dart';

import '../services/trip_repository.dart';

class ReportViolationScreen extends StatefulWidget {
  const ReportViolationScreen({super.key, required this.repository});

  final TripRepository repository;

  @override
  State<ReportViolationScreen> createState() => _ReportViolationScreenState();
}

class _ReportViolationScreenState extends State<ReportViolationScreen> {
  final _formKey = GlobalKey<FormState>();
  final _descriptionController = TextEditingController();
  String _type = 'overspeeding';
  bool _submitting = false;

  static const _types = <DropdownMenuItem<String>>[
    DropdownMenuItem(value: 'overspeeding', child: Text('Overspeeding')),
    DropdownMenuItem(
      value: 'reckless_driving',
      child: Text('Reckless driving'),
    ),
    DropdownMenuItem(
      value: 'unsafe_overtaking',
      child: Text('Unsafe overtaking'),
    ),
    DropdownMenuItem(value: 'overloading', child: Text('Overloading')),
    DropdownMenuItem(
      value: 'traffic_obstruction',
      child: Text('Traffic obstruction'),
    ),
    DropdownMenuItem(value: 'road_damage', child: Text('Road damage')),
    DropdownMenuItem(value: 'other', child: Text('Other')),
  ];

  @override
  void dispose() {
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() => _submitting = true);
    try {
      final sent = await widget.repository.submitViolation(
        type: _type,
        description: _descriptionController.text,
      );
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            sent
                ? 'Violation report sent.'
                : 'Report saved locally and will retry when online.',
          ),
        ),
      );
      Navigator.of(context).pop();
    } on Object {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Report saved locally and will retry when online.'),
        ),
      );
      Navigator.of(context).pop();
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Report Violation')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      DropdownButtonFormField<String>(
                        initialValue: _type,
                        items: _types,
                        onChanged: (value) =>
                            setState(() => _type = value ?? _type),
                        decoration: const InputDecoration(
                          labelText: 'Violation type',
                          prefixIcon: Icon(Icons.category_outlined),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _descriptionController,
                        minLines: 4,
                        maxLines: 7,
                        maxLength: 5000,
                        decoration: const InputDecoration(
                          labelText: 'Description',
                          alignLabelWithHint: true,
                          prefixIcon: Icon(Icons.notes),
                        ),
                        validator: (value) {
                          if (_type == 'other' &&
                              (value == null || value.trim().length < 10)) {
                            return 'Add a short description for other reports.';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      FilledButton.icon(
                        onPressed: _submitting ? null : _submit,
                        icon: _submitting
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Icon(Icons.send),
                        label: Text(_submitting ? 'Sending...' : 'Send Report'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
