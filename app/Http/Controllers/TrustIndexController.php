<?php

namespace App\Http\Controllers;

use App\Models\TrustIndexSubmission;
use App\Services\TrustIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrustIndexController extends Controller
{
    public function __construct(
        private readonly TrustIndexService $trustIndex,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('TrustIndex/Index', [
            'scams' => config('trust_index.scams', []),
            'listedCount' => count(config('trust_index.scammers', [])),
            'flash' => $this->flashProps($request),
        ]);
    }

    public function createReport(Request $request): Response
    {
        return Inertia::render('TrustIndex/Report', [
            'categories' => $this->categories(),
            'flash' => $this->flashProps($request),
        ]);
    }

    public function createAppeal(Request $request): Response
    {
        $username = trim((string) $request->query('username', ''));

        return Inertia::render('TrustIndex/Appeal', [
            'initialUsername' => $username !== '' ? $username : null,
            'flash' => $this->flashProps($request),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (strlen($query) < 2) {
            return response()->json([
                'found' => false,
                'message' => 'too_short',
            ]);
        }

        $scammer = $this->trustIndex->lookup($query);

        if ($scammer === null) {
            return response()->json([
                'found' => false,
                'query' => $query,
            ]);
        }

        return response()->json([
            'found' => true,
            'scammer' => $scammer,
        ]);
    }

    public function storeReport(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'minecraft_username' => ['required', 'string', 'min:2', 'max:16', 'regex:/^[a-zA-Z0-9_]+$/'],
            'category' => ['required', 'string', 'max:64'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'evidence' => ['nullable', 'string', 'max:2000'],
            'submitter_name' => ['nullable', 'string', 'max:64'],
            'submitter_contact' => ['nullable', 'string', 'max:128'],
        ]);

        TrustIndexSubmission::query()->create([
            'type' => TrustIndexSubmission::TYPE_REPORT,
            'status' => TrustIndexSubmission::STATUS_PENDING,
            'minecraft_username' => $data['minecraft_username'],
            'category' => $data['category'],
            'description' => $data['description'],
            'evidence' => $data['evidence'] ?? null,
            'submitter_name' => $data['submitter_name'] ?? null,
            'submitter_contact' => $data['submitter_contact'] ?? null,
            'user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('trust-index')
            ->with('trust_index_message', 'Scam report submitted. Our team will review it.')
            ->with('trust_index_variant', 'success');
    }

    public function storeAppeal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'minecraft_username' => ['required', 'string', 'min:2', 'max:16', 'regex:/^[a-zA-Z0-9_]+$/'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'evidence' => ['nullable', 'string', 'max:2000'],
            'submitter_name' => ['nullable', 'string', 'max:64'],
            'submitter_contact' => ['nullable', 'string', 'max:128'],
        ]);

        TrustIndexSubmission::query()->create([
            'type' => TrustIndexSubmission::TYPE_APPEAL,
            'status' => TrustIndexSubmission::STATUS_PENDING,
            'minecraft_username' => $data['minecraft_username'],
            'description' => $data['description'],
            'evidence' => $data['evidence'] ?? null,
            'submitter_name' => $data['submitter_name'] ?? null,
            'submitter_contact' => $data['submitter_contact'] ?? null,
            'user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('trust-index')
            ->with('trust_index_message', 'Appeal submitted. We will review your case.')
            ->with('trust_index_variant', 'success');
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function categories(): array
    {
        return collect(config('trust_index.incident_categories', []))
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{message: mixed, variant: string}
     */
    private function flashProps(Request $request): array
    {
        return [
            'message' => $request->session()->get('trust_index_message'),
            'variant' => $request->session()->get('trust_index_variant', 'success'),
        ];
    }
}
