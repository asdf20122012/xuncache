<?php
    include "xuncache.class.php";

    $xuncache = new xuncache();
    //添加数据
    $status = $xuncache->key("xuncache")->add("xuncache");
    dump($status);
    //查找数据
    $cache = $xuncache->key("xuncache")->find();
    dump($cache);
    //删除数据
    $status = $xuncache->key("xuncache")->del();
    dump($status);

 ?>
