<?php
/**
 * Environment configuration loader
 * Loads .env file and makes variables available via getenv()
 */

// Get the root directory of the application
$root_dir = dirname(dirname(__FILE__));
$env_file = $root_dir . '/.env';

// Load .env file if it exists
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos($line, '#') === 0) {
            continue;
        }
        
        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }
            if (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }
            
            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
} else {
    // If .env file doesn't exist, show warning but don't fail
    error_log("Warning: .env file not found at $env_file. Using system environment variables or defaults.");
}

/**
 * Get an environment variable with optional default value
 *
 * @param string $key The environment variable key
 * @param mixed $default Default value if key doesn't exist
 * @return mixed The environment variable value or default
 */
if (!function_exists('getEnv')) {
    function getEnv($key, $default = null) {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        // Convert string booleans to actual booleans
        if ($value === 'true' || $value === 'TRUE' || $value === '1') {
            return true;
        }
        if ($value === 'false' || $value === 'FALSE' || $value === '0') {
            return false;
        }

        // Convert numeric strings to integers if applicable
        if (is_numeric($value) && strpos($value, '.') === false) {
            return (int) $value;
        }

        return $value;
    }
}

/**
 * Get a setting value from the database settings table
 * Falls back to environment variable if setting doesn't exist in database
 * 
 * @param string $key The setting key
 * @param mixed $default Default value if not found
 * @param mysqli $conn Optional database connection (for efficiency if calling multiple times)
 * @return mixed The setting value
 */
function getSettingValue($key, $default = null, $conn = null) {
    // Use provided connection or create new one
    if ($conn === null) {
        $conn = getDBConnection();
        $close_conn = true;
    } else {
        $close_conn = false;
    }
    
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            $value = $row['setting_value'];
            $stmt->close();
            
            // Close connection if we created it
            if ($close_conn) {
                $conn->close();
            }
            
            return $value;
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error reading setting '$key' from database: " . $e->getMessage());
    }
    
    // Close connection if we created it
    if ($close_conn && $conn) {
        $conn->close();
    }
    
    // Fall back to environment variable or default
    return getEnv($key, $default);
}

/**
 * Set a setting value in the database settings table
 * 
 * @param string $key The setting key
 * @param mixed $value The setting value
 * @param mysqli $conn Optional database connection
 * @return bool True if successful, false otherwise
 */
function setSettingValue($key, $value, $conn = null) {
    // Use provided connection or create new one
    if ($conn === null) {
        $conn = getDBConnection();
        $close_conn = true;
    } else {
        $close_conn = false;
    }
    
    try {
        // Convert value to string for storage
        $value_str = is_array($value) ? json_encode($value) : (string) $value;
        
        // Use INSERT OR REPLACE pattern (INSERT ... ON DUPLICATE KEY UPDATE for MySQL)
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ss", $key, $value_str);
        $result = $stmt->execute();
        $stmt->close();
        
        // Close connection if we created it
        if ($close_conn) {
            $conn->close();
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error setting '$key' in database: " . $e->getMessage());
        
        if ($close_conn && $conn) {
            $conn->close();
        }
        
        return false;
    }
}
?>
