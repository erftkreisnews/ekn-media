<?php

namespace App\Support;

use App\Models\Brand;

class BrandContext
{
    public function currentBrand(): ?Brand
    {
        $brand = app()->bound('currentBrand') ? app('currentBrand') : null;

        return $brand instanceof Brand ? $brand : null;
    }

    public function currentBrandId(): ?int
    {
        return $this->currentBrand()?->id;
    }

    public function is(string $brandKey): bool
    {
        return $this->currentBrand()?->key === $brandKey;
    }
}
