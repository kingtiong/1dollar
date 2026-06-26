<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;

class WalletController
{
    public function summary(Request $req): void
    {
        $u = Auth::require($req);
        $row = Database::one("SELECT balance, points FROM users WHERE id = ?", [$u['user_id']]);
        $cs = Database::val(
            "SELECT COALESCE(SUM(amount),0) FROM commissions WHERE user_id = ?",
            [$u['user_id']]
        );
        Response::ok([
            'balance'       => (float)$row['balance'],
            'points'        => (int)$row['points'],
            'commission'    => (float)$cs,
        ]);
    }

    public function transactions(Request $req): void
    {
        $u = Auth::require($req);
        $rows = Database::all(
            "SELECT id, kind, amount, balance_after, reference, note, created_at
             FROM wallet_txns WHERE user_id = ? ORDER BY id DESC LIMIT 100",
            [$u['user_id']]
        );
        Response::ok($rows);
    }

    public function recharge(Request $req): void
    {
        $u = Auth::require($req);
        $amount = (float)$req->input('amount', 0);
        $gw     = (string)$req->input('gateway', 'manual');
        if ($amount < 10) Response::fail('Minimum recharge 10');
        try {
            $row = PaymentService::createRecharge((int)$u['user_id'], $amount, $gw);
        } catch (\Throwable $e) {
            Response::fail($e->getMessage());
        }
        Response::ok($row);
    }

    public function submitProof(Request $req): void
    {
        $u = Auth::require($req);
        $orderNo = (string)$req->input('order_no');
        $ref     = (string)$req->input('gateway_ref');
        $proof   = (string)$req->input('proof_image');
        if (!$orderNo) Response::fail('order_no required');

        Database::q(
            "UPDATE payments SET gateway_ref = ?, proof_image = ?
             WHERE order_no = ? AND user_id = ?",
            [$ref, $proof, $orderNo, $u['user_id']]
        );
        Response::ok();
    }

    public function withdraw(Request $req): void
    {
        $u = Auth::require($req);
        $amount = (float)$req->input('amount', 0);
        $method = (string)$req->input('method', 'usdt');
        $payee  = $req->input('payee_info', []);
        $min = (float)(Database::val("SELECT `value` FROM settings WHERE `key`='min_withdraw'") ?: 100);

        if ($amount < $min) Response::fail("Minimum withdraw $min");
        Database::tx(function () use ($u, $amount, $method, $payee) {
            $bal = (float)Database::val("SELECT balance FROM users WHERE id = ? FOR UPDATE", [$u['user_id']]);
            if ($bal < $amount) throw new \RuntimeException('Insufficient balance');
            $newBal = round($bal - $amount, 2);
            Database::q("UPDATE users SET balance = ? WHERE id = ?", [$newBal, $u['user_id']]);
            Database::q(
                "INSERT INTO wallet_txns (user_id, kind, amount, balance_after, note)
                 VALUES (?, 'withdraw', ?, ?, 'Withdraw request')",
                [$u['user_id'], -$amount, $newBal]
            );
            Database::q(
                "INSERT INTO withdrawals (user_id, amount, method, payee_info, status)
                 VALUES (?, ?, ?, ?, 'pending')",
                [$u['user_id'], $amount, $method, json_encode($payee)]
            );
        });
        Response::ok();
    }
}
