<?php

namespace App\Modules\Hr\Performance\Services;

class PerformanceQuestionnaireService
{
    private const TYPES = ['rating', 'text', 'number', 'boolean', 'choice'];

    public function normalize(array $sections): array
    {
        abort_if($sections === [], 422, 'Şablonda en az bir bölüm olmalıdır.');
        $ids = [];
        $scorableWeight = 0.0;
        $normalized = [];
        foreach ($sections as $sectionIndex => $section) {
            abort_if(blank($section['title'] ?? null) || empty($section['questions']), 422, 'Şablon bölümü geçersiz.');
            $questions = [];
            foreach ($section['questions'] as $questionIndex => $question) {
                $id = trim((string) ($question['id'] ?? ''));
                $type = (string) ($question['type'] ?? 'rating');
                $weight = round((float) ($question['weight'] ?? 0), 2);
                abort_if($id === '' || in_array($id, $ids, true) || blank($question['label'] ?? null) || ! in_array($type, self::TYPES, true), 422, 'Şablon soruları geçersiz.');
                abort_if($type !== 'text' && $weight <= 0, 422, 'Puanlanan soruların ağırlığı sıfırdan büyük olmalıdır.');
                $ids[] = $id;
                $item = [
                    'id' => $id,
                    'label' => trim((string) $question['label']),
                    'type' => $type,
                    'required' => (bool) ($question['required'] ?? true),
                    'weight' => $type === 'text' ? 0 : $weight,
                ];
                if ($type === 'number') {
                    $item['min'] = (float) ($question['min'] ?? 0);
                    $item['max'] = (float) ($question['max'] ?? 100);
                    abort_if($item['max'] <= $item['min'], 422, 'Sayısal sorunun üst sınırı alt sınırdan büyük olmalıdır.');
                }
                if ($type === 'choice') {
                    $item['options'] = array_values($question['options'] ?? []);
                    abort_if(count($item['options']) < 2, 422, 'Seçimli soruda en az iki seçenek olmalıdır.');
                    foreach ($item['options'] as $option) {
                        abort_if(blank($option['value'] ?? null) || blank($option['label'] ?? null) || ! is_numeric($option['score'] ?? null) || $option['score'] < 0 || $option['score'] > 100, 422, 'Seçim seçenekleri geçersiz.');
                    }
                }
                $scorableWeight += $item['weight'];
                $questions[] = $item;
            }
            $normalized[] = ['title' => trim((string) $section['title']), 'questions' => $questions];
        }
        abort_if(abs($scorableWeight - 100) > 0.01, 422, 'Puanlanan soruların ağırlıkları toplamı 100 olmalıdır.');

        return $normalized;
    }

    public function evaluate(array $sections, array $answers): array
    {
        $clean = [];
        $score = 0.0;
        foreach ($sections as $section) {
            foreach ($section['questions'] as $question) {
                $id = (string) $question['id'];
                $type = (string) ($question['type'] ?? 'rating');
                $value = $answers[$id] ?? null;
                $required = (bool) ($question['required'] ?? true);
                abort_if($required && ($value === null || $value === ''), 422, "{$question['label']} yanıtı zorunludur.");
                if ($value === null || $value === '') {
                    $clean[$id] = null;
                    continue;
                }
                $weight = (float) ($question['weight'] ?? 0);
                if ($type === 'rating') {
                    abort_unless(is_numeric($value) && $value >= 1 && $value <= 5, 422, "{$question['label']} için 1-5 arası puan girin.");
                    $clean[$id] = (int) $value;
                    $score += ((float) $value / 5) * $weight;
                } elseif ($type === 'text') {
                    $clean[$id] = trim((string) $value);
                    abort_if(mb_strlen($clean[$id]) > 2000, 422, "{$question['label']} yanıtı 2000 karakteri aşamaz.");
                } elseif ($type === 'number') {
                    $min = (float) ($question['min'] ?? 0);
                    $max = (float) ($question['max'] ?? 100);
                    abort_unless(is_numeric($value) && $value >= $min && $value <= $max, 422, "{$question['label']} sayısal sınırların dışında.");
                    $clean[$id] = (float) $value;
                    $score += (((float) $value - $min) / ($max - $min)) * $weight;
                } elseif ($type === 'boolean') {
                    $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    abort_if($boolean === null, 422, "{$question['label']} için evet veya hayır seçin.");
                    $clean[$id] = $boolean;
                    $score += $boolean ? $weight : 0;
                } else {
                    $option = collect($question['options'] ?? [])->firstWhere('value', (string) $value);
                    abort_unless($option, 422, "{$question['label']} için geçerli bir seçenek seçin.");
                    $clean[$id] = (string) $value;
                    $score += ((float) $option['score'] / 100) * $weight;
                }
            }
        }

        return ['answers' => $clean, 'score' => round($score, 2)];
    }
}
