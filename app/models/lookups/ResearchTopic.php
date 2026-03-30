<?php

declare(strict_types=1);

namespace app\models\lookups;

/**
 * ResearchTopic Model
 */
class ResearchTopic extends BaseLookupModel
{
    /**
     * Fetch all research topics.
     */
    public function getAllTopics(): array
    {
        return $this->getAll('ResearchTopics', 'tID', 'abbr, tname', 'abbr', true, 1);
    }
}
