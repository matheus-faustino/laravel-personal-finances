<?php

namespace App\Interfaces;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryServiceInterface
{
    public function index(): Collection;

    public function show(Category $category): Category;

    public function store(array $data): Category;

    public function update(Category $category, array $data): Category;

    public function destroy(Category $category): void;
}
