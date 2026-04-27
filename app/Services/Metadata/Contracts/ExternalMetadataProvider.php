<?php

declare(strict_types=1);

namespace App\Services\Metadata\Contracts;

use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;

interface ExternalMetadataProvider
{
    public function providerKey(): string;

    public function supports(ExternalMetadataLookup $lookup): bool;

    public function lookup(ExternalMetadataLookup $lookup): ExternalMetadataResult;
}
