<?php

use App\Http\Controllers\Api\V1\Admin\Auth\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\Company\BankController as AdminCompanyBankController;
use App\Http\Controllers\Api\V1\Admin\Company\WarehouseController as AdminWarehouseController;
use App\Http\Controllers\Api\V1\Admin\Customer\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\V1\Admin\Dashboard\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\Inventory\ShippingController as AdminShippingController;
use App\Http\Controllers\Api\V1\Admin\Inventory\StockAdjustmentController as AdminStockAdjustmentController;
use App\Http\Controllers\Api\V1\Admin\Inventory\StockController as AdminWarehouseStockController;
use App\Http\Controllers\Api\V1\Admin\Inventory\StockMutationController as AdminStockMutationController;
use App\Http\Controllers\Api\V1\Admin\Partnership\DistributorController as AdminDistributorController;
use App\Http\Controllers\Api\V1\Admin\Partnership\GenealogyController as AdminGenealogyController;
use App\Http\Controllers\Api\V1\Admin\Partnership\MemberController as AdminMemberController;
use App\Http\Controllers\Api\V1\Admin\Partnership\MemberDowngradeController as AdminMemberDowngradeController;
use App\Http\Controllers\Api\V1\Admin\Partnership\MemberStockController as AdminMemberStockController;
use App\Http\Controllers\Api\V1\Admin\Partnership\RegistrationController as AdminRegistrationController;
use App\Http\Controllers\Api\V1\Admin\Partnership\StockistController as AdminStockistController;
use App\Http\Controllers\Api\V1\Admin\Partnership\UpgradeController as AdminUpgradeController;
use App\Http\Controllers\Api\V1\Admin\Product\CategoryController as AdminProductCategoryController;
use App\Http\Controllers\Api\V1\Admin\Product\PriceController as AdminProductPriceController;
use App\Http\Controllers\Api\V1\Admin\Product\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\Report\CashIncomeReportController as AdminCashIncomeReportController;
use App\Http\Controllers\Api\V1\Admin\Report\MemberBatchStockReportController as AdminMemberBatchStockReportController;
use App\Http\Controllers\Api\V1\Admin\Report\PartnershipReportController as AdminPartnershipReportController;
use App\Http\Controllers\Api\V1\Admin\Report\PartnershipSalesController as AdminPartnershipSalesController;
use App\Http\Controllers\Api\V1\Admin\Report\SalesReportController as AdminSalesReportController;
use App\Http\Controllers\Api\V1\Admin\Report\StockReportController as AdminStockReportController;
use App\Http\Controllers\Api\V1\Admin\Reward\AnnualRewardController as AdminAnnualRewardController;
use App\Http\Controllers\Api\V1\Admin\Reward\MonthlyRewardController as AdminMonthlyRewardController;
use App\Http\Controllers\Api\V1\Admin\Reward\PointAchievementController as AdminPointAchievementController;
use App\Http\Controllers\Api\V1\Admin\Reward\SharingProfitController as AdminSharingProfitController;
use App\Http\Controllers\Api\V1\Admin\Reward\SharingProfitHistoryController as AdminSharingProfitHistoryController;
use App\Http\Controllers\Api\V1\Admin\Reward\StockistRewardController as AdminStockistRewardController;
use App\Http\Controllers\Api\V1\Admin\Stc\BalanceController as AdminStcBalanceController;
use App\Http\Controllers\Api\V1\Admin\Stc\TopUpController as AdminStcTopUpController;
use App\Http\Controllers\Api\V1\Admin\System\AdministratorController as AdminAdministratorController;
use App\Http\Controllers\Api\V1\Admin\System\AuditTrailController as AdminAuditTrailController;
use App\Http\Controllers\Api\V1\Admin\System\CommissionConfigController as AdminCommissionConfigController;
use App\Http\Controllers\Api\V1\Admin\System\ConfigController as AdminConfigController;
use App\Http\Controllers\Api\V1\Admin\System\MemberLevelController as AdminMemberLevelController;
use App\Http\Controllers\Api\V1\Admin\System\MenuController as AdminMenuManagementController;
use App\Http\Controllers\Api\V1\Admin\System\ProfileController as AdminProfileController;
use App\Http\Controllers\Api\V1\Admin\System\RoleController as AdminRoleController;
use App\Http\Controllers\Api\V1\Admin\Transaction\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\Transaction\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\Transaction\ReturnController as AdminReturnController;
use App\Http\Controllers\Api\V1\Callback\StcShippingController;
use App\Http\Controllers\Api\V1\Media\UploadController as MediaUploadController;
use App\Http\Controllers\Api\V1\Member\Account\AddressController as MemberAddressController;
use App\Http\Controllers\Api\V1\Member\Account\BankController as MemberBankController;
use App\Http\Controllers\Api\V1\Member\Account\ProfileController as MemberProfileController;
use App\Http\Controllers\Api\V1\Member\Auth\AuthController as MemberAuthController;
use App\Http\Controllers\Api\V1\Member\Dashboard\DashboardController as MemberDashboardController;
use App\Http\Controllers\Api\V1\Member\Inventory\NotificationController as MemberNotificationController;
use App\Http\Controllers\Api\V1\Member\Inventory\StockAdjustmentController as MemberStockAdjustmentController;
use App\Http\Controllers\Api\V1\Member\Inventory\StockController as MemberStockController;
use App\Http\Controllers\Api\V1\Member\Inventory\StockMutationController as MemberStockMutationController;
use App\Http\Controllers\Api\V1\Member\Network\GenealogyController as MemberGenealogyController;
use App\Http\Controllers\Api\V1\Member\Network\RegistrationController as MemberRegistrationController;
use App\Http\Controllers\Api\V1\Member\Purchase\CatalogController as MemberCatalogController;
use App\Http\Controllers\Api\V1\Member\Purchase\GoodsReceiveController as MemberGoodsReceiveController;
use App\Http\Controllers\Api\V1\Member\Purchase\OrderController as MemberPurchaseOrderController;
use App\Http\Controllers\Api\V1\Member\Purchase\PaymentController as MemberPurchasePaymentController;
use App\Http\Controllers\Api\V1\Member\Return\ReturnController as MemberReturnController;
use App\Http\Controllers\Api\V1\Member\Reward\AnnualRewardController as MemberAnnualRewardController;
use App\Http\Controllers\Api\V1\Member\Reward\MonthlyRewardController as MemberMonthlyRewardController;
use App\Http\Controllers\Api\V1\Member\Reward\SharingProfitController as MemberSharingProfitController;
use App\Http\Controllers\Api\V1\Member\Reward\StockistRewardController as MemberStockistRewardController;
use App\Http\Controllers\Api\V1\Member\SalesOrder\CustomerController as MemberSaleCustomerController;
use App\Http\Controllers\Api\V1\Member\SalesOrder\OrderController as MemberSaleOrderController;
use App\Http\Controllers\Api\V1\Member\SalesOrder\PaymentController as MemberSalePaymentController;
use App\Http\Controllers\Api\V1\Member\SalesOrder\ShippingController as MemberSaleShippingController;
use App\Http\Controllers\Api\V1\Member\Shipping\ShippingController as MemberShippingController;
use App\Http\Controllers\Api\V1\Reference\ReferenceController;
use Illuminate\Support\Facades\Route;

$mediaUploadRoutes = function (): void {
    Route::post('media/uploads', [MediaUploadController::class, 'store']);
    Route::get('media/uploads/{media}', [MediaUploadController::class, 'show']);
    Route::post('media/uploads/{media}/chunks/{chunk}', [MediaUploadController::class, 'chunk'])
        ->whereNumber('chunk');
    Route::post('media/uploads/{media}/complete', [MediaUploadController::class, 'complete']);
    Route::get('media/uploads/{media}/content', [MediaUploadController::class, 'content']);
    Route::delete('media/uploads/{media}', [MediaUploadController::class, 'destroy']);
};

Route::prefix('v1')->middleware('throttle:dny-api')->group(function () use ($mediaUploadRoutes): void {
    Route::post('callbacks/stc/shipping', StcShippingController::class);

    Route::get('media/public/{media}/content.{extension}', [MediaUploadController::class, 'publicContent'])
        ->whereAlphaNumeric('extension')
        ->name('api.v1.media.public-content');

    foreach (['admin', 'member'] as $audience) {
        Route::get("{$audience}/media/uploads/{media}/content.{extension}", [MediaUploadController::class, 'content'])
            ->middleware('signed')
            ->whereAlphaNumeric('extension')
            ->name("api.v1.{$audience}.media.content");
    }

    Route::prefix('references')->controller(ReferenceController::class)->group(function (): void {
        Route::get('provinces', 'provinces');
        Route::get('cities', 'cities');
        Route::get('districts', 'districts');
        Route::get('subdistricts', 'subdistricts');
        Route::get('banks', 'banks');
        Route::get('countries', 'countries');
    });

    Route::prefix('admin')->group(function () use ($mediaUploadRoutes): void {
        Route::post('auth/login', [AdminAuthController::class, 'login'])
            ->middleware('throttle:dny-login');
        Route::post('auth/refresh', [AdminAuthController::class, 'refresh'])
            ->middleware('throttle:dny-login');
        Route::post('auth/forgot-password', [AdminAuthController::class, 'forgotPassword'])
            ->middleware('throttle:dny-password-reset');
        Route::post('auth/reset-password', [AdminAuthController::class, 'resetPassword'])
            ->middleware('throttle:dny-password-reset');

        Route::middleware(['auth:admin_api', 'admin.active'])->group(function () use ($mediaUploadRoutes): void {
            Route::get('auth/me', [AdminAuthController::class, 'me']);
            Route::post('auth/logout', [AdminAuthController::class, 'logout']);
            Route::get('menus', [AdminAuthController::class, 'menus']);
            Route::group([], $mediaUploadRoutes);

            Route::get('profile', [AdminProfileController::class, 'show']);
            Route::put('profile', [AdminProfileController::class, 'update']);
            Route::put('profile/password', [AdminProfileController::class, 'updatePassword']);

            Route::get('dashboard/action-summary', [AdminDashboardController::class, 'actionSummary']);
            Route::get('dashboard/analytics', [AdminDashboardController::class, 'index']);
            Route::get('user-analytics/statistics', [AdminDashboardController::class, 'statistics']);

            Route::apiResource('product/categories', AdminProductCategoryController::class)
                ->parameters(['categories' => 'category']);
            Route::get('product/prices', [AdminProductPriceController::class, 'index']);
            Route::post('product/prices/bulk', [AdminProductPriceController::class, 'bulkUpdate']);
            Route::get('product/prices/{product}', [AdminProductPriceController::class, 'show']);
            Route::put('product/prices/{product}', [AdminProductPriceController::class, 'update']);
            Route::apiResource('products', AdminProductController::class);
            Route::apiResource('partnership/distributors', AdminDistributorController::class)
                ->only(['index', 'store', 'show'])
                ->parameters(['distributors' => 'distributor']);
            Route::get('partnership/members', [AdminMemberController::class, 'index']);
            Route::get('partnership/members/{member}/deactivation-options', [AdminMemberController::class, 'deactivationOptions']);
            Route::post('partnership/members/{member}/deactivate', [AdminMemberController::class, 'deactivate']);
            Route::post('partnership/members/{member}/reset-password', [AdminMemberController::class, 'resetPassword']);
            Route::get('partnership/members/{member}', [AdminMemberController::class, 'show']);
            Route::put('partnership/members/{member}', [AdminMemberController::class, 'update']);
            Route::get('partnership/genealogy', [AdminGenealogyController::class, 'index']);
            Route::get('partnership/upgrades', [AdminUpgradeController::class, 'index']);
            Route::get('partnership/upgrades/{upgrade}', [AdminUpgradeController::class, 'show']);
            Route::post('partnership/upgrades/{upgrade}/approve', [AdminUpgradeController::class, 'approve']);
            Route::post('partnership/upgrades/{upgrade}/reject', [AdminUpgradeController::class, 'reject']);
            Route::get('partnership/downgrades', [AdminMemberDowngradeController::class, 'index']);
            Route::get(
                'partnership/downgrades/sponsor-options',
                [AdminMemberDowngradeController::class, 'sponsorOptions']
            );
            Route::get('partnership/downgrades/{downgrade}', [AdminMemberDowngradeController::class, 'show']);
            Route::post(
                'partnership/members/{member}/downgrade',
                [AdminMemberDowngradeController::class, 'store']
            );
            Route::get('partnership/registrations/options', [AdminRegistrationController::class, 'options']);
            Route::get('partnership/registrations', [AdminRegistrationController::class, 'index']);
            Route::post('partnership/registrations', [AdminRegistrationController::class, 'store']);
            Route::get('partnership/registrations/{registration}', [AdminRegistrationController::class, 'show']);
            Route::post(
                'partnership/registrations/{registration}/approve',
                [AdminRegistrationController::class, 'approve']
            );
            Route::post(
                'partnership/registrations/{registration}/reject',
                [AdminRegistrationController::class, 'reject']
            );
            Route::get('partnership/stockists/options', [AdminStockistController::class, 'options']);
            Route::apiResource('partnership/stockists', AdminStockistController::class);
            Route::apiResource('company/banks', AdminCompanyBankController::class)
                ->parameters(['banks' => 'companyBank']);
            Route::apiResource('company/warehouses', AdminWarehouseController::class);
            Route::get('customers', [AdminCustomerController::class, 'index']);
            Route::get('customers/{customer}', [AdminCustomerController::class, 'show']);
            Route::get('inventory/stocks', [AdminWarehouseStockController::class, 'index']);
            Route::get('inventory/stocks/{stock}', [AdminWarehouseStockController::class, 'show']);
            Route::get('inventory/mutations', [AdminStockMutationController::class, 'index']);
            Route::get('inventory/adjustments', [AdminStockAdjustmentController::class, 'index']);
            Route::post('inventory/adjustments', [AdminStockAdjustmentController::class, 'store']);
            Route::get('inventory/adjustments/{adjustment}', [AdminStockAdjustmentController::class, 'show']);
            Route::get('inventory/shipments', [AdminShippingController::class, 'index']);
            Route::get('inventory/shipments/express/schedules', [AdminShippingController::class, 'schedules']);
            Route::match(
                ['get', 'post'],
                'inventory/shipments/{trx}/couriers',
                [AdminShippingController::class, 'couriers'],
            );
            Route::get('inventory/shipments/{trx}', [AdminShippingController::class, 'show']);
            Route::post('inventory/shipments/{trx}/tracking', [AdminShippingController::class, 'tracking']);
            Route::post('inventory/shipments/{trx}/ship', [AdminShippingController::class, 'ship']);
            Route::get('partnership/member-stocks', [AdminMemberStockController::class, 'index']);
            Route::get('rewards/annual', [AdminAnnualRewardController::class, 'index']);
            Route::get('rewards/annual/{annualReward}', [AdminAnnualRewardController::class, 'show']);
            Route::get('rewards/monthly', [AdminMonthlyRewardController::class, 'index']);
            Route::get('rewards/monthly/{monthlyReward}', [AdminMonthlyRewardController::class, 'show']);
            Route::post('rewards/monthly/{monthlyReward}/process', [AdminMonthlyRewardController::class, 'process']);
            Route::get('rewards/stockists', [AdminStockistRewardController::class, 'index']);
            Route::get('rewards/stockists/{stockistReward}', [AdminStockistRewardController::class, 'show']);
            Route::get('rewards/point-achievements', [AdminPointAchievementController::class, 'index']);

            Route::get('rewards/sharing-profits/history', [AdminSharingProfitHistoryController::class, 'index']);
            Route::get('rewards/sharing-profits/history/{id}', [AdminSharingProfitHistoryController::class, 'show']);
            Route::get('rewards/sharing-profits', [AdminSharingProfitController::class, 'index']);
            Route::post('rewards/sharing-profits/approve', [AdminSharingProfitController::class, 'approve']);
            Route::post('rewards/sharing-profits/transfer', [AdminSharingProfitController::class, 'transfer']);
            Route::get('rewards/sharing-profits/{upline}', [AdminSharingProfitController::class, 'show']);

            Route::get('stc/balance', [AdminStcBalanceController::class, 'show']);
            Route::get('stc/mutations', [AdminStcBalanceController::class, 'mutations']);
            Route::get('stc/top-ups', [AdminStcTopUpController::class, 'index']);
            Route::get('stc/top-up-options', [AdminStcTopUpController::class, 'options']);

            Route::get('transactions/orders/summary', [AdminOrderController::class, 'summary']);
            Route::get('transactions/orders', [AdminOrderController::class, 'index']);
            Route::get('transactions/orders/{trx}', [AdminOrderController::class, 'show']);
            Route::get('transactions/orders/{trx}/document', [AdminOrderController::class, 'document']);
            Route::post(
                'transactions/orders/{trx}/stock-screening/approve',
                [AdminOrderController::class, 'approveStockScreening'],
            );
            Route::post(
                'transactions/orders/{trx}/stock-screening/reject',
                [AdminOrderController::class, 'rejectStockScreening'],
            );
            Route::get('transactions/payments', [AdminPaymentController::class, 'index']);
            Route::get('transactions/payments/{payment}', [AdminPaymentController::class, 'show']);
            Route::post('transactions/payments/{payment}/approve', [AdminPaymentController::class, 'approve']);
            Route::post('transactions/payments/{payment}/reject', [AdminPaymentController::class, 'reject']);
            Route::get('transactions/returns', [AdminReturnController::class, 'index']);
            Route::get('transactions/returns/{return}', [AdminReturnController::class, 'show']);
            Route::get(
                'transactions/returns/{return}/shipping/{referenceType}/couriers',
                [AdminReturnController::class, 'couriers'],
            )->whereIn('referenceType', ['return_company', 'return_replacement']);
            Route::post(
                'transactions/returns/{return}/shipping/{referenceType}/couriers',
                [AdminReturnController::class, 'couriers'],
            )->whereIn('referenceType', ['return_company', 'return_replacement']);
            Route::get(
                'transactions/returns/{return}/shipping/{referenceType}/schedules',
                [AdminReturnController::class, 'schedules'],
            )->whereIn('referenceType', ['return_company', 'return_replacement']);
            Route::post('transactions/returns/{return}/approve', [AdminReturnController::class, 'approve']);
            Route::post('transactions/returns/{return}/reject', [AdminReturnController::class, 'reject']);
            Route::post('transactions/returns/{return}/receive', [AdminReturnController::class, 'receive']);
            Route::post('transactions/returns/{return}/replacement/ship', [AdminReturnController::class, 'shipReplacement']);

            Route::get('reports/sales', [AdminSalesReportController::class, 'index']);
            Route::get('reports/partnerships', [AdminPartnershipReportController::class, 'index']);
            Route::get('reports/partnership-sales', [AdminPartnershipSalesController::class, 'index']);
            Route::get('reports/partnership-sales/{trx}', [AdminPartnershipSalesController::class, 'show']);
            Route::get('reports/stocks', [AdminStockReportController::class, 'index']);
            Route::get('reports/member-stock-batches', [AdminMemberBatchStockReportController::class, 'index']);
            Route::get('reports/cash-income', [AdminCashIncomeReportController::class, 'index']);

            Route::get('system/roles/{role}/privileges', [AdminRoleController::class, 'privileges']);
            Route::put('system/roles/{role}/privileges', [AdminRoleController::class, 'syncPrivileges']);
            Route::apiResource('system/roles', AdminRoleController::class);
            Route::put(
                'system/administrators/{administrator}/password',
                [AdminAdministratorController::class, 'updatePassword']
            );
            Route::apiResource('system/administrators', AdminAdministratorController::class);
            Route::apiResource('system/configs', AdminConfigController::class)
                ->only(['index', 'show', 'update']);
            Route::apiResource('system/commission-configs', AdminCommissionConfigController::class)
                ->only(['index', 'update'])
                ->parameters(['commission-configs' => 'config']);
            Route::apiResource('system/member-levels', AdminMemberLevelController::class)
                ->only(['index', 'show', 'update'])
                ->parameters(['member-levels' => 'memberLevel']);
            Route::apiResource('system/audit-trails', AdminAuditTrailController::class)
                ->only(['index', 'show'])
                ->parameters(['audit-trails' => 'auditTrail']);
            Route::get('system/menus/tree', [AdminMenuManagementController::class, 'tree']);
            Route::apiResource('system/menus', AdminMenuManagementController::class);
        });
    });

    Route::prefix('member')->group(function () use ($mediaUploadRoutes): void {
        Route::post('auth/login', [MemberAuthController::class, 'login'])
            ->middleware('throttle:dny-login');
        Route::post('auth/refresh', [MemberAuthController::class, 'refresh'])
            ->middleware('throttle:dny-login');
        Route::post('auth/forgot-password', [MemberAuthController::class, 'forgotPassword'])
            ->middleware('throttle:dny-password-reset');
        Route::post('auth/reset-password', [MemberAuthController::class, 'resetPassword'])
            ->middleware('throttle:dny-password-reset');

        Route::middleware(['auth:member_api', 'member.active'])->group(function () use ($mediaUploadRoutes): void {
            Route::get('auth/me', [MemberAuthController::class, 'me']);
            Route::post('auth/logout', [MemberAuthController::class, 'logout']);
            Route::group([], $mediaUploadRoutes);

            Route::get('profile', [MemberProfileController::class, 'show']);
            Route::put('profile', [MemberProfileController::class, 'update']);
            Route::put('profile/photo', [MemberProfileController::class, 'updatePhoto']);
            Route::put('profile/password', [MemberProfileController::class, 'updatePassword']);
            Route::get('dashboard', [MemberDashboardController::class, 'index']);

            Route::apiResource('addresses', MemberAddressController::class);
            Route::put('addresses/{address}/default', [MemberAddressController::class, 'setDefault']);

            Route::apiResource('banks', MemberBankController::class);
            Route::put('banks/{bank}/default', [MemberBankController::class, 'setDefault']);
            Route::get('purchases/catalog/categories', [MemberCatalogController::class, 'categories']);
            Route::get('purchases/catalog/products', [MemberCatalogController::class, 'index']);
            Route::get('purchases/catalog/products/{product}', [MemberCatalogController::class, 'show']);
            Route::get('purchases/options', [MemberPurchaseOrderController::class, 'options']);
            Route::get('purchases/orders', [MemberPurchaseOrderController::class, 'index']);
            Route::get('purchases/orders/summary', [MemberPurchaseOrderController::class, 'summary']);
            Route::post('purchases/orders', [MemberPurchaseOrderController::class, 'store']);
            Route::get('purchases/orders/{trx}', [MemberPurchaseOrderController::class, 'show']);
            Route::post('purchases/orders/{trx}/cancel', [MemberPurchaseOrderController::class, 'cancel']);
            Route::post('purchases/orders/{trx}/payment', [MemberPurchasePaymentController::class, 'store']);
            Route::get('purchases/goods-receipts', [MemberGoodsReceiveController::class, 'index']);
            Route::get('purchases/goods-receipts/{trx}', [MemberGoodsReceiveController::class, 'show']);
            Route::post(
                'purchases/goods-receipts/{trx}/confirm',
                [MemberGoodsReceiveController::class, 'confirm']
            );
            Route::get('network/total-downlines', [MemberGenealogyController::class, 'totalDownlines']);
            Route::get('network/genealogy', [MemberGenealogyController::class, 'index']);
            Route::get('network/registrations/options', [MemberRegistrationController::class, 'options']);
            Route::get('network/registrations', [MemberRegistrationController::class, 'index']);
            Route::post('network/registrations', [MemberRegistrationController::class, 'store']);
            Route::get('network/registrations/{registration}', [MemberRegistrationController::class, 'show']);
            Route::get('inventory/stocks', [MemberStockController::class, 'index']);
            Route::get('inventory/mutations', [MemberStockMutationController::class, 'index']);
            Route::get('inventory/adjustments', [MemberStockAdjustmentController::class, 'index']);
            Route::post('inventory/adjustments', [MemberStockAdjustmentController::class, 'store']);
            Route::get('inventory/adjustments/{adjustment}', [MemberStockAdjustmentController::class, 'show']);
            Route::get('notifications', [MemberNotificationController::class, 'index']);
            Route::post('notifications/{notification}/read', [MemberNotificationController::class, 'read']);
            Route::get('inventory/returns/eligible-receipts', [MemberReturnController::class, 'eligibleReceipts']);
            Route::post('inventory/returns/shipping/couriers', [MemberReturnController::class, 'couriers']);
            Route::post('inventory/returns/shipping/schedules', [MemberReturnController::class, 'schedules']);
            Route::get('inventory/returns/summary', [MemberReturnController::class, 'summary']);
            Route::get('inventory/returns', [MemberReturnController::class, 'index']);
            Route::post('inventory/returns', [MemberReturnController::class, 'store']);
            Route::get('inventory/returns/{return}', [MemberReturnController::class, 'show']);
            Route::get('inventory/returns/{return}/shipping/schedules', [MemberReturnController::class, 'shippingSchedules']);
            Route::post('inventory/returns/{return}/ship', [MemberReturnController::class, 'ship']);
            Route::post('inventory/returns/{return}/replacement/receive', [MemberReturnController::class, 'complete']);
            Route::get('rewards/annual', [MemberAnnualRewardController::class, 'index']);
            Route::get('rewards/monthly', [MemberMonthlyRewardController::class, 'index']);
            Route::get('rewards/monthly/downlines', [MemberMonthlyRewardController::class, 'downlines']);
            Route::post(
                'rewards/monthly/downlines/{monthly_reward}/approve',
                [MemberMonthlyRewardController::class, 'approveDownline'],
            );
            Route::get('rewards/monthly/growth', [MemberMonthlyRewardController::class, 'growth']);
            Route::get('rewards/monthly/{monthly_reward}', [MemberMonthlyRewardController::class, 'show']);
            Route::get('rewards/stockists', [MemberStockistRewardController::class, 'index']);
            Route::get('rewards/stockists/{stockist_reward}', [MemberStockistRewardController::class, 'show']);
            Route::get('rewards/sharing-profits', [MemberSharingProfitController::class, 'index']);
            Route::get('rewards/sharing-profits/{sharingProfit}', [MemberSharingProfitController::class, 'show']);
            Route::post('shipping/express/rates', [MemberShippingController::class, 'rates']);
            Route::post('shipping/instant/rates', [MemberShippingController::class, 'instantRates']);
            Route::get('sales/options', [MemberSaleOrderController::class, 'options']);
            Route::get('sales/catalog/products', [MemberSaleOrderController::class, 'products']);
            Route::get('sales/customers/options', [MemberSaleCustomerController::class, 'options']);
            Route::post('sales/customers', [MemberSaleCustomerController::class, 'store']);
            Route::get('sales/orders', [MemberSaleOrderController::class, 'index']);
            Route::get('sales/orders/summary', [MemberSaleOrderController::class, 'summary']);
            Route::post('sales/orders', [MemberSaleOrderController::class, 'store']);
            Route::get('sales/orders/{trx}', [MemberSaleOrderController::class, 'show']);
            Route::post('sales/orders/{trx}/cancel', [MemberSaleOrderController::class, 'cancel']);
            Route::post('sales/orders/{trx}/payment/approve', [MemberSalePaymentController::class, 'approve']);
            Route::post('sales/orders/{trx}/payment/reject', [MemberSalePaymentController::class, 'reject']);
            Route::post('sales/orders/{trx}/ship', [MemberSaleShippingController::class, 'store']);
        });
    });
});
