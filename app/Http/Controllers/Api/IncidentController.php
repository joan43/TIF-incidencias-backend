<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IncidentController extends Controller
{
    /** Categorías disponibles para el formulario de alta. */
    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Lista incidencias con filtros. Los usuarios solo consultan las propias;
     * los técnicos pueden consultar todas.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(Incident::STATUSES)],
            'estado' => ['sometimes', 'nullable', Rule::in(Incident::STATUSES)],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'categoria_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $query = Incident::query()->with($this->listRelations());

        if ($user->role !== 'tecnico') {
            $query->where('user_id', $user->id);
        }

        $status = $filters['status'] ?? $filters['estado'] ?? null;
        if ($status) {
            $query->where('status', $status);
        }

        $categoryId = $filters['category_id'] ?? $filters['categoria_id'] ?? null;
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        } elseif (! empty($filters['category'])) {
            $category = trim($filters['category']);
            if (ctype_digit($category)) {
                $query->where('category_id', (int) $category);
            } else {
                $query->whereHas('category', function (Builder $categoryQuery) use ($category): void {
                    $categoryQuery->whereRaw('LOWER(name) = ?', [mb_strtolower($category)]);
                });
            }
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

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

    /** Crea una incidencia siempre con estado ABIERTA y creador autenticado. */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeIncidentInput($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['required', 'string', 'min:5', 'max:10000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $incident = Incident::query()->create([
            'title' => trim($validated['title']),
            'description' => trim($validated['description']),
            'category_id' => $validated['category_id'],
            'user_id' => $request->user()->id,
            'technician_id' => null,
            'status' => Incident::STATUS_OPEN,
            'resolution' => null,
        ]);

        $incident->load($this->detailRelations());

        return response()->json([
            'message' => 'Incidencia creada correctamente.',
            'data' => $incident,
        ], 201);
    }

    /** Devuelve el detalle completo, incluidos comentarios cronológicos. */
    public function show(Request $request, int $id): JsonResponse
    {
        $incident = Incident::query()->with($this->detailRelations())->findOrFail($id);

        if (! $this->canView($request, $incident)) {
            return $this->notFoundForUser();
        }

        return response()->json(['data' => $incident]);
    }

    /** Solo un técnico puede tomar una incidencia que todavía esté ABIERTA. */
    public function tomar(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'tecnico') {
            return $this->forbidden('Solo un técnico puede tomar una incidencia.');
        }

        $takenId = DB::transaction(function () use ($id, $request): ?int {
            $incident = Incident::query()->lockForUpdate()->findOrFail($id);

            if ($incident->status !== Incident::STATUS_OPEN) {
                return null;
            }

            $incident->update([
                'technician_id' => $request->user()->id,
                'status' => Incident::STATUS_IN_PROGRESS,
            ]);

            return $incident->id;
        });

        if ($takenId === null) {
            return response()->json([
                'message' => 'La incidencia no está ABIERTA y no puede tomarse.',
            ], 409);
        }

        $incident = Incident::query()->with($this->detailRelations())->findOrFail($takenId);

        return response()->json([
            'message' => 'Incidencia asignada al técnico autenticado.',
            'data' => $incident,
        ]);
    }

    /** Solo el técnico asignado puede resolver una incidencia en proceso. */
    public function resolver(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'tecnico') {
            return $this->forbidden('Solo un técnico puede resolver una incidencia.');
        }

        if (! $request->exists('resolution') && $request->exists('resolucion')) {
            $request->merge(['resolution' => $request->input('resolucion')]);
        }

        $validated = $request->validate([
            'resolution' => ['required', 'string', 'min:5', 'max:10000'],
        ]);

        $result = DB::transaction(function () use ($id, $request, $validated): string {
            $incident = Incident::query()->lockForUpdate()->findOrFail($id);

            if ($incident->technician_id !== $request->user()->id) {
                return 'not_assigned';
            }

            if ($incident->status !== Incident::STATUS_IN_PROGRESS) {
                return 'invalid_status';
            }

            $incident->update([
                'resolution' => trim($validated['resolution']),
                'status' => Incident::STATUS_RESOLVED,
            ]);

            return 'resolved';
        });

        if ($result === 'not_assigned') {
            return $this->forbidden('Solo el técnico asignado puede resolver esta incidencia.');
        }

        if ($result === 'invalid_status') {
            return response()->json([
                'message' => 'Solo se puede resolver una incidencia que esté EN PROCESO.',
            ], 409);
        }

        $incident = Incident::query()->with($this->detailRelations())->findOrFail($id);

        return response()->json([
            'message' => 'Incidencia resuelta correctamente.',
            'data' => $incident,
        ]);
    }

    /** Lista comentarios en orden cronológico y respeta la visibilidad de la incidencia. */
    public function getComments(Request $request, int $id): JsonResponse
    {
        $incident = Incident::query()->findOrFail($id);

        if (! $this->canView($request, $incident)) {
            return $this->notFoundForUser();
        }

        $comments = Comment::query()
            ->with('user:id,name,email,role')
            ->where('incident_id', $incident->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $comments]);
    }

    /** Cualquier técnico puede comentar; un usuario común, solo en sus incidencias. */
    public function addComment(Request $request, int $id): JsonResponse
    {
        $incident = Incident::query()->findOrFail($id);

        if (! $this->canView($request, $incident)) {
            return $this->notFoundForUser();
        }

        if (! $request->exists('comment') && $request->exists('comentario')) {
            $request->merge(['comment' => $request->input('comentario')]);
        }

        $validated = $request->validate([
            'comment' => ['required', 'string', 'min:1', 'max:3000'],
        ]);

        $comment = Comment::query()->create([
            'incident_id' => $incident->id,
            'user_id' => $request->user()->id,
            'comment' => trim($validated['comment']),
        ]);
        $comment->load('user:id,name,email,role');

        return response()->json([
            'message' => 'Comentario agregado correctamente.',
            'data' => $comment,
        ], 201);
    }

    /** Relaciones livianas para el listado, sin cargar todos los comentarios. */
    private function listRelations(): array
    {
        return [
            'category:id,name',
            'user:id,name,email,role',
            'technician:id,name,email,role',
        ];
    }

    /** Relaciones completas del detalle, con comentarios cronológicos y sus autores. */
    private function detailRelations(): array
    {
        return array_merge($this->listRelations(), [
            'comments' => fn ($query) => $query
                ->with('user:id,name,email,role')
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);
    }

    private function canView(Request $request, Incident $incident): bool
    {
        $user = $request->user();

        return $user->role === 'tecnico' || $incident->user_id === $user->id;
    }

    private function normalizeIncidentInput(Request $request): void
    {
        $aliases = [
            'titulo' => 'title',
            'descripcion' => 'description',
            'categoria_id' => 'category_id',
        ];

        foreach ($aliases as $source => $target) {
            if (! $request->exists($target) && $request->exists($source)) {
                $request->merge([$target => $request->input($source)]);
            }
        }
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 403);
    }

    private function notFoundForUser(): JsonResponse
    {
        // Evita confirmar la existencia de incidencias ajenas a un usuario común.
        return response()->json(['message' => 'Incidencia no encontrada.'], 404);
    }
}

