<?php

declare(strict_types=1);

namespace App\Support\Torrents;

use Illuminate\Http\Request;

final class TorrentBrowseFilters
{
    public function __construct(
        public readonly string $q,
        public readonly string $type,
        public readonly ?int $categoryId,
        public readonly string $order,
        public readonly string $direction,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $q = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));

        $sort = trim((string) $request->query('sort', ''));
        $order = trim((string) $request->query('order', $sort));
        $direction = strtolower(trim((string) $request->query('direction', 'desc')));

        $categoryRaw = $request->query('category', $request->query('category_id'));
        $categoryId = null;

        if ($categoryRaw !== null && $categoryRaw !== '' && ctype_digit((string) $categoryRaw)) {
            $categoryId = (int) $categoryRaw;
        }

        if ($order === '') {
            $order = 'uploaded_at';
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return new self($q, $type, $categoryId, $order, $direction);
    }

    public function toArray(): array
    {
        return [
            'q' => $this->q,
            'type' => $this->type,
            'category_id' => $this->categoryId,
            'order' => $this->order,
            'direction' => $this->direction,
        ];
    }

    public function queryParams(): array
    {
        $params = [];

        if ($this->q !== '') {
            $params['q'] = $this->q;
        }

        if ($this->type !== '') {
            $params['type'] = $this->type;
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
