# Design Document

## Overview

Este design implementa a sincronização entre o código Dart/Flutter específico de cada app e o index.html gerado, garantindo que a interface web reflita corretamente a funcionalidade implementada. O sistema analisará o código Dart para extrair metadados e gerar um index.html personalizado que carregue o app Flutter real.

## Architecture

### Current State Analysis
- **Problema Atual**: `generateFlutterIndexHtml()` gera template genérico
- **Código Real**: Salvo em `main.dart.js` mas não utilizado no index.html
- **Desconexão**: Interface genérica não reflete funcionalidade específica

### Proposed Architecture
```
Dart Code Input → Code Analyzer → Metadata Extractor → HTML Generator → Customized index.html
                                                    ↓
                                              Flutter Engine Loader → Real App Execution
```

## Components and Interfaces

### 1. Flutter Code Analyzer
**Responsabilidade**: Analisar código Dart para extrair metadados

**Interface**:
```php
class FlutterCodeAnalyzer {
    public function analyzeCode(string $dartCode): array;
    public function extractAppMetadata(string $dartCode): array;
    public function validateDartSyntax(string $dartCode): bool;
}
```

**Metadados Extraídos**:
- App title (de MaterialApp ou CupertinoApp)
- Theme configuration (cores, brightness)
- Main widget class name
- Dependencies utilizadas
- Estrutura de navegação

### 2. Enhanced HTML Generator
**Responsabilidade**: Gerar index.html personalizado baseado nos metadados

**Interface**:
```php
class EnhancedFlutterHtmlGenerator {
    public function generateCustomHtml(int $appId, array $metadata, string $dartCode): string;
    public function createFlutterEngineConfig(array $metadata): array;
    public function generateLoadingScreen(array $metadata): string;
}
```

### 3. Flutter Engine Integration
**Responsabilidade**: Carregar e executar o app Flutter real no navegador

**Componentes**:
- Flutter Web Engine loader
- Service Worker para cache
- Error handling e fallbacks
- WorkzSDK integration bridge

## Data Models

### App Metadata Structure
```php
[
    'title' => string,           // App title from MaterialApp
    'theme' => [
        'primaryColor' => string,
        'brightness' => 'light|dark',
        'useMaterial3' => bool
    ],
    'mainWidget' => string,      // Main widget class name
    'dependencies' => array,     // Flutter dependencies used
    'hasNavigation' => bool,     // Uses Navigator/routing
    'appType' => string,         // Game, utility, business, etc.
    'customAssets' => array      // Custom assets referenced
]
```

### HTML Template Configuration
```php
[
    'loadingTheme' => array,     // Loading screen colors/style
    'flutterConfig' => array,    // Flutter engine configuration
    'workzSdkIntegration' => bool,
    'serviceWorkerEnabled' => bool,
    'errorFallback' => string    // Custom error page
]
```

## Error Handling

### Code Analysis Errors
- **Invalid Dart Syntax**: Fallback to generic template with error notice
- **Missing Required Components**: Generate warning but proceed
- **Parsing Failures**: Log error, use safe defaults

### Runtime Errors
- **Flutter Engine Load Failure**: Show user-friendly error with retry option
- **App Initialization Failure**: Fallback to error page with debug info
- **WorkzSDK Integration Issues**: Graceful degradation without SDK features

### Fallback Strategy
1. **Primary**: Custom generated HTML with real Flutter app
2. **Secondary**: Generic template with Flutter simulation
3. **Tertiary**: Static error page with manual app link

## Testing Strategy

### Unit Tests
- `FlutterCodeAnalyzer::analyzeCode()` with various Dart code samples
- `EnhancedFlutterHtmlGenerator::generateCustomHtml()` output validation
- Metadata extraction accuracy tests

### Integration Tests
- End-to-end app creation and HTML generation
- Flutter engine loading in different browsers
- WorkzSDK integration functionality

### Browser Compatibility Tests
- Chrome, Firefox, Safari, Edge
- Mobile browsers (iOS Safari, Chrome Mobile)
- Different screen sizes and orientations

## Implementation Phases

### Phase 1: Code Analysis Enhancement
- Implement `FlutterCodeAnalyzer` class
- Create metadata extraction logic
- Add Dart syntax validation

### Phase 2: HTML Generation Overhaul
- Replace `generateFlutterIndexHtml()` with enhanced version
- Implement template customization based on metadata
- Add Flutter engine integration

### Phase 3: Real Flutter App Loading
- Implement actual Flutter web engine loading
- Replace simulation with real app execution
- Add proper error handling and fallbacks

### Phase 4: Advanced Features
- Service worker implementation
- Progressive Web App (PWA) features
- Performance optimizations

## Security Considerations

### Code Injection Prevention
- Sanitize all extracted metadata before HTML generation
- Validate Dart code structure to prevent malicious content
- Escape all user-provided content in generated HTML

### Resource Access Control
- Limit Flutter app access to authorized APIs only
- Maintain WorkzSDK security boundaries
- Implement proper CORS handling for cross-origin requests

## Performance Optimizations

### Loading Performance
- Implement lazy loading for Flutter engine
- Add resource preloading hints
- Optimize initial bundle size

### Runtime Performance
- Enable Flutter web optimizations
- Implement proper caching strategies
- Monitor and log performance metrics

### Memory Management
- Proper cleanup of Flutter app instances
- Garbage collection optimization
- Resource leak prevention