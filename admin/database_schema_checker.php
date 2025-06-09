<?php
/**
 * Database Schema Detective v1.0.0
 * Identifies actual database structure for safe integration
 * Created: 2025-06-09
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Database Schema Detective</h1>";

// Try to connect to database
try {
    // Try multiple config paths
    $config_paths = ['../includes/config.php', './includes/config.php', 'includes/config.php'];
    $config_loaded = false;
    
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $config_loaded = true;
            echo "✅ Config loaded from: $path<br>";
            break;
        }
    }
    
    if (!$config_loaded) {
        throw new Exception("Could not find config.php");
    }
    
    $pdo = getDatabase();
    echo "✅ Database connection successful<br><br>";
    
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// Function to get table structure
function getTableStructure($pdo, $tableName) {
    try {
        $stmt = $pdo->query("DESCRIBE $tableName");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return "❌ Error: " . $e->getMessage();
    }
}

// Function to get sample data
function getSampleData($pdo, $tableName, $limit = 3) {
    try {
        $stmt = $pdo->query("SELECT * FROM $tableName LIMIT $limit");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return "❌ Error: " . $e->getMessage();
    }
}

// Check all relevant tables
$tables_to_check = [
    'certificates' => 'Certificate table structure',
    'course_participants' => 'Participants table structure', 
    'courses' => 'Courses table structure',
    'users' => 'Users table structure',
    'course_interest' => 'Interest table structure'
];

foreach ($tables_to_check as $table => $description) {
    echo "<h2>📋 $description</h2>";
    
    // Check if table exists
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $table_exists = $stmt->fetch();
        
        if ($table_exists) {
            echo "✅ Table '$table' exists<br>";
            
            // Get table structure
            echo "<h3>🏗️ Structure:</h3>";
            $structure = getTableStructure($pdo, $table);
            
            if (is_array($structure)) {
                echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
                echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
                
                foreach ($structure as $column) {
                    echo "<tr>";
                    echo "<td style='padding: 5px; font-weight: bold;'>" . $column['Field'] . "</td>";
                    echo "<td style='padding: 5px;'>" . $column['Type'] . "</td>";
                    echo "<td style='padding: 5px;'>" . $column['Null'] . "</td>";
                    echo "<td style='padding: 5px;'>" . $column['Key'] . "</td>";
                    echo "<td style='padding: 5px;'>" . ($column['Default'] ?? 'NULL') . "</td>";
                    echo "<td style='padding: 5px;'>" . $column['Extra'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                // Count records
                $count_stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                $count = $count_stmt->fetch()['count'];
                echo "📊 Total records: <strong>$count</strong><br>";
                
                // Show sample data if any exists
                if ($count > 0) {
                    echo "<h3>📄 Sample Data (first 3 records):</h3>";
                    $sample_data = getSampleData($pdo, $table);
                    
                    if (is_array($sample_data) && !empty($sample_data)) {
                        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; font-size: 12px;'>";
                        
                        // Header
                        echo "<tr style='background: #e0e0e0;'>";
                        foreach (array_keys($sample_data[0]) as $column) {
                            echo "<th style='padding: 3px;'>$column</th>";
                        }
                        echo "</tr>";
                        
                        // Data rows
                        foreach ($sample_data as $row) {
                            echo "<tr>";
                            foreach ($row as $value) {
                                $display_value = $value !== null ? htmlspecialchars(substr($value, 0, 50)) : 'NULL';
                                echo "<td style='padding: 3px;'>$display_value</td>";
                            }
                            echo "</tr>";
                        }
                        echo "</table>";
                    }
                }
                
            } else {
                echo $structure;
            }
            
        } else {
            echo "❌ Table '$table' does NOT exist<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error checking table '$table': " . $e->getMessage() . "<br>";
    }
    
    echo "<hr style='margin: 20px 0;'>";
}

// Check for views
echo "<h2>👁️ Database Views</h2>";
try {
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    $views = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($views)) {
        foreach ($views as $view) {
            $view_name = $view['Tables_in_inventijn_cursus'] ?? $view['Tables_in_' . $pdo->query("SELECT DATABASE()")->fetchColumn()];
            echo "✅ View: $view_name<br>";
        }
    } else {
        echo "ℹ️ No views found<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking views: " . $e->getMessage() . "<br>";
}

// Check for stored procedures
echo "<h2>⚙️ Stored Procedures</h2>";
try {
    $stmt = $pdo->query("SHOW PROCEDURE STATUS WHERE Db = DATABASE()");
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($procedures)) {
        foreach ($procedures as $procedure) {
            echo "✅ Procedure: " . $procedure['Name'] . "<br>";
        }
    } else {
        echo "ℹ️ No stored procedures found<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking procedures: " . $e->getMessage() . "<br>";
}

echo "<hr style='margin: 20px 0;'>";
echo "<h2>🎯 Integration Recommendations</h2>";

// Check certificates table specifically
try {
    $cert_structure = getTableStructure($pdo, 'certificates');
    if (is_array($cert_structure)) {
        $cert_columns = array_column($cert_structure, 'Field');
        
        echo "<h3>📋 Certificates Table Analysis:</h3>";
        echo "Available columns: " . implode(', ', $cert_columns) . "<br><br>";
        
        // Check for common columns the unified version expects
        $expected_columns = ['id', 'course_participant_id', 'generated_date', 'file_path', 'download_date'];
        $missing_columns = [];
        
        foreach ($expected_columns as $col) {
            if (in_array($col, $cert_columns)) {
                echo "✅ Column '$col' exists<br>";
            } else {
                echo "❌ Column '$col' MISSING<br>";
                $missing_columns[] = $col;
            }
        }
        
        if (!empty($missing_columns)) {
            echo "<br><strong>🚨 Missing columns need to be added or code needs to be adapted!</strong><br>";
            echo "Missing: " . implode(', ', $missing_columns) . "<br>";
        }
        
    }
} catch (Exception $e) {
    echo "❌ Could not analyze certificates table: " . $e->getMessage() . "<br>";
}

echo "<br><hr>";
echo "<h3>📝 Next Steps:</h3>";
echo "1. ✅ Upload this database_check.php to /admin/<br>";
echo "2. ✅ Run it and send Martijn the output<br>";
echo "3. ✅ Based on results, create schema-aware integration<br>";
echo "4. ✅ Test with real database structure<br>";

?>