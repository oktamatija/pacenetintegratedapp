import 'dart:io' show Platform;
import 'package:flutter/material.dart';
import 'windows_webview_view.dart';
import 'android_webview_view.dart';

class UniversalWebView extends StatefulWidget {
  final String url;
  final Function(String title)? onTitleChanged;
  final VoidCallback? onSwitchServer;

  const UniversalWebView({
    super.key,
    required this.url,
    this.onTitleChanged,
    this.onSwitchServer,
  });

  @override
  State<UniversalWebView> createState() => UniversalWebViewState();
}

class UniversalWebViewState extends State<UniversalWebView> {
  final GlobalKey<WindowsWebViewViewState> _windowsKey = GlobalKey<WindowsWebViewViewState>();
  final GlobalKey<AndroidWebViewViewState> _androidKey = GlobalKey<AndroidWebViewViewState>();

  Future<void> reload() async {
    if (Platform.isWindows) {
      await _windowsKey.currentState?.reload();
    } else if (Platform.isAndroid) {
      await _androidKey.currentState?.reload();
    }
  }

  Future<void> loadUrl(String url) async {
    if (Platform.isWindows) {
      await _windowsKey.currentState?.loadUrl(url);
    } else if (Platform.isAndroid) {
      await _androidKey.currentState?.loadUrl(url);
    }
  }

  Future<bool> handleBackPress() async {
    if (Platform.isAndroid) {
      return await _androidKey.currentState?.handleBackPress() ?? false;
    }
    return false;
  }

  @override
  Widget build(BuildContext context) {
    if (Platform.isWindows) {
      return WindowsWebViewView(
        key: _windowsKey,
        initialUrl: widget.url,
        onTitleChanged: widget.onTitleChanged,
      );
    } else if (Platform.isAndroid) {
      return AndroidWebViewView(
        key: _androidKey,
        initialUrl: widget.url,
        onTitleChanged: widget.onTitleChanged,
      );
    } else {
      return Center(
        child: Text(
          'Platform ${Platform.operatingSystem} belum didukung untuk WebView.',
          style: const TextStyle(color: Colors.white70),
        ),
      );
    }
  }
}
