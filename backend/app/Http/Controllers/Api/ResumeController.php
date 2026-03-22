<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resume;
use App\Services\ResumeParserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResumeController extends Controller
{
    public function __construct(private readonly ResumeParserService $parser) {}

    /**
     * GET /api/resumes — 列表，支持结构化过滤+分页。
     */
    public function index(Request $request): JsonResponse
    {
        $query = Resume::with('createdBy:id,name')
            ->where('organization_id', 1);

        if ($request->filled('status')) {
            $query->where('status', $request->integer('status'));
        }

        if ($request->filled('work_type')) {
            $query->whereRaw('JSON_CONTAINS(work_types, ?)', [json_encode($request->input('work_type'))]);
        }

        if ($request->filled('district')) {
            $query->whereRaw('JSON_CONTAINS(districts, ?)', [json_encode($request->input('district'))]);
        }

        if ($request->filled('position')) {
            $query->whereRaw('JSON_CONTAINS(positions, ?)', [json_encode($request->input('position'))]);
        }

        $resumes = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($resumes);
    }

    /**
     * POST /api/resumes/parse — 解析文字/图片，返回结构化数据（不保存）。
     */
    public function parse(Request $request): JsonResponse
    {
        $request->validate([
            'text'         => 'required|string|max:5000',
            'image_base64' => 'nullable|string',
        ]);

        $parsed = $this->parser->parseResume(
            $request->input('text'),
            $request->input('image_base64')
        );

        return response()->json(['data' => $parsed]);
    }

    /**
     * POST /api/resumes — 保存一份简历。
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'nullable|string|max:100',
            'phone'             => 'nullable|string|max:20',
            'gender'            => 'nullable|integer|in:0,1,2',
            'age'               => 'nullable|integer|min:15|max:99',
            'districts'         => 'nullable|array',
            'work_types'        => 'nullable|array',
            'positions'         => 'nullable|array',
            'experience_years'  => 'nullable|numeric|min:0|max:50',
            'salary_min'        => 'nullable|integer|min:0',
            'salary_max'        => 'nullable|integer|min:0',
            'salary_unit'       => 'nullable|integer|in:1,2,3',
            'education'         => 'nullable|integer|in:1,2,3,4',
            'availability_date' => 'nullable|date',
            'languages'         => 'nullable|array',
            'skills'            => 'nullable|array',
            'raw_text'          => 'nullable|string|max:10000',
            'source'            => 'nullable|integer|in:1,2,3',
            'status'            => 'nullable|integer|in:0,1,2,3',
            'notes'             => 'nullable|string|max:2000',
        ]);

        $resume = Resume::create(array_merge($data, [
            'organization_id' => 1,
            'created_by'      => $request->user()->id,
            'source'          => $data['source'] ?? 2,
        ]));

        return response()->json(['data' => $resume], 201);
    }

    /**
     * POST /api/resumes/batch — 批量解析并保存多份简历。
     * body: {items: [{text, image_base64?}, ...]}
     */
    public function batch(Request $request): JsonResponse
    {
        $request->validate([
            'items'              => 'required|array|min:1|max:50',
            'items.*.text'       => 'required|string|max:5000',
            'items.*.image_base64' => 'nullable|string',
        ]);

        $success = 0;
        $failed  = 0;
        $results = [];

        foreach ($request->input('items') as $item) {
            try {
                $parsed = $this->parser->parseResume($item['text'], $item['image_base64'] ?? null);

                if (empty($parsed)) {
                    $failed++;
                    $results[] = ['status' => 'failed', 'reason' => 'AI解析失败'];

                    continue;
                }

                $resume = Resume::create(array_merge($parsed, [
                    'organization_id' => 1,
                    'created_by'      => $request->user()->id,
                    'raw_text'        => $item['text'],
                    'source'          => 2,
                ]));

                $success++;
                $results[] = ['status' => 'ok', 'id' => $resume->id, 'name' => $resume->name];
            } catch (\Throwable $e) {
                $failed++;
                $results[] = ['status' => 'failed', 'reason' => $e->getMessage()];
            }
        }

        return response()->json([
            'total'   => count($request->input('items')),
            'success' => $success,
            'failed'  => $failed,
            'results' => $results,
        ]);
    }

    /**
     * GET /api/resumes/search?q= — 自然语言搜索。
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|max:500']);

        $criteria = $this->parser->parseSearchQuery($request->input('q'));

        $query = Resume::with('createdBy:id,name')
            ->where('organization_id', 1)
            ->where('status', '!=', 0);

        $this->applyCriteria($query, $criteria);

        $resumes = $query->orderByDesc('created_at')->limit(50)->get();

        return response()->json([
            'data'     => $resumes,
            'criteria' => $criteria,
            'total'    => $resumes->count(),
        ]);
    }

    /**
     * GET /api/resumes/{id}
     */
    public function show(Resume $resume): JsonResponse
    {
        return response()->json(['data' => $resume->load('createdBy:id,name')]);
    }

    /**
     * PUT /api/resumes/{id}
     */
    public function update(Request $request, Resume $resume): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'nullable|string|max:100',
            'phone'             => 'nullable|string|max:20',
            'gender'            => 'nullable|integer|in:0,1,2',
            'age'               => 'nullable|integer|min:15|max:99',
            'districts'         => 'nullable|array',
            'work_types'        => 'nullable|array',
            'positions'         => 'nullable|array',
            'experience_years'  => 'nullable|numeric|min:0|max:50',
            'salary_min'        => 'nullable|integer|min:0',
            'salary_max'        => 'nullable|integer|min:0',
            'salary_unit'       => 'nullable|integer|in:1,2,3',
            'education'         => 'nullable|integer|in:1,2,3,4',
            'availability_date' => 'nullable|date',
            'languages'         => 'nullable|array',
            'skills'            => 'nullable|array',
            'status'            => 'nullable|integer|in:0,1,2,3',
            'notes'             => 'nullable|string|max:2000',
        ]);

        $resume->update($data);

        return response()->json(['data' => $resume]);
    }

    /**
     * DELETE /api/resumes/{id}
     */
    public function destroy(Resume $resume): JsonResponse
    {
        $resume->delete();

        return response()->json(['message' => 'deleted']);
    }

    private function applyCriteria(Builder $query, array $criteria): void
    {
        if (! empty($criteria['districts'])) {
            $query->where(function (Builder $q) use ($criteria): void {
                foreach ($criteria['districts'] as $district) {
                    $q->orWhereRaw('JSON_CONTAINS(districts, ?)', [json_encode($district)]);
                }
            });
        }

        if (! empty($criteria['work_types'])) {
            $query->where(function (Builder $q) use ($criteria): void {
                foreach ($criteria['work_types'] as $wt) {
                    $q->orWhereRaw('JSON_CONTAINS(work_types, ?)', [json_encode($wt)]);
                }
            });
        }

        if (! empty($criteria['positions'])) {
            $query->where(function (Builder $q) use ($criteria): void {
                foreach ($criteria['positions'] as $pos) {
                    $q->orWhereRaw('JSON_CONTAINS(positions, ?)', [json_encode($pos)])
                      ->orWhereRaw('JSON_SEARCH(positions, "one", ?) IS NOT NULL', ["%{$pos}%"]);
                }
            });
        }

        if (! empty($criteria['keywords'])) {
            $query->where(function (Builder $q) use ($criteria): void {
                foreach ($criteria['keywords'] as $kw) {
                    $q->orWhere('notes', 'like', "%{$kw}%")
                      ->orWhere('raw_text', 'like', "%{$kw}%");
                }
            });
        }
    }
}
