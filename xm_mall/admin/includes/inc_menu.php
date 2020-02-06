<?php

/**
 * ECSHOP 管理中心菜单数组
 * ============================================================================
 * * 版权所有 2005-2012 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liubo $
 * $Id: inc_menu.php 17217 2011-01-19 06:29:08Z liubo $
 */
if (!defined('IN_ECS')) {
    die('Hacking attempt');
}

//首页
$modules['01_home']['01_carousel_list'] = 'carousel.php?act=list'; // 轮播管理
$modules['01_home']['02_navigation_list'] = 'home_hot_search.php?act=list'; // 热搜词
$modules['01_home']['03_rec_list'] = 'home_goods.php?act=list'; // 商品推荐
//$modules['01_home']['04_hot_list'] = 'home_navigation.php?act=list'; // 导航栏管理


//商品
$modules['02_goods']['01_attr_list'] = 'app_goods.php?act=list'; // 商品管理
$modules['02_goods']['02_goods_list'] = 'app_attr.php?act=attr_val_list'; // 属性值管理
$modules['02_goods']['03_attr_val_list'] = 'app_attr.php?act=attr_list'; // 属性管理
$modules['02_goods']['04_cate_list'] = 'app_cate.php?act=list'; // 分类管理
$modules['02_goods']['05_goods_list'] = 'app_goods.php?act=seckill_list'; // 秒杀管理


// 订单
$modules['03_order']['01_order_list'] = 'app_order.php?act=app_list';
//$modules['03_order']['02_buyback_list'] = 'mq_buyback.php?act=buyback_list';
$modules['03_order']['03_order_comment'] = 'order_comment.php?act=list';

//报表统计
$modules['04_stats']['01_deal_list'] = 'app_deal.php?act=deal_list'; // 交易大厅数据统计
//$modules['04_stats']['02_dealing_list'] = 'app_dealing_list.php?act=list'; // 服务中心数据统计
//$modules['04_stats']['03_performance_list'] = 'trade_data_list.php?act=list'; //
//$modules['04_stats']['04_trade_list'] = 'person_performance_list.php?act=list'; // 业绩统计
//$modules['04_stats']['04_fruit_data'] = 'fruit_data.php?act=list'; // 业绩统计
//$modules['04_stats']['05_chart_group'] = 'chart_group.php?act=view'; // 20180521 1540 V 数据统计
//$modules['04_stats']['06_push_list'] = 'push_list.php?act=list'; // 直推排行统计
//$modules['04_stats']['07_h_buyback_stats'] = 'h_buyback_stats.php?act=list'; // H单统计
//$modules['04_stats']['08_integral_ranking_list'] = 'integral_ranking_list.php?act=list'; // 积分排行统计
//$modules['04_stats']['09_mq_account_transfer_stats'] = 'mq_account_transfer_stats.php?act=list'; // 积分转账排行统计
$modules['04_stats']['10_mq_pre_recharge_count'] = 'mq_pre_recharge_count.php?act=list'; // 报单统计
$modules['04_stats']['11_register_user'] = 'user_added_stats.php?act=list'; // 注册会员
$modules['04_stats']['12_release_balance'] = 'release_balance.php?act=list'; // 释放优惠券数据
//$modules['04_stats']['13_balance_data'] = 'balance_data.php?act=list'; // 优惠券数据

//$modules['04_stats']['recharge_audit_count'] = 'recharge_audit_count.php?act=list'; // 充值排行统计
//$modules['04_stats']['cash_count_list'] = 'cash_count_list.php?act=list'; // 提现排行统计
//$modules['04_stats']['recharge_audit_count_new'] = 'recharge_audit_count_new.php?act=list'; // 充值排行统计
//$modules['04_stats']['cash_count_list_new'] = 'cash_count_list_new.php?act=list'; // 提现排行统计
$modules['04_stats']['14_app_team_order'] = 'app_team_order.php?act=app_list';//团队报单统计
$modules['04_stats']['15_top_account'] = 'user_account.php?act=list';//用户优惠券排行

//会员管理
$modules['05_members']['01_users_list'] = 'users.php?act=list';
//$modules['05_members']['02_restriction_list'] = 'users_limit.php?act=restriction_list';
//$modules['05_members']['03_user_translation'] = 'user_translation.php?act=add';
//$modules['05_members']['04_user_translation_log'] = 'user_translation.php?act=list';

//权限管理

$modules['06_admin']['01_admin_logs'] = 'admin_logs.php?act=list';
$modules['06_admin']['02_admin_list'] = 'privilege.php?act=list';
$modules['06_admin']['03_admin_role'] = 'role.php?act=list';

//系统设置
$modules['07_system']['01_config_settings'] = 'shop_config.php?act=config_settings';
//$modules['07_system']['02_mq_hdan_pause'] = 'mq_hdan_pause.php?act=list';
//$modules['07_system']['03_fruit_settings'] = 'fruit_settings.php?act=list';
$modules['07_system']['04_add_announce'] = 'app_deal.php?act=trading_list';

//积分管理
$modules['08_invest']['01_integration_details'] = 'mq_integration_details_new.php?act=list'; // 积分投资释放记录列表

//审核
//$modules['09_recharge_review']['01_recharge_audit_lists'] = 'mq_recharge_audit.php?act=list'; // 充值审核
//$modules['09_recharge_review']['02_cash_audit_lists'] = 'mq_cash_audit.php?act=list'; // 提现审核
$modules['09_recharge_review']['03_name_cert_lists'] = 'mq_name_cert.php?act=list';
//$modules['09_recharge_review']['04_transfer_audit_lists'] = 'mq_transfer_audit.php?act=list'; // 转账审核

//交易大厅
//$modules['10_announce']['02_list_announce'] = 'app_deal.php?act=deal_list';
//$modules['10_announce']['03_trade_settings'] = 'shop_config.php?act=trade_settings';
//$modules['10_announce']['04_trade_parameter'] = 'shop_config.php?act=trade_parameter';

// add by fuhuaquan 意见反馈
$modules['11_suggestions']['01_suggestions_manage'] = 'suggestions_manage.php?act=list'; // 意见反馈

//运营管理
//$modules['12_management']['01_group_list'] = 'app_group.php?act=list'; // 群组管理
$modules['12_management']['02_service_list'] = 'home_service.php?act=list'; // 客服管理
//$modules['12_management']['03_message_list'] = 'app_message.php?act=list'; // 系统消息管理
$modules['12_management']['04_share_img'] = 'home_service.php?act=share_list'; // 分享图片
$modules['12_management']['05_alert_img'] = 'home_service.php?act=alert_list';//  首页弹窗

//国庆菜单
$modules['13_mid_autumn']['01_prize_setting'] = 'mid_autumn.php?act=list';//奖品设置
$modules['13_mid_autumn']['02_winning_record'] = 'mid_winning.php?act=app_list';//获奖记录

//消息推送菜单
$modules['14_getui']['01_getui_send'] = 'getui.php?act=send_list';//发布消息
$modules['14_getui']['02_getui_audit'] = 'getui.php?act=audit_list';//消息审核

//火粉头条
$modules['15_community']['01_community_content'] = 'community.php?act=list';//火单头条内容

//钱包管理
$modules['16_examine']['16_examine_option'] = 'examine.php?act=option';//银行卡列表
$modules['16_examine']['16_examine_list'] = 'verify.php?act=list';//审核列表
$modules['16_examine']['16_pass_list'] = 'verifypass.php?act=list';//审核列表

?>
