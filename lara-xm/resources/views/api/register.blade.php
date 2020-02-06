<!DOCTYPE HTML>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    {{--<meta name="viewport" content="initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />--}}
    <title>注册页面</title>
    <style>
        body{margin: 0;padding: 0;}
        .screen {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: url({{asset('images/screen.png')}});
            background-size: 100% 100%;
            -webkit-text-size-adjust: 100% !important;
        }

        .logo {
            display: block;
            margin: 155px auto 0;
            width: 40%;
        }

        .input-box {
            width: 750px;
            height: 120px;
            border-radius: 64px;
            border: 1px solid #fff;
            background-color: rgba(255, 255, 255, 0.05);
            outline: none;
            margin: 0 auto 44px;
            line-height: 120px;
            padding: 0 0 0 60px;
            box-sizing: border-box;
        }

        .first{
            margin-top: 80px;
        }


        .input-box.submit {
            display: block;
            background-color: #273651;
            font-size: 48px;
            color: #fff;
            padding: 0;
        }

        .icon {
            width: 8%;
            vertical-align: middle;
            border-right: 3px solid #fff;
            padding-right: 35px;
            margin-top: -25px;
        }

        .icon.small {
            width: 7%;
        }

        .input-static {
            font-size: 48px;
            color: #fff;
            margin-left: 25px;
        }

        .input-text {
            width: 73%;
            line-height: 112px;
            font-size: 48px;
            margin-left: 35px;
            display: inline-block;
            background: none;
            border: none;
            color: #fff;
            outline: none;
        }

        .input-text.short {
            width: 30%;
        }

        .code {
            text-decoration: none;
            color: #fff;
            font-size: 40px;
        }
        .span-a{
            float: right;
            width: 288px;
            height: 112px;
            background-color: #273651;
            border-top-right-radius: 64px;
            border-bottom-right-radius: 64px;
            text-align: center;
        }

        .box {
            width: 750px;
            height: 120px;
            padding-left: 30px;
            outline: none;
            margin: 24px auto 0;
            text-align: left;

        }

        .box-size {
            position: relative;
            top: 0px;
            text-decoration: none;
            font-size: 30px;
            color: #fff;
            margin-left: 25px;
        }
        .box img {
            position: relative;
            top: 5px;
            width: 40px;
            height: 40px;
        }


        .demo-class .layui-layer-btn{
            height:100px;
        }
        .demo-class .layui-layer-btn .layui-layer-btn0{
            height: 80px;
            width: 100px;
            text-align: center;
            line-height: 80px;
            font-size: 30px;
        }
        .demo-class .layui-layer-btn .layui-layer-btn1{
            height: 80px;
            width: 100px;
            text-align: center;
            line-height: 80px;
            font-size: 30px;
        }
        .scroll-wrapper {
            -webkit-overflow-scrolling: touch;
            overflow-y: scroll;
            /* 提示: 请在此处加上需要设置的大小(dimensions)或位置(positioning)信息! */
        }

    </style>
</head>
<body>
<div class="screen">
    <img src="{{asset('images/logo.png')}}" alt="" class="logo">
    <div class="input-box first">
        <img src="{{asset('images/tuijianren@2x.png')}}" alt="" class="icon">
        <span class="input-static">推荐人：{{$remobile}}</span>
    </div>
    <div class="input-box">
        <img src="{{asset('images/tuijianren@2x.png')}}" alt="" class="icon">
        <input type="text" class="input-text" id="username" placeholder="请输入用户名">
    </div>
    <div class="input-box">
        <img src="{{asset('images/psw.png')}}" alt="" class="icon">
        <input type="password" class="input-text" id="password" placeholder="请输入密码">
    </div>
    <div class="input-box">
        <img src="{{asset('images/shouji@2x.png')}}" alt="" class="icon small">
        <input type="text" class="input-text" id="mobile" placeholder="请输入手机号码">
    </div>
    <div class="input-box">
        <img src="{{asset('images/yanzhengma@2x.png')}}" alt="" class="icon">
        <input type="text" id="msg" class="input-text short">
        <span class="span-a"><a href="#" class="code">获取验证码</a></span>
    </div>
    <button type="submit" class="input-box submit" onclick="">注册</button>
    <div class="box">
        <img src="{{asset('images/no.png')}}" alt="" class="no">
        {{--{{url('/api/common/getWebProtocol')}}--}}
        <span class=""><a class="box-size" href="#">同意 《火单用户注册协议》</a></span>
    </div>
    {{--<div  hidden>--}}
        <div id="hid-text" hidden>
            {!! $protocol !!}
            {{--<div class="bottom-button">--}}
                {{--<div class="disagree">不同意</div>--}}
                {{--<div class="agree">同意</div>--}}
            {{--</div>--}}
        </div>

    {{--</div>--}}

</div>

</body>

{{--<script src="{{asset('js/jquery.min.js')}}"></script>--}}
<script src="//code.jquery.com/jquery-1.9.1.min.js"></script>
<script src="{{asset('js/layer/layer.js')}}"></script>
<script>
    $(document).ready(function () {
{{--        localStorage.setItem('remobile','{{$remobile}}');--}}
{{--var htmlP = '{{url('api/common/getWebProtocol')}}';--}}
        function layerOPen() {

            layer.open({
                type: 1,
                title: ' ',
                shadeClose: true,
                closeBtn:1,
                shade: 0.8,
                area: ['80%', '80%'],
                content: $('#hid-text').html(),
                btn:['同意','不同意'],
                btnAlign:'c',
                skin: 'demo-class',
                btn1:function(index,layero){
                    var _self = $('.box img');
                    _self.attr('class','yes');
                    _self.attr('src',"{{asset('images/yes.png')}}");
                    layer.close(index);
                },
                btn2:function(index,layero){
                    var _self = $('.box img');
                    _self.attr('class','no');
                    _self.attr('src',"{{asset('images/no.png')}}");
                },
                success : function() {
                    $(".layui-layer-page").css("z-index", "198910151");
                },
                end : function() {
                    $(".layui-layer-shade").css("display","none");
                }


            });
        }
        function layerMsg(str) {
            layer.msg('<span style="font-size: 25px;">'+str+'</span>', {area:['200px', '60px']});
        }
        $('.box-size').click(function () {
            layerOPen();
        });

        var remobile = '{{$remobile}}';
        var t = 60;
        var tip = null;
        $('.code').click(function () {
            var mobile = $('#mobile').val();
            if (mobile.length == 0) {
                layerMsg('请输入手机号');
                return false;
            }

            if (tip == null) {

                $.ajax({
                    type: "POST",
                    url: "{{asset('/api/common/sendMsg')}}",
                    data: {
                        'u_mobile': mobile,
                        'type': 1
                    },
                    dataType: "json",
                    success: function (res) {
                        if (res.code == 1) {
                            //成功t
                            tip = 0;
                            intervalId = setInterval(function () {
                                $('.code').html('剩余' + t + '秒');
                                t--;
                                if (t <= 0) {
                                    tip = null;
                                    clearInterval(intervalId);
                                    $('.tip').html('获取验证码');
                                }
                            }, 1000);
                        } else {
                            //失败
                        }
                    }

                });
            }

        });
        //同意协议
        $('.box img').click(function () {
            var _self = $(this);
            var name = _self.attr('class');
            if(name == 'no'){
                layerOPen();

            }else if(name == 'yes'){
                _self.attr('class','no');
                _self.attr('src',"{{asset('images/no.png')}}");
            }
        });

        //注册
        $('.submit').click(function () {
            var mobile = $('#mobile').val();
            var msg = $('#msg').val();
            var agree = $('.box img').attr('class');
            var username = $('#username').val();
            var password = $('#password').val();

            if (username.length == 0){
                layerMsg('请输入用户名');
                return false;
            }
            if (password.length == 0){
                layerMsg('请输入密码');
                return false;
            }
            if (mobile.length == 0){
                layerMsg('请输入手机号');
                return false;
            }
            if (msg.length == 0){
                layerMsg('请输入验证码');
                return false;
            }

            if (agree == 'no'){
                layerMsg('请同意注册协议');
                return false;
            }

            $.ajax({
                type:"POST",
                url:"{{url('/api/web/register')}}",
                data:{
                    're_mobile':remobile,
                    'u_mobile':mobile,
                    'username':username,
                    'password':password,
                    'msg':msg
                },
                dataType:'json',
                success:function (res) {
                    if (res.code == 1) {
                        //成功
                       layerMsg('注册成功');
                       location.href = "{{asset('/api/share/download')}}";
                    } else {
                        //失败
                        layerMsg(res.errorMsg);
                    }

                }
            });

        });
    });
</script>


</html>
