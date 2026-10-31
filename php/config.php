<?php
/**
 * Configuration du formulaire de contact.
 * Ce fichier ne doit jamais être exposé côté client.
 */

return [
    // Adresse qui reçoit les messages
    'recipient_email' => 'nolan.babic.pro@gmail.com',

    // Nom affiché comme expéditeur technique.
    // IMPORTANT : utiliser une adresse de VOTRE domaine (ex. no-reply@votre-domaine.fr)
    // sinon les mails risquent de finir en spam.
    'from_email' => 'no-reply@votre-domaine.fr',
    'from_name'  => 'Portfolio Nolan Babic',

    // Préfixe de l'objet des e-mails reçus
    'subject_prefix' => '[Portfolio] ',

    // Anti-spam : nombre max de messages par IP sur la période donnée
    'rate_limit_max'     => 3,
    'rate_limit_seconds' => 600, // 10 minutes

    // Limites de taille
    'max_name_length'    => 100,
    'max_subject_length' => 150,
    'max_message_length' => 2000,

    // Dossier où sont sauvegardés les messages (copie de sécurité) et le suivi anti-spam.
    // Doit être accessible en écriture par Apache (www-data) et NON accessible depuis le web.
    'storage_dir' => __DIR__ . '/../storage',
];
