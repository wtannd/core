<?php

declare(strict_types=1);

namespace app\models\lookups;

/**
 * Institution Model
 */
class Institution extends BaseLookupModel
{
    /**
     * Fetch all available institutions.
     */
    public function getAllInstitutions(): array
    {
        return $this->getAll('Institutions', 'iID', 'iname', 'iname', true);
    }
}
