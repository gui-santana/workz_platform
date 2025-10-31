# Developer Tutorials and Examples

## Getting Started with Workz! Development

This guide provides step-by-step tutorials for building applications on the Workz! platform, covering both JavaScript and Flutter development approaches.

## Tutorial 1: Building Your First JavaScript App

### Overview
Create a simple task management app using JavaScript and the Workz! SDK.

### Prerequisites
- Basic JavaScript knowledge
- Understanding of HTML/CSS
- Access to Workz! App Builder

### Step 1: Project Setup

1. **Create a new app in App Builder**:
   - Navigate to the Workz! dashboard
   - Click "Create New App"
   - Choose "JavaScript" as the app type
   - Name your app "Task Manager"

2. **Initialize the basic structure**:

```html
<!-- index.html -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .task-input {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .task-input input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .task-input button {
            padding: 12px 24px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .task-input button:hover {
            background: #0056b3;
        }
        
        .task-list {
            list-style: none;
            padding: 0;
        }
        
        .task-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 1px solid #eee;
            border-radius: 4px;
            margin-bottom: 8px;
            background: #fafafa;
        }
        
        .task-item.completed {
            opacity: 0.6;
            text-decoration: line-through;
        }
        
        .task-item input[type="checkbox"] {
            margin-right: 12px;
        }
        
        .task-text {
            flex: 1;
        }
        
        .task-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .task-delete:hover {
            background: #c82333;
        }
        
        .stats {
            margin-top: 24px;
            padding: 16px;
            background: #e9ecef;
            border-radius: 4px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Task Manager</h1>
        
        <div class="task-input">
            <input type="text" id="taskInput" placeholder="Enter a new task..." />
            <button onclick="addTask()">Add Task</button>
        </div>
        
        <ul id="taskList" class="task-list"></ul>
        
        <div class="stats">
            <span id="taskStats">0 tasks</span>
        </div>
    </div>

    <script src="main.js"></script>
</body>
</html>
```

### Step 2: Implement Core Functionality

```javascript
// main.js
class TaskManager {
    constructor() {
        this.tasks = [];
        this.taskIdCounter = 1;
        this.init();
    }
    
    async init() {
        try {
            // Initialize Workz SDK
            await WorkzSDK.init({
                apiUrl: 'https://api.workz.com',
                token: window.WORKZ_APP_TOKEN
            });
            
            console.log('Workz SDK initialized successfully');
            
            // Load existing tasks from storage
            await this.loadTasks();
            
            // Render initial task list
            this.renderTasks();
            
            // Setup event listeners
            this.setupEventListeners();
            
        } catch (error) {
            console.error('Failed to initialize app:', error);
            alert('Failed to initialize the app. Please refresh and try again.');
        }
    }
    
    async loadTasks() {
        try {
            const savedTasks = await WorkzSDK.kv.get('tasks');
            if (savedTasks) {
                this.tasks = JSON.parse(savedTasks);
                this.taskIdCounter = Math.max(...this.tasks.map(t => t.id), 0) + 1;
            }
        } catch (error) {
            console.error('Failed to load tasks:', error);
        }
    }
    
    async saveTasks() {
        try {
            await WorkzSDK.kv.set('tasks', JSON.stringify(this.tasks));
        } catch (error) {
            console.error('Failed to save tasks:', error);
        }
    }
    
    setupEventListeners() {
        const taskInput = document.getElementById('taskInput');
        
        // Allow adding tasks with Enter key
        taskInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.addTask();
            }
        });
    }
    
    async addTask() {
        const taskInput = document.getElementById('taskInput');
        const taskText = taskInput.value.trim();
        
        if (!taskText) {
            alert('Please enter a task description');
            return;
        }
        
        const newTask = {
            id: this.taskIdCounter++,
            text: taskText,
            completed: false,
            createdAt: new Date().toISOString()
        };
        
        this.tasks.push(newTask);
        taskInput.value = '';
        
        await this.saveTasks();
        this.renderTasks();
    }
    
    async toggleTask(taskId) {
        const task = this.tasks.find(t => t.id === taskId);
        if (task) {
            task.completed = !task.completed;
            task.completedAt = task.completed ? new Date().toISOString() : null;
            
            await this.saveTasks();
            this.renderTasks();
        }
    }
    
    async deleteTask(taskId) {
        if (confirm('Are you sure you want to delete this task?')) {
            this.tasks = this.tasks.filter(t => t.id !== taskId);
            await this.saveTasks();
            this.renderTasks();
        }
    }
    
    renderTasks() {
        const taskList = document.getElementById('taskList');
        const taskStats = document.getElementById('taskStats');
        
        // Clear existing tasks
        taskList.innerHTML = '';
        
        // Render each task
        this.tasks.forEach(task => {
            const taskItem = document.createElement('li');
            taskItem.className = `task-item ${task.completed ? 'completed' : ''}`;
            
            taskItem.innerHTML = `
                <input 
                    type="checkbox" 
                    ${task.completed ? 'checked' : ''} 
                    onchange="taskManager.toggleTask(${task.id})"
                />
                <span class="task-text">${this.escapeHtml(task.text)}</span>
                <button 
                    class="task-delete" 
                    onclick="taskManager.deleteTask(${task.id})"
                >
                    Delete
                </button>
            `;
            
            taskList.appendChild(taskItem);
        });
        
        // Update statistics
        const totalTasks = this.tasks.length;
        const completedTasks = this.tasks.filter(t => t.completed).length;
        const pendingTasks = totalTasks - completedTasks;
        
        taskStats.textContent = `${totalTasks} total, ${completedTasks} completed, ${pendingTasks} pending`;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Global functions for HTML event handlers
let taskManager;

function addTask() {
    taskManager.addTask();
}

// Initialize the app when the page loads
document.addEventListener('DOMContentLoaded', () => {
    taskManager = new TaskManager();
});
```

### Step 3: Add Advanced Features

```javascript
// Enhanced features for the task manager
class EnhancedTaskManager extends TaskManager {
    constructor() {
        super();
        this.categories = ['Personal', 'Work', 'Shopping', 'Health'];
        this.currentFilter = 'all';
    }
    
    async init() {
        await super.init();
        this.setupAdvancedFeatures();
    }
    
    setupAdvancedFeatures() {
        this.addCategoryFilter();
        this.addSearchFunctionality();
        this.addExportFeature();
        this.setupUserProfile();
    }
    
    addCategoryFilter() {
        const container = document.querySelector('.container');
        const filterHTML = `
            <div class="filters" style="margin-bottom: 20px;">
                <label>Filter by category:</label>
                <select id="categoryFilter" onchange="taskManager.filterTasks(this.value)">
                    <option value="all">All Tasks</option>
                    <option value="completed">Completed</option>
                    <option value="pending">Pending</option>
                    ${this.categories.map(cat => `<option value="${cat}">${cat}</option>`).join('')}
                </select>
                
                <label style="margin-left: 20px;">Search:</label>
                <input type="text" id="searchInput" placeholder="Search tasks..." 
                       oninput="taskManager.searchTasks(this.value)" />
            </div>
        `;
        
        container.insertAdjacentHTML('afterbegin', filterHTML);
    }
    
    addSearchFunctionality() {
        // Search functionality is handled by the input event listener
    }
    
    addExportFeature() {
        const statsDiv = document.querySelector('.stats');
        const exportButton = document.createElement('button');
        exportButton.textContent = 'Export Tasks';
        exportButton.onclick = () => this.exportTasks();
        exportButton.style.marginLeft = '20px';
        
        statsDiv.appendChild(exportButton);
    }
    
    async setupUserProfile() {
        try {
            const profile = await WorkzSDK.profile.get();
            const welcomeMessage = document.createElement('div');
            welcomeMessage.innerHTML = `<p>Welcome back, ${profile.name}!</p>`;
            welcomeMessage.style.textAlign = 'center';
            welcomeMessage.style.marginBottom = '20px';
            welcomeMessage.style.color = '#666';
            
            document.querySelector('.container').insertAdjacentElement('afterbegin', welcomeMessage);
        } catch (error) {
            console.error('Failed to load user profile:', error);
        }
    }
    
    filterTasks(filter) {
        this.currentFilter = filter;
        this.renderTasks();
    }
    
    searchTasks(query) {
        this.searchQuery = query.toLowerCase();
        this.renderTasks();
    }
    
    getFilteredTasks() {
        let filteredTasks = [...this.tasks];
        
        // Apply category/status filter
        if (this.currentFilter === 'completed') {
            filteredTasks = filteredTasks.filter(t => t.completed);
        } else if (this.currentFilter === 'pending') {
            filteredTasks = filteredTasks.filter(t => !t.completed);
        } else if (this.categories.includes(this.currentFilter)) {
            filteredTasks = filteredTasks.filter(t => t.category === this.currentFilter);
        }
        
        // Apply search filter
        if (this.searchQuery) {
            filteredTasks = filteredTasks.filter(t => 
                t.text.toLowerCase().includes(this.searchQuery)
            );
        }
        
        return filteredTasks;
    }
    
    renderTasks() {
        const taskList = document.getElementById('taskList');
        const taskStats = document.getElementById('taskStats');
        
        // Get filtered tasks
        const filteredTasks = this.getFilteredTasks();
        
        // Clear existing tasks
        taskList.innerHTML = '';
        
        // Render filtered tasks
        filteredTasks.forEach(task => {
            const taskItem = document.createElement('li');
            taskItem.className = `task-item ${task.completed ? 'completed' : ''}`;
            
            taskItem.innerHTML = `
                <input 
                    type="checkbox" 
                    ${task.completed ? 'checked' : ''} 
                    onchange="taskManager.toggleTask(${task.id})"
                />
                <span class="task-text">
                    ${this.escapeHtml(task.text)}
                    ${task.category ? `<small style="color: #666;"> (${task.category})</small>` : ''}
                </span>
                <button 
                    class="task-delete" 
                    onclick="taskManager.deleteTask(${task.id})"
                >
                    Delete
                </button>
            `;
            
            taskList.appendChild(taskItem);
        });
        
        // Update statistics
        const totalTasks = this.tasks.length;
        const completedTasks = this.tasks.filter(t => t.completed).length;
        const pendingTasks = totalTasks - completedTasks;
        const filteredCount = filteredTasks.length;
        
        taskStats.textContent = `Showing ${filteredCount} of ${totalTasks} tasks (${completedTasks} completed, ${pendingTasks} pending)`;
    }
    
    async exportTasks() {
        try {
            const exportData = {
                tasks: this.tasks,
                exportDate: new Date().toISOString(),
                totalTasks: this.tasks.length,
                completedTasks: this.tasks.filter(t => t.completed).length
            };
            
            const dataStr = JSON.stringify(exportData, null, 2);
            const dataBlob = new Blob([dataStr], { type: 'application/json' });
            
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `tasks-export-${new Date().toISOString().split('T')[0]}.json`;
            link.click();
            
            URL.revokeObjectURL(url);
            
        } catch (error) {
            console.error('Failed to export tasks:', error);
            alert('Failed to export tasks. Please try again.');
        }
    }
}

// Use the enhanced version
document.addEventListener('DOMContentLoaded', () => {
    taskManager = new EnhancedTaskManager();
});
```

### Step 4: Testing and Deployment

1. **Test your app**:
   - Add several tasks
   - Mark some as completed
   - Test the search and filter functionality
   - Verify data persistence by refreshing the page

2. **Deploy your app**:
   - Save your changes in the App Builder
   - The app will be automatically deployed
   - Share the app URL with others

## Tutorial 2: Building Your First Flutter App

### Overview
Create a weather app using Flutter and the Workz! SDK.

### Prerequisites
- Basic Dart/Flutter knowledge
- Understanding of Flutter widgets
- Access to Workz! App Builder

### Step 1: Project Setup

1. **Create a new Flutter app**:
   - Choose "Flutter" as the app type
   - Name your app "Weather App"

2. **Set up the project structure**:

```yaml
# pubspec.yaml
name: weather_app
description: A weather app built with Flutter and Workz SDK

dependencies:
  flutter:
    sdk: flutter
  workz_sdk: ^2.0.0
  http: ^1.1.0

dev_dependencies:
  flutter_test:
    sdk: flutter

flutter:
  uses-material-design: true
  assets:
    - assets/images/
```

### Step 2: Create the Main App Structure

```dart
// lib/main.dart
import 'package:flutter/material.dart';
import 'package:workz_sdk/workz_sdk.dart';
import 'screens/weather_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  try {
    await WorkzSDK.init(
      apiUrl: 'https://api.workz.com',
      token: const String.fromEnvironment('WORKZ_APP_TOKEN'),
    );
    
    runApp(WeatherApp());
  } catch (error) {
    print('Failed to initialize Workz SDK: $error');
    runApp(ErrorApp(error: error.toString()));
  }
}

class WeatherApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Weather App',
      theme: ThemeData(
        primarySwatch: Colors.blue,
        visualDensity: VisualDensity.adaptivePlatformDensity,
      ),
      home: WeatherScreen(),
    );
  }
}

class ErrorApp extends StatelessWidget {
  final String error;
  
  const ErrorApp({Key? key, required this.error}) : super(key: key);
  
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      home: Scaffold(
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.error, size: 64, color: Colors.red),
              SizedBox(height: 16),
              Text('Failed to initialize app'),
              SizedBox(height: 8),
              Text(error, style: TextStyle(fontSize: 12, color: Colors.grey)),
            ],
          ),
        ),
      ),
    );
  }
}
```

### Step 3: Create Weather Models

```dart
// lib/models/weather.dart
class Weather {
  final String city;
  final String country;
  final double temperature;
  final String description;
  final String icon;
  final double humidity;
  final double windSpeed;
  final DateTime timestamp;

  Weather({
    required this.city,
    required this.country,
    required this.temperature,
    required this.description,
    required this.icon,
    required this.humidity,
    required this.windSpeed,
    required this.timestamp,
  });

  factory Weather.fromJson(Map<String, dynamic> json) {
    return Weather(
      city: json['name'] ?? '',
      country: json['sys']['country'] ?? '',
      temperature: (json['main']['temp'] as num).toDouble(),
      description: json['weather'][0]['description'] ?? '',
      icon: json['weather'][0]['icon'] ?? '',
      humidity: (json['main']['humidity'] as num).toDouble(),
      windSpeed: (json['wind']['speed'] as num).toDouble(),
      timestamp: DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'city': city,
      'country': country,
      'temperature': temperature,
      'description': description,
      'icon': icon,
      'humidity': humidity,
      'windSpeed': windSpeed,
      'timestamp': timestamp.toIso8601String(),
    };
  }

  factory Weather.fromStoredJson(Map<String, dynamic> json) {
    return Weather(
      city: json['city'],
      country: json['country'],
      temperature: json['temperature'],
      description: json['description'],
      icon: json['icon'],
      humidity: json['humidity'],
      windSpeed: json['windSpeed'],
      timestamp: DateTime.parse(json['timestamp']),
    );
  }
}
```

### Step 4: Create Weather Service

```dart
// lib/services/weather_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:workz_sdk/workz_sdk.dart';
import '../models/weather.dart';

class WeatherService {
  static const String _apiKey = 'your-openweather-api-key';
  static const String _baseUrl = 'https://api.openweathermap.org/data/2.5';
  
  Future<Weather> getCurrentWeather(String city) async {
    try {
      // Check cache first
      final cachedWeather = await _getCachedWeather(city);
      if (cachedWeather != null && _isCacheValid(cachedWeather.timestamp)) {
        return cachedWeather;
      }
      
      // Fetch from API
      final url = '$_baseUrl/weather?q=$city&appid=$_apiKey&units=metric';
      final response = await http.get(Uri.parse(url));
      
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        final weather = Weather.fromJson(data);
        
        // Cache the result
        await _cacheWeather(city, weather);
        
        return weather;
      } else {
        throw Exception('Failed to load weather data: ${response.statusCode}');
      }
    } catch (error) {
      print('Weather service error: $error');
      rethrow;
    }
  }
  
  Future<List<String>> getFavoriteCities() async {
    try {
      final favoritesJson = await WorkzSDK.kv.get('favorite_cities');
      if (favoritesJson != null) {
        final List<dynamic> favorites = json.decode(favoritesJson);
        return favorites.cast<String>();
      }
      return [];
    } catch (error) {
      print('Failed to load favorite cities: $error');
      return [];
    }
  }
  
  Future<void> addFavoriteCity(String city) async {
    try {
      final favorites = await getFavoriteCities();
      if (!favorites.contains(city)) {
        favorites.add(city);
        await WorkzSDK.kv.set('favorite_cities', json.encode(favorites));
      }
    } catch (error) {
      print('Failed to add favorite city: $error');
    }
  }
  
  Future<void> removeFavoriteCity(String city) async {
    try {
      final favorites = await getFavoriteCities();
      favorites.remove(city);
      await WorkzSDK.kv.set('favorite_cities', json.encode(favorites));
    } catch (error) {
      print('Failed to remove favorite city: $error');
    }
  }
  
  Future<Weather?> _getCachedWeather(String city) async {
    try {
      final cacheKey = 'weather_$city';
      final cachedJson = await WorkzSDK.kv.get(cacheKey);
      if (cachedJson != null) {
        final data = json.decode(cachedJson);
        return Weather.fromStoredJson(data);
      }
      return null;
    } catch (error) {
      print('Failed to get cached weather: $error');
      return null;
    }
  }
  
  Future<void> _cacheWeather(String city, Weather weather) async {
    try {
      final cacheKey = 'weather_$city';
      await WorkzSDK.kv.set(cacheKey, json.encode(weather.toJson()));
    } catch (error) {
      print('Failed to cache weather: $error');
    }
  }
  
  bool _isCacheValid(DateTime timestamp) {
    final now = DateTime.now();
    final difference = now.difference(timestamp);
    return difference.inMinutes < 10; // Cache valid for 10 minutes
  }
}
```

### Step 5: Create the Main Weather Screen

```dart
// lib/screens/weather_screen.dart
import 'package:flutter/material.dart';
import '../models/weather.dart';
import '../services/weather_service.dart';
import '../widgets/weather_card.dart';
import '../widgets/favorites_list.dart';

class WeatherScreen extends StatefulWidget {
  @override
  _WeatherScreenState createState() => _WeatherScreenState();
}

class _WeatherScreenState extends State<WeatherScreen> {
  final WeatherService _weatherService = WeatherService();
  final TextEditingController _cityController = TextEditingController();
  
  Weather? _currentWeather;
  List<String> _favoriteCities = [];
  bool _isLoading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadFavoriteCities();
    _loadDefaultWeather();
  }

  Future<void> _loadFavoriteCities() async {
    try {
      final favorites = await _weatherService.getFavoriteCities();
      setState(() {
        _favoriteCities = favorites;
      });
    } catch (error) {
      print('Failed to load favorite cities: $error');
    }
  }

  Future<void> _loadDefaultWeather() async {
    // Load weather for the first favorite city or a default city
    if (_favoriteCities.isNotEmpty) {
      await _loadWeather(_favoriteCities.first);
    } else {
      await _loadWeather('London');
    }
  }

  Future<void> _loadWeather(String city) async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final weather = await _weatherService.getCurrentWeather(city);
      setState(() {
        _currentWeather = weather;
        _isLoading = false;
      });
    } catch (error) {
      setState(() {
        _error = error.toString();
        _isLoading = false;
      });
    }
  }

  Future<void> _searchWeather() async {
    final city = _cityController.text.trim();
    if (city.isNotEmpty) {
      await _loadWeather(city);
      _cityController.clear();
    }
  }

  Future<void> _toggleFavorite(String city) async {
    if (_favoriteCities.contains(city)) {
      await _weatherService.removeFavoriteCity(city);
    } else {
      await _weatherService.addFavoriteCity(city);
    }
    await _loadFavoriteCities();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Weather App'),
        elevation: 0,
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          if (_currentWeather != null) {
            await _loadWeather(_currentWeather!.city);
          }
        },
        child: SingleChildScrollView(
          physics: AlwaysScrollableScrollPhysics(),
          padding: EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Search bar
              Card(
                child: Padding(
                  padding: EdgeInsets.all(16),
                  child: Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _cityController,
                          decoration: InputDecoration(
                            hintText: 'Enter city name...',
                            border: InputBorder.none,
                          ),
                          onSubmitted: (_) => _searchWeather(),
                        ),
                      ),
                      IconButton(
                        icon: Icon(Icons.search),
                        onPressed: _searchWeather,
                      ),
                    ],
                  ),
                ),
              ),
              
              SizedBox(height: 16),
              
              // Weather display
              if (_isLoading)
                Center(
                  child: Padding(
                    padding: EdgeInsets.all(32),
                    child: CircularProgressIndicator(),
                  ),
                )
              else if (_error != null)
                Card(
                  child: Padding(
                    padding: EdgeInsets.all(16),
                    child: Column(
                      children: [
                        Icon(Icons.error, size: 48, color: Colors.red),
                        SizedBox(height: 8),
                        Text('Error loading weather'),
                        SizedBox(height: 4),
                        Text(_error!, style: TextStyle(fontSize: 12, color: Colors.grey)),
                        SizedBox(height: 8),
                        ElevatedButton(
                          onPressed: () => _loadDefaultWeather(),
                          child: Text('Retry'),
                        ),
                      ],
                    ),
                  ),
                )
              else if (_currentWeather != null)
                WeatherCard(
                  weather: _currentWeather!,
                  isFavorite: _favoriteCities.contains(_currentWeather!.city),
                  onFavoriteToggle: () => _toggleFavorite(_currentWeather!.city),
                ),
              
              SizedBox(height: 16),
              
              // Favorites list
              if (_favoriteCities.isNotEmpty)
                FavoritesList(
                  cities: _favoriteCities,
                  onCityTap: _loadWeather,
                  onCityRemove: _toggleFavorite,
                ),
            ],
          ),
        ),
      ),
    );
  }
}
```

### Step 6: Create Weather Widgets

```dart
// lib/widgets/weather_card.dart
import 'package:flutter/material.dart';
import '../models/weather.dart';

class WeatherCard extends StatelessWidget {
  final Weather weather;
  final bool isFavorite;
  final VoidCallback onFavoriteToggle;

  const WeatherCard({
    Key? key,
    required this.weather,
    required this.isFavorite,
    required this.onFavoriteToggle,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 4,
      child: Padding(
        padding: EdgeInsets.all(20),
        child: Column(
          children: [
            // City name and favorite button
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        weather.city,
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      Text(
                        weather.country,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: Colors.grey[600],
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  icon: Icon(
                    isFavorite ? Icons.favorite : Icons.favorite_border,
                    color: isFavorite ? Colors.red : Colors.grey,
                  ),
                  onPressed: onFavoriteToggle,
                ),
              ],
            ),
            
            SizedBox(height: 20),
            
            // Temperature and description
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                Column(
                  children: [
                    Text(
                      '${weather.temperature.round()}°C',
                      style: Theme.of(context).textTheme.displayMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: _getTemperatureColor(weather.temperature),
                      ),
                    ),
                    Text(
                      weather.description.toUpperCase(),
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        letterSpacing: 1.2,
                      ),
                    ),
                  ],
                ),
                // Weather icon placeholder
                Container(
                  width: 80,
                  height: 80,
                  decoration: BoxDecoration(
                    color: Colors.blue.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(40),
                  ),
                  child: Icon(
                    _getWeatherIcon(weather.icon),
                    size: 40,
                    color: Colors.blue,
                  ),
                ),
              ],
            ),
            
            SizedBox(height: 20),
            
            // Additional weather info
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                _buildWeatherInfo(
                  icon: Icons.water_drop,
                  label: 'Humidity',
                  value: '${weather.humidity.round()}%',
                ),
                _buildWeatherInfo(
                  icon: Icons.air,
                  label: 'Wind Speed',
                  value: '${weather.windSpeed.toStringAsFixed(1)} m/s',
                ),
              ],
            ),
            
            SizedBox(height: 12),
            
            // Last updated
            Text(
              'Updated: ${_formatTime(weather.timestamp)}',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Colors.grey[600],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildWeatherInfo({
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Column(
      children: [
        Icon(icon, size: 24, color: Colors.blue),
        SizedBox(height: 4),
        Text(label, style: TextStyle(fontSize: 12, color: Colors.grey[600])),
        Text(value, style: TextStyle(fontWeight: FontWeight.bold)),
      ],
    );
  }

  Color _getTemperatureColor(double temperature) {
    if (temperature < 0) return Colors.blue;
    if (temperature < 15) return Colors.lightBlue;
    if (temperature < 25) return Colors.green;
    if (temperature < 35) return Colors.orange;
    return Colors.red;
  }

  IconData _getWeatherIcon(String iconCode) {
    // Map weather icon codes to Flutter icons
    switch (iconCode.substring(0, 2)) {
      case '01': return Icons.wb_sunny;
      case '02': return Icons.wb_cloudy;
      case '03':
      case '04': return Icons.cloud;
      case '09':
      case '10': return Icons.grain;
      case '11': return Icons.flash_on;
      case '13': return Icons.ac_unit;
      case '50': return Icons.foggy;
      default: return Icons.wb_sunny;
    }
  }

  String _formatTime(DateTime dateTime) {
    return '${dateTime.hour.toString().padLeft(2, '0')}:${dateTime.minute.toString().padLeft(2, '0')}';
  }
}
```

```dart
// lib/widgets/favorites_list.dart
import 'package:flutter/material.dart';

class FavoritesList extends StatelessWidget {
  final List<String> cities;
  final Function(String) onCityTap;
  final Function(String) onCityRemove;

  const FavoritesList({
    Key? key,
    required this.cities,
    required this.onCityTap,
    required this.onCityRemove,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Favorite Cities',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.bold,
              ),
            ),
            SizedBox(height: 12),
            ...cities.map((city) => _buildCityTile(context, city)).toList(),
          ],
        ),
      ),
    );
  }

  Widget _buildCityTile(BuildContext context, String city) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Icon(Icons.location_city, color: Colors.blue),
      title: Text(city),
      trailing: IconButton(
        icon: Icon(Icons.remove_circle, color: Colors.red),
        onPressed: () => onCityRemove(city),
      ),
      onTap: () => onCityTap(city),
    );
  }
}
```

### Step 7: Testing and Deployment

1. **Test the Flutter app**:
   - Search for different cities
   - Add/remove favorites
   - Test the refresh functionality
   - Verify data persistence

2. **Build and deploy**:
   - The app will be built automatically for web
   - Additional platforms (mobile, desktop) can be configured in the build settings

## Tutorial 3: Migration from Database to Filesystem Storage

### Overview
Learn how to migrate an existing database-stored app to filesystem storage for advanced features.

### When to Migrate

Consider migration when your app:
- Exceeds 50KB in size
- Needs Git version control
- Requires collaborative development
- Benefits from build pipeline optimizations

### Step-by-Step Migration Process

#### Step 1: Assess Your Current App

```javascript
// Assessment script to run in your app
class AppAssessment {
    static async assessApp() {
        const assessment = {
            codeSize: 0,
            complexity: 0,
            features: [],
            recommendations: []
        };
        
        // Calculate code size
        const appData = await this.getCurrentAppData();
        assessment.codeSize = this.calculateCodeSize(appData);
        
        // Analyze complexity
        assessment.complexity = this.analyzeComplexity(appData);
        
        // Detect features that benefit from filesystem storage
        assessment.features = this.detectAdvancedFeatures(appData);
        
        // Generate recommendations
        assessment.recommendations = this.generateRecommendations(assessment);
        
        return assessment;
    }
    
    static calculateCodeSize(appData) {
        let totalSize = 0;
        
        if (appData.js_code) totalSize += new Blob([appData.js_code]).size;
        if (appData.html_template) totalSize += new Blob([appData.html_template]).size;
        if (appData.css_styles) totalSize += new Blob([appData.css_styles]).size;
        
        return totalSize;
    }
    
    static analyzeComplexity(appData) {
        let complexity = 0;
        const code = appData.js_code || '';
        
        // Count functions, classes, and control structures
        complexity += (code.match(/function\s+\w+/g) || []).length;
        complexity += (code.match(/class\s+\w+/g) || []).length;
        complexity += (code.match(/if\s*\(/g) || []).length;
        complexity += (code.match(/for\s*\(/g) || []).length;
        complexity += (code.match(/while\s*\(/g) || []).length;
        
        return complexity;
    }
    
    static detectAdvancedFeatures(appData) {
        const features = [];
        const code = appData.js_code || '';
        
        if (code.includes('import ') || code.includes('require(')) {
            features.push('modules');
        }
        
        if (code.includes('async ') || code.includes('await ')) {
            features.push('async-operations');
        }
        
        if (code.includes('class ') && code.includes('extends ')) {
            features.push('object-oriented');
        }
        
        return features;
    }
    
    static generateRecommendations(assessment) {
        const recommendations = [];
        
        if (assessment.codeSize > 51200) { // 50KB
            recommendations.push({
                type: 'migration',
                reason: 'size',
                message: 'App size exceeds 50KB. Filesystem storage recommended for better performance.'
            });
        }
        
        if (assessment.complexity > 20) {
            recommendations.push({
                type: 'migration',
                reason: 'complexity',
                message: 'High complexity detected. Filesystem storage enables better code organization.'
            });
        }
        
        if (assessment.features.includes('modules')) {
            recommendations.push({
                type: 'migration',
                reason: 'modules',
                message: 'Module usage detected. Filesystem storage supports better dependency management.'
            });
        }
        
        return recommendations;
    }
}

// Run assessment
AppAssessment.assessApp().then(assessment => {
    console.log('App Assessment Results:', assessment);
});
```

#### Step 2: Prepare for Migration

```javascript
// Pre-migration preparation
class MigrationPreparation {
    static async prepareForMigration(appId) {
        console.log('Preparing app for migration...');
        
        // 1. Create backup
        const backup = await this.createBackup(appId);
        console.log('Backup created:', backup.id);
        
        // 2. Validate app structure
        const validation = await this.validateAppStructure(appId);
        if (!validation.valid) {
            throw new Error('App validation failed: ' + validation.errors.join(', '));
        }
        
        // 3. Prepare migration plan
        const plan = await this.createMigrationPlan(appId);
        console.log('Migration plan created:', plan);
        
        return { backup, validation, plan };
    }
    
    static async createBackup(appId) {
        const response = await fetch(`/api/apps/${appId}/backup`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${window.WORKZ_APP_TOKEN}`
            }
        });
        
        if (!response.ok) {
            throw new Error('Failed to create backup');
        }
        
        return await response.json();
    }
    
    static async validateAppStructure(appId) {
        const response = await fetch(`/api/apps/${appId}/validate`, {
            headers: {
                'Authorization': `Bearer ${window.WORKZ_APP_TOKEN}`
            }
        });
        
        return await response.json();
    }
    
    static async createMigrationPlan(appId) {
        const response = await fetch(`/api/apps/${appId}/migration-plan`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${window.WORKZ_APP_TOKEN}`
            },
            body: JSON.stringify({
                targetStorage: 'filesystem',
                preserveHistory: true,
                enableGit: true
            })
        });
        
        return await response.json();
    }
}
```

#### Step 3: Execute Migration

```javascript
// Migration execution
class MigrationExecutor {
    static async executeMigration(appId, plan) {
        console.log('Starting migration process...');
        
        try {
            // 1. Trigger migration
            const migrationResponse = await fetch(`/api/apps/${appId}/migrate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${window.WORKZ_APP_TOKEN}`
                },
                body: JSON.stringify(plan)
            });
            
            if (!migrationResponse.ok) {
                throw new Error('Migration request failed');
            }
            
            const migrationResult = await migrationResponse.json();
            console.log('Migration initiated:', migrationResult.migrationId);
            
            // 2. Monitor migration progress
            const finalStatus = await this.monitorMigration(migrationResult.migrationId);
            
            // 3. Validate migration result
            if (finalStatus.status === 'completed') {
                const validation = await this.validateMigration(appId);
                if (validation.success) {
                    console.log('Migration completed successfully!');
                    return { success: true, result: finalStatus };
                } else {
                    throw new Error('Migration validation failed');
                }
            } else {
                throw new Error(`Migration failed: ${finalStatus.error}`);
            }
            
        } catch (error) {
            console.error('Migration failed:', error);
            
            // Attempt rollback
            await this.rollbackMigration(appId, plan.backupId);
            throw error;
        }
    }
    
    static async monitorMigration(migrationId) {
        const maxAttempts = 60; // 5 minutes with 5-second intervals
        let attempts = 0;
        
        while (attempts < maxAttempts) {
            const response = await fetch(`/api/migrations/${migrationId}/status`);
            const status = await response.json();
            
            console.log(`Migration status: ${status.status} (${status.progress}%)`);
            
            if (status.status === 'completed' || status.status === 'failed') {
                return status;
            }
            
            await new Promise(resolve => setTimeout(resolve, 5000));
            attempts++;
        }
        
        throw new Error('Migration timeout');
    }
    
    static async validateMigration(appId) {
        const response = await fetch(`/api/apps/${appId}/validate-migration`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${window.WORKZ_APP_TOKEN}`
            }
        });
        
        return await response.json();
    }
    
    static async rollbackMigration(appId, backupId) {
        console.log('Attempting migration rollback...');
        
        const response = await fetch(`/api/apps/${appId}/rollback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${window.WORKZ_APP_TOKEN}`
            },
            body: JSON.stringify({ backupId })
        });
        
        if (response.ok) {
            console.log('Rollback completed successfully');
        } else {
            console.error('Rollback failed');
        }
    }
}
```

#### Step 4: Post-Migration Setup

```javascript
// Post-migration configuration
class PostMigrationSetup {
    static async setupFilesystemFeatures(appId) {
        console.log('Setting up filesystem features...');
        
        // 1. Initialize Git repository
        await this.initializeGitRepo(appId);
        
        // 2. Configure build pipeline
        await this.configureBuildPipeline(appId);
        
        // 3. Set up development workflow
        await this.setupDevelopmentWorkflow(appId);
        
        // 4. Update app configuration
        await this.updateAppConfiguration(appId);
        
        console.log('Filesystem features setup completed');
    }
    
    static async initializeGitRepo(appId) {
        const response = await fetch(`/api/apps/${appId}/git/init`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${window.WORKZ_APP_TOKEN}`
            }
        });
        
        if (!response.ok) {
            throw new Error('Failed to initialize Git repository');
        }
        
        console.log('Git repository initialized');
    }
    
    static async configureBuildPipeline(appId) {
        const buildConfig = {
            enabled: true,
            targets: ['web'],
            optimization: {
                minify: true,
                sourceMaps: false,
                assetOptimization: true
            },
            caching: {
                enabled: true,
                ttl: 3600
            }
        };
        
        const response = await fetch(`/api/apps/${appId}/build-config`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${window.WORKZ_APP_TOKEN}`
            },
            body: JSON.stringify(buildConfig)
        });
        
        if (!response.ok) {
            throw new Error('Failed to configure build pipeline');
        }
        
        console.log('Build pipeline configured');
    }
    
    static async setupDevelopmentWorkflow(appId) {
        // Configure Git hooks, branch protection, etc.
        const workflowConfig = {
            gitHooks: {
                prePush: ['npm test', 'npm run lint'],
                preCommit: ['npm run format']
            },
            branchProtection: {
                main: {
                    requirePullRequest: false,
                    requireStatusChecks: true
                }
            }
        };
        
        const response = await fetch(`/api/apps/${appId}/workflow-config`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${window.WORKZ_APP_TOKEN}`
            },
            body: JSON.stringify(workflowConfig)
        });
        
        if (response.ok) {
            console.log('Development workflow configured');
        }
    }
    
    static async updateAppConfiguration(appId) {
        // Update app metadata to reflect new capabilities
        const updates = {
            storageType: 'filesystem',
            features: ['git', 'build-pipeline', 'collaboration'],
            migrationCompletedAt: new Date().toISOString()
        };
        
        const response = await fetch(`/api/apps/${appId}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${window.WORKZ_APP_TOKEN}`
            },
            body: JSON.stringify(updates)
        });
        
        if (response.ok) {
            console.log('App configuration updated');
        }
    }
}
```

### Complete Migration Example

```javascript
// Complete migration workflow
async function migrateAppToFilesystem(appId) {
    try {
        console.log(`Starting migration for app: ${appId}`);
        
        // Step 1: Assess the app
        const assessment = await AppAssessment.assessApp();
        console.log('Assessment completed:', assessment);
        
        // Step 2: Prepare for migration
        const preparation = await MigrationPreparation.prepareForMigration(appId);
        console.log('Preparation completed:', preparation);
        
        // Step 3: Execute migration
        const migrationResult = await MigrationExecutor.executeMigration(appId, preparation.plan);
        console.log('Migration completed:', migrationResult);
        
        // Step 4: Setup filesystem features
        await PostMigrationSetup.setupFilesystemFeatures(appId);
        
        console.log('Migration process completed successfully!');
        
        // Display success message to user
        alert('Your app has been successfully migrated to filesystem storage! You now have access to Git version control, build pipeline, and enhanced development features.');
        
        // Refresh the page to show new features
        window.location.reload();
        
    } catch (error) {
        console.error('Migration failed:', error);
        alert(`Migration failed: ${error.message}. Your app remains unchanged.`);
    }
}

// Usage
// migrateAppToFilesystem('your-app-id');
```

## Best Practices Guide

### Code Organization

#### JavaScript Apps
```
src/
├── components/          # Reusable UI components
│   ├── Button.js
│   ├── Modal.js
│   └── Form.js
├── services/           # API and business logic
│   ├── api.js
│   ├── auth.js
│   └── storage.js
├── utils/              # Helper functions
│   ├── validation.js
│   ├── formatting.js
│   └── constants.js
├── styles/             # CSS files
│   ├── main.css
│   ├── components.css
│   └── themes.css
├── assets/             # Static assets
│   ├── images/
│   └── fonts/
└── main.js             # Entry point
```

#### Flutter Apps
```
lib/
├── main.dart           # Entry point
├── models/             # Data models
│   ├── user.dart
│   └── weather.dart
├── services/           # Business logic
│   ├── api_service.dart
│   ├── auth_service.dart
│   └── storage_service.dart
├── screens/            # App screens
│   ├── home_screen.dart
│   ├── profile_screen.dart
│   └── settings_screen.dart
├── widgets/            # Reusable widgets
│   ├── custom_button.dart
│   ├── loading_indicator.dart
│   └── error_message.dart
├── utils/              # Helper functions
│   ├── validators.dart
│   ├── formatters.dart
│   └── constants.dart
└── themes/             # App themes
    ├── light_theme.dart
    └── dark_theme.dart
```

### Performance Optimization

#### JavaScript Performance Tips
```javascript
// 1. Use efficient DOM manipulation
class DOMHelper {
    static updateMultipleElements(updates) {
        // Batch DOM updates to avoid reflow
        const fragment = document.createDocumentFragment();
        
        updates.forEach(update => {
            const element = document.createElement(update.tag);
            element.textContent = update.content;
            element.className = update.className;
            fragment.appendChild(element);
        });
        
        return fragment;
    }
    
    static debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// 2. Implement efficient data caching
class DataCache {
    constructor(maxSize = 100, ttl = 300000) { // 5 minutes default TTL
        this.cache = new Map();
        this.maxSize = maxSize;
        this.ttl = ttl;
    }
    
    set(key, value) {
        // Implement LRU eviction
        if (this.cache.size >= this.maxSize) {
            const firstKey = this.cache.keys().next().value;
            this.cache.delete(firstKey);
        }
        
        this.cache.set(key, {
            value,
            timestamp: Date.now()
        });
    }
    
    get(key) {
        const item = this.cache.get(key);
        
        if (!item) return null;
        
        // Check TTL
        if (Date.now() - item.timestamp > this.ttl) {
            this.cache.delete(key);
            return null;
        }
        
        return item.value;
    }
}
```

#### Flutter Performance Tips
```dart
// 1. Use efficient list rendering
class EfficientListView extends StatelessWidget {
  final List<Item> items;
  
  const EfficientListView({Key? key, required this.items}) : super(key: key);
  
  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      itemCount: items.length,
      itemBuilder: (context, index) {
        return ItemWidget(
          key: ValueKey(items[index].id), // Use stable keys
          item: items[index],
        );
      },
    );
  }
}

// 2. Implement proper state management
class AppStateManager extends ChangeNotifier {
  Map<String, dynamic> _cache = {};
  
  Future<T> getCachedData<T>(
    String key,
    Future<T> Function() fetcher,
  ) async {
    if (_cache.containsKey(key)) {
      return _cache[key] as T;
    }
    
    final data = await fetcher();
    _cache[key] = data;
    notifyListeners();
    
    return data;
  }
  
  void clearCache() {
    _cache.clear();
    notifyListeners();
  }
}

// 3. Use const constructors where possible
class OptimizedWidget extends StatelessWidget {
  final String title;
  final VoidCallback onTap;
  
  const OptimizedWidget({
    Key? key,
    required this.title,
    required this.onTap,
  }) : super(key: key);
  
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16), // Use const
        child: Text(title),
      ),
    );
  }
}
```

### Security Best Practices

#### Data Validation
```javascript
// Input validation and sanitization
class SecurityHelper {
    static validateInput(input, type) {
        switch (type) {
            case 'email':
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input);
            case 'phone':
                return /^\+?[\d\s\-\(\)]+$/.test(input);
            case 'alphanumeric':
                return /^[a-zA-Z0-9]+$/.test(input);
            default:
                return input.length > 0;
        }
    }
    
    static sanitizeHtml(input) {
        const div = document.createElement('div');
        div.textContent = input;
        return div.innerHTML;
    }
    
    static sanitizeUrl(url) {
        try {
            const parsed = new URL(url);
            return ['http:', 'https:'].includes(parsed.protocol) ? url : '';
        } catch {
            return '';
        }
    }
}
```

#### Secure API Communication
```dart
// Secure HTTP client implementation
class SecureHttpClient {
  static const Duration _timeout = Duration(seconds: 30);
  
  static Future<http.Response> secureGet(String url, {Map<String, String>? headers}) async {
    final uri = Uri.parse(url);
    
    // Validate URL
    if (!['https'].contains(uri.scheme)) {
      throw SecurityException('Only HTTPS URLs are allowed');
    }
    
    final client = http.Client();
    
    try {
      final response = await client.get(
        uri,
        headers: {
          'User-Agent': 'WorkzApp/1.0',
          'Accept': 'application/json',
          ...?headers,
        },
      ).timeout(_timeout);
      
      return response;
    } finally {
      client.close();
    }
  }
  
  static Future<Map<String, dynamic>> securePost(
    String url,
    Map<String, dynamic> data,
  ) async {
    final response = await secureGet(url, headers: {
      'Content-Type': 'application/json',
    });
    
    if (response.statusCode == 200) {
      return json.decode(response.body);
    } else {
      throw HttpException('Request failed: ${response.statusCode}');
    }
  }
}

class SecurityException implements Exception {
  final String message;
  SecurityException(this.message);
  
  @override
  String toString() => 'SecurityException: $message';
}
```

This comprehensive tutorial guide provides developers with practical examples and best practices for building applications on the Workz! platform, covering both JavaScript and Flutter development approaches, migration strategies, and optimization techniques.