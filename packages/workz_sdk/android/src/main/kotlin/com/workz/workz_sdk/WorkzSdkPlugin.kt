// packages/workz_sdk/android/src/main/kotlin/com/workz/workz_sdk/WorkzSdkPlugin.kt

package com.workz.workz_sdk

import androidx.annotation.NonNull
import io.flutter.embedding.engine.plugins.FlutterPlugin
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import io.flutter.plugin.common.MethodChannel.MethodCallHandler
import io.flutter.plugin.common.MethodChannel.Result
import kotlinx.coroutines.*
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.io.IOException

/** WorkzSdkPlugin */
class WorkzSdkPlugin: FlutterPlugin, MethodCallHandler {
  private lateinit var channel : MethodChannel
  private var workzSDK: WorkzSDKNative? = null

  override fun onAttachedToEngine(@NonNull flutterPluginBinding: FlutterPlugin.FlutterPluginBinding) {
    channel = MethodChannel(flutterPluginBinding.binaryMessenger, "workz_sdk")
    channel.setMethodCallHandler(this)
  }

  override fun onMethodCall(@NonNull call: MethodCall, @NonNull result: Result) {
    when (call.method) {
      "init" -> {
        val apiUrl = call.argument<String>("apiUrl")
        val token = call.argument<String>("token")
        
        if (apiUrl != null && token != null) {
          workzSDK = WorkzSDKNative(apiUrl, token)
          result.success(true)
        } else {
          result.error("INVALID_ARGUMENTS", "apiUrl and token are required", null)
        }
      }
      "getToken" -> {
        result.success(workzSDK?.getToken())
      }
      "getUser" -> {
        workzSDK?.getUser { user, error ->
          if (error != null) {
            result.error("GET_USER_ERROR", error, null)
          } else {
            result.success(user)
          }
        }
      }
      "isReady" -> {
        result.success(workzSDK?.isReady() ?: false)
      }
      "apiGet" -> {
        val path = call.argument<String>("path")
        if (path != null) {
          workzSDK?.apiGet(path) { response, error ->
            if (error != null) {
              result.error("API_GET_ERROR", error, null)
            } else {
              result.success(response)
            }
          }
        } else {
          result.error("INVALID_ARGUMENTS", "path is required", null)
        }
      }
      "apiPost" -> {
        val path = call.argument<String>("path")
        val body = call.argument<Map<String, Any>>("body")
        if (path != null) {
          workzSDK?.apiPost(path, body) { response, error ->
            if (error != null) {
              result.error("API_POST_ERROR", error, null)
            } else {
              result.success(response)
            }
          }
        } else {
          result.error("INVALID_ARGUMENTS", "path is required", null)
        }
      }
      "apiPut" -> {
        val path = call.argument<String>("path")
        val body = call.argument<Map<String, Any>>("body")
        if (path != null) {
          workzSDK?.apiPut(path, body) { response, error ->
            if (error != null) {
              result.error("API_PUT_ERROR", error, null)
            } else {
              result.success(response)
            }
          }
        } else {
          result.error("INVALID_ARGUMENTS", "path is required", null)
        }
      }
      "apiDelete" -> {
        val path = call.argument<String>("path")
        if (path != null) {
          workzSDK?.apiDelete(path) { response, error ->
            if (error != null) {
              result.error("API_DELETE_ERROR", error, null)
            } else {
              result.success(response)
            }
          }
        } else {
          result.error("INVALID_ARGUMENTS", "path is required", null)
        }
      }
      "kvSet" -> {
        val key = call.argument<String>("key")
        val value = call.argument<String>("value")
        val ttl = call.argument<Int>("ttl")
        if (key != null && value != null) {
          workzSDK?.kvSet(key, value, ttl) { response, error ->
            if (error != null) {
              result.error("KV_SET_ERROR", error, null)
            } else {
              result.success(response)
            }
          }
        } else {
          result.error("INVALID_ARGUMENTS", "key and value are required", null)
        }
      }
      "kvGet" -> {
        val key = call.argument<String>("key")
        if (key != null) {
          workzSDK?.kvGet(key) { response, error ->
            if (error != null) {
              result.error("KV_GET_ERROR", error, null)
            } else {
              result.success(response)
            }
          }
        } else {
          result.error("INVALID_ARGUMENTS", "key is required", null)
        }
      }
      "kvDelete" -> {
        val key = call.argument<String>("key")
        if (key != null) {
          workzSDK?.kvDelete(key) { response, error ->
            if (error != null) {
              result.error("KV_DELETE_ERROR", error, null)
            } else {
              result.success(response)
            }
          }
        } else {
          result.error("INVALID_ARGUMENTS", "key is required", null)
        }
      }
      "kvList" -> {
        workzSDK?.kvList { response, error ->
          if (error != null) {
            result.error("KV_LIST_ERROR", error, null)
          } else {
            result.success(response)
          }
        }
      }
      "docsSave" -> {
        val id = call.argument<String>("id")
        val document = call.argument<Map<String, Any>>("document")
        if (id != null && document != null) {
          workzSDK?.docsSave(id, document) { response, error ->
            if (error != null) {
              result.error("DOCS_SAVE_ERROR", error, null)
            } else {
              result.success(response)
            }
          }
        } else {
          result.error("INVALID_ARGUMENTS", "id and document are required", null)
        }
      }
      "docsGet" -> {
        val id = call.argument<String>("id")
        if (id != null) {
          workzSDK?.docsGet(id) { response, error ->
            if (error != null) {
              result.error("DOCS_GET_ERROR", error, null)
            } else {
              result.success(response)
            }
          }
        } else {
          result.error("INVALID_ARGUMENTS", "id is required", null)
        }
      }
      "docsDelete" -> {
        val id = call.argument<String>("id")
        if (id != null) {
          workzSDK?.docsDelete(id) { response, error ->
            if (error != null) {
              result.error("DOCS_DELETE_ERROR", error, null)
            } else {
              result.success(response)
            }
          }
        } else {
          result.error("INVALID_ARGUMENTS", "id is required", null)
        }
      }
      "docsList" -> {
        workzSDK?.docsList { response, error ->
          if (error != null) {
            result.error("DOCS_LIST_ERROR", error, null)
          } else {
            result.success(response)
          }
        }
      }
      "docsQuery" -> {
        val query = call.argument<Map<String, Any>>("query")
        if (query != null) {
          workzSDK?.docsQuery(query) { response, error ->
            if (error != null) {
              result.error("DOCS_QUERY_ERROR", error, null)
            } else {
              result.success(response)
            }
          }
        } else {
          result.error("INVALID_ARGUMENTS", "query is required", null)
        }
      }
      else -> {
        result.notImplemented()
      }
    }
  }

  override fun onDetachedFromEngine(@NonNull binding: FlutterPlugin.FlutterPluginBinding) {
    channel.setMethodCallHandler(null)
  }
}

class WorkzSDKNative(private val apiUrl: String, private val token: String) {
  private val client = OkHttpClient()
  private val scope = CoroutineScope(Dispatchers.IO + SupervisorJob())
  private var user: Map<String, Any>? = null
  private var isInitialized = false

  init {
    // Initialize and fetch user info
    scope.launch {
      try {
        fetchUserInfo()
        isInitialized = true
      } catch (e: Exception) {
        println("Failed to initialize WorkzSDK: ${e.message}")
      }
    }
  }

  fun getToken(): String = token

  fun isReady(): Boolean = isInitialized

  fun getUser(callback: (Map<String, Any>?, String?) -> Unit) {
    if (user != null) {
      callback(user, null)
    } else {
      scope.launch {
        try {
          fetchUserInfo()
          callback(user, null)
        } catch (e: Exception) {
          callback(null, e.message)
        }
      }
    }
  }

  private suspend fun fetchUserInfo() {
    val request = Request.Builder()
      .url("$apiUrl/me")
      .addHeader("Authorization", "Bearer $token")
      .build()

    val response = client.newCall(request).execute()
    if (response.isSuccessful) {
      val jsonString = response.body?.string()
      if (jsonString != null) {
        val jsonObject = JSONObject(jsonString)
        user = jsonObject.toMap()
      }
    }
  }

  fun apiGet(path: String, callback: (Map<String, Any>?, String?) -> Unit) {
    scope.launch {
      try {
        val request = Request.Builder()
          .url("$apiUrl$path")
          .addHeader("Authorization", "Bearer $token")
          .build()

        val response = client.newCall(request).execute()
        val jsonString = response.body?.string()
        if (response.isSuccessful && jsonString != null) {
          val jsonObject = JSONObject(jsonString)
          callback(jsonObject.toMap(), null)
        } else {
          callback(null, "HTTP ${response.code}: ${response.message}")
        }
      } catch (e: Exception) {
        callback(null, e.message)
      }
    }
  }

  fun apiPost(path: String, body: Map<String, Any>?, callback: (Map<String, Any>?, String?) -> Unit) {
    scope.launch {
      try {
        val jsonBody = if (body != null) {
          JSONObject(body).toString().toRequestBody("application/json".toMediaType())
        } else {
          "{}".toRequestBody("application/json".toMediaType())
        }

        val request = Request.Builder()
          .url("$apiUrl$path")
          .addHeader("Authorization", "Bearer $token")
          .post(jsonBody)
          .build()

        val response = client.newCall(request).execute()
        val jsonString = response.body?.string()
        if (response.isSuccessful && jsonString != null) {
          val jsonObject = JSONObject(jsonString)
          callback(jsonObject.toMap(), null)
        } else {
          callback(null, "HTTP ${response.code}: ${response.message}")
        }
      } catch (e: Exception) {
        callback(null, e.message)
      }
    }
  }

  fun apiPut(path: String, body: Map<String, Any>?, callback: (Map<String, Any>?, String?) -> Unit) {
    scope.launch {
      try {
        val jsonBody = if (body != null) {
          JSONObject(body).toString().toRequestBody("application/json".toMediaType())
        } else {
          "{}".toRequestBody("application/json".toMediaType())
        }

        val request = Request.Builder()
          .url("$apiUrl$path")
          .addHeader("Authorization", "Bearer $token")
          .put(jsonBody)
          .build()

        val response = client.newCall(request).execute()
        val jsonString = response.body?.string()
        if (response.isSuccessful && jsonString != null) {
          val jsonObject = JSONObject(jsonString)
          callback(jsonObject.toMap(), null)
        } else {
          callback(null, "HTTP ${response.code}: ${response.message}")
        }
      } catch (e: Exception) {
        callback(null, e.message)
      }
    }
  }

  fun apiDelete(path: String, callback: (Map<String, Any>?, String?) -> Unit) {
    scope.launch {
      try {
        val request = Request.Builder()
          .url("$apiUrl$path")
          .addHeader("Authorization", "Bearer $token")
          .delete()
          .build()

        val response = client.newCall(request).execute()
        val jsonString = response.body?.string()
        if (response.isSuccessful && jsonString != null) {
          val jsonObject = JSONObject(jsonString)
          callback(jsonObject.toMap(), null)
        } else {
          callback(null, "HTTP ${response.code}: ${response.message}")
        }
      } catch (e: Exception) {
        callback(null, e.message)
      }
    }
  }

  // KV Storage methods
  fun kvSet(key: String, value: String, ttl: Int?, callback: (Map<String, Any>?, String?) -> Unit) {
    val body = mutableMapOf<String, Any>(
      "key" to key,
      "value" to value,
      "scopeType" to "user",
      "scopeId" to (user?.get("id") ?: 0)
    )
    if (ttl != null) {
      body["ttl"] = ttl
    }
    apiPost("/appdata/kv", body, callback)
  }

  fun kvGet(key: String, callback: (Map<String, Any>?, String?) -> Unit) {
    val userId = user?.get("id") ?: 0
    apiGet("/appdata/kv?key=$key&scopeType=user&scopeId=$userId", callback)
  }

  fun kvDelete(key: String, callback: (Map<String, Any>?, String?) -> Unit) {
    val userId = user?.get("id") ?: 0
    apiDelete("/appdata/kv?key=$key&scopeType=user&scopeId=$userId", callback)
  }

  fun kvList(callback: (Map<String, Any>?, String?) -> Unit) {
    val userId = user?.get("id") ?: 0
    apiGet("/appdata/kv?scopeType=user&scopeId=$userId", callback)
  }

  // Document Storage methods
  fun docsSave(id: String, document: Map<String, Any>, callback: (Map<String, Any>?, String?) -> Unit) {
    val body = mapOf(
      "docType" to "user_data",
      "docId" to id,
      "document" to document,
      "scopeType" to "user",
      "scopeId" to (user?.get("id") ?: 0)
    )
    apiPost("/appdata/docs/upsert", body, callback)
  }

  fun docsGet(id: String, callback: (Map<String, Any>?, String?) -> Unit) {
    val body = mapOf(
      "docType" to "user_data",
      "filters" to mapOf("docId" to id),
      "scopeType" to "user",
      "scopeId" to (user?.get("id") ?: 0)
    )
    apiPost("/appdata/docs/query", body, callback)
  }

  fun docsDelete(id: String, callback: (Map<String, Any>?, String?) -> Unit) {
    val userId = user?.get("id") ?: 0
    apiDelete("/appdata/docs/user_data/$id?scopeType=user&scopeId=$userId", callback)
  }

  fun docsList(callback: (Map<String, Any>?, String?) -> Unit) {
    val body = mapOf(
      "docType" to "user_data",
      "filters" to emptyMap<String, Any>(),
      "scopeType" to "user",
      "scopeId" to (user?.get("id") ?: 0)
    )
    apiPost("/appdata/docs/query", body, callback)
  }

  fun docsQuery(query: Map<String, Any>, callback: (Map<String, Any>?, String?) -> Unit) {
    val body = mapOf(
      "docType" to "user_data",
      "filters" to query,
      "scopeType" to "user",
      "scopeId" to (user?.get("id") ?: 0)
    )
    apiPost("/appdata/docs/query", body, callback)
  }
}

// Extension function to convert JSONObject to Map
fun JSONObject.toMap(): Map<String, Any> {
  val map = mutableMapOf<String, Any>()
  val keys = this.keys()
  while (keys.hasNext()) {
    val key = keys.next()
    val value = this.get(key)
    map[key] = when (value) {
      is JSONObject -> value.toMap()
      else -> value
    }
  }
  return map
}