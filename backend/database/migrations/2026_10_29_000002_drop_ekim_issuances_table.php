<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ekim_issuances');
    }

    public function down(): void
    {
        // Fitur penerbitan E-KIM terpisah telah dikeluarkan dari ruang lingkup.
    }
};
