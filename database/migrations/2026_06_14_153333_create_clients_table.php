<?php

use App\Enums\ClientStatus;
use App\Models\Client;
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
        if (!Schema::hasTable('clients')) {
            Schema::create('clients', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('phone', 20);
                $table->tinyInteger('status')->default(ClientStatus::ACTIVE)->index();
                $table->timestamps();
                $table->timestamp('deleted_at')->default(Client::getLiveTimestamp());

                $table->unique(['phone', 'deleted_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
