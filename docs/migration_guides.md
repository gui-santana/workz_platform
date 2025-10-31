# Migration Guides

## Overview

This guide helps you migrate existing Workz! applications to the new unified architecture. The migration process varies depending on your current setup and target architecture.

## Migration Scenarios

### 1. Database-Stored Apps to Filesystem Storage

This migration is recommended for apps that have grown beyond the 50KB threshold or need advanced features like Git version control.

#### Prerequisites

- App size > 50KB or requires Git features
- Backup of current app code
- Testing environment for validation

#### Migration Steps

1. **Backup Current App**:
```javascript
// Use the App Builder or API to export current code
const currentCode = await fetch(`/api/apps/${appId}/export`);
const backup = await currentCode.json();
// Save backup locally
```

2. **Trigger Migration**:
```javascript
// Via API
const response = await fetch(`/api/apps/${appId}/migrate`, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
        targetStorage: 'filesystem',
        preserveHistory: true
    })
});

const migrationResult = await response.json();
```

3. **Verify Migration**:
```javascript
// Check migration status
const status = await fetch(`/api/apps/${appId}/migration-status`);
const statusData = await status.json();

if (statusData.status === 'completed') {
    console.log('Migration successful');
    console.log('Repository path:', statusData.repositoryPath);
} else if (statusData.status === 'failed') {
    console.error('Migration failed:', statusData.error);
    // Rollback if needed
}
```

#### Post-Migration Tasks

1. **Update Development Workflow**:
   - Use Git for version control
   - Set up branches for feature development
   - Configure collaborative development if needed

2. **Update Build Process**:
   - Apps now use the build pipeline
   - Artifacts are generated automatically
   - Multiple platform targets available

3. **Test Functionality**:
   - Verify app loads correctly
   - Test all features and integrations
   - Validate performance improvements

### 2. Filesystem-Stored Apps to Database Storage

This migration might be needed for apps that have become smaller or simpler over time.

#### When to Consider

- App size reduced to < 25KB (with buffer)
- No longer needs Git features
- Wants simpler deployment model

#### Migration Steps

1. **Assess Current App**:
```bash
# Check current app size and complexity
cd /apps/your-app-slug
du -sh src/
git log --oneline | wc -l  # Check commit history
```

2. **Prepare for Migration**:
```javascript
// Flatten code structure for database storage
const codeFlattener = {
    async flattenApp(appPath) {
        const files = await this.getAllFiles(appPath + '/src');
        const flattened = {};
        
        for (const file of files) {
            const content = await fs.readFile(file, 'utf8');
            const relativePath = file.replace(appPath + '/src/', '');
            flattened[relativePath] = content;
        }
        
        return flattened;
    }
};
```

3. **Execute Migration**:
```javascript
const response = await fetch(`/api/apps/${appId}/migrate`, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
        targetStorage: 'database',
        preserveGitHistory: false  // Git history will be archived
    })
});
```

### 3. SDK v1.x to v2.x Migration

Migrate from the old platform-specific SDKs to the new unified SDK.

#### JavaScript Apps Migration

**Before (v1.x)**:
```javascript
// Old SDK initialization
WorkzJS.configure({
    token: 'your-token',
    apiBase: 'https://api.workz.com'
});

// Old API calls
WorkzJS.storage.set('key', 'value');
const profile = WorkzJS.user.getProfile();
```

**After (v2.x)**:
```javascript
// New SDK initialization
await WorkzSDK.init({
    apiUrl: 'https://api.workz.com',
    token: 'your-token'
});

// New API calls
await WorkzSDK.kv.set('key', 'value');
const profile = await WorkzSDK.profile.get();
```

**Migration Script**:
```javascript
// migration-helper.js
class SDKMigrationHelper {
    static async migrateJavaScriptApp(oldCode) {
        let newCode = oldCode;
        
        // Replace old SDK calls
        const replacements = [
            [/WorkzJS\.configure\(/g, 'await WorkzSDK.init('],
            [/WorkzJS\.storage\.set\(/g, 'await WorkzSDK.kv.set('],
            [/WorkzJS\.storage\.get\(/g, 'await WorkzSDK.kv.get('],
            [/WorkzJS\.user\.getProfile\(\)/g, 'await WorkzSDK.profile.get()'],
            [/WorkzJS\.http\.get\(/g, 'await WorkzSDK.http.get('],
            [/WorkzJS\.http\.post\(/g, 'await WorkzSDK.http.post(']
        ];
        
        for (const [pattern, replacement] of replacements) {
            newCode = newCode.replace(pattern, replacement);
        }
        
        // Add async/await where needed
        newCode = this.addAsyncAwait(newCode);
        
        return newCode;
    }
    
    static addAsyncAwait(code) {
        // Simple heuristic to add async/await
        // In practice, this would be more sophisticated
        if (code.includes('WorkzSDK.')) {
            code = code.replace(/function\s+(\w+)\s*\(/g, 'async function $1(');
        }
        return code;
    }
}
```

#### Flutter Apps Migration

**Before (v1.x)**:
```dart
// Old SDK
import 'package:workz_flutter_sdk/workz_flutter_sdk.dart';

// Old initialization
WorkzFlutter.configure(token: 'your-token');

// Old API calls
WorkzFlutter.kvStore.setValue('key', 'value');
final profile = WorkzFlutter.userService.getProfile();
```

**After (v2.x)**:
```dart
// New SDK
import 'package:workz_sdk/workz_sdk.dart';

// New initialization
await WorkzSDK.init(
  apiUrl: 'https://api.workz.com',
  token: 'your-token',
);

// New API calls
await WorkzSDK.kv.set('key', 'value');
final profile = await WorkzSDK.profile.get();
```

**Migration Steps for Flutter**:

1. **Update Dependencies**:
```yaml
# pubspec.yaml
dependencies:
  # Remove old SDK
  # workz_flutter_sdk: ^1.0.0
  
  # Add new SDK
  workz_sdk: ^2.0.0
```

2. **Update Imports**:
```dart
// Replace old imports
// import 'package:workz_flutter_sdk/workz_flutter_sdk.dart';

// With new imports
import 'package:workz_sdk/workz_sdk.dart';
```

3. **Update API Calls**:
```dart
// migration_helper.dart
class FlutterMigrationHelper {
  static String migrateCode(String oldCode) {
    String newCode = oldCode;
    
    // Replace imports
    newCode = newCode.replaceAll(
      'package:workz_flutter_sdk/workz_flutter_sdk.dart',
      'package:workz_sdk/workz_sdk.dart'
    );
    
    // Replace API calls
    final replacements = {
      'WorkzFlutter.configure': 'await WorkzSDK.init',
      'WorkzFlutter.kvStore.setValue': 'await WorkzSDK.kv.set',
      'WorkzFlutter.kvStore.getValue': 'await WorkzSDK.kv.get',
      'WorkzFlutter.userService.getProfile': 'await WorkzSDK.profile.get',
      'WorkzFlutter.httpClient.get': 'await WorkzSDK.http.get',
      'WorkzFlutter.httpClient.post': 'await WorkzSDK.http.post',
    };
    
    for (final entry in replacements.entries) {
      newCode = newCode.replaceAll(entry.key, entry.value);
    }
    
    return newCode;
  }
}
```

### 4. Build System Migration

Migrate from manual deployment to the automated build pipeline.

#### Before: Manual Deployment

```javascript
// Old manual process
// 1. Write code in App Builder
// 2. Save directly to database
// 3. Code executes immediately
```

#### After: Build Pipeline

```javascript
// New automated process
// 1. Write code in App Builder or Git
// 2. Trigger build pipeline
// 3. Artifacts generated for multiple platforms
// 4. Optimized code deployed
```

#### Migration Steps

1. **Update App Configuration**:
```json
// Add workz.json to your app
{
  "name": "My App",
  "version": "1.0.0",
  "appType": "javascript",
  "buildTargets": ["web"],
  "workzSDK": {
    "version": "^2.0.0"
  }
}
```

2. **Configure Build Settings**:
```javascript
// Via API or App Builder
const buildConfig = {
  minify: true,
  sourceMaps: false,
  optimizeAssets: true,
  targetBrowsers: ['> 1%', 'last 2 versions']
};

await fetch(`/api/apps/${appId}/build-config`, {
  method: 'PUT',
  body: JSON.stringify(buildConfig)
});
```

3. **Test Build Process**:
```javascript
// Trigger a test build
const buildResponse = await fetch(`/api/apps/${appId}/build`, {
  method: 'POST'
});

const buildStatus = await buildResponse.json();
console.log('Build ID:', buildStatus.buildId);

// Monitor build progress
const checkBuild = async (buildId) => {
  const status = await fetch(`/api/builds/${buildId}/status`);
  const data = await status.json();
  
  if (data.status === 'completed') {
    console.log('Build completed successfully');
    console.log('Artifacts:', data.artifacts);
  } else if (data.status === 'failed') {
    console.error('Build failed:', data.errors);
  }
};
```

## Migration Tools and Utilities

### Automated Migration Script

```javascript
// migration-tool.js
class MigrationTool {
    constructor(apiUrl, token) {
        this.apiUrl = apiUrl;
        this.token = token;
    }
    
    async migrateApp(appId, options = {}) {
        const {
            targetStorage = 'filesystem',
            targetSDK = 'v2',
            preserveHistory = true,
            dryRun = false
        } = options;
        
        console.log(`Starting migration for app ${appId}`);
        
        try {
            // 1. Backup current app
            const backup = await this.createBackup(appId);
            console.log('Backup created:', backup.id);
            
            // 2. Analyze current app
            const analysis = await this.analyzeApp(appId);
            console.log('App analysis:', analysis);
            
            // 3. Generate migration plan
            const plan = await this.generateMigrationPlan(appId, analysis, options);
            console.log('Migration plan:', plan);
            
            if (dryRun) {
                console.log('Dry run completed. No changes made.');
                return { success: true, plan, backup };
            }
            
            // 4. Execute migration
            const result = await this.executeMigration(appId, plan);
            
            // 5. Validate migration
            const validation = await this.validateMigration(appId, plan);
            
            if (validation.success) {
                console.log('Migration completed successfully');
                await this.cleanupBackup(backup.id);
            } else {
                console.error('Migration validation failed');
                await this.rollbackMigration(appId, backup.id);
            }
            
            return { success: validation.success, result, validation };
            
        } catch (error) {
            console.error('Migration failed:', error);
            throw error;
        }
    }
    
    async createBackup(appId) {
        const response = await fetch(`${this.apiUrl}/apps/${appId}/backup`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${this.token}` }
        });
        return await response.json();
    }
    
    async analyzeApp(appId) {
        const response = await fetch(`${this.apiUrl}/apps/${appId}/analyze`, {
            headers: { 'Authorization': `Bearer ${this.token}` }
        });
        return await response.json();
    }
    
    async generateMigrationPlan(appId, analysis, options) {
        const response = await fetch(`${this.apiUrl}/apps/${appId}/migration-plan`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`
            },
            body: JSON.stringify({ analysis, options })
        });
        return await response.json();
    }
    
    async executeMigration(appId, plan) {
        const response = await fetch(`${this.apiUrl}/apps/${appId}/migrate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`
            },
            body: JSON.stringify(plan)
        });
        return await response.json();
    }
    
    async validateMigration(appId, plan) {
        const response = await fetch(`${this.apiUrl}/apps/${appId}/validate-migration`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`
            },
            body: JSON.stringify(plan)
        });
        return await response.json();
    }
    
    async rollbackMigration(appId, backupId) {
        const response = await fetch(`${this.apiUrl}/apps/${appId}/rollback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`
            },
            body: JSON.stringify({ backupId })
        });
        return await response.json();
    }
}

// Usage
const migrationTool = new MigrationTool('https://api.workz.com', 'your-token');

// Dry run first
const dryRun = await migrationTool.migrateApp('app-123', {
    targetStorage: 'filesystem',
    dryRun: true
});

console.log('Dry run results:', dryRun);

// Execute actual migration if dry run looks good
if (dryRun.success) {
    const migration = await migrationTool.migrateApp('app-123', {
        targetStorage: 'filesystem',
        dryRun: false
    });
    
    console.log('Migration results:', migration);
}
```

### Code Analysis Tool

```javascript
// code-analyzer.js
class CodeAnalyzer {
    static analyzeJavaScript(code) {
        const analysis = {
            size: new Blob([code]).size,
            complexity: this.calculateComplexity(code),
            dependencies: this.extractDependencies(code),
            sdkVersion: this.detectSDKVersion(code),
            features: this.detectFeatures(code),
            recommendations: []
        };
        
        // Generate recommendations
        if (analysis.size > 50000) {
            analysis.recommendations.push('Consider filesystem storage for better performance');
        }
        
        if (analysis.sdkVersion === 'v1') {
            analysis.recommendations.push('Upgrade to SDK v2 for unified API');
        }
        
        if (analysis.complexity > 10) {
            analysis.recommendations.push('Consider breaking into smaller modules');
        }
        
        return analysis;
    }
    
    static calculateComplexity(code) {
        // Simple complexity calculation
        const patterns = [
            /function\s+\w+/g,
            /class\s+\w+/g,
            /if\s*\(/g,
            /for\s*\(/g,
            /while\s*\(/g,
            /switch\s*\(/g
        ];
        
        let complexity = 0;
        for (const pattern of patterns) {
            const matches = code.match(pattern);
            complexity += matches ? matches.length : 0;
        }
        
        return complexity;
    }
    
    static extractDependencies(code) {
        const dependencies = [];
        
        // Extract import statements
        const importMatches = code.match(/import\s+.*?from\s+['"]([^'"]+)['"]/g);
        if (importMatches) {
            for (const match of importMatches) {
                const dep = match.match(/from\s+['"]([^'"]+)['"]/)[1];
                dependencies.push(dep);
            }
        }
        
        // Extract require statements
        const requireMatches = code.match(/require\s*\(\s*['"]([^'"]+)['"]\s*\)/g);
        if (requireMatches) {
            for (const match of requireMatches) {
                const dep = match.match(/['"]([^'"]+)['"]/)[1];
                dependencies.push(dep);
            }
        }
        
        return [...new Set(dependencies)];
    }
    
    static detectSDKVersion(code) {
        if (code.includes('WorkzSDK.init')) return 'v2';
        if (code.includes('WorkzJS.configure') || code.includes('WorkzFlutter.configure')) return 'v1';
        return 'none';
    }
    
    static detectFeatures(code) {
        const features = [];
        
        if (code.includes('git') || code.includes('version control')) {
            features.push('version-control');
        }
        
        if (code.includes('collaborative') || code.includes('multi-user')) {
            features.push('collaboration');
        }
        
        if (code.includes('build') || code.includes('compile')) {
            features.push('build-pipeline');
        }
        
        return features;
    }
}
```

## Migration Checklist

### Pre-Migration

- [ ] **Backup Current App**
  - Export all code and assets
  - Document current functionality
  - Note any custom configurations

- [ ] **Analyze Current App**
  - Check app size and complexity
  - Identify dependencies
  - Review SDK usage patterns

- [ ] **Plan Migration Strategy**
  - Choose target storage type
  - Decide on SDK version
  - Plan testing approach

### During Migration

- [ ] **Execute Migration**
  - Run migration tool or API
  - Monitor progress
  - Handle any errors

- [ ] **Validate Results**
  - Test app functionality
  - Verify data integrity
  - Check performance

### Post-Migration

- [ ] **Update Development Workflow**
  - Configure new tools (Git, build pipeline)
  - Update documentation
  - Train team members

- [ ] **Monitor Performance**
  - Check load times
  - Monitor error rates
  - Gather user feedback

- [ ] **Cleanup**
  - Remove old backups (after validation period)
  - Update deployment scripts
  - Archive old documentation

## Troubleshooting Common Issues

### Migration Failures

**Issue**: Migration fails with "Code size too large for database storage"
**Solution**: 
```javascript
// Split large files or use filesystem storage
const options = {
    targetStorage: 'filesystem',
    splitLargeFiles: true,
    maxFileSize: 10000 // 10KB per file
};
```

**Issue**: SDK v2 initialization fails
**Solution**:
```javascript
// Check token and API URL
try {
    await WorkzSDK.init({
        apiUrl: 'https://api.workz.com',
        token: process.env.WORKZ_TOKEN,
        debug: true // Enable debug logging
    });
} catch (error) {
    console.error('SDK init failed:', error);
    // Check network connectivity and token validity
}
```

**Issue**: Build pipeline fails after migration
**Solution**:
```javascript
// Check build configuration
const buildConfig = {
    appType: 'javascript', // or 'flutter'
    buildTargets: ['web'],
    dependencies: [], // List all dependencies
    buildSettings: {
        minify: true,
        sourceMaps: false
    }
};
```

### Performance Issues

**Issue**: App loads slowly after migration
**Solution**:
- Enable build pipeline optimizations
- Use CDN for static assets
- Implement lazy loading

**Issue**: Increased memory usage
**Solution**:
- Review code for memory leaks
- Optimize asset loading
- Use efficient data structures

### Compatibility Issues

**Issue**: Old SDK calls not working
**Solution**:
- Use migration helper tools
- Update all API calls to v2 format
- Test thoroughly in development environment

**Issue**: Platform-specific features missing
**Solution**:
- Check platform detection logic
- Implement platform-specific adapters
- Use feature detection instead of platform detection

## Support and Resources

### Getting Help

1. **Documentation**: Refer to the complete SDK documentation
2. **Migration Tool**: Use the automated migration utilities
3. **Support Team**: Contact support for complex migrations
4. **Community**: Join the developer community for tips and best practices

### Best Practices

1. **Always backup before migration**
2. **Test in development environment first**
3. **Migrate during low-traffic periods**
4. **Monitor closely after migration**
5. **Have rollback plan ready**
6. **Update documentation and team knowledge**

### Migration Timeline

**Small Apps (< 10KB)**:
- Planning: 1-2 hours
- Execution: 30 minutes
- Validation: 1 hour
- Total: 2-4 hours

**Medium Apps (10-50KB)**:
- Planning: 2-4 hours
- Execution: 1-2 hours
- Validation: 2-4 hours
- Total: 5-10 hours

**Large Apps (> 50KB)**:
- Planning: 4-8 hours
- Execution: 2-4 hours
- Validation: 4-8 hours
- Total: 10-20 hours

**Complex Multi-Platform Apps**:
- Planning: 1-2 days
- Execution: 4-8 hours
- Validation: 1-2 days
- Total: 2-4 days