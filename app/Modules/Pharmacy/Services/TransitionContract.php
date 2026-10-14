<?php

namespace Pharmacy\Services;

final class TransitionContract
{
    public const PRESCRIPTION_REVIEW = [
        'draft' => ['approved', 'rejected', 'clarification_requested', 'cancelled'],
        'submitted' => ['approved', 'rejected', 'clarification_requested', 'cancelled'],
        'clarification_requested' => ['approved', 'rejected', 'clarification_requested', 'cancelled'],
        'approved' => ['approved'],
        'rejected' => ['rejected'],
        'cancelled' => ['cancelled'],
    ];

    public const ORDER = [
        'pending' => ['pending', 'fulfilled', 'cancelled'],
        'fulfilled' => ['fulfilled'],
        'cancelled' => ['cancelled'],
    ];

    public const PAYMENT = [
        'pending' => ['pending', 'authorized', 'paid', 'failed'],
        'authorized' => ['authorized', 'paid', 'failed'],
        'paid' => ['paid', 'refunded'],
        'failed' => ['failed', 'pending'],
        'refunded' => ['refunded'],
    ];

    /**
     * Strict billing reconciliation contract; the legacy PAYMENT map remains for existing dashboard compatibility.
     */
    public const PAYMENT_BILLING = [
        'pending' => ['authorized', 'failed'],
        'authorized' => ['paid', 'failed'],
        'paid' => ['refunded'],
        'failed' => ['pending'],
        'refunded' => [],
    ];

    public const DELIVERY = [
        'pending' => ['pending', 'packed', 'failed'],
        'packed' => ['packed', 'dispatched', 'failed'],
        'dispatched' => ['dispatched', 'out_for_delivery', 'failed'],
        'out_for_delivery' => ['out_for_delivery', 'delivered', 'failed'],
        'failed' => ['failed', 'returned'],
        'delivered' => ['delivered', 'returned'],
        'returned' => [],
    ];

    public function allows(string $domain, string $current, string $target): bool
    {
        $maps = [
            'prescription' => self::PRESCRIPTION_REVIEW,
            'order' => self::ORDER,
            'payment' => self::PAYMENT,
            'payment_billing' => self::PAYMENT_BILLING,
            'delivery' => self::DELIVERY,
        ];

        return in_array($target, $maps[$domain][$current] ?? [], true);
    }
}
