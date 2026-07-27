<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// Config embedded (with fallbacks for local dev)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'root');
define('DB_NAME', getenv('DB_NAME') ?: 'guvi_internship');

// Connect to MySQL
$db = null;
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $exception) {
    echo json_encode(["success" => false, "message" => "Database Connection error."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
    $lastname = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if(empty($firstname) || empty($lastname) || empty($email) || empty($password)) {
        echo json_encode(["success" => false, "message" => "All fields are required."]);
        exit;
    }

    $fullname = trim($firstname . ' ' . $lastname);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Invalid email format."]);
        exit;
    }

    if(strlen($password) < 6) {
        echo json_encode(["success" => false, "message" => "Password must be at least 6 characters."]);
        exit;
    }

    try {
        $check_query = "SELECT id FROM users WHERE email = :email LIMIT 1";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();

        if ($check_stmt->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "Email is already registered."]);
            exit;
        }

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $insert_query = "INSERT INTO users (fullname, email, password) VALUES (:fullname, :email, :password)";
        $insert_stmt = $db->prepare($insert_query);
        
        $insert_stmt->bindParam(':fullname', $fullname);
        $insert_stmt->bindParam(':email', $email);
        $insert_stmt->bindParam(':password', $hashed_password);

        if ($insert_stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Registration successful!"]);
        } else {
            echo json_encode(["success" => false, "message" => "Registration failed."]);
        }
    } catch(PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database error."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid Request Method."]);
}
?>
