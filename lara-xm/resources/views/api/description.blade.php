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
        p{
            padding: 0;
            margin:  0 0 -5px 0;
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

</div>
</body>
</html>