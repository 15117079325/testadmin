/*******************************************************************************
 * 会员登录
 * modified by fuhuaquan 2018/1/11
 */
function user_login(back_history){

    var logform = $('form[name="formLogin"]');
    var username = logform.find('#username');
    var password = logform.find('#password');
    var captcha = logform.find('#authcode');
    var error = logform.find('.msg-wrap');
    var back_act = logform.find("input[name='back_act']").val();

    if(username.val()==''){
        error.css({'visibility':'visible'});
        error.find('.msg-error-text').html('请输入账户名');
        username.parents('.item').addClass('item-error');
        return false;
    }

    if(password.val()==''){
        error.css({'visibility':'visible'});
        password.parents('.item').addClass('item-error');
        error.find('.msg-error-text').html('请输入密码');
        return false;
    }

    if(captcha.val()==''){
        error.css({'visibility':'visible'});
        captcha.parents('.item-detail').addClass('item-error');
        error.find('.msg-error-text').html('请输入验证码');
        return false;
    }

    if(back_history){
        Ajax.call( 'user.php?act=act_login', 'username=' + username.val()+'&password='+password.val()+'&captcha='+captcha.val()+'&back_act='+back_act, return_login_back , 'POST', 'JSON');
    }else{
        Ajax.call( 'user.php?act=act_login', 'username=' + username.val()+'&password='+password.val()+'&captcha='+captcha.val()+'&back_act='+back_act, return_login , 'POST', 'JSON');
    }
    return false;
}
function return_login(result){
    console.log(result);
    if(result.error>0){
        $('form[name="formLogin"]').find('.msg-error-text').html(result.message);
        $('#o-authcode').find('img').attr('src','captcha.php?is_login=1&'+Math.random());
        if(result.message != '对不起，您输入的验证码不正确。'){
            $('#authcode').parents('.item-detail').removeClass('item-error');
            $('form[name="formLogin"]').find('.msg-wrap').css('visibility','visible');
        }
        if(result.message == '对不起，您输入的验证码不正确。'){
            $('#authcode').parents('.item-detail').addClass('item-error');
            $('form[name="formLogin"]').find('.msg-wrap').css('visibility','visible');
        }
        if(result.message == '用户名或密码错误'){
            $('#password,#username').parents('.item').addClass('item-error');
            $('form[name="formLogin"]').find('.msg-wrap').css('visibility','visible');
        }
        if(result.message != '用户名或密码错误'){
            $('#password,#username').parents('.item').removeClass('item-error');
            $('form[name="formLogin"]').find('.msg-wrap').css('visibility','visible');
        }
    }else{
        $('.pop-login,.pop-mask').hide();
        $('form[name="formLogin"]').find('.msg-error-text').css('visibility','visible');
        top.location.reload();
    }
}

/*******************************************************************************
 * IP变化 验证会员手机登录
 */
function user_phone_login(back_history){

    var logform = $('form[name="formLogin"]');
    var captcha = $("#auth_code").val(); //验证码
    var mobile_code = $("#mobile_code").val(); //手机验证码
    var mobile = $("#mobile").val(); //手机
    var back_act = logform.find("input[name='back_act']").val();
    if (captcha == "") {
        layer.msg('请先输入验证码');
        return false;
    }
    if (mobile_code == "") {
        layer.msg('请先获取手机验证码');
        return false;
    }
    var params = {mobile: mobile, captcha: captcha,mobile_code:mobile_code,back_act:back_act};

    if(mobile==''){
        error.css({'visibility':'visible'});
        error.find('.msg-error-text').html('你还未注册手机号');
        username.parents('.item').addClass('item-error');
        return false;
    }

    if(mobile_code==''){
        error.css({'visibility':'visible'});
        password.parents('.item').addClass('item-error');
        error.find('.msg-error-text').html('请先获取手机验证码');
        return false;
    }

    if(captcha==''){
        error.css({'visibility':'visible'});
        captcha.parents('.item-detail').addClass('item-error');
        error.find('.msg-error-text').html('请输入验证码');
        return false;
    }
	/* 异步请求 接口 用手机安全登录 */
    if(back_history){
        Ajax.call( 'user.php?act=phone_login', 'mobile=' + mobile+'&mobile_code='+mobile_code+'&captcha='+captcha+'&back_act='+back_act, return_login_back , 'POST', 'JSON');
    }else{
        Ajax.call( 'user.php?act=phone_login', 'mobile=' + mobile+'&mobile_code='+mobile_code+'&captcha='+captcha+'&back_act='+back_act, return_login , 'POST', 'JSON');
    }
    return false;
}


/*
 * modified by fuhuauqan 2018/1/19
 * */
function return_login_back(result){
    console.log(result);
    switch(result.error)
    {
        case 1:
            $("#cpa_img").click();
            $("#authcode").val("");
            $('form[name="formLogin"]').find('.msg-error-text').html(result.message);
            $('form[name="formLogin"]').find('.msg-wrap').css('visibility','visible');
            break;

        case 2:
            /*请求后台的接口  看客户机 IP地址*/
            $(document).ready(function(){
                $('#entry').hide();
                /*手机验证 登录界面*/
                $('#phoneDiv').show();
                /* 刷新验证码 */
                $("#cap_phone_img").click();

                $('form[name="formLogin"]').find('.msg-wrap').css('visibility','hidden');
                $("#mobile").val(result.phone);

            })
            /* 点击发送手机验证码 */
            $("#click_send").click(function () {
                var phoneNum = $("#mobile").val(); //手机号
                var captcha = $("#auth_code").val(); //验证码
                if(!captcha){
                    $('form[name="formLogin"]').find('.msg-error-text').html('请先输入验证码');
                    $('form[name="formLogin"]').find('.msg-wrap').css('visibility','visible');
                    return false;
                }
                var params = {phone: phoneNum, captcha: captcha};
                $.post("user.php?act=send_code", params, function (data) {
                    console.log(data);
                    var data = JSON.parse(data);

                    //验证码不正确，60S 不计时
                    /* staus 1 成功  2 发送太频繁 3 发送失败 */
                    switch (data.status) {
                        /* 验证码发送成功 执行 $("#phoneBut").click(function(){} */
                        case 1:
                            var obj=  document.getElementById('click_send');
                            $("#cap_phone_img").click();
                            settime(obj);
                            break;
                        default:
                            $('form[name="formLogin"]').find('.msg-error-text').html(data.message);
                            $('form[name="formLogin"]').find('.msg-wrap').css('visibility','visible');
                            $("#cap_phone_img").click();
                            break;
                    }
                })
            })

            /* 手机 登录 按钮发送到处理页面 */
            $("#phonesubmit").click(function(){
                var captcha=$('#auth_code').val();
                var mobile_code=$('#mobile_code').val();
                var mobile = $("#mobile").val(); //手机
                if(mobile_code==''){
                 //   layer.msg('请先获取手机验证码');
                    $('form[name="formLogin"]').find('.msg-error-text').html('请先获取手机验证码');
                    $('form[name="formLogin"]').find('.msg-wrap').css('visibility','visible');
                    return false;
                }
                $("#hidden_act").val("act_phone_login");
                $('form[name="formLogin"]').attr("onSubmit","return user_phone_login('1')");
            })
            break;

        case 3:
            /*请求后台的接口  看客户机 IP地址*/
            $('form[name="formLogin"]').find('.msg-error-text').html(result.message);
            $('form[name="formLogin"]').find('.msg-wrap').css('visibility','visible');
            $("#capImage").click();
            break;
        default:
            $('form[name="formLogin"]').find('.msg-error-text').css('visibility','visible');
            window.location.href = result.url;

            break;
    }
}
/* 60S 倒计时 */
var countdown = 60;
function settime(obj) {

    if (countdown == 0) {
        obj.removeAttribute("disabled");
        obj.value = "获取手机验证码";
        countdown = 60;
        return;
    } else {
        obj.setAttribute("disabled", true);
        obj.value = "重新发送(" + countdown + ")";
        countdown--;
    }
    setTimeout(function () {
            settime(obj)
        }
        , 1000)
}
