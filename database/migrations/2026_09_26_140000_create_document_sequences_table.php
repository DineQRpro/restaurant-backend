<?php

use App\Models\DocumentSequence;
use App\Services\Document\DocumentNumberService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50)->unique();
            $table->unsignedBigInteger('next_number');
            $table->timestamps();
        });

        $service = app(DocumentNumberService::class);
        $now = now();

        DB::table('document_sequences')->insert([
            [
                'name' => DocumentSequence::ORDER,
                'next_number' => $service->initialNextNumber(DocumentSequence::ORDER),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => DocumentSequence::BILL,
                'next_number' => $service->initialNextNumber(DocumentSequence::BILL),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
