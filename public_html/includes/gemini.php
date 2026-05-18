<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

function call_gemini(string $systemPrompt, string $userPrompt): array
{
    $config = app_config();
    $apiKey = trim((string)($config['gemini_api_key'] ?? ''));
    $model = trim((string)($config['gemini_model'] ?? 'gemini-flash-latest'));

    if ($apiKey === '' || $apiKey === 'YOUR_KEY') {
        return [
            'ok' => false,
            'model_id' => $model,
            'data' => null,
            'error' => 'missing_api_key',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'model_id' => $model,
            'data' => null,
            'error' => 'curl_missing',
        ];
    }

    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $payload = [
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemPrompt],
            ],
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $userPrompt],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => [
                    'urgencyLevel' => ['type' => 'STRING', 'enum' => ['self-care', 'doctor-soon', 'urgent', 'emergency']],
                    'mostLikelyExplanation' => ['type' => 'STRING'],
                    'confidence' => ['type' => 'STRING', 'enum' => ['low', 'medium', 'high']],
                    'possibleCauses' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                    'recommendedTests' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                    'recommendedTestReason' => ['type' => 'STRING'],
                    'whatToDoNow' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                    'whenToSeekHelp' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                    'doctorQuestions' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                    'doctorSummary' => ['type' => 'STRING'],
                    'regionalViralNote' => ['type' => 'STRING'],
                ],
                'required' => [
                    'urgencyLevel',
                    'mostLikelyExplanation',
                    'confidence',
                    'possibleCauses',
                    'recommendedTests',
                    'recommendedTestReason',
                    'whatToDoNow',
                    'whenToSeekHelp',
                    'doctorQuestions',
                    'doctorSummary',
                    'regionalViralNote',
                ],
            ],
        ],
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-goog-api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 45,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        $errorDetail = $curlError ?: 'api_http_' . $httpCode;
        if (is_string($response) && trim($response) !== '') {
            $body = json_decode($response, true);
            $message = $body['error']['message'] ?? null;
            $errorDetail .= ': ' . clean_text(is_string($message) ? $message : $response, 500);
        }

        return [
            'ok' => false,
            'model_id' => $model,
            'data' => null,
            'error' => $errorDetail,
        ];
    }

    $decoded = json_decode((string)$response, true);
    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!is_string($text) || trim($text) === '') {
        return [
            'ok' => false,
            'model_id' => $model,
            'data' => null,
            'error' => 'empty_response',
        ];
    }

    $json = json_decode($text, true);
    if (!is_array($json)) {
        $json = extract_json_object($text);
    }

    return [
        'ok' => is_array($json),
        'model_id' => $model,
        'data' => is_array($json) ? $json : null,
        'error' => is_array($json) ? null : 'invalid_json',
    ];
}

function extract_json_object(string $text): ?array
{
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }

    $candidate = substr($text, $start, $end - $start + 1);
    $decoded = json_decode($candidate, true);

    return is_array($decoded) ? $decoded : null;
}

function validate_ai_result($result, array $data): ?array
{
    if (!is_array($result)) {
        return null;
    }

    $required = [
        'urgencyLevel',
        'mostLikelyExplanation',
        'confidence',
        'possibleCauses',
        'recommendedTests',
        'whatToDoNow',
        'whenToSeekHelp',
        'doctorQuestions',
        'doctorSummary',
    ];

    foreach ($required as $key) {
        if (!array_key_exists($key, $result)) {
            return null;
        }
    }

    $urgency = (string)$result['urgencyLevel'];
    if (!in_array($urgency, ['self-care', 'doctor-soon', 'urgent', 'emergency'], true)) {
        $urgency = 'doctor-soon';
    }

    $confidence = strtolower((string)$result['confidence']);
    if (!in_array($confidence, ['low', 'medium', 'high'], true)) {
        $confidence = 'low';
    }

    return [
        'urgencyLevel' => $urgency,
        'mostLikelyExplanation' => clean_multiline($result['mostLikelyExplanation'], 1200),
        'confidence' => $confidence,
        'possibleCauses' => normalize_result_list($result['possibleCauses'], 5),
        'recommendedTests' => normalize_result_list($result['recommendedTests'], 1),
        'recommendedTestReason' => clean_multiline($result['recommendedTestReason'] ?? '', 400),
        'whatToDoNow' => normalize_result_list($result['whatToDoNow'], 5),
        'whenToSeekHelp' => normalize_result_list($result['whenToSeekHelp'], 5),
        'doctorQuestions' => normalize_result_list($result['doctorQuestions'], 4),
        'doctorSummary' => clean_multiline($result['doctorSummary'] ?: doctor_summary($data), 1800),
        'disclaimer' => CARE_DISCLAIMER,
        'regionalViralNote' => clean_multiline($result['regionalViralNote'] ?? '', 600),
        'redFlagTriggered' => false,
    ];
}

function normalize_result_list($items, int $maxItems): array
{
    if (!is_array($items)) {
        $items = [$items];
    }

    $clean = [];
    foreach ($items as $item) {
        $item = clean_text($item, 340);
        if ($item !== '') {
            $clean[] = $item;
        }
    }

    return array_slice($clean, 0, $maxItems);
}

function fallback_result(array $data): array
{
    return [
        'urgencyLevel' => 'doctor-soon',
        'mostLikelyExplanation' => 'This could not be matched safely by the automated check. It may still be something minor, but it is sensible to speak with a health professional if symptoms persist, worsen, or worry you.',
        'confidence' => 'low',
        'possibleCauses' => [
            'A common short-term irritation or strain',
            'An infection or inflammation',
            'Another cause that needs a clinician to assess in context',
        ],
        'recommendedTests' => [],
        'whatToDoNow' => [
            'Rest and avoid activities that clearly make it worse.',
            'Drink fluids and monitor any change in symptoms.',
            'Use simple self-care measures you already know are safe for you.',
            'Arrange medical advice if this is not improving or you feel concerned.',
        ],
        'whenToSeekHelp' => [
            'Seek urgent help if severe symptoms appear or the problem worsens quickly.',
            'Contact a doctor soon if symptoms last more than a few days, keep returning, or interfere with normal activity.',
        ],
        'doctorQuestions' => [
            'What are the most likely causes for these symptoms?',
            'Do I need any tests or an examination?',
            'What changes would mean I need urgent help?',
        ],
        'doctorSummary' => doctor_summary($data),
        'disclaimer' => CARE_DISCLAIMER,
        'redFlagTriggered' => false,
    ];
}

function build_system_prompt(): string
{
    return 'You are a careful health guidance assistant. You do not diagnose, do not claim certainty, and do not replace a doctor. Your role is to assess user-provided symptoms, identify possible explanations, estimate urgency, and give practical next steps.

Use all provided details, including age, sex, symptom location, duration, severity, worsening pattern, warning symptoms, medical history, medications, allergies, and conditional answers. Prioritize safety: if symptoms suggest a red flag or possible serious condition, recommend urgent or emergency care clearly.

Be specific and useful. Explain the most likely explanation based on the provided information, but also mention important alternative causes when relevant. Give a confidence level that is calibrated to the quality and completeness of the information:
- Use "high" only when the symptom pattern is clear and low-risk.
- Use "medium" when there is enough information to give useful guidance but more details would help.
- Use "low" when symptoms are vague, severe, conflicting, or potentially serious.

Do not exaggerate certainty. Do not provide a definitive diagnosis. Do not recommend prescription medication. Do not tell the user to ignore symptoms. Avoid vague advice when clearer action is possible. Use simple, calm, practical language.

For regionalViralNote: if the user provided a location, use your knowledge of typical seasonal and regional disease patterns to assess whether any currently common illnesses in that area (flu, COVID variants, RSV, norovirus, scarlet fever, strep, or other locally prevalent infections) are consistent with the reported symptoms. Write one concise sentence naming the illness and why it fits — for example: "Flu is currently widespread in London and matches your fever, body aches, and fatigue." If the symptoms do not match any regional illness, or no location was provided, return an empty string.

For recommendedTests, return at most ONE item — the single most useful home test, home monitoring tool, or online-orderable test given your ANALYSIS CONCLUSION, not just the raw symptoms. Base this on what you concluded, not on what the user described. If your conclusion points to a specific condition, recommend a specific option for that condition: for example "Thyroid TSH Test" for suspected hypothyroidism, "Ferritin Test" for suspected iron-deficiency anaemia, "HbA1c inger-prick Test" for suspected diabetes, or " Urine Dipstick Test" for suspected UTI. For vague or multi-cause presentations where no condition can be concluded, a home monitoring tool is appropriate: "COVID lateral flow test" for viral respiratory illness, "Thermometer" for general fever monitoring, "Blood Pressure Monitor" for unexplained dizziness or headaches, "Peak Flow Meter" for breathing issues, "Pregnancy Test" where pregnancy is plausible. Do not return generic phrases like "blood test" or "see a doctor" — always be specific. If no home or online-orderable at-home option genuinely adds value, return an empty array.

For recommendedTestReason, write 2 sentences: what the test measures or detects, then what an abnormal result reveals and how it helps confirm or rule out your concluded diagnosis. Example: "An Thyroid TSH Test measures thyroid-stimulating hormone levels. An abnormal result would support or argue against hypothyroidism and help decide whether medical follow-up is needed." If recommendedTests is empty, return an empty string.

Keep the result concise. For mostLikelyExplanation, use 2-3 clear sentences. For lists, return only the most useful items and keep each item to one short actionable sentence. For doctorSummary, write a compact paragraph a doctor could scan quickly.

Return only valid JSON matching the requested schema. Do not include markdown, explanations outside JSON, or extra keys.';
}

function build_user_prompt(array $data): string
{
    $profile = $data['profile'] ?? [];
    $issue = $data['issue'] ?? [];
    $location = $data['location'] ?? [];
    $followup = $data['followup'] ?? [];
    $context = $data['context'] ?? [];
    $conditional = $followup['conditional'] ?? [];

    $conditionalLines = [];
    foreach ($conditional as $key => $value) {
        $label = ucwords(str_replace(['conditional_', '_'], ['', ' '], (string)$key));
        $conditionalLines[] = '- ' . $label . ': ' . ($value ?: 'Not answered');
    }

    if (!$conditionalLines) {
        $conditionalLines[] = '- None';
    }

    $medications = ($context['medication_status'] ?? '') === 'Yes'
        ? 'Yes - ' . (($context['medication_text'] ?? '') ?: 'details not provided')
        : (($context['medication_status'] ?? '') ?: 'Not answered');
    $allergies = list_text($profile['allergies'] ?? []);

    return 'Analyze this user health-check information and return structured guidance.

User profile:
- Sex: ' . (($profile['sex'] ?? '') ?: 'Not answered') . '
- Age: ' . (($profile['age'] ?? '') !== '' ? (string)$profile['age'] : 'Not answered') . '
- Location: ' . (($profile['user_location'] ?? '') ?: 'Not provided') . '
- Important conditions: ' . list_text($profile['conditions'] ?? []) . '
- Allergies: ' . $allergies . '
- Pregnancy status: ' . (($profile['pregnancy_status'] ?? '') ?: 'Not applicable') . '

Symptom:
- Description: ' . (($issue['description'] ?? '') ?: 'Not answered') . '
- Body area: ' . (($issue['body_area'] ?? '') ?: 'Not selected') . '
- Precise location: ' . (($location['precise_location'] ?? '') ?: 'Not answered') . '
- Duration: ' . (($followup['duration'] ?? '') ?: 'Not answered') . '
- Severity: ' . (($followup['severity'] ?? '') ?: 'Not answered') . '
- Getting worse: ' . (($followup['worsening'] ?? '') ?: 'Not answered') . '
- Warning symptoms: ' . list_text($followup['warning_symptoms'] ?? []) . '

Conditional answers:
' . implode("\n", $conditionalLines) . '

Medical context:
- History: ' . list_text($context['medical_history'] ?? []) . '
- Medications: ' . $medications . '
- Allergies: ' . $allergies . '

Structured state object:
' . json_encode($data['structured'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '

Return JSON only, matching this schema:
{
  "urgencyLevel": "self-care" | "doctor-soon" | "urgent" | "emergency",
  "mostLikelyExplanation": "...",
  "confidence": "low" | "medium" | "high",
  "possibleCauses": ["..."],
  "recommendedTests": ["One specific home test, home monitoring tool, or online-orderable  test based on your ANALYSIS CONCLUSION. Empty array if no home option adds value."],
  "recommendedTestReason": "2 sentences: what the test measures, then how an abnormal result confirms or rules out your concluded diagnosis. Empty string if no test.",
  "whatToDoNow": ["Practical things the user can do at home right now - rest, diet, self-care, monitoring. Do not include see a doctor or seek medical advice here; those belong in whenToSeekHelp."],
  "whenToSeekHelp": ["..."],
  "doctorQuestions": ["..."],
  "doctorSummary": "...",
  "regionalViralNote": "One sentence about a regional viral illness matching symptoms, or empty string."
}';
}
