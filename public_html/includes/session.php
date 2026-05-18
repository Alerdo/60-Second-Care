<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

const CARE_TOTAL_STEPS = 6;
const CARE_DISCLAIMER = 'This tool gives guidance only and does not replace professional medical advice.';

function app_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'db_host' => 'localhost',
        'db_name' => 'your_db',
        'db_user' => 'your_user',
        'db_pass' => 'your_pass',
        'gemini_api_key' => getenv('GEMINI_API_KEY') ?: '',
        'gemini_model' => 'gemini-flash-latest',
        'app_version' => '0.1.0',
    ];

    $configPath = dirname(__DIR__) . '/config.php';
    $examplePath = dirname(__DIR__) . '/config.example.php';
    $loaded = [];

    if (is_file($configPath)) {
        $loaded = require $configPath;
    } elseif (is_file($examplePath)) {
        $loaded = require $examplePath;
    }

    if (!is_array($loaded)) {
        $loaded = [];
    }

    $config = array_merge($defaults, $loaded);
    $config['gemini_api_key'] = getenv('GEMINI_API_KEY') ?: (string)($config['gemini_api_key'] ?? '');

    return $config;
}

function app_session_id(): string
{
    if (empty($_SESSION['anonymous_session_id'])) {
        $_SESSION['anonymous_session_id'] = bin2hex(random_bytes(16));
    }

    return (string)$_SESSION['anonymous_session_id'];
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function clean_text($value, int $maxLength = 500): string
{
    if (is_array($value)) {
        $value = implode(' ', array_map(static fn($item) => is_scalar($item) ? (string)$item : '', $value));
    } elseif (is_object($value)) {
        $value = '';
    }

    $text = trim(strip_tags((string)$value));
    $text = preg_replace('/[ \t\r\n]+/', ' ', $text) ?? '';

    return substr($text, 0, $maxLength);
}

function clean_multiline($value, int $maxLength = 1200): string
{
    if (is_array($value)) {
        $value = implode("\n", array_map(static fn($item) => is_scalar($item) ? (string)$item : '', $value));
    } elseif (is_object($value)) {
        $value = '';
    }

    $text = trim(strip_tags((string)$value));
    $text = preg_replace("/\r\n|\r/", "\n", $text) ?? '';
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? '';

    return substr($text, 0, $maxLength);
}

function post_array(string $key): array
{
    $value = $_POST[$key] ?? [];
    if (!is_array($value)) {
        $value = [$value];
    }

    $clean = [];
    foreach ($value as $item) {
        $item = clean_text($item, 80);
        if ($item !== '' && !in_array($item, $clean, true)) {
            $clean[] = $item;
        }
    }

    return $clean;
}

function none_clears(array $items, string $noneValue = 'None'): array
{
    if (in_array($noneValue, $items, true)) {
        return [$noneValue];
    }

    return array_values(array_filter($items, static function ($item) use ($noneValue) {
        return $item !== $noneValue && $item !== '';
    }));
}

function list_text(array $items): string
{
    $items = array_values(array_filter($items, static fn($item) => $item !== ''));

    return $items ? implode(', ', $items) : 'None';
}

function value_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = str_replace(['&', '/', '-'], [' and ', ' ', ' '], $value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
    return trim($value, '_');
}

function pregnancy_applies(string $sex, int $age): bool
{
    return $sex === 'Female' && $age >= 12 && $age <= 55;
}

function normalize_country_code(string $location): string
{
    $code = strtoupper(trim($location));
    if (preg_match('/^[A-Z]{2}$/', $code)) {
        return $code;
    }

    $lower = strtolower($location);
    if (str_contains($lower, 'united states') || preg_match('/\b(u\.s\.a?|usa)\b/', $lower)) {
        return 'US';
    }
    return 'GB';
}

function local_care_terms(string $country): array
{
    if ($country === 'US') {
        return [
            'local_emergency_number' => '911',
            'local_urgent_care_term' => 'ER',
            'local_primary_care_term' => 'PCP',
        ];
    }

    return [
        'local_emergency_number' => '999',
        'local_urgent_care_term' => 'A&E',
        'local_primary_care_term' => 'GP',
    ];
}

function associated_symptom_options(string $region): array
{
    $key = value_key($region ?: 'Other');
    if (str_contains($key, 'head')) {
        return [
            'fever' => 'Fever',
            'nausea_or_vomiting' => 'Nausea or vomiting',
            'vision_changes' => 'Vision changes',
            'neck_stiffness' => 'Neck stiffness',
            'light_sensitivity' => 'Light sensitivity',
            'weakness_one_side' => 'Weakness on one side of body',
            'difficulty_speaking' => 'Difficulty speaking',
            'confusion' => 'Confusion',
            'recent_head_injury' => 'Recent head injury',
        ];
    }
    if (str_contains($key, 'neck')) {
        return [
            'fever' => 'Fever',
            'stiffness' => 'Stiffness',
            'numbness_in_arms' => 'Numbness in arms',
            'recent_injury' => 'Recent injury',
            'difficulty_swallowing' => 'Difficulty swallowing',
        ];
    }
    if (str_contains($key, 'chest')) {
        return [
            'shortness_of_breath' => 'Shortness of breath',
            'pain_radiating_to_arm_jaw' => 'Pain radiating to arm/jaw',
            'sweating' => 'Sweating',
            'nausea' => 'Nausea',
            'cough' => 'Cough',
            'fever' => 'Fever',
            'heart_racing' => 'Heart racing',
        ];
    }
    if (str_contains($key, 'stomach')) {
        return [
            'nausea_or_vomiting' => 'Nausea or vomiting',
            'diarrhoea' => 'Diarrhoea',
            'constipation' => 'Constipation',
            'blood_in_stool' => 'Blood in stool',
            'fever' => 'Fever',
            'loss_of_appetite' => 'Loss of appetite',
            'bloating' => 'Bloating',
            'pain_when_urinating' => 'Pain when urinating',
        ];
    }
    if (str_contains($key, 'arm')) {
        return [
            'numbness_or_tingling' => 'Numbness or tingling',
            'weakness' => 'Weakness',
            'swelling' => 'Swelling',
            'recent_injury' => 'Recent injury',
            'discoloration' => 'Discoloration',
        ];
    }
    if (str_contains($key, 'leg')) {
        return [
            'swelling_one_leg' => 'Swelling (one leg)',
            'calf_pain' => 'Calf pain',
            'numbness' => 'Numbness',
            'weakness' => 'Weakness',
            'discoloration' => 'Discoloration',
            'recent_long_travel_immobility' => 'Recent long travel/immobility',
        ];
    }

    return [
        'fever' => 'Fever',
        'fatigue' => 'Fatigue',
        'unexplained_weight_loss' => 'Unexplained weight loss',
        'night_sweats' => 'Night sweats',
        'rash' => 'Rash',
    ];
}

function has_value(array $items, string $needle): bool
{
    foreach ($items as $item) {
        if (strcasecmp((string)$item, $needle) === 0) {
            return true;
        }
    }

    return false;
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function set_flash(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash(string $key): ?string
{
    if (empty($_SESSION['flash'][$key])) {
        return null;
    }

    $message = (string)$_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $message;
}

function clear_current_check(): void
{
    foreach ([
        'profile',
        'issue',
        'location',
        'followup',
        'context',
        'result',
        'red_flag_reasons',
        'result_model_id',
        'health_check_session_id',
        'consent_saved',
    ] as $key) {
        unset($_SESSION[$key]);
    }
}

function require_step(string $sessionKey, string $redirectPath): void
{
    if (empty($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
        redirect_to($redirectPath);
    }
}

function save_profile_from_post(): bool
{
    $sex = clean_text($_POST['sex'] ?? '', 30);
    $age = filter_var($_POST['age'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 120],
    ]);
    $conditions = none_clears(post_array('conditions'));
    $userLocation = clean_text($_POST['user_location'] ?? '', 100);
    $locationCountry = clean_text($_POST['location_country'] ?? '', 2);
    $allergies = none_clears(post_array('allergies'), 'None known');
    $allergyOther = clean_text($_POST['allergy_other'] ?? '', 100);
    $pregnancyStatus = clean_text($_POST['pregnancy_status'] ?? '', 30);

    if (!in_array($sex, ['Male', 'Female', 'Prefer not to say'], true)) {
        $sex = 'Prefer not to say';
    }

    if (!$conditions) {
        $conditions = ['None'];
    }
    if (!$allergies) {
        $allergies = ['None known'];
    }
    if (in_array('Other', $allergies, true) && $allergyOther !== '') {
        $allergies[] = 'Other: ' . $allergyOther;
    }

    if ($age === false) {
        set_flash('error', 'Please complete the profile details before continuing.');
        return false;
    }

    if (pregnancy_applies($sex, (int)$age)) {
        if (!in_array($pregnancyStatus, ['No', 'Yes', 'Possibly', 'Prefer not to say'], true)) {
            set_flash('error', 'Please answer the pregnancy question before continuing.');
            return false;
        }
    } else {
        $pregnancyStatus = 'not_applicable';
    }

    $country = $locationCountry !== '' ? normalize_country_code($locationCountry) : normalize_country_code($userLocation);

    $_SESSION['profile'] = [
        'sex' => $sex,
        'age' => (int)$age,
        'conditions' => $conditions,
        'user_location' => $userLocation,
        'location' => [
            'city' => $userLocation,
            'area' => '',
            'country' => $country,
        ],
        'allergies' => $allergies,
        'pregnancy_status' => $pregnancyStatus,
    ];

    return true;
}

function save_issue_from_post(): bool
{
    $description = clean_multiline($_POST['description'] ?? '', 1000);
    $bodyArea = clean_text($_POST['body_area'] ?? '', 50);
    $quality = none_clears(post_array('pain_quality'), 'None');
    $qualityOther = clean_text($_POST['pain_quality_other'] ?? '', 100);
    $onset = clean_text($_POST['onset'] ?? '', 60);

    if ($description === '' && $bodyArea === '') {
        set_flash('error', 'Please add a short description, choose a body area, or both.');
        return false;
    }
    if (!$quality || $quality === ['None']) {
        set_flash('error', 'Please select how the pain feels before continuing.');
        return false;
    }
    if (in_array('Other', $quality, true) && $qualityOther !== '') {
        $quality[] = 'Other: ' . $qualityOther;
    }
    if (!in_array($onset, ['Suddenly (within minutes)', 'Gradually (over hours)', 'Comes and goes', 'Not sure'], true)) {
        set_flash('error', 'Please select how it started before continuing.');
        return false;
    }

    $_SESSION['issue'] = [
        'description' => $description,
        'body_area' => $bodyArea,
        'quality' => $quality,
        'onset' => $onset,
    ];

    return true;
}

function save_location_from_post(): bool
{
    $preciseLocation = clean_text($_POST['precise_location'] ?? '', 100);

    if ($preciseLocation === '') {
        set_flash('error', 'Please choose or enter a more precise location.');
        return false;
    }

    $_SESSION['location'] = [
        'precise_location' => $preciseLocation,
    ];

    return true;
}

function save_followup_from_post(): bool
{
    $duration = clean_text($_POST['duration'] ?? '', 50);
    $severityScore = filter_var($_POST['severity_0_10'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 10],
    ]);
    if ($severityScore === false) {
        $severityScore = 5;
    }
    $severity = $severityScore <= 3 ? 'Mild' : ($severityScore <= 6 ? 'Moderate' : 'Severe');
    $worsening = clean_text($_POST['worsening'] ?? '', 20);
    $warningSymptoms = none_clears(post_array('warning_symptoms'));
    $associatedAll = post_array('associated_symptoms_all');
    $associatedSelected = none_clears(post_array('associated_symptoms'), 'None of these');
    $associatedPresent = in_array('None of these', $associatedSelected, true) ? [] : $associatedSelected;
    $associatedDenied = array_values(array_diff($associatedAll, $associatedPresent));
    $redFlagAnswers = [];
    $conditional = [];

    foreach ($_POST as $key => $value) {
        if (str_starts_with((string)$key, 'conditional_')) {
            $conditional[(string)$key] = clean_text($value, 80);
        }
        if (str_starts_with((string)$key, 'red_flag_')) {
            $redFlagAnswers[(string)$key] = clean_text($value, 20);
        }
    }

    $validWorsening = ['Yes', 'No', 'Not sure'];

    if ($duration === ''
        || !in_array($worsening, $validWorsening, true)
        || !$warningSymptoms) {
        set_flash('error', 'Please add how long this has been happening and choose a severity before continuing.');
        return false;
    }
    if (!$associatedAll) {
        $associatedAll = array_keys(associated_symptom_options($_SESSION['issue']['body_area'] ?? 'Other'));
        $associatedDenied = array_values(array_diff($associatedAll, $associatedPresent));
    }

    $_SESSION['followup'] = [
        'duration' => $duration,
        'severity' => $severity,
        'severity_0_10' => (int)$severityScore,
        'worsening' => $worsening,
        'warning_symptoms' => $warningSymptoms,
        'associated_symptoms' => [
            'present' => $associatedPresent,
            'denied' => $associatedDenied,
        ],
        'red_flag_screen' => [
            'triggered' => (string)($_POST['red_flag_triggered'] ?? '0') === '1',
            'answers' => $redFlagAnswers,
        ],
        'conditional' => $conditional,
    ];

    return true;
}

function save_context_from_post(): bool
{
    $history = none_clears(post_array('medical_history'));
    if (!$history && !empty($_SESSION['profile']['conditions'])) {
        $history = (array)$_SESSION['profile']['conditions'];
    }
    $medicationStatus = clean_text($_POST['medication_status'] ?? '', 10);
    $medicationText = clean_multiline($_POST['medication_taken'] ?? ($_POST['medication_text'] ?? ''), 500);
    $medicationDose = clean_text($_POST['medication_dose'] ?? '', 80);
    $medicationTime = clean_text($_POST['medication_time_ago'] ?? '', 80);
    $medicationEffect = clean_text($_POST['medication_effect'] ?? '', 80);
    $allergyStatus = clean_text($_POST['allergy_status'] ?? '', 10);
    $allergyText = clean_multiline($_POST['allergy_text'] ?? '', 500);

    if (!$history) {
        $history = ['None'];
    }

    if (!in_array($medicationStatus, ['No', 'Yes'], true)) {
        $medicationStatus = $medicationText !== '' ? 'Yes' : 'No';
    }

    if (!in_array($allergyStatus, ['No', 'Yes'], true)) {
        $allergyStatus = $allergyText !== '' ? 'Yes' : 'No';
    }

    if (!in_array($medicationStatus, ['No', 'Yes'], true) || !in_array($allergyStatus, ['No', 'Yes'], true)) {
        set_flash('error', 'Please complete the medical context before getting your result.');
        return false;
    }

    $_SESSION['context'] = [
        'medical_history' => $history,
        'medication_status' => $medicationStatus,
        'medication_text' => $medicationStatus === 'Yes' ? $medicationText : '',
        'medication_dose' => $medicationStatus === 'Yes' ? $medicationDose : '',
        'medication_time_ago' => $medicationStatus === 'Yes' ? $medicationTime : '',
        'medication_effect' => $medicationStatus === 'Yes' ? $medicationEffect : '',
        'allergy_status' => $allergyStatus,
        'allergy_text' => $allergyStatus === 'Yes' ? $allergyText : '',
    ];

    return true;
}

function collect_health_check(): array
{
    app_session_id();
    $data = [
        'anonymous_session_id' => $_SESSION['anonymous_session_id'],
        'profile' => $_SESSION['profile'] ?? [],
        'issue' => $_SESSION['issue'] ?? [],
        'location' => $_SESSION['location'] ?? [],
        'followup' => $_SESSION['followup'] ?? [],
        'context' => $_SESSION['context'] ?? [],
    ];
    $data['structured'] = structured_health_payload($data);

    return $data;
}

function structured_health_payload(array $data): array
{
    $profile = $data['profile'] ?? [];
    $issue = $data['issue'] ?? [];
    $followup = $data['followup'] ?? [];
    $context = $data['context'] ?? [];
    $country = (string)($profile['location']['country'] ?? normalize_country_code((string)($profile['user_location'] ?? '')));
    $associated = $followup['associated_symptoms'] ?? ['present' => [], 'denied' => []];

    return [
        'patient' => [
            'age' => (int)($profile['age'] ?? 0),
            'sex' => value_key((string)($profile['sex'] ?? 'prefer_not_to_say')),
            'location' => $profile['location'] ?? ['city' => (string)($profile['user_location'] ?? ''), 'area' => '', 'country' => $country],
            'pregnancy_status' => value_key((string)($profile['pregnancy_status'] ?? 'not_applicable')),
            'conditions' => array_map('value_key', (array)($profile['conditions'] ?? ['None'])),
            'allergies' => array_map('value_key', (array)($profile['allergies'] ?? ['None known'])),
        ],
        'complaint' => [
            'free_text' => (string)($issue['description'] ?? ''),
            'body_region' => value_key((string)($issue['body_area'] ?? 'other')),
            'quality' => array_map('value_key', (array)($issue['quality'] ?? [])),
            'onset' => value_key((string)($issue['onset'] ?? 'not_sure')),
            'duration' => (string)($followup['duration'] ?? ''),
            'severity_0_10' => (int)($followup['severity_0_10'] ?? 5),
        ],
        'associated_symptoms' => [
            'present' => array_values((array)($associated['present'] ?? [])),
            'denied' => array_values((array)($associated['denied'] ?? [])),
        ],
        'treatment_so_far' => [
            'medication_taken' => (string)($context['medication_text'] ?? ''),
            'dose' => (string)($context['medication_dose'] ?? ''),
            'time_ago' => value_key((string)($context['medication_time_ago'] ?? '')),
            'effect' => value_key((string)($context['medication_effect'] ?? '')),
        ],
        'red_flag_screen' => $followup['red_flag_screen'] ?? ['triggered' => false, 'answers' => []],
        'context' => local_care_terms($country),
    ];
}

function age_range(int $age): string
{
    if ($age <= 12) {
        return '0-12';
    }
    if ($age <= 17) {
        return '13-17';
    }
    if ($age <= 24) {
        return '18-24';
    }
    if ($age <= 34) {
        return '25-34';
    }
    if ($age <= 44) {
        return '35-44';
    }
    if ($age <= 54) {
        return '45-54';
    }
    if ($age <= 64) {
        return '55-64';
    }
    if ($age <= 74) {
        return '65-74';
    }

    return '75+';
}

function body_area_is(array $data, array $needles): bool
{
    $bodyArea = strtolower((string)($data['issue']['body_area'] ?? ''));
    $precise = strtolower((string)($data['location']['precise_location'] ?? ''));

    foreach ($needles as $needle) {
        $needle = strtolower($needle);
        if ($bodyArea === $needle || str_contains($bodyArea, $needle) || str_contains($precise, $needle)) {
            return true;
        }
    }

    return false;
}

function doctor_summary(array $data, array $redFlagReasons = []): string
{
    $profile = $data['profile'] ?? [];
    $issue = $data['issue'] ?? [];
    $location = $data['location'] ?? [];
    $followup = $data['followup'] ?? [];
    $context = $data['context'] ?? [];

    $lines = [
        '60 Second Care summary',
        'Sex: ' . (($profile['sex'] ?? '') ?: 'Not provided'),
        'Age: ' . (($profile['age'] ?? '') !== '' ? (string)$profile['age'] : 'Not provided'),
        'Important conditions: ' . list_text($profile['conditions'] ?? []),
        'Allergies: ' . list_text($profile['allergies'] ?? ['None known']),
        'Pregnancy status: ' . (($profile['pregnancy_status'] ?? '') ?: 'Not applicable'),
        'Description: ' . (($issue['description'] ?? '') ?: 'Not provided'),
        'Body area: ' . (($issue['body_area'] ?? '') ?: 'Not selected'),
        'Pain quality: ' . list_text($issue['quality'] ?? []),
        'Onset: ' . (($issue['onset'] ?? '') ?: 'Not provided'),
        'Precise location: ' . (($location['precise_location'] ?? '') ?: 'Not provided'),
        'Duration: ' . (($followup['duration'] ?? '') ?: 'Not provided'),
        'Severity: ' . (($followup['severity_0_10'] ?? '') !== '' ? (string)$followup['severity_0_10'] . '/10' : (($followup['severity'] ?? '') ?: 'Not provided')),
        'Getting worse: ' . (($followup['worsening'] ?? '') ?: 'Not provided'),
        'Warning symptoms: ' . list_text($followup['warning_symptoms'] ?? []),
    ];

    if (!empty($followup['associated_symptoms'])) {
        $lines[] = 'Associated symptoms present: ' . list_text($followup['associated_symptoms']['present'] ?? []);
        $lines[] = 'Associated symptoms denied: ' . list_text($followup['associated_symptoms']['denied'] ?? []);
    }

    if (!empty($followup['conditional'])) {
        foreach ($followup['conditional'] as $key => $value) {
            $label = ucwords(str_replace(['conditional_', '_'], ['', ' '], (string)$key));
            $lines[] = $label . ': ' . ($value ?: 'Not provided');
        }
    }

    $lines[] = 'Medical history: ' . list_text($context['medical_history'] ?? []);
    $lines[] = 'Taking medication: ' . (($context['medication_status'] ?? '') ?: 'Not provided');
    if (($context['medication_status'] ?? '') === 'Yes' && !empty($context['medication_text'])) {
        $lines[] = 'Medication details: ' . $context['medication_text'];
        if (!empty($context['medication_dose'])) {
            $lines[] = 'Medication dose: ' . $context['medication_dose'];
        }
        if (!empty($context['medication_time_ago'])) {
            $lines[] = 'Medication taken: ' . $context['medication_time_ago'];
        }
        if (!empty($context['medication_effect'])) {
            $lines[] = 'Medication effect: ' . $context['medication_effect'];
        }
    }
    $lines[] = 'Allergies: ' . (($context['allergy_status'] ?? '') ?: 'Not provided');
    if (($context['allergy_status'] ?? '') === 'Yes' && !empty($context['allergy_text'])) {
        $lines[] = 'Allergy details: ' . $context['allergy_text'];
    }

    if ($redFlagReasons) {
        $lines[] = 'Red flags noted: ' . implode('; ', $redFlagReasons);
    }

    return implode("\n", $lines);
}

function page_start(string $title, int $step): void
{
    $percent = max(0, min(100, (int)round(($step / CARE_TOTAL_STEPS) * 100)));
    $error = flash('error');
    $notice = flash('notice');
    app_session_id();
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> | 60 Second Care</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <script>
    window.tailwind = window.tailwind || {};
    window.tailwind.config = { theme: { extend: { colors: { careBlue: '#3B82F6', carePale: '#E6F0FA' } } } };
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/app.js" defer></script>
</head>
<body class="min-h-screen bg-white text-slate-900 antialiased">
  <main class="mx-auto flex min-h-screen w-full max-w-3xl flex-col px-4 py-5 sm:px-6 sm:py-8">
    <div class="mb-5">
      <div class="mb-2 flex items-center justify-between gap-4 text-sm font-semibold text-slate-600">
        <a href="index.php" class="text-careBlue">60 Second Care</a>
        <span>Step <?= $step ?> of <?= CARE_TOTAL_STEPS ?></span>
      </div>
      <div class="h-2 overflow-hidden rounded-full bg-carePale" aria-label="Step <?= $step ?> of <?= CARE_TOTAL_STEPS ?>">
        <div class="h-full rounded-full bg-careBlue" style="width: <?= $percent ?>%"></div>
      </div>
    </div>
    <?php if ($error): ?>
      <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
        <?= h($error) ?>
      </div>
    <?php endif; ?>
    <?php if ($notice): ?>
      <div class="mb-4 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800" role="status">
        <?= h($notice) ?>
      </div>
    <?php endif; ?>
    <?php
}

function page_end(): void
{
    ?>
    <p class="mt-6 text-center text-xs leading-5 text-slate-500"><?= h(CARE_DISCLAIMER) ?></p>
  </main>
</body>
</html>
    <?php
}

function checked_attr($actual, $expected): string
{
    return (string)$actual === (string)$expected ? ' checked' : '';
}

function selected_attr($actual, $expected): string
{
    return (string)$actual === (string)$expected ? ' selected' : '';
}

function checkbox_attr(array $items, string $expected): string
{
    return in_array($expected, $items, true) ? ' checked' : '';
}

function urgency_meta(string $level): array
{
    $meta = [
        'self-care' => ['label' => 'Self-care', 'class' => 'border-blue-200 bg-blue-50 text-blue-800', 'icon' => 'Self-care'],
        'doctor-soon' => ['label' => 'Doctor soon', 'class' => 'border-amber-200 bg-amber-50 text-amber-900', 'icon' => 'Doctor soon'],
        'urgent' => ['label' => 'Urgent', 'class' => 'border-orange-200 bg-orange-50 text-orange-900', 'icon' => 'Urgent'],
        'emergency' => ['label' => 'Emergency', 'class' => 'border-red-200 bg-red-50 text-red-800', 'icon' => 'Emergency'],
    ];

    return $meta[$level] ?? $meta['doctor-soon'];
}

function confidence_label(string $confidence): string
{
    return [
        'low' => 'Low match',
        'medium' => 'Medium match',
        'high' => 'High match',
    ][$confidence] ?? 'Low match';
}
