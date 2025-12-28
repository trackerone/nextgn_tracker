# Architecture: Variant B (A-compatible)

## Current state

Variant B introduces a dedicated frontend application alongside the existing Laravel backend. The monorepo now contains:

- `apps/web`: Vite + TypeScript frontend (React) for UI delivery.
- `packages/api-contract`: Shared TypeScript contracts for API DTOs.
- Laravel backend remains in the repository root.

## Why Variant B

Variant B keeps the backend in place while allowing independent frontend development. It establishes a shared contract package to minimize drift between backend responses and frontend expectations.

## Path to Variant A

Variant A can later split the backend into its own repository while preserving:

- The `apps/web` frontend structure.
- The `packages/api-contract` contract package, which can move to its own repo or become a published package.

The current directory layout and workspace tooling are designed so that the frontend and contract package can be extracted without refactoring application code.
