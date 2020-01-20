# 简介

基于PHP实现的简易方正教务管理系统爬虫, 包括了登录学校WebVPN功能, 主要适用于北京工业大学.

# 安装

使用 `composer` 进行安装：
`composer require leoleoasd/zf_spider`

> 注意：请先安装 `composer`

# Example

```php
<?php
require_once "./vendor/autoload.php";


use GuzzleHttp\Client as HttpClient;

$client = new \ZfSpider\Client(['stu_id' => 'xxx', 'stu_pwd' => 'xxx']);

var_dump($client->login_vpn('xxx',"xxx"));
var_dump(serialize($client->getCookieJar()));
//$client->setCookieJar(unserialize(""));
$client->login();

var_dump($client->getExams());
var_dump($client->getExams('2019-2020','1'));
var_dump($client->getGrade());
var_dump($client->getSchedule());
var_dump($client->getSchedule('2019-2020','1'));

``` 

# API
## login_vpn
```php
$client->login_vpn("学号", "密码");
// 返回$this, 可链式调用.
```

## test_vpn
```php
if($client->test_vpn()){
    foo();
}
// 返回一个Bool, 代表当前是否已经登录了vpn.
```

## login
```php
$client->login();
// 登录教务系统.
```

## getCookieJar / setCookieJar
```php
$client->login_vpn("xxx","xxx");
$jar = $client->getCookieJar();


$jar = unserialize("");
$client->setCookieJar($jar);
// 设置cookieJar和获取cookieJar. 可登录vpn后缓存.
```

## getGrade

```php

$client->getGrade();
// 获取所有已修课程成绩.
/*
[
    {
        "year":"2019-2020",
        "term":"1",
        "courseId":"0006794",
        "courseName":"大学生心理适应指导",
        "courseType":"校选修课",
        "courseBelong":"第二课堂",
        "courseCredit":"1.0",
        "gradePoint":"4.00",
        "score":"100",
        "minorMark":"0", // 辅修标记
        "makeUpScore":" ", // 补考成绩
        "retakeMark":"0", // 重修标记
        "retakeScore":" ", // 重修成绩
        "academy":"学生发展指导中心",
        "remark":" "
    },
    ...
]
*/
```

## getExams
```php
// 获取考试信息.
var_dump($client->getExams());
var_dump($client->getExams('2019-2020','1'));
/*
[
    {
        "courseId":"(2019-2020-1)-0004312-08625-3",
        "courseName":"中国近现代史纲要",
        "name":"xxx",
        "dateTime":"xxx",
        "classroom":"xxx",
        "type":" ",
        "seat":"xxx",
        "campus":"通州校区"
    },
    ...
]
 */
```

## getSchedule
```php
// 获取课表信息
var_dump($client->getSchedule());
var_dump($client->getSchedule('2019-2020','1'));
```

# 鸣谢

感谢 [西大望路东锤子研究所](https://github.com/BJUT-hammer) 的 [bjuthelper](https://github.com/BJUT-hammer/bjuthelper), 为本项目提供了很多的参考.

本项目的初始代码 fork 自 [Lcrawl](https://github.com/lndj/Lcrawl). 感谢 [lndj](https://github.com/lndj/) 对于方正教务系统的研究以及编写了对应的PHP爬虫库.

# License
This project is licensed under **MIT license**.

For more information, checkout LICENSE.