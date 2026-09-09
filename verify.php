<?php
declare(strict_types=1);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['found' => false, 'error' => 'Invalid request method.']);
    exit;
}

require_once __DIR__ . '/db.php';

$input = strtoupper(trim($_POST['cert'] ?? ''));

if ($input === '') {
    echo json_encode(['found' => false, 'error' => 'No certificate number provided.']);
    exit;
}

if (!preg_match('/^[A-Z0-9\/\s&.\-]{3,40}$/', $input)) {
    echo json_encode(['found' => false, 'error' => 'Invalid certificate number format.']);
    exit;
}

$pdo  = get_db();
$stmt = $pdo->prepare(
    'SELECT c.cert_number, c.holder_name,
            p.name AS workshop, p.type, p.theme, p.date, p.venue, p.issuer
     FROM certificates c
     JOIN projects p ON p.id = c.project_id
     WHERE c.cert_number = :num
     LIMIT 1'
);
$stmt->execute([':num' => $input]);
$row = $stmt->fetch();

if ($row) {
    echo json_encode([
        'found'    => true,
        'cert_no'  => $row['cert_number'],
        'name'     => $row['holder_name'],
        'type'     => $row['type'],
        'workshop' => $row['workshop'],
        'event'    => $row['theme'],
        'date'     => $row['date'],
        'venue'    => $row['venue'],
        'issuer'   => $row['issuer'],
    ]);
} else {
    echo json_encode([
        'found' => false,
        'error' => 'The ID "' . htmlspecialchars($input, ENT_QUOTES, 'UTF-8')
                 . '" does not match any record in our database. Please check and try again.',
    ]);
}
