import 'package:dio/dio.dart';
import '../native_bridge.dart';
import '../web_interop_stub.dart' if (dart.library.html) '../web_interop.dart';

/// Key-Value storage service for Flutter apps
class KVStorage {
  final Dio? _client;
  final bool _useWebInterop;
  final bool _useNativeBridge;

  KVStorage({Dio? client}) 
    : _client = client,
      _useWebInterop = client == null && _isWeb(),
      _useNativeBridge = client == null && !_isWeb();

  static bool _isWeb() {
    return const bool.fromEnvironment('dart.library.html', defaultValue: false);
  }

  /// Get value by key
  /// Returns null if key is not found
  Future<String?> get(String key) async {
    if (_useWebInterop) {
      try {
        final result = await WorkzSDKWebInterop.kvGet(key);
        return result['value'] as String?;
      } catch (e) {
        return null; // Key not found or other error
      }
    }
    
    if (_useNativeBridge) {
      try {
        final result = await WorkzSDKNativeBridge.kvGet(key);
        return result['value'] as String?;
      } catch (e) {
        return null; // Key not found or other error
      }
    }
    
    // Legacy Dio implementation
    try {
      final response = await _client!.get('/storage/kv/$key');
      if (response.statusCode == 200) {
        return response.data['value'];
      }
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) return null;
      rethrow;
    }
    return null;
  }

  /// Set value for a key
  Future<void> set(String key, String value, {int? ttl}) async {
    if (_useWebInterop) {
      await WorkzSDKWebInterop.kvSet(key, value, ttl: ttl);
      return;
    }
    
    if (_useNativeBridge) {
      await WorkzSDKNativeBridge.kvSet(key, value, ttl: ttl);
      return;
    }
    
    // Legacy Dio implementation
    await _client!.post('/storage/kv', data: {
      'key': key, 
      'value': value,
      if (ttl != null) 'ttl': ttl,
    });
  }
  
  /// Delete a key
  Future<void> delete(String key) async {
    if (_useWebInterop) {
      await WorkzSDKWebInterop.kvDelete(key);
      return;
    }
    
    if (_useNativeBridge) {
      await WorkzSDKNativeBridge.kvDelete(key);
      return;
    }
    
    // Legacy implementation would need to be added
    throw UnimplementedError('Delete not implemented for Dio client');
  }
  
  /// List all keys
  Future<List<String>> list() async {
    if (_useWebInterop) {
      final result = await WorkzSDKWebInterop.kvList();
      final keys = result['keys'] as List?;
      return keys?.cast<String>() ?? [];
    }
    
    if (_useNativeBridge) {
      final result = await WorkzSDKNativeBridge.kvList();
      final keys = result['keys'] as List?;
      return keys?.cast<String>() ?? [];
    }
    
    // Legacy implementation would need to be added
    throw UnimplementedError('List not implemented for Dio client');
  }
}