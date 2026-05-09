<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/redflags.php';
require_once __DIR__ . '/../includes/gemini.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('../loading.php');
}

if (($_POST['action'] ?? '') === 'save_consent') {
    if (empty($_SESSION['result']) || !is_array($_SESSION['result'])) {
        redirect_to('../index.php');
    }

    $consent = (string)($_POST['consent_to_store'] ?? '0') === '1';
    if ($consent) {
        $saved = save_consent_and_session(true);
        set_flash('notice', $saved
            ? 'Thanks. Your anonymous health-check answers were saved.'
            : 'We could not save your preference just now. Your result still worked and no health-check answers were saved.');
    } else {
        $_SESSION['consent_saved'] = false;
        set_flash('notice', 'No anonymous health-check answers were saved.');
    }

    redirect_to('../result.php');
}

require_step('profile', '../index.php');
require_step('issue', '../describe.php');
require_step('location', '../location.php');
require_step('followup', '../location.php');

if (empty($_SESSION['context']) || !is_array($_SESSION['context'])) {
    if (!save_context_from_post()) {
        redirect_to('../location.php');
    }
}

unset($_SESSION['result'], $_SESSION['red_flag_reasons'], $_SESSION['result_model_id'], $_SESSION['health_check_session_id'], $_SESSION['consent_saved']);
unset($_SESSION['gemini_error']);

$data = collect_health_check();
$flags = check_red_flags($data);

if ($flags['matched']) {
    $_SESSION['red_flag_reasons'] = $flags['reasons'];
    $_SESSION['result'] = emergency_result($data, $flags['reasons']);
    $_SESSION['result_model_id'] = 'red-flag-rules';

    if ((string)($_POST['consent_to_store'] ?? '0') === '1') {
        save_consent_and_session(true);
    }

    redirect_to('../result.php');
}

$geminiResponse = call_gemini(build_system_prompt(), build_user_prompt($data));
$result = null;
$_SESSION['gemini_error'] = $geminiResponse['error'] ?? null;

if ($geminiResponse['ok']) {
    $result = validate_ai_result($geminiResponse['data'], $data);
    $_SESSION['gemini_error'] = $result ? null : 'invalid_or_missing_result_fields';
}

$_SESSION['result'] = $result ?: fallback_result($data);
$_SESSION['result_model_id'] = $geminiResponse['model_id'] ?? (app_config()['gemini_model'] ?? 'gemini-flash-latest');

if ((string)($_POST['consent_to_store'] ?? '0') === '1') {
    save_consent_and_session(true);
}

redirect_to('../result.php');
