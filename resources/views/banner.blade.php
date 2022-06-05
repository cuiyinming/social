<!DOCTYPE html>
<html>
<head>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport">
    <title>{{$title}}</title>
</head>
<style>
    img {
        width: 100%;
        display: inline-block;
        margin-top: 20px;
        border-radius: 5px;
    }

    body {
        background: #191919;
    }

    .main {
        width: 96%;
        margin-left: 2%;
        color: #191919;
        background: #191919;
    }

    .text {
        padding: 20px 15px;
        text-indent: 2em;
        line-height: 30px;
        font-size: 14px;
        margin: 20px auto;
        background: #fff;
        border-radius: 5px;
        text-indent: 2em;
    }

</style>
<body>
<div class="main">
    <div class="mid-info">
        <div class="mid-info-box">
            <img src="{{$image_url}}" alt="{{$title}}">
            <div class="text">
                {{$cont}}
            </div>
        </div>
    </div>
</div>
</body>
</html>
