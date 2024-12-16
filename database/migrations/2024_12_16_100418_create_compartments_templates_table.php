<?php

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
        Schema::create('compartments_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('data');
            $table->timestamps();
        });

        \App\Models\CompartmentsTemplate::create([
            'name' => 'VN-SHELF-02',
            'data' => [
                [
                    'volume_class' => 'C',
                    'width' => 79.5,
                    'height' => 78,
                    'depth' => 113,
                    'is_truck' => 0,
                    'is_movable' => 1,
                    'is_walk_through' => 0,
                    'is_manual' => 0
                ],
                [
                    'volume_class' => 'C',
                    'width' => 79.5,
                    'height' => 95,
                    'depth' => 113,
                    'is_truck' => 0,
                    'is_movable' => 1,
                    'is_walk_through' => 0,
                    'is_manual' => 0
                ]
            ]
        ]);

        \App\Models\CompartmentsTemplate::create([
            'name' => 'VN-SHELF-03',
            'data' => [
                [
                    'volume_class' => 'C',
                    'width' => 79.5,
                    'height' => 78,
                    'depth' => 113,
                    'is_truck' => 0,
                    'is_movable' => 1,
                    'is_walk_through' => 0,
                    'is_manual' => 0
                ],
                [
                    'volume_class' => 'B',
                    'width' => 79.5,
                    'height' => 68,
                    'depth' => 113,
                    'is_truck' => 0,
                    'is_movable' => 1,
                    'is_walk_through' => 0,
                    'is_manual' => 0
                ],
                [
                    'volume_class' => 'A',
                    'width' => 79.5,
                    'height' => 24.5,
                    'depth' => 113,
                    'is_truck' => 0,
                    'is_movable' => 1,
                    'is_walk_through' => 0,
                    'is_manual' => 0
                ]
            ]
        ]);

        \App\Models\CompartmentsTemplate::create([
            'name' => 'VN-SHELF-04',
            'data' => [
                [
                    'volume_class' => 'C',
                    'width' => 79.5,
                    'height' => 78,
                    'depth' => 113,
                    'is_truck' => 0,
                    'is_movable' => 1,
                    'is_walk_through' => 0,
                    'is_manual' => 0
                ],
                [
                    'volume_class' => 'B',
                    'width' => 79.5,
                    'height' => 38,
                    'depth' => 113,
                    'is_truck' => 0,
                    'is_movable' => 1,
                    'is_walk_through' => 0,
                    'is_manual' => 0
                ],
                [
                    'volume_class' => 'A',
                    'width' => 79.5,
                    'height' => 28,
                    'depth' => 113,
                    'is_truck' => 0,
                    'is_movable' => 1,
                    'is_walk_through' => 0,
                    'is_manual' => 0
                ],
                [
                    'volume_class' => 'A',
                    'width' => 79.5,
                    'height' => 24.5,
                    'depth' => 113,
                    'is_truck' => 0,
                    'is_movable' => 1,
                    'is_walk_through' => 0,
                    'is_manual' => 0
                ]
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compartments_templates');
    }
};
