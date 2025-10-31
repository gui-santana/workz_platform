import 'dart:convert';
import 'package:workz_sdk/src/api_client.dart';
import 'package:workz_sdk/src/services/kv_storage.dart';
import 'package:workz_sdk/src/services/docs_storage.dart';
import 'package:workz_sdk/src/services/blob_storage.dart';
import 'package:workz_sdk/src/native_bridge.dart';
import 'web_interop_stub.dart' if (dart.library.html) 'web_interop.dart';

/// Main WorkzSDK class with unified API for both web and native platforms
///
/// Must be initialized at app startup with [init].
class WorkzSDK {
  // Singleton pattern to ensure single SDK instance
  static final WorkzSDK _instance = WorkzSDK._internal();
  factory WorkzSDK() => _instance;
  WorkzSDK._internal();

  ApiClient? _apiClient;
  late final KVStorage kv;
  late final DocsStorage docs;
  late final BlobStorage blobs;

  bool _isInitialized = false;
  bool _isWebPlatform = false;
  String? _currentToken;
  Map<String, dynamic>? _currentUser;

  /// Initialize the SDK with the necessary configuration
  ///
  /// For web platforms, this will use JavaScript interop.
  /// For native platforms, this will use direct API calls.
  ///
  /// [apiUrl] is the base URL of your Workz! instance (e.g., https://api.yourcompany.com)
  /// [token] is the app access token provided by the platform
  /// [mode] is the initialization mode ('embed' for iframes, 'standalone' for direct access)
  Future<bool> initialize({
    String? apiUrl,
    String? token,
    String mode = 'embed',
    bool? debug,
  }) async {
    if (_isInitialized) {
      print("WorkzSDK already initialized.");
      return true;
    }

    // Detect if we're running on web platform
    _isWebPlatform = _isRunningOnWeb();

    if (_isWebPlatform) {
      // Use JavaScript interop for web platforms
      final success = await WorkzSDKWebInterop.init(
        baseUrl: apiUrl,
        mode: mode,
      );
      
      if (!success) {
        print("Failed to initialize WorkzSDK via JavaScript interop");
        return false;
      }
      
      // Initialize services using web interop
      kv = KVStorage(); // No Dio client for web interop
      docs = DocsStorage();
      blobs = BlobStorage();
      
      // Get user info from JavaScript SDK
      _currentToken = WorkzSDKWebInterop.getToken();
      _currentUser = WorkzSDKWebInterop.getUser();
      
    } else {
      // Use native bridge for mobile platforms
      if (apiUrl == null || token == null) {
        throw ArgumentError('apiUrl and token are required for native platforms');
      }
      
      final success = await WorkzSDKNativeBridge.init(
        apiUrl: apiUrl,
        token: token,
      );
      
      if (!success) {
        print("Failed to initialize WorkzSDK via native bridge");
        return false;
      }
      
      // Initialize services using native bridge
      kv = KVStorage(); // No Dio client for native bridge
      docs = DocsStorage();
      blobs = BlobStorage();
      
      _currentToken = token;
      _currentUser = await WorkzSDKNativeBridge.getUser();
    }

    _isInitialized = true;
    print("WorkzSDK initialized successfully!");
    return true;
  }

  // ---- Compatibility helpers (static) ----
  // Many samples call WorkzSDK.init(...) and WorkzSDK.storage.set(...)
  // Provide static shims that delegate to the singleton.
  static Future<bool> init({ String? apiUrl, String? token, String mode = 'embed', bool? debug }) {
    return WorkzSDK().initialize(apiUrl: apiUrl, token: token, mode: mode, debug: debug);
  }
  static _StorageFacade get storage => _StorageFacade();

  /// Check if running on web platform
  bool _isRunningOnWeb() {
    // Use conditional imports to detect web platform
    return const bool.fromEnvironment('dart.library.html', defaultValue: false);
  }

  /// Get current authentication token
  String? getToken() {
    if (_isWebPlatform) {
      return WorkzSDKWebInterop.getToken();
    }
    return _currentToken;
  }

  /// Get current user information
  Map<String, dynamic>? getUser() {
    if (_isWebPlatform) {
      return WorkzSDKWebInterop.getUser();
    }
    return _currentUser;
  }

  /// Get current context
  Map<String, dynamic>? getContext() {
    if (_isWebPlatform) {
      return WorkzSDKWebInterop.getContext();
    }
    return null; // Context not available in native mode
  }

  /// Get platform information
  Map<String, dynamic>? getPlatform() {
    if (_isWebPlatform) {
      return WorkzSDKWebInterop.getPlatform();
    }
    return {
      'type': 'flutter-native',
      'isWeb': false,
      'isMobile': true,
    };
  }

  /// Check if SDK is ready
  bool isReady() {
    if (_isWebPlatform) {
      return WorkzSDKWebInterop.isReady();
    }
    return _isInitialized;
  }

  /// Add event listener (web only)
  void addEventListener(String type, Function callback) {
    if (_isWebPlatform) {
      WorkzSDKWebInterop.addEventListener(type, callback);
    }
  }

  /// Remove event listener (web only)
  void removeEventListener(String type, Function callback) {
    if (_isWebPlatform) {
      WorkzSDKWebInterop.removeEventListener(type, callback);
    }
  }

  /// Emit event (web only)
  void emit(String type, Map<String, dynamic>? payload) {
    if (_isWebPlatform) {
      WorkzSDKWebInterop.emit(type, payload);
    }
  }

  /// API methods
  Future<Map<String, dynamic>> apiGet(String path) async {
    if (_isWebPlatform) {
      return await WorkzSDKWebInterop.apiGet(path);
    } else {
      return await WorkzSDKNativeBridge.apiGet(path);
    }
  }

  Future<Map<String, dynamic>> apiPost(String path, Map<String, dynamic>? body) async {
    if (_isWebPlatform) {
      return await WorkzSDKWebInterop.apiPost(path, body);
    } else {
      return await WorkzSDKNativeBridge.apiPost(path, body);
    }
  }

  Future<Map<String, dynamic>> apiPut(String path, Map<String, dynamic>? body) async {
    if (_isWebPlatform) {
      return await WorkzSDKWebInterop.apiPut(path, body);
    } else {
      return await WorkzSDKNativeBridge.apiPut(path, body);
    }
  }

  Future<Map<String, dynamic>> apiDelete(String path) async {
    if (_isWebPlatform) {
      return await WorkzSDKWebInterop.apiDelete(path);
    } else {
      return await WorkzSDKNativeBridge.apiDelete(path);
    }
  }
}

/// Facade to mimic WorkzSDK.storage used in examples
class _StorageFacade {
  KVStorage get _kv => WorkzSDK().kv;

  Future<void> set(String key, dynamic value, {int? ttl}) async {
    final str = value is String ? value : jsonEncode(value);
    await _kv.set(key, str, ttl: ttl);
  }
  Future<String?> get(String key) => _kv.get(key);
  Future<void> delete(String key) => _kv.delete(key);
  Future<List<String>> list() => _kv.list();
}
