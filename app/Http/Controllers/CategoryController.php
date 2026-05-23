<?php

namespace App\Http\Controllers;

use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Interfaces\CategoryServiceInterface;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryServiceInterface $categoryService) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->categoryService->getAll());
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        Gate::authorize('manage-categories');

        $category = $this->categoryService->create($request->validated());

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        Gate::authorize('manage-categories');

        return new CategoryResource($this->categoryService->update($category, $request->validated()));
    }

    public function destroy(Category $category): JsonResponse
    {
        Gate::authorize('manage-categories');

        $this->categoryService->delete($category);

        return response()->json(null, 204);
    }
}
