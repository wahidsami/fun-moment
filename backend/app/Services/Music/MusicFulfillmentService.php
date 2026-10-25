<?php

namespace App\Services\Music;

use App\MusicEntitlement;
use App\MusicOffer;
use App\MusicOrder;
use App\MusicSellerLedgerEntry;
use App\MusicSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class MusicFulfillmentService
 *
 * Authoritative fulfillment service for the Music Marketplace.
 * Exclusively responsible for transitioning paid orders to completed status,
 * issuing immutable streaming entitlements, and recording seller earnings ledger entries.
 */
class MusicFulfillmentService
{
    /**
     * Authoritatively fulfill an order upon confirmed payment.
     *
     * @param MusicOrder $order
     * @param string $gateway 'wallet' or 'paytabs'
     * @param string $transactionRef Unique provider or ledger transaction ID
     * @param array $paymentDetails Sanitized audit details
     * @return array
     */
    public function fulfillOrder(
        MusicOrder $order,
        string $gateway,
        string $transactionRef,
        array $paymentDetails = []
    ): array {
        return DB::transaction(function () use ($order, $gateway, $transactionRef, $paymentDetails) {
            /** @var MusicOrder $lockedOrder */
            $lockedOrder = MusicOrder::where('id', $order->id)->lockForUpdate()->first();
            if (!$lockedOrder) {
                return [
                    'success' => false,
                    'message' => 'Order not found.',
                ];
            }

            // 1. Idempotency Check
            if ($lockedOrder->isCompleted()) {
                Log::info("[MusicFulfillment] Order #{$lockedOrder->id} already completed. Reusing existing entitlement.");
                $existingEntitlement = MusicEntitlement::where('order_id', $lockedOrder->id)->first();
                return [
                    'success' => true,
                    'already_fulfilled' => true,
                    'order' => $lockedOrder,
                    'entitlement' => $existingEntitlement,
                ];
            }

            // 2. Authoritative Database State Mutation
            $lockedOrder->payment_status = MusicOrder::STATUS_COMPLETED;
            $lockedOrder->payment_gateway = $gateway;
            $lockedOrder->transaction_id = $transactionRef;
            $lockedOrder->paid_at = now();
            if (!empty($paymentDetails)) {
                $lockedOrder->payment_details = $paymentDetails;
            }
            $lockedOrder->save();

            // 3. Issue Exactly One Music Entitlement
            $offer = $lockedOrder->offer ?: MusicOffer::find($lockedOrder->offer_id);
            $offerType = $lockedOrder->offer_type ?: ($offer ? $offer->offer_type : MusicOffer::OFFER_TYPE_RENT);

            $expiresAt = null;
            if ($offerType === MusicOffer::OFFER_TYPE_RENT) {
                $rentalDays = ($offer && $offer->rental_duration_days > 0)
                    ? $offer->rental_duration_days
                    : (int) MusicSetting::get('default_rental_duration_days', 7);

                $expiresAt = now()->addDays($rentalDays);
            } elseif ($offerType === MusicOffer::OFFER_TYPE_SALE) {
                // Sale is strictly perpetual streaming in-app
                $expiresAt = null;
            }

            $entitlement = MusicEntitlement::create([
                'user_id' => $lockedOrder->buyer_id,
                'track_id' => $lockedOrder->track_id,
                'offer_id' => $lockedOrder->offer_id,
                'order_id' => $lockedOrder->id,
                'access_type' => MusicEntitlement::ACCESS_STREAM_ONLY,
                'license_type' => MusicEntitlement::LICENSE_STREAM_ONLY,
                'allow_download' => false,
                'commercial_use' => false,
                'remix_rights' => false,
                'starts_at' => now(),
                'expires_at' => $expiresAt,
                'is_active' => true,
            ]);

            // 4. Record Immutable Seller Earnings in Financial Ledger (if seller exists)
            if (!empty($lockedOrder->seller_id)) {
                MusicSellerLedgerEntry::create([
                    'seller_id' => $lockedOrder->seller_id,
                    'order_id' => $lockedOrder->id,
                    'track_id' => $lockedOrder->track_id,
                    'entry_type' => MusicSellerLedgerEntry::TYPE_EARNING,
                    'gross_amount' => $lockedOrder->subtotal ?: $lockedOrder->amount,
                    'commission_deducted' => $lockedOrder->commission_amount,
                    'vat_deducted' => $lockedOrder->commission_vat_amount,
                    'net_earning' => $lockedOrder->seller_earning,
                    'currency' => $lockedOrder->currency ?: 'SAR',
                    'status' => MusicSellerLedgerEntry::STATUS_PAYABLE,
                    'metadata' => [
                        'invoice' => $lockedOrder->invoice,
                        'gateway' => $gateway,
                        'transaction_id' => $transactionRef,
                        'tax_treatment' => $lockedOrder->tax_treatment,
                    ],
                ]);
            }

            Log::info("[MusicFulfillment] Order #{$lockedOrder->id} fulfilled successfully. Entitlement #{$entitlement->id} created.");

            return [
                'success' => true,
                'already_fulfilled' => false,
                'order' => $lockedOrder,
                'entitlement' => $entitlement,
            ];
        });
    }
}
