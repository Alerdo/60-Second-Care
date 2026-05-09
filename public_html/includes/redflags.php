<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

function normalized_blob(array $data): string
{
    $parts = [
        $data['issue']['description'] ?? '',
        $data['issue']['body_area'] ?? '',
        $data['location']['precise_location'] ?? '',
        list_text($data['followup']['warning_symptoms'] ?? []),
        list_text($data['profile']['conditions'] ?? []),
        list_text($data['context']['medical_history'] ?? []),
    ];

    return strtolower(implode(' ', $parts));
}

function conditional_yes(array $data, string $key): bool
{
    $value = strtolower((string)($data['followup']['conditional'][$key] ?? ''));

    return $value === 'yes';
}

function warning_has(array $data, string $warning): bool
{
    return has_value($data['followup']['warning_symptoms'] ?? [], $warning);
}

function associated_has(array $data, string $symptom): bool
{
    return in_array($symptom, (array)($data['followup']['associated_symptoms']['present'] ?? []), true);
}

function condition_has(array $data, array $needles): bool
{
    $conditions = array_merge(
        $data['profile']['conditions'] ?? [],
        $data['context']['medical_history'] ?? []
    );

    foreach ($conditions as $condition) {
        foreach ($needles as $needle) {
            if (strcasecmp((string)$condition, $needle) === 0) {
                return true;
            }
        }
    }

    return false;
}

function check_red_flags(array $data): array
{
    $reasons = [];
    $blob = normalized_blob($data);
    $severity = strtolower((string)($data['followup']['severity'] ?? ''));
    $severityScore = (int)($data['followup']['severity_0_10'] ?? 0);
    $worsening = strtolower((string)($data['followup']['worsening'] ?? ''));
    $onset = value_key((string)($data['issue']['onset'] ?? ''));

    $hasChestPain = warning_has($data, 'Chest pain')
        || body_area_is($data, ['chest'])
        || str_contains($blob, 'chest pain');
    $hasBreathingTrouble = warning_has($data, 'Trouble breathing')
        || associated_has($data, 'shortness_of_breath')
        || conditional_yes($data, 'conditional_chest_breath')
        || str_contains($blob, 'short of breath')
        || str_contains($blob, 'trouble breathing');
    $hasSweatingOrFainting = warning_has($data, 'Fainting')
        || associated_has($data, 'sweating')
        || conditional_yes($data, 'conditional_chest_sweat_dizzy_faint')
        || str_contains($blob, 'sweating')
        || str_contains($blob, 'faint');
    $hasRadiatingPain = conditional_yes($data, 'conditional_chest_radiates')
        || associated_has($data, 'pain_radiating_to_arm_jaw')
        || str_contains($blob, 'spreads to arm')
        || str_contains($blob, 'spreading to arm')
        || str_contains($blob, 'spreads to jaw')
        || str_contains($blob, 'spreading to jaw')
        || str_contains($blob, 'spreads to back')
        || str_contains($blob, 'spreading to back');

    if ($hasChestPain && ($hasBreathingTrouble || $hasSweatingOrFainting || $hasRadiatingPain)) {
        $reasons[] = 'Chest pain with breathing trouble, fainting, sweating, or pain spreading to the arm, jaw, or back';
    }

    if (warning_has($data, 'Severe sudden pain') && body_area_is($data, ['head'])) {
        $reasons[] = 'Severe sudden headache';
    }

    if (body_area_is($data, ['head']) && $severityScore >= 8 && str_starts_with($onset, 'suddenly')) {
        $reasons[] = 'Severe sudden headache';
    }

    if (warning_has($data, 'Numbness') || associated_has($data, 'weakness_one_side') || str_contains($blob, 'sudden weakness') || str_contains($blob, 'sudden numbness')) {
        $reasons[] = 'Sudden weakness or numbness';
    }

    if (warning_has($data, 'Trouble speaking') || associated_has($data, 'difficulty_speaking')) {
        $reasons[] = 'Trouble speaking';
    }

    if (warning_has($data, 'Confusion') || associated_has($data, 'confusion')) {
        $reasons[] = 'Confusion';
    }

    if (warning_has($data, 'Vision loss')) {
        $reasons[] = 'Vision loss';
    }

    if (warning_has($data, 'Fainting')) {
        $reasons[] = 'Fainting';
    }

    $hasSwelling = warning_has($data, 'Swelling') || conditional_yes($data, 'conditional_mouth_swelling');
    $hasMouthFaceArea = body_area_is($data, ['mouth', 'face', 'jaw', 'throat']);
    $hasSwallowingTrouble = conditional_yes($data, 'conditional_mouth_swallow_breathe')
        || str_contains($blob, 'trouble swallowing')
        || str_contains($blob, 'difficulty swallowing');

    if ((str_contains($blob, 'allergic reaction') || str_contains($blob, 'anaphylaxis') || str_contains($blob, 'throat swelling'))
        && ($hasBreathingTrouble || $hasSwelling)) {
        $reasons[] = 'Possible severe allergic reaction';
    }

    if (warning_has($data, 'Severe bleeding')) {
        $reasons[] = 'Severe bleeding';
    }

    if ($hasMouthFaceArea && $hasSwelling && ($hasBreathingTrouble || $hasSwallowingTrouble)) {
        $reasons[] = 'Mouth or face swelling with breathing or swallowing trouble';
    }

    if (body_area_is($data, ['back', 'lower back']) && conditional_yes($data, 'conditional_back_bladder_bowel')) {
        $reasons[] = 'Back pain with loss of bladder or bowel control';
    }

    $pregnant = condition_has($data, ['Pregnant', 'Pregnancy'])
        || in_array((string)($data['profile']['pregnancy_status'] ?? ''), ['Yes', 'Possibly'], true);
    $abdominalPain = body_area_is($data, ['stomach', 'abdomen', 'abdominal'])
        || str_contains($blob, 'abdominal pain')
        || str_contains($blob, 'stomach pain');

    if ($pregnant && $abdominalPain && $severity === 'severe') {
        $reasons[] = 'Pregnancy with severe abdominal pain';
    }

    $bloodInVomitOrStool = (str_contains($blob, 'blood') && (str_contains($blob, 'vomit') || str_contains($blob, 'stool') || str_contains($blob, 'poo')));
    if ($abdominalPain && $severity === 'severe' && $bloodInVomitOrStool) {
        $reasons[] = 'Severe abdominal pain with blood in vomit or stool';
    }

    if (warning_has($data, 'Severe sudden pain') && $worsening === 'yes') {
        $reasons[] = 'Severe sudden pain that is getting worse';
    }

    return [
        'matched' => $reasons !== [],
        'reasons' => array_values(array_unique($reasons)),
    ];
}

function emergency_result(array $data, array $reasons): array
{
    $message = 'Based on your answers, this could be serious. Please seek emergency medical help now. If you are in immediate danger, call 999 (UK) or your local emergency number.';

    return [
        'urgencyLevel' => 'emergency',
        'mostLikelyExplanation' => $message,
        'confidence' => 'high',
        'possibleCauses' => [
            'A red-flag symptom that needs urgent medical assessment',
            'A serious condition that cannot be safely checked online',
        ],
        'recommendedTests' => [],
        'whatToDoNow' => [
            'Seek emergency medical help now.',
            'Call 999 in the UK, or your local emergency number, if there is immediate danger.',
            'Do not drive yourself if you feel faint, short of breath, confused, or severely unwell.',
            'Keep this summary ready to show a clinician.',
        ],
        'whenToSeekHelp' => [
            'Seek emergency help now because your answers include: ' . implode('; ', $reasons),
        ],
        'doctorQuestions' => [
            'What serious causes need to be ruled out first?',
            'Do I need urgent tests or monitoring?',
            'What symptoms should prompt immediate return after assessment?',
        ],
        'doctorSummary' => doctor_summary($data, $reasons),
        'disclaimer' => CARE_DISCLAIMER,
        'redFlagTriggered' => true,
    ];
}
