<?php

namespace Tests\Unit;

use App\Models\RoadSegment;
use App\Models\SegmentType;
use App\Models\SegmentTypeRule;
use App\Services\SegmentRuleResolver;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class SegmentRuleResolverTest extends TestCase
{
    public function test_it_resolves_no_parking_rule_from_other_rules(): void
    {
        $rule = new SegmentTypeRule([
            'rule_name' => 'No parking',
            'rule_type' => 'other',
            'rule_value' => 'No parking',
            'is_active' => true,
        ]);

        $result = (new SegmentRuleResolver())->resolveNoParkingRuleForSegment(
            $this->segmentWithRules([$rule])
        );

        $this->assertIsArray($result);
        $this->assertSame('No parking', $result['rule_name']);
    }

    public function test_inactive_no_parking_rule_is_not_resolved(): void
    {
        $rule = new SegmentTypeRule([
            'rule_name' => 'No parking',
            'rule_type' => 'other',
            'rule_value' => 'No parking',
            'is_active' => false,
        ]);

        $result = (new SegmentRuleResolver())->resolveNoParkingRuleForSegment(
            $this->segmentWithRules([$rule])
        );

        $this->assertNull($result);
    }

    /**
     * @param array<int, SegmentTypeRule> $rules
     */
    private function segmentWithRules(array $rules): RoadSegment
    {
        $segmentType = new SegmentType(['name' => 'Restricted curb']);
        $segmentType->setRelation('defaultRules', new Collection($rules));

        $segment = new RoadSegment(['segment_name' => 'Test segment']);
        $segment->setRelation('segmentType', $segmentType);

        return $segment;
    }
}
