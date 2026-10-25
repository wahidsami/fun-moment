<?php

namespace App\Services\Music;

use App\MusicOffer;
use App\MusicOrder;
use App\MusicSetting;
use App\MusicTrack;
use App\User;
use InvalidArgumentException;

/**
 * Class MusicFinancialCalculationService
 *
 * Authoritative server-side financial calculation engine for the Music Marketplace.
 * Uses exact integer halala arithmetic (1 SAR = 100 halalas) to eliminate floating-point
 * rounding discrepancies during commercial checkout, VAT assessment, and settlement.
 */
class MusicFinancialCalculationService
{
    /**
     * Convert SAR amount to integer halalas.
     */
    public static function toHalalas(float|int|string $sar): int
    {
        return (int) round(((float) $sar) * 100);
    }

    /**
     * Convert integer halalas to SAR float with 2 decimal places.
     */
    public static function fromHalalas(int $halalas): float
    {
        return round($halalas / 100, 2);
    }

    /**
     * Calculate authoritative commercial price breakdown for an offer checkout.
     *
     * @param MusicOffer $offer The active commercial offer (rent or sale)
     * @param User|null $seller The resolved seller (or null for admin/platform track)
     * @param array $options Overrides or context (e.g., explicit commission_rate, vat_rate, display_mode)
     * @return array Authoritative financial breakdown
     */
    public function calculateOfferBreakdown(MusicOffer $offer, ?User $seller = null, array $options = []): array
    {
        $offerPriceSar = (float) $offer->price;
        if ($offerPriceSar < 0) {
            throw new InvalidArgumentException("Offer price cannot be negative.");
        }

        // 1. Resolve Global Settings & Context
        $vatEnabled = isset($options['vat_enabled'])
            ? (bool) $options['vat_enabled']
            : (bool) (int) MusicSetting::get('music_vat_enabled', 0);

        $configuredVatRate = isset($options['vat_rate'])
            ? (float) $options['vat_rate']
            : (float) MusicSetting::get('music_vat_rate', 15.00);

        $configuredCommissionRate = isset($options['commission_rate'])
            ? (float) $options['commission_rate']
            : (float) MusicSetting::get('platform_commission_rate', 15.00);

        $priceDisplayMode = isset($options['price_display_mode'])
            ? (string) $options['price_display_mode']
            : (string) MusicSetting::get('music_price_display_mode', MusicOrder::PRICE_MODE_EXCLUSIVE);

        // 2. Determine Tax Treatment Scenario
        $track = $offer->track ?: MusicTrack::find($offer->track_id);
        $isPlatformOwned = $track ? $track->isOwnedByAdmin() : false;

        $taxTreatment = $this->determineTaxTreatment($track, $seller, $isPlatformOwned, $options);

        // 3. Exact Halala Calculations
        $offerPriceHalalas = self::toHalalas($offerPriceSar);
        $vatRatePercent = $vatEnabled ? max(0.0, $configuredVatRate) : 0.0;
        $commissionRatePercent = max(0.0, min(100.0, $configuredCommissionRate));

        $subtotalHalalas = 0;
        $buyerVatHalalas = 0;
        $buyerTotalHalalas = 0;

        if ($priceDisplayMode === MusicOrder::PRICE_MODE_INCLUSIVE && $vatRatePercent > 0) {
            // Listed price is VAT-inclusive: total = listed price, subtotal = total / (1 + vatRate)
            $buyerTotalHalalas = $offerPriceHalalas;
            $divisor = 1.0 + ($vatRatePercent / 100.0);
            $subtotalHalalas = (int) round($offerPriceHalalas / $divisor);
            $buyerVatHalalas = $buyerTotalHalalas - $subtotalHalalas;
        } else {
            // Listed price is VAT-exclusive (standard): subtotal = listed price, vat = subtotal * rate
            $subtotalHalalas = $offerPriceHalalas;
            if ($vatRatePercent > 0) {
                $buyerVatHalalas = (int) round(($subtotalHalalas * $vatRatePercent) / 100.0);
            }
            $buyerTotalHalalas = $subtotalHalalas + $buyerVatHalalas;
        }

        // 4. Commission & Seller Proceeds Calculation
        // Explicit basis: Commission is calculated on the commercial supply Subtotal (exclusive of VAT)
        $commissionBasisHalalas = $subtotalHalalas;
        $commissionHalalas = (int) round(($commissionBasisHalalas * $commissionRatePercent) / 100.0);

        $commissionVatHalalas = 0;
        if ($taxTreatment === MusicOrder::TAX_INTERMEDIARY && $vatRatePercent > 0) {
            // In Intermediary treatment, platform charges VAT on its intermediation fee to the seller
            $commissionVatHalalas = (int) round(($commissionHalalas * $vatRatePercent) / 100.0);
        }

        $platformTotalFeeHalalas = $commissionHalalas + $commissionVatHalalas;

        // Seller Earnings
        $sellerGrossHalalas = $subtotalHalalas;
        $sellerEarningHalalas = 0;

        if ($isPlatformOwned) {
            // Platform keeps 100% of proceeds; seller earning is 0
            $sellerEarningHalalas = 0;
            $platformRevenueHalalas = $subtotalHalalas;
        } else {
            $sellerEarningHalalas = max(0, $sellerGrossHalalas - $platformTotalFeeHalalas);
            $platformRevenueHalalas = $commissionHalalas;
        }

        return [
            'currency' => 'SAR',
            'offer_price' => self::fromHalalas($offerPriceHalalas),
            'subtotal' => self::fromHalalas($subtotalHalalas),
            'vat_rate' => round($vatRatePercent, 2),
            'vat_amount' => self::fromHalalas($buyerVatHalalas),
            'total_amount' => self::fromHalalas($buyerTotalHalalas),
            'commission_rate' => round($commissionRatePercent, 2),
            'commission_amount' => self::fromHalalas($commissionHalalas),
            'commission_vat_amount' => self::fromHalalas($commissionVatHalalas),
            'platform_fee_total' => self::fromHalalas($platformTotalFeeHalalas),
            'seller_gross' => self::fromHalalas($sellerGrossHalalas),
            'seller_earning' => self::fromHalalas($sellerEarningHalalas),
            'platform_revenue' => self::fromHalalas($platformRevenueHalalas),
            'vat_collected_for_remittance' => self::fromHalalas($buyerVatHalalas + $commissionVatHalalas),
            'tax_treatment' => $taxTreatment,
            'price_display_mode' => $priceDisplayMode,
            'halalas' => [
                'offer_price' => $offerPriceHalalas,
                'subtotal' => $subtotalHalalas,
                'buyer_vat' => $buyerVatHalalas,
                'total' => $buyerTotalHalalas,
                'commission' => $commissionHalalas,
                'commission_vat' => $commissionVatHalalas,
                'seller_earning' => $sellerEarningHalalas,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Determine the applicable Saudi ZATCA VAT treatment for this transaction.
     */
    protected function determineTaxTreatment(?MusicTrack $track, ?User $seller, bool $isPlatformOwned, array $options): string
    {
        if (isset($options['tax_treatment'])) {
            return $options['tax_treatment'];
        }

        if ($isPlatformOwned) {
            return MusicOrder::TAX_DIRECT_PLATFORM;
        }

        if ($seller && $seller->isVatRegistered() && $seller->isTaxVerified()) {
            return MusicOrder::TAX_INTERMEDIARY;
        }

        // Default approved scenario for digital marketplaces under ZATCA Jan 2026 amendments:
        // Marketplace acts as deemed supplier when seller is not VAT-registered.
        return MusicOrder::TAX_DEEMED_SUPPLIER;
    }

    /**
     * Calculate proportional refund breakdown (for full or partial refund).
     *
     * @param MusicOrder $order Confirmed paid order
     * @param float|null $requestedRefundAmount If null, calculates full remaining refund
     * @param string $reason Audit reason
     * @return array Refund calculation breakdown
     */
    public function calculateRefund(MusicOrder $order, ?float $requestedRefundAmount = null, string $reason = ''): array
    {
        $paidHalalas = self::toHalalas($order->amount);
        $alreadyRefundedHalalas = self::toHalalas($order->refund_amount ?? 0.00);
        $remainingHalalas = max(0, $paidHalalas - $alreadyRefundedHalalas);

        if ($remainingHalalas <= 0) {
            throw new InvalidArgumentException("Order is already fully refunded.");
        }

        $targetRefundHalalas = $requestedRefundAmount !== null
            ? self::toHalalas($requestedRefundAmount)
            : $remainingHalalas;

        if ($targetRefundHalalas <= 0) {
            throw new InvalidArgumentException("Refund amount must be greater than zero.");
        }

        if ($targetRefundHalalas > $remainingHalalas) {
            throw new InvalidArgumentException(sprintf(
                "Requested refund (%s SAR) exceeds remaining refundable amount (%s SAR).",
                self::fromHalalas($targetRefundHalalas),
                self::fromHalalas($remainingHalalas)
            ));
        }

        // Calculate proportional factor
        $ratio = $targetRefundHalalas / (float) $paidHalalas;

        $orderSubtotalHalalas = self::toHalalas($order->subtotal ?: $order->amount);
        $orderVatHalalas = self::toHalalas($order->vat_amount ?? 0.00);
        $orderCommissionHalalas = self::toHalalas($order->commission_amount ?? 0.00);
        $orderCommissionVatHalalas = self::toHalalas($order->commission_vat_amount ?? 0.00);
        $orderSellerEarningHalalas = self::toHalalas($order->seller_earning ?? 0.00);

        // Proportional adjustments
        $refundSubtotalHalalas = (int) round($orderSubtotalHalalas * $ratio);
        $refundVatHalalas = $targetRefundHalalas - $refundSubtotalHalalas;
        $reversedCommissionHalalas = (int) round($orderCommissionHalalas * $ratio);
        $reversedCommissionVatHalalas = (int) round($orderCommissionVatHalalas * $ratio);
        $reversedSellerEarningHalalas = (int) round($orderSellerEarningHalalas * $ratio);

        $isFullRefund = ($alreadyRefundedHalalas + $targetRefundHalalas) >= $paidHalalas;

        return [
            'order_id' => $order->id,
            'currency' => $order->currency ?: 'SAR',
            'paid_amount' => self::fromHalalas($paidHalalas),
            'already_refunded' => self::fromHalalas($alreadyRefundedHalalas),
            'refund_amount' => self::fromHalalas($targetRefundHalalas),
            'remaining_after_refund' => self::fromHalalas(max(0, $remainingHalalas - $targetRefundHalalas)),
            'refund_subtotal' => self::fromHalalas($refundSubtotalHalalas),
            'refund_vat' => self::fromHalalas($refundVatHalalas),
            'reversed_commission' => self::fromHalalas($reversedCommissionHalalas),
            'reversed_commission_vat' => self::fromHalalas($reversedCommissionVatHalalas),
            'reversed_seller_earning' => self::fromHalalas($reversedSellerEarningHalalas),
            'is_full_refund' => $isFullRefund,
            'reason' => $reason,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
