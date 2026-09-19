import 'package:shared_preferences/shared_preferences.dart';

class StorageService {
  static const String _keySelectedUrl = 'selected_server_url';
  static const String _keySelectedName = 'selected_server_name';
  static const String _keyCustomUrl = 'custom_server_url';
  static const String _keyAutoConnect = 'auto_connect';

  static Future<String> getSelectedServerUrl() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_keySelectedUrl) ?? 'https://hy0045.my.id/app/';
  }

  static Future<void> setSelectedServerUrl(String url) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keySelectedUrl, url);
  }

  static Future<String> getSelectedServerName() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_keySelectedName) ?? 'VPS Utama (hy0045)';
  }

  static Future<void> setSelectedServerName(String name) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keySelectedName, name);
  }

  static Future<String> getCustomServerUrl() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_keyCustomUrl) ?? 'http://192.168.88.1/';
  }

  static Future<void> setCustomServerUrl(String url) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyCustomUrl, url);
  }

  static Future<bool> getAutoConnect() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(_keyAutoConnect) ?? true;
  }

  static Future<void> setAutoConnect(bool value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_keyAutoConnect, value);
  }
}
