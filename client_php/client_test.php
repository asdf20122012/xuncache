<?php
    include "xuncache.class.php";

    $xuncache = new xuncache();
    //数组操作(仅支持二位数组)

        $array['name']    =  "xuncache";
        $array['num']     =  1;
        $array['version'] =  "beta";
        //增加数组
        //$status = $xuncache->key("array")->add($array);
        $array = $xuncache->key("array")->index(5)->find();
        dump($array);
        //bool(true)

        //查找数组
        //$array = $xuncache->key("array")->find();
        //dump($array);
        /*  array(2) {
        *      ["name"] => string(8) "xuncache"
        *      ["version"] => string(3) "beta"
        *  }
        */

        //删除数组
        //$status = $xuncache->key("array")->del();
        //dump($status);
        //bool(true)

    //获取xuncache信息
        //$info = $xuncache->info();
        //dump($info);
        
        /*
        *   array(3) {
        *       ["keys"] => int(0)
        *       ["total_commands"] => int(10)
        *       ["version"] => string(3) "0.5"
        *   }
        */
 ?>
