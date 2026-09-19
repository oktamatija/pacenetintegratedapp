import 'dart:async';
import 'package:flutter/material.dart';
import 'package:webview_windows/webview_windows.dart';

class WindowsWebViewView extends StatefulWidget {
  final String initialUrl;
  final Function(String title)? onTitleChanged;
  final VoidCallback? onReloadRequested;

  const WindowsWebViewView({
    super.key,
    required this.initialUrl,
    this.onTitleChanged,
    this.onReloadRequested,
  });

  @override
  State<WindowsWebViewView> createState() => WindowsWebViewViewState();
}

class WindowsWebViewViewState extends State<WindowsWebViewView> {
  final _controller = WebviewController();
  bool _isInitialized = false;
  String? _errorMessage;
  final List<StreamSubscription> _subscriptions = [];

  @override
  void initState() {
    super.initState();
    _initPlatformState();
  }

  Future<void> _initPlatformState() async {
    try {
      await _controller.initialize();
      _subscriptions.add(_controller.url.listen((url) {
        // Track URL change
      }));
      _subscriptions.add(_controller.title.listen((title) {
        if (widget.onTitleChanged != null && title.isNotEmpty) {
          widget.onTitleChanged!(title);
        }
      }));

      await _controller.setBackgroundColor(const Color(0xFF0F172A));
      await _controller.setPopupWindowPolicy(WebviewPopupWindowPolicy.deny);
      await _controller.loadUrl(widget.initialUrl);

      if (!mounted) return;
      setState(() {
        _isInitialized = true;
        _errorMessage = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = 'Gagal memuat WebView Windows: $e\nPastikan Microsoft Edge WebView2 Runtime terpasang.';
      });
    }
  }

  Future<void> reload() async {
    if (_isInitialized) {
      await _controller.reload();
    }
  }

  Future<void> loadUrl(String url) async {
    if (_isInitialized) {
      await _controller.loadUrl(url);
    }
  }

  @override
  void dispose() {
    for (var sub in _subscriptions) {
      sub.cancel();
    }
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_errorMessage != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, color: Colors.redAccent, size: 48),
              const SizedBox(height: 16),
              Text(
                _errorMessage!,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white70, fontSize: 14),
              ),
              const SizedBox(height: 16),
              ElevatedButton.icon(
                onPressed: () {
                  setState(() {
                    _errorMessage = null;
                  });
                  _initPlatformState();
                },
                icon: const Icon(Icons.refresh),
                label: const Text('Coba Lagi'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF2563EB),
                  foregroundColor: Colors.white,
                ),
              ),
            ],
          ),
        ),
      );
    }

    if (!_isInitialized) {
      return const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircularProgressIndicator(color: Color(0xFF38BDF8)),
            SizedBox(height: 16),
            Text(
              'Menyiapkan Edge WebView2 Engine...',
              style: TextStyle(color: Colors.white70, fontSize: 13),
            ),
          ],
        ),
      );
    }

    return Webview(
      _controller,
      permissionRequested: _onPermissionRequested,
    );
  }

  Future<WebviewPermissionDecision> _onPermissionRequested(
      String url, WebviewPermissionKind kind, bool isUserInitiated) async {
    final decision = await showDialog<WebviewPermissionDecision>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        backgroundColor: const Color(0xFF1E293B),
        title: const Text('Izin Akses Browser', style: TextStyle(color: Colors.white)),
        content: Text(
          'Halaman $url meminta izin: ${kind.name}',
          style: const TextStyle(color: Colors.white70),
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.pop(context, WebviewPermissionDecision.deny),
            child: const Text('Tolak', style: TextStyle(color: Colors.grey)),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, WebviewPermissionDecision.allow),
            child: const Text('Izinkan', style: TextStyle(color: Color(0xFF38BDF8))),
          ),
        ],
      ),
    );

    return decision ?? WebviewPermissionDecision.none;
  }
}
