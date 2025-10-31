// packages/workz_sdk/lib/src/web_interop.dart

import 'package:js/js.dart' as js; // JS annotations
import 'dart:js' as dart_js; // JS runtime interop types
import 'dart:js_util' as js_util;
import 'dart:async';
import 'dart:convert';

/// JavaScript interop bindings for Flutter web to access WorkzSDK
@js.JS('WorkzSDK')
external WorkzSDKJS get _workzSDKJS;

/// JavaScript WorkzSDK interface
@js.JS()
@js.anonymous
class WorkzSDKJS {
  external Future<bool> init(dart_js.JsObject config);
  external String? getToken();
  external dart_js.JsObject? getUser();
  external dart_js.JsObject? getContext();
  external dart_js.JsObject? getPlatform();
  external bool isReady();
  external void on(String type, dart_js.JsFunction callback);
  external void off(String type, dart_js.JsFunction callback);
  external void emit(String type, dart_js.JsObject? payload);
  external dart_js.JsObject get api;
  external dart_js.JsObject get storage;
}

/// JavaScript API interface
@js.JS()
@js.anonymous
class WorkzSDKApiJS {
  external Future<dart_js.JsObject> get(String path);
  external Future<dart_js.JsObject> post(String path, dart_js.JsObject? body);
  external Future<dart_js.JsObject> put(String path, dart_js.JsObject? body);
  external Future<dart_js.JsObject> delete(String path);
}

/// JavaScript Storage interface
@js.JS()
@js.anonymous
class WorkzSDKStorageJS {
  external dart_js.JsObject get kv;
  external dart_js.JsObject get docs;
  external dart_js.JsObject get blobs;
}

/// Utility class for converting between Dart and JavaScript promises/futures
class PromiseUtils {
  /// Converts a JavaScript Promise to a Dart Future
  static Future<T> promiseToFuture<T>(dart_js.JsObject promise) {
    final completer = Completer<T>();
    
    final onSuccess = dart_js.allowInterop((result) {
      completer.complete(result as T);
    });
    
    final onError = dart_js.allowInterop((error) {
      completer.completeError(error);
    });
    
    js_util.callMethod(promise, 'then', [onSuccess]);
    js_util.callMethod(promise, 'catch', [onError]);
    
    return completer.future;
  }
  
  /// Converts a JavaScript Promise to a Dart Future with JSON parsing
  static Future<Map<String, dynamic>> promiseToJsonFuture(dart_js.JsObject promise) {
    return promiseToFuture<dart_js.JsObject>(promise).then((jsObject) {
      return _jsObjectToMap(jsObject);
    });
  }
  
  /// Converts a Dart Map to a JavaScript object
  static dart_js.JsObject mapToJsObject(Map<String, dynamic> map) {
    return dart_js.JsObject.jsify(map) as dart_js.JsObject;
  }
  
  /// Converts a JavaScript object to a Dart Map
  static Map<String, dynamic> _jsObjectToMap(dart_js.JsObject jsObject) {
    final jsonString = dart_js.context.callMethod('JSON.stringify', [jsObject]);
    return json.decode(jsonString);
  }
}

/// Flutter web interop layer for WorkzSDK
class WorkzSDKWebInterop {
  static WorkzSDKJS? _jsSDK;
  static final Map<String, List<Function>> _eventListeners = {};
  
  /// Initialize the JavaScript SDK
  static Future<bool> init({
    String? baseUrl,
    String mode = 'embed',
  }) async {
    try {
      _jsSDK = _workzSDKJS;
      
      final config = PromiseUtils.mapToJsObject({
        if (baseUrl != null) 'baseUrl': baseUrl,
        'mode': mode,
      });
      
      final initPromise = _jsSDK!.init(config);
      return await PromiseUtils.promiseToFuture<bool>(initPromise as dart_js.JsObject);
    } catch (e) {
      print('Failed to initialize WorkzSDK: $e');
      return false;
    }
  }
  
  /// Get authentication token
  static String? getToken() {
    return _jsSDK?.getToken();
  }
  
  /// Get current user information
  static Map<String, dynamic>? getUser() {
    final jsUser = _jsSDK?.getUser();
    if (jsUser == null) return null;
    return PromiseUtils._jsObjectToMap(jsUser);
  }
  
  /// Get current context
  static Map<String, dynamic>? getContext() {
    final jsContext = _jsSDK?.getContext();
    if (jsContext == null) return null;
    return PromiseUtils._jsObjectToMap(jsContext);
  }
  
  /// Get platform information
  static Map<String, dynamic>? getPlatform() {
    final jsPlatform = _jsSDK?.getPlatform();
    if (jsPlatform == null) return null;
    return PromiseUtils._jsObjectToMap(jsPlatform);
  }
  
  /// Check if SDK is ready
  static bool isReady() {
    return _jsSDK?.isReady() ?? false;
  }
  
  /// Add event listener
  static void addEventListener(String type, Function callback) {
    if (!_eventListeners.containsKey(type)) {
      _eventListeners[type] = [];
      
      // Register JavaScript event listener
      final jsCallback = dart_js.allowInterop((dart_js.JsObject? data) {
        final dartData = data != null ? PromiseUtils._jsObjectToMap(data) : null;
        for (final listener in _eventListeners[type]!) {
          try {
            listener(dartData);
          } catch (e) {
            print('Error in event listener: $e');
          }
        }
      }) as dynamic; // ensure JS callback type
      
      _jsSDK?.on(type, jsCallback as dynamic);
    }
    
    _eventListeners[type]!.add(callback);
  }
  
  /// Remove event listener
  static void removeEventListener(String type, Function callback) {
    if (_eventListeners.containsKey(type)) {
      _eventListeners[type]!.remove(callback);
      
      if (_eventListeners[type]!.isEmpty) {
        _eventListeners.remove(type);
        // Note: JavaScript SDK doesn't expose the callback reference for removal
        // This is a limitation of the current JS interop
      }
    }
  }
  
  /// Emit event
  static void emit(String type, Map<String, dynamic>? payload) {
    final jsPayload = payload != null ? PromiseUtils.mapToJsObject(payload) : null;
    _jsSDK?.emit(type, jsPayload);
  }
  
  /// API methods
  static Future<Map<String, dynamic>> apiGet(String path) async {
    final api = _jsSDK?.api;
    if (api == null) throw Exception('SDK not initialized');
    
    final promise = js_util.callMethod(api, 'get', [path]) as dart_js.JsObject;
    return await PromiseUtils.promiseToJsonFuture(promise);
  }
  
  static Future<Map<String, dynamic>> apiPost(String path, Map<String, dynamic>? body) async {
    final api = _jsSDK?.api;
    if (api == null) throw Exception('SDK not initialized');
    
    final jsBody = body != null ? PromiseUtils.mapToJsObject(body) : null;
    final promise = js_util.callMethod(api, 'post', [path, jsBody]) as dart_js.JsObject;
    return await PromiseUtils.promiseToJsonFuture(promise);
  }
  
  static Future<Map<String, dynamic>> apiPut(String path, Map<String, dynamic>? body) async {
    final api = _jsSDK?.api;
    if (api == null) throw Exception('SDK not initialized');
    
    final jsBody = body != null ? PromiseUtils.mapToJsObject(body) : null;
    final promise = js_util.callMethod(api, 'put', [path, jsBody]) as dart_js.JsObject;
    return await PromiseUtils.promiseToJsonFuture(promise);
  }
  
  static Future<Map<String, dynamic>> apiDelete(String path) async {
    final api = _jsSDK?.api;
    if (api == null) throw Exception('SDK not initialized');
    
    final promise = js_util.callMethod(api, 'delete', [path]) as dart_js.JsObject;
    return await PromiseUtils.promiseToJsonFuture(promise);
  }
  
  /// Storage KV methods
  static Future<Map<String, dynamic>> kvSet(String key, String value, {int? ttl}) async {
    final storage = _jsSDK?.storage;
    if (storage == null) throw Exception('SDK not initialized');
    
    final kv = js_util.getProperty(storage, 'kv');
    final data = PromiseUtils.mapToJsObject({
      'key': key,
      'value': value,
      if (ttl != null) 'ttl': ttl,
    });
    
    final promise = js_util.callMethod(kv, 'set', [data]) as dart_js.JsObject;
    return await PromiseUtils.promiseToJsonFuture(promise);
  }
  
  static Future<Map<String, dynamic>> kvGet(String key) async {
    final storage = _jsSDK?.storage;
    if (storage == null) throw Exception('SDK not initialized');
    
    final kv = js_util.getProperty(storage, 'kv');
    final promise = js_util.callMethod(kv, 'get', [key]) as dart_js.JsObject;
    return await PromiseUtils.promiseToJsonFuture(promise);
  }
  
  static Future<Map<String, dynamic>> kvDelete(String key) async {
    final storage = _jsSDK?.storage;
    if (storage == null) throw Exception('SDK not initialized');
    
    final kv = js_util.getProperty(storage, 'kv');
    final promise = js_util.callMethod(kv, 'delete', [key]) as dart_js.JsObject;
    return await PromiseUtils.promiseToJsonFuture(promise);
  }
  
  static Future<Map<String, dynamic>> kvList() async {
    final storage = _jsSDK?.storage;
    if (storage == null) throw Exception('SDK not initialized');
    
    final kv = js_util.getProperty(storage, 'kv');
    final promise = js_util.callMethod(kv, 'list', []) as dart_js.JsObject;
    return await PromiseUtils.promiseToJsonFuture(promise);
  }
  
  /// Storage Docs methods
  static Future<Map<String, dynamic>> docsSave(String id, Map<String, dynamic> document) async {
    final storage = _jsSDK?.storage;
    if (storage == null) throw Exception('SDK not initialized');
    
    final docs = js_util.getProperty(storage, 'docs');
    final jsDocument = PromiseUtils.mapToJsObject(document);
    final promise = js_util.callMethod(docs, 'save', [id, jsDocument]) as dart_js.JsObject;
    return await PromiseUtils.promiseToJsonFuture(promise);
  }
  
  static Future<Map<String, dynamic>> docsGet(String id) async {
    final storage = _jsSDK?.storage;
    if (storage == null) throw Exception('SDK not initialized');
    
    final docs = js_util.getProperty(storage, 'docs');
    final promise = js_util.callMethod(docs, 'get', [id]) as dart_js.JsObject;
    return await PromiseUtils.promiseToJsonFuture(promise);
  }
  
  static Future<Map<String, dynamic>> docsDelete(String id) async {
    final storage = _jsSDK?.storage;
    if (storage == null) throw Exception('SDK not initialized');
    
    final docs = js_util.getProperty(storage, 'docs');
    final promise = js_util.callMethod(docs, 'delete', [id]) as dart_js.JsObject;
    return await PromiseUtils.promiseToJsonFuture(promise);
  }
  
  static Future<Map<String, dynamic>> docsList() async {
    final storage = _jsSDK?.storage;
    if (storage == null) throw Exception('SDK not initialized');
    
    final docs = js_util.getProperty(storage, 'docs');
    final promise = js_util.callMethod(docs, 'list', []) as dart_js.JsObject;
    return await PromiseUtils.promiseToJsonFuture(promise);
  }
  
  static Future<Map<String, dynamic>> docsQuery(Map<String, dynamic> query) async {
    final storage = _jsSDK?.storage;
    if (storage == null) throw Exception('SDK not initialized');
    
    final docs = js_util.getProperty(storage, 'docs');
    final jsQuery = PromiseUtils.mapToJsObject(query);
    final promise = js_util.callMethod(docs, 'query', [jsQuery]) as dart_js.JsObject;
    return await PromiseUtils.promiseToJsonFuture(promise);
  }
}
