// packages/workz_sdk/lib/src/native_bridge.dart

import 'dart:async';
import 'package:flutter/services.dart';

/// Native bridge for iOS and Android platforms
class WorkzSDKNativeBridge {
  static const MethodChannel _channel = MethodChannel('workz_sdk');
  
  /// Initialize the native SDK
  static Future<bool> init({
    required String apiUrl,
    required String token,
  }) async {
    try {
      final result = await _channel.invokeMethod('init', {
        'apiUrl': apiUrl,
        'token': token,
      });
      return result == true;
    } on PlatformException catch (e) {
      print('Failed to initialize native SDK: ${e.message}');
      return false;
    }
  }
  
  /// Get authentication token
  static Future<String?> getToken() async {
    try {
      return await _channel.invokeMethod('getToken');
    } on PlatformException catch (e) {
      print('Failed to get token: ${e.message}');
      return null;
    }
  }
  
  /// Get current user information
  static Future<Map<String, dynamic>?> getUser() async {
    try {
      final result = await _channel.invokeMethod('getUser');
      return result != null ? Map<String, dynamic>.from(result) : null;
    } on PlatformException catch (e) {
      print('Failed to get user: ${e.message}');
      return null;
    }
  }
  
  /// Check if SDK is ready
  static Future<bool> isReady() async {
    try {
      final result = await _channel.invokeMethod('isReady');
      return result == true;
    } on PlatformException catch (e) {
      print('Failed to check ready state: ${e.message}');
      return false;
    }
  }
  
  /// Make API GET request
  static Future<Map<String, dynamic>> apiGet(String path) async {
    try {
      final result = await _channel.invokeMethod('apiGet', {'path': path});
      return Map<String, dynamic>.from(result);
    } on PlatformException catch (e) {
      throw Exception('API GET failed: ${e.message}');
    }
  }
  
  /// Make API POST request
  static Future<Map<String, dynamic>> apiPost(String path, Map<String, dynamic>? body) async {
    try {
      final result = await _channel.invokeMethod('apiPost', {
        'path': path,
        'body': body,
      });
      return Map<String, dynamic>.from(result);
    } on PlatformException catch (e) {
      throw Exception('API POST failed: ${e.message}');
    }
  }
  
  /// Make API PUT request
  static Future<Map<String, dynamic>> apiPut(String path, Map<String, dynamic>? body) async {
    try {
      final result = await _channel.invokeMethod('apiPut', {
        'path': path,
        'body': body,
      });
      return Map<String, dynamic>.from(result);
    } on PlatformException catch (e) {
      throw Exception('API PUT failed: ${e.message}');
    }
  }
  
  /// Make API DELETE request
  static Future<Map<String, dynamic>> apiDelete(String path) async {
    try {
      final result = await _channel.invokeMethod('apiDelete', {'path': path});
      return Map<String, dynamic>.from(result);
    } on PlatformException catch (e) {
      throw Exception('API DELETE failed: ${e.message}');
    }
  }
  
  /// KV Storage - Set value
  static Future<Map<String, dynamic>> kvSet(String key, String value, {int? ttl}) async {
    try {
      final result = await _channel.invokeMethod('kvSet', {
        'key': key,
        'value': value,
        if (ttl != null) 'ttl': ttl,
      });
      return Map<String, dynamic>.from(result);
    } on PlatformException catch (e) {
      throw Exception('KV Set failed: ${e.message}');
    }
  }
  
  /// KV Storage - Get value
  static Future<Map<String, dynamic>> kvGet(String key) async {
    try {
      final result = await _channel.invokeMethod('kvGet', {'key': key});
      return Map<String, dynamic>.from(result);
    } on PlatformException catch (e) {
      throw Exception('KV Get failed: ${e.message}');
    }
  }
  
  /// KV Storage - Delete key
  static Future<Map<String, dynamic>> kvDelete(String key) async {
    try {
      final result = await _channel.invokeMethod('kvDelete', {'key': key});
      return Map<String, dynamic>.from(result);
    } on PlatformException catch (e) {
      throw Exception('KV Delete failed: ${e.message}');
    }
  }
  
  /// KV Storage - List keys
  static Future<Map<String, dynamic>> kvList() async {
    try {
      final result = await _channel.invokeMethod('kvList');
      return Map<String, dynamic>.from(result);
    } on PlatformException catch (e) {
      throw Exception('KV List failed: ${e.message}');
    }
  }
  
  /// Document Storage - Save document
  static Future<Map<String, dynamic>> docsSave(String id, Map<String, dynamic> document) async {
    try {
      final result = await _channel.invokeMethod('docsSave', {
        'id': id,
        'document': document,
      });
      return Map<String, dynamic>.from(result);
    } on PlatformException catch (e) {
      throw Exception('Docs Save failed: ${e.message}');
    }
  }
  
  /// Document Storage - Get document
  static Future<Map<String, dynamic>> docsGet(String id) async {
    try {
      final result = await _channel.invokeMethod('docsGet', {'id': id});
      return Map<String, dynamic>.from(result);
    } on PlatformException catch (e) {
      throw Exception('Docs Get failed: ${e.message}');
    }
  }
  
  /// Document Storage - Delete document
  static Future<Map<String, dynamic>> docsDelete(String id) async {
    try {
      final result = await _channel.invokeMethod('docsDelete', {'id': id});
      return Map<String, dynamic>.from(result);
    } on PlatformException catch (e) {
      throw Exception('Docs Delete failed: ${e.message}');
    }
  }
  
  /// Document Storage - List documents
  static Future<Map<String, dynamic>> docsList() async {
    try {
      final result = await _channel.invokeMethod('docsList');
      return Map<String, dynamic>.from(result);
    } on PlatformException catch (e) {
      throw Exception('Docs List failed: ${e.message}');
    }
  }
  
  /// Document Storage - Query documents
  static Future<Map<String, dynamic>> docsQuery(Map<String, dynamic> query) async {
    try {
      final result = await _channel.invokeMethod('docsQuery', {'query': query});
      return Map<String, dynamic>.from(result);
    } on PlatformException catch (e) {
      throw Exception('Docs Query failed: ${e.message}');
    }
  }
}