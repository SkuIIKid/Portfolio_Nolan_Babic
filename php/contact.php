<?php
/**
 * Traitement du formulaire de contact.
 * Reçoit une requête POST, valide les données, envoie un e-mail
 * et répond en JSON.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/**
 * Envoie une réponse JSON et arrête le script.
 */
function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Seule la méthode POST est acceptée
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['success' => false, 'message' => 'Méthode non autorisée.']);
}

$config = require __DIR__ . '/config.php';

// 2. Anti-spam : champ piège (honeypot)
// Les robots remplissent ce champ caché, pas les humains.
if (!empty($_POST['website'] ?? '')) {
    // On fait croire au robot que tout s'est bien passé
    respond(200, ['success' => true, 'message' => 'Message envoyé.']);
}

// 3. Anti-spam : limitation par IP
$storageDir = $config['storage_dir'];
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0750, true);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = $storageDir . '/rate_' . hash('sha256', $ip) . '.json';
$now = time();
$attempts = [];

if (is_file($rateFile)) {
    $decoded = json_decode((string) file_get_contents($rateFile), true);
    if (is_array($decoded)) {
        // On ne garde que les tentatives encore dans la fenêtre de temps
        $attempts = array_values(array_filter(
            $decoded,
            static fn($t) => is_int($t) && ($now - $t) < $config['rate_limit_seconds']
        ));
    }
}

if (count($attempts) >= $config['rate_limit_max']) {
    respond(429, [
        'success' => false,
        'message' => 'Trop de messages envoyés. Merci de patienter quelques minutes avant de réessayer.',
    ]);
}

// 4. Récupération et nettoyage des champs
$name    = trim((string) ($_POST['name'] ?? ''));
$email   = trim((string) ($_POST['email'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

// 5. Validation
$errors = [];

$nameLen = mb_strlen($name);
if ($nameLen < 2 || $nameLen > $config['max_name_length']) {
    $errors['name'] = 'Nom invalide (2 à ' . $config['max_name_length'] . ' caractères).';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
    $errors['email'] = 'Adresse e-mail invalide.';
}

$subjectLen = mb_strlen($subject);
if ($subjectLen < 3 || $subjectLen > $config['max_subject_length']) {
    $errors['subject'] = 'Objet invalide (3 à ' . $config['max_subject_length'] . ' caractères).';
}

$messageLen = mb_strlen($message);
if ($messageLen < 10 || $messageLen > $config['max_message_length']) {
    $errors['message'] = 'Message invalide (10 à ' . $config['max_message_length'] . ' caractères).';
}

// 6. Protection contre l'injection d'en-têtes e-mail (retours à la ligne)
foreach ([$name, $email, $subject] as $value) {
    if (preg_match('/[\r\n]/', $value)) {
        $errors['name'] = $errors['name'] ?? 'Caractères non autorisés détectés.';
        break;
    }
}

if (!empty($errors)) {
    respond(422, [
        'success' => false,
        'message' => 'Merci de corriger les champs indiqués.',
        'errors'  => $errors,
    ]);
}

// 7. Construction de l'e-mail
$mailSubject = '=?UTF-8?B?' . base64_encode($config['subject_prefix'] . $subject) . '?=';

$body  = "Nouveau message depuis votre portfolio\n";
$body .= "======================================\n\n";
$body .= "Nom     : {$name}\n";
$body .= "E-mail  : {$email}\n";
$body .= "Objet   : {$subject}\n";
$body .= "Date    : " . date('d/m/Y H:i:s') . "\n\n";
$body .= "Message :\n";
$body .= "--------------------------------------\n";
$body .= $message . "\n";
$body .= "--------------------------------------\n";

$fromName = '=?UTF-8?B?' . base64_encode($config['from_name']) . '?=';

$headers = [
    'From'         => "{$fromName} <{$config['from_email']}>",
    'Reply-To'     => $email,   // permet de répondre directement à l'expéditeur
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
    'X-Mailer'     => 'PHP/' . PHP_VERSION,
];

// 8. Sauvegarde locale du message (copie de sécurité si le mail échoue)
$logLine = json_encode([
    'date'    => date('c'),
    'name'    => $name,
    'email'   => $email,
    'subject' => $subject,
    'message' => $message,
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
@file_put_contents($storageDir . '/messages.log', $logLine, FILE_APPEND | LOCK_EX);

// 9. Envoi
$sent = mail($config['recipient_email'], $mailSubject, $body, $headers);

if (!$sent) {
    error_log('[contact.php] Échec de l\'envoi du mail depuis ' . $email);
    respond(500, [
        'success' => false,
        'message' => 'Le message n\'a pas pu être envoyé. Merci de m\'écrire directement par e-mail.',
    ]);
}

// 10. Enregistrement de la tentative pour la limitation par IP
$attempts[] = $now;
@file_put_contents($rateFile, json_encode($attempts), LOCK_EX);

respond(200, [
    'success' => true,
    'message' => 'Merci ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' ! Votre message a bien été envoyé, je vous réponds rapidement.',
]);
