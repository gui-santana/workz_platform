// packages/workz_sdk/ios/Classes/WorkzSdkPlugin.swift

import Flutter
import UIKit
import Foundation

public class WorkzSdkPlugin: NSObject, FlutterPlugin {
  private var workzSDK: WorkzSDKNative?
  
  public static func register(with registrar: FlutterPluginRegistrar) {
    let channel = FlutterMethodChannel(name: "workz_sdk", binaryMessenger: registrar.messenger())
    let instance = WorkzSdkPlugin()
    registrar.addMethodCallDelegate(instance, channel: channel)
  }

  public func handle(_ call: FlutterMethodCall, result: @escaping FlutterResult) {
    switch call.method {
    case "init":
      guard let args = call.arguments as? [String: Any],
            let apiUrl = args["apiUrl"] as? String,
            let token = args["token"] as? String else {
        result(FlutterError(code: "INVALID_ARGUMENTS", message: "apiUrl and token are required", details: nil))
        return
      }
      
      workzSDK = WorkzSDKNative(apiUrl: apiUrl, token: token)
      result(true)
      
    case "getToken":
      result(workzSDK?.getToken())
      
    case "getUser":
      workzSDK?.getUser { user, error in
        if let error = error {
          result(FlutterError(code: "GET_USER_ERROR", message: error, details: nil))
        } else {
          result(user)
        }
      }
      
    case "isReady":
      result(workzSDK?.isReady() ?? false)
      
    case "apiGet":
      guard let args = call.arguments as? [String: Any],
            let path = args["path"] as? String else {
        result(FlutterError(code: "INVALID_ARGUMENTS", message: "path is required", details: nil))
        return
      }
      
      workzSDK?.apiGet(path: path) { response, error in
        if let error = error {
          result(FlutterError(code: "API_GET_ERROR", message: error, details: nil))
        } else {
          result(response)
        }
      }
      
    case "apiPost":
      guard let args = call.arguments as? [String: Any],
            let path = args["path"] as? String else {
        result(FlutterError(code: "INVALID_ARGUMENTS", message: "path is required", details: nil))
        return
      }
      
      let body = args["body"] as? [String: Any]
      workzSDK?.apiPost(path: path, body: body) { response, error in
        if let error = error {
          result(FlutterError(code: "API_POST_ERROR", message: error, details: nil))
        } else {
          result(response)
        }
      }
      
    case "apiPut":
      guard let args = call.arguments as? [String: Any],
            let path = args["path"] as? String else {
        result(FlutterError(code: "INVALID_ARGUMENTS", message: "path is required", details: nil))
        return
      }
      
      let body = args["body"] as? [String: Any]
      workzSDK?.apiPut(path: path, body: body) { response, error in
        if let error = error {
          result(FlutterError(code: "API_PUT_ERROR", message: error, details: nil))
        } else {
          result(response)
        }
      }
      
    case "apiDelete":
      guard let args = call.arguments as? [String: Any],
            let path = args["path"] as? String else {
        result(FlutterError(code: "INVALID_ARGUMENTS", message: "path is required", details: nil))
        return
      }
      
      workzSDK?.apiDelete(path: path) { response, error in
        if let error = error {
          result(FlutterError(code: "API_DELETE_ERROR", message: error, details: nil))
        } else {
          result(response)
        }
      }
      
    case "kvSet":
      guard let args = call.arguments as? [String: Any],
            let key = args["key"] as? String,
            let value = args["value"] as? String else {
        result(FlutterError(code: "INVALID_ARGUMENTS", message: "key and value are required", details: nil))
        return
      }
      
      let ttl = args["ttl"] as? Int
      workzSDK?.kvSet(key: key, value: value, ttl: ttl) { response, error in
        if let error = error {
          result(FlutterError(code: "KV_SET_ERROR", message: error, details: nil))
        } else {
          result(response)
        }
      }
      
    case "kvGet":
      guard let args = call.arguments as? [String: Any],
            let key = args["key"] as? String else {
        result(FlutterError(code: "INVALID_ARGUMENTS", message: "key is required", details: nil))
        return
      }
      
      workzSDK?.kvGet(key: key) { response, error in
        if let error = error {
          result(FlutterError(code: "KV_GET_ERROR", message: error, details: nil))
        } else {
          result(response)
        }
      }
      
    case "kvDelete":
      guard let args = call.arguments as? [String: Any],
            let key = args["key"] as? String else {
        result(FlutterError(code: "INVALID_ARGUMENTS", message: "key is required", details: nil))
        return
      }
      
      workzSDK?.kvDelete(key: key) { response, error in
        if let error = error {
          result(FlutterError(code: "KV_DELETE_ERROR", message: error, details: nil))
        } else {
          result(response)
        }
      }
      
    case "kvList":
      workzSDK?.kvList { response, error in
        if let error = error {
          result(FlutterError(code: "KV_LIST_ERROR", message: error, details: nil))
        } else {
          result(response)
        }
      }
      
    case "docsSave":
      guard let args = call.arguments as? [String: Any],
            let id = args["id"] as? String,
            let document = args["document"] as? [String: Any] else {
        result(FlutterError(code: "INVALID_ARGUMENTS", message: "id and document are required", details: nil))
        return
      }
      
      workzSDK?.docsSave(id: id, document: document) { response, error in
        if let error = error {
          result(FlutterError(code: "DOCS_SAVE_ERROR", message: error, details: nil))
        } else {
          result(response)
        }
      }
      
    case "docsGet":
      guard let args = call.arguments as? [String: Any],
            let id = args["id"] as? String else {
        result(FlutterError(code: "INVALID_ARGUMENTS", message: "id is required", details: nil))
        return
      }
      
      workzSDK?.docsGet(id: id) { response, error in
        if let error = error {
          result(FlutterError(code: "DOCS_GET_ERROR", message: error, details: nil))
        } else {
          result(response)
        }
      }
      
    case "docsDelete":
      guard let args = call.arguments as? [String: Any],
            let id = args["id"] as? String else {
        result(FlutterError(code: "INVALID_ARGUMENTS", message: "id is required", details: nil))
        return
      }
      
      workzSDK?.docsDelete(id: id) { response, error in
        if let error = error {
          result(FlutterError(code: "DOCS_DELETE_ERROR", message: error, details: nil))
        } else {
          result(response)
        }
      }
      
    case "docsList":
      workzSDK?.docsList { response, error in
        if let error = error {
          result(FlutterError(code: "DOCS_LIST_ERROR", message: error, details: nil))
        } else {
          result(response)
        }
      }
      
    case "docsQuery":
      guard let args = call.arguments as? [String: Any],
            let query = args["query"] as? [String: Any] else {
        result(FlutterError(code: "INVALID_ARGUMENTS", message: "query is required", details: nil))
        return
      }
      
      workzSDK?.docsQuery(query: query) { response, error in
        if let error = error {
          result(FlutterError(code: "DOCS_QUERY_ERROR", message: error, details: nil))
        } else {
          result(response)
        }
      }
      
    default:
      result(FlutterMethodNotImplemented)
    }
  }
}

class WorkzSDKNative {
  private let apiUrl: String
  private let token: String
  private var user: [String: Any]?
  private var isInitialized = false
  
  init(apiUrl: String, token: String) {
    self.apiUrl = apiUrl
    self.token = token
    
    // Initialize and fetch user info
    fetchUserInfo { [weak self] _, _ in
      self?.isInitialized = true
    }
  }
  
  func getToken() -> String {
    return token
  }
  
  func isReady() -> Bool {
    return isInitialized
  }
  
  func getUser(completion: @escaping ([String: Any]?, String?) -> Void) {
    if let user = user {
      completion(user, nil)
    } else {
      fetchUserInfo(completion: completion)
    }
  }
  
  private func fetchUserInfo(completion: @escaping ([String: Any]?, String?) -> Void) {
    apiGet(path: "/me", completion: { [weak self] response, error in
      if let response = response {
        self?.user = response
        completion(response, nil)
      } else {
        completion(nil, error)
      }
    })
  }
  
  func apiGet(path: String, completion: @escaping ([String: Any]?, String?) -> Void) {
    guard let url = URL(string: "\(apiUrl)\(path)") else {
      completion(nil, "Invalid URL")
      return
    }
    
    var request = URLRequest(url: url)
    request.httpMethod = "GET"
    request.addValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
    
    URLSession.shared.dataTask(with: request) { data, response, error in
      DispatchQueue.main.async {
        if let error = error {
          completion(nil, error.localizedDescription)
          return
        }
        
        guard let data = data else {
          completion(nil, "No data received")
          return
        }
        
        do {
          if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
            completion(json, nil)
          } else {
            completion(nil, "Invalid JSON response")
          }
        } catch {
          completion(nil, "JSON parsing error: \(error.localizedDescription)")
        }
      }
    }.resume()
  }
  
  func apiPost(path: String, body: [String: Any]?, completion: @escaping ([String: Any]?, String?) -> Void) {
    makeRequest(method: "POST", path: path, body: body, completion: completion)
  }
  
  func apiPut(path: String, body: [String: Any]?, completion: @escaping ([String: Any]?, String?) -> Void) {
    makeRequest(method: "PUT", path: path, body: body, completion: completion)
  }
  
  func apiDelete(path: String, completion: @escaping ([String: Any]?, String?) -> Void) {
    makeRequest(method: "DELETE", path: path, body: nil, completion: completion)
  }
  
  private func makeRequest(method: String, path: String, body: [String: Any]?, completion: @escaping ([String: Any]?, String?) -> Void) {
    guard let url = URL(string: "\(apiUrl)\(path)") else {
      completion(nil, "Invalid URL")
      return
    }
    
    var request = URLRequest(url: url)
    request.httpMethod = method
    request.addValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
    
    if let body = body {
      do {
        request.httpBody = try JSONSerialization.data(withJSONObject: body)
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
      } catch {
        completion(nil, "JSON encoding error: \(error.localizedDescription)")
        return
      }
    }
    
    URLSession.shared.dataTask(with: request) { data, response, error in
      DispatchQueue.main.async {
        if let error = error {
          completion(nil, error.localizedDescription)
          return
        }
        
        guard let data = data else {
          completion(nil, "No data received")
          return
        }
        
        do {
          if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
            completion(json, nil)
          } else {
            completion(nil, "Invalid JSON response")
          }
        } catch {
          completion(nil, "JSON parsing error: \(error.localizedDescription)")
        }
      }
    }.resume()
  }
  
  // KV Storage methods
  func kvSet(key: String, value: String, ttl: Int?, completion: @escaping ([String: Any]?, String?) -> Void) {
    var body: [String: Any] = [
      "key": key,
      "value": value,
      "scopeType": "user",
      "scopeId": user?["id"] ?? 0
    ]
    if let ttl = ttl {
      body["ttl"] = ttl
    }
    apiPost(path: "/appdata/kv", body: body, completion: completion)
  }
  
  func kvGet(key: String, completion: @escaping ([String: Any]?, String?) -> Void) {
    let userId = user?["id"] ?? 0
    apiGet(path: "/appdata/kv?key=\(key)&scopeType=user&scopeId=\(userId)", completion: completion)
  }
  
  func kvDelete(key: String, completion: @escaping ([String: Any]?, String?) -> Void) {
    let userId = user?["id"] ?? 0
    apiDelete(path: "/appdata/kv?key=\(key)&scopeType=user&scopeId=\(userId)", completion: completion)
  }
  
  func kvList(completion: @escaping ([String: Any]?, String?) -> Void) {
    let userId = user?["id"] ?? 0
    apiGet(path: "/appdata/kv?scopeType=user&scopeId=\(userId)", completion: completion)
  }
  
  // Document Storage methods
  func docsSave(id: String, document: [String: Any], completion: @escaping ([String: Any]?, String?) -> Void) {
    let body: [String: Any] = [
      "docType": "user_data",
      "docId": id,
      "document": document,
      "scopeType": "user",
      "scopeId": user?["id"] ?? 0
    ]
    apiPost(path: "/appdata/docs/upsert", body: body, completion: completion)
  }
  
  func docsGet(id: String, completion: @escaping ([String: Any]?, String?) -> Void) {
    let body: [String: Any] = [
      "docType": "user_data",
      "filters": ["docId": id],
      "scopeType": "user",
      "scopeId": user?["id"] ?? 0
    ]
    apiPost(path: "/appdata/docs/query", body: body, completion: completion)
  }
  
  func docsDelete(id: String, completion: @escaping ([String: Any]?, String?) -> Void) {
    let userId = user?["id"] ?? 0
    apiDelete(path: "/appdata/docs/user_data/\(id)?scopeType=user&scopeId=\(userId)", completion: completion)
  }
  
  func docsList(completion: @escaping ([String: Any]?, String?) -> Void) {
    let body: [String: Any] = [
      "docType": "user_data",
      "filters": [:],
      "scopeType": "user",
      "scopeId": user?["id"] ?? 0
    ]
    apiPost(path: "/appdata/docs/query", body: body, completion: completion)
  }
  
  func docsQuery(query: [String: Any], completion: @escaping ([String: Any]?, String?) -> Void) {
    let body: [String: Any] = [
      "docType": "user_data",
      "filters": query,
      "scopeType": "user",
      "scopeId": user?["id"] ?? 0
    ]
    apiPost(path: "/appdata/docs/query", body: body, completion: completion)
  }
}