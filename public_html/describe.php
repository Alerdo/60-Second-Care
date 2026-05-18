<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sex'])) {
    if (!save_profile_from_post()) {
        redirect_to('index.php?form=1');
    }
    redirect_to('describe.php');
}

require_step('profile', 'index.php?form=1');

$issue = $_SESSION['issue'] ?? [];
$selectedArea = $issue['body_area'] ?? '';
$description = $issue['description'] ?? '';
$selectedQuality = $issue['quality'] ?? [];
$selectedOnset = $issue['onset'] ?? '';

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Describe Issue | 60 Second Care</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <script>
    window.tailwind = window.tailwind || {};
    window.tailwind.config = { theme: { extend: { colors: { careBlue: '#3B82F6', carePale: '#E6F0FA' } } } };
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/style.css?v=desktop-4">
  <script src="assets/app.js?v=structured-flow-1" defer></script>
  <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js"></script>
</head>
<body class="min-h-screen bg-white text-slate-950 antialiased">
  <main class="describe-onboarding">
    <div class="describe-shell">
      <header class="describe-header">
        <a class="describe-back-button" href="index.php?form=1" aria-label="Back">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M15.5 4.75 8.25 12l7.25 7.25" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
        <div class="describe-brand"><span>60</span> Second Care</div>
      </header>

      <div class="describe-progress">
        <p>Step 2 of 4</p>
        <div class="describe-progress-track" aria-label="Step 2 of 4">
          <span style="width: 50%"></span>
        </div>
      </div>

      <form method="post" action="location.php" class="describe-form" data-describe-form>
        <section class="describe-prompt-row">
          <div class="describe-bot-icon" aria-hidden="true">
            <svg viewBox="0 0 32 32" focusable="false">
              <rect x="6" y="10" width="20" height="16" rx="7" fill="none" stroke="currentColor" stroke-width="2"/>
              <path d="M16 6v4M10 17h-2.5M24.5 17H22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <circle cx="13" cy="18" r="1.6" fill="currentColor"/>
              <circle cx="19" cy="18" r="1.6" fill="currentColor"/>
              <path d="M12.5 22c2.1 1.5 4.9 1.5 7 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="describe-bubble">Describe the pain or issue in 2-4 sentences.</div>
        </section>

        <label class="describe-textbox-wrap">
          <span class="sr-only">Describe the issue</span>
          <textarea class="describe-textbox" name="description" rows="3" placeholder="Describe what you feel, where it is, when it started, and what makes it better or worse."><?= h($description) ?></textarea>
          <svg class="describe-pencil" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="m4 16.8-.7 3.9 3.9-.7L19.4 7.8a2.2 2.2 0 0 0 0-3.1l-.1-.1a2.2 2.2 0 0 0-3.1 0L4 16.8Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
            <path d="m14.8 6 3.2 3.2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </label>

        <div class="describe-helper-line">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M5 3.5 6.4 8 11 9.5 6.4 11 5 15.5 3.6 11-1 9.5 3.6 8 5 3.5ZM17 5l.9 2.9L21 9l-3.1 1.1L17 13l-.9-2.9L13 9l3.1-1.1L17 5Z" fill="currentColor"/>
          </svg>
          <p>Then select the area on the body if applicable. <span class="describe-mvp-note">&#9432; MVP: model is a placeholder — a future version will be sex-matched and let you pinpoint the exact body part.</span></p>
        </div>

        <input type="hidden" name="body_area" id="bodyAreaInput" value="<?= h($selectedArea) ?>">

        <div class="describe-model-controls">
          <div class="describe-view-toggle" aria-label="Body view">
            <button type="button" data-view-toggle="front" data-active="true">Front</button>
            <button type="button" data-view-toggle="back" data-active="false">Back</button>
          </div>
          <div class="describe-zoom-hint">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M9 5v8M9 13l-3-3M9 13l3-3M15 19v-8M15 11l-3 3M15 11l3 3" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Drag to rotate &middot; Pinch to zoom</span>
          </div>
        </div>

        <section class="describe-body-picker" data-body-selector data-initial-area="<?= h($selectedArea) ?>">
          <div class="describe-area-list" aria-label="Body areas">
            <?php
            $areas = [
                ['Head', 'head', '#3B6DEB'],
                ['Neck', 'neck', '#9A5BE8'],
                ['Chest', 'chest', '#F23D8E'],
                ['Stomach', 'stomach', '#FF8A18'],
                ['Arms', 'arms', '#22B873'],
                ['Legs', 'legs', '#2C6BFF'],
                ['Other', 'other', '#94A3B8'],
            ];
            foreach ($areas as [$label, $icon, $color]):
            ?>
              <button class="describe-area-card" type="button" data-body-area="<?= h($label === 'Arms' ? 'Arm' : ($label === 'Legs' ? 'Leg' : $label)) ?>" style="--area-color: <?= h($color) ?>" aria-label="<?= h($label) ?>">
                <span class="describe-area-icon" aria-hidden="true">
                  <?php if ($icon === 'head'): ?>
                    <svg viewBox="0 0 32 32"><path d="M11 25v-4.5c-2.5-2.2-3.6-5-3.1-8.2C8.7 7.5 12.4 4 17.1 4c4.8 0 8.3 3.5 8.3 8.1 0 3.5-1.8 6.1-4.8 7.7V25" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M18 10.5c-2.8.5-4.2 2.2-4.2 5M18 10.5c2.2.3 3.4 1.6 3.7 3.7M18 10.5v-3M14.4 15.7c1.4 1 3.6 1 5 0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                  <?php elseif ($icon === 'neck'): ?>
                    <svg viewBox="0 0 32 32"><path d="M19 6.5c3.1 1.2 4.8 3.7 4.8 7 0 3.5-1.7 5.8-5 6.8v4.4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M13 6.5c2.7.6 4.2 2.1 4.5 4.6.2 1.7-.3 3-1.5 4.1M11.2 24.7h8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                  <?php elseif ($icon === 'chest'): ?>
                    <svg viewBox="0 0 32 32"><path d="M12 25c.4-5 1.4-8.6 4-10.5 2.6 1.9 3.6 5.5 4 10.5M10 12.5c1.6-2.1 3.6-3.1 6-3.1s4.4 1 6 3.1M9 25c.1-3.8.7-7.2 2-10.1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                  <?php elseif ($icon === 'stomach'): ?>
                    <svg viewBox="0 0 32 32"><path d="M17 5c4 2.2 5.1 5.4 3.4 9.5 4.9 1.6 5.1 8.2.4 10.3-3.1 1.4-7.1.2-9.3-2.8-2.6.6-4.8-.8-5.5-3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 8c-2.7 2.6-3.1 5.6-1.2 9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                  <?php elseif ($icon === 'arms'): ?>
                    <svg viewBox="0 0 32 32"><path d="M15 6c-2 4.5-3.2 8.5-3.4 12 0 2.8 1.7 4.6 4.9 5.2l4.5.9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M20.5 17.5c-1.2 1.9-2.6 3.1-4.2 3.7M21.5 24.2c2.3.5 4.1-.3 5.5-2.3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                  <?php elseif ($icon === 'legs'): ?>
                    <svg viewBox="0 0 32 32"><path d="M15 5h5l-1 14 2.5 7H12l2.4-7L15 5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M13 26h9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                  <?php else: ?>
                    <svg viewBox="0 0 32 32"><circle cx="16" cy="15" r="10.5" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="13" r="1.3" fill="currentColor"/><circle cx="20" cy="13" r="1.3" fill="currentColor"/><path d="M11.5 21c2.4-2.4 6.6-2.4 9 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                  <?php endif; ?>
                </span>
                <span><?= h($label) ?></span>
              </button>
            <?php endforeach; ?>
          </div>

          <div class="describe-body-stage">
            <div class="describe-body-image-wrap" style="border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.07), 0 10px 40px -4px rgba(0,0,0,0.13), inset 0 0 0 1px rgba(0,0,0,0.06);">
              <div id="viewerSizeLabel" style="position:absolute;top:6px;left:6px;z-index:99;background:rgba(0,0,0,0.75);color:#fff;font-size:12px;font-weight:700;padding:4px 8px;border-radius:6px;pointer-events:none;font-family:monospace;"></div>
              <div id="viewerCamLabel" style="position:absolute;bottom:6px;left:6px;z-index:99;background:rgba(0,0,0,0.75);color:#fff;font-size:11px;font-weight:600;padding:5px 8px;border-radius:6px;pointer-events:none;font-family:monospace;line-height:1.6;white-space:pre;"></div>
              <div class="describe-model-coach" aria-hidden="true">
                <span>Click</span>
                <span>Zoom</span>
                <span>Select</span>
              </div>

           <model-viewer
  id="bodyViewer"
  class="describe-body-viewer"
  src="assets/3d/male_body.glb"
  camera-controls
  touch-action="pan-y"
  camera-orbit="0.010128459770996752rad 1.308996938995747rad 1098.211782678m"
  camera-target="-0.134m 161.329m 11.179m"
  field-of-view="16deg"
  min-camera-orbit="auto 75deg auto"
  max-camera-orbit="auto 75deg auto"
  interpolation-decay="120"
  exposure="0.96"
  shadow-intensity="0.9"
  environment-image="neutral"
  alt="3D male body model">
                <button slot="hotspot-head"        class="body-hotspot" data-body-area="Head"       data-position="0 1.65 0.05"   data-normal="0 0 1">Head</button>
                <button slot="hotspot-neck"        class="body-hotspot" data-body-area="Neck"       data-position="0 1.5 0.07"    data-normal="0 0 1">Neck</button>
                <button slot="hotspot-chest"       class="body-hotspot" data-body-area="Chest"      data-position="0 1.3 0.12"    data-normal="0 0 1">Chest</button>
                <button slot="hotspot-stomach"     class="body-hotspot" data-body-area="Stomach"    data-position="0 1.05 0.12"   data-normal="0 0 1">Stomach</button>
                <button slot="hotspot-arm-left"    class="body-hotspot" data-body-area="Arm"        data-position="-0.35 1.2 0"   data-normal="-1 0 0">Arm</button>
                <button slot="hotspot-arm-right"   class="body-hotspot" data-body-area="Arm"        data-position="0.35 1.2 0"    data-normal="1 0 0">Arm</button>
                <button slot="hotspot-leg"         class="body-hotspot" data-body-area="Leg"        data-position="0 0.55 0.08"   data-normal="0 0 1">Leg</button>
                <button slot="hotspot-upper-back"  class="body-hotspot" data-body-area="Upper back" data-position="0 1.35 -0.12"  data-normal="0 0 -1">Upper back</button>
                <button slot="hotspot-lower-back"  class="body-hotspot" data-body-area="Lower back" data-position="0 1.0 -0.12"   data-normal="0 0 -1">Lower back</button>
              </model-viewer>

            </div>
          </div>
        </section>

        <p class="sr-only" id="selectedAreaText">Selected area: <?= $selectedArea ? h($selectedArea) : 'None' ?></p>

        <section class="describe-extra-card">
          <h2>How does it feel?</h2>
          <p>Select all that apply</p>
          <div class="describe-chip-grid" data-checkbox-group="pain_quality">
            <?php foreach (['Throbbing', 'Sharp', 'Dull', 'Burning', 'Pressure / tight', 'Cramping', 'Stabbing', 'Aching', 'Tingling', 'Other'] as $quality): ?>
              <label>
                <input type="checkbox" name="pain_quality[]" value="<?= h($quality) ?>"<?= checkbox_attr($selectedQuality, $quality) ?><?= $quality === 'Other' ? ' data-reveals-other="painQualityOtherField"' : '' ?>>
                <span><?= h($quality) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <input id="painQualityOtherField" class="describe-extra-input hidden" type="text" name="pain_quality_other" placeholder="Describe the feeling" maxlength="100">
        </section>

        <section class="describe-extra-card">
          <h2>How did it start?</h2>
          <div class="describe-chip-grid describe-onset-grid">
            <?php foreach (['Suddenly (within minutes)', 'Gradually (over hours)', 'Comes and goes', 'Not sure'] as $onset): ?>
              <label>
                <input type="radio" name="onset" value="<?= h($onset) ?>"<?= checked_attr($selectedOnset, $onset) ?>>
                <span><?= h($onset) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </section>

        <p class="describe-error hidden" data-describe-error>
          Please add a description or body area, then select pain quality and how it started.
        </p>

        <button class="describe-next-button" type="submit">Next</button>
      </form>

      <p class="describe-disclaimer"><?= h(CARE_DISCLAIMER) ?></p>
    </div>
  </main>
</body>
</html>
