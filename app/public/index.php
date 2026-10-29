<?php

header('Content-Type: application/json; charset=utf-8');

try {
    $host = getenv('DB_HOST');
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');

    $password = trim(
        file_get_contents('/run/secrets/db_app_password')
    );

    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(
            file_get_contents('php://input'),
            true
        );

        $message = trim((string)($input['message'] ?? ''));

        if ($message === '' || strlen($message) > 180) {
            http_response_code(400);

            echo json_encode([
                'error' => 'Message invalide'
            ]);

            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO events (message) VALUES (?)'
        );

        $stmt->execute([$message]);

        http_response_code(201);

        echo json_encode([
            'status' => 'created'
        ]);
        echo "// v1.1" ;
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);

        echo json_encode([
            'error' => 'Méthode non autorisée'
        ]);

        exit;
    }

    $rows = $pdo->query(
        'SELECT id, message, created_at
         FROM events
         ORDER BY id'
    )->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'ok',
        'events' => $rows
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(503);

    echo json_encode([
        'status' => 'unavailable'
    ]);
}
