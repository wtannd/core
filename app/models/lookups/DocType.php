<?php

declare(strict_types=1);

namespace app\models\lookups;

/**
 * DocType Model
 */
class DocType extends BaseLookupModel
{
    /**
     * Fetch all document types.
     */
    public function getAllDocTypes(): array
    {
        return $this->getAll('DocTypes', 'ID', 'dtname', 'ID', true, 0, 255);
    }
}
