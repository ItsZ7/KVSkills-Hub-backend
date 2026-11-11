<?php
declare(strict_types=1);

final class CompetitionService
{
    public static function helperRequirements(array $skill): array
    {
        return [
            'assistant_count' => (int)($skill['assistant_count'] ?? 0),
            'model_count' => (int)($skill['model_count'] ?? 0),
            'note' => (string)($skill['assistant_note'] ?? ''),
        ];
    }

    public static function validateHelpers(array $skill, array $helpers): array
    {
        $requirements = self::helperRequirements($skill);
        $errors = [];
        $assistantNames = array_values(array_filter(array_map('trim', $helpers['assistant'] ?? [])));
        $modelNames = array_values(array_filter(array_map('trim', $helpers['model'] ?? [])));

        if (count($assistantNames) !== $requirements['assistant_count']) {
            $errors[] = "Bidang {$skill['name']} memerlukan tepat {$requirements['assistant_count']} pembantu.";
        }
        if (count($modelNames) !== $requirements['model_count']) {
            $errors[] = "Bidang {$skill['name']} memerlukan tepat {$requirements['model_count']} model.";
        }
        return $errors;
    }

    public static function medalForScore(float $score, string $category): ?string
    {
        if ($score < 0 || $score > 100 || !in_array($category, ['heavy','light'], true)) {
            return null;
        }
        $thresholds = $category === 'heavy'
            ? [['Emas',90],['Perak',80],['Gangsa',70],['Medallion',65]]
            : [['Emas',95],['Perak',90],['Gangsa',85],['Medallion',75]];
        foreach ($thresholds as [$medal,$minimum]) {
            if ($score >= $minimum) return $medal;
        }
        return null;
    }

    public static function medalBadge(?string $medal): string
    {
        return match ($medal) {
            'Emas' => 'bg-warning text-dark',
            'Perak' => 'bg-secondary',
            'Gangsa' => 'bg-danger',
            'Medallion' => 'bg-info text-dark',
            default => 'bg-light text-dark',
        };
    }
}
