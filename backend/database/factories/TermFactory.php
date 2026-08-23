<?php

namespace Database\Factories;

use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    protected $model = Term::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'name_en' => 'First Term',
            'name_ar' => 'الفصل الدراسي الأول',
            // Not ->unique() -- the real constraint is composite
            // (academic_year_id, sequence_order); each factory call gets
            // its own independent AcademicYear by default, matching
            // SectionFactory's own reasoning.
            'sequence_order' => 1,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->startOfYear()->addMonths(4),
            'status' => Term::STATUS_UPCOMING,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Term::STATUS_ACTIVE]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Term::STATUS_CLOSED]);
    }

    public function ordered(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sequence_order' => $order,
            'name_en' => match ($order) {
                1 => 'First Term',
                2 => 'Second Term',
                3 => 'Third Term',
                default => "Term {$order}",
            },
            'name_ar' => match ($order) {
                1 => 'الفصل الدراسي الأول',
                2 => 'الفصل الدراسي الثاني',
                3 => 'الفصل الدراسي الثالث',
                default => "الفصل {$order}",
            },
        ]);
    }
}
