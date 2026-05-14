<?php

namespace Tests\Unit\Category;

use App\Interfaces\CategoryServiceInterface;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CategoryServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(CategoryServiceInterface::class);
    }

    public function test_index_returns_all_categories(): void
    {
        Category::factory()->count(3)->create();

        $result = $this->service->index();

        $this->assertCount(3, $result);
    }

    public function test_index_returns_empty_collection_when_no_categories_exist(): void
    {
        $result = $this->service->index();

        $this->assertCount(0, $result);
    }

    public function test_show_returns_the_given_category(): void
    {
        $category = Category::factory()->create();

        $result = $this->service->show($category);

        $this->assertTrue($result->is($category));
    }

    public function test_store_creates_and_returns_a_category(): void
    {
        $result = $this->service->store(['name' => 'Electronics']);

        $this->assertInstanceOf(Category::class, $result);
        $this->assertSame('Electronics', $result->name);
        $this->assertDatabaseHas('categories', ['name' => 'Electronics']);
    }

    public function test_update_changes_the_category_name(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name']);

        $result = $this->service->update($category, ['name' => 'New Name']);

        $this->assertSame('New Name', $result->name);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    public function test_update_returns_the_updated_category(): void
    {
        $category = Category::factory()->create();

        $result = $this->service->update($category, ['name' => 'Updated']);

        $this->assertTrue($result->is($category));
    }

    public function test_destroy_deletes_the_category(): void
    {
        $category = Category::factory()->create();

        $this->service->destroy($category);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
