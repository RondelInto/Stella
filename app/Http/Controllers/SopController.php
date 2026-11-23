<?php

namespace App\Http\Controllers;

use App\Http\Requests\SopRequest;
use App\Models\Role;
use App\Models\SopRecord;
use App\Models\SopVersion;
use App\Services\SopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * SOP Library (Module 9).
 *  - View: 'view-sops' + SopRecordPolicy (staff see only their roles' active, effective SOPs).
 *  - Manage: 'manage-sops' (Management).
 */
class SopController extends Controller
{
    public function __construct(private SopService $sops)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $canManage = $user->can('manage-sops');
        $filters = $request->only(['search', 'category', 'status', 'role']);

        $sops = SopRecord::query()
            ->visibleTo($user)
            ->with('roles')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$s}%")
                ->orWhere('sop_code', 'like', "%{$s}%")
                ->orWhere('description', 'like', "%{$s}%")))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($canManage && ($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when($canManage && ($filters['role'] ?? null), fn ($q) => $q->whereHas('roles', fn ($q) => $q->where('roles.id', $filters['role'])))
            ->orderBy('category')
            ->orderBy('title')
            ->paginate(20)
            ->withQueryString();

        return view('sops.index', [
            'sops' => $sops,
            'filters' => $filters,
            'canManage' => $canManage,
            'categories' => SopRecord::query()->visibleTo($user)->distinct()->orderBy('category')->pluck('category'),
            'roles' => Role::orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('sops.create', $this->formOptions());
    }

    public function store(SopRequest $request): RedirectResponse
    {
        $sop = $this->sops->create($request->user(), $request->sopData(), $request->validated('roles'));

        return redirect()->route('sops.show', $sop)->with('success', "SOP {$sop->sop_code} was created as ".strtoupper($sop->status).'.');
    }

    public function show(SopRecord $sop): View
    {
        Gate::authorize('view', $sop);

        $sop->load(['roles', 'createdBy', 'updatedBy']);

        return view('sops.show', [
            'sop' => $sop,
            'versions' => Gate::allows('manage-sops') ? $sop->versions()->with('changedBy')->get() : collect(),
        ]);
    }

    public function edit(SopRecord $sop): View
    {
        return view('sops.edit', ['sop' => $sop->load('roles')] + $this->formOptions());
    }

    public function update(SopRequest $request, SopRecord $sop): RedirectResponse
    {
        $newVersion = $this->sops->update($sop, $request->user(), $request->sopData(), $request->validated('roles'));

        $message = $newVersion
            ? "SOP {$sop->sop_code} was updated to version {$sop->fresh()->version_no}. The previous version was kept in the history."
            : "SOP {$sop->sop_code} was updated.";

        return redirect()->route('sops.show', $sop)->with('success', $message);
    }

    public function updateStatus(Request $request, SopRecord $sop): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([SopRecord::STATUS_DRAFT, SopRecord::STATUS_ACTIVE, SopRecord::STATUS_ARCHIVED])],
        ]);

        $this->sops->changeStatus($sop, $request->user(), $validated['status']);

        return back()->with('success', "SOP {$sop->sop_code} is now ".strtoupper($validated['status']).'.');
    }

    /** Read-only earlier version (Management). */
    public function showVersion(SopRecord $sop, SopVersion $version): View
    {
        abort_unless($version->sop_record_id === $sop->id, 404);

        return view('sops.version', ['sop' => $sop, 'version' => $version->load('changedBy')]);
    }

    public function print(SopRecord $sop): View
    {
        Gate::authorize('view', $sop);

        return view('sops.print', ['sop' => $sop->load('roles')]);
    }

    /** Server-side preview, so it looks exactly like the saved SOP (team decision S1). */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate(['content' => ['nullable', 'string', 'max:60000']]);

        return response()->json(['html' => (string) SopRecord::render($validated['content'] ?? '')]);
    }

    private function formOptions(): array
    {
        return [
            'roles' => Role::orderBy('id')->get(),
            'categories' => SopRecord::distinct()->orderBy('category')->pluck('category'),
        ];
    }
}
