# Platform Integration Guides

## JavaScript Web Apps

### Basic Setup

1. **Include the SDK in your HTML**:
```html
<!DOCTYPE html>
<html>
<head>
    <title>My Workz App</title>
</head>
<body>
    <div id="app"></div>
    
    <!-- WorkzSDK is automatically available in the Workz! runtime -->
    <script>
        // SDK is pre-loaded, no need to include separately
    </script>
</body>
</html>
```

2. **Initialize in your JavaScript**:
```javascript
// main.js
document.addEventListener('DOMContentLoaded', async () => {
    try {
        await WorkzSDK.init({
            apiUrl: 'https://api.workz.com',
            token: window.WORKZ_APP_TOKEN // Automatically provided by platform
        });
        
        console.log('SDK initialized successfully');
        initializeApp();
    } catch (error) {
        console.error('SDK initialization failed:', error);
    }
});

async function initializeApp() {
    // Your app logic here
    const profile = await WorkzSDK.profile.get();
    document.getElementById('app').innerHTML = `
        <h1>Welcome, ${profile.name}!</h1>
    `;
}
```

### Framework Integration

#### React
```javascript
// App.js
import React, { useEffect, useState } from 'react';

function App() {
    const [profile, setProfile] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        async function initSDK() {
            try {
                await WorkzSDK.init({
                    apiUrl: 'https://api.workz.com',
                    token: window.WORKZ_APP_TOKEN
                });
                
                const userProfile = await WorkzSDK.profile.get();
                setProfile(userProfile);
            } catch (error) {
                console.error('SDK error:', error);
            } finally {
                setLoading(false);
            }
        }
        
        initSDK();
    }, []);

    if (loading) return <div>Loading...</div>;

    return (
        <div>
            <h1>Welcome, {profile?.name}!</h1>
        </div>
    );
}

export default App;
```

#### Vue.js
```javascript
// main.js
import { createApp } from 'vue';
import App from './App.vue';

async function initApp() {
    try {
        await WorkzSDK.init({
            apiUrl: 'https://api.workz.com',
            token: window.WORKZ_APP_TOKEN
        });
        
        const app = createApp(App);
        app.config.globalProperties.$workz = WorkzSDK;
        app.mount('#app');
    } catch (error) {
        console.error('SDK initialization failed:', error);
    }
}

initApp();
```

### Storage Patterns

```javascript
// Reactive data store
class AppStore {
    constructor() {
        this.data = {};
        this.listeners = [];
    }
    
    async get(key) {
        if (!(key in this.data)) {
            this.data[key] = await WorkzSDK.kv.get(key);
        }
        return this.data[key];
    }
    
    async set(key, value) {
        this.data[key] = value;
        await WorkzSDK.kv.set(key, value);
        this.notifyListeners(key, value);
    }
    
    subscribe(callback) {
        this.listeners.push(callback);
        return () => {
            const index = this.listeners.indexOf(callback);
            if (index > -1) this.listeners.splice(index, 1);
        };
    }
    
    notifyListeners(key, value) {
        this.listeners.forEach(callback => callback(key, value));
    }
}

const store = new AppStore();
```

## Flutter Web Apps

### Project Setup

1. **Add WorkzSDK dependency**:
```yaml
# pubspec.yaml
name: my_workz_app
description: A Workz! Flutter application

dependencies:
  flutter:
    sdk: flutter
  workz_sdk: ^2.0.0

dev_dependencies:
  flutter_test:
    sdk: flutter

flutter:
  uses-material-design: true
```

2. **Configure web integration**:
```html
<!-- web/index.html -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Workz App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div id="loading">Loading...</div>
    
    <!-- WorkzSDK JavaScript (automatically provided by Workz! platform) -->
    <script src="workz-sdk-v2.js"></script>
    
    <script>
        // Make SDK available to Flutter
        window.workzSDK = WorkzSDK;
    </script>
    
    <script src="main.dart.js" type="application/javascript"></script>
</body>
</html>
```

3. **Initialize in Flutter**:
```dart
// lib/main.dart
import 'package:flutter/material.dart';
import 'package:workz_sdk/workz_sdk.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  try {
    await WorkzSDK.init(
      apiUrl: 'https://api.workz.com',
      token: const String.fromEnvironment('WORKZ_APP_TOKEN'),
    );
    
    runApp(MyApp());
  } catch (error) {
    print('SDK initialization failed: $error');
    runApp(ErrorApp(error: error.toString()));
  }
}

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'My Workz App',
      home: HomePage(),
    );
  }
}

class HomePage extends StatefulWidget {
  @override
  _HomePageState createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  Profile? profile;
  bool loading = true;

  @override
  void initState() {
    super.initState();
    loadProfile();
  }

  Future<void> loadProfile() async {
    try {
      final userProfile = await WorkzSDK.profile.get();
      setState(() {
        profile = userProfile;
        loading = false;
      });
    } catch (error) {
      print('Failed to load profile: $error');
      setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      appBar: AppBar(title: Text('Welcome')),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text('Hello, ${profile?.name ?? 'User'}!'),
            ElevatedButton(
              onPressed: () => savePreference(),
              child: Text('Save Preference'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> savePreference() async {
    try {
      await WorkzSDK.kv.set('theme', 'dark');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Preference saved!')),
      );
    } catch (error) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to save: $error')),
      );
    }
  }
}
```

### State Management Integration

#### Provider Pattern
```dart
// lib/providers/app_provider.dart
import 'package:flutter/foundation.dart';
import 'package:workz_sdk/workz_sdk.dart';

class AppProvider extends ChangeNotifier {
  Profile? _profile;
  Map<String, dynamic> _preferences = {};
  bool _loading = false;

  Profile? get profile => _profile;
  Map<String, dynamic> get preferences => _preferences;
  bool get loading => _loading;

  Future<void> loadProfile() async {
    _loading = true;
    notifyListeners();
    
    try {
      _profile = await WorkzSDK.profile.get();
    } catch (error) {
      print('Failed to load profile: $error');
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> setPreference(String key, dynamic value) async {
    try {
      await WorkzSDK.kv.set('pref_$key', value);
      _preferences[key] = value;
      notifyListeners();
    } catch (error) {
      print('Failed to save preference: $error');
    }
  }

  Future<dynamic> getPreference(String key) async {
    if (!_preferences.containsKey(key)) {
      try {
        final value = await WorkzSDK.kv.get('pref_$key');
        _preferences[key] = value;
      } catch (error) {
        print('Failed to load preference: $error');
      }
    }
    return _preferences[key];
  }
}
```

## Flutter Native Apps (iOS/Android)

### iOS Setup

1. **Configure iOS project**:
```ruby
# ios/Podfile
platform :ios, '11.0'

target 'Runner' do
  use_frameworks!
  use_modular_headers!

  flutter_install_all_ios_pods File.dirname(File.realpath(__FILE__))
end
```

2. **Add permissions** (if needed):
```xml
<!-- ios/Runner/Info.plist -->
<dict>
    <!-- Other keys -->
    <key>NSAppTransportSecurity</key>
    <dict>
        <key>NSAllowsArbitraryLoads</key>
        <true/>
    </dict>
</dict>
```

### Android Setup

1. **Configure Android project**:
```gradle
// android/app/build.gradle
android {
    compileSdkVersion 33
    
    defaultConfig {
        minSdkVersion 21
        targetSdkVersion 33
    }
}
```

2. **Add permissions**:
```xml
<!-- android/app/src/main/AndroidManifest.xml -->
<manifest xmlns:android="http://schemas.android.com/apk/res/android">
    <uses-permission android:name="android.permission.INTERNET" />
    
    <application
        android:label="My Workz App"
        android:icon="@mipmap/ic_launcher">
        <!-- Activity configuration -->
    </application>
</manifest>
```

### Native Features

```dart
// lib/services/native_service.dart
import 'package:workz_sdk/workz_sdk.dart';

class NativeService {
  static Future<bool> get isNativePlatform async {
    return WorkzSDK.platform.isMobile;
  }

  static Future<void> saveToNativeStorage(String key, dynamic value) async {
    if (await isNativePlatform) {
      // Use native storage capabilities
      await WorkzSDK.kv.set(key, value);
    } else {
      // Fallback to web storage
      await WorkzSDK.kv.set(key, value);
    }
  }

  static Future<String?> pickFile() async {
    if (await isNativePlatform) {
      // Native file picker available
      return await WorkzSDK.files.pickFile();
    } else {
      // Web file picker or alternative
      throw UnsupportedError('File picker not available on web');
    }
  }
}
```

## Flutter Desktop Apps

### Windows Setup

1. **Configure Windows build**:
```cmake
# windows/CMakeLists.txt
cmake_minimum_required(VERSION 3.14)
project(my_workz_app LANGUAGES CXX)

set(BINARY_NAME "my_workz_app")

flutter_build(${BINARY_NAME})
```

2. **Desktop-specific features**:
```dart
// lib/desktop/desktop_features.dart
import 'package:workz_sdk/workz_sdk.dart';

class DesktopFeatures {
  static Future<void> setupDesktopIntegration() async {
    if (WorkzSDK.platform.isDesktop) {
      // Desktop-specific initialization
      await WorkzSDK.desktop.setupSystemTray();
      await WorkzSDK.desktop.registerProtocolHandler('workz');
    }
  }

  static Future<void> saveToDesktopFile(String content) async {
    if (WorkzSDK.platform.isDesktop) {
      final path = await WorkzSDK.desktop.showSaveDialog();
      if (path != null) {
        await WorkzSDK.files.writeFile(path, content);
      }
    }
  }
}
```

## Cross-Platform Considerations

### Responsive Design

```dart
// lib/utils/responsive.dart
import 'package:flutter/material.dart';
import 'package:workz_sdk/workz_sdk.dart';

class ResponsiveBuilder extends StatelessWidget {
  final Widget mobile;
  final Widget? tablet;
  final Widget desktop;

  const ResponsiveBuilder({
    Key? key,
    required this.mobile,
    this.tablet,
    required this.desktop,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (WorkzSDK.platform.isDesktop || constraints.maxWidth >= 1200) {
          return desktop;
        } else if (constraints.maxWidth >= 600) {
          return tablet ?? mobile;
        } else {
          return mobile;
        }
      },
    );
  }
}
```

### Platform-Specific Code

```dart
// lib/utils/platform_utils.dart
import 'package:workz_sdk/workz_sdk.dart';

class PlatformUtils {
  static Future<void> showNotification(String message) async {
    if (WorkzSDK.platform.isMobile) {
      await WorkzSDK.notifications.show(message);
    } else if (WorkzSDK.platform.isDesktop) {
      await WorkzSDK.desktop.showSystemNotification(message);
    } else {
      // Web fallback
      print('Notification: $message');
    }
  }

  static Future<String?> selectFile() async {
    if (WorkzSDK.platform.isMobile) {
      return await WorkzSDK.files.pickFile();
    } else if (WorkzSDK.platform.isDesktop) {
      return await WorkzSDK.desktop.showOpenDialog();
    } else {
      return await WorkzSDK.web.selectFile();
    }
  }
}
```

## Testing Integration

### JavaScript Testing

```javascript
// tests/sdk.test.js
describe('WorkzSDK Integration', () => {
    beforeAll(async () => {
        // Mock the SDK for testing
        global.WorkzSDK = {
            init: jest.fn().mockResolvedValue(undefined),
            kv: {
                get: jest.fn(),
                set: jest.fn(),
            },
            profile: {
                get: jest.fn().mockResolvedValue({
                    id: '123',
                    name: 'Test User',
                    email: 'test@example.com'
                })
            }
        };
    });

    test('should initialize SDK', async () => {
        await WorkzSDK.init({
            apiUrl: 'https://api.workz.com',
            token: 'test-token'
        });
        
        expect(WorkzSDK.init).toHaveBeenCalledWith({
            apiUrl: 'https://api.workz.com',
            token: 'test-token'
        });
    });

    test('should get user profile', async () => {
        const profile = await WorkzSDK.profile.get();
        expect(profile.name).toBe('Test User');
    });
});
```

### Flutter Testing

```dart
// test/sdk_test.dart
import 'package:flutter_test/flutter_test.dart';
import 'package:mockito/mockito.dart';
import 'package:workz_sdk/workz_sdk.dart';

class MockWorkzSDK extends Mock implements WorkzSDK {}

void main() {
  group('WorkzSDK Integration', () {
    late MockWorkzSDK mockSDK;

    setUp(() {
      mockSDK = MockWorkzSDK();
    });

    test('should initialize SDK', () async {
      when(mockSDK.init(
        apiUrl: anyNamed('apiUrl'),
        token: anyNamed('token'),
      )).thenAnswer((_) async {});

      await mockSDK.init(
        apiUrl: 'https://api.workz.com',
        token: 'test-token',
      );

      verify(mockSDK.init(
        apiUrl: 'https://api.workz.com',
        token: 'test-token',
      )).called(1);
    });
  });
}
```

## Deployment Considerations

### JavaScript Apps
- Ensure all dependencies are bundled or available via CDN
- Minify code for production builds
- Test in the Workz! iframe environment

### Flutter Web Apps
- Build with `flutter build web --release`
- Ensure JavaScript interop is properly configured
- Test cross-browser compatibility

### Flutter Native Apps
- Build platform-specific binaries
- Test on actual devices when possible
- Configure proper signing and certificates

### Flutter Desktop Apps
- Build for target platforms: Windows, macOS, Linux
- Package with appropriate installers
- Test on different OS versions

## Performance Optimization

### Lazy Loading
```dart
// lib/utils/lazy_loader.dart
class LazyLoader<T> {
  T? _value;
  final Future<T> Function() _loader;

  LazyLoader(this._loader);

  Future<T> get value async {
    _value ??= await _loader();
    return _value!;
  }
}

// Usage
final lazyProfile = LazyLoader(() => WorkzSDK.profile.get());
```

### Caching Strategy
```javascript
// js/cache.js
class SDKCache {
    constructor(ttl = 300000) { // 5 minutes default
        this.cache = new Map();
        this.ttl = ttl;
    }
    
    async get(key, fetcher) {
        const cached = this.cache.get(key);
        
        if (cached && Date.now() - cached.timestamp < this.ttl) {
            return cached.value;
        }
        
        const value = await fetcher();
        this.cache.set(key, {
            value,
            timestamp: Date.now()
        });
        
        return value;
    }
}

const cache = new SDKCache();

// Usage
const profile = await cache.get('profile', () => WorkzSDK.profile.get());
```

## Security Best Practices

### Token Management
- Never hardcode tokens in client-side code
- Use environment variables or secure configuration
- Implement token refresh logic for long-running apps

### Data Validation
```dart
// lib/utils/validation.dart
class DataValidator {
  static bool isValidEmail(String email) {
    return RegExp(r'^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$').hasMatch(email);
  }

  static String sanitizeInput(String input) {
    return input.replaceAll(RegExp(r'[<>"\']'), '');
  }

  static Future<void> validateAndSave(String key, dynamic value) async {
    if (key.isEmpty || value == null) {
      throw ArgumentError('Invalid key or value');
    }
    
    await WorkzSDK.kv.set(key, value);
  }
}
```

### Error Boundaries
```dart
// lib/widgets/error_boundary.dart
class ErrorBoundary extends StatefulWidget {
  final Widget child;
  final Widget Function(Object error)? errorBuilder;

  const ErrorBoundary({
    Key? key,
    required this.child,
    this.errorBuilder,
  }) : super(key: key);

  @override
  _ErrorBoundaryState createState() => _ErrorBoundaryState();
}

class _ErrorBoundaryState extends State<ErrorBoundary> {
  Object? error;

  @override
  Widget build(BuildContext context) {
    if (error != null) {
      return widget.errorBuilder?.call(error!) ?? 
        Center(child: Text('An error occurred: $error'));
    }
    
    return widget.child;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    
    FlutterError.onError = (details) {
      setState(() {
        error = details.exception;
      });
    };
  }
}
```