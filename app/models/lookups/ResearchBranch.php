<?php

declare(strict_types=1);

namespace app\models\lookups;

/**
 * ResearchBranch Model
 */
class ResearchBranch extends BaseLookupModel
{
    /**
     * Fetch all research branches.
     */
    public function getAllBranches(): array
    {
        return $this->getAll('ResearchBranches', 'bID', 'abbr, bname', 'abbr', true, 1);
    }
}
