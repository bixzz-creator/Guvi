<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// Config embedded (with fallbacks for local dev)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'root');
define('DB_NAME', getenv('DB_NAME') ?: 'guvi_internship');
define('REDIS_HOST', getenv('REDIS_HOST') ?: '127.0.0.1');
define('REDIS_PORT', getenv('REDIS_PORT') ?: 6379);

// Connect to MySQL
$db = null;
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $exception) {
    echo json_encode(["success" => false, "message" => "MySQL Connection error."]);
    exit;
}

// Connect to Redis
$redis = null;
try {
    $redis = new Redis();
    $redis->connect(REDIS_HOST, REDIS_PORT);
} catch(Exception $e) {
    echo json_encode(["success" => false, "message" => "Redis Connection error."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if(empty($email) || empty($password)) {
        echo json_encode(["success" => false, "message" => "Email and password are required."]);
        exit;
    }

    try {
        $query = "SELECT id, password FROM users WHERE email = :email LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password'])) {
                
                $token = bin2hex(random_bytes(32)); 
                $redis->setex($token, 300, $user['id']);

                echo json_encode([
                    "success" => true, 
                    "message" => "Login successful",
                    "token" => $token
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "Invalid email or password."]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Invalid email or password."]);
        }
    } catch(Exception $e) {
        echo json_encode(["success" => false, "message" => "System error."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid Request Method."]);
}
?>
