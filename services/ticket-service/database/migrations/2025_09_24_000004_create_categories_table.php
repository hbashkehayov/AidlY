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
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->uuid('parent_category_id')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Self-referencing foreign key
            $table->foreign('parent_category_id')->references('id')->on('categories');

            // Indexes
            $table->index('parent_category_id');
            $table->index('is_active');
            $table->index('display_order');
        });

        // Insert some default categories
        DB::table('categories')->insert([
            [
                'id' => DB::raw('gen_random_uuid()'),
                'name' => 'Technical Support',
                'description' => 'Technical issues and troubleshooting',
                'icon' => 'wrench',
                'color' => '#3B82F6',
                'display_order' => 1,
                'created_at' => now()
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'name' => 'Billing',
                'description' => 'Billing and payment related inquiries',
                'icon' => 'credit-card',
                'color' => '#10B981',
                'display_order' => 2,
                'created_at' => now()
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'name' => 'Feature Request',
                'description' => 'New feature requests and suggestions',
                'icon' => 'lightbulb',
                'color' => '#F59E0B',
                'display_order' => 3,
                'created_at' => now()
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'name' => 'Bug Report',
                'description' => 'Software bugs and issues',
                'icon' => 'bug',
                'color' => '#EF4444',
                'display_order' => 4,
                'created_at' => now()
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'name' => 'General Inquiry',
                'description' => 'General questions and inquiries',
                'icon' => 'chat',
                'color' => '#6B7280',
                'display_order' => 5,
                'created_at' => now()
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};