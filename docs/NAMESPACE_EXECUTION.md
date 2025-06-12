# Namespace Execution Solutions for WordPress

## Problem Overview

When executing WordPress files from within a namespaced PHP class, the required files inherit the namespace context. This causes issues because WordPress expects to run in the global namespace and relies heavily on global variables like `$_wp_submenu_nopriv`, `$wp_query`, etc.

## Current Solution

The Handler now returns a file path instead of executing WordPress directly, allowing the execution to happen in the global scope:

```php
// handler.php (global scope)
$result = (new Handler($config))->run();

// If a file path is returned, require it in global scope
if ($result) {
    require $result;  // Executed in global scope!
}
```

This elegant solution ensures WordPress files are always executed in the global scope, completely avoiding namespace issues.

### The Issue

```php
namespace WpNx\Handler;

class Handler {
    public function executeWordPress($file) {
        require $file; // This executes in WpNx\Handler namespace!
    }
}
```

In the above code, any WordPress file loaded via `require` will execute within the `WpNx\Handler` namespace, causing WordPress globals to be inaccessible.

## Alternative Solutions Considered

These are alternative approaches that were considered during development but not implemented in favor of the simpler file path return approach:

### 1. Global Function Method

**Concept:** Create a global function to execute WordPress files

**File:** `src/functions.php`

```php
// No namespace declaration - this is important!
function wpnx_execute_wordpress($file, $workingDir, $serverVars = []) {
    chdir($workingDir);
    foreach ($serverVars as $key => $value) {
        $_SERVER[$key] = $value;
    }
    require $file;
}
```

**Usage in Handler:**
```php
call_user_func('\wpnx_execute_wordpress', $filePath, $workingDirectory, $serverVars);
```

**Advantages:**
- Simple and reliable
- No eval() usage
- Minimal performance overhead
- Easy to understand and maintain

### 2. Bootstrap File Method

**File:** `src/bootstrap.php`

```php
// No namespace declaration
$data = $GLOBALS['_wpnx_bootstrap_data'];
unset($GLOBALS['_wpnx_bootstrap_data']);
require $data['file'];
```

**Usage in Handler:**
```php
$GLOBALS['_wpnx_bootstrap_data'] = ['file' => $filePath];
require __DIR__ . '/bootstrap.php';
```

**Advantages:**
- Complete isolation from namespace
- Can include additional setup logic
- Good for complex initialization

### 3. Loader File Method

**File:** `src/loader.php`

```php
// No namespace declaration
$file = $_ENV['WPNX_LOADER_FILE'];
$dir = $_ENV['WPNX_LOADER_DIR'];
chdir($dir);
require $file;
```

**Usage in Handler:**
```php
$_ENV['WPNX_LOADER_FILE'] = $filePath;
$_ENV['WPNX_LOADER_DIR'] = $workingDirectory;
require __DIR__ . '/loader.php';
```

**Advantages:**
- Uses environment variables for communication
- Clean separation of concerns
- No global variable pollution

### 4. Call User Function Method

```php
// Define global function
function global_require($file) {
    require $file;
}

// Use in namespaced code
call_user_func('\global_require', $filePath);
```

**Advantages:**
- PHP built-in approach
- No additional files needed
- Clear intent


## Why Not Use These Methods?

### eval() - Security Risk
```php
// DON'T DO THIS
$code = file_get_contents($file);
eval('?>' . $code);
```
- Security vulnerability
- Difficult to debug
- Poor performance
- No opcode caching

### Reflection - Doesn't Solve the Problem
```php
// This still executes in current namespace
$reflection = new ReflectionFunction(function() use ($file) {
    require $file;
});
$reflection->invoke();
```

### runkit Extension - Not Standard
- Requires PECL extension
- Not available in most environments
- Compatibility issues

## Why the Current Solution?

The file path return approach was chosen because it:

1. **Simplicity**: No additional files or functions needed
2. **Clarity**: Clear separation between Handler logic and WordPress execution
3. **Performance**: No overhead from function calls or file includes
4. **Compatibility**: Works with all PHP versions and configurations
5. **Security**: Maintains all existing security checks in the Handler

## Testing

To verify WordPress is executing in the global scope:

```php
// In your WordPress file (e.g., wp-admin/admin.php)
var_dump(__NAMESPACE__); // Should output empty string
global $_wp_submenu_nopriv;
var_dump(isset($_wp_submenu_nopriv)); // Should return true when properly initialized
```

## Implementation Details

The Handler's `run()` method signature:

```php
public function run(): ?string
```

- Returns `string` when a WordPress file should be executed (the file path)
- Returns `null` when the response has already been sent (e.g., static files, redirects)

This approach ensures that WordPress files are always executed in the correct scope while maintaining the Handler's ability to process various request types.