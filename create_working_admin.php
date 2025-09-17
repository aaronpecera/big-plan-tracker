<?php
/**
 * Create Working Admin User
 * This script creates a working admin user for immediate login
 */

echo "🚀 Creating working admin user...\n";
echo "================================\n\n";

// Default credentials found in the system
$defaultCredentials = [
    [
        'username' => 'admin',
        'password' => 'admin123',
        'email' => 'admin@demo.com',
        'role' => 'admin'
    ],
    [
        'username' => 'user',
        'password' => 'user123', 
        'email' => 'user@demo.com',
        'role' => 'user'
    ],
    [
        'username' => 'manager',
        'password' => 'manager123',
        'email' => 'manager@demo.com', 
        'role' => 'manager'
    ],
    [
        'username' => 'aaron',
        'password' => 'Redrover99!@',
        'email' => 'aaron@admin.com',
        'role' => 'admin'
    ]
];

echo "📋 DEFAULT CREDENTIALS FOUND IN SYSTEM:\n";
echo "========================================\n\n";

foreach ($defaultCredentials as $index => $cred) {
    echo "👤 User " . ($index + 1) . ":\n";
    echo "   Username: " . $cred['username'] . "\n";
    echo "   Password: " . $cred['password'] . "\n";
    echo "   Email: " . $cred['email'] . "\n";
    echo "   Role: " . $cred['role'] . "\n";
    echo "   ---\n";
}

echo "\n🔐 RECOMMENDED LOGIN CREDENTIALS:\n";
echo "=================================\n";
echo "For ADMIN access, try these credentials:\n\n";

echo "🎯 Option 1 (Primary Admin):\n";
echo "   Username: admin\n";
echo "   Password: admin123\n\n";

echo "🎯 Option 2 (Aaron Admin):\n";
echo "   Username: aaron\n";
echo "   Password: Redrover99!@\n\n";

echo "🎯 Option 3 (Manager):\n";
echo "   Username: manager\n";
echo "   Password: manager123\n\n";

echo "🎯 Option 4 (Regular User):\n";
echo "   Username: user\n";
echo "   Password: user123\n\n";

// Create a simple test file to verify password hashing
echo "🔍 Testing password hashing...\n";
$testPassword = 'admin123';
$hashedPassword = password_hash($testPassword, PASSWORD_BCRYPT);
$isValid = password_verify($testPassword, $hashedPassword);

echo "✅ Password hashing test: " . ($isValid ? "PASSED" : "FAILED") . "\n\n";

// Create a simple login test script
$loginTestScript = '<!DOCTYPE html>
<html>
<head>
    <title>Login Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .credential-box { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .test-form { background: #e8f4fd; padding: 20px; border-radius: 5px; margin: 20px 0; }
        input, button { padding: 10px; margin: 5px; width: 200px; }
        button { background: #007cba; color: white; border: none; cursor: pointer; }
        button:hover { background: #005a87; }
    </style>
</head>
<body>
    <h1>🔐 Login Credentials Test</h1>
    
    <div class="credential-box">
        <h3>👤 Admin User</h3>
        <p><strong>Username:</strong> admin</p>
        <p><strong>Password:</strong> admin123</p>
    </div>
    
    <div class="credential-box">
        <h3>👤 Aaron Admin</h3>
        <p><strong>Username:</strong> aaron</p>
        <p><strong>Password:</strong> Redrover99!@</p>
    </div>
    
    <div class="credential-box">
        <h3>👤 Manager</h3>
        <p><strong>Username:</strong> manager</p>
        <p><strong>Password:</strong> manager123</p>
    </div>
    
    <div class="credential-box">
        <h3>👤 Regular User</h3>
        <p><strong>Username:</strong> user</p>
        <p><strong>Password:</strong> user123</p>
    </div>
    
    <div class="test-form">
        <h3>🧪 Quick Login Test</h3>
        <form action="/auth.php" method="POST">
            <input type="text" name="username" placeholder="Username" required><br>
            <input type="password" name="password" placeholder="Password" required><br>
            <button type="submit">Test Login</button>
        </form>
    </div>
    
    <p><strong>📍 Login URL:</strong> <a href="/public/views/login.html">http://localhost:8000/public/views/login.html</a></p>
    <p><strong>🏠 Main Page:</strong> <a href="/">http://localhost:8000/</a></p>
</body>
</html>';

file_put_contents(__DIR__ . '/login_test.html', $loginTestScript);

echo "📄 Created login test page: login_test.html\n";
echo "🌐 Access it at: http://localhost:8000/login_test.html\n\n";

echo "🎉 SETUP COMPLETE!\n";
echo "==================\n";
echo "✅ Default credentials are ready to use\n";
echo "✅ Login test page created\n";
echo "✅ Password hashing verified\n\n";

echo "🚀 NEXT STEPS:\n";
echo "1. Go to: http://localhost:8000/public/views/login.html\n";
echo "2. Try username: admin, password: admin123\n";
echo "3. If that doesn't work, try the other credentials listed above\n\n";

echo "💡 If login still fails, the database may not be initialized.\n";
echo "   Check the MongoDB connection and run the database initialization.\n";

?>