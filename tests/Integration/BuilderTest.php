<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace Hammerstone\Sidecar\Tests\Integration;

use Hammerstone\FastPaginate\Tests\Support\User;

class BuilderTest extends BaseTest
{
    /** @test */
    public function basic_test()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            $results = User::query()->fastPaginate();
        });

        $this->assertEquals(15, $results->count());
        $this->assertEquals('Person 15', $results->last()->name);
        $this->assertCount(3, $queries);

        $this->assertEquals(
            'select * from "users" where "users"."id" in (1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15) limit 16 offset 0',
            $queries[2]['query']
        );
    }

    /** @test */
    public function different_page_size()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            $results = User::query()->fastPaginate(5);
        });

        $this->assertEquals(5, $results->count());

        $this->assertEquals(
            'select * from "users" where "users"."id" in (1, 2, 3, 4, 5) limit 6 offset 0',
            $queries[2]['query']
        );
    }

    /** @test */
    public function page_2()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            $results = User::query()->fastPaginate(5, ['*'], 'page', 2);
        });

        $this->assertEquals(5, $results->count());

        $this->assertEquals(
            'select * from "users" where "users"."id" in (6, 7, 8, 9, 10) limit 6 offset 0',
            $queries[2]['query']
        );
    }

    /** @test */
    public function order_is_propagated()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            $results = User::query()->orderBy('name')->fastPaginate(5);
        });

        $this->assertEquals(
            'select * from "users" where "users"."id" in (1, 10, 11, 12, 13) order by "name" asc limit 6 offset 0',
            $queries[2]['query']
        );
    }

    /** @test */
    public function eager_loads_are_cleared_on_inner_query()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            $results = User::query()->with('posts')->fastPaginate(5);
        });

        // If we didn't clear the eager loads, there would be 5 queries.
        $this->assertCount(4, $queries);

        // The eager load should come last, after the outer query has run.
        $this->assertEquals(
            'select * from "posts" where "posts"."user_id" in (1, 2, 3, 4, 5)',
            $queries[3]['query']
        );
    }

    /** @test */
    public function eager_loads_are_loaded_on_outer_query()
    {
        $this->withQueriesLogged(function () use (&$results) {
            $results = User::query()->with('posts')->fastPaginate();
        });

        $this->assertTrue($results->first()->relationLoaded('posts'));
        $this->assertEquals(1, $results->first()->posts->count());
    }
}