<?php

namespace App\Interfaces;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryServiceInterface
{
    /**
     * Returns all categories.
     *
     * @return Collection<int, Category>
     */
    public function getAll(): Collection;

    /**
     * Creates and persists a new category with the given data.
     *
     * @param  array{name: string, description?: string|null}  $data
     */
    public function create(array $data): Category;

    /**
     * Updates the given category with the provided data.
     *
     * @param  array{name: string, description?: string|null}  $data
     */
    public function update(Category $category, array $data): Category;

    /**
     * Deletes the given category.
     */
    public function delete(Category $category): void;
}
