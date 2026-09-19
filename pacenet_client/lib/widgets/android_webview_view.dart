import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

class AndroidWebViewView extends StatefulWidget {
  final String initialUrl;
  final Function(String title)? onTitleChanged;
  final Function(bool canGoBack)? onCanGoBackChanged;

  const AndroidWebViewView({
    super.key,
    required this.initialUrl,
    this.onTitleChanged,
    this.onCanGoBackChanged,
  });

  @override
  State<AndroidWebViewView> createState() => AndroidWebViewViewState();
}

class AndroidWebViewViewState extends State<AndroidWebViewView> {
  late final WebViewController _controller;
  bool _isLoading = true;
  int _progress = 0;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _initController();
  }

  void _initController() {
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFF0F172A))
      ..setNavigationDelegate(
        NavigationDelegate(
          onProgress: (int progress) {
            if (mounted) {
              setState(() {
                _progress = progress;
                _isLoading = progress < 100;
              });
            }
          },
          onPageStarted: (String url) {
            if (mounted) {
              setState(() {
                _isLoading = true;
                _errorMessage = null;
              });
            }
          },
          onPageFinished: (String url) async {
            if (mounted) {
              setState(() {
                _isLoading = false;
              });
              final title = await _controller.getTitle();
              if (title != null && widget.onTitleChanged != null) {
                widget.onTitleChanged!(title);
              }
              final canGoBack = await _controller.canGoBack();
              if (widget.onCanGoBackChanged != null) {
                widget.onCanGoBackChanged!(canGoBack);
              }
            }
          },
          onWebResourceError: (WebResourceError error) {
            if (mounted && error.isForMainFrame == true) {
              setState(() {
                _errorMessage = 'Gagal memuat halaman: ${error.description} (Kode: ${error.errorCode})';
                _isLoading = false;
              });
            }
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.initialUrl));
  }

  Future<void> reload() async {
    setState(() {
      _errorMessage = null;
      _isLoading = true;
    });
    await _controller.reload();
  }

  Future<void> loadUrl(String url) async {
    setState(() {
      _errorMessage = null;
      _isLoading = true;
    });
    await _controller.loadRequest(Uri.parse(url));
  }

  Future<bool> handleBackPress() async {
    if (await _controller.canGoBack()) {
      await _controller.goBack();
      return true;
    }
    return false;
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        if (_errorMessage == null)
          WebViewWidget(controller: _controller)
        else
          Center(
            child: Padding(
              padding: const EdgeInsets.all(24.0),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.wifi_off_rounded, color: Colors.amber, size: 56),
                  const SizedBox(height: 16),
                  const Text(
                    'Koneksi ke Server Terputus',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _errorMessage!,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Colors.white70, fontSize: 13),
                  ),
                  const SizedBox(height: 24),
                  ElevatedButton.icon(
                    onPressed: reload,
                    icon: const Icon(Icons.refresh_rounded),
                    label: const Text('Coba Muat Ulang'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF2563EB),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                    ),
                  ),
                ],
              ),
            ),
          ),
        if (_isLoading && _progress > 0 && _progress < 100)
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: LinearProgressIndicator(
              value: _progress / 100.0,
              backgroundColor: Colors.transparent,
              valueColor: const AlwaysStoppedAnimation<Color>(Color(0xFF38BDF8)),
              minHeight: 3,
            ),
          ),
      ],
    );
  }
}
