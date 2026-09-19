class ServerProfile {
  final String id;
  final String name;
  final String url;
  final String description;
  final String iconType; // 'primary', 'backup', 'custom'

  const ServerProfile({
    required this.id,
    required this.name,
    required this.url,
    required this.description,
    required this.iconType,
  });

  static const List<ServerProfile> defaultProfiles = [
    ServerProfile(
      id: 'vps_primary',
      name: 'VPS Utama (hy0045)',
      url: 'https://hy0045.my.id/app/',
      description: 'Server Produksi Utama Cloud & Database PostgreSQL',
      iconType: 'primary',
    ),
    ServerProfile(
      id: 'vps_backup',
      name: 'VPS Cadangan (hi1271)',
      url: 'https://hi1271.my.id/app/',
      description: 'Server Replikasi & Failover Cadangan',
      iconType: 'backup',
    ),
  ];

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'url': url,
    'description': description,
    'iconType': iconType,
  };

  factory ServerProfile.fromJson(Map<String, dynamic> json) => ServerProfile(
    id: json['id'] as String? ?? 'custom',
    name: json['name'] as String? ?? 'Custom Server',
    url: json['url'] as String? ?? '',
    description: json['description'] as String? ?? '',
    iconType: json['iconType'] as String? ?? 'custom',
  );
}
