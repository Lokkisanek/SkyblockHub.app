<?php

namespace App\Http\Controllers;

use App\Models\TrustIndexSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTrustIndexSubmissionController extends Controller
{
    public function index(Request $request): Response
    {
        $submissions = TrustIndexSubmission::query()
            ->with('reviewer:id,discord_username,minecraft_username')
            ->latest()
            ->get()
            ->sort(function (TrustIndexSubmission $a, TrustIndexSubmission $b) {
                $pending = TrustIndexSubmission::STATUS_PENDING;
                $pa = $a->status === $pending ? 0 : 1;
                $pb = $b->status === $pending ? 0 : 1;
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }

                return $b->created_at <=> $a->created_at;
            })
            ->values()
            ->map(fn (TrustIndexSubmission $s) => $this->submissionSummary($s));

        return Inertia::render('Admin/TrustIndex/Submissions', [
            'submissions' => $submissions,
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function show(TrustIndexSubmission $submission): Response
    {
        $submission->load(['user:id,discord_username,minecraft_username', 'reviewer:id,discord_username,minecraft_username']);

        return Inertia::render('Admin/TrustIndex/Review', [
            'submission' => $this->submissionDetail($submission),
        ]);
    }

    public function approve(Request $request, TrustIndexSubmission $submission): RedirectResponse
    {
        return $this->resolve($request, $submission, TrustIndexSubmission::STATUS_APPROVED);
    }

    public function reject(Request $request, TrustIndexSubmission $submission): RedirectResponse
    {
        return $this->resolve($request, $submission, TrustIndexSubmission::STATUS_REJECTED);
    }

    private function resolve(Request $request, TrustIndexSubmission $submission, string $status): RedirectResponse
    {
        if ($submission->status !== TrustIndexSubmission::STATUS_PENDING) {
            return redirect()
                ->route('admin.trust-index.submissions.show', $submission)
                ->with('status', 'This submission has already been reviewed.');
        }

        $data = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $submission->update([
            'status' => $status,
            'admin_notes' => $data['admin_notes'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()?->id,
        ]);

        $kind = $submission->type === TrustIndexSubmission::TYPE_APPEAL ? 'Appeal' : 'Report';

        return redirect()
            ->route('admin.trust-index.submissions')
            ->with('status', $status === TrustIndexSubmission::STATUS_APPROVED
                ? "{$kind} marked as approved / triaged."
                : "{$kind} rejected and closed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function submissionSummary(TrustIndexSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'type' => $submission->type,
            'status' => $submission->status,
            'minecraftUsername' => $submission->minecraft_username,
            'category' => $submission->category,
            'categoryLabel' => $this->categoryLabel($submission->category),
            'createdAt' => $submission->created_at?->toIso8601String(),
            'submitterName' => $submission->submitter_name,
            'submitterContact' => $submission->submitter_contact,
            'reviewedAt' => $submission->reviewed_at?->toIso8601String(),
            'reviewedBy' => $this->reviewerLabel($submission->reviewer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function submissionDetail(TrustIndexSubmission $submission): array
    {
        $base = $this->submissionSummary($submission);

        return array_merge($base, [
            'description' => $submission->description,
            'evidence' => $submission->evidence,
            'adminNotes' => $submission->admin_notes,
            'submitterUser' => $submission->user
                ? [
                    'id' => $submission->user->id,
                    'discordUsername' => $submission->user->discord_username,
                    'minecraftUsername' => $submission->user->minecraft_username,
                ]
                : null,
        ]);
    }

    private function categoryLabel(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        $meta = config("trust_index.incident_categories.{$key}");

        return is_array($meta) ? ($meta['label'] ?? $key) : $key;
    }

    private function reviewerLabel(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return $user->minecraft_username ?? $user->discord_username ?? ('#'.$user->id);
    }
}
