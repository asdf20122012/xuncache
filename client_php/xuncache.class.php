<?php

class xuncache {
	/**
     +------------------------------------------------------------------------------
     * xuncache模型类 + Litez Tcp协议
     +------------------------------------------------------------------------------
     * @author    sun8911879 <joijoi360@gmail.com>
     * @version   $Id: xuncache.class.php 2013-09-26 22:54:00 sun8911879 $
     +------------------------------------------------------------------------------
    */
	private $send_pack = array();
	private $send_over;
	private $tmp_buffio_cap = 128;

    //连接IP
    private $addr = "127.0.0.1";
    //连接端口
    private $port = "3351";
    //连接密码
    private $password = "";
    // 调试模式
    private $debug = true;
    // 查询表达式参数
    private $options = array();
	/**
     *----------------------------------------------------------
     * 架构函数
     * 取得TCP类的实例对象
     *----------------------------------------------------------
     * @param mixed $connection TCP连接信息
     *----------------------------------------------------------
     * @access public
     *----------------------------------------------------------
     */
    public function __construct() {
        $this->send_pack["v"] = 0.1;
		$this->send_pack["pact"] = "litez";
		$this->send_pack["type"] = "msg";
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $status = @socket_connect($this->socket,$this->addr,$this->port);
        if(!$status){
        	return false;
        }
    }

    /**
     *----------------------------------------------------------
     * 利用__call方法实现一些特殊的Model方法
     *----------------------------------------------------------
     * @access public
     *----------------------------------------------------------
     * @param string $method 方法名称
     * @param array $args 调用参数
     *----------------------------------------------------------
     * @return mixed
     *----------------------------------------------------------
     */
    public function __call($method,$args) {
        $method = strtolower($method);
        // 连贯操作的实现
        if(in_array($method,array('key','index'),true)) {
            $this->options[$method] = $args[0];
            return $this;
        }else{
            return false;
        }
    }

    /**
     *----------------------------------------------------------
     * 添加数据
     *----------------------------------------------------------
     * @access public
     *----------------------------------------------------------
     * @param mixed $options 表达式参数
     *----------------------------------------------------------
     * @return mixed
     *----------------------------------------------------------
     */
    public function add($array){
        $send['Pass'] = $this->password;
        $send['key'] = $this->options['key'];
        $send['Protocol'] = "push";
        $send['index'] = @$this->options['index'];
        $send['data'] = $array;
        $array = json_encode($send);
        $this->Write($array);
        $accept = $this->Read();
        $accept = json_decode($accept,true);
        //状态判断
        if(@$accept["Errors"] == true && $this->debug == true){
            $this->customError("connect",@$accept["Point"]);
        }elseif(@$accept["Errors"] == true){
            return false;
        }
        return @$accept["Id"];
    }

    /**
     *----------------------------------------------------------
     * 查找单条数据
     *----------------------------------------------------------
     * @access public
     *----------------------------------------------------------
     * @param mixed $options 表达式参数
     *----------------------------------------------------------
     * @return mixed
     *----------------------------------------------------------
     */
    public function find(){
        $send['Pass'] = $this->password;
        $send['key'] = $this->options['key'];
        $send['index'] = $this->options['index'];
        $send['Protocol'] = "find";
        $array = json_encode($send);
        $this->Write($array);
        $accept = $this->Read();
        dump($accept);
        $accept = json_decode($accept,true);
        //状态判断
        if(@$accept["Errors"] == true && $this->debug == true){
            $this->customError("connect",@$accept["Point"]);
        }elseif(@$accept["Errors"] == true){
            return false;
        }
        return @$accept["Id"];
    }

    private function customError($errno, $errstr){ 
        echo "<b>Error:</b> [$errno] $errstr<br />";
        exit();
    }

    //Litez
	private function Write($msg){
		$this->send_pack["len"] = strlen($msg);
		$send_pack = json_encode($this->send_pack).":EOF;";
		@socket_write($this->socket, $send_pack);
		@socket_write($this->socket, $msg);
	}

	private function Read(){
		$pack = $this->head_pack();
		if(!$pack){
			return false;
		}
		//判断协议
		if($pack["pact"] != $this->send_pack["pact"]){
			return false;
		}
		if($pack["v"] != $this->send_pack["v"]){
			return false;
		}
		if($pack["len"] < 1){
			return false;
		}
		//补全之前接收到byte
		$buffio_len = strlen($this->send_over);
		$buffio = null;
		$buffio = $this->send_over;
		$this->send_over = null;
		if($buffio_len >= $pack["len"]){
			return substr($buffio,0,$pack["len"]);
		}
		if($pack["len"] < $this->tmp_buffio_cap){
			$this->tmp_buffio_cap = $pack["len"];
		}
		while($head_pack_tmp = @socket_read($this->socket, $this->tmp_buffio_cap, PHP_BINARY_READ)){
			//读取追加字节
			$buffio .= $head_pack_tmp;
			if(strlen($buffio)>=$pack["len"]){
				//处理过长字符串
				$buffio_msg = substr($buffio,0,$pack["len"]);
				//冗余到下个通道
				$this->send_over = null;
				$this->send_over = substr($buffio,$pack["len"]);
				break;
			}
		}

		return $buffio_msg;
	}

	private function Close(){
		return socket_close($this->socket);
	}

	private function head_pack(){
		$buffio = null;
		//冗余上一次收到的byte
		$buffio = $this->send_over;
		$this->send_over = null;
		//头部协议获取
		while($head_pack_tmp = @socket_read($this->socket, 128, PHP_BINARY_READ)){
			//读取追加字节
			$buffio .= $head_pack_tmp;
			$pack_len = strpos($buffio,":EOF;");
			if($pack_len > 1){
				$head_pack = substr($buffio,0,$pack_len);
				$this->send_over = substr($buffio,$pack_len+5);
				break;
			}
		}
		if(!$head_pack){
			return false;
		}
		return json_decode($head_pack,true);

	}

}

    /**
     *----------------------------------------------------------
     * 浏览器友好的变量输出
     *----------------------------------------------------------
     * 此函数来自thinkphp
     *----------------------------------------------------------
     */
    function dump($var, $echo=true, $label=null, $strict=true) {
        $label = ($label === null) ? '' : rtrim($label) . ' ';
        if (!$strict) {
            if (ini_get('html_errors')) {
                $output = print_r($var, true);
                $output = "<pre>" . $label . htmlspecialchars($output, ENT_QUOTES) . "</pre>";
            } else {
                $output = $label . print_r($var, true);
            }
        } else {
            ob_start();
            var_dump($var);
            $output = ob_get_clean();
            if (!extension_loaded('xdebug')) {
                $output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);
                $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
            }
        }
        if ($echo) {
            echo($output);
            return null;
        }else
            return $output;
    }
?>