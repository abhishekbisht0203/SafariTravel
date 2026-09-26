<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sanctum personal access tokens.
 *
 * Published from laravel/sanctum, then adjusted in one place: the framework
 * derives index names from the (prefixed) table name, and MySQL caps identifiers
 * at 64 characters. With the backend's own `safari_api_` prefix the generated
 * morph index came out at 65 characters and the migration failed. The columns
 * and index names are therefore declared explicitly, which keeps the schema
 * correct for any prefix length.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();

            // Equivalent to $table->morphs('tokenable'), with a short index name.
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->index(['tokenable_type', 'tokenable_id'], 'safari_api_tokenable_index');

            $table->text('name');
            $table->string('token', 64)->unique('safari_api_pat_token_unique');
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->index('expires_at', 'safari_api_pat_expires_index');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
