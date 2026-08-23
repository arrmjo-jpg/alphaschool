<?php

namespace App\Modules\Academic\Http\Controllers\Concerns;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * The thin REST adapter shared by every Academic Reference Master Data
 * entity's own controller (UI Sprint 1-B, docs/ADMIN_DESIGN_SYSTEM.md
 * §28), mirroring on the backend the exact discipline §28.8 already
 * bound the frontend's shared List/Detail/Form shells to: one
 * generic implementation driven by small per-entity declarations
 * (model class, searchable/fillable fields, status shape), never a
 * bespoke controller re-implementing the same list/get/create/update/
 * setStatus shape five times. No business logic is invented here --
 * every write reuses the model's own real method where one exists
 * (AcademicYear::close()/Term::close() for the 'closed' transition),
 * matching E-B/F-B's "expose existing services verbatim" principle.
 *
 * §28.7's binding domain rule (Reference Master Data never exposes
 * Delete) is enforced structurally: no destroy()/delete route exists
 * on this base class, so a subclass cannot accidentally expose one by
 * registering a `Route::delete(...)` against it -- there is no method
 * to route to.
 *
 * Authorization uses hasPermissionTo($permission, 'sanctum') directly,
 * not can()/Gate/Policy -- Sprint 3.1's documented guard gotcha
 * (this app's default 'web' guard silently breaks can()'s permission
 * resolution) already forced the same choice in
 * WorkspaceAccessResolver and ConfigurationController, whose
 * is_super_admin-bypass-then-hasPermissionTo idiom is reproduced here
 * verbatim, not reinvented.
 */
abstract class AcademicMasterDataController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** @return list<string> columns matched by the list endpoint's `search` query param. */
    abstract protected function searchableFields(): array;

    /** The column driving the List filter and row-action label (`is_active` or `status`). */
    abstract protected function statusField(): string;

    /** @return 'boolean'|'lifecycle3' */
    abstract protected function statusType(): string;

    /**
     * @return array<string, mixed> Laravel validation rules for create/update
     *                              -- never includes the status field itself
     *                              (§28.6: status changes are a separate
     *                              action, never form-editable). $id is the
     *                              row being updated (null on create), so a
     *                              subclass can build a `Rule::unique(...)
     *                              ->ignore($id)` for its own unique
     *                              columns -- without this, a real unique
     *                              DB constraint violation surfaces as a
     *                              raw 500 QueryException instead of a
     *                              clean 422 (a real gap found and fixed
     *                              while wiring GradeLevel, UI Sprint 1-B).
     */
    abstract protected function validationRules(bool $isUpdate, int|string|null $id): array;

    /**
     * A no-op hook, overridden by an entity whose List pattern needs an
     * additional filter beyond the baseline search/status (§28.8 rule 3's
     * `extraFilters` slot, first exercised by Term's own
     * `academic_year_id` scoping, UI Sprint 1-B) -- a small, named
     * extension point rather than every entity re-implementing index()
     * from scratch to add one `where()` clause.
     */
    protected function applyExtraFilters(Builder $query, Request $request): void
    {
        //
    }

    protected function requiredPermission(): string
    {
        return 'academic.manage-catalog';
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $model = $this->modelClass();
        /** @var Builder $query */
        $query = $model::query();

        if ($search = $request->query('search')) {
            $fields = $this->searchableFields();
            $query->where(function (Builder $q) use ($fields, $search) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', '%'.$search.'%');
                }
            });
        }

        if ($status = $request->query('status')) {
            $query->where($this->statusField(), $this->statusType() === 'boolean' ? $status === 'active' : $status);
        }

        $this->applyExtraFilters($query, $request);

        if ($sort = $request->query('sort')) {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $query->orderBy(ltrim($sort, '-'), $direction);
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $paginator = $query->paginate($perPage);

        // Laravel's raw paginator->toArray() is flat (current_page/total/
        // etc. as top-level siblings of data) -- admin/src/platform/
        // data-table/types.ts's ServerPage<T> contract (already proven
        // against every other list endpoint's own queryFn shape) expects
        // data+meta nested. Shaped explicitly here rather than relying on
        // the default cast, which would silently mismatch the frontend
        // contract.
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $this->authorizeManage($request);

        return response()->json($this->modelClass()::findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate($this->validationRules(isUpdate: false, id: null));
        $instance = $this->modelClass()::create($validated);

        return response()->json($instance, 201);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $this->authorizeManage($request);

        $instance = $this->modelClass()::findOrFail($id);
        $validated = $request->validate($this->validationRules(isUpdate: true, id: $id));
        $instance->update($validated);

        return response()->json($instance);
    }

    public function setStatus(Request $request, int|string $id): JsonResponse
    {
        $this->authorizeManage($request);

        $request->validate(['status' => ['required', 'string']]);

        $instance = $this->modelClass()::findOrFail($id);
        $this->applyStatus($instance, $request->string('status')->toString());

        return response()->json($instance);
    }

    protected function applyStatus(Model $instance, string $status): void
    {
        if ($this->statusType() === 'boolean') {
            $instance->{$this->statusField()} = $status === 'active';
            $instance->save();

            return;
        }

        // lifecycle3: 'closed' reuses the model's own close() (real
        // domain behavior -- e.g. AcademicYearClosed dispatch -- never
        // a raw mass-update standing in for it). Any other transition
        // (upcoming -> active) has no dedicated method on these models
        // today, so it's a plain column write, matching what the
        // models themselves currently expose.
        if ($status === 'closed' && method_exists($instance, 'close')) {
            $instance->close();

            return;
        }

        $instance->{$this->statusField()} = $status;
        $instance->save();
    }

    protected function authorizeManage(Request $request): void
    {
        $user = $request->user();

        if ($user->is_super_admin) {
            return;
        }

        try {
            abort_unless($user->hasPermissionTo($this->requiredPermission(), 'sanctum'), 403);
        } catch (PermissionDoesNotExist) {
            abort(403);
        }
    }
}
