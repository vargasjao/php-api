<?php
//Database connection
$host = '127.0.0.1';
$user = 'apiuser'; 
$pass = '123456';
$db   = 'api';

$con = mysqli_connect($host, $user, $pass, $db);

if (!$con) {
    echo ('Response code: '. http_response_code(500));
}

// Creates the token, saves it to the database and returns the token details
function createToken($con, $user_id) {
    $token = bin2hex(random_bytes(16));
    $expiring_time = time() + 900;
    $expires_at = date("H:i:s", $expiring_time);

    $stmt_token = mysqli_prepare($con, "INSERT INTO access_tokens (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt_token, "iss", $user_id, $token, $expires_at);
    mysqli_stmt_execute($stmt_token);

    echo json_encode([
        "token" => $token,
        "expires_in" => '900 seconds',
        "expires_at" => $expires_at
    ]);

}

$method = $_SERVER["REQUEST_METHOD"];
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

switch ($method) {
    case 'GET':
        //Returns user data  authorized by token
        if($path == "/private"){
            $headers = getallheaders();
            $auth = $headers["Authorization"] ?? null;

            if(!$auth || trim($auth) === ""){
                http_response_code(401);
                echo json_encode(["error 401" => "Authorization token is missing."]);
                exit;
            }

            $token = trim(preg_replace('/^Bearer\s+/i','',$auth));
            $stmt_token = mysqli_prepare($con, "SELECT A.id, A.email, B.expires_at, B.revoked_at FROM users A JOIN access_tokens B ON B.user_id=A.id WHERE B.token=? LIMIT 1");
            mysqli_stmt_bind_param($stmt_token, "s", $token);
            mysqli_stmt_execute($stmt_token);
            $tokenResponse = mysqli_stmt_get_result($stmt_token);
            $row = mysqli_fetch_assoc($tokenResponse);

            if(!$row){
                http_response_code(401);
                echo json_encode(["error 401" => "Invalid token."]);
                exit;
            }

            if(strtotime($row["expires_at"]) < time() || $row["revoked_at"] !== null){
                http_response_code(401);
                echo json_encode(["error 401" => "Token expired or revoked."]);
                exit;
            }
            
            echo 'Response code: '. http_response_code(200);
            echo json_encode([
                "message" => "Hello from a private endpoint",
                "user" => ["id" => $row['id'], "email" => $row['email']]
            ]);
            exit;
        } elseif ($path == "/public") {
            echo json_encode(["message" => "Hello World"]);
            exit;
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Endpoint not found."]);
            exit;
        } 
        break;

    case 'POST':
        $body = file_get_contents("php://input");
        $data = json_decode($body, true);
        $email = $data["email"] ?? null;
        $password = $data["password"] ?? null;

        if($email === null || $password === null){
            http_response_code(401);
            echo json_encode(["error" => "Email and password are required."]);
            exit;
        }

        $stmt = mysqli_prepare($con, "SELECT id, password_hash FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);

        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        
        if($path == "/login"){
        // validate credentials and create token if valid

            if($user && password_verify($password, $user["password_hash"])){
                http_response_code(200);
                
                $user_id = $user["id"];
                $response = createToken($con, $user_id);
                echo json_encode($response);
            } else {
                http_response_code(401);
                echo json_encode(["error" => "Invalid credentials."]);
            }
        } elseif ($path == "/refresh") {
            // revoke existing active tokens and create a new one if credentials are valid

            if($user && password_verify($password, $user["password_hash"])){
                $user_id = $user["id"];

                $stmt_revoke = mysqli_prepare($con, "UPDATE access_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL");
                mysqli_stmt_bind_param($stmt_revoke, "i", $user_id);
                mysqli_stmt_execute($stmt_revoke);

                $response = createToken($con, $user_id);
                http_response_code(200);
            } else {
                http_response_code(401);
                echo json_encode(["error" => "Invalid credentials."]);
            }
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Endpoint not found."]);
        }
        break;
}
?>