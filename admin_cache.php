<?php
/**
 * Admin Cache Management Page
 * 
 * This page provides access to the cache management interface.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is already authenticated
$isAuthenticated = false;
if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true) {
    $isAuthenticated = true;
}

// Handle login form submission
if (!$isAuthenticated && isset($_POST['username']) && isset($_POST['password'])) {
    // Replace with your actual authentication logic
    // This is a simple example - in production, use a more secure method
    if ($_POST['username'] === 'Guilherme' && $_POST['password'] === 'VaiPoCaralho69@@') {
        $_SESSION['admin_authenticated'] = true;
        $isAuthenticated = true;
        
        // Set a cookie for convenience (optional)
        setcookie('admin_authenticated', 'true', time() + 3600, '/'); // 1 hour
        
        // Redirect to avoid form resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = 'Invalid username or password';
    }
}

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    // Clear session
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    // Clear cookie
    setcookie('admin_authenticated', '', time() - 3600, '/');
    
    // Redirect to login page
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// If authenticated, redirect to the cache management script
if ($isAuthenticated && !isset($_GET['view'])) {
    header('Location: scripts/common/clear_cache.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Cache Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f5f5f5;
        }
        .container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            margin-top: 0;
            color: #2c3e50;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .error {
            color: #e74c3c;
            margin-bottom: 15px;
        }
        .links {
            margin-top: 20px;
            text-align: center;
        }
        .links a {
            color: #3498db;
            text-decoration: none;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$isAuthenticated): ?>
            <h1>Admin Login</h1>
            
            <?php if (isset($loginError)): ?>
                <div class="error"><?php echo htmlspecialchars($loginError); ?></div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn">Login</button>
            </form>
            
            <div class="links">
                <a href="index.php">Back to Home</a>
            </div>
        <?php else: ?>
            <h1>Admin Panel</h1>
            
            <p>You are logged in as an administrator.</p>
            
            <div class="links">
                <p><a href="scripts/common/clear_cache.php">Manage Cache</a></p>
                <p><a href="?logout=1">Logout</a></p>
                <p><a href="index.php">Back to Home</a></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 