<?php

namespace App\Http\Controllers;

use App\Models\ProductMatchIssue;
use App\Services\Marketplace\MarketplaceManualMatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MarketplaceMatchingBulkSelectionController extends Controller
{
    private const SESSION_KEY = 'marketplace_matching.selected_issue_ids';

    public function select(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $ids = $this->authorizedIds($request, $request->input('issue_ids', []));
        $request->session()->put(self::SESSION_KEY, $ids);

        if ($request->expectsJson()) {
            return response()->json(['selected_issue_ids' => $ids]);
        }

        return redirect()->route('mp.matching')->with('success', count($ids).' kayıt toplu işlem için seçildi.');
    }

    public function apply(Request $request, MarketplaceManualMatchService $service): RedirectResponse
    {
        $action = $request->string('action')->toString();
        $ids = $this->authorizedIds($request, $request->session()->get(self::SESSION_KEY, []));

        if ($ids === [] || ! in_array($action, ['create_products', 'ignore', 'reopen'], true)) {
            return redirect()->route('mp.matching')->with('warning', 'Toplu işlem için kayıt ve geçerli aksiyon seçin.');
        }

        $processed = 0;
        foreach (ProductMatchIssue::query()->with(['store', 'channelListing.channelProduct', 'channelListing.product'])->whereIn('id', $ids)->get() as $issue) {
            if ($action === 'create_products' && $issue->match_status === 'pending') {
                try {
                    $service->createMasterProductFromListing($issue, $request->user()->id);
                    $processed++;
                } catch (\Throwable) {
                    // Listeleme ilişkisi olmayan veya eksik kanal verili kayıtlar atlanır.
                }
            } elseif ($action === 'ignore' && $issue->match_status === 'pending') {
                $service->ignore($issue, $request->user()->id);
                $processed++;
            } elseif ($action === 'reopen' && $issue->match_status !== 'pending') {
                $service->reopen($issue);
                $processed++;
            }
        }

        $request->session()->forget(self::SESSION_KEY);

        if ($action === 'create_products') {
            return redirect()->route('mp.products')->with('success', $processed.' seçili kayıt ürün listesine eklendi ve eşleştirildi.');
        }

        return redirect()->route('mp.matching')->with('success', $processed.' kayıt için toplu işlem uygulandı.');
    }

    public function clear(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        if ($request->expectsJson()) {
            return response()->json(['selected_issue_ids' => []]);
        }

        return redirect()->route('mp.matching');
    }

    public function createProduct(Request $request, MarketplaceManualMatchService $service): RedirectResponse
    {
        $issue = ProductMatchIssue::query()
            ->with(['store', 'channelListing.channelProduct', 'channelListing.product'])
            ->whereKey((int) $request->input('issue_id'))
            ->whereHas('store', fn ($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();

        try {
            $result = $service->createMasterProductFromListing($issue, $request->user()->id);
            $message = $result['created']
                ? "Ana ürün oluşturuldu ve '{$result['product']->product_name}' ürün listesine eklendi."
                : "Mevcut ana ürün '{$result['product']->product_name}' ile eşleştirildi.";

            return redirect()->route('mp.products')->with('success', $message);
        } catch (\Throwable $exception) {
            return redirect()->route('mp.matching')->with('warning', $exception->getMessage());
        }
    }

    public function defer(Request $request, MarketplaceManualMatchService $service): RedirectResponse
    {
        $issue = ProductMatchIssue::query()
            ->with('store')
            ->whereKey((int) $request->input('issue_id'))
            ->whereHas('store', fn ($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();

        $service->ignore($issue, $request->user()->id);

        return redirect()->route('mp.matching')->with('success', 'Kayıt inceleme dışı bırakıldı.');
    }

    public function toggle(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $issueId = (int) $request->input('issue_id');
        $ids = $this->authorizedIds($request, $request->session()->get(self::SESSION_KEY, []));
        $authorizedId = $this->authorizedIds($request, [$issueId])[0] ?? null;

        if ($authorizedId === null) {
            return redirect()->route('mp.matching')->with('warning', 'Bu kayıt için seçim yapılamadı.');
        }

        $ids = $request->boolean('selected')
            ? collect($ids)->push($authorizedId)->unique()->values()->all()
            : collect($ids)->reject(fn ($id) => (string) $id === (string) $authorizedId)->values()->all();

        $request->session()->put(self::SESSION_KEY, $ids);

        if ($request->expectsJson()) {
            return response()->json(['selected_issue_ids' => $ids]);
        }

        return redirect()->route('mp.matching');
    }

    private function authorizedIds(Request $request, mixed $ids): array
    {
        return ProductMatchIssue::query()
            ->whereIn('id', collect((array) $ids)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->all())
            ->whereHas('store', fn ($query) => $query->where('user_id', $request->user()->id))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }
}
