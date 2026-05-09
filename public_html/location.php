<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/redflags.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duration'])) {
    if (!save_followup_from_post()) {
        redirect_to('location.php');
    }
    if (!save_context_from_post()) {
        redirect_to('location.php');
    }
    if ((string)($_POST['red_flag_emergency'] ?? '0') === '1') {
        $data = collect_health_check();
        $_SESSION['red_flag_reasons'] = ['Client red-flag follow-up answered yes'];
        $_SESSION['result'] = emergency_result($data, ['Client red-flag follow-up answered yes']);
        $_SESSION['result_model_id'] = 'red-flag-screen';
        redirect_to('result.php');
    }
    redirect_to('loading.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['description']) || isset($_POST['body_area']))) {
    if (!save_issue_from_post()) {
        redirect_to('describe.php');
    }
    $area = (string)($_SESSION['issue']['body_area'] ?? '');
    $_SESSION['location'] = [
        'precise_location' => $area !== '' ? $area : 'Not specified',
    ];
    redirect_to('location.php');
}

require_step('profile', 'index.php?form=1');
require_step('issue', 'describe.php');

if (empty($_SESSION['location'])) {
    $area = (string)($_SESSION['issue']['body_area'] ?? '');
    $_SESSION['location'] = [
        'precise_location' => $area !== '' ? $area : 'Not specified',
    ];
}

$issue = $_SESSION['issue'] ?? [];
$bodyArea = (string)($issue['body_area'] ?? 'Other');
$bodyLabel = $bodyArea !== '' ? $bodyArea : 'Other';
$followup = $_SESSION['followup'] ?? [];
$context = $_SESSION['context'] ?? [];
$history = $context['medical_history'] ?? ['None'];
$medicationText = (string)($context['medication_text'] ?? '');
$medicationDose = (string)($context['medication_dose'] ?? '');
$medicationTime = (string)($context['medication_time_ago'] ?? '');
$medicationEffect = (string)($context['medication_effect'] ?? '');
$medicationStatus = $medicationText !== '' ? 'Yes' : 'No';
$severityScore = (int)($followup['severity_0_10'] ?? 5);
$associatedOptions = associated_symptom_options($bodyArea);
$associatedSelected = (array)($followup['associated_symptoms']['present'] ?? ['None of these']);
if (!$associatedSelected) {
    $associatedSelected = ['None of these'];
}
$error = flash('error');

function selected_area_icon(string $area): string
{
    $area = strtolower($area);

    if (str_contains($area, 'head')) {
        return '<svg viewBox="0 0 32 32" focusable="false"><path d="M11 25v-4.5c-2.5-2.2-3.6-5-3.1-8.2C8.7 7.5 12.4 4 17.1 4c4.8 0 8.3 3.5 8.3 8.1 0 3.5-1.8 6.1-4.8 7.7V25" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M18 10.5c-2.8.5-4.2 2.2-4.2 5M18 10.5c2.2.3 3.4 1.6 3.7 3.7M18 10.5v-3M14.4 15.7c1.4 1 3.6 1 5 0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';
    }
    if (str_contains($area, 'neck')) {
        return '<svg viewBox="0 0 32 32" focusable="false"><path d="M19 6.5c3.1 1.2 4.8 3.7 4.8 7 0 3.5-1.7 5.8-5 6.8v4.4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M13 6.5c2.7.6 4.2 2.1 4.5 4.6.2 1.7-.3 3-1.5 4.1M11.2 24.7h8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }
    if (str_contains($area, 'chest')) {
        return '<svg viewBox="0 0 32 32" focusable="false"><path d="M12 25c.4-5 1.4-8.6 4-10.5 2.6 1.9 3.6 5.5 4 10.5M10 12.5c1.6-2.1 3.6-3.1 6-3.1s4.4 1 6 3.1M9 25c.1-3.8.7-7.2 2-10.1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }
    if (str_contains($area, 'stomach')) {
        return '<svg viewBox="0 0 32 32" focusable="false"><path d="M17 5c4 2.2 5.1 5.4 3.4 9.5 4.9 1.6 5.1 8.2.4 10.3-3.1 1.4-7.1.2-9.3-2.8-2.6.6-4.8-.8-5.5-3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 8c-2.7 2.6-3.1 5.6-1.2 9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }
    if (str_contains($area, 'arm')) {
        return '<svg viewBox="0 0 32 32" focusable="false"><path d="M15 6c-2 4.5-3.2 8.5-3.4 12 0 2.8 1.7 4.6 4.9 5.2l4.5.9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M20.5 17.5c-1.2 1.9-2.6 3.1-4.2 3.7M21.5 24.2c2.3.5 4.1-.3 5.5-2.3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }
    if (str_contains($area, 'leg')) {
        return '<svg viewBox="0 0 32 32" focusable="false"><path d="M15 5h5l-1 14 2.5 7H12l2.4-7L15 5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M13 26h9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }

    return '<svg viewBox="0 0 32 32" focusable="false"><circle cx="16" cy="15" r="10.5" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="13" r="1.3" fill="currentColor"/><circle cx="20" cy="13" r="1.3" fill="currentColor"/><path d="M11.5 21c2.4-2.4 6.6-2.4 9 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Follow-up Questions | 60 Second Care</title>
  <script>
    window.tailwind = window.tailwind || {};
    window.tailwind.config = { theme: { extend: { colors: { careBlue: '#3B82F6', carePale: '#E6F0FA' } } } };
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/style.css?v=desktop-4">
  <script src="assets/app.js?v=dropdown-fields-1" defer></script>
</head>
<body class="min-h-screen bg-white text-slate-950 antialiased">
  <main class="screen3-onboarding">
    <div class="screen3-shell">
      <header class="screen3-header">
        <a class="screen3-back-button" href="describe.php" aria-label="Back">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M15.5 4.75 8.25 12l7.25 7.25" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
        <div class="screen3-brand"><span>60</span> Second Care</div>
        <button class="screen3-bell" type="button" aria-label="Notifications">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M18 9.8c0-3.2-2.1-5.8-6-5.8s-6 2.6-6 5.8v3.3l-1.7 3.3h15.4L18 13.1V9.8Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
            <path d="M9.7 19.2c.5 1 1.3 1.5 2.3 1.5s1.8-.5 2.3-1.5M12 2.4V4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </header>

      <div class="screen3-progress">
        <p>Step 3 of 4</p>
        <div class="screen3-progress-track" aria-label="Step 3 of 4">
          <span style="width: 75%"></span>
        </div>
      </div>

      <h1 class="screen3-title">A few follow-up questions</h1>

      <?php if ($error): ?>
        <div class="screen3-alert" role="alert"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="location.php" class="screen3-form" data-screen3-form data-region="<?= h(value_key($bodyArea)) ?>" data-onset="<?= h(value_key((string)($issue['onset'] ?? ''))) ?>">
        <input type="hidden" name="worsening" value="Not sure">
        <input type="hidden" name="warning_symptoms[]" value="None">
        <input type="hidden" name="medication_status" value="<?= h($medicationStatus) ?>" data-medication-status>
        <input type="hidden" name="allergy_status" value="No">
        <input type="hidden" name="red_flag_triggered" value="0" data-red-flag-triggered>
        <input type="hidden" name="red_flag_emergency" value="0" data-red-flag-emergency>

        <section class="screen3-question-card">
          <h2><span>1.</span> How long has this been happening?</h2>
          <label class="screen3-duration-wrap">
            <span class="sr-only">How long has this been happening?</span>
            <input type="text" name="duration" required value="<?= h($followup['duration'] ?? '') ?>" placeholder="Example: since this morning, 2 days, 1 week">
          </label>
        </section>

        <section class="screen3-question-card">
          <h2><span>2.</span> How severe is the pain? <small>(0 = none, 10 = worst imaginable)</small></h2>
          <div class="screen3-severity-slider">
            <div class="screen3-slider-value" aria-live="polite"><strong data-severity-value><?= h((string)$severityScore) ?></strong>/10</div>
            <input type="range" name="severity_0_10" min="0" max="10" step="1" value="<?= h((string)$severityScore) ?>" data-severity-slider>
          </div>
        </section>

        <section class="screen3-question-card">
          <h2><span>3.</span> Medication already taken</h2>
          <label class="screen3-medication-label">
            <span>What medication did you take?</span>
            <span class="screen3-medication-input-wrap">
              <input type="text" name="medication_taken" value="<?= h($medicationText) ?>" placeholder="e.g. Aspirin, Paracetamol" data-medication-input>
              <button type="button" data-clear-medication aria-label="Clear medication">
                <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                  <path d="m6 6 8 8M14 6l-8 8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
              </button>
            </span>
          </label>

          <div class="screen3-medication-details<?= $medicationText === '' ? ' hidden' : '' ?>" data-medication-details>
            <label class="screen3-medication-label">
              <span>Dose (if known)</span>
              <span class="screen3-medication-input-wrap">
                <input type="text" name="medication_dose" value="<?= h($medicationDose) ?>" placeholder="e.g. 500mg, 2 tablets">
              </span>
            </label>

            <div class="screen3-medication-select-row">
              <label class="screen3-select-label">
                <span>How long ago?</span>
                <select name="medication_time_ago">
                  <option value="">Select time</option>
                  <?php foreach (['Less than 1 hour', '1–4 hours ago', '4–12 hours ago', 'Over 12 hours ago'] as $option): ?>
                    <option value="<?= h($option) ?>"<?= selected_attr($medicationTime, $option) ?>><?= h($option) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>

              <label class="screen3-select-label">
                <span>Did it help?</span>
                <select name="medication_effect">
                  <option value="">Select effect</option>
                  <?php foreach (['Yes', 'A bit', 'No', 'Too soon to tell'] as $option): ?>
                    <option value="<?= h($option) ?>"<?= selected_attr($medicationEffect, $option) ?>><?= h($option) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
          </div>
        </section>

        <section class="screen3-question-card">
          <h2><span>4.</span> Anything else happening?</h2>
          <div class="screen3-chip-grid screen3-chip-grid-symptoms" data-checkbox-group="associated_symptoms">
            <label>
              <input type="checkbox" name="associated_symptoms[]" value="None of these"<?= in_array('None of these', $associatedSelected, true) ? ' checked' : '' ?> data-none-checkbox>
              <span>None of these</span>
            </label>
            <?php foreach ($associatedOptions as $symptomKey => $symptomLabel): ?>
              <input type="hidden" name="associated_symptoms_all[]" value="<?= h($symptomKey) ?>">
              <label>
                <input type="checkbox" name="associated_symptoms[]" value="<?= h($symptomKey) ?>"<?= in_array($symptomKey, $associatedSelected, true) ? ' checked' : '' ?>>
                <span><?= h($symptomLabel) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </section>

        <button class="screen3-next-button" type="submit">Next</button>

        <div class="redflag-modal hidden" data-red-flag-modal aria-hidden="true">
          <div class="redflag-dialog" role="dialog" aria-modal="true" aria-labelledby="redFlagTitle">
            <h2 id="redFlagTitle">Quick safety check</h2>
            <p>These answers can change what guidance is safest. Please answer before continuing.</p>
            <div class="redflag-questions" data-red-flag-questions></div>
            <div class="redflag-actions">
              <button type="button" class="redflag-secondary" data-red-flag-close>Review answers</button>
              <button type="button" class="redflag-primary" data-red-flag-continue>Continue</button>
            </div>
            <a class="redflag-call hidden" href="tel:999" data-red-flag-call>Call 999 now</a>
          </div>
        </div>
      </form>

      <p class="screen3-disclaimer"><?= h(CARE_DISCLAIMER) ?></p>
    </div>
  </main>
</body>
</html>
