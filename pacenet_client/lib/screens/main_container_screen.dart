import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../widgets/universal_webview.dart';
import 'server_selector_screen.dart';

class MainContainerScreen extends StatefulWidget {
  final String serverUrl;
  final String serverName;

  const MainContainerScreen({
    super.key,
    required this.serverUrl,
    required this.serverName,
  });

  @override
  State<MainContainerScreen> createState() => _MainContainerScreenState();
}

class _MainContainerScreenState extends State<MainContainerScreen> {
  final GlobalKey<UniversalWebViewState> _webKey = GlobalKey<UniversalWebViewState>();
  String _pageTitle = 'PaceNet Integrated App';
  bool _isFullscreen = false;

  void _toggleFullscreen() {
    setState(() {
      _isFullscreen = !_isFullscreen;
    });
    if (_isFullscreen) {
      SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
    } else {
      SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    }
  }

  void _switchServer() {
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (context) => const ServerSelectorScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) async {
        if (didPop) return;
        final handled = await _webKey.currentState?.handleBackPress() ?? false;
        if (!handled && mounted) {
          final shouldExit = await showDialog<bool>(
            context: context,
            builder: (context) => AlertDialog(
              backgroundColor: const Color(0xFF1E293B),
              title: const Text('Keluar dari Aplikasi?', style: TextStyle(color: Colors.white)),
              content: const Text(
                'Apakah Anda yakin ingin menutup aplikasi PaceNet Pro?',
                style: TextStyle(color: Colors.white70),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context, false),
                  child: const Text('Batal', style: TextStyle(color: Colors.grey)),
                ),
                TextButton(
                  onPressed: () => Navigator.pop(context, true),
                  child: const Text('Keluar', style: TextStyle(color: Colors.redAccent)),
                ),
              ],
            ),
          );
          if (shouldExit == true && mounted) {
            SystemNavigator.pop();
          }
        }
      },
      child: Scaffold(
        backgroundColor: const Color(0xFF0F172A),
        appBar: _isFullscreen
            ? null
            : PreferredSize(
                preferredSize: const Size.fromHeight(52),
                child: Container(
                  decoration: const BoxDecoration(
                    color: Color(0xFF0B0F19),
                    border: Border(
                      bottom: BorderSide(color: Color(0xFF1E293B), width: 1),
                    ),
                  ),
                  child: SafeArea(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      child: Row(
                        children: [
                          // Brand icon & title
                          Container(
                            padding: const EdgeInsets.all(6),
                            decoration: BoxDecoration(
                              color: const Color(0xFF2563EB).withValues(alpha: 0.2),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Icon(
                              Icons.wifi_tethering_rounded,
                              color: Color(0xFF38BDF8),
                              size: 18,
                            ),
                          ),
                          const SizedBox(width: 8),
                          const Text(
                            'PaceNet Pro',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                              fontSize: 14,
                              letterSpacing: 0.5,
                            ),
                          ),
                          const SizedBox(width: 10),

                          // Server badge
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: const Color(0xFF1E293B),
                              borderRadius: BorderRadius.circular(6),
                              border: Border.all(color: const Color(0xFF334155)),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Container(
                                  width: 7,
                                  height: 7,
                                  decoration: const BoxDecoration(
                                    color: Color(0xFF10B981),
                                    shape: BoxShape.circle,
                                  ),
                                ),
                                const SizedBox(width: 6),
                                ConstrainedBox(
                                  constraints: const BoxConstraints(maxWidth: 160),
                                  child: Text(
                                    widget.serverName,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: Color(0xFF94A3B8),
                                      fontSize: 11,
                                      fontWeight: FontWeight.w500,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),

                          const Spacer(),

                          // Reload action
                          IconButton(
                            icon: const Icon(Icons.refresh_rounded, size: 20, color: Color(0xFF94A3B8)),
                            tooltip: 'Muat Ulang Halaman (F5)',
                            onPressed: () => _webKey.currentState?.reload(),
                          ),

                          // Fullscreen / Kiosk action
                          IconButton(
                            icon: Icon(
                              _isFullscreen ? Icons.fullscreen_exit_rounded : Icons.fullscreen_rounded,
                              size: 20,
                              color: const Color(0xFF94A3B8),
                            ),
                            tooltip: 'Mode Kiosk / Layar Penuh',
                            onPressed: _toggleFullscreen,
                          ),

                          // Switch server action
                          TextButton.icon(
                            onPressed: _switchServer,
                            icon: const Icon(Icons.swap_horiz_rounded, size: 16, color: Color(0xFF38BDF8)),
                            label: const Text(
                              'Ganti Server',
                              style: TextStyle(color: Color(0xFF38BDF8), fontSize: 12),
                            ),
                            style: TextButton.styleFrom(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              backgroundColor: const Color(0xFF38BDF8).withValues(alpha: 0.1),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
        body: Stack(
          children: [
            UniversalWebView(
              key: _webKey,
              url: widget.serverUrl,
              onTitleChanged: (title) {
                if (mounted) {
                  setState(() {
                    _pageTitle = title;
                  });
                }
              },
            ),

            // Floating Restore Bar button when in Fullscreen mode
            if (_isFullscreen)
              Positioned(
                top: 12,
                right: 12,
                child: Opacity(
                  opacity: 0.6,
                  child: FloatingActionButton.small(
                    backgroundColor: const Color(0xFF1E293B),
                    foregroundColor: Colors.white,
                    tooltip: 'Keluar Layar Penuh',
                    onPressed: _toggleFullscreen,
                    child: const Icon(Icons.fullscreen_exit_rounded, size: 18),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
