<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

function db_connection(): ?PDO
{
    static $pdo = null;
    static $attempted = false;

    if ($attempted) {
        return $pdo;
    }

    $attempted = true;
    $config = app_config();

    try {
        $dsn = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, (string)$config['db_user'], (string)$config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        $pdo = null;
    }

    return $pdo;
}

function save_consent_and_session(bool $consent): bool
{
    if (!$consent) {
        $_SESSION['consent_saved'] = false;
        return true;
    }

    if (!empty($_SESSION['health_check_session_id'])) {
        $_SESSION['consent_saved'] = true;
        return true;
    }

    $pdo = db_connection();
    if (!$pdo) {
        return false;
    }

    $data = collect_health_check();
    $profile = $data['profile'] ?? [];
    $issue = $data['issue'] ?? [];
    $location = $data['location'] ?? [];
    $followup = $data['followup'] ?? [];
    $result = $_SESSION['result'] ?? [];
    $config = app_config();

    try {
        $pdo->beginTransaction();

        $consentStmt = $pdo->prepare(
            'INSERT INTO consent_records (anonymous_session_id, consent_type, consent_value, consent_version)
             VALUES (:anonymous_session_id, :consent_type, :consent_value, :consent_version)'
        );
        $consentStmt->execute([
            ':anonymous_session_id' => $data['anonymous_session_id'],
            ':consent_type' => 'anonymous_health_check_storage',
            ':consent_value' => 1,
            ':consent_version' => '0.1',
        ]);

        $sessionStmt = $pdo->prepare(
            'INSERT INTO health_check_sessions
             (anonymous_session_id, age_range, sex, selected_body_area, precise_location, duration, severity,
              worsening_status, warning_symptoms, red_flag_triggered, urgency_level, confidence, model_id,
              app_version, consent_to_store)
             VALUES
             (:anonymous_session_id, :age_range, :sex, :selected_body_area, :precise_location, :duration, :severity,
              :worsening_status, :warning_symptoms, :red_flag_triggered, :urgency_level, :confidence, :model_id,
              :app_version, :consent_to_store)'
        );

        $age = isset($profile['age']) ? (int)$profile['age'] : 0;
        $sessionStmt->execute([
            ':anonymous_session_id' => $data['anonymous_session_id'],
            ':age_range' => age_range($age),
            ':sex' => $profile['sex'] ?? null,
            ':selected_body_area' => $issue['body_area'] ?? null,
            ':precise_location' => $location['precise_location'] ?? null,
            ':duration' => $followup['duration'] ?? null,
            ':severity' => $followup['severity'] ?? null,
            ':worsening_status' => $followup['worsening'] ?? null,
            ':warning_symptoms' => list_text($followup['warning_symptoms'] ?? []),
            ':red_flag_triggered' => !empty($result['redFlagTriggered']) ? 1 : 0,
            ':urgency_level' => $result['urgencyLevel'] ?? null,
            ':confidence' => $result['confidence'] ?? null,
            ':model_id' => $_SESSION['result_model_id'] ?? ($config['gemini_model'] ?? null),
            ':app_version' => $config['app_version'] ?? '0.1.0',
            ':consent_to_store' => 1,
        ]);

        $_SESSION['health_check_session_id'] = (int)$pdo->lastInsertId();
        $_SESSION['consent_saved'] = true;
        $pdo->commit();

        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

function save_feedback(string $helpfulness, string $reason, string $feedbackText): bool
{
    $pdo = db_connection();
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO health_check_feedback
             (anonymous_session_id, health_check_session_id, helpfulness, missing_reason, feedback_text)
             VALUES (:anonymous_session_id, :health_check_session_id, :helpfulness, :missing_reason, :feedback_text)'
        );
        $stmt->execute([
            ':anonymous_session_id' => app_session_id(),
            ':health_check_session_id' => $_SESSION['health_check_session_id'] ?? null,
            ':helpfulness' => $helpfulness,
            ':missing_reason' => $reason ?: null,
            ':feedback_text' => $feedbackText ?: null,
        ]);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}
