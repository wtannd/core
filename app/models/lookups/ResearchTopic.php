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

    /**
     * Fetch a single research topic by its ID.
     * * @param int $tID The topic ID
     * @return array|null
     */
    public function getTopicById(int $tID): ?array
    {
        // Call the parent's protected method with the exact table structure
        return $this->getOne('ResearchTopics', 'tID', $tID, 'tID, abbr, tname, is_active');
    }
}
