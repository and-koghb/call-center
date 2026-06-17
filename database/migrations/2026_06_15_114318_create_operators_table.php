<?php

use App\Enums\OperatorStatus;
use App\Models\Operator;
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
        if (!Schema::hasTable('operators')) {
            Schema::create('operators', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->tinyInteger('status')->default(OperatorStatus::OFFLINE)->index();
                $table->timestamp('last_call_at')->nullable();
                $table->timestamps();
                $table->timestamp('deleted_at')->default(Operator::getLiveTimestamp());

                $table->unique(['user_id', 'deleted_at']);
                $table->index(['status', 'deleted_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operators');
    }
};
