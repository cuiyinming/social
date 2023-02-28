## 系统说明
一款免费开源的社交系统软件，主要支持的应用是类似于小圈，面具公园，糖果空间这样的运营模式，采用laravel5.8 + mysql8.0 + elasticsearch 6.8 + redis6.0 + docker + 队列 ，支持负载均衡和平行扩展分库分表，所有非必要的实时处理业务均放在了异步队列进行处理，提高了应用的响应速度和用户体验。500万以下的用户体量在硬件支持到位的情况下基本可以实现300毫秒以内的响应。所有用户搜索和查询均通过es 和 redis 实现，mysql 做固定话存储。支持开源的改造和二次发布，适用于婚恋，社交类型的应用用作服务端，已经集成了完整的苹果支付和微信支付宝支付，拥有完整的每日报表，事件上报接口，同时还包含了代理的分成和独立的代理管理后台接口。
## 应用说明

本系统已经在文件中对重要的秘钥等信息进行了脱敏处理，要运行起来需要注册响应的服务信息，并填写秘钥进系统对应的位置。

* 需要注册的`服务`主要包含：
    * 性能尚可的服务器一台或者多台。
    * OSS云存储服务
    * CDN 资源内容分发
    * 域名
    * 负载均衡服务器，自建或者购买现成的均可
    * redis 服务，自建或者购买云服务均可
    * mysql 服务，自建或者购买rds 均可
    * elasticsearch 服务，购买现成的服务或者自建均可，有现成的docker 镜像，可以用docker搭建，配置更加简单，需要配置ik分词组件。
    * 本项目的im 使用的是融云的im 服务，并非自建，主要注册融云的账号，并把秘钥信息填写在config 下的配置项中。
    * 本系统的推送使用的是极光的推送，并包含了极光的手机号码一键登陆功能，需要配置秘钥信息，并需要配置`公钥`到系统中以实现手机号的一键登陆功能。
    * 阿里云`内容安全`的鉴黄服务。
    * 阿里云 `金融级人脸活体验证`，用于实现对用户的实人认证。


### 定时备份
系统根目录的db.sh 是一个定时备份数据库的shell 脚本，需要在里面配置自己的数据库信息并挂载定时任务，即可实现定时清理陈旧数据库和备份新数据库的功能。

### 队列
本项目使用了很多队列处理的逻辑，比如苹果的下单支付，注册完成后的任务下发，站内信等业务的处理，一些非必须实时处理的业务基本都是通过异步队列处理，能够较为有效的提高接口的访问效率，需要您注意的是 队列采用的是 laravel 自带的队列，需要挂载`supervisor`使用，当然您也可以改造为 rabbitmq 这样的二次确认队列来做更加严谨的处理。

### 内容审核及鉴黄
为了保证系统中内容的审核安全，本系统接入了阿里云的内容安全审核，对于上传到服务器或者是oss 的图片会进行审核后在上传，最大限度的保证了系统中内容的安全。对于视频的审核，系统采用了抽帧检测的方法，对于视频，分别抽取第1,2,3,4,5,6,7...的视频帧进项检测，如果没问题才予以通过。对于文本也采用了ai 审核接口，最大限度的保证文本内容的安全。同时为了防止机器审核漏审或者误判的情况，系统中用户的所有关键操作，均会在用户日志中体现出来，可以通过人工二次审核的方法杜绝所有对平台不利的信息或图片。

### 报表
系统通过定时任务对每日报表进行了详细的统计，便于您清楚的了解自己项目的情况，包括了日注册，设备分布，市场分布情况，性别分布情况，付费类别分布，付费比例，每日arpu,每日dau,客户端用户群体分布，客户端版本分布等信息。

### 更多案例及演示请移步至：
http://www.fletter.cn/

### 演示地址
* 地址 :http://47.108.162.237/manager/
  * 账号：testabcd
  * 密码：qq112233
  * 安全码：qq112233

##关于作者

```
  nickName  : "Momo",
  QQ : 1825934566
  微信 : everthink
```

### 更过说明：
如果你有相关的社交系统需要开发，也可以联系我定制，只要说出你的需求就可以了哈，保质保量哦！`QQ : 1825934566`

### 后端截图
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/1.png)

![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/2.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/3.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/4.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/5.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/6.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/7.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/8.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/9.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/10.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/11.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/12.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/13.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/14.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/15.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/16.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/17.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/18.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/19.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/20.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/21.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/22.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0411/23.png)

### 前端部分案例【案例1】
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0528/1.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0528/2.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0528/3.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0528/4.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0528/5.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0528/6.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0528/7.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0528/8.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0528/9.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0528/10.png)

### 前端部分案例【案例2】
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0530/1.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0530/2.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0530/3.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0530/4.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0530/5.png)
![mahua](https://youmicp.oss-cn-hangzhou.aliyuncs.com/album/2022/0530/6.png)
