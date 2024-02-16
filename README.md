# Fast Paginate for Laravel

## About

This is a fast `limit`/`offset` pagination macro for Laravel. It can be used in place of the standard `paginate`
methods.

This package uses a SQL method similar to a "deferred join" to achieve this speedup. A deferred join is a technique that
defers access to requested columns until _after_ the `offset` and `limit` have been applied.

In our case we don't actually do a join, but rather a `where in` with a subquery. Using this technique we create a
subquery that can be optimized with specific indexes for maximum speed and then use those results to fetch the full
rows.

The SQL looks something like this:

```sql
select *
from contacts -- The full data that you want to show your users.
where contacts.id in ( -- The "deferred join" or subquery, in our case.
    select id
    from contacts -- The pagination, accessing as little data as possible - ID only.
             limit 15
offset 150000 )
```

> You might get an error trying to run the query above! Something
> like `This version of MySQL doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery.`
> In this package, we run them as [two
_separate_ queries](https://github.com/hammerstonedev/fast-paginate/blob/154da286f8160a9e75e64e8025b0da682aa2ba23/src/BuilderMixin.php#L62-L79)
> to get around that!

The benefits can vary based on your dataset, but this method allows the database to examine as little data as possible
to satisfy the user's intent.

It's unlikely that this method will ever perform worse than traditional `offset` / `limit`, although it is possible, so
be
sure to test on your data!

> If you want to read 3,000 words on the theory of this package, you can head over
> to [aaronfrancis.com/2022/efficient-pagination-using-deferred-joins](https://aaronfrancis.com/2022/efficient-pagination-using-deferred-joins).

## Installation

This package supports Laravel 8, 9, and 10. (Laravel 8 must be 8.37 or higher.)

To install, require the package via composer:

```
composer require hammerstone/fast-paginate
```

There is nothing further you need to do. The service provider will be loaded automatically by Laravel.

## Usage

Anywhere you would use `Model::query()->paginate()`, you can use `Model::query()->fastPaginate()`! That's it!
Optionally, you can provide `$options` array as last argument.

Relationships are supported as well:

```php
User::first()->posts()->fastPaginate();
```

### Options

Pass an options array as the last argument to control the pagination behavior:

```php
$options = [
    'should_omit_wheres' => true,
    'should_omit_joins' => true,
];

User::query()->fastPaginate(null, ['*'], 'page', null, $options);

// Or, using PHP 8 named arguments:
User::query()->fastPaginate(options: $options);
```

#### Where and Join Statements

The package uses all provided `where` constraints to retrieve a list of IDs. These constraints are omitted for the subsequent query that retrieves the actual data. For queries with `join` clauses, the package preserves all provided `where` conditions.

Here are some SQL queries to illustrate this behavior:

```sql
-- Query to retrieve list of IDs
select `users`.`id`
from `users`
where `users`.`name` like '%Person%' limit 15
offset 0

-- Omit where constraints in subsequent query
select *
from `users`
where `users`.`id` in (1, 2, 3) limit 15
offset 0


-- Query to retrieve list of IDs with join clause
select `users`.`id`
from `users`
         left join `posts` on `posts`.`user_id` = `users`.`id`
where `users`.`name` like '%Person%' limit 15
offset 0

-- Preserve where constraints in subsequent query
select *
from `users`
         left join `posts` on `posts`.`user_id` = `users`.`id`
where `users`.`name` like '%Person%'
  and `users`.`id` in (1, 2, 3) limit 15
offset 0
```

This behavior can be controlled with the following options:

| Option key               | Type | Default | Description                                                                                                     |
|--------------------------|------|---------|-----------------------------------------------------------------------------------------------------------------|
| `should_preserve_wheres` | bool | `false` | Set this option to `true` to preserve all where conditions in the subsequent query.                             |
| `should_omit_wheres`     | bool | `false` | Set this option to `true` to remove all where conditions in the subsequent query, even if it has a join clause. |
| `should_omit_joins`      | bool | `false` | Set this option to `true` to remove all join conditions from the subsequent query.                              |

Please remember to test your queries after modifying these options.

## A Favor

If this helps you, please [tweet at me](https://twitter.com/aarondfrancis) with before and after times! I'd love to
know :D

Some community results so far:

* [30 seconds --> 250ms](https://twitter.com/mdavis1982/status/1482429071288066054)
* [28 seconds --> 2 seconds](https://twitter.com/joecampo/status/1483550610028957701)
* [7.5x faster](https://twitter.com/max_eckel/status/1483764319372333057)
* [1.1 seconds --> 0.1 seconds](https://twitter.com/max_eckel/status/1483852300414337032)
* [20 seconds --> 2 seconds](https://twitter.com/1ralphmorris/status/1484242437618941957)
* [2 seconds --> .2 seconds](https://twitter.com/julioelpoeta/status/1549524738980077568) 
