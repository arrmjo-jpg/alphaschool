<?php

use App\Core\Models\Organization;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\School;
use Illuminate\Database\QueryException;

function makeSchool(): School
{
    $organization = Organization::create(['legal_name' => 'AlphaSchool Test Co.', 'display_name' => 'AlphaSchool']);

    return School::create(['organization_id' => $organization->id, 'name_en' => 'Main School', 'name_ar' => 'المدرسة الرئيسية']);
}

it('creates a branch with a valid code', function () {
    $branch = Branch::create([
        'school_id' => makeSchool()->id,
        'code' => 'MC',
        'name_en' => 'Main Campus',
        'name_ar' => 'الحرم الرئيسي',
    ]);

    expect($branch->code)->toBe('MC')
        ->and($branch->is_active)->toBeTrue();
});

it('rejects a duplicate branch code', function () {
    $schoolId = makeSchool()->id;
    Branch::create(['school_id' => $schoolId, 'code' => 'MC', 'name_en' => 'Main Campus', 'name_ar' => 'الحرم الرئيسي']);

    Branch::create(['school_id' => $schoolId, 'code' => 'MC', 'name_en' => 'Another Campus', 'name_ar' => 'حرم آخر']);
})->throws(QueryException::class);

it('rejects a structurally invalid code', function (string $invalidCode) {
    Branch::create(['school_id' => makeSchool()->id, 'code' => $invalidCode, 'name_en' => 'Main Campus', 'name_ar' => 'الحرم الرئيسي']);
})->with([
    'lowercase' => ['mc'],
    'too short' => ['M'],
    'too long' => ['ABCDEFGHIJK'],
    'contains a space' => ['M C'],
    'contains a hyphen' => ['M-C'],
])->throws(InvalidArgumentException::class);

it('never allows the code to change once set', function () {
    $branch = Branch::create(['school_id' => makeSchool()->id, 'code' => 'MC', 'name_en' => 'Main Campus', 'name_ar' => 'الحرم الرئيسي']);

    $branch->update(['code' => 'BR2']);
})->throws(RuntimeException::class);

it('allows renaming a branch without touching its code', function () {
    $branch = Branch::create(['school_id' => makeSchool()->id, 'code' => 'MC', 'name_en' => 'Main Campus', 'name_ar' => 'الحرم الرئيسي']);

    $branch->update(['name_en' => 'Downtown Campus']);

    expect($branch->fresh()->code)->toBe('MC')
        ->and($branch->fresh()->name_en)->toBe('Downtown Campus');
});

it('deactivates rather than deletes', function () {
    $branch = Branch::create(['school_id' => makeSchool()->id, 'code' => 'MC', 'name_en' => 'Main Campus', 'name_ar' => 'الحرم الرئيسي']);

    $branch->update(['is_active' => false]);

    expect(Branch::find($branch->id))->not->toBeNull()
        ->and($branch->fresh()->is_active)->toBeFalse();
});

it('supports a nullable parent branch for future regional grouping', function () {
    $schoolId = makeSchool()->id;
    $region = Branch::create(['school_id' => $schoolId, 'code' => 'REGION1', 'name_en' => 'Region 1', 'name_ar' => 'المنطقة 1']);
    $child = Branch::create(['school_id' => $schoolId, 'parent_branch_id' => $region->id, 'code' => 'MC', 'name_en' => 'Main Campus', 'name_ar' => 'الحرم الرئيسي']);

    expect($child->parentBranch->id)->toBe($region->id)
        ->and($region->childBranches()->count())->toBe(1);
});
