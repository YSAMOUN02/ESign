<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('original_name');
            $table->string('original_path');
            $table->string('signed_path')->nullable();
            $table->string('original_hash', 64);
            $table->string('signed_hash', 64)->nullable();
            $table->unsignedInteger('page_count')->default(0);
            $table->boolean('sequential')->default(false);
            $table->enum('status', ['draft', 'sent', 'completed', 'declined', 'voided'])->default('draft')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->unsignedInteger('order')->default(1);
            $table->string('token', 64)->unique();
            $table->enum('status', ['pending', 'viewed', 'signed', 'declined'])->default('pending');
            $table->text('decline_reason')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'email']);
        });

        Schema::create('document_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signer_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['signature', 'initials', 'date', 'text', 'name']);
            $table->unsignedInteger('page');
            // Position & size as a fraction (0..1) of the page width / height.
            $table->decimal('x', 8, 6);
            $table->decimal('y', 8, 6);
            $table->decimal('width', 8, 6);
            $table->decimal('height', 8, 6);
            $table->boolean('required')->default(true);
            $table->text('value')->nullable(); // text value, or storage path to signature PNG
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('description');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('document_fields');
        Schema::dropIfExists('signers');
        Schema::dropIfExists('documents');
    }
};
