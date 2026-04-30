<?php

declare(strict_types=1);

namespace app\models;

/**
 * Comment Entity
 * 
 * Represents a comment, review, or rating on a document.
 * Handles both basic comments and creditable comments/reviews.
 */
class Comment
{
    public int $cID = 0;
    public int $ctype = 3;  // Default: Creditable Comment
    public ?int $parent_ID = null;
    public int $dID = 0;
    public ?int $submitter_ID = null;
    public int $visibility = 60;
    public ?string $ts = null;
    public string $comment_text = '';

    // CRComment fields (for creditable comments/reviews)
    public ?int $ID_num = null;
    public ?string $ID_alphanum = null;
    public ?int $inviter_id = null;
    public ?string $author_list = null;
    public int $anonymity = 1;  // Default: Open
    public ?string $passcode = null;
    public int $Nth = 1;
    public int $T = 0;
    public int $N_ratings = 0;
    public float $S_ave = 0.0;
    public float $ECP = 0.0;

    // Additional fields from JOINs
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

    public function isSubmitter(int $mID): bool
    {
        return $this->submitter_ID === $mID;
    }

    public function getAuthors(): array
    {
        return $this->decodedAuthors ?? [];
    }

    public function getAuthorList(): ?array
    {
        return $this->decodedAuthorList;
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

    public function isAnonymous(): bool
    {
        return $this->anonymity > 3;
    }

    public function isOpen(): bool
    {
        return $this->anonymity < 4;
    }

    public function hasPasscode(): bool
    {
        return $this->anonymity === 5 && !empty($this->passcode);
    }

    public function canViewSubmitter(int $adminRole, int $mID): bool
    {
        if ($this->isOpen()) return true;
        if ($this->submitter_ID === $mID) return true;
        if ($adminRole >= ADMIN_ROLE_MIN) return true; // Admin can see
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
        if ($this->submitter_ID === null) return '';
        return "/member/" . ($this->submitter_coreid ?? $this->submitter_ID);
    }

    public function hasRatings(): bool
    {
        return $this->N_ratings > 0;
    }

    public function getFormattedS_ave(): string
    {
        if ($this->N_ratings === 0) return '—';
        return number_format($this->S_ave / 10, 1);
    }

    public function getFormattedECP(): string
    {
        if ($this->ECP == 0) return '—';
        return number_format($this->ECP, 2);
    }

    public function isOnHold(): bool
    {
        return $this->visibility >= VISIBILITY_ON_HOLD;
    }

    public function isVisibleTo(int $mRole): bool
    {
        return $mRole >= $this->visibility;
    }

    public function hasParent(): bool
    {
        return $this->parent_ID !== null;
    }

    public function getCommentUrl(): string
    {
        return "/comment?id={$this->cID}";
    }

    public function getCommentIdString(): string
    {
        if (!empty($this->ID_alphanum)) {
            return $this->ID_alphanum;
        }
        return (string)$this->cID;
    }
}
