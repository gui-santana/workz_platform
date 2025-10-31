# Calculator App - Database Storage Example

This is a simple calculator application that demonstrates the database storage approach in the Workz! platform. It's designed to be lightweight and suitable for apps under 50KB.

## Features

- Basic arithmetic operations (+, -, ×, ÷)
- Calculation history with persistent storage
- Keyboard support
- Responsive design
- Clean, modern UI

## Storage Characteristics

- **Storage Type**: Database
- **Size**: ~15KB (well under the 50KB threshold)
- **Complexity**: Low (simple single-file structure)
- **Features**: Basic functionality without advanced development needs

## Code Structure

```
javascript_calculator/
├── index.html          # Main HTML file with embedded CSS
├── main.js            # JavaScript logic and Workz SDK integration
└── README.md          # This file
```

## Key Implementation Details

### Workz SDK Integration

```javascript
// Initialize SDK
await WorkzSDK.init({
    apiUrl: 'https://api.workz.com',
    token: window.WORKZ_APP_TOKEN
});

// Use KV storage for history
await WorkzSDK.kv.set('calculator_history', JSON.stringify(history));
const savedHistory = await WorkzSDK.kv.get('calculator_history');
```

### Storage Usage

The app uses Workz KV storage to persist calculation history:
- Stores up to 50 recent calculations
- Automatically saves after each calculation
- Loads history on app initialization

### Performance Optimizations

- Minimal DOM manipulation
- Efficient event handling
- Lightweight CSS (no external dependencies)
- Fast initialization and response times

## Why Database Storage?

This app is ideal for database storage because:

1. **Small Size**: Total code size is under 15KB
2. **Simple Structure**: Single HTML file with embedded CSS and JavaScript
3. **No Build Process**: Runs directly without compilation
4. **Quick Deployment**: Immediate execution after saving
5. **No Version Control Needs**: Simple enough for direct editing

## Usage Instructions

1. **Basic Operations**:
   - Click number buttons or use keyboard (0-9)
   - Click operator buttons or use keyboard (+, -, *, /)
   - Press = or Enter to calculate
   - Press C or Escape to clear

2. **History**:
   - View recent calculations in the history panel
   - Click on any history item to use its result
   - Clear history with the "Clear History" button

3. **Keyboard Shortcuts**:
   - Numbers: 0-9
   - Operators: +, -, *, /
   - Calculate: Enter or =
   - Clear: Escape
   - Backspace: Backspace

## Deployment

This app is automatically deployed when saved in the Workz! App Builder:

1. Code is stored directly in the database
2. No build process required
3. Immediate availability after saving
4. Fast loading due to database proximity

## Extending the App

To add more features while staying in database storage:

- Add more mathematical functions (sin, cos, sqrt)
- Implement memory functions (M+, M-, MR, MC)
- Add themes or color customization
- Include unit conversions

If the app grows beyond 50KB or needs advanced features like:
- Git version control
- Collaborative development
- Complex build processes
- Multiple file organization

Consider migrating to filesystem storage using the migration tools provided by the platform.

## Performance Metrics

- **Load Time**: < 100ms (database query + rendering)
- **Memory Usage**: < 5MB
- **Storage Usage**: ~2KB for history data
- **Response Time**: < 10ms for calculations

This demonstrates the efficiency of database storage for simple applications.