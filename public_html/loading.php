<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';

require_step('profile', 'index.php?form=1');
require_step('issue', 'describe.php');
require_step('location', 'location.php');
require_step('followup', 'location.php');
require_step('context', 'location.php');

$profile = $_SESSION['profile'] ?? [];
$issue = $_SESSION['issue'] ?? [];
$location = $_SESSION['location'] ?? [];
$context = $_SESSION['context'] ?? [];
$age = isset($profile['age']) ? (int)$profile['age'] : 0;
$conditions = list_text($profile['conditions'] ?? []);
$history = list_text($context['medical_history'] ?? []);
$bodyArea = (string)($issue['body_area'] ?? 'Not selected');
$preciseLocation = (string)($location['precise_location'] ?? $bodyArea);
$description = (string)($issue['description'] ?? '');
$quality = list_text($issue['quality'] ?? []);
$userLocation = clean_text((string)($profile['user_location'] ?? ''), 60);
$allergyText = ($context['allergy_status'] ?? '') === 'Yes'
    ? (clean_text((string)($context['allergy_text'] ?? ''), 50) ?: 'yes')
    : '';

$medSubtitle = $history !== 'None' ? strtolower($history) : ($conditions !== 'None' ? strtolower($conditions) : 'no known conditions');
if ($allergyText !== '') {
    $medSubtitle .= ' · allergies: ' . strtolower($allergyText);
}

$symSubtitle = strtolower($bodyArea);
if ($quality !== '' && $quality !== 'None') {
    $symSubtitle .= ' · ' . strtolower($quality);
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Analysing | 60 Second Care</title>
  <script>
    window.tailwind = window.tailwind || {};
    window.tailwind.config = { theme: { extend: { colors: { careBlue: '#3B82F6', carePale: '#E6F0FA' } } } };
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/style.css?v=desktop-4">
  <style>
    .analysis-check-text { display: flex; flex-direction: column; gap: 1px; }
    .analysis-check-sub { font-size: 12px; font-weight: 400; opacity: 0.52; line-height: 1.3; }
  </style>
</head>
<body class="min-h-screen bg-white text-slate-950 antialiased">
  <main class="analysis-loading">
    <div class="analysis-shell">
      <header class="analysis-header">
        <a class="analysis-back-button" href="location.php" aria-label="Back">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M15.5 4.75 8.25 12l7.25 7.25" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
        <div class="analysis-brand"><span>60</span> Second Care</div>
      </header>

      <div class="analysis-progress">
        <p>Step 4 of 4</p>
        <div class="analysis-progress-track" aria-label="Step 4 of 4">
          <span style="width: 100%"></span>
        </div>
      </div>

      <section class="analysis-hero">
        <h1>Analysing your issue</h1>
        <p>We're reviewing your symptoms and context to prepare your result.</p>
      </section>

      <section class="analysis-check-list" aria-label="Analysis progress">
        <div class="analysis-check-row is-active" data-analysis-row>
          <span class="analysis-check-icon"><?= icon_user() ?></span>
          <span class="analysis-check-text">
            Reviewing profile
            <small class="analysis-check-sub"><?= h(age_range($age)) ?>, <?= h(strtolower((string)($profile['sex'] ?? 'unknown'))) ?></small>
          </span>
          <?= analysis_status() ?>
        </div>
        <div class="analysis-check-row" data-analysis-row>
          <span class="analysis-check-icon"><?= icon_shield_small() ?></span>
          <span class="analysis-check-text">
            Checking for urgent warning signs
            <small class="analysis-check-sub">scanning red-flag indicators</small>
          </span>
          <?= analysis_status() ?>
        </div>
        <div class="analysis-check-row" data-analysis-row>
          <span class="analysis-check-icon"><?= icon_heart() ?></span>
          <span class="analysis-check-text">
            Scanning medical history
            <small class="analysis-check-sub"><?= h($medSubtitle) ?></small>
          </span>
          <?= analysis_status() ?>
        </div>
        <div class="analysis-check-row" data-analysis-row>
          <span class="analysis-check-icon"><?= icon_sparkles() ?></span>
          <span class="analysis-check-text">
            Comparing symptoms with cautious care guidance
            <small class="analysis-check-sub"><?= h($symSubtitle) ?></small>
          </span>
          <?= analysis_status() ?>
        </div>
        <div class="analysis-check-row" data-analysis-row<?= $userLocation === '' ? ' data-instant' : '' ?>>
          <span class="analysis-check-icon"><?= icon_pin() ?></span>
          <span class="analysis-check-text">
            Researching local viral activity
            <small class="analysis-check-sub"><?= $userLocation !== '' ? h($userLocation) : 'no location provided' ?></small>
          </span>
          <?= analysis_status() ?>
        </div>
        <div class="analysis-check-row" data-analysis-row>
          <span class="analysis-check-icon"><?= icon_brain() ?></span>
          <span class="analysis-check-text">
            Refining results with multi-model AI analysis
            <small class="analysis-check-sub">cross-checking for accuracy and safety</small>
          </span>
          <?= analysis_status() ?>
        </div>
        <div class="analysis-check-row" data-analysis-row>
          <span class="analysis-check-icon"><?= icon_bars() ?></span>
          <span class="analysis-check-text">
            Almost ready — finalising your result
            <small class="analysis-check-sub">just a moment</small>
          </span>
          <?= analysis_status() ?>
        </div>
      </section>

      <div class="analysis-orb" aria-hidden="true">
        <span></span>
        <i class="analysis-orbit orbit-one"></i>
        <i class="analysis-orbit orbit-two"></i>
        <i class="analysis-spark spark-one"></i>
        <i class="analysis-spark spark-two"></i>
      </div>

      <p class="analysis-note"><span><?= icon_shield_small() ?></span>This usually takes a few seconds.</p>

      <form id="analysisSubmit" method="post" action="api/health-check.php"></form>
    </div>
  </main>
  <script>
    document.getElementById('analysisSubmit').submit();

    var analysisRows = Array.from(document.querySelectorAll('[data-analysis-row]'));
    function tickRow(index) {
      if (index >= analysisRows.length) return;
      var row = analysisRows[index];
      var delay = row.hasAttribute('data-instant') ? 80 : 700;
      window.setTimeout(function () {
        row.classList.add('is-complete');
        row.classList.remove('is-active');
        if (analysisRows[index + 1]) {
          analysisRows[index + 1].classList.add('is-active');
        }
        tickRow(index + 1);
      }, delay);
    }
    tickRow(0);
  </script>
</body>
</html>
<?php
function analysis_status(): string
{
    return '<i class="analysis-status"><span class="analysis-spinner" aria-hidden="true"></span><span class="analysis-tick" aria-hidden="true">' . icon_check() . '</span></i>';
}

function icon_user(): string
{
    return '<svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="3.2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5.5 20c.7-4.1 3-6.2 6.5-6.2s5.8 2.1 6.5 6.2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
}
function icon_heart(): string
{
    return '<svg viewBox="0 0 24 24"><path d="M4 12.5h3l2-4 3.2 7 2-3h5.8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 20s-7-4.3-7-10a4 4 0 0 1 7-2.7A4 4 0 0 1 19 10c0 5.7-7 10-7 10Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>';
}
function icon_pin(): string
{
    return '<svg viewBox="0 0 24 24"><path d="M12 21s6-5.4 6-11a6 6 0 0 0-12 0c0 5.6 6 11 6 11Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="10" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';
}
function icon_brain(): string
{
    return '<svg viewBox="0 0 24 24"><path d="M9 4.5A3.5 3.5 0 0 0 5.8 9a3.5 3.5 0 0 0 .7 6.7A3.8 3.8 0 0 0 12 19V6.2A3.4 3.4 0 0 0 9 4.5ZM15 4.5A3.5 3.5 0 0 1 18.2 9a3.5 3.5 0 0 1-.7 6.7A3.8 3.8 0 0 1 12 19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}
function icon_shield_small(): string
{
    return '<svg viewBox="0 0 24 24"><path d="M12 3.7 18.5 6v5.2c0 4.3-2.5 7.2-6.5 9.1-4-1.9-6.5-4.8-6.5-9.1V6L12 3.7Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 8.5v6M9 11.5h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
}
function icon_sparkles(): string
{
    return '<svg viewBox="0 0 24 24"><path d="M8 3.5 9.5 8 14 9.5 9.5 11 8 15.5 6.5 11 2 9.5 6.5 8 8 3.5ZM17 13l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>';
}
function icon_bars(): string
{
    return '<svg viewBox="0 0 24 24"><path d="M5 20V9h4v11M10 20V4h4v16M15 20v-7h4v7M4 20h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}
function icon_check(): string
{
    return '<svg viewBox="0 0 24 24"><path d="m7 12.4 3.2 3.2L17.5 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}
