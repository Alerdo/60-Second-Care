<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';

if (empty($_SESSION['result']) || !is_array($_SESSION['result'])) {
    redirect_to('index.php');
}

$result = $_SESSION['result'];
$geminiError = $_SESSION['gemini_error'] ?? null;
$level = (string)($result['urgencyLevel'] ?? 'doctor-soon');
$meta = urgency_meta($level);
$summary = (string)($result['doctorSummary'] ?? '');
$possibleCauses = array_values($result['possibleCauses'] ?? []);
$whatToDo = array_values($result['whatToDoNow'] ?? []);
$explanation = (string)($result['mostLikelyExplanation'] ?? 'Possible explanation based on your symptoms.');
$confidence = (string)($result['confidence'] ?? 'low');
$matchLabel = confidence_label($confidence);
$ringClass = $level === 'emergency' ? 'is-emergency' : ($level === 'urgent' ? 'is-urgent' : 'is-ok');
$confColors = [
    'low'    => ['arc' => '90 302',  'stroke' => '#F59E0B', 'bg' => 'rgba(245,158,11,0.12)'],
    'medium' => ['arc' => '187 302', 'stroke' => '#3B82F6', 'bg' => 'rgba(59,130,246,0.12)'],
    'high'   => ['arc' => '263 302', 'stroke' => '#22B455', 'bg' => 'rgba(34,180,85,0.16)'],
];
$confColor = $confColors[$confidence] ?? $confColors['low'];
$regionalViralNote = trim((string)($result['regionalViralNote'] ?? ''));
$recommendedTests = array_values(array_filter(array_map('clean_text', $result['recommendedTests'] ?? [])));
$recommendedTest = $recommendedTests[0] ?? null;
$recommendedTestReason = trim((string)($result['recommendedTestReason'] ?? ''));
$noTestPhrases = ['no specific', 'no home test', 'no test', 'not recommended'];
$hasTests = $recommendedTest !== null
    && trim($recommendedTest) !== ''
    && !array_reduce($noTestPhrases, fn($carry, $p) => $carry || stripos($recommendedTest, $p) !== false, false);
$careTerms = local_care_terms((string)($_SESSION['profile']['location']['country'] ?? 'GB'));

$downloadLines = [
    '60 Second Care result',
    'Urgency level: ' . $meta['label'],
    'Possible explanation: ' . $explanation,
    'Confidence: ' . $matchLabel,
    '',
    'Possible causes:',
    '- ' . implode("\n- ", $possibleCauses),
    '',
    'Recommended test, if useful:',
    $hasTests ? '- ' . implode("\n- ", $recommendedTests) : '- No specific home test recommended from this check.',
    '',
    'What you can do now:',
    '- ' . implode("\n- ", $whatToDo),
    '',
    'When to seek help:',
    '- ' . implode("\n- ", $result['whenToSeekHelp'] ?? []),
    '',
    'Summary for doctor:',
    $summary,
    '',
    $result['disclaimer'] ?? CARE_DISCLAIMER,
];
$downloadText = implode("\n", $downloadLines);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Result | 60 Second Care</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <script>
    window.tailwind = window.tailwind || {};
    window.tailwind.config = { theme: { extend: { colors: { careBlue: '#3B82F6', carePale: '#E6F0FA' } } } };
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/style.css?v=desktop-4">
  <script src="assets/app.js?v=result-1" defer></script>
</head>
<body class="min-h-screen bg-white text-slate-950 antialiased">
  <main class="result-screen">
    <div class="result-shell">
      <header class="result-header">
        <a class="result-back-button" href="location.php" aria-label="Back">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M15.5 4.75 8.25 12l7.25 7.25" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
        <div class="result-brand"><span>60</span> Second Care</div>
      </header>

      <div class="result-progress">
        <p>Step 4 of 4</p>
        <div class="result-progress-track" aria-label="Step 4 of 4">
          <span style="width: 100%"></span>
        </div>
      </div>

      <section class="result-title-row">
        <h1>Analysis Result</h1>
        <span class="result-shield <?= h($ringClass) ?>" aria-label="<?= h($meta['label']) ?>"><?= icon_result_shield() ?></span>
      </section>

      <?php if ($geminiError): ?>
        <section class="result-debug-error" role="alert">
          <strong>Gemini API failed</strong>
          <span><?= h((string)$geminiError) ?></span>
        </section>
      <?php endif; ?>

      <section class="result-cause-card <?= h($ringClass) ?>">
        <div class="result-cause-main">
          <div>
            <p>Possible explanation</p>
            <h2><?= h($explanation) ?></h2>
            <span>Based on your symptoms</span>
          </div>
          <div class="result-ring">
            <svg viewBox="0 0 120 120" aria-hidden="true" focusable="false">
              <circle cx="60" cy="60" r="48"></circle>
              <circle cx="60" cy="60" r="48"></circle>
            </svg>
            <strong><?= h($matchLabel) ?></strong>
          </div>
        </div>
        <div class="result-causes-list">
          <details class="result-causes-details">
            <summary>Other possible causes</summary>
            <ul>
              <?php foreach (array_slice($possibleCauses, 0, 3) as $index => $cause): ?>
                <li>
                  <span><?= h($cause) ?></span>
                  <em><?= $index === 0 ? 'Possible' : 'Also possible' ?></em>
                </li>
              <?php endforeach; ?>
            </ul>
          </details>
        </div>
      </section>

      <?php if ($regionalViralNote !== ''): ?>
      <div class="result-viral-note">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v4.5M12 15.5v.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <p><?= h($regionalViralNote) ?></p>
      </div>
      <?php endif; ?>

      <?php if ($hasTests): ?>
      <section class="result-test-card">
        <div class="result-test-pill">Recommended Test</div>
        <h2 class="result-test-name"><?= h($recommendedTest) ?></h2>
        <?php if ($recommendedTestReason !== ''): ?>
        <p class="result-test-reason"><?= h($recommendedTestReason) ?></p>
        <?php endif; ?>
        <a class="result-test-amazon" href="https://www.amazon.co.uk/s?k=<?= rawurlencode($recommendedTest) ?>" target="_blank" rel="noopener noreferrer">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="19" r="1.5" fill="currentColor"/><circle cx="17" cy="19" r="1.5" fill="currentColor"/><path d="M1 2h2l2.4 10.4a2 2 0 0 0 2 1.6H17a2 2 0 0 0 2-1.6L20.5 7H5.2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Order this test Online
        </a>
        <div class="result-test-cta">
          <p>Once you have your test results, come back and run another check — real data means a much more accurate analysis.</p>
          <a href="index.php?restart=1">Run another check after your test &rarr;</a>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($level === 'emergency'): ?>
      <a class="result-emergency-call" href="tel:<?= h($careTerms['local_emergency_number']) ?>">
        Call <?= h($careTerms['local_emergency_number']) ?> now
      </a>
      <?php endif; ?>

  <section class="result-action-card">
  <h2>Suggested Next Steps at Home</h2>
  <div class="result-action-list">
    <?php foreach (array_slice($whatToDo, 0, 3) as $index => $item): ?>
      <div>
        <span class="result-action-icon icon-<?= $index + 1 ?>"><?= result_action_icon($index) ?></span>
        <p><?= h($item) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
      <a class="result-start-over" href="index.php?restart=1">Start Another Check</a>

      <p class="result-disclaimer"><?= h($result['disclaimer'] ?? CARE_DISCLAIMER) ?></p>

      <textarea class="hidden" id="resultDownloadText"><?= h($downloadText) ?></textarea>
      <!-- <button class="result-save-button" type="button" data-save-result>
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M12 3v12M7 10l5 5 5-5M5 20h14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Save Result
      </button> -->

      <details class="result-details">
        <summary>Doctor summary and more details</summary>
        <pre id="doctorSummary"><?= h($summary) ?></pre>
        <button type="button" data-copy-summary>Copy summary</button>
      </details>
    </div>
  </main>
</body>
</html>
<?php
function icon_result_shield(): string
{
    return '<svg viewBox="0 0 24 24"><path d="M12 3.7 18.5 6v5.2c0 4.3-2.5 7.2-6.5 9.1-4-1.9-6.5-4.8-6.5-9.1V6L12 3.7Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m8.8 11.8 2.1 2.1 4.5-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}
function result_action_icon(int $index): string
{
    $icons = [
        '<svg viewBox="0 0 24 24"><path d="M8 14c-1.5-1-2.3-2.4-2.3-4.1A5.9 5.9 0 0 1 12 4a5.9 5.9 0 0 1 6.3 5.9c0 1.7-.8 3.1-2.3 4.1v3.5H8V14Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 20h6M9 17.5h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        '<svg viewBox="0 0 24 24"><path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11Z" fill="none" stroke="currentColor" stroke-width="1.9"/><path d="M9 15.5c1.5 1.1 3.5 1.1 5 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        '<svg viewBox="0 0 24 24"><rect x="3.8" y="9" width="16.4" height="6" rx="3" fill="none" stroke="currentColor" stroke-width="1.9" transform="rotate(-45 12 12)"/><path d="M9 15 15 9" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>',
    ];

    return $icons[$index] ?? $icons[0];
}
