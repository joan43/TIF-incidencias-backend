<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compatibilidad con instalaciones anteriores: las migraciones originales
 * creaban incidents solo con id/fechas y comments con una columna name.
 * En instalaciones nuevas estas columnas ya existen y no se duplican.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('incidents') || ! Schema::hasTable('comments')) {
            return;
        }

        $missingIncidentFields = collect([
            'title', 'description', 'status', 'user_id', 'technician_id',
            'category_id', 'resolution',
        ])->contains(fn (string $column): bool => ! Schema::hasColumn('incidents', $column));

        if ($missingIncidentFields) {
            Schema::table('incidents', function (Blueprint $table): void {
                if (! Schema::hasColumn('incidents', 'title')) {
                    $table->string('title', 180)->nullable();
                }
                if (! Schema::hasColumn('incidents', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('incidents', 'status')) {
                    $table->string('status', 30)->default('ABIERTA')->index();
                }
                if (! Schema::hasColumn('incidents', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('incidents', 'technician_id')) {
                    $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('incidents', 'category_id')) {
                    $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
                }
                if (! Schema::hasColumn('incidents', 'resolution')) {
                    $table->text('resolution')->nullable();
                }
            });
        }

        $missingCommentFields = collect(['incident_id', 'user_id', 'comment'])
            ->contains(fn (string $column): bool => ! Schema::hasColumn('comments', $column));

        if ($missingCommentFields) {
            Schema::table('comments', function (Blueprint $table): void {
                if (! Schema::hasColumn('comments', 'incident_id')) {
                    $table->foreignId('incident_id')->nullable()->constrained('incidents')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('comments', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('comments', 'comment')) {
                    $table->text('comment')->nullable();
                }
            });
        }

        if (Schema::hasColumn('comments', 'name')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        }
    }

    public function down(): void
    {
        // Migración de reparación no destructiva; no se revierte para evitar perder datos.
    }
};
