<?php

use App\Enums\CallStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('calls')) {
            Schema::create('calls', function (Blueprint $table) {
                $table->id();
                $table->string('phone', 25);
                $table->foreignId('client_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
                $table->foreignId('operator_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
                $table->tinyInteger('status')->default(CallStatus::NEW)->index();
                $table->timestamps();
                $table->timestamp('finished_at')->nullable()->index();

                $table->index(['created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
