<?php

declare(strict_types=1);

namespace app\controllers\api;

use app\models\Institution;

class InstitutionAjaxController extends BaseAjaxController
{
    private Institution $instModel;

    public function __construct()
    {
        parent::__construct();
        $this->instModel = new Institution();
    }

    /**
     * Lookup institutions by name.
     * POST /api/lookupInstitutions
     */
    public function lookupInstitutions(): void
    {
        $json = json_decode(file_get_contents('php://input'), true) ?? [];
        $this->validateCsrf($json);

        $rawText = trim($json['text'] ?? '');
        
        // Fast exit if empty or too short
        if ($rawText === '' || mb_strlen($rawText) < 4) {
            $this->jsonResponse([]); 
            return;
        }

        // Call the database lookup
        $results = $this->instModel->searchByName($rawText);

        $this->jsonResponse($results);
    }
}
