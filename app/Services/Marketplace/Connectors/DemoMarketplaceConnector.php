<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\ChannelListing;
use App\Models\ChannelOrderPackage;
use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\AnswersCustomerQuestions;
use App\Services\Marketplace\Contracts\ManagesClaims;
use App\Services\Marketplace\Contracts\ManagesCommonLabels;
use App\Services\Marketplace\Contracts\PullsClaims;
use App\Services\Marketplace\Contracts\PullsCustomerQuestions;
use App\Services\Marketplace\Contracts\PullsFinancials;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\PushesPrice;
use App\Services\Marketplace\Contracts\PushesStock;
use App\Services\Marketplace\Contracts\SendsInvoiceLinks;
use App\Services\Marketplace\Contracts\UpdatesPackageStatus;

/**
 * Gerçek pazaryeri veya kargo API'lerine istek göndermeden entegrasyon
 * akışlarının uçtan uca denenebilmesini sağlayan deterministik bağlayıcı.
 */
class DemoMarketplaceConnector extends AbstractMarketplaceConnector implements AnswersCustomerQuestions, ManagesClaims, ManagesCommonLabels, PullsClaims, PullsCustomerQuestions, PullsFinancials, PullsOrders, PullsProducts, PushesPrice, PushesStock, SendsInvoiceLinks, UpdatesPackageStatus
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(
        protected string $provider,
        protected array $definition = [],
    ) {}

    public function providerKey(): string
    {
        return $this->provider;
    }

    public function displayName(): string
    {
        return (string) ($this->definition['label'] ?? $this->provider).' (Demo)';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return null;
    }

    /**
     * Demo mağazaları bütün ürün yüzeylerini güvenle deneyebilsin diye tüm
     * ortak capability'ler desteklenmiş gibi görünür.
     *
     * @return array<string, bool>
     */
    public function capabilities(): array
    {
        return array_fill_keys(array_keys(parent::capabilities()), true);
    }

    public function testConnection(MarketplaceStore $store): array
    {
        return [
            'ok' => true,
            'message' => 'Demo bağlantısı doğrulandı; harici API isteği gönderilmedi.',
            'mode' => 'demo',
            'provider' => $this->providerKey(),
            'store_id' => $store->getKey(),
        ];
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        return $this->emptyPullResponse($store, 'orders', $options);
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        return $this->emptyPullResponse($store, 'products', $options);
    }

    public function pullFinancialEvents(MarketplaceStore $store, array $options = []): array
    {
        return $this->emptyPullResponse($store, 'finance', $options);
    }

    public function pullCustomerQuestions(MarketplaceStore $store, array $options = []): array
    {
        return $this->emptyPullResponse($store, 'questions', $options);
    }

    public function pullClaims(MarketplaceStore $store, array $options = []): array
    {
        return $this->emptyPullResponse($store, 'claims', $options);
    }

    public function pushPrice(ChannelListing $listing, float $price, array $context = []): array
    {
        $actionId = $this->actionId('price-push', $this->listingReference($listing), round($price, 2));

        return [
            'success' => true,
            'status' => 'completed',
            'mode' => 'demo',
            'provider' => $this->providerKey(),
            'listing_id' => $listing->getKey(),
            'price' => round($price, 2),
            'batch_request_id' => $actionId,
            'external_action_id' => $actionId,
            'context' => $context,
        ];
    }

    public function pushStock(ChannelListing $listing, int $quantity, array $context = []): array
    {
        $actionId = $this->actionId('stock-push', $this->listingReference($listing), $quantity);

        return [
            'success' => true,
            'status' => 'completed',
            'mode' => 'demo',
            'provider' => $this->providerKey(),
            'listing_id' => $listing->getKey(),
            'quantity' => $quantity,
            'batch_request_id' => $actionId,
            'external_action_id' => $actionId,
            'context' => $context,
        ];
    }

    public function answerCustomerQuestion(MarketplaceQuestion $question, string $answer): array
    {
        $externalAnswerId = $this->actionId(
            'question-answer',
            $question->external_question_id ?: $question->getKey(),
            trim($answer),
        );

        return [
            'success' => true,
            'status' => 'answered',
            'mode' => 'demo',
            'provider' => $this->providerKey(),
            'external_answer_id' => $externalAnswerId,
        ];
    }

    public function approveClaim(MarketplaceStore $store, string $externalClaimId, array $context = []): array
    {
        return [
            'success' => true,
            'status' => 'approved',
            'message' => 'Demo iade onayı tamamlandı; pazaryerine istek gönderilmedi.',
            'mode' => 'demo',
            'provider' => $this->providerKey(),
            'external_action_id' => $this->actionId('claim-approve', $store->getKey(), $externalClaimId),
        ];
    }

    public function rejectClaim(MarketplaceStore $store, string $externalClaimId, string $reason, array $context = []): array
    {
        return [
            'success' => true,
            'status' => 'rejected',
            'message' => 'Demo iade reddi tamamlandı; pazaryerine istek gönderilmedi.',
            'mode' => 'demo',
            'provider' => $this->providerKey(),
            'external_action_id' => $this->actionId('claim-reject', $store->getKey(), $externalClaimId, trim($reason)),
        ];
    }

    public function notifyPackagePicking(ChannelOrderPackage $package, array $context = []): array
    {
        return $this->packageActionResponse($package, 'package-picking', 'Picking');
    }

    public function notifyPackageInvoiced(ChannelOrderPackage $package, array $context = []): array
    {
        return $this->packageActionResponse($package, 'package-invoiced', 'Invoiced');
    }

    public function createCommonLabel(ChannelOrderPackage $package, array $context = []): array
    {
        return $this->commonLabelResponse($package, $context, 'common-label-create');
    }

    public function getCommonLabel(ChannelOrderPackage $package, array $context = []): array
    {
        return $this->commonLabelResponse($package, $context, 'common-label-get');
    }

    public function sendInvoiceLink(ChannelOrderPackage $package, string $invoiceLink, array $context = []): array
    {
        return array_merge($this->packageActionResponse($package, 'invoice-link', 'completed'), [
            'invoice_link' => $invoiceLink,
        ]);
    }

    /**
     * Pazaryeri contract'ı dışında kalan kargo aksiyonları için de aynı güvenli
     * ve deterministik demo cevabını üretir.
     *
     * @param  array<int, mixed>  $parts
     * @return array<string, mixed>
     */
    public function simulateAction(string $action, array $parts = []): array
    {
        return [
            'success' => true,
            'status' => 'completed',
            'mode' => 'demo',
            'provider' => $this->providerKey(),
            'action' => $action,
            'external_action_id' => $this->actionId($action, ...$parts),
            'message' => 'Demo aksiyonu tamamlandı; harici servise istek gönderilmedi.',
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{items: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    protected function emptyPullResponse(MarketplaceStore $store, string $resource, array $options): array
    {
        return [
            'items' => [],
            'meta' => [
                'items_received' => 0,
                'cursor_after' => $options['end_date'] ?? null,
                'mode' => 'demo',
                'provider' => $this->providerKey(),
                'resource' => $resource,
                'store_id' => $store->getKey(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function packageActionResponse(ChannelOrderPackage $package, string $action, string $status): array
    {
        return [
            'success' => true,
            'status' => $status,
            'mode' => 'demo',
            'provider' => $this->providerKey(),
            'package_id' => $package->getKey(),
            'package_external_id' => $package->external_package_id,
            'external_action_id' => $this->actionId($action, $this->packageReference($package)),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function commonLabelResponse(ChannelOrderPackage $package, array $context, string $action): array
    {
        $reference = $this->packageReference($package);
        $cargoBarcode = $package->cargo_barcode
            ?: $package->cargo_tracking_number
            ?: 'DEMO-'.strtoupper(substr(hash('sha256', (string) $reference), 0, 12));

        return array_merge($this->packageActionResponse($package, $action, 'completed'), [
            'tracking_number' => $package->cargo_tracking_number ?: $cargoBarcode,
            'cargo_barcode' => $cargoBarcode,
            'label_format' => (string) ($context['format'] ?? 'ZPL'),
            'label_count' => 1,
            'response' => [
                'demo' => true,
                'label' => '^XA^FO50,50^FDZOLM DEMO LABEL^FS^XZ',
            ],
        ]);
    }

    protected function listingReference(ChannelListing $listing): string|int|null
    {
        return $listing->listing_id ?: $listing->getKey();
    }

    protected function packageReference(ChannelOrderPackage $package): string|int|null
    {
        return $package->external_package_id ?: $package->getKey();
    }

    protected function actionId(string $action, mixed ...$parts): string
    {
        $payload = json_encode(
            [$this->providerKey(), $action, ...$parts],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return 'demo-'.$this->providerKey().'-'.$action.'-'.substr(hash('sha256', (string) $payload), 0, 16);
    }
}
