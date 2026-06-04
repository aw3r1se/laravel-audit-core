<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Tests;

use Aw3r1se\Audit\AuditServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [AuditServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('authors', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('body')->nullable();
            $table->string('secret')->nullable();
            $table->foreignId('author_id')->nullable();
            $table->timestamps();
        });

        Schema::create('post_tag', function (Blueprint $table): void {
            $table->foreignId('post_id');
            $table->foreignId('tag_id');
            $table->string('note')->nullable();
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->nullable();
            $table->string('body')->nullable();
            $table->timestamps();
        });
    }
}
