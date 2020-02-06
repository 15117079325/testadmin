<!DOCTYPE HTML>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <title></title>
    <style type="text/css" media="screen">
        body{
            margin: 0;
            padding: 0;
        }
        img{
            width:100%;
        }
        .empty-p{
            margin:0 auto;
            text-align: center;
        }
        .bottom-button div{
            color: #fff;
            width: 50%;
            height: 70px;
            bottom: 0px;
            line-height: 70px;
            text-align: center;
        }
        .disagree{
            position: fixed;
            left: 0px;
            background-color: #ccc;
        }
        .agree{
            position: fixed;
            right: 0px;
            background-color: #6d83de;
        }
    </style>
</head>
<body>
<div class="employer_term">
    @if(empty($des))
        <p class="empty-p">空空如也！~~</p>
    @else
        {!! $des !!}
    @endif
    <div class="bottom-button">
        <div class="disagree">不同意</div>
        <div class="agree">同意</div>
    </div>
</div>
</body>
<script src="{{asset('js/jquery.min.js')}}"></script>
<script>
    $(document).ready(function () {
        var remobile = localStorage.getItem('remobile');

        $('.agree').click(function () {
            localStorage.setItem('agree','1');
            location.href = '/api/share/url?remobile='+remobile;
        });
        $('.disagree').click(function () {
            location.href = '/api/share/url?remobile='+remobile;
        });
    });

</script>

</html>