import 'package:flutter/material.dart';
import '../models/server_profile.dart';
import '../services/storage_service.dart';
import 'main_container_screen.dart';

class ServerSelectorScreen extends StatefulWidget {
  const ServerSelectorScreen({super.key});

  @override
  State<ServerSelectorScreen> createState() => _ServerSelectorScreenState();
}

class _ServerSelectorScreenState extends State<ServerSelectorScreen> {
  String _selectedUrl = 'https://hy0045.my.id/app/';
  String _selectedName = 'VPS Utama (hy0045)';
  bool _autoConnect = true;
  bool _isCustomSelected = false;
  final TextEditingController _customUrlController = TextEditingController(text: 'http://192.168.88.1/');
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadSavedPreferences();
  }

  Future<void> _loadSavedPreferences() async {
    final savedUrl = await StorageService.getSelectedServerUrl();
    final savedName = await StorageService.getSelectedServerName();
    final customUrl = await StorageService.getCustomServerUrl();
    final autoConnect = await StorageService.getAutoConnect();

    bool isCustom = true;
    for (var p in ServerProfile.defaultProfiles) {
      if (p.url == savedUrl) {
        isCustom = false;
        break;
      }
    }

    if (mounted) {
      setState(() {
        _selectedUrl = savedUrl;
        _selectedName = savedName;
        _customUrlController.text = customUrl;
        _autoConnect = autoConnect;
        _isCustomSelected = isCustom;
        _isLoading = false;
      });
    }
  }

  Future<void> _handleConnect() async {
    String finalUrl = _selectedUrl;
    String finalName = _selectedName;

    if (_isCustomSelected) {
      String rawCustom = _customUrlController.text.trim();
      if (rawCustom.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Harap masukkan alamat URL server yang valid!')),
        );
        return;
      }
      if (!rawCustom.startsWith('http://') && !rawCustom.startsWith('https://')) {
        rawCustom = 'http://$rawCustom';
      }
      if (!rawCustom.endsWith('/')) {
        rawCustom = '$rawCustom/';
      }
      finalUrl = rawCustom;
      finalName = 'Custom Server ($rawCustom)';
      await StorageService.setCustomServerUrl(rawCustom);
    }

    await StorageService.setSelectedServerUrl(finalUrl);
    await StorageService.setSelectedServerName(finalName);
    await StorageService.setAutoConnect(_autoConnect);

    if (!mounted) return;

    Navigator.pushReplacement(
      context,
      MaterialPageRoute(
        builder: (context) => MainContainerScreen(
          serverUrl: finalUrl,
          serverName: finalName,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        backgroundColor: Color(0xFF0F172A),
        body: Center(
          child: CircularProgressIndicator(color: Color(0xFF38BDF8)),
        ),
      );
    }

    return Scaffold(
      backgroundColor: const Color(0xFF0B0F19),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 580),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // App Branding Header
                  Center(
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        gradient: const LinearGradient(
                          colors: [Color(0xFF2563EB), Color(0xFF06B6D4)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: const Color(0xFF2563EB).withValues(alpha: 0.35),
                            blurRadius: 28,
                            spreadRadius: 4,
                          ),
                        ],
                      ),
                      child: const Icon(
                        Icons.wifi_tethering_rounded,
                        size: 48,
                        color: Colors.white,
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'PACENET INTEGRATED PRO',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 1.5,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Pilih server gateway untuk terhubung ke Dashboard & Voucher System',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 13,
                      color: Color(0xFF94A3B8),
                    ),
                  ),
                  const SizedBox(height: 32),

                  // Preset Server Profiles
                  ...ServerProfile.defaultProfiles.map((profile) {
                    final isSelected = !_isCustomSelected && _selectedUrl == profile.url;
                    return _buildServerCard(
                      profile: profile,
                      isSelected: isSelected,
                      onTap: () {
                        setState(() {
                          _isCustomSelected = false;
                          _selectedUrl = profile.url;
                          _selectedName = profile.name;
                        });
                      },
                    );
                  }),

                  // Custom Server Profile Card
                  _buildCustomServerCard(),

                  const SizedBox(height: 20),

                  // Auto connect toggle
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    decoration: BoxDecoration(
                      color: const Color(0xFF1E293B).withValues(alpha: 0.5),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFF334155)),
                    ),
                    child: Row(
                      children: [
                        Checkbox(
                          value: _autoConnect,
                          activeColor: const Color(0xFF2563EB),
                          onChanged: (val) {
                            setState(() {
                              _autoConnect = val ?? true;
                            });
                          },
                        ),
                        const Expanded(
                          child: Text(
                            'Sambungkan otomatis saat aplikasi dibuka berikutnya',
                            style: TextStyle(color: Color(0xFFE2E8F0), fontSize: 12),
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 24),

                  // Connect Button
                  ElevatedButton(
                    onPressed: _handleConnect,
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 18),
                      backgroundColor: const Color(0xFF2563EB),
                      foregroundColor: Colors.white,
                      elevation: 8,
                      shadowColor: const Color(0xFF2563EB).withValues(alpha: 0.5),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    child: const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.login_rounded, size: 20),
                        SizedBox(width: 10),
                        Text(
                          'MASUK KE SISTEM PACENET',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.bold,
                            letterSpacing: 0.8,
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 24),

                  // App version info
                  const Center(
                    child: Text(
                      'PaceNet Integrated App v2.5 • Multiplatform Android & Windows Desktop',
                      style: TextStyle(
                        fontSize: 11,
                        color: Color(0xFF64748B),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildServerCard({
    required ServerProfile profile,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    final isPrimary = profile.iconType == 'primary';
    final accentColor = isPrimary ? const Color(0xFF10B981) : const Color(0xFF38BDF8);

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: isSelected
                  ? const Color(0xFF1E293B)
                  : const Color(0xFF131D2E).withValues(alpha: 0.8),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: isSelected ? accentColor : const Color(0xFF1E293B),
                width: isSelected ? 2 : 1,
              ),
              boxShadow: isSelected
                  ? [
                      BoxShadow(
                        color: accentColor.withValues(alpha: 0.25),
                        blurRadius: 16,
                        spreadRadius: 1,
                      )
                    ]
                  : [],
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: accentColor.withValues(alpha: 0.15),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    isPrimary ? Icons.dns_rounded : Icons.shield_rounded,
                    color: accentColor,
                    size: 26,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Text(
                            profile.name,
                            style: TextStyle(
                              color: isSelected ? Colors.white : const Color(0xFFE2E8F0),
                              fontSize: 15,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(
                              color: accentColor.withValues(alpha: 0.2),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text(
                              isPrimary ? 'ONLINE' : 'BACKUP',
                              style: TextStyle(
                                color: accentColor,
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        profile.url,
                        style: const TextStyle(
                          color: Color(0xFF38BDF8),
                          fontSize: 12,
                          fontFamily: 'monospace',
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        profile.description,
                        style: const TextStyle(
                          color: Color(0xFF94A3B8),
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
                Radio<bool>(
                  value: true,
                  groupValue: isSelected,
                  activeColor: accentColor,
                  onChanged: (val) => onTap(),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildCustomServerCard() {
    final isSelected = _isCustomSelected;
    const accentColor = Color(0xFFA855F7);

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () {
            setState(() {
              _isCustomSelected = true;
            });
          },
          borderRadius: BorderRadius.circular(16),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: isSelected
                  ? const Color(0xFF1E293B)
                  : const Color(0xFF131D2E).withValues(alpha: 0.8),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: isSelected ? accentColor : const Color(0xFF1E293B),
                width: isSelected ? 2 : 1,
              ),
              boxShadow: isSelected
                  ? [
                      BoxShadow(
                        color: accentColor.withValues(alpha: 0.25),
                        blurRadius: 16,
                        spreadRadius: 1,
                      )
                    ]
                  : [],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: accentColor.withValues(alpha: 0.15),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.settings_ethernet_rounded,
                        color: accentColor,
                        size: 26,
                      ),
                    ),
                    const SizedBox(width: 16),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Custom Server / IP Router Lokal',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 15,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          SizedBox(height: 2),
                          Text(
                            'Sambungkan ke alamat IP Router lokal atau VPS alternatif',
                            style: TextStyle(
                              color: Color(0xFF94A3B8),
                              fontSize: 11,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Radio<bool>(
                      value: true,
                      groupValue: isSelected,
                      activeColor: accentColor,
                      onChanged: (val) {
                        setState(() {
                          _isCustomSelected = true;
                        });
                      },
                    ),
                  ],
                ),
                if (isSelected) ...[
                  const SizedBox(height: 14),
                  TextField(
                    controller: _customUrlController,
                    style: const TextStyle(color: Colors.white, fontSize: 13, fontFamily: 'monospace'),
                    decoration: InputDecoration(
                      filled: true,
                      fillColor: const Color(0xFF0F172A),
                      hintText: 'http://192.168.88.1/ atau https://domain.anda/app/',
                      hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                      prefixIcon: const Icon(Icons.link_rounded, color: Color(0xFFA855F7), size: 20),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(10),
                        borderSide: const BorderSide(color: Color(0xFF334155)),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(10),
                        borderSide: const BorderSide(color: Color(0xFF334155)),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(10),
                        borderSide: const BorderSide(color: Color(0xFFA855F7), width: 1.5),
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
