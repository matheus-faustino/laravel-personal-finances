<?php

namespace App\Services;

use App\Interfaces\CategoryServiceInterface;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryService implements CategoryServiceInterface
{
    /** {@inheritDoc} */
    public function getAll(): Collection
    {
        return Category::all();
    }

    /** {@inheritDoc} */
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    /** {@inheritDoc} */
    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category;
    }

    /** {@inheritDoc} */
    public function delete(Category $category): void
    {
        $category->delete();
    }
}
