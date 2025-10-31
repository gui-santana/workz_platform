// packages/workz_sdk/lib/src/web_interop_stub.dart
// Stub implementation for non-web platforms

/// Stub implementation of WorkzSDKWebInterop for non-web platforms
class WorkzSDKWebInterop {
  static Future<bool> init({String? baseUrl, String mode = 'embed'}) async {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static String? getToken() {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Map<String, dynamic>? getUser() {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Map<String, dynamic>? getContext() {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Map<String, dynamic>? getPlatform() {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static bool isReady() {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static void addEventListener(String type, Function callback) {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static void removeEventListener(String type, Function callback) {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static void emit(String type, Map<String, dynamic>? payload) {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Future<Map<String, dynamic>> apiGet(String path) async {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Future<Map<String, dynamic>> apiPost(String path, Map<String, dynamic>? body) async {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Future<Map<String, dynamic>> apiPut(String path, Map<String, dynamic>? body) async {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Future<Map<String, dynamic>> apiDelete(String path) async {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Future<Map<String, dynamic>> kvSet(String key, String value, {int? ttl}) async {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Future<Map<String, dynamic>> kvGet(String key) async {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Future<Map<String, dynamic>> kvDelete(String key) async {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Future<Map<String, dynamic>> kvList() async {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Future<Map<String, dynamic>> docsSave(String id, Map<String, dynamic> document) async {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Future<Map<String, dynamic>> docsGet(String id) async {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Future<Map<String, dynamic>> docsDelete(String id) async {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Future<Map<String, dynamic>> docsList() async {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
  
  static Future<Map<String, dynamic>> docsQuery(Map<String, dynamic> query) async {
    throw UnsupportedError('Web interop is only available on web platforms');
  }
}