<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/Config.php';
require __DIR__ . '/../app/Core/Database.php';
require __DIR__ . '/../app/Core/Request.php';
require __DIR__ . '/../app/Core/Response.php';
require __DIR__ . '/../app/Core/Router.php';
require __DIR__ . '/../app/Core/Auth.php';
require __DIR__ . '/../app/Core/Helpers.php';
require __DIR__ . '/../app/Core/I18n.php';
require __DIR__ . '/../app/Services/DrawService.php';
require __DIR__ . '/../app/Services/CommissionService.php';
require __DIR__ . '/../app/Services/GroupBonusService.php';
require __DIR__ . '/../app/Services/PaymentService.php';
require __DIR__ . '/../app/Services/BargainService.php';
require __DIR__ . '/../app/Controllers/AuthController.php';
require __DIR__ . '/../app/Controllers/ProductController.php';
require __DIR__ . '/../app/Controllers/PurchaseController.php';
require __DIR__ . '/../app/Controllers/WalletController.php';
require __DIR__ . '/../app/Controllers/MiscController.php';
require __DIR__ . '/../app/Controllers/EngagementController.php';
require __DIR__ . '/../app/Controllers/BargainController.php';
require __DIR__ . '/../app/Controllers/AdminController.php';

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

Config::load();

if (Config::get('app.debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../storage/logs/php.log');
}

set_exception_handler(function (\Throwable $e) {
    error_log('[' . date('c') . '] ' . $e);
    if (Config::get('app.debug')) {
        Response::fail($e->getMessage() . "\n" . $e->getTraceAsString(), 500);
    }
    Response::fail('Server error', 500);
});

header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') exit;

$req = new Request();

/* Serve static H5 / admin html pages directly when the path matches. */
$static = __DIR__ . $req->path;
if ($req->path !== '/' && file_exists($static) && is_file($static)) {
    // Apache should have handled this; for built-in server fallback:
    $mime = match (pathinfo($static, PATHINFO_EXTENSION)) {
        'html' => 'text/html', 'css' => 'text/css', 'js' => 'application/javascript',
        'svg' => 'image/svg+xml', 'jpg','jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'json' => 'application/json', default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    readfile($static); exit;
}

$r = new Router();

// Root / -> h5
$r->get('/', fn() => Response::redirect('/h5/index.html'));
$r->get('/h5', fn() => Response::redirect('/h5/index.html'));

// ---- auth ----
$r->post('/api/auth/register',  [App\Controllers\AuthController::class, 'register']);
$r->post('/api/auth/login',     [App\Controllers\AuthController::class, 'login']);
$r->post('/api/auth/logout',    [App\Controllers\AuthController::class, 'logout']);
$r->get( '/api/auth/me',        [App\Controllers\AuthController::class, 'me']);
$r->post('/api/auth/profile',   [App\Controllers\AuthController::class, 'updateProfile']);

// ---- products ----
$r->get('/api/home',                  [App\Controllers\ProductController::class, 'home']);
$r->get('/api/products',              [App\Controllers\ProductController::class, 'list']);
$r->get('/api/periods/:id',           [App\Controllers\ProductController::class, 'detail']);
$r->get('/api/reveals',               [App\Controllers\ProductController::class, 'reveals']);

// ---- purchase ----
$r->post('/api/periods/:id/buy',      [App\Controllers\PurchaseController::class, 'buy']);
$r->get('/api/me/orders',             [App\Controllers\PurchaseController::class, 'myOrders']);
$r->get('/api/me/wins',               [App\Controllers\PurchaseController::class, 'myWins']);
$r->post('/api/me/wins/:id/claim',    [App\Controllers\PurchaseController::class, 'claimWin']);
$r->post('/api/me/wins/:id/upload-proof', [App\Controllers\EngagementController::class, 'uploadProof']);
$r->get( '/api/me/proofs',            [App\Controllers\EngagementController::class, 'myProofs']);
$r->get( '/api/me/checkin/state',     [App\Controllers\EngagementController::class, 'checkinState']);
$r->post('/api/me/checkin',           [App\Controllers\EngagementController::class, 'checkin']);
$r->post('/api/me/social-share',      [App\Controllers\EngagementController::class, 'submitShare']);
$r->get( '/api/me/social-shares',     [App\Controllers\EngagementController::class, 'mySocialShares']);
$r->get('/api/periods/:id/my-codes',  [App\Controllers\PurchaseController::class, 'myCodes']);

// ---- wallet ----
$r->get( '/api/wallet/summary',       [App\Controllers\WalletController::class, 'summary']);
$r->get( '/api/wallet/transactions',  [App\Controllers\WalletController::class, 'transactions']);
$r->post('/api/wallet/recharge',      [App\Controllers\WalletController::class, 'recharge']);
$r->post('/api/wallet/proof',         [App\Controllers\WalletController::class, 'submitProof']);
$r->post('/api/wallet/withdraw',      [App\Controllers\WalletController::class, 'withdraw']);

// ---- misc ----
$r->get( '/api/me/addresses',         [App\Controllers\MiscController::class, 'addresses']);
$r->post('/api/me/addresses',         [App\Controllers\MiscController::class, 'addressSave']);
$r->delete('/api/me/addresses/:id',   [App\Controllers\MiscController::class, 'addressDelete']);
$r->get( '/api/me/favorites',         [App\Controllers\MiscController::class, 'favorites']);
$r->post('/api/me/favorites',         [App\Controllers\MiscController::class, 'favoriteToggle']);
$r->get( '/api/me/commissions',       [App\Controllers\MiscController::class, 'commissions']);
$r->get( '/api/me/team',              [App\Controllers\MiscController::class, 'team']);
$r->get( '/api/settings',             [App\Controllers\MiscController::class, 'settings']);
$r->post('/api/upload',               [App\Controllers\MiscController::class, 'upload']);

// ---- bargain ("砍一刀") ----
$r->post('/api/bargain/start',        [App\Controllers\BargainController::class, 'start']);
$r->post('/api/bargain/help',         [App\Controllers\BargainController::class, 'help']);
$r->get( '/api/bargain/:token',       [App\Controllers\BargainController::class, 'get']);
$r->get( '/api/me/bargains',          [App\Controllers\BargainController::class, 'mine']);

// ---- admin ----
$A = App\Controllers\AdminController::class;
$r->post('/api/admin/login',          [$A, 'login']);
$r->get( '/api/admin/dashboard',      [$A, 'dashboard']);
$r->get( '/api/admin/products',       [$A, 'listProducts']);
$r->post('/api/admin/products',       [$A, 'saveProduct']);
$r->delete('/api/admin/products/:id', [$A, 'deleteProduct']);
$r->get( '/api/admin/periods',        [$A, 'listPeriods']);
$r->post('/api/admin/periods/:id/draw', [$A, 'forceDraw']);
$r->get( '/api/admin/users',          [$A, 'listUsers']);
$r->post('/api/admin/users/adjust',   [$A, 'adjustUser']);
$r->post('/api/admin/users/status',   [$A, 'setUserStatus']);
$r->get( '/api/admin/payments',       [$A, 'listPayments']);
$r->post('/api/admin/payments/approve', [$A, 'approvePayment']);
$r->post('/api/admin/payments/reject',  [$A, 'rejectPayment']);
$r->get( '/api/admin/withdrawals',    [$A, 'listWithdrawals']);
$r->post('/api/admin/withdrawals/approve', [$A, 'approveWithdrawal']);
$r->post('/api/admin/withdrawals/reject',  [$A, 'rejectWithdrawal']);
$r->get( '/api/admin/winners',        [$A, 'listWinners']);
$r->post('/api/admin/winners/ship',   [$A, 'shipWinner']);
$r->post('/api/admin/winners/deliver',[$A, 'deliverWinner']);
$r->get( '/api/admin/settings',       [$A, 'settings']);
$r->post('/api/admin/settings',       [$A, 'settings']);
$r->get( '/api/admin/ranks',          [$A, 'listRanks']);
$r->post('/api/admin/ranks',          [$A, 'saveRank']);
$r->post('/api/admin/upload-site-logo', [$A, 'uploadSiteLogo']);
$r->get( '/api/admin/proofs',         [$A, 'listProofs']);
$r->post('/api/admin/proofs/approve', [$A, 'approveProof']);
$r->post('/api/admin/proofs/reject',  [$A, 'rejectProof']);
$r->get( '/api/admin/shares',         [$A, 'listShares']);
$r->post('/api/admin/shares/approve', [$A, 'approveShare']);
$r->post('/api/admin/shares/reject',  [$A, 'rejectShare']);

$r->dispatch($req);
