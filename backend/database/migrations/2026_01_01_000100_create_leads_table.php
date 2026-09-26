<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead store for the API.
 *
 * Lives alongside the WordPress `safari_leads` table in the same database but
 * under the backend's own table prefix, so the two applications never share a
 * row and neither migration can touch the other's tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('status', 20)->default('new')->index();
            $table->string('name', 190);
            $table->string('email', 190)->index();
            $table->string('phone', 50)->nullable();

            $table->unsignedBigInteger('destination_id')->nullable()->index();
            $table->unsignedBigInteger('tour_id')->nullable();
            $table->string('destination_text', 190)->nullable();
            $table->string('travel_style', 100)->nullable();

            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->boolean('dates_flexible')->default(false);
            $table->unsignedSmallInteger('adults')->nullable();
            $table->unsignedSmallInteger('children')->nullable();
            $table->string('budget_range', 50)->nullable();

            $table->string('subject', 190)->nullable();
            $table->text('message')->nullable();

            $table->string('source_form', 50)->default('contact')->index();
            $table->string('source_url', 500)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('referrer', 500)->nullable();

            $table->boolean('consent_privacy')->default(false);
            $table->boolean('consent_marketing')->default(false);

            // SHA-256 of the visitor IP, salted. The raw address is never stored.
            $table->char('ip_hash', 64)->nullable()->index();

            $table->foreignId('assigned_to')->nullable()->index();

            // Set when this lead is mirrored into the WordPress plugin's table.
            $table->unsignedBigInteger('wordpress_lead_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
