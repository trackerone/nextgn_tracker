# API Contract

The `packages/api-contract` workspace is the single source of truth for frontend TypeScript API types.

## Goals

- Keep shared DTOs in one place.
- Let frontend code import types directly via a workspace dependency.
- Reduce drift between backend JSON responses and frontend expectations.

## Usage

Import types from the workspace package:

```ts
import type { TorrentDto } from '@nextgn/api-contract';
```

As backend endpoints evolve, update the DTOs here first and then adjust frontend usage.
