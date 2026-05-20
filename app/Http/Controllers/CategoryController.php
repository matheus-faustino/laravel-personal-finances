<?php

namespace App\Http\Controllers;

use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Interfaces\CategoryServiceInterface;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryServiceInterface $categoryService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->categoryService->getAll());
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json($category);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        Gate::authorize('manage-categories');

        $category = $this->categoryService->create($request->validated());

        return response()->json($category, 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        Gate::authorize('manage-categories');

        return response()->json($this->categoryService->update($category, $request->validated()));
    }

    public function destroy(Category $category): JsonResponse
    {
        Gate::authorize('manage-categories');

        $this->categoryService->delete($category);

        return response()->json(null, 204);
    }
}
