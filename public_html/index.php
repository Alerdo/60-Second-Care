<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';

if (isset($_GET['restart'])) {
    clear_current_check();
    set_flash('notice', 'Started a new health check.');
    redirect_to('index.php');
}

$profile = $_SESSION['profile'] ?? [];
$conditions = $profile['conditions'] ?? ['None'];
$allergies = $profile['allergies'] ?? ['None known'];
$selectedSex = $profile['sex'] ?? 'Male';
$selectedPregnancy = $profile['pregnancy_status'] ?? '';
$error = flash('error');
$notice = flash('notice');
$showProfile = $error !== null || isset($_GET['form']);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>60 Second Care</title>
  <script>
    window.tailwind = window.tailwind || {};
    window.tailwind.config = { theme: { extend: { colors: { careBlue: '#3B82F6', carePale: '#E6F0FA' } } } };
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/style.css?v=desktop-4">
  <script src="assets/app.js?v=dropdown-fields-1" defer></script>
</head>
<body class="min-h-screen bg-white text-slate-950 antialiased">
  <main class="min-h-screen">
    <section class="splash-screen <?= $showProfile ? 'hidden' : '' ?>" data-splash-screen>
      <div class="splash-waves" aria-hidden="true">
        <svg viewBox="0 0 1440 820" preserveAspectRatio="none" focusable="false">
          <defs>
            <linearGradient id="waveA" x1="0" x2="1" y1="0" y2="1">
              <stop offset="0" stop-color="#DCEBFD" stop-opacity="0.88"/>
              <stop offset="1" stop-color="#F6FAFF" stop-opacity="0.68"/>
            </linearGradient>
            <linearGradient id="waveB" x1="0" x2="1" y1="0" y2="1">
              <stop offset="0" stop-color="#CFE3FA" stop-opacity="0.68"/>
              <stop offset="0.58" stop-color="#EEF6FF" stop-opacity="0.92"/>
              <stop offset="1" stop-color="#FFFFFF" stop-opacity="0.72"/>
            </linearGradient>
            <linearGradient id="waveC" x1="1" x2="0" y1="0" y2="1">
              <stop offset="0" stop-color="#EAF4FF" stop-opacity="0.95"/>
              <stop offset="1" stop-color="#D7E8FB" stop-opacity="0.55"/>
            </linearGradient>
          </defs>
          <path fill="url(#waveA)" d="M-80 360C80 318 166 358 294 448C428 542 536 568 672 484C808 400 858 238 1034 196C1216 153 1348 206 1520 286V820H-80Z"/>
          <path fill="url(#waveB)" d="M-80 494C66 448 196 470 326 588C448 700 596 740 742 650C906 548 998 416 1156 376C1306 338 1404 388 1520 466V820H-80Z"/>
          <path fill="url(#waveC)" d="M-80 610C88 552 202 586 354 706C484 808 602 810 744 722C902 624 1016 502 1192 460C1338 426 1438 470 1520 548V820H-80Z"/>
        </svg>
      </div>

      <?php if ($notice): ?>
        <div class="splash-notice" role="status"><?= h($notice) ?></div>
      <?php endif; ?>

      <div class="splash-content">
        <svg class="clock-mark" viewBox="0 0 180 180" aria-hidden="true" focusable="false">
          <defs>
            <linearGradient id="clockStroke" x1="28" y1="24" x2="154" y2="158" gradientUnits="userSpaceOnUse">
              <stop offset="0" stop-color="#5F8BFF"/>
              <stop offset="0.48" stop-color="#3469E9"/>
              <stop offset="1" stop-color="#2557D6"/>
            </linearGradient>
            <filter id="clockGlow" x="-45%" y="-45%" width="190%" height="190%">
              <feGaussianBlur stdDeviation="8" result="blur"/>
              <feColorMatrix in="blur" type="matrix" values="0 0 0 0 0.23 0 0 0 0 0.46 0 0 0 0 0.95 0 0 0 0.24 0"/>
              <feBlend in="SourceGraphic"/>
            </filter>
          </defs>
          <circle cx="90" cy="90" r="62" fill="#FFFFFF" opacity="0.74" filter="url(#clockGlow)"/>
          <circle cx="90" cy="90" r="61" fill="none" stroke="url(#clockStroke)" stroke-width="18"/>
          <line x1="90" y1="90" x2="90" y2="48" stroke="url(#clockStroke)" stroke-width="14" stroke-linecap="round"/>
          <line x1="90" y1="90" x2="126" y2="120" stroke="url(#clockStroke)" stroke-width="14" stroke-linecap="round"/>
          <circle cx="45" cy="90" r="6" fill="#3469E9"/>
          <circle cx="135" cy="90" r="6" fill="#3469E9"/>
          <circle cx="90" cy="137" r="6" fill="#3469E9"/>
        </svg>

        <h1 class="splash-title"><span>60</span> Second Care</h1>
        <p class="splash-subtitle">Start your 60 seconds health check</p>

        <button class="splash-start-button" type="button" data-start-health-check>
          Start 60 Seconds Health Check
        </button>
      </div>

      <p class="splash-disclaimer"><?= h(CARE_DISCLAIMER) ?></p>
    </section>

    <section class="profile-onboarding <?= $showProfile ? '' : 'hidden' ?>" data-profile-screen>
      <div class="profile-shell">
        <header class="profile-header">
          <button class="profile-back-button" type="button" data-back-to-splash aria-label="Back">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M15.5 4.75 8.25 12l7.25 7.25" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <div class="profile-brand"><span>60</span> Second Care</div>
        </header>

        <div class="profile-progress">
          <p>Step 1 of 4</p>
          <div class="profile-progress-track" aria-label="Step 1 of 4">
            <span style="width: 25%"></span>
          </div>
        </div>

        <?php if ($error): ?>
          <div class="profile-alert" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <div class="profile-intro">
          <h1>Before we begin</h1>
          <p>A few details help us give safer guidance.</p>
        </div>

        <form method="post" action="describe.php" class="profile-form">
          <section class="profile-card">
            <fieldset class="profile-form-section">
              <legend>
                <span class="profile-section-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" focusable="false">
                    <circle cx="12" cy="7" r="3.3" fill="none" stroke="currentColor" stroke-width="1.9"/>
                    <path d="M5.5 20c.7-4.1 3-6.2 6.5-6.2s5.8 2.1 6.5 6.2" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                  </svg>
                </span>
                <span>Sex</span>
              </legend>
              <div class="profile-segmented">
                <?php foreach (['Male', 'Female'] as $sex): ?>
                  <label>
                    <input type="radio" name="sex" value="<?= h($sex) ?>" required<?= checked_attr($selectedSex, $sex) ?>>
                    <span><?= h($sex) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>

            <fieldset class="profile-form-section">
              <legend>
                <span class="profile-section-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" focusable="false">
                    <rect x="4" y="5.5" width="16" height="14.5" rx="2.2" fill="none" stroke="currentColor" stroke-width="1.9"/>
                    <path d="M8 3.8v4M16 3.8v4M4.5 10h15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                  </svg>
                </span>
                <span>Age</span>
              </legend>
              <input class="profile-age-input" type="number" name="age" min="0" max="120" required placeholder="Enter your age" value="<?= h($profile['age'] ?? '') ?>">
              <div class="profile-age-ranges" aria-label="Quick age ranges">
                <button type="button" data-age-fill="24">18-29</button>
                <button type="button" data-age-fill="37">30-44</button>
                <button type="button" data-age-fill="54">45-64</button>
                <button type="button" data-age-fill="70">65+</button>
              </div>
            </fieldset>

            <fieldset class="profile-form-section">
              <legend>
                <span class="profile-section-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" focusable="false">
                    <path d="M12 21s6-5.4 6-11a6 6 0 0 0-12 0c0 5.6 6 11 6 11Z" fill="none" stroke="currentColor" stroke-width="1.9"/>
                    <circle cx="12" cy="10" r="2.2" fill="none" stroke="currentColor" stroke-width="1.9"/>
                  </svg>
                </span>
                <span>Your Location</span>
              </legend>
              <input class="profile-age-input" type="text" name="user_location" maxlength="100" placeholder="e.g. London, UK" value="<?= h($profile['user_location'] ?? '') ?>">
              <p class="profile-field-hint">Location helps us check for viral infections currently active in the area.</p>
            </fieldset>

<fieldset class="profile-form-section">
  <legend>
    <span class="profile-section-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" focusable="false">
        <path d="M12 3.7 18.5 6v5.2c0 4.3-2.5 7.2-6.5 9.1-4-1.9-6.5-4.8-6.5-9.1V6L12 3.7Z" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>
        <path d="M12 8.5v6M9 11.5h6" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
      </svg>
    </span>
    <span>Important medical context</span>
  </legend>
  <div class="profile-select-wrapper">
    <select class="profile-select" name="conditions[]">
      <?php
        $conditionOptions = ['None', 'Diabetes', 'Asthma', 'Heart Condition', 'High Blood Pressure', 'Pregnant', 'Other'];
        $selectedCondition = !empty($conditions) ? $conditions[0] : 'None';
        foreach ($conditionOptions as $condition):
      ?>
        <option value="<?= h($condition) ?>"<?= $selectedCondition === $condition ? ' selected' : '' ?>><?= h($condition) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</fieldset>
<fieldset class="profile-form-section">
  <legend>
    <span class="profile-section-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" focusable="false">
        <path d="M8 7.5 12 3l4 4.5V19a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2V7.5Z" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>
        <path d="M9 11h6M9 15h6" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
      </svg>
    </span>
    <span>Allergies</span>
  </legend>
  <div class="profile-select-wrapper">
    <select class="profile-select" name="allergies[]" id="allergySelect">
      <?php
        $allergyOptions = ['None known', 'Penicillin', 'NSAIDs (ibuprofen, aspirin)', 'Aspirin', 'Paracetamol', 'Other'];
        $selectedAllergy = !empty($allergies) ? $allergies[0] : 'None known';
        foreach ($allergyOptions as $allergy):
      ?>
        <option value="<?= h($allergy) ?>"<?= $selectedAllergy === $allergy ? ' selected' : '' ?>><?= h($allergy) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <input id="allergyOtherField" class="profile-age-input mt-3 <?= $selectedAllergy === 'Other' ? '' : 'hidden' ?>" type="text" name="allergy_other" maxlength="100" placeholder="Type allergy" value="">
</fieldset>

            <fieldset class="profile-form-section profile-form-section-last hidden" data-pregnancy-field>
              <legend>
                <span class="profile-section-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" focusable="false">
                    <circle cx="12" cy="8" r="3.2" fill="none" stroke="currentColor" stroke-width="1.9"/>
                    <path d="M8 21c.3-4.2 1.7-7 4-7s3.7 2.8 4 7" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                  </svg>
                </span>
                <span>Are you pregnant?</span>
              </legend>
              <div class="profile-condition-grid profile-pregnancy-grid">
                <?php foreach (['No', 'Yes', 'Possibly', 'Prefer not to say'] as $pregnancy): ?>
                  <label>
                    <input type="radio" name="pregnancy_status" value="<?= h($pregnancy) ?>"<?= checked_attr($selectedPregnancy, $pregnancy) ?> data-pregnancy-option>
                    <span><?= h($pregnancy) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>
          </section>

          <button class="profile-continue-button" type="submit">Continue</button>
        </form>

        <p class="profile-disclaimer"><?= h(CARE_DISCLAIMER) ?></p>
      </div>
    </section>
  </main>
</body>
</html>
