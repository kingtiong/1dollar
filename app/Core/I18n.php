<?php
namespace App\Core;

class I18n
{
    private static string $locale = 'zh';

    private static array $dict = [
        'home'              => ['zh' => '首页',         'en' => 'Home'],
        'all_products'      => ['zh' => '所有商品',     'en' => 'All Products'],
        'latest_reveals'    => ['zh' => '最新揭晓',     'en' => 'Latest Reveals'],
        'my_page'           => ['zh' => '我的主页',     'en' => 'My Page'],
        'search'            => ['zh' => '搜索',         'en' => 'Search'],
        'buy'               => ['zh' => '购买',         'en' => 'Buy'],
        'buy_now'           => ['zh' => '立即购买',     'en' => 'Buy Now'],
        'add_to_cart'       => ['zh' => '添加到购物车', 'en' => 'Add to Cart'],
        'participated'      => ['zh' => '已经参与',     'en' => 'Joined'],
        'total_needed'      => ['zh' => '总需求',       'en' => 'Total'],
        'remaining'         => ['zh' => '剩余',         'en' => 'Left'],
        'value'             => ['zh' => '价值',         'en' => 'Value'],
        'period'            => ['zh' => '期',           'en' => 'No.'],
        'winner'            => ['zh' => '获奖者',       'en' => 'Winner'],
        'login'             => ['zh' => '登录',         'en' => 'Login'],
        'logout'            => ['zh' => '退出登录',     'en' => 'Logout'],
        'register'          => ['zh' => '注册',         'en' => 'Register'],
    ];

    public static function setLocale(string $loc): void
    {
        self::$locale = in_array($loc, ['zh','en'], true) ? $loc : 'zh';
    }

    public static function t(string $key): string
    {
        return self::$dict[$key][self::$locale] ?? $key;
    }
}
