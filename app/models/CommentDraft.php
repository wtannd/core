<?php

declare(strict_types=1);

namespace app\models;

/**
 * CommentDraft Entity
 * 
 * Pure data entity representing a comment draft.
 * Contains utility methods and auto-decodes JSON fields.
 */
class CommentDraft
{
    public int $cID = 0;
    public int $ctype = 3;  // Default: Creditable Comment
    public ?int $parent_ID = null;
    public int $dID = 0;
    public int $submitter_ID = 0;
    public ?int $inviter_id = null;
    public ?string $author_list = null;
    public ?string $ts = null;
    public string $comment_text = '';
    public int $anonymity = 1;  // Default: Open
    public ?string $passcode = null;
    public int $to_be_moderated = 0;

    // Additional fields from JOINs
    public ?string $doc_title = null;
    public ?string $doc_doi = null;
    public ?string $submitter_name = null;
    public ?string $submitter_coreid = null;

    private ?array $decodedAuthors = null;
    private ?array $decodedAuthorList = null;

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
        if (!empty($this->author_list)) {
            $authorData = json_decode($this->author_list, true) ?? [];
            $this->decodedAuthorList = $authorData;
            $this->decodedAuthors = $authorData['authors'] ?? [];
        }
    }

    public function getAuthors(): array
    {
        return $this->decodedAuthors ?? [];
    }

    public function getAuthorList(): ?array
    {
        return $this->decodedAuthorList;
    }

    public function isSubmitter(int $mID): bool
    {
        return $this->submitter_ID === $mID;
    }

    public function getFormattedTime(): string
    {
        if (empty($this->ts)) return '';
        return date('M d, Y', strtotime($this->ts));
    }

    public function getFormattedDateTime(): string
    {
        if (empty($this->ts)) return '';
        return date('Y-m-d H:i:s', strtotime($this->ts)) . ' UTC';
    }

    public function isCreditable(): bool
    {
        return $this->ctype === 1 || $this->ctype === 2 || $this->ctype === 3;
    }

    public function isInvitedReview(): bool
    {
        return $this->ctype === 1;
    }

    public function isContributedReview(): bool
    {
        return $this->ctype === 2;
    }

    public function isCreditableComment(): bool
    {
        return $this->ctype === 3;
    }

    public function isUncreditedComment(): bool
    {
        return $this->ctype === 4;
    }

    public function isAnonymous(): bool
    {
        return $this->anonymity > 3;
    }

    public function isOpen(): bool
    {
        return $this->anonymity < 4;
    }

    public function getCommentTypeLabel(): string
    {
        return match ($this->ctype) {
            1 => 'Invited Review',
            2 => 'Contributed Review',
            3 => 'Creditable Comment',
            4 => 'Uncredited Comment',
            default => 'Unknown'
        };
    }

    public function getAnonymityLabel(): string
    {
        return match ($this->anonymity) {
            1 => 'Open',
            2 => 'Open After Semi-Anonymous',
            3 => 'Open After Anonymous',
            4 => 'Semi-Anonymous',
            5 => 'Anonymous',
            default => 'Unknown'
        };
    }

    public function hasPasscode(): bool
    {
        return $this->anonymity === 5 && !empty($this->passcode);
    }

    public function toBeModerated(): bool
    {
        return $this->to_be_moderated === 1;
    }

    public function canViewSubmitter(int $adminRole, int $mID): bool
    {
        if ($this->isOpen()) return true;
        if ($this->submitter_ID === $mID) return true;
        if ($adminRole >= ADMIN_ROLE_MIN) return true;
        return false;
    }

    public function getSubmitterDisplayName(int $adminRole, int $mID): string
    {
        if (!$this->canViewSubmitter($adminRole, $mID)) {
            return match ($this->anonymity) {
                4 => 'Semi-Anonymous',
                5 => 'Anonymous',
                default => 'Anonymous'
            };
        }
        return $this->submitter_name ?? 'Unknown';
    }

    public function getSubmitterProfileUrl(): string
    {
        if ($this->submitter_ID === 0) return '';
        return "/member/" . ($this->submitter_coreid ?? $this->submitter_ID);
    }

    public function hasParent(): bool
    {
        return $this->parent_ID !== null;
    }

    public function getEditUrl(): string
    {
        return "/edit_comment_draft?id={$this->cID}";
    }

    public function getViewUrl(): string
    {
        return "/commentdraft?id={$this->cID}";
    }
}
