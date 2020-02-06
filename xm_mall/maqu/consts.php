<?php

/**
 * Created by PhpStorm.
 * User: yangxy
 * Date: 2016/5/27
 * Time: 13:38
 */

//返回状态值
const RESPONSE_FAILURE =0;              //失败
const RESPONSE_SUCCESS =1;              //正常
const RESPONSE_ARGUMENT_INVALID =2;   //参数错误
const RESPONSE_SERVER_ERROR =3;        //服务器错误

//验证状态auth_failure
const AUTH_FAILURE_NO =0;               //失败
const AUTH_FAILURE_YES =1;              //正常

//消息编码
const MESSAGECODE_APIKEY_EXPIRED =1001;        //APIKEY过期
const MESSAGECODE_TOKEN_FAILURE =1002;         //TOKEN验证失败
const MESSAGECODE_TIMESTAMP_EXPIRED =1003;    //TIMESTAMP过期
const MESSAGECODE_USER_NOT_BIND_PHONE = 1004; //用户未绑定手机号码

const YES = true;   //TRUE
const NO = false;   //FALSE

//验证码类型
const SMS_CODE_TYPE_REGISTER = 1;
const SMS_CODE_TYPE_RESET_PWD = 2;
const SMS_CODE_TYPE_LOGIN = 3;
const SMS_CODE_TYPE_BIND_MOBILE = 4;

//account type
const ACCOUNT_TYPE_shopping = 'shopping';
const ACCOUNT_TYPE_consume = 'consume';
const ACCOUNT_TYPE_useable = 'useable';
const ACCOUNT_TYPE_invest = 'invest';
const ACCOUNT_TYPE_share = 'share';
const ACCOUNT_TYPE_cash = 'cash';
const ACCOUNT_TYPE_register = 'register';

const SYSTEM_OFFICAL_ACCOUNT_USERID = '1';

//配置项code
const CONFIG_CODE_xm_precharge_amount_min = 'xm_precharge_amount_min';
const CONFIG_CODE_xm_precharge_amount_max = 'xm_precharge_amount_max';
const CONFIG_CODE_xm_precharge_rate_c_s_r = 'xm_precharge_rate_c_s_r';
const CONFIG_CODE_xm_precharge_rate_all_shop = 'xm_precharge_rate_all_shop';
const CONFIG_CODE_xm_precharge_rate_all_consume = 'xm_precharge_rate_all_consume';
const CONFIG_CODE_xm_precharge_rate_all_invest = 'xm_precharge_rate_all_invest';
const CONFIG_CODE_xm_precharge_rate_all_usable = 'xm_precharge_rate_all_usable';
const CONFIG_CODE_xm_precharge_rate_all_share = 'xm_precharge_rate_all_share';
const CONFIG_CODE_xm_precharge_rate_all_cash = 'xm_precharge_rate_all_cash';
const CONFIG_CODE_xm_precharge_rate_all_register = 'xm_precharge_rate_all_register';
const CONFIG_CODE_xm_precharge_rate_bonus = 'xm_precharge_rate_bonus';
const CONFIG_CODE_xm_credit_invest_reinvest_per = 'xm_credit_invest_reinvest_per';
const CONFIG_CODE_xm_transfer_rate_consume_fee = 'xm_transfer_rate_consume_fee';
const CONFIG_CODE_xm_credit_invest_beishu_mult = 'xm_credit_invest_beishu_mult';
const CONFIG_CODE_xm_take_cash_min_amount = 'xm_take_cash_min_amount';
const CONFIG_CODE_xm_take_cash_max_amount = 'xm_take_cash_max_amount';
const CONFIG_CODE_xm_take_cash_fee = 'xm_take_cash_fee';
const CONFIG_CODE_xm_daily_cash_ceiling = 'xm_daily_cash_ceiling';//每人每日提现上限
const CONFIG_CODE_xm_closing_weekend_withdrawals = 'xm_closing_weekend_withdrawals';//是否关闭周末提现
const CONFIG_CODE_xm_closing_withdrawals = 'xm_closing_withdrawals';//是否关闭提现
const CONFIG_CODE_xm_closing_cash_reason = 'xm_closing_cash_reason';//关闭理由
const CONFIG_CODE_xm_precharge_amount_monthly_ma = 'xm_precharge_amount_monthly_ma';//自我报单月上限
const CONFIG_CODE_xm_transfer_rate_cash_fee = 'xm_transfer_rate_cash_fee';//新美转账手续费
const CONFIG_CODE_xm_transfer_cash_close = 'xm_transfer_cash_close';//新美转账关闭
const CONFIG_CODE_xm_transfer_cash_close_reason = 'xm_transfer_cash_close_reason';//新美转账关闭理由

//用户的等级
const CX_RANK_REGISTER_USER = 0;
const CX_RANK_ACTIVED_USER = 1;
const CX_RANK_3W_WEISHANG_USER = 2;
const CX_RANK_10W_WEISHANG_USER = 3;
const CX_RANK_30W_WEISHANG_USER = 4;

//回购状态
const BUY_BACK_PENDING = 0; //待支付
const BUY_BACK_APPLY_SCCUESS = 1;   //申购成功
const BUY_BACK_EXPIRED = 2;//已到期
const BUY_BACK_PAYED_BACK = 3;  //已回购

//所属系统
const BELONG_SYS_ADMIN = 0;     //管理后台
const BELONG_SYS_UCENTER = 1;   //会员中心

//快钱付款API支持的银行列表
const KQ_SUPPORT_BANK_LIST = [
            '中国工商银行', '招商银行', '中国建设银行',
            '中国农业银行', '中国银行', '上海浦东发展银行',
            '交通银行', '中国民生银行', '深圳发展银行',
            '广东发展银行', '中信银行', '华夏银行',
            '兴业银行', '广州市农村信用合作社', '广州市商业银行',
            '上海农村商业银行', '中国邮政储蓄',
            '中国光大银行', '上海银行', '北京银行',
            '渤海银行', '北京农村商业银行',
            ];
//验证ip地址时是否需要短信验证
const CHECK_SEND_SMS_TRUE = 1;     //需要发送
const CHECK_SEND_SMS_FALSE = 2;   //不需要发送
//新美积分大金额审核
const XM_CASH_LARGE_TRANSFER_YES = 1;   //开启新美积分大金额审核
const XM_CASH_LARGE_TRANSFER_NO = 0;   //不开启
//消费积分大金额审核
const XM_CONSUME_LARGE_TRANSFER_YES = 1;   //开启消费积分大金额审核
const XM_CONSUME_LARGE_TRANSFER_NO = 0;   //不开启

/*用户账户资金变动类型*/

const USER_ACCOUNT_CHANGE_RECHARGE = 1; //充值
const USER_ACCOUNT_CHANGE_RECHARGE_DESC = '充值'; //充值
const USER_ACCOUNT_CHANGE_TRANSFER = 2; //转账
const USER_ACCOUNT_CHANGE_TRANSFER_DESC = '转账'; //转账
const USER_ACCOUNT_CHANGE_EXCHANGE = 3; //兑换
const USER_ACCOUNT_CHANGE_EXCHANGE_DESC = '兑换'; //兑换
const USER_ACCOUNT_CHANGE_PRE_RECHARGE = 4; //报单
const USER_ACCOUNT_CHANGE_PRE_RECHARGE_DESC = '报单'; //报单
const USER_ACCOUNT_CHANGE_INVEST = 5; //复投
const USER_ACCOUNT_CHANGE_INVEST_DESC = '复投'; //复投
const USER_ACCOUNT_CHANGE_WEISHANG = 6; //服务中心
const USER_ACCOUNT_CHANGE_WEISHANG_DESC = '服务中心'; //服务中心
const USER_ACCOUNT_CHANGE_SHOPPING = 7; //购物
const USER_ACCOUNT_CHANGE_SHOPPING_DESC = '购物'; //购物
const USER_ACCOUNT_CHANGE_RECOMMEND = 8; //推荐
const USER_ACCOUNT_CHANGE_RECOMMEND_DESC = '推荐'; //推荐
const USER_ACCOUNT_CHANGE_INVEST_RELEASE = 9; //积分释放
const USER_ACCOUNT_CHANGE_INVEST_RELEASE_DESC = '积分释放'; //积分释放
const USER_ACCOUNT_CHANGE_INVEST_STOP = 10; //主动释放
const USER_ACCOUNT_CHANGE_INVEST_STOP_DESC = '主动释放'; //积分释放
const USER_ACCOUNT_CHANGE_REFUND = 11; //退款
const USER_ACCOUNT_CHANGE_REFUND_DESC = '退款'; //退货后退款
const USER_ACCOUNT_CHANGE_FROZEN = 12; //冻结
const USER_ACCOUNT_CHANGE_FROZEN_DESC = '冻结'; //冻结
const USER_ACCOUNT_CHANGE_UNFROZEN = 13; //解冻
const USER_ACCOUNT_CHANGE_UNFROZEN_DESC = '解冻'; //解冻
const USER_ACCOUNT_CHANGE_TAKE_CASH = 14; //提现
const USER_ACCOUNT_CHANGE_TAKE_CASH_DESC = '提现'; //提现
const USER_ACCOUNT_BUY_BACK_PAY_BACK = 15; //H单拨款
const USER_ACCOUNT_BUY_BACK_PAY_BACK_DESC = 'H单拨款'; //H单拨款
const USER_ACCOUNT_HORDER_BUY_BACK = 16; //H单购买
const USER_ACCOUNT_HORDER_BUY_BACK_DESC = 'H单购买'; //H单购买
const USER_ACCOUNT_KQ_CHANGE_RECHARGE = 17; //快钱充值
const USER_ACCOUNT_KQ_CHANGE_RECHARGE_DESC = '快钱充值'; //快钱充值

/*转账审核状态*/
const XM_TRANSFER_STATUS_WAIT = 0;   //待审核
const XM_TRANSFER_STATUS_SUCCESS = 1;   //审核成功
const XM_TRANSFER_STATUS_FAILURE = 2;   //拒绝审核 失败
/*充值服务中心审核状态*/
const XM_WEISHANG_USER_STATUS_WAIT = 0;   //待审核
const XM_WEISHANG_USER_STATUS_SUCCESS = 1;   //审核成功
const XM_WEISHANG_USER_STATUS_FAILURE = 2;   //拒绝审核 失败





