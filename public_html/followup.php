<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['precise_location'])) {
    if (!save_location_from_post()) {
        redirect_to('location.php');
    }
    redirect_to('followup.php');
}

require_step('profile', 'index.php');
require_step('issue', 'describe.php');
require_step('location', 'location.php');

$data = collect_health_check();
$followup = $_SESSION['followup'] ?? [];
$warnings = $followup['warning_symptoms'] ?? ['None'];
$bodyArea = (string)($data['issue']['body_area'] ?? '');
$precise = (string)($data['location']['precise_location'] ?? '');
$bodyBlob = strtolower($bodyArea . ' ' . $precise);
$isMouth = str_contains($bodyBlob, 'mouth') || str_contains($bodyBlob, 'teeth') || str_contains($bodyBlob, 'gums') || str_contains($bodyBlob, 'tongue') || str_contains($bodyBlob, 'throat') || str_contains($bodyBlob, 'jaw');
$isLowerBack = str_contains($bodyBlob, 'lower back') || str_contains($bodyBlob, 'back');
$isChest = str_contains($bodyBlob, 'chest');

$conditionalGroups = [];
if ($isMouth) {
    $conditionalGroups[] = ['conditional_mouth_chewing', 'Hurts when chewing?'];
    $conditionalGroups[] = ['conditional_mouth_swelling', 'Swelling?'];
    $conditionalGroups[] = ['conditional_mouth_bleeding_gums', 'Bleeding gums?'];
    $conditionalGroups[] = ['conditional_mouth_hot_cold', 'Tooth sensitivity to hot or cold?'];
    $conditionalGroups[] = ['conditional_mouth_pus', 'Bad taste or pus?'];
    $conditionalGroups[] = ['conditional_mouth_swallow_breathe', 'Trouble swallowing or breathing?'];
}
if ($isLowerBack) {
    $conditionalGroups[] = ['conditional_back_injury', 'Started after lifting or injury?'];
    $conditionalGroups[] = ['conditional_back_leg_pain', 'Pain down the leg?'];
    $conditionalGroups[] = ['conditional_back_numb_weak', 'Numbness or weakness?'];
    $conditionalGroups[] = ['conditional_back_urinating', 'Trouble urinating?'];
    $conditionalGroups[] = ['conditional_back_bladder_bowel', 'Loss of bladder or bowel control?'];
    $conditionalGroups[] = ['conditional_back_fever', 'Fever?'];
}
if ($isChest) {
    $conditionalGroups[] = ['conditional_chest_pressure', 'Crushing or pressure?'];
    $conditionalGroups[] = ['conditional_chest_radiates', 'Spreads to arm, jaw, or back?'];
    $conditionalGroups[] = ['conditional_chest_breath', 'Short of breath?'];
    $conditionalGroups[] = ['conditional_chest_sweat_dizzy_faint', 'Sweating, dizzy, or faint?'];
    $conditionalGroups[] = ['conditional_chest_sudden', 'Sudden onset?'];
}

page_start('Follow-up Questions', 4);
?>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" data-followup-page data-is-chest="<?= $isChest ? 'true' : 'false' ?>">
  <h1 class="text-2xl font-bold text-slate-950">A few follow-up questions</h1>
  <p class="mt-2 text-base leading-7 text-slate-600">These help sort mild symptoms from symptoms that need faster care.</p>

  <div class="mt-5 hidden rounded-2xl border border-red-300 bg-red-50 px-4 py-4 text-base font-semibold leading-7 text-red-800" data-chest-alert role="alert">
    Chest pain with breathing trouble, sweating, fainting or pain spreading to the arm, jaw or back can be serious. Seek emergency medical help now.
  </div>

  <form method="post" action="context.php" class="mt-6 space-y-7">
    <fieldset>
      <legend class="text-base font-semibold text-slate-900">How long?</legend>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <?php foreach (['Less than 24 hours', '1-3 days', 'More than 3 days', 'More than 1 week', 'More than 1 month'] as $duration): ?>
          <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-base text-slate-800 has-[:checked]:border-careBlue has-[:checked]:bg-blue-50">
            <input class="h-4 w-4 accent-blue-600" type="radio" name="duration" value="<?= h($duration) ?>" required<?= checked_attr($followup['duration'] ?? '', $duration) ?>>
            <span><?= h($duration) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset>
      <legend class="text-base font-semibold text-slate-900">Severity</legend>
      <div class="mt-3 grid gap-3 sm:grid-cols-3">
        <?php foreach (['Mild', 'Moderate', 'Severe'] as $severity): ?>
          <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-base text-slate-800 has-[:checked]:border-careBlue has-[:checked]:bg-blue-50">
            <input class="h-4 w-4 accent-blue-600" type="radio" name="severity" value="<?= h($severity) ?>" required<?= checked_attr($followup['severity'] ?? '', $severity) ?>>
            <span><?= h($severity) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset>
      <legend class="text-base font-semibold text-slate-900">Getting worse?</legend>
      <div class="mt-3 grid gap-3 sm:grid-cols-3">
        <?php foreach (['Yes', 'No', 'Not sure'] as $worsening): ?>
          <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-base text-slate-800 has-[:checked]:border-careBlue has-[:checked]:bg-blue-50">
            <input class="h-4 w-4 accent-blue-600" type="radio" name="worsening" value="<?= h($worsening) ?>" required<?= checked_attr($followup['worsening'] ?? '', $worsening) ?>>
            <span><?= h($worsening) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset>
      <legend class="text-base font-semibold text-slate-900">Warning symptoms</legend>
      <div class="mt-3 grid gap-3 sm:grid-cols-2" data-checkbox-group="warning_symptoms">
        <?php foreach (['Fever', 'Chest pain', 'Trouble breathing', 'Numbness', 'Vomiting', 'Swelling', 'Fainting', 'Severe sudden pain', 'Confusion', 'Trouble speaking', 'Vision loss', 'Severe bleeding', 'None'] as $warning): ?>
          <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-base text-slate-800 has-[:checked]:border-careBlue has-[:checked]:bg-blue-50">
            <input class="h-4 w-4 accent-blue-600" type="checkbox" name="warning_symptoms[]" value="<?= h($warning) ?>"<?= checkbox_attr($warnings, $warning) ?><?= $warning === 'None' ? ' data-none-checkbox' : '' ?>>
            <span><?= h($warning) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <?php if ($conditionalGroups): ?>
      <fieldset>
        <legend class="text-base font-semibold text-slate-900">Related questions</legend>
        <div class="mt-3 space-y-4">
          <?php foreach ($conditionalGroups as [$key, $label]): ?>
            <div class="rounded-2xl border border-slate-200 px-4 py-4">
              <p class="font-medium text-slate-900"><?= h($label) ?></p>
              <div class="mt-3 grid gap-2 sm:grid-cols-3">
                <?php foreach (['Yes', 'No', 'Not sure'] as $answer): ?>
                  <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-800 has-[:checked]:border-careBlue has-[:checked]:bg-blue-50">
                    <input class="h-4 w-4 accent-blue-600" type="radio" name="<?= h($key) ?>" value="<?= h($answer) ?>"<?= checked_attr($followup['conditional'][$key] ?? '', $answer) ?>>
                    <span><?= h($answer) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endif; ?>

    <button class="w-full rounded-2xl bg-careBlue px-5 py-4 text-lg font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100" type="submit">
      Continue
    </button>
  </form>
</section>
<?php page_end(); ?>
