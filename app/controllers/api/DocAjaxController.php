<?php

declare(strict_types=1);

namespace app\controllers\api;

use app\controllers\BaseController;
use app\models\Member;

/**
 * DocAjaxController
 * 
 * Handles AJAX endpoints for document-related operations.
 */
class DocAjaxController extends BaseAjaxController
{
    private Member $memberModel;

    public function __construct()
    {
        parent::__construct();
        $this->memberModel = new Member();
    }

    /**
     * Lookup authors by CORE-ID or alphanumeric ID.
     * POST /lookupAuthors
     */
    public function lookupAuthors(): void
    {
        $json = json_decode(file_get_contents('php://input'), true) ?? [];
        $this->validateCsrf($json);

        $rawText = $json['text'] ?? '';
		if (trim($rawText) === '') {
			$this->jsonResponse([]); // Fast exit if empty
			return;
		}

        $rawIds = preg_split('/[\n\r,;]+/', $rawText, -1, PREG_SPLIT_NO_EMPTY);
        $parsedIds = [];
        foreach ($rawIds as $id) {
            $clean = str_replace('-', '', trim($id));
            if (!empty($clean)) {
                $clean = ltrim($clean, '0');
                if ($clean !== '') $parsedIds[] = $clean;
            }
        }
        $parsedIds = array_unique($parsedIds);

        $results = [];
        if (!empty($parsedIds)) {
            $results = $this->memberModel->lookUpByCoreIDs($parsedIds);
        }

        $this->jsonResponse($results);
    }
}
