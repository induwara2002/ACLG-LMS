<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('books')) {
            return;
        }

        if (! Schema::hasColumn('books', 'author_id')) {
            Schema::table('books', function (Blueprint $table) {
                $table->foreignId('author_id')->nullable()->after('title');
            });
        }

        if (! Schema::hasColumn('books', 'publisher_id')) {
            Schema::table('books', function (Blueprint $table) {
                $table->foreignId('publisher_id')->nullable()->after('author_id');
            });
        }

        if (Schema::hasColumn('books', 'author')) {
            DB::statement("INSERT INTO authors (name, created_at, updated_at)
                SELECT DISTINCT author, NOW(), NOW() FROM books
                WHERE author IS NOT NULL AND author <> ''
                AND NOT EXISTS (SELECT 1 FROM authors WHERE authors.name = books.author)");
            DB::statement("UPDATE books b INNER JOIN authors a ON a.name = b.author SET b.author_id = a.id WHERE b.author_id IS NULL");
        }

        if (Schema::hasColumn('books', 'publisher')) {
            DB::statement("INSERT INTO publishers (name, created_at, updated_at)
                SELECT DISTINCT publisher, NOW(), NOW() FROM books
                WHERE publisher IS NOT NULL AND publisher <> ''
                AND NOT EXISTS (SELECT 1 FROM publishers WHERE publishers.name = books.publisher)");
            DB::statement("UPDATE books b INNER JOIN publishers p ON p.name = b.publisher SET b.publisher_id = p.id WHERE b.publisher_id IS NULL");
        }

        if (DB::table('books')->whereNull('author_id')->exists() || DB::table('books')->whereNull('publisher_id')->exists()) {
            throw new RuntimeException('Cannot reconcile legacy books because an author or publisher cannot be mapped.');
        }

        DB::statement("ALTER TABLE books MODIFY author_id BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE books MODIFY publisher_id BIGINT UNSIGNED NOT NULL");

        if (! $this->hasForeignKey('books', 'books_author_id_foreign')) {
            Schema::table('books', function (Blueprint $table) {
                $table->foreign('author_id')->references('id')->on('authors')->restrictOnDelete();
            });
        }

        if (! $this->hasForeignKey('books', 'books_publisher_id_foreign')) {
            Schema::table('books', function (Blueprint $table) {
                $table->foreign('publisher_id')->references('id')->on('publishers')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Legacy columns and data are intentionally retained.
    }

    private function hasForeignKey(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};