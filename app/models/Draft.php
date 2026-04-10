<?php

declare(strict_types=1);

namespace app\models;

/**
 * Draft Entity
 * 
 * Pure data entity representing a document draft.
 * Contains utility methods and auto-decodes JSON fields.
 */
class Draft
{
    public int $dID = 0;
    public int $submitter_ID = 0;
    public string $title = '';
    public string $abstract = '';
    public ?string $author_list = null;
    public int $has_file = 0;
    public int $dtype = 1;
    public ?string $notes = null;
    public ?string $full_text = null;
    public ?string $link_list = null;
    public ?string $branch_list = null;
    public ?int $tID = null;
    public ?string $datetime_added = null;
    public ?string $last_update_time = null;
    public ?string $submission_time = null;
    public ?string $pubdate = null;
    public ?int $main_pages = null;
    public ?int $main_figs = null;
    public ?int $main_tabs = null;

    private ?array $decodedAuthors = null;
    private ?array $decodedAffiliations = null;
    private ?array $decodedBranches = null;
    private ?array $decodedExtLinks = null;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        $this->decodeJsonFields();
    }

    private function decodeJsonFields(): void
    {
        $authorData = json_decode($this->author_list ?? '', true) ?? [];
        $this->decodedAuthors = $authorData['authors'] ?? [];
        $this->decodedAffiliations = $authorData['affiliations'] ?? [];

        $this->decodedBranches = json_decode($this->branch_list ?? '[]', true) ?: [];
        $this->decodedExtLinks = json_decode($this->link_list ?? '[]', true) ?: [];
    }

    public function getAuthors(): array
    {
        return $this->decodedAuthors ?? [];
    }

    public function getAffiliations(): array
    {
        return $this->decodedAffiliations ?? [];
    }

    public function getBranches(): array
    {
        return $this->decodedBranches ?? [];
    }

    public function getExtLinks(): array
    {
        return $this->decodedExtLinks ?? [];
    }

    public function hasMainFile(): bool
    {
        return $this->has_file > 0;
    }

    public function hasSupplFile(): bool
    {
        return $this->has_file === 2 || $this->has_file === 3;
    }

    public function getFileTypeLabel(): string
    {
        return match ($this->has_file) {
            0 => 'None',
            1 => 'Main PDF',
            2 => 'Main + Suppl PDF',
            3 => 'Main + Suppl ZIP',
            default => 'Unknown',
        };
    }

    public function getMainFileLink(): string
    {
        return "/stream?type=draft&id={$this->dID}";
    }

    public function getSupplFileLink(): string
    {
        return "/stream?type=draft&id={$this->dID}&suppl";
    }

    public function isSubmitter(int $mID): bool
    {
        return $this->submitter_ID === $mID;
    }

    public function getFormattedDateAdded(): string
    {
        if (empty($this->datetime_added)) return '';
        return date('M d, Y H:i', strtotime($this->datetime_added)) . ' UTC';
    }

    public function getFormattedLastUpdateTime(): string
    {
        if (empty($this->last_update_time)) return '';
        return date('M d, Y H:i:s', strtotime($this->last_update_time)) . ' UTC';
    }

    public function getFormattedSubmissionTime(): string
    {
        if (empty($this->submission_time)) return '';
        return date('M d, Y H:i:s', strtotime($this->submission_time)) . ' UTC';
    }

    public function getFormattedSubmitTime(): string
    {
        if (!empty($this->datetime_added)) {
            return date('M d, Y', strtotime($this->datetime_added));
        }
        if (!empty($this->submission_time)) {
            return date('M d, Y', strtotime($this->submission_time));
        }
        return '';
    }

    public function getEditUrl(): string
    {
        return "/edit_draft?id={$this->dID}";
    }
}
