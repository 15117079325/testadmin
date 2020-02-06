<?php

/**
 * ECSHOP 权限对照表
 * ============================================================================
 * * 版权所有 2005-2012 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: sunxiaodong $
 * $Id: inc_priv.php 15503 2008-12-24 09:22:45Z sunxiaodong $
 */
if(! defined('IN_ECS'))
{
    die('Hacking attempt');
}

//首页
$purview['01_carousel_list'] = 'carousel';
$purview['02_navigation_list'] = 'navigation';
$purview['03_rec_list'] = 'rec_goods';
$purview['04_hot_list'] = 'app_hot';

//商品
$purview['01_attr_list'] = 'goods_manage';
$purview['02_goods_list'] = 'attr_val_manage';
$purview['03_attr_val_list'] = 'attr_manage';
$purview['04_cate_list'] = 'cate_manage';

// 订单
$purview['01_order_list'] = 'app_order';
$purview['02_buyback_list'] = 'buyback_list';
$purview['03_order_comment'] = 'buyback_list';

//报表统计
$purview['01_deal_list'] = 'deal_data'; // 交易大厅数据统计
$purview['02_dealing_list'] = 'buy_deal_data'; //求购
$purview['03_performance_list'] = 'rank_data'; // 服务中心数据统计
$purview['04_trade_list'] = 'performance_data'; // 业绩统计
$purview['04_fruit_data'] = 'fruit_data'; // 业绩统计
$purview['05_chart_group'] = 'data_stats'; // 20180521 1540 V 数据统计
$purview['06_push_list'] = 'push_list'; // 直推排行统计
$purview['07_h_buyback_stats'] = 'h_buyback_stats'; // H单统计
$purview['08_integral_ranking_list'] = 'integral_ranking_list'; // 积分排行统计
$purview['09_mq_account_transfer_stats'] = 'mq_account_transfer_stats'; // 积分转账排行统计
$purview['10_mq_pre_recharge_count'] = 'mq_pre_recharge_count'; // 报单统计

$purview['11_register_user'] = 'register_user'; // 积分排行统计
$purview['12_release_balance'] = 'release_balance'; // 积分转账排行统计
$purview['13_balance_data'] = 'balance_data'; // 报单统计
$purview['14_app_team_order'] = 'app_team_order';//团队报单统计
$purview['15_top_account'] = 'top_account';//用户优惠券排行

//会员管理
$purview['01_users_list'] = 'users_manage';
$purview['02_restriction_list'] = 'restriction_list';
$purview['03_user_translation'] = 'user_translation';
$purview['04_user_translation_log'] = 'user_translation_log';

//权限管理
$purview['01_admin_logs'] = 'admin_manage';
$purview['02_admin_list'] = 'logs_manage';
$purview['03_admin_role'] = 'role_manage';

//系统设置
$purview['01_config_settings'] = 'shop_config';
$purview['02_mq_hdan_pause'] = 'mq_hdan_pause';
$purview['03_fruit_settings'] = 'fruit_settings';
$purview['04_add_announce'] = 'add_announce';


//积分管理
$purview['01_integration_details'] = 'integration_details'; // 积分投资释放记录列表

//审核
$purview['01_recharge_audit_lists'] = 'recharge_audit_lists'; // 充值审核
$purview['02_cash_audit_lists'] = 'cash_audit_lists'; // 提现审核
$purview['03_name_cert_lists'] = 'name_cert_lists';
$purview['04_transfer_audit_lists'] = 'transfer_audit_lists'; // 转账审核

//交易大厅
$purview['02_list_announce'] = 'list_announce';
$purview['03_trade_settings'] = 'trade_parameter';
$purview['04_trade_parameter'] = 'trade_settings';

// add by fuhuaquan 意见反馈
$purview['01_suggestions_manage'] = 'suggestions_manage'; // 意见反馈

//运营管理
$purview['01_group_list'] = 'group_list'; // 群组管理
$purview['02_service_list'] = 'service_list'; // 客服管理
$purview['03_message_list'] = 'message_list'; // 系统消息管理
$purview['04_share_img'] = 'share_img';  //分享图片
$purview['05_alert_img'] = 'alert_img';  //首页弹窗

//国庆菜单
$purview['01_prize_setting'] = 'prize_setting';//奖品设置
$purview['02_winning_record'] = 'winning_record';//获奖记录


//火单头条
$purview['01_community_content'] = 'community_content';//火单头条内容



//火单头条
$purview['01_community_content'] = 'community_content';//火单头条内容

//国庆菜单
$purview['16_examine_option'] = 'examine_option';//银行卡列表
$purview['16_examine_list'] = 'examine_list';//审核列表
