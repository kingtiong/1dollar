<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Helpers;

class PaymentService
{
    /**
     * Create a recharge order. Returns the payment row including any gateway
     * instructions (address / payment intent / checkout url).
     */
    public static function createRecharge(int $userId, float $amount, string $gateway): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('amount must be > 0');
        $gateways = Config::get('payments.gateways');
        if (!in_array($gateway, $gateways, true)) {
            throw new \InvalidArgumentException('unsupported gateway');
        }

        $orderNo = Helpers::orderNo('R');
        Database::q(
            "INSERT INTO payments (user_id, order_no, gateway, amount, currency, status)
             VALUES (?,?,?,?,?, 'pending')",
            [$userId, $orderNo, $gateway, $amount, 'Rs']
        );
        $id = (int)Database::insertId();

        $row = Database::one("SELECT * FROM payments WHERE id = ?", [$id]);

        // Instructions per gateway
        if ($gateway === 'usdt') {
            $cfg = Config::get('payments.usdt');
            $row['_instructions'] = [
                'address' => $cfg['address'],
                'rate'    => $cfg['rate'],
                'amount_usdt' => round($amount / (float)$cfg['rate'], 4),
                'note'    => "Send the exact USDT amount and upload the tx hash.",
            ];
        } elseif ($gateway === 'manual') {
            $row['_instructions'] = [
                'note' => 'Transfer to merchant bank account, upload proof image, wait for admin approval.',
            ];
        }
        return $row;
    }

    /**
     * Mark a payment as paid and credit wallet. Idempotent on order_no.
     */
    public static function markPaid(string $orderNo, ?string $gatewayRef = null): void
    {
        Database::tx(function () use ($orderNo, $gatewayRef) {
            $p = Database::one("SELECT * FROM payments WHERE order_no = ? FOR UPDATE", [$orderNo]);
            if (!$p) throw new \RuntimeException('payment not found');
            if ($p['status'] === 'paid') return;

            Database::q(
                "UPDATE payments SET status = 'paid', gateway_ref = ?, paid_at = NOW() WHERE id = ?",
                [$gatewayRef, $p['id']]
            );

            $u = Database::one("SELECT balance FROM users WHERE id = ? FOR UPDATE", [$p['user_id']]);
            $newBal = round((float)$u['balance'] + (float)$p['amount'], 2);
            Database::q("UPDATE users SET balance = ? WHERE id = ?", [$newBal, $p['user_id']]);

            Database::q(
                "INSERT INTO wallet_txns (user_id, kind, amount, balance_after, reference, note)
                 VALUES (?,?,?,?,?,?)",
                [$p['user_id'], 'recharge', $p['amount'], $newBal,
                 'payment:' . $p['order_no'], "Recharge via " . $p['gateway']]
            );
        });
    }
}
