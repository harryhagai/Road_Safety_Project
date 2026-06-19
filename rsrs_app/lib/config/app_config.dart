class AppConfig {
  static const apiBaseUrl = String.fromEnvironment(
    'RSRS_API_BASE_URL',
    defaultValue: 'https://davidson-cons-necessary-aspect.trycloudflare.com',
  );

  static Uri apiUri(String path) => _join(apiBaseUrl, path);

  static Uri webUri(String path) => _join(apiBaseUrl, path);

  static Uri _join(String baseUrl, String path) {
    final cleanBase = baseUrl.endsWith('/')
        ? baseUrl.substring(0, baseUrl.length - 1)
        : baseUrl;
    final cleanPath = path.startsWith('/') ? path : '/$path';

    return Uri.parse('$cleanBase$cleanPath');
  }
}
