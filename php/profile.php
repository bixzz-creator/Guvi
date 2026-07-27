<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");



// Config embedded (with fallbacks for local dev)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'root');
define('DB_NAME', getenv('DB_NAME') ?: 'guvi_internship');
define('MONGO_URI', getenv('MONGO_URI') ?: 'mongodb://127.0.0.1:27017');
define('MONGO_DB_NAME', getenv('MONGO_DB_NAME') ?: 'guvi_internship_profile');
define('REDIS_HOST', getenv('REDIS_HOST') ?: '127.0.0.1');
define('REDIS_PORT', getenv('REDIS_PORT') ?: 6379);

// Connect to Redis for Auth
$redis = null;
try {
    $redis = new Redis();
    $redis->connect(REDIS_HOST, REDIS_PORT);
} catch(Exception $e) {
    echo json_encode(["success" => false, "message" => "Redis Connection error."]);
    exit;
}

// 1. Authenticate Request
$headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
if (!isset($headers['Authorization']) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
}

if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "No authorization header found."]);
    exit;
}

$parts = explode(" ", $headers['Authorization']);
if (count($parts) != 2 || $parts[0] !== 'Bearer') {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Invalid authorization format."]);
    exit;
}
$token = $parts[1];
$user_id = $redis->get($token);

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Session expired or invalid."]);
    exit;
}
$redis->expire($token, 3600); // refresh expiry

// Handle Logout Action
if ((isset($_GET['action']) && $_GET['action'] == 'logout') || (isset($_POST['action']) && $_POST['action'] == 'logout')) {
    $redis->del($token);
    echo json_encode(["success" => true, "message" => "Logged out successfully."]);
    exit;
}

// Connect to MySQL
$mysql = null;
try {
    $mysql = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $exception) {
    echo json_encode(["success" => false, "message" => "MySQL Connection error."]);
    exit;
}

// Connect to MongoDB using Native Low-Level Driver (No Composer Needed!)
$mongoManager = null;
try {
    if (class_exists('MongoDB\Driver\Manager')) {
        $mongoManager = new MongoDB\Driver\Manager(MONGO_URI);
    } else {
        throw new Exception("Native MongoDB Driver not found.");
    }
} catch(Exception $e) {
    echo json_encode(["success" => false, "message" => "MongoDB Connection error."]);
    exit;
}

// Handle GET Request (Fetch Profile)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "SELECT fullname, email FROM users WHERE id = :id LIMIT 1";
        $stmt = $mysql->prepare($query);
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();
        $mysql_data = $stmt->fetch(PDO::FETCH_ASSOC);

        $queryFilter = ['user_id' => $user_id];
        $queryOptions = ['limit' => 1];
        $mongoQuery = new MongoDB\Driver\Query($queryFilter, $queryOptions);
        $cursor = $mongoManager->executeQuery(MONGO_DB_NAME . '.profiles', $mongoQuery);
        $mongoArray = $cursor->toArray();
        $mongo_data = !empty($mongoArray) ? $mongoArray[0] : null;

        $response = [
            "success" => true,
            "data" => [
                "mysql" => $mysql_data,
                "mongo" => $mongo_data ? $mongo_data : null
            ]
        ];
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error fetching profile."]);
    }
}

// Handle POST Request (Update Profile)
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updateData = [
            'age' => isset($_POST['age']) ? $_POST['age'] : '',
            'dob' => isset($_POST['dob']) ? $_POST['dob'] : '',
            'phone' => isset($_POST['phone']) ? $_POST['phone'] : '',
            'gender' => isset($_POST['gender']) ? $_POST['gender'] : '',
            'address' => isset($_POST['address']) ? $_POST['address'] : '',
            'city' => isset($_POST['city']) ? $_POST['city'] : '',
            'state' => isset($_POST['state']) ? $_POST['state'] : '',
            'country' => isset($_POST['country']) ? $_POST['country'] : '',
            'bio' => isset($_POST['bio']) ? $_POST['bio'] : '',
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Upsert into MongoDB using Native Driver
        $bulk = new MongoDB\Driver\BulkWrite;
        $bulk->update(
            ['user_id' => $user_id],
            ['$set' => $updateData],
            ['multi' => false, 'upsert' => true]
        );
        $mongoManager->executeBulkWrite(MONGO_DB_NAME . '.profiles', $bulk);

        echo json_encode(["success" => true, "message" => "Profile updated successfully!"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error updating profile."]);
    }
}
?>
