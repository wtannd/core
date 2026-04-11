<?php

declare(strict_types=1);

namespace app\models;

/**
 * FeedDocument Entity
 * 
 * Lightweight entity for document lists/feeds.
 * Only includes columns needed for feed display.
 */
class FeedDocument
{
    public int $dID = 0;
    public ?string $doi = null;
    public int $version = 0;
    public ?int $ver_suppl = null;
    public ?int $main_pages = null;
    public ?int $main_size = null;
    public ?string $submission_time = null;
    public ?string $author_list = null;
    public string $abstract = '';
    public string $title = '';
    public int $visibility = 1;

    private ?array $decodedAuthors = null;

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
    }

    public function getAuthors(): array
    {
        return $this->decodedAuthors ?? [];
    }

    public function getFormattedSubmitTime(): string
    {
        if (empty($this->submission_time)) return '';
        return date('M d, Y', strtotime($this->submission_time));
    }

    public function getVersionString(): string
    {
        $str = "v{$this->version}";
        if ($this->ver_suppl !== null) {
            $str .= "+v{$this->ver_suppl}";
        }
        return $str;
    }

    public function hasDoi(): bool
    {
        return !empty($this->doi);
    }

    public function hasMainFile(): bool
    {
        return $this->version > 0;
    }

    public static function formatSize(int $bytes): string
    {
        if ($bytes <= 0) return '';
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    public function getFormattedMainSize(): string
    {
        return self::formatSize($this->main_size);
    }
}
