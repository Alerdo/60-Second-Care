<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('../result.php');
}

if (empty($_SESSION['result']) || !is_array($_SESSION['result'])) {
    redirect_to('../index.php');
}

$helpfulness = clean_text($_POST['helpfulness'] ?? '', 20);
$reason = clean_text($_POST['missing_reason'] ?? '', 50);
$feedbackText = clean_multiline($_POST['feedback_text'] ?? '', 800);

if (!in_array($helpfulness, ['Yes', 'Somewhat', 'No'], true)) {
    set_flash('error', 'Please choose whether the result was helpful.');
    redirect_to('../result.php#feedback');
}

if ($helpfulness !== 'No') {
    $reason = '';
}

$saved = save_feedback($helpfulness, $reason, $feedbackText);
set_flash('notice', $saved ? 'Thanks for the feedback.' : 'We could not save the feedback just now.');

redirect_to('../result.php#feedback');
