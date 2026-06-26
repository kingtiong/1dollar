// i18n — 中文 / English / සිංහල / বাংলা
window.I18N = (function () {
    const dict = {
        home:               { zh: '首页',                    en: 'Home',                          si: 'මුල් පිටුව',                 bn: 'হোম' },
        all:                { zh: '所有商品',                en: 'All Products',                  si: 'සියලුම නිෂ්පාදන',           bn: 'সকল পণ্য' },
        reveals:            { zh: '最新揭晓',                en: 'Latest Reveals',                si: 'නවතම ප්‍රතිඵල',            bn: 'সর্বশেষ ফলাফল' },
        me:                 { zh: '我的主页',                en: 'My Page',                       si: 'මගේ පිටුව',                bn: 'আমার পৃষ্ঠা' },
        search:             { zh: '搜索',                    en: 'Search',                        si: 'සොයන්න',                   bn: 'অনুসন্ধান' },
        favorite:           { zh: '喜欢',                    en: 'Favorite',                      si: 'ප්‍රියතම',                  bn: 'প্রিয়' },
        buy:                { zh: '购买',                    en: 'Buy',                           si: 'මිලදී ගන්න',                bn: 'কিনুন' },
        buy_now:            { zh: '立即购买',                en: 'Buy Now',                       si: 'දැන්ම මිලදී ගන්න',          bn: 'এখনই কিনুন' },
        add_cart:           { zh: '添加到购物车',            en: 'Add to Cart',                   si: 'කරත්තයට එක් කරන්න',         bn: 'কার্টে যোগ করুন' },
        joined:             { zh: '已经参与',                en: 'Joined',                        si: 'සහභාගි වී ඇත',              bn: 'যোগ দিয়েছেন' },
        total:              { zh: '总需求',                  en: 'Total',                         si: 'මුළු',                      bn: 'মোট' },
        remaining:          { zh: '剩余',                    en: 'Left',                          si: 'ඉතිරි',                     bn: 'বাকি' },
        value:              { zh: '价值',                    en: 'Value',                         si: 'වටිනාකම',                   bn: 'মূল্য' },
        period:             { zh: '期',                      en: 'No.',                           si: 'අංකය',                      bn: 'নং' },
        winner:             { zh: '获奖者',                  en: 'Winner',                        si: 'ජයග්‍රාහකයා',                bn: 'বিজয়ী' },
        lucky_code:         { zh: '幸运码',                  en: 'Lucky Code',                    si: 'වාසනා කේතය',                bn: 'লাকি কোড' },
        login:              { zh: '登录',                    en: 'Login',                         si: 'පිවිසෙන්න',                 bn: 'লগইন' },
        register:           { zh: '注册',                    en: 'Register',                      si: 'ලියාපදිංචි වන්න',           bn: 'নিবন্ধন' },
        logout:             { zh: '退出登录',                en: 'Logout',                        si: 'පිටවන්න',                   bn: 'লগআউট' },
        username:           { zh: '用户名',                  en: 'Username',                      si: 'පරිශීලක නාමය',              bn: 'ইউজারনেম' },
        password:           { zh: '密码',                    en: 'Password',                      si: 'මුරපදය',                    bn: 'পাসওয়ার্ড' },
        balance:            { zh: '幸运币',                  en: 'Lucky Coin',                    si: 'වාසනා කාසිය',               bn: 'লাকি কয়েন' },
        points:             { zh: '积分',                    en: 'Points',                        si: 'ලකුණු',                     bn: 'পয়েন্ট' },
        my_records:         { zh: '参与记录',                en: 'My Joins',                      si: 'මගේ සහභාගිත්වය',            bn: 'আমার অংশগ্রহণ' },
        my_wins:            { zh: '获得商品',                en: 'My Wins',                       si: 'මගේ ජයග්‍රහණ',              bn: 'আমার জয়' },
        my_favs:            { zh: '我的收藏',                en: 'Favorites',                     si: 'ප්‍රියතම',                  bn: 'প্রিয়' },
        wallet_recharge:    { zh: '我的幸运币/充值',         en: 'Wallet / Recharge',             si: 'පසුම්බිය / රීචාජ්',         bn: 'ওয়ালেট / রিচার্জ' },
        share:              { zh: '分享',                    en: 'Share',                         si: 'බෙදාගන්න',                  bn: 'শেয়ার' },
        share_note:         { zh: '与朋友分享，赚取 10% 佣金', en: 'Share & earn 10% commission', si: 'මිතුරන් සමඟ බෙදාගෙන 10% කොමිස් උපයන්න', bn: 'বন্ধুদের সাথে শেয়ার করুন ও 10% কমিশন আয় করুন' },
        addresses:          { zh: '地址管理',                en: 'Addresses',                     si: 'ලිපින',                     bn: 'ঠিকানা' },
        my_addr:            { zh: '我的收货地址',            en: 'My delivery address',           si: 'මගේ බෙදාහැරීමේ ලිපිනය',      bn: 'আমার ডেলিভারি ঠিকানা' },
        commissions:        { zh: '奖金&佣金',               en: 'Bonus & Commission',            si: 'ප්‍රසාද සහ කොමිස්',          bn: 'বোনাস ও কমিশন' },
        comm_note:          { zh: '兑换商品和邀请朋友的奖励', en: 'Rewards for referrals',        si: 'යොමුකිරීම් සඳහා ත්‍යාග',     bn: 'রেফারেলের জন্য পুরস্কার' },
        login_required:     { zh: '请先登录',                en: 'Please log in first',           si: 'කරුණාකර පළමුව පිවිසෙන්න',   bn: 'প্রথমে লগইন করুন' },
        please_login:       { zh: '请登录已以查看账户信息',  en: 'Log in to view your account',   si: 'ගිණුම් තොරතුරු බැලීමට පිවිසෙන්න', bn: 'অ্যাকাউন্ট দেখতে লগইন করুন' },
        guest:              { zh: '游客',                    en: 'Guest',                         si: 'අමුත්තා',                   bn: 'অতিথি' },
        amount:             { zh: '金额',                    en: 'Amount',                        si: 'මුදල',                      bn: 'পরিমাণ' },
        gateway:            { zh: '支付方式',                en: 'Payment Method',                si: 'ගෙවීම් ක්‍රමය',              bn: 'পেমেন্ট পদ্ধতি' },
        recharge:           { zh: '充值',                    en: 'Recharge',                      si: 'රීචාජ් කරන්න',              bn: 'রিচার্জ' },
        withdraw:           { zh: '提现',                    en: 'Withdraw',                      si: 'ආපසු ගන්න',                 bn: 'উত্তোলন' },
        all_records:        { zh: '所有参与记录',            en: 'All participation records',     si: 'සියලුම සහභාගිත්ව වාර්තා',    bn: 'সকল অংশগ্রহণের রেকর্ড' },
        trend:              { zh: '趋势图',                  en: 'Trend',                         si: 'ප්‍රවණතාව',                 bn: 'ট্রেন্ড' },
        details:            { zh: '图片和文字详细信息',      en: 'Details',                       si: 'විස්තර',                    bn: 'বিস্তারিত' },
        last_winner:        { zh: '上期获得者',              en: 'Last winner',                   si: 'අවසන් ජයග්‍රාහකයා',         bn: 'শেষ বিজয়ী' },
        slots:              { zh: '份数',                    en: 'Slots',                         si: 'ස්ලට්',                     bn: 'স্লট' },
        confirm_buy:        { zh: '确认购买',                en: 'Confirm Purchase',              si: 'මිලදී ගැනීම තහවුරු කරන්න',  bn: 'ক্রয় নিশ্চিত করুন' },
        cancel:             { zh: '取消',                    en: 'Cancel',                        si: 'අවලංගු කරන්න',              bn: 'বাতিল' },
        save:               { zh: '保存',                    en: 'Save',                          si: 'සුරකින්න',                  bn: 'সংরক্ষণ' },
        name:               { zh: '姓名',                    en: 'Name',                          si: 'නම',                        bn: 'নাম' },
        phone:              { zh: '电话',                    en: 'Phone',                         si: 'දුරකථන',                    bn: 'ফোন' },
        address:            { zh: '地址',                    en: 'Address',                       si: 'ලිපිනය',                    bn: 'ঠিকানা' },
        city:               { zh: '城市',                    en: 'City',                          si: 'නගරය',                      bn: 'শহর' },
        province:           { zh: '省份',                    en: 'Province',                      si: 'පළාත',                      bn: 'প্রদেশ' },
        country:            { zh: '国家',                    en: 'Country',                       si: 'රට',                        bn: 'দেশ' },
        copy:               { zh: '复制',                    en: 'Copy',                          si: 'පිටපත් කරන්න',              bn: 'কপি' },
        referral_code:      { zh: '邀请码',                  en: 'Referral Code',                 si: 'යොමු කේතය',                 bn: 'রেফারেল কোড' },
        my_ref_link:        { zh: '我的邀请链接',            en: 'My Referral Link',              si: 'මගේ යොමු සබැඳිය',           bn: 'আমার রেফারেল লিঙ্ক' },
        upcoming:           { zh: '即将公布',                en: 'Upcoming',                      si: 'ඉදිරියට එන',                bn: 'আসন্ন' },
        result:             { zh: '揭晓',                    en: 'Revealed',                      si: 'ප්‍රතිඵලය',                  bn: 'ফলাফল' },
        empty_list:         { zh: '暂无数据',                en: 'Nothing here yet',              si: 'තවම දත්ත නැත',              bn: 'এখনও কিছু নেই' },
        status_pending:     { zh: '待发货',                  en: 'Awaiting shipment',             si: 'බෙදාහැරීම සඳහා රැඳී සිටී',   bn: 'শিপমেন্টের অপেক্ষায়' },
        status_shipped:     { zh: '运输中',                  en: 'In transit',                    si: 'ප්‍රවාහනයේ',                bn: 'পরিবহনে' },
        status_delivered:   { zh: '已送达',                  en: 'Delivered',                     si: 'ගෙන්වන ලදී',                bn: 'ডেলিভারি হয়েছে' },
        status_claimed:     { zh: '已签收',                  en: 'Received',                      si: 'ලැබුණි',                    bn: 'গৃহীত' },
        tracking_no:        { zh: '物流单号',                en: 'Tracking #',                    si: 'ට්‍රැකින් අංකය',             bn: 'ট্র্যাকিং নং' },
        ship_to:            { zh: '收货地址',                en: 'Ship to',                       si: 'බෙදාහරින්නේ',               bn: 'ঠিকানায় পাঠান' },
        confirm_received:   { zh: '确认收货',                en: 'Confirm received',              si: 'ලැබීම තහවුරු කරන්න',        bn: 'প্রাপ্তি নিশ্চিত করুন' },
        confirm_received_q: { zh: '确认已收到此商品？',      en: 'Confirm you have received this prize?', si: 'ඔබට මෙම ත්‍යාගය ලැබුණි දැයි තහවුරු කරන්නද?', bn: 'আপনি কি এই পুরস্কারটি পেয়েছেন?' },
        no_address_set:     { zh: '尚未设置收货地址，请先添加', en: 'No shipping address set — please add one', si: 'බෙදාහැරීමේ ලිපිනයක් සකසා නැත — කරුණාකර එකක් එක් කරන්න', bn: 'কোন শিপিং ঠিকানা সেট নেই — অনুগ্রহ করে একটি যোগ করুন' },
        add_address:        { zh: '添加地址',                en: 'Add address',                   si: 'ලිපිනය එක් කරන්න',          bn: 'ঠিকানা যোগ করুন' },
        free_draws:         { zh: '免费抽奖',                en: 'Free draws',                    si: 'නොමිලේ අදින්නන්',           bn: 'ফ্রি ড্র' },
        checkin:            { zh: '签到',                    en: 'Check in',                      si: 'පැමිණීම සටහන් කරන්න',       bn: 'চেক ইন' },
        checkin_today:      { zh: '今日签到',                en: 'Daily check-in',                si: 'දෛනික පැමිණීම',             bn: 'দৈনিক চেক-ইন' },
        checkin_done:       { zh: '今日已签到',              en: 'Already checked in today',      si: 'අද දැනටමත් පැමිණ ඇත',       bn: 'আজ ইতিমধ্যে চেক ইন করা হয়েছে' },
        checkin_streak:     { zh: '连续签到',                en: 'Streak',                        si: 'දිගට ම පැමිණීම',            bn: 'ধারাবাহিকতা' },
        checkin_day:        { zh: '天',                      en: 'days',                          si: 'දින',                       bn: 'দিন' },
        next_reward:        { zh: '本次奖励',                en: 'Reward',                        si: 'ත්‍යාගය',                    bn: 'পুরস্কার' },
        upload_proof:       { zh: '上传中奖凭证',            en: 'Upload winning proof',          si: 'ජයග්‍රහණ සාක්ෂි උඩුගත කරන්න', bn: 'জয়ের প্রমাণ আপলোড করুন' },
        proof_photo:        { zh: '上传照片',                en: 'Upload photo',                  si: 'ඡායාරූපය උඩුගත කරන්න',      bn: 'ছবি আপলোড করুন' },
        proof_video:        { zh: '提交视频链接',            en: 'Submit video URL',              si: 'වීඩියෝ URL ඉදිරිපත් කරන්න', bn: 'ভিডিও URL জমা দিন' },
        proof_note_ph:      { zh: '说几句话（选填）',        en: 'Add a note (optional)',         si: 'සටහනක් එක් කරන්න (අත්‍යවශ්‍ය නොවේ)', bn: 'একটি নোট যোগ করুন (ঐচ্ছিক)' },
        video_url_ph:       { zh: '抖音/YouTube/微博视频链接', en: 'Douyin / YouTube / Weibo video URL', si: 'Douyin / YouTube / Weibo වීඩියෝ URL', bn: 'Douyin / YouTube / Weibo ভিডিও URL' },
        submit:             { zh: '提交',                    en: 'Submit',                        si: 'ඉදිරිපත් කරන්න',            bn: 'জমা দিন' },
        share_to_earn:      { zh: '分享到社交媒体赢奖励',    en: 'Share to social — earn rewards', si: 'සමාජ මාධ්‍ය වෙත බෙදාගෙන ත්‍යාග උපයන්න', bn: 'সামাজিক মাধ্যমে শেয়ার করুন — পুরস্কার পান' },
        screenshot:         { zh: '截图',                    en: 'Screenshot',                    si: 'තිර රුව',                   bn: 'স্ক্রিনশট' },
        platform:           { zh: '平台',                    en: 'Platform',                      si: 'වේදිකාව',                   bn: 'প্ল্যাটফর্ম' },
        post_link_optional: { zh: '帖子链接（选填）',        en: 'Post URL (optional)',           si: 'පළ කිරීමේ URL (අත්‍යවශ්‍ය නොවේ)', bn: 'পোস্ট URL (ঐচ্ছিক)' },
        status_approved:    { zh: '已通过',                  en: 'Approved',                      si: 'අනුමත කරන ලදී',             bn: 'অনুমোদিত' },
        status_rejected:    { zh: '已驳回',                  en: 'Rejected',                      si: 'ප්‍රතික්ෂේප කරන ලදී',       bn: 'প্রত্যাখ্যাত' },
        reward_granted:     { zh: '获得奖励',                en: 'Reward granted',                si: 'ත්‍යාගය ලබා දෙන ලදී',       bn: 'পুরস্কার প্রদান করা হয়েছে' },

        // home / index
        categories_title:   { zh: '商品分类',                en: 'Categories',                    si: 'වර්ග',                      bn: 'বিভাগ' },
        featured_periods:   { zh: '正在进行',                en: 'Active Periods',                si: 'ක්‍රියාත්මක වට',             bn: 'চলমান রাউন্ড' },
        join_now:           { zh: '立即参与',                en: 'Join Now',                      si: 'දැන්ම සහභාගි වන්න',         bn: 'এখনই যোগ দিন' },
        more:               { zh: '更多',                    en: 'More',                          si: 'තවත්',                      bn: 'আরও' },
        for_you:            { zh: '喜欢',                    en: 'For You',                       si: 'ඔබට',                       bn: 'আপনার জন্য' },
        badge_hot:          { zh: '热门',                    en: 'HOT',                           si: 'උණුසුම්',                   bn: 'হট' },
        badge_closing:      { zh: '即将开奖',                en: 'CLOSING',                       si: 'වැසෙයි',                    bn: 'বন্ধ হচ্ছে' },
        badge_new:          { zh: '新品',                    en: 'NEW',                           si: 'නව',                        bn: 'নতুন' },
        hero_tag_hot:       { zh: '人气爆款',                en: 'Hot Now',                       si: 'දැන් ජනප්‍රිය',              bn: 'এখন জনপ্রিয়' },
        hero_tag_closing:   { zh: '即将开奖',                en: 'Almost Closed',                 si: 'වැසෙන්නට යයි',              bn: 'প্রায় বন্ধ' },
        hero_tag_featured:  { zh: '精选推荐',                en: 'Featured',                      si: 'විශේෂාංග',                  bn: 'বৈশিষ্ট্যযুক্ত' },
        win_from:           { zh: '仅需 {0} 起',             en: 'Win it from {0}',               si: '{0} සිට දිනන්න',             bn: '{0} থেকে জিতুন' },
        slots_left:         { zh: '剩余 {0} 注',             en: '{0} slots left',                si: 'ස්ලට් {0} ඉතිරියි',          bn: '{0}টি স্লট বাকি' },

        // me / wallet / orders
        my_orders:          { zh: '我的订单',                en: 'My Orders',                     si: 'මගේ ඇණවුම්',                bn: 'আমার অর্ডার' },
        wallet:             { zh: '钱包',                    en: 'Wallet',                        si: 'පසුම්බිය',                  bn: 'ওয়ালেট' },
        invite_friends:     { zh: '邀请朋友',                en: 'Invite Friends',                si: 'මිතුරන් ආරාධනා කරන්න',      bn: 'বন্ধুদের আমন্ত্রণ' },
        team:               { zh: '我的团队',                en: 'My Team',                       si: 'මගේ කණ්ඩායම',               bn: 'আমার টিম' },
        rank:               { zh: '等级',                    en: 'Rank',                          si: 'ශ්‍රේණිය',                   bn: 'র‍্যাঙ্ক' },
        direct_refs:        { zh: '直推',                    en: 'Direct Refs',                   si: 'සෘජු යොමු',                 bn: 'সরাসরি রেফ' },
        team_volume:        { zh: '团队业绩',                en: 'Team Volume',                   si: 'කණ්ඩායම් පරිමාව',           bn: 'টিম ভলিউম' },
        to_next_rank:       { zh: '冲刺',                    en: 'To',                            si: 'ඊළඟට',                      bn: 'পরবর্তী' },
        commission_earned:  { zh: '已获佣金',                en: 'Commission Earned',             si: 'උපයූ කොමිස්',                bn: 'অর্জিত কমিশন' },
        my_team:            { zh: '我的团队',                en: 'My Team',                       si: 'මගේ කණ්ඩායම',               bn: 'আমার টিম' },
        my_team_note:       { zh: '查看等级·业绩·分红',      en: 'Rank · volume · bonuses',        si: 'ශ්‍රේණිය · පරිමාව · ප්‍රසාද', bn: 'র‍্যাঙ্ক · ভলিউম · বোনাস' },
        day_n_reward:       { zh: '连续 {0} 天 +¥{1}',       en: 'Day {0}! +¥{1}',                 si: 'දින {0}! +¥{1}',             bn: 'দিন {0}! +¥{1}' },
        mtd:                { zh: '当月数据',                en: 'MONTH-TO-DATE',                 si: 'මේ මස දත්ත',                bn: 'এই মাসের তথ্য' },
        team_bonus:         { zh: '团队分红',                en: 'TEAM BONUS',                    si: 'කණ්ඩායම් ප්‍රසාද',           bn: 'টিম বোনাস' },
        direct_this_month:  { zh: '本月直推',                en: 'Direct (this month)',           si: 'සෘජු (මේ මස)',              bn: 'সরাসরি (এই মাস)' },
        team_volume_lbl:    { zh: '团队业绩',                en: 'Team Volume',                   si: 'කණ්ඩායම් පරිමාව',           bn: 'টিম ভলিউম' },
        earned_month_lbl:   { zh: '本月分红',                en: 'Earned (month)',                si: 'උපයූ (මස)',                 bn: 'অর্জিত (মাস)' },
        direct_members:     { zh: '我的直推',                en: 'Direct Members',                si: 'සෘජු සාමාජිකයින්',          bn: 'সরাসরি সদস্য' },
        recent_team_bonus:  { zh: '最近团队分红',            en: 'Recent Team Bonuses',           si: 'මෑත කණ්ඩායම් ප්‍රසාද',       bn: 'সাম্প্রতিক টিম বোনাস' },
        no_directs_yet:     { zh: '暂无直推会员',            en: 'No direct members yet',         si: 'තවම සෘජු සාමාජිකයින් නැත',  bn: 'এখনও কোন সরাসরি সদস্য নেই' },
        no_bonuses_yet:     { zh: '暂无分红记录',            en: 'No bonuses yet',                si: 'තවම ප්‍රසාද නැත',            bn: 'এখনও কোন বোনাস নেই' },
        progress_to_next:   { zh: '距离下一等级',            en: 'Progress to next tier',         si: 'ඊළඟ ශ්‍රේණියට ප්‍රගතිය',     bn: 'পরবর্তী টিয়ারে অগ্রগতি' },
        please_login_team:  { zh: '请先登录查看团队',        en: 'Please log in to view your team', si: 'ඔබේ කණ්ඩායම බැලීමට පිවිසෙන්න', bn: 'আপনার টিম দেখতে লগইন করুন' },
        to_label:           { zh: '冲刺',                    en: 'To',                            si: 'ඊළඟට',                      bn: 'এর দিকে' },
        direct_label:       { zh: '直推',                    en: 'Direct',                        si: 'සෘජු',                      bn: 'সরাসরি' },
        volume_label:       { zh: '业绩',                    en: 'Volume',                        si: 'පරිමාව',                    bn: 'ভলিউম' },
        this_month_short:   { zh: '本月',                    en: 'this month',                    si: 'මේ මස',                     bn: 'এই মাস' },
        team_total_note:    { zh: '团队总人数',              en: 'Total team',                    si: 'මුළු කණ්ඩායම',              bn: 'মোট টিম' },
        view_full_plan:     { zh: '查看完整方案',            en: 'View full plan',                si: 'සම්පූර්ණ සැලැස්ම බලන්න',    bn: 'সম্পূর্ণ পরিকল্পনা দেখুন' },
        team_settled_monthly: { zh: '团队等级月度结算 · 实时分红到余额', en: 'Tier settled monthly · bonuses paid live to balance', si: 'ශ්‍රේණිය මාසිකව තීරණය වේ · ප්‍රසාද ශේෂයට සජීවීව ගෙවනු ලැබේ', bn: 'টিয়ার মাসিক নিষ্পত্তি · ব্যালেন্সে রিয়েল-টাইম বোনাস' },

        // product / orders
        winner_code_label:  { zh: '获奖码',                  en: 'Winner',                        si: 'ජයග්‍රාහී කේතය',             bn: 'বিজয়ী কোড' },
        purchased:          { zh: '购买成功',                en: 'Purchased!',                    si: 'මිලදී ගත්!',                bn: 'ক্রয় সম্পন্ন!' },
        drawn_label:        { zh: '已揭晓',                  en: 'Drawn',                         si: 'අදින ලදී',                  bn: 'ড্র সম্পন্ন' },

        // reveals / wins
        latest_winners:     { zh: '最新获奖者',              en: 'Latest Winners',                si: 'නවතම ජයග්‍රාහකයින්',         bn: 'সর্বশেষ বিজয়ী' },
        about_to_draw:      { zh: '即将开奖',                en: 'About to Draw',                 si: 'අදින්නට නියමිතයි',          bn: 'ড্র হতে যাচ্ছে' },
        no_wins_yet:        { zh: '暂未中奖',                en: 'No wins yet',                   si: 'තවම ජයග්‍රහණයක් නැත',        bn: 'এখনও কোন জয় নেই' },
        view_proof:         { zh: '查看凭证',                en: 'View proof',                    si: 'සාක්ෂි බලන්න',              bn: 'প্রমাণ দেখুন' },
        deliver:            { zh: '发货',                    en: 'Deliver',                       si: 'බෙදාහරින්න',                bn: 'ডেলিভারি' },

        // lucky wheel
        try_your_luck:      { zh: '幸运转盘',                en: 'Try Your Luck',                 si: 'වාසනාව අත්හදා බලන්න',       bn: 'ভাগ্য পরীক্ষা করুন' },
        spin_the_wheel:     { zh: '开始抽奖',                en: 'Spin the Wheel',                si: 'රෝදය කරකවන්න',              bn: 'হুইল ঘোরান' },
        daily_spin_hint:    { zh: '每日免费抽奖 · 明日再来', en: 'Daily free spin · come back tomorrow for another', si: 'දෛනික නොමිලේ කරකැවීම · හෙට නැවත එන්න', bn: 'দৈনিক ফ্রি স্পিন · কাল আবার আসুন' },
        congrats:           { zh: '🎉 恭喜中奖！',            en: '🎉 Congratulations!',            si: '🎉 සුභ පැතුම්!',              bn: '🎉 অভিনন্দন!' },
        awesome:            { zh: '太棒了！',                en: 'Awesome!',                      si: 'අතිවිශාලයි!',               bn: 'অসাধারণ!' },
        points_use_hint:    { zh: '积分可兑换免费参与机会', en: 'Use points for free slots on any product', si: 'ඕනෑම නිෂ්පාදනයක නොමිලේ ස්ලට් සඳහා ලකුණු භාවිතා කරන්න', bn: 'যেকোন পণ্যে ফ্রি স্লটের জন্য পয়েন্ট ব্যবহার করুন' },
        prize_pts:          { zh: '积分',                    en: 'pts',                           si: 'ලකුණු',                     bn: 'পয়েন্ট' },
        prize_try_again:    { zh: '再来一次',                en: 'Try Again',                     si: 'නැවත උත්සාහ කරන්න',         bn: 'আবার চেষ্টা করুন' },

        // wins / proofs / shares
        timeline:           { zh: '物流进度',                en: 'Timeline',                      si: 'කාලරේඛාව',                  bn: 'টাইমলাইন' },
        timeline_drawn:     { zh: '开奖',                    en: 'Drawn',                         si: 'ඇද ඇත',                     bn: 'ড্র হয়েছে' },
        timeline_shipped:   { zh: '发货',                    en: 'Shipped',                       si: 'යවන ලදී',                   bn: 'পাঠানো হয়েছে' },
        timeline_delivered: { zh: '送达',                    en: 'Delivered',                     si: 'ලැබුණි',                    bn: 'পৌঁছেছে' },
        timeline_received:  { zh: '签收',                    en: 'Received',                      si: 'භාරගත්',                    bn: 'গৃহীত' },
        tap_choose_photo:   { zh: '点击选择照片',            en: 'Tap to choose a photo',         si: 'ඡායාරූපයක් තේරීමට තට්ටු කරන්න', bn: 'ছবি বেছে নিতে ট্যাপ করুন' },
        tap_choose_screenshot: { zh: '点击选择截图',         en: 'Tap to choose a screenshot',    si: 'තිර රුවක් තේරීමට තට්ටු කරන්න', bn: 'স্ক্রিনশট বেছে নিতে ট্যাপ করুন' },
        choose_photo_first: { zh: '请选择照片',              en: 'Choose a photo',                si: 'කරුණාකර ඡායාරූපයක් තෝරන්න', bn: 'অনুগ্রহ করে একটি ছবি বেছে নিন' },
        enter_video_url:    { zh: '请输入视频链接',          en: 'Enter a video URL',             si: 'වීඩියෝ URL එකක් ඇතුළත් කරන්න', bn: 'একটি ভিডিও URL লিখুন' },
        choose_screenshot_first: { zh: '请选择截图',         en: 'Choose a screenshot',           si: 'කරුණාකර තිර රුවක් තෝරන්න',  bn: 'অনুগ্রহ করে একটি স্ক্রিনশট বেছে নিন' },
        submitted_pending:  { zh: '提交成功 — 等待审核',     en: 'Submitted — pending review',    si: 'ඉදිරිපත් කරන ලදී — සමාලෝචනය බලා සිටී', bn: 'জমা দেওয়া হয়েছে — পর্যালোচনার অপেক্ষায়' },

        // customer service
        support:            { zh: '在线客服',                en: 'Live Support',                  si: 'සජීවී සහාය',                bn: 'লাইভ সহায়তা' },
        support_note:       { zh: '点击联系客服',            en: 'Tap to chat with support',      si: 'සහාය සමඟ කතා කිරීමට තට්ටු කරන්න', bn: 'সহায়তার সাথে চ্যাট করতে ট্যাপ করুন' },
        support_unavailable: { zh: '客服暂未配置，请稍后再试', en: 'Support not configured yet — please try again later', si: 'සහාය තවම සකසා නැත — පසුව උත්සාහ කරන්න', bn: 'সহায়তা এখনও কনফিগার করা হয়নি — পরে চেষ্টা করুন' },

        // bargain / cut-a-knife
        bargain:            { zh: '砍一刀拿奖',              en: 'Bargain for prize',             si: 'ත්‍යාගය සඳහා කපන්න',         bn: 'পুরস্কারের জন্য কাটুন' },
        bargain_note:       { zh: '邀好友砍价，砍到 0 元免费抽', en: 'Invite friends to cut the price to ¥0 — free draw!', si: 'මිතුරන් කපන්න ආරාධනා කරන්න — නොමිලේ ඇදීමක්!', bn: 'বন্ধুদের কাটতে আমন্ত্রণ জানান — ফ্রি ড্র!' },
        start_bargain:      { zh: '发起砍价',                en: 'Start Bargain',                 si: 'කපීම ආරම්භ කරන්න',          bn: 'কাটা শুরু করুন' },
        help_cut:           { zh: '帮 TA 砍一刀',            en: 'Cut for them',                  si: 'ඔවුන් වෙනුවෙන් කපන්න',      bn: 'তাদের জন্য কাটুন' },
        cut_done:           { zh: '砍掉了 ¥{0}',             en: 'Cut ¥{0} off',                  si: '¥{0} කපා දමන ලදී',          bn: '¥{0} কাটা হয়েছে' },
        cut_already:        { zh: '你已经帮 TA 砍过了',      en: 'You already cut for this one',  si: 'ඔබ දැනටමත් කපා ඇත',         bn: 'আপনি ইতিমধ্যে কেটেছেন' },
        bargain_completed:  { zh: '🎉 砍价成功！获得免费抽奖一次',  en: '🎉 Bargain done! 1 free draw earned', si: '🎉 කපීම සම්පූර්ණයි! නොමිලේ ඇදීමක්', bn: '🎉 কাটা সম্পন্ন! ১টি ফ্রি ড্র' },
        bargain_expired:    { zh: '砍价已过期',              en: 'Bargain expired',               si: 'කපීම කල් ඉකුත් වී ඇත',       bn: 'কাটা মেয়াদ শেষ' },
        bargain_progress:   { zh: '已砍 {0} / {1}',           en: 'Cut {0} of {1}',                si: '{1} න් {0} කපන ලදී',         bn: '{1} এর {0} কাটা হয়েছে' },
        bargain_helpers:    { zh: '{0} 位好友已助力',         en: '{0} friends helped',            si: 'මිතුරන් {0} දෙනෙක් උදව් කළා', bn: '{0} জন বন্ধু সাহায্য করেছেন' },
        share_to_friends:   { zh: '分享给好友帮砍',           en: 'Share to friends to cut',       si: 'කපන්න මිතුරන්ට බෙදාගන්න',    bn: 'বন্ধুদের কাছে শেয়ার করুন' },
        bargain_helper_bonus: { zh: '帮砍可获 1 次免费抽奖',  en: 'Helpers earn 1 free draw',      si: 'උදව්කරුවන්ට නොමිලේ ඇදීමක්', bn: 'সাহায্যকারীরা ১টি ফ্রি ড্র পান' },
        my_bargains:        { zh: '我的砍价',                en: 'My Bargains',                   si: 'මගේ කපීම්',                 bn: 'আমার কাটা' },
        copy_link:          { zh: '复制链接',                en: 'Copy link',                     si: 'සබැඳිය පිටපත් කරන්න',       bn: 'লিঙ্ক কপি করুন' },
        link_copied:        { zh: '链接已复制',              en: 'Link copied',                   si: 'සබැඳිය පිටපත් කරන ලදී',     bn: 'লিঙ্ক কপি হয়েছে' },
        bargain_remaining_now: { zh: '还差 ¥{0}',            en: '¥{0} left to go',               si: 'තවත් ¥{0} ඉතිරියි',          bn: 'আরও ¥{0} বাকি' },
        bargain_expires_in: { zh: '剩余 {0}',                en: '{0} left',                      si: 'ඉතිරි {0}',                 bn: '{0} বাকি' },
    };

    const SUPPORTED = ['zh', 'en', 'si', 'bn'];
    // Order of fallbacks when a key is missing in the active language.
    const FALLBACK = { zh: ['en'], en: ['zh'], si: ['en', 'zh'], bn: ['en', 'zh'] };
    const LABELS = {
        zh: { short: '中',  long: '中文'    },
        en: { short: 'EN',  long: 'English' },
        si: { short: 'සිං', long: 'සිංහල'  },
        bn: { short: 'বাং', long: 'বাংলা'  },
    };

    const KEY = 'lm.lang';
    let stored = localStorage.getItem(KEY);
    let lang = SUPPORTED.includes(stored) ? stored : 'zh';

    function set(l) {
        lang = SUPPORTED.includes(l) ? l : 'zh';
        localStorage.setItem(KEY, lang);
        apply();
    }
    function get() { return lang; }
    function supported() { return SUPPORTED.slice(); }
    function label(l, kind) { return (LABELS[l] || LABELS.zh)[kind || 'short']; }

    function t(k) {
        const entry = dict[k];
        if (!entry) return k;
        if (entry[lang]) return entry[lang];
        for (const f of (FALLBACK[lang] || [])) if (entry[f]) return entry[f];
        return k;
    }

    function apply(root = document) {
        root.querySelectorAll('[data-i18n]').forEach(el => {
            el.textContent = t(el.getAttribute('data-i18n'));
        });
        root.querySelectorAll('[data-i18n-ph]').forEach(el => {
            el.setAttribute('placeholder', t(el.getAttribute('data-i18n-ph')));
        });
        // Map our internal codes to valid BCP-47 tags for the <html lang> attribute.
        const htmlLang = { zh: 'zh', en: 'en', si: 'si', bn: 'bn' }[lang] || 'zh';
        document.documentElement.lang = htmlLang;
    }

    function pickName(o) {
        // Products carry name_zh/_en/_si/_bn; categories/ranks only _zh/_en today.
        // Fall back through FALLBACK chain so a missing localized name doesn't blank out.
        const direct = o['name_' + lang];
        if (direct) return direct;
        for (const f of (FALLBACK[lang] || [])) {
            if (o['name_' + f]) return o['name_' + f];
        }
        return o.name_en || o.name_zh || '';
    }

    function tf(k, ...args) {
        let s = t(k);
        args.forEach((v, i) => { s = s.replace('{' + i + '}', v); });
        return s;
    }

    return { t, tf, set, get, apply, pickName, supported, label };
})();
