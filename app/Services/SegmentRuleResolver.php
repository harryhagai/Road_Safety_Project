<?php

namespace App\Services;

use App\Models\RoadSegment;
use App\Models\SegmentTypeRule;
use Illuminate\Support\Collection;

class SegmentRuleResolver
{
    public function resolveEffectiveRulesForSegment(RoadSegment $segment): Collection
    {
        $segment->loadMissing([
            'segmentType.defaultRules',
        ]);

        if (! $segment->segmentType) {
            return collect();
        }

        return $segment->segmentType->defaultRules
            ->map(function (SegmentTypeRule $templateRule) use ($segment): array {
                return [
                    'segment_id' => $segment->id,
                    'segment_name' => $segment->segment_name,
                    'segment_type_rule_id' => $templateRule->id,
                    'rule_name' => $templateRule->rule_name,
                    'rule_type' => $templateRule->rule_type,
                    'rule_value' => $templateRule->rule_value,
                    'description' => $templateRule->description,
                    'is_active' => $templateRule->is_active,
                ];
            })
            ->filter(fn (array $rule): bool => $this->isRuleActiveNow($rule))
            ->values();
    }

    public function resolveSpeedLimitRuleForSegment(RoadSegment $segment): ?array
    {
        return $this->resolveEffectiveRulesForSegment($segment)
            ->first(fn (array $rule): bool => (string) $rule['rule_type'] === 'speed_limit');
    }

    private function isRuleActiveNow(array $rule): bool
    {
        return (bool) ($rule['is_active'] ?? false);
    }
}
