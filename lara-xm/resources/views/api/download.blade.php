<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>下载页面</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style lang="">
        * {
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: 100% !important;

        }
        .download {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background-color: #fff;

        }
        .download__bg {
            width: 100%;
            height: 100%;
        }
        .download__logo {
            position: absolute;
            top: 77%;
            width: 80px;
            left: 50%;
            margin-left: -40px;
        }
        .download__btn {
            width: 8.6rem;
            height: 2.3rem;
            display: block;
            border: 1px solid #fff;
            border-radius: 4px;
            position: absolute;
            top: 43%;
            left: 50%;
            margin-left: -4.3rem;
            line-height: 2.3rem;
            color: #fff;
            text-align: center;
            text-decoration: none;
        }
        .download__btn + .download__btn {
            margin-top: 60px;
        }
        .download__ios {
            width: 16px;
            height: 18px;
            background: url({{asset('images/apple-icon.png')}}) no-repeat;
            background-size: 100% 100%;
            display: inline-block;
            margin-right: 8px;
        }
        .download__android {
            width: 16px;
            height: 18px;
            background: url({{asset('images/android-icon.png')}}) no-repeat;
            background-size: 100% 100%;
            display: inline-block;
            margin-right: 8px;
        }
    </style>
</head>
<body>
<div class="download">
    <img src="{{asset('images/screen-bg.jpeg')}}" alt="" class="download__bg">
    <img src="{{asset('images/logo-word.png')}}" alt="" class="download__logo">
    <a href="{{$ios_url}}" class="download__btn">
        <span class="download__ios"></span>IOS下载
    </a>
    <a href="{{$android_url}}" class="download__btn">
        <span class="download__android"></span>Android下载
    </a>
</div>
</body>
</html>