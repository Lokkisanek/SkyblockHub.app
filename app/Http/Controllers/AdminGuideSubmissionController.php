<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Models\GuideRevision;
use App\Models\GuideSubmission;
use App\Services\GuideCatalogService;
use App\Services\GuideContentNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminGuideSubmissionController extends Controller
{
    public function __construct(
        private readonly GuideContentNormalizer $normalizer,
    ) {}

    public function index(): Response
    {
        $submissions = GuideSubmission::query()
            ->with('guide:id,slug,title')
            ->orderByRaw("status = 'pending' desc")
            ->latest()
            ->get()
            ->map(fn (GuideSubmission $submission) => $this->submissionSummary($submission));

        return Inertia::render('Admin/Guides/Submissions', [
            'submissions' => $submissions,
        ]);
    }

    public function show(GuideSubmission $submission): Response
    {
        $submission->load('guide:id,slug,title,description,category,category_label,sections,useful_links,last_updated_on');

        return Inertia::render('Admin/Guides/Review', [
            'submission' => $this->submissionDetail($submission),
            'categories' => GuideCatalogService::CATEGORIES,
        ]);
    }

    public function update(Request $request, GuideSubmission $submission): RedirectResponse
    {
        if ($submission->status !== GuideSubmission::STATUS_PENDING) {
            return back()->with('status', 'This submission has already been reviewed.');
        }

        $submission->update($this->validatedReviewData($request, $submission));

        return back()->with('status', 'Submission draft updated.');
    }

    public function approve(Request $request, GuideSubmission $submission): RedirectResponse
    {
        if ($submission->status !== GuideSubmission::STATUS_PENDING) {
            return back()->with('status', 'This submission has already been reviewed.');
        }

        $data = $this->validatedReviewData($request, $submission);
        $adminId = $request->user()?->id;

        DB::transaction(function () use ($submission, $data, $adminId) {
            $submission->update($data);

            $guide = $submission->type === GuideSubmission::TYPE_EDIT && $submission->guide
                ? $submission->guide
                : new Guide(['created_by' => $adminId]);

            $guide->fill([
                'slug' => $data['slug'],
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $data['category'],
                'category_label' => $data['category_label'],
                'sections' => $data['sections'],
                'useful_links' => $data['useful_links'],
                'status' => Guide::STATUS_PUBLISHED,
                'last_updated_on' => now()->toDateString(),
                'updated_by' => $adminId,
                'published_at' => $guide->published_at ?? now(),
            ]);

            if (! $guide->exists) {
                $guide->sort_order = (Guide::query()->max('sort_order') ?? 0) + 1;
            }

            $guide->save();

            GuideRevision::query()->create([
                'guide_id' => $guide->id,
                'guide_submission_id' => $submission->id,
                'user_id' => $adminId,
                'title' => $guide->title,
                'slug' => $guide->slug,
                'description' => $guide->description,
                'category' => $guide->category,
                'category_label' => $guide->category_label,
                'sections' => $guide->sections,
                'useful_links' => $guide->useful_links,
            ]);

            $submission->update([
                'guide_id' => $guide->id,
                'status' => GuideSubmission::STATUS_APPROVED,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);
        });

        return redirect()->route('admin.guides.submissions')->with('status', 'Guide submission approved.');
    }

    public function reject(Request $request, GuideSubmission $submission): RedirectResponse
    {
        if ($submission->status !== GuideSubmission::STATUS_PENDING) {
            return back()->with('status', 'This submission has already been reviewed.');
        }

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $submission->update([
            'status' => GuideSubmission::STATUS_REJECTED,
            'admin_notes' => $validated['admin_notes'] ?? $submission->admin_notes,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('admin.guides.submissions')->with('status', 'Guide submission rejected.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedReviewData(Request $request, GuideSubmission $submission): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:160',
                Rule::unique('guides', 'slug')->ignore($submission->guide_id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['required', 'string', Rule::in(array_keys(GuideCatalogService::CATEGORIES))],
            'sections' => ['required', 'array'],
            'useful_links' => ['nullable', 'array'],
            'admin_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $category = $validated['category'];

        return [
            'title' => trim($validated['title']),
            'slug' => str($validated['slug'])->slug()->toString(),
            'description' => $validated['description'] ?? null,
            'category' => $category,
            'category_label' => GuideCatalogService::CATEGORIES[$category],
            'sections' => $this->normalizer->sections($validated['sections']),
            'useful_links' => $this->normalizer->usefulLinks($validated['useful_links'] ?? []),
            'admin_notes' => $validated['admin_notes'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function submissionSummary(GuideSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'type' => $submission->type,
            'status' => $submission->status,
            'title' => $submission->title,
            'slug' => $submission->slug,
            'submitterName' => $submission->submitter_name,
            'submitterContact' => $submission->submitter_contact,
            'createdAt' => $submission->created_at?->diffForHumans(),
            'guide' => $submission->guide ? [
                'slug' => $submission->guide->slug,
                'title' => $submission->guide->title,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function submissionDetail(GuideSubmission $submission): array
    {
        return [
            ...$this->submissionSummary($submission),
            'description' => $submission->description,
            'category' => $submission->category,
            'categoryLabel' => $submission->category_label,
            'sections' => $submission->sections ?? [],
            'usefulLinks' => $submission->useful_links ?? [],
            'adminNotes' => $submission->admin_notes,
            'guide' => $submission->guide ? $submission->guide->toPublicArray() : null,
        ];
    }
}
