# WorkzSDK Documentation

## Overview

The WorkzSDK provides a unified development experience for building applications on the Workz! platform. Whether you're creating JavaScript web apps or Flutter multi-platform applications, the SDK offers consistent APIs for authentication, storage, and platform services.

## Quick Start

### JavaScript Integration

```javascript
// Initialize the SDK
await WorkzSDK.init({
  apiUrl: 'https://api.workz.com',
  token: 'your-app-token'
});

// Use KV storage
await WorkzSDK.kv.set('user-preference', 'dark-mode');
const preference = await WorkzSDK.kv.get('user-preference');

// Get user profile
const profile = await WorkzSDK.profile.get();
console.log(`Hello, ${profile.name}!`);
```

### Flutter Integration

```dart
import 'package:workz_sdk/workz_sdk.dart';

// Initialize the SDK
await WorkzSDK.init(
  apiUrl: 'https://api.workz.com',
  token: 'your-app-token',
);

// Use KV storage
await WorkzSDK.kv.set('user-preference', 'dark-mode');
final preference = await WorkzSDK.kv.get('user-preference');

// Get user profile
final profile = await WorkzSDK.profile.get();
print('Hello, ${profile.name}!');
```

## Core APIs

### Authentication

The SDK handles authentication automatically using the provided token. All API calls are authenticated by default.

#### JavaScript
```javascript
// Check authentication status
const isAuthenticated = await WorkzSDK.auth.isAuthenticated();

// Get current token info
const tokenInfo = await WorkzSDK.auth.getTokenInfo();
```

#### Flutter
```dart
// Check authentication status
final isAuthenticated = await WorkzSDK.auth.isAuthenticated();

// Get current token info
final tokenInfo = await WorkzSDK.auth.getTokenInfo();
```

### Key-Value Storage

Persistent storage for your application data.

#### JavaScript
```javascript
// Store data
await WorkzSDK.kv.set('key', 'value');
await WorkzSDK.kv.set('user-settings', { theme: 'dark', language: 'en' });

// Retrieve data
const value = await WorkzSDK.kv.get('key');
const settings = await WorkzSDK.kv.get('user-settings');

// Delete data
await WorkzSDK.kv.delete('key');

// List all keys
const keys = await WorkzSDK.kv.keys();
```

#### Flutter
```dart
// Store data
await WorkzSDK.kv.set('key', 'value');
await WorkzSDK.kv.set('user-settings', {'theme': 'dark', 'language': 'en'});

// Retrieve data
final value = await WorkzSDK.kv.get('key');
final settings = await WorkzSDK.kv.get('user-settings');

// Delete data
await WorkzSDK.kv.delete('key');

// List all keys
final keys = await WorkzSDK.kv.keys();
```

### Profile API

Access user profile information.

#### JavaScript
```javascript
// Get current user profile
const profile = await WorkzSDK.profile.get();
// Returns: { id, name, email, avatar, ... }

// Update profile (if permissions allow)
await WorkzSDK.profile.update({ name: 'New Name' });
```

#### Flutter
```dart
// Get current user profile
final profile = await WorkzSDK.profile.get();
// Returns: Profile object with id, name, email, avatar, etc.

// Update profile (if permissions allow)
await WorkzSDK.profile.update(name: 'New Name');
```

### HTTP Client

Make authenticated API calls to external services.

#### JavaScript
```javascript
// GET request
const response = await WorkzSDK.http.get('https://api.example.com/data');

// POST request
const result = await WorkzSDK.http.post('https://api.example.com/submit', {
  data: 'payload'
});

// Custom headers
const response = await WorkzSDK.http.get('https://api.example.com/data', {
  headers: { 'Custom-Header': 'value' }
});
```

#### Flutter
```dart
// GET request
final response = await WorkzSDK.http.get('https://api.example.com/data');

// POST request
final result = await WorkzSDK.http.post(
  'https://api.example.com/submit',
  data: {'data': 'payload'},
);

// Custom headers
final response = await WorkzSDK.http.get(
  'https://api.example.com/data',
  headers: {'Custom-Header': 'value'},
);
```

## Platform-Specific Features

### Flutter Web

When running Flutter apps on the web, the SDK uses JavaScript interop to communicate with the platform:

```dart
// The SDK automatically detects the platform and uses appropriate methods
// No special configuration needed for web deployment
```

### Flutter Native (iOS/Android)

For native mobile apps, the SDK uses platform channels:

```dart
// Native features are automatically available
// The SDK handles platform-specific implementations

// Example: Native file picker (mobile only)
if (WorkzSDK.platform.isMobile) {
  final file = await WorkzSDK.files.pickFile();
}
```

### JavaScript Web

For pure JavaScript apps, all features are available through the web API:

```javascript
// Full feature set available in web environment
// Optimized for iframe execution within Workz! platform
```

## Error Handling

### JavaScript
```javascript
try {
  const data = await WorkzSDK.kv.get('key');
} catch (error) {
  if (error.code === 'NETWORK_ERROR') {
    console.log('Network connection failed');
  } else if (error.code === 'PERMISSION_DENIED') {
    console.log('Insufficient permissions');
  }
}
```

### Flutter
```dart
try {
  final data = await WorkzSDK.kv.get('key');
} catch (error) {
  if (error is NetworkError) {
    print('Network connection failed');
  } else if (error is PermissionError) {
    print('Insufficient permissions');
  }
}
```

## Configuration

### SDK Initialization Options

```javascript
// JavaScript
await WorkzSDK.init({
  apiUrl: 'https://api.workz.com',
  token: 'your-app-token',
  debug: true, // Enable debug logging
  timeout: 30000, // Request timeout in ms
  retries: 3 // Number of retry attempts
});
```

```dart
// Flutter
await WorkzSDK.init(
  apiUrl: 'https://api.workz.com',
  token: 'your-app-token',
  debug: true, // Enable debug logging
  timeout: Duration(seconds: 30), // Request timeout
  retries: 3, // Number of retry attempts
);
```

### Environment Configuration

The SDK automatically detects the environment and configures itself appropriately:

- **Development**: Enhanced logging and debugging features
- **Production**: Optimized performance and minimal logging
- **Testing**: Mock responses and isolated storage

## Best Practices

### 1. Initialize Early
Always initialize the SDK before making any API calls:

```javascript
// JavaScript - in your app's main entry point
document.addEventListener('DOMContentLoaded', async () => {
  await WorkzSDK.init({ /* config */ });
  // Now safe to use SDK features
});
```

```dart
// Flutter - in your main() function
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await WorkzSDK.init(/* config */);
  runApp(MyApp());
}
```

### 2. Handle Errors Gracefully
Always wrap SDK calls in try-catch blocks:

```javascript
// JavaScript
async function saveUserPreference(key, value) {
  try {
    await WorkzSDK.kv.set(key, value);
    return { success: true };
  } catch (error) {
    console.error('Failed to save preference:', error);
    return { success: false, error: error.message };
  }
}
```

### 3. Use Appropriate Storage Keys
Use descriptive, namespaced keys for KV storage:

```javascript
// Good
await WorkzSDK.kv.set('user.preferences.theme', 'dark');
await WorkzSDK.kv.set('app.state.currentView', 'dashboard');

// Avoid
await WorkzSDK.kv.set('theme', 'dark');
await WorkzSDK.kv.set('view', 'dashboard');
```

### 4. Optimize for Performance
Cache frequently accessed data:

```javascript
// Cache user profile to avoid repeated API calls
let cachedProfile = null;

async function getUserProfile() {
  if (!cachedProfile) {
    cachedProfile = await WorkzSDK.profile.get();
  }
  return cachedProfile;
}
```

## Troubleshooting

### Common Issues

#### 1. SDK Not Initialized
**Error**: `SDK not initialized. Call WorkzSDK.init() first.`

**Solution**: Ensure you call `WorkzSDK.init()` before using any SDK features.

#### 2. Network Timeout
**Error**: `Request timeout after 30000ms`

**Solution**: Check your network connection or increase the timeout in SDK configuration.

#### 3. Permission Denied
**Error**: `Permission denied for scope: storage.kv.write`

**Solution**: Verify your app token has the required permissions in the Workz! dashboard.

#### 4. Flutter Web Interop Issues
**Error**: `JavaScript interop failed`

**Solution**: Ensure the WorkzSDK JavaScript is loaded before your Flutter web app initializes.

### Debug Mode

Enable debug mode for detailed logging:

```javascript
// JavaScript
await WorkzSDK.init({
  // ... other config
  debug: true
});

// Check logs in browser console
```

```dart
// Flutter
await WorkzSDK.init(
  // ... other config
  debug: true,
);

// Check logs in Flutter console
```

## Migration Guide

### From SDK v1.x to v2.x

The unified SDK v2.x provides a consistent API across all platforms. Here's how to migrate:

#### API Changes

**Old (v1.x)**:
```javascript
// Different APIs for different platforms
WorkzJS.storage.set('key', 'value');
WorkzFlutter.kvStore.setValue('key', 'value');
```

**New (v2.x)**:
```javascript
// Unified API
WorkzSDK.kv.set('key', 'value');
```

#### Initialization Changes

**Old (v1.x)**:
```javascript
WorkzJS.configure({ token: 'token' });
```

**New (v2.x)**:
```javascript
await WorkzSDK.init({ 
  apiUrl: 'https://api.workz.com',
  token: 'token' 
});
```

#### Breaking Changes

1. **Async Initialization**: SDK initialization is now async
2. **Unified Namespace**: All APIs are under `WorkzSDK` namespace
3. **Promise-based**: All methods return Promises (JavaScript) or Futures (Flutter)
4. **Error Handling**: Standardized error types across platforms

### Migration Steps

1. **Update Dependencies**:
   ```json
   // package.json (JavaScript)
   {
     "dependencies": {
       "workz-sdk": "^2.0.0"
     }
   }
   ```

   ```yaml
   # pubspec.yaml (Flutter)
   dependencies:
     workz_sdk: ^2.0.0
   ```

2. **Update Initialization Code**:
   Replace synchronous configuration with async initialization.

3. **Update API Calls**:
   Replace platform-specific APIs with unified SDK methods.

4. **Add Error Handling**:
   Wrap SDK calls in try-catch blocks for proper error handling.

5. **Test Thoroughly**:
   Test all SDK integrations to ensure compatibility.

## API Reference

### WorkzSDK.init(config)

Initialize the SDK with configuration options.

**Parameters**:
- `config.apiUrl` (string): The Workz! API base URL
- `config.token` (string): Your app authentication token
- `config.debug` (boolean, optional): Enable debug logging
- `config.timeout` (number, optional): Request timeout in milliseconds
- `config.retries` (number, optional): Number of retry attempts

**Returns**: Promise<void>

### WorkzSDK.auth

Authentication and token management.

#### Methods

- `isAuthenticated()`: Promise<boolean>
- `getTokenInfo()`: Promise<TokenInfo>
- `refreshToken()`: Promise<void>

### WorkzSDK.kv

Key-value storage for persistent data.

#### Methods

- `get(key: string)`: Promise<any>
- `set(key: string, value: any)`: Promise<void>
- `delete(key: string)`: Promise<void>
- `keys()`: Promise<string[]>
- `clear()`: Promise<void>

### WorkzSDK.profile

User profile information and management.

#### Methods

- `get()`: Promise<Profile>
- `update(data: Partial<Profile>)`: Promise<Profile>

### WorkzSDK.http

HTTP client for external API calls.

#### Methods

- `get(url: string, options?: RequestOptions)`: Promise<Response>
- `post(url: string, data?: any, options?: RequestOptions)`: Promise<Response>
- `put(url: string, data?: any, options?: RequestOptions)`: Promise<Response>
- `delete(url: string, options?: RequestOptions)`: Promise<Response>

### WorkzSDK.platform

Platform detection and capabilities.

#### Properties

- `isWeb`: boolean
- `isMobile`: boolean
- `isDesktop`: boolean
- `platform`: 'web' | 'ios' | 'android' | 'windows' | 'macos' | 'linux'

## Support

For additional help and support:

- **Documentation**: [https://docs.workz.com/sdk](https://docs.workz.com/sdk)
- **GitHub Issues**: [https://github.com/workz/sdk/issues](https://github.com/workz/sdk/issues)
- **Community Forum**: [https://community.workz.com](https://community.workz.com)
- **Email Support**: sdk-support@workz.com