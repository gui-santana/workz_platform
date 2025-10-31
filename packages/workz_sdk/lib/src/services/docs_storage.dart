// packages/workz_sdk/lib/src/services/docs_storage.dart

import 'dart:async';
import '../native_bridge.dart';
import '../web_interop_stub.dart' if (dart.library.html) '../web_interop.dart';

/// Document storage service for Flutter apps
class DocsStorage {
  static bool _isWeb() {
    return const bool.fromEnvironment('dart.library.html', defaultValue: false);
  }

  /// Save a document with the given ID
  Future<Map<String, dynamic>> save(String id, Map<String, dynamic> document) async {
    if (_isWeb()) {
      return await WorkzSDKWebInterop.docsSave(id, document);
    } else {
      return await WorkzSDKNativeBridge.docsSave(id, document);
    }
  }
  
  /// Get a document by ID
  Future<Map<String, dynamic>> get(String id) async {
    if (_isWeb()) {
      return await WorkzSDKWebInterop.docsGet(id);
    } else {
      return await WorkzSDKNativeBridge.docsGet(id);
    }
  }
  
  /// Delete a document by ID
  Future<Map<String, dynamic>> delete(String id) async {
    if (_isWeb()) {
      return await WorkzSDKWebInterop.docsDelete(id);
    } else {
      return await WorkzSDKNativeBridge.docsDelete(id);
    }
  }
  
  /// List all documents
  Future<Map<String, dynamic>> list() async {
    if (_isWeb()) {
      return await WorkzSDKWebInterop.docsList();
    } else {
      return await WorkzSDKNativeBridge.docsList();
    }
  }
  
  /// Query documents with filters
  Future<Map<String, dynamic>> query(Map<String, dynamic> query) async {
    if (_isWeb()) {
      return await WorkzSDKWebInterop.docsQuery(query);
    } else {
      return await WorkzSDKNativeBridge.docsQuery(query);
    }
  }
}