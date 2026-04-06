<?php

declare(strict_types=1);

namespace app\models;

/**
 * Document Entity
 * 
 * Full entity representing a published document.
 * Extends FeedDocument for common properties and methods.
 */
class Document extends FeedDocument
{
    public int $submitter_ID = 0;
    public int $dtype = 1;
    public ?string $notes = null;
    public ?string $full_text = null;
    public ?int $suppl_ext = null;
    public ?string $pubdate = null;
    public ?string $announce_time = null;
    public ?string $last_update_time = null;
    public ?string $last_revision_time = null;
    public ?string $revision_history = null;
    public int $main_figs = 0;
    public int $main_tabs = 0;
    public int $suppl_size = 0;

    public ?string $submitter_name = null;
    public ?string $submitter_coreid = null;

    private ?array $decodedAffiliations = null;
    private ?array $decodedRevisionHistory = null;

    public function __construct(array $data = [])
    {
        parent::__construct($data);

        $this->decodeJsonFields();
    }

    private function decodeJsonFields(): void
    {
        $authorData = json_decode($this->author_list ?? '', true) ?? [];
        $this->decodedAffiliations = $authorData['affiliations'] ?? [];

        $this->decodedRevisionHistory = json_decode($this->revision_history ?? '[]', true) ?: [];
    }

    public function hasSupplFile(): bool
    {
        return $this->ver_suppl !== null;
    }

    public function getSupplExtLabel(): string
    {
        return match ($this->suppl_ext) {
            1 => 'PDF',
            2 => 'ZIP',
            default => '',
        };
    }

    public function getAffiliations(): array
    {
        return $this->decodedAffiliations ?? [];
    }

    public function getRevisionHistory(): array
    {
        return $this->decodedRevisionHistory ?? [];
    }

    public function isSubmitter(int $mID): bool
    {
        return $this->submitter_ID === $mID;
    }

    public function isOnHold(): bool
    {
        return $this->visibility === VISIBILITY_ON_HOLD;
    }

    public function getMainFileLink(): string
    {
        return "/stream?id={$this->dID}";
    }

    public function getSupplFileLink(): string
    {
        return "/stream?id={$this->dID}&suppl=1";
    }

    public function getVersionedMainFileLink(int $ver): string
    {
        return "/stream?id={$this->dID}&ver={$ver}";
    }

    public function getVersionedSupplFileLink(int $ver): string
    {
        return "/stream?id={$this->dID}&suppl=1&ver={$ver}";
    }

    public function getSubmitterProfileUrl(): string
    {
        return "/profile?id=" . ($this->submitter_coreid ?? $this->submitter_ID);
    }

    public function getFormattedAnnounceTime(): string
    {
        if (empty($this->announce_time)) return '&mdash;';
        return date('M d, Y', strtotime($this->announce_time));
    }

    public function getFormattedLastUpdateTime(): string
    {
        if (empty($this->last_update_time)) return '';
        return date('Y-m-d H:i:s', strtotime($this->last_update_time)) . ' UTC';
    }

    public function getFormattedLastRevisionTime(): string
    {
        if (!empty($this->last_revision_time)) {
            return date('Y-m-d H:i:s', strtotime($this->last_revision_time)) . ' UTC';
        }
        if (!empty($this->submission_time)) {
            return date('Y-m-d H:i:s', strtotime($this->submission_time)) . ' UTC';
        }
        return '';
    }

    public function getFormattedSupplSize(): string
    {
        return self::formatSize($this->suppl_size);
    }
}
