<?php

declare(strict_types=1);

namespace App\Support\Torrents;

final class TorrentBrowseFilters
{
    public function __construct(
        public readonly string $q,
        public readonly string $type,
        public readonly string $resolution,
        public readonly string $source,
        public readonly ?int $categoryId,
        public readonly string $order,
        public readonly string $direction,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $q = trim((string) ($input['q'] ?? ''));
        $type = trim((string) ($input['type'] ?? ''));
        $resolution = trim((string) ($input['resolution'] ?? ''));
        $source = trim((string) ($input['source'] ?? ''));

        $sort = trim((string) ($input['sort'] ?? ''));
        $order = trim((string) ($input['order'] ?? $sort));
        $direction = strtolower(trim((string) ($input['direction'] ?? 'desc')));

        $categoryRaw = $input['category'] ?? $input['category_id'] ?? null;
        $categoryId = null;

        if ($categoryRaw !== null && $categoryRaw !== '' && is_numeric($categoryRaw)) {
            $categoryId = (int) $categoryRaw;
        }

        if ($order === '') {
            $order = 'uploaded_at';
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return new self($q, $type, $resolution, $source, $categoryId, $order, $direction);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'q' => $this->q,
            'type' => $this->type,
            'resolution' => $this->resolution,
            'source' => $this->source,
            'category_id' => $this->categoryId,
            'order' => $this->order,
            'direction' => $this->direction,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function queryParams(): array
    {
        $params = [];

        if ($this->q !== '') {
            $params['q'] = $this->q;
        }

        if ($this->type !== '') {
            $params['type'] = $this->type;
        }

        if ($this->resolution !== '') {
            $params['resolution'] = $this->resolution;
        }

        if ($this->source !== '') {
            $params['source'] = $this->source;
        }

        if ($this->categoryId !== null) {
            $params['category_id'] = $this->categoryId;
        }

        if ($this->order !== '' && $this->order !== 'uploaded_at') {
            $params['order'] = $this->order;
        }

        if ($this->direction !== 'desc') {
            $params['direction'] = $this->direction;
        }

        return $params;
    }
}
