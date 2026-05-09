<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duration'])) {
    if (!save_followup_from_post()) {
        redirect_to('followup.php');
    }
    redirect_to('context.php');
}

require_step('profile', 'index.php');
require_step('issue', 'describe.php');
require_step('location', 'location.php');
require_step('followup', 'followup.php');

$context = $_SESSION['context'] ?? [];
$history = $context['medical_history'] ?? ['None'];
$medicationStatus = $context['medication_status'] ?? 'No';
$allergyStatus = $context['allergy_status'] ?? 'No';

page_start('Medical Context', 5);
?>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
  <h1 class="text-2xl font-bold text-slate-950">Medical context</h1>
  <p class="mt-2 text-base leading-7 text-slate-600">Add anything important that could change the advice.</p>

  <form method="post" action="api/health-check.php" class="mt-6 space-y-7">
    <fieldset>
      <legend class="text-base font-semibold text-slate-900">Medical history</legend>
      <div class="mt-3 grid gap-3 sm:grid-cols-2" data-checkbox-group="medical_history">
        <?php foreach (['None', 'Diabetes', 'Heart', 'Kidney', 'Asthma', 'High BP', 'Pregnancy', 'Immune', 'Other'] as $item): ?>
          <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-base text-slate-800 has-[:checked]:border-careBlue has-[:checked]:bg-blue-50">
            <input class="h-4 w-4 accent-blue-600" type="checkbox" name="medical_history[]" value="<?= h($item) ?>"<?= checkbox_attr($history, $item) ?><?= $item === 'None' ? ' data-none-checkbox' : '' ?>>
            <span><?= h($item) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset data-toggle-section>
      <legend class="text-base font-semibold text-slate-900">Taking medication?</legend>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <?php foreach (['No', 'Yes'] as $answer): ?>
          <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-base text-slate-800 has-[:checked]:border-careBlue has-[:checked]:bg-blue-50">
            <input class="h-4 w-4 accent-blue-600" type="radio" name="medication_status" value="<?= h($answer) ?>" required<?= checked_attr($medicationStatus, $answer) ?> data-toggle-radio>
            <span><?= h($answer) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <label class="mt-3 block <?= $medicationStatus === 'Yes' ? '' : 'hidden' ?>" data-toggle-detail>
        <span class="text-sm font-medium text-slate-700">Medication details, optional</span>
        <textarea class="mt-2 min-h-24 w-full rounded-2xl border border-slate-300 px-4 py-3 text-base outline-none focus:border-careBlue focus:ring-4 focus:ring-blue-100" name="medication_text" rows="3"><?= h($context['medication_text'] ?? '') ?></textarea>
      </label>
    </fieldset>

    <fieldset data-toggle-section>
      <legend class="text-base font-semibold text-slate-900">Allergies?</legend>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <?php foreach (['No', 'Yes'] as $answer): ?>
          <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-base text-slate-800 has-[:checked]:border-careBlue has-[:checked]:bg-blue-50">
            <input class="h-4 w-4 accent-blue-600" type="radio" name="allergy_status" value="<?= h($answer) ?>" required<?= checked_attr($allergyStatus, $answer) ?> data-toggle-radio>
            <span><?= h($answer) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <label class="mt-3 block <?= $allergyStatus === 'Yes' ? '' : 'hidden' ?>" data-toggle-detail>
        <span class="text-sm font-medium text-slate-700">Allergy details, optional</span>
        <textarea class="mt-2 min-h-24 w-full rounded-2xl border border-slate-300 px-4 py-3 text-base outline-none focus:border-careBlue focus:ring-4 focus:ring-blue-100" name="allergy_text" rows="3"><?= h($context['allergy_text'] ?? '') ?></textarea>
      </label>
    </fieldset>

    <button class="w-full rounded-2xl bg-careBlue px-5 py-4 text-lg font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100" type="submit">
      Get Result
    </button>
  </form>
</section>
<?php page_end(); ?>
