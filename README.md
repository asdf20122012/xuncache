## xuncache
========
xuncache 是免费开源的NOSQL(内存数据库) 采用golang开发,简单易用而且 功能强大(就算新手也完全胜任)、性能卓越能轻松处理海量数据,可用于缓存系统.

目前版本 version 0.2.5

前期它是活跃的 更新很迅速

version 1.0版本前 作者不推荐用于生产环境

采用json协议 socket通信 --后期打算用bson

## 目前功能
========
-增加or设置

-查找数据

-删除数据

-暂不支持key过期操作

支持 php 客户端 
## php代码示例
========

	$xuncache = new xuncache();
    //添加数据
    $status = $xuncache->key("xuncache")->add("xuncache");
    dump($status);
    //string(8) "xuncache"
    
    //查找数据
    $cache = $xuncache->key("xuncache")->find();
    dump($cache);
    //bool(true)

    //删除数据
    $status = $xuncache->key("xuncache")->del();
    dump($status);
    //bool(true)
	
## 性能测试(仅代表本机速度)
###不是专业测试 如果你有更好的测试结果欢迎提交

![](images/property.png?raw=true)

## 关于
- by [孙彦欣](http://weibo.com/sun8911879)
-    [更新日志](https://github.com/sun8911879/xuncache/blob/master/UPDATE.md)
- LICENSE: under the [BSD](https://github.com/sun8911879/xuncache/blob/master/LICENSE-BSD.md) License