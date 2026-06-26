<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\BargainService;

class BargainController
{
    /** POST /api/bargain/start  body: { product_id } — owner starts a session. */
    public function start(Request $req): void
    {
        $u = Auth::require($req);
        $pid = (int)$req->input('product_id');
        if ($pid <= 0) Response::fail('product_id required');
        try {
            $r = BargainService::start((int)$u['user_id'], $pid);
        } catch (\Throwable $e) {
            Response::fail($e->getMessage());
        }
        Response::ok($r);
    }

    /** GET /api/bargain/:token — public read of a session (used by friends opening the share link). */
    public function get(Request $req, array $params): void
    {
        $token = (string)($params['token'] ?? '');
        if ($token === '') Response::fail('token required');
        $s = BargainService::getByToken($token);
        if (!$s) Response::fail('not found', 404);

        // Tell the caller whether they've already helped (so the UI can hide the cut button).
        $me = Auth::userFromRequest($req);
        $alreadyHelped = false;
        $isOwner = false;
        if ($me) {
            $isOwner = ((int)$me['user_id'] === (int)$s['user_id']);
            if (!$isOwner) {
                $alreadyHelped = (bool)Database::one(
                    "SELECT id FROM bargain_helps WHERE session_id = ? AND helper_user_id = ?",
                    [(int)$s['id'], (int)$me['user_id']]
                );
            }
        }
        $s['viewer_is_owner']      = $isOwner;
        $s['viewer_already_helped'] = $alreadyHelped;
        Response::ok($s);
    }

    /** POST /api/bargain/help  body: { token } — logged-in friend cuts a slice. */
    public function help(Request $req): void
    {
        $u = Auth::require($req);
        $token = (string)$req->input('token');
        if ($token === '') Response::fail('token required');
        try {
            $r = BargainService::help((int)$u['user_id'], $token);
        } catch (\Throwable $e) {
            Response::fail($e->getMessage());
        }
        Response::ok($r);
    }

    /** GET /api/me/bargains — caller's own sessions, newest first. */
    public function mine(Request $req): void
    {
        $u = Auth::require($req);
        $rows = BargainService::listMine((int)$u['user_id']);
        Response::ok($rows);
    }
}
