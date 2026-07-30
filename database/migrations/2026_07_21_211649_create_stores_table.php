<?php

use App\Enums\StoreStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade')->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('tagline', 255)->nullable();
            $table->string('logo_url', 255)->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('whatsapp_number', 50);
            $table->enum('status', array_column(StoreStatusEnum::cases(), 'name'))->default(StoreStatusEnum::ACTIVE->name);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
